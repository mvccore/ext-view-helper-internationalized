<?php

/**
 * MvcCore
 *
 * This source file is subject to the BSD 3 License
 * For the full copyright and license information, please view
 * the LICENSE.md file that are distributed with this source code.
 *
 * @copyright	Copyright (c) 2016 Tom Flidr (https://github.com/mvccore)
 * @license		https://mvccore.github.io/docs/mvccore/5.0.0/LICENSE.md
 */

namespace MvcCore\Ext\Views\Helpers;

use \MvcCore\Ext\Tools;

/**
 * Responsibility - abstract class to process date, number or money formatting by `Intl` extension or by locale formatting conventions.
 * - Formatting processed by `Intl` extension if installed or (automatically) configured system locale settings.
 * - System locale settings automatically configured by request language and request locale.
 * - Encoding result string to always return it in response encoding, in UTF-8 by default.
 * @method \MvcCore\Ext\Views\Helpers\InternationalizedHelper GetInstance()
 */
abstract class InternationalizedHelper extends \MvcCore\Ext\Views\Helpers\AbstractHelper {

	/**
	 * MvcCore Extension - View Helper - Assets - version:
	 * Comparison by PHP function version_compare();
	 * @see http://php.net/manual/en/function.version-compare.php
	 */
	const VERSION = '5.0.1';

	/**
	 * Boolean about if `Intl` (PHP Internationalization Functions) has installed.
	 * @see http://php.net/manual/en/book.intl.php
	 * @var bool|NULL
	 */
	protected $intlExtensionFormatting = NULL;

	/**
	 * Automatically assigned language from controller request object.
	 * @var string|NULL
	 */
	protected $lang = NULL;

	/**
	 * Automatically assigned locale from controller request object.
	 * @var string|NULL
	 */
	protected $locale = NULL;

	/**
	 * Automatically assigned lang and locale combination from controller request object.
	 * @var string|NULL
	 */
	protected $langAndLocale = NULL;

	/**
	 * Storage with `Intl` datetime and number formatter instances.
	 * @var \IntlDateFormatter[]|\NumberFormatter[]
	 */
	protected $intlFormatters = [];

	/**
	 * Default encoding to use if there is no response encoding configured.
	 * @var string
	 */
	protected $defaultEncoding = 'UTF-8';

	/**
	 * System `setlocale()` category to set up system locale automatically in `SetView()` method.
	 * @var \int[]
	 */
	protected $localeCategories = [LC_ALL];

	/**
	 * `TRUE` if there necessary to process any `iconv()` conversion from `strftime()`
	 * or from `number_format()` etc... results into view helper result rendered into
	 * application response. This variable is automatically resolved in every `SetView()` call.
	 * @var bool|NULL
	 */
	protected $encodingConversion = NULL;

	/**
	 * System `setlocale()` encoding, automatically configured by application request object.
	 * If there is no language and locale in request object, there is set UTF-8 by default.
	 * This variable is automatically resolved in every `SetView()` call.
	 * @var string|NULL
	 */
	protected $systemEncoding = NULL;

	/**
	 * Target encoding, automatically assigned from application response.
	 * If there is no response encoding, there is set UTF-8 by default.
	 * This variable is automatically resolved in every `SetView()` call.
	 * @var string|NULL
	 */
	protected $responseEncoding = NULL;

	/**
	 * Default language and locale used for `Intl` formatting fallback,
	 * when is not possible to configure system locale value
	 * and when there is necessary to define some default formatting rules.
	 * @var string[]
	 */
	protected $defaultLangAndLocale = ['en', 'US'];

	/**
	 * Create new helper instance, set boolean about `Intl` extension formatting by loaded extension.
	 * @return void
	 */
	public function __construct () {
		if ($this->intlExtensionFormatting === NULL)
			$this->intlExtensionFormatting = extension_loaded('Intl');
	}

	/**
	 * Set `TRUE` if you want to use explicitly `Intl` extension formatting
	 * (PHP Internationalization Functions) or set `FALSE` if you want to use explicitly
	 * `strftime()`, `number_format()`, `money_format()` etc... old fashion functions formatting.
	 * @see http://php.net/manual/en/book.intl.php
	 * @see http://php.net/strftime
	 * @see http://php.net/number_format
	 * @see http://php.net/money_format
	 * @param  bool $intlExtensionFormatting `TRUE` by default.
	 * @return \MvcCore\Ext\Views\Helpers\InternationalizedHelper
	 */
	public function SetIntlExtensionFormatting ($intlExtensionFormatting = TRUE) {
		$this->intlExtensionFormatting = $intlExtensionFormatting;
		return $this;
	}

