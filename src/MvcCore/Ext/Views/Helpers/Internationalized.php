<?php

/**
 * MvcCore
 *
 * This source file is subject to the BSD 3 License
 * For the full copyright and license information, please view
 * the LICENSE.md file that are distributed with this source code.
 *
 * @copyright	Copyright (c) 2016 Tom Flídr (https://github.com/mvccore/mvccore)
 * @license		https://mvccore.github.io/docs/mvccore/5.0.0/LICENCE.md
 */

namespace MvcCore\Ext\Views\Helpers;

/**
 * Responsibility - abstract class to process date, number or money formating by `Intl` extension or by locale formating conventions.
 * - Formating processed by `Intl` extension if installed or (automaticly) configured system locale settings.
 * - System locale settings automaticly configured by request language and request locale.
 * - Encoding result string to always return it in response encoding, in UTF-8 by default.
 */
abstract class Internationalized extends \MvcCore\Ext\Views\Helpers\AbstractHelper
{
	/**
	 * MvcCore Extension - View Helper - Assets - version:
	 * Comparation by PHP function version_compare();
	 * @see http://php.net/manual/en/function.version-compare.php
	 */
	const VERSION = '5.0.0-alpha';

	/**
	 * Boolean about if `Intl` (PHP Internationalization Functions) has installed.
	 * @see http://php.net/manual/en/book.intl.php
	 * @var bool|NULL
	 */
	protected $intlExtensionFormating = NULL;

	/**
	 * Automaticly assigned language from controller request object.
	 * @var string|NULL
	 */
	protected $lang = NULL;

	/**
	 * Automaticly assigned locale from controller request object.
	 * @var string|NULL
	 */
	protected $locale = NULL;

	/**
	 * Automaticly assigned lang and locale combination from controller request object.
	 * @var string|NULL
	 */
	protected $langAndLocale = NULL;

	/**
	 * Storage with `Intl` datetime and number formater instances.
	 * @var \IntlDateFormatter[]|\NumberFormatter[]
	 */
	protected $intlFormaters = [];

	/**
	 * Default encoding to use if there is no response encoding configured.
	 * @var string
	 */
	protected $defaultEncoding = 'UTF-8';

	/**
	 * System `setlocale()` category to set up system locale automaticly in `SetView()` method.
	 * @var \int[]
	 */
	protected $localeCategories = [LC_ALL];

	/**
	 * `TRUE` if there necessary to process any `iconv()` conversion from `strftime()`
	 * or from `number_format()` etc... results into view helper result rendered into
	 * application response. This variable is automaticly resolved in every `SetView()` call.
	 * @var bool|NULL
	 */
	protected $encodingConversion = NULL;

	/**
	 * System `setlocale()` encoding, automaticly configured by application request object.
	 * If there is no language and locale in request object, there is set UTF-8 by default.
	 * This variable is automaticly resolved in every `SetView()` call.
	 * @var string|NULL
	 */
	protected $systemEncoding = NULL;

	/**
	 * Target encoding, automaticly assigned from application response.
	 * If there is no response encoding, there is set UTF-8 by default.
	 * This variable is automaticly resolved in every `SetView()` call.
	 * @var string|NULL
	 */
	protected $responseEncoding = NULL;

	/**
	 * Default language and locale used for `Intl` formating fallback,
	 * when is not possible to configure system locale value
	 * and when there is necessary to define some default formating rules.
	 * @var string[]
	 */
	protected $defaultLangAndLocale = ['en', 'US'];

	/**
	 * Create new helper instance, set boolean about `Intl` extension formating by loaded extension.
	 * @return \MvcCore\Ext\Views\Helpers\Internationalized
	 */
	public function __construct () {
		$this->intlExtensionFormating = extension_loaded('Intl');
	}

	/**
	 * Set `TRUE` if you want to use explicitly `Intl` extension formating
	 * (PHP Internationalization Functions) or set `FALSE` if you want to use explicitly
	 * `strftime()`, `number_format()`, `money_format()` etc... old fashion functions formating.
	 * @see http://php.net/manual/en/book.intl.php
	 * @see http://php.net/strftime
	 * @see http://php.net/number_format
	 * @see http://php.net/money_format
	 * @param bool $intlExtensionFormating `TRUE` by default.
	 * @return \MvcCore\Ext\Views\Helpers\Internationalized
	 */
	public function & SetIntlExtensionFormating ($intlExtensionFormating = TRUE) {
		$this->intlExtensionFormating = $intlExtensionFormating;
		return $this;
	}

	/**
	 * Set language code and locale (teritory) code manualy.
	 * Use this function olny if there is no language and locale codes presented in request object.
	 * @param string $lang `"en" | "de" ...`
	 * @param string $locale `"US" | "GB" ...`
	 * @return \MvcCore\Ext\Views\Helpers\Internationalized
	 */
	public function & SetLangAndLocale ($lang = NULL, $locale = NULL) {
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
	 * and if there is necessary to use `strftime()` or `number_format()` etc.. formating as a fallback.
	 * @param string $encoding
	 * @return \MvcCore\Ext\Views\Helpers\Internationalized
	 */
	public function & SetDefaultEncoding ($encoding = 'UTF-8') {
		$this->defaultEncoding = strtoupper($encoding);
		return $this;
	}

	/**
	 * Set default language and locale used for `Intl` formating fallback,
	 * when is not possible to configure system locale value
	 * and when there is necessary to define some default formating rules.
	 * @param string[] $defaultLangAndLocale
	 * @return \MvcCore\Ext\Views\Helpers\Internationalized
	 */
	public function & SetDefaultLangAndLocale ($defaultLangAndLocale = ['en', 'US']) {
		$this->defaultLangAndLocale = $defaultLangAndLocale;
		return $this;
	}

	/**
	 * Set up view properties and language and locale by request object in every view rendering change.
	 * @param \MvcCore\IView $view
	 * @return \MvcCore\Ext\Views\Helpers\Internationalized
	 */
	public function & SetView (\MvcCore\IView & $view) {
		parent::SetView($view);
		return $this->SetLangAndLocale($this->request->GetLang(), $this->request->GetLocale());
	}

	/**
	 * Try to define system locale and it's system locale conversion information.
	 * This function is used only if `Intl` extension is not installed
	 * and if there is necessary to use `strftime()` or `number_format()` etc.. formating as a fallback.
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
	 * and if there is necessary to use `strftime()` or `number_format()` etc.. formating as a fallback.
	 * @param string $str
	 * @return string
	 */
	protected function encode (& $str = '') {
		return $this->encodingConversion
			? iconv($this->systemEncoding, $this->responseEncoding, $str)
			: $str;
	}
}