	/**
	 * Set language code and locale (territory) code manually.
	 * Use this function only if there is no language and locale 
	 * codes presented in request object.
	 * @param  string $lang   `"en" | "de" ...`
	 * @param  string $locale `"US" | "GB" ...`
	 * @return \MvcCore\Ext\Views\Helpers\InternationalizedHelper
	 */
	public function SetLangAndLocale ($lang = NULL, $locale = NULL) {
		$this->lang = $lang;
		$this->locale = $locale;
		$this->encodingConversion = NULL;
		if ($this->lang !== NULL) {
			$this->langAndLocale = $this->lang;
			if ($this->locale !== NULL)
				$this->langAndLocale .= '_' . $this->locale;
		}
		return $this;
	}

	/**
	 * Set default encoding if there is no response encoding configured.
	 * Use this function is used only if `Intl` extension is not installed
	 * and if there is necessary to use `strftime()` or `number_format()` etc.. formatting as a fallback.
	 * @param  string $encoding
	 * @return \MvcCore\Ext\Views\Helpers\InternationalizedHelper
	 */
	public function SetDefaultEncoding ($encoding = 'UTF-8') {
		$this->defaultEncoding = strtoupper($encoding);
		return $this;
	}

	/**
	 * Set default language and locale used for `Intl` formatting fallback,
	 * when is not possible to configure system locale value
	 * and when there is necessary to define some default formatting rules.
	 * @param  string[] $defaultLangAndLocale
	 * @return \MvcCore\Ext\Views\Helpers\InternationalizedHelper
	 */
	public function SetDefaultLangAndLocale ($defaultLangAndLocale = ['en', 'US']) {
		$this->defaultLangAndLocale = $defaultLangAndLocale;
		return $this;
	}

	/**
	 * Set up view properties and language and locale by request object in every view rendering change.
	 * @param  \MvcCore\View $view
	 * @return \MvcCore\Ext\Views\Helpers\InternationalizedHelper
	 */
	public function SetView (\MvcCore\IView $view) {
		parent::SetView($view);
		return $this->SetLangAndLocale(
			$this->lang ?: $this->request->GetLang(), 
			$this->locale ?: $this->request->GetLocale()
		);
	}

	/**
	 * Try to define system locale and it's system locale conversion information.
	 * This function is used only if `Intl` extension is not installed
	 * and if there is necessary to use `strftime()` or `number_format()` etc.. formatting as a fallback.
	 * @return void
	 */
	protected function setUpSystemLocaleAndEncodings () {
		if ($this->responseEncoding === NULL) {
			$this->responseEncoding = $this->response ? $this->response->GetEncoding() : NULL;
			$this->responseEncoding = ($this->responseEncoding === NULL
				? $this->defaultEncoding
				: strtoupper($this->responseEncoding));
		}
		$this->systemEncoding = NULL;
		if ($this->lang !== NULL && $this->locale !== NULL) {
			$systemEncodings = [];
			foreach ($this->localeCategories as $localeCategory) {
				$newRawSystemLocaleValue = \MvcCore\Ext\Tools\Locale::SetLocale(
					$localeCategory,
					$this->langAndLocale . '.' . $this->responseEncoding
				);
				if ($newRawSystemLocaleValue === FALSE) continue;
				$systemParsedLocale = \MvcCore\Ext\Tools\Locale::GetLocale($localeCategory);
				if ($systemParsedLocale !== NULL && $systemParsedLocale->encoding !== NULL)
					$systemEncodings[] = $systemParsedLocale->encoding;
			}
			if ($systemEncodings && count($systemEncodings) == count($this->localeCategories))
				$this->systemEncoding = $systemEncodings[0];
		}
		$this->encodingConversion = (
			$this->systemEncoding !== $this->responseEncoding &&
			$this->systemEncoding !== NULL &&
			$this->responseEncoding !== NULL
		);
	}

	/**
	 * Encode given string from system encoding into response encoding if necessary.
	 * This function is used only if `Intl` extension is not installed
	 * and if there is necessary to use `strftime()` or `number_format()` etc.. 
	 * formatting as a fallback.
	 * @param  string $str
	 * @return string
	 */
	protected function encode ($str = '') {
		return $this->encodingConversion
			? iconv($this->systemEncoding, $this->responseEncoding, $str)
			: $str;
	}
}
