<?php

/**
 * This file is part of the Nette Framework.
 *
 * Copyright (c) 2004, 2010 David Grudl (http://davidgrudl.com)
 *
 * This source file is subject to the "Nette license", and/or
 * GPL license. For more information please see http://nette.org
 */

namespace Nette\Application;

use Nette,
	Nette\String;



/**
 * The bidirectional route is responsible for mapping
 * HTTP request to a PresenterRoute object for dispatch and vice-versa.
 *
 * @author     David Grudl
 */
class Route extends Nette\Object implements IRouter
{
	const PRESENTER_KEY = 'presenter';
	const MODULE_KEY = 'module';

	/** flag */
	const CASE_SENSITIVE = 256;

	/**#@+ @internal uri type */
	const HOST = 1;
	const PATH = 2;
	const RELATIVE = 3;
	/**#@-*/

	/**#@+ key used in {@link Route::$styles} or metadata {@link Route::__construct} */
	const VALUE = 'value';
	const PATTERN = 'pattern';
	const FILTER_IN = 'filterIn';
	const FILTER_OUT = 'filterOut';
	const FILTER_TABLE = 'filterTable';
	/**#@-*/

	/**#@+ @internal fixity types - how to handle default value? {@link Route::$metadata} */
	const OPTIONAL = 0;
	const PATH_OPTIONAL = 1;
	const CONSTANT = 2;
	/**#@-*/

	/** @var bool */
	public static $defaultFlags = 0;

	/** @var array */
	public static $styles = array(
		'#' => array( // default style for path parameters
			self::PATTERN => '[^/]+',
			self::FILTER_IN => 'rawurldecode',
			self::FILTER_OUT => 'rawurlencode',
		),
		'?#' => array( // default style for query parameters
		),
		'module' => array(
			self::PATTERN => '[a-z][a-z0-9.-]*',
			self::FILTER_IN => array(__CLASS__, 'path2presenter'),
			self::FILTER_OUT => array(__CLASS__, 'presenter2path'),
		),
		'presenter' => array(
			self::PATTERN => '[a-z][a-z0-9.-]*',
			self::FILTER_IN => array(__CLASS__, 'path2presenter'),
			self::FILTER_OUT => array(__CLASS__, 'presenter2path'),
		),
		'action' => array(
			self::PATTERN => '[a-z][a-z0-9-]*',
			self::FILTER_IN => array(__CLASS__, 'path2action'),
			self::FILTER_OUT => array(__CLASS__, 'action2path'),
		),
		'?module' => array(
		),
		'?presenter' => array(
		),
		'?action' => array(
		),
	);

	/** @var string */
	private $mask;

	/** @var array */
	private $sequence;

	/** @var string  regular expression pattern */
	private $re;

	/** @var array of [value & fixity, filterIn, filterOut] */
	private $metadata = array();

	/** @var array  */
	private $xlat;

	/** @var int HOST, PATH, RELATIVE */
	private $type;

	/** @var int */
	private $flags;



	/**
	 * @param  string  URL mask, e.g. '<presenter>/<action>/<id \d{1,3}>'
	 * @param  array   default values or metadata
	 * @param  int     flags
	 */
	public function __construct($mask, array $metadata = array(), $flags = 0)
	{
		$this->flags = $flags | self::$defaultFlags;
		$this->setMask($mask, $metadata);
	}



	/**
	 * Maps HTTP request to a PresenterRequest object.
	 * @param  Nette\Web\IHttpRequest
	 * @return PresenterRequest|NULL
	 */
	public function match(Nette\Web\IHttpRequest $httpRequest)
	{
		// combine with precedence: mask (params in URL-path), fixity, query, (post,) defaults

		// 1) URL MASK
		$uri = $httpRequest->getUri();

		if ($this->type === self::HOST) {
			$path = '//' . $uri->getHost() . $uri->getPath();

		} elseif ($this->type === self::RELATIVE) {
			$basePath = $uri->getBasePath();
			if (strncmp($uri->getPath(), $basePath, strlen($basePath)) !== 0) {
				return NULL;
			}
			$path = (string) substr($uri->getPath(), strlen($basePath));

		} else {
			$path = $uri->getPath();
		}

		if ($path !== '') {
			$path = rtrim($path, '/') . '/';
		}

		if (!$matches = String::match($path, $this->re)) {
			// stop, not matched
			return NULL;
		}

		// deletes numeric keys, restore '-' chars
		$params = array();
		foreach ($matches as $k => $v) {
			if (is_string($k) && $v !== '') {
				$params[str_replace('___', '-', $k)] = $v; // trick
			}
		}


		// 2) CONSTANT FIXITY
		foreach ($this->metadata as $name => $meta) {
			if (isset($params[$name])) {
				//$params[$name] = $this->flags & self::CASE_SENSITIVE === 0 ? strtolower($params[$name]) : */$params[$name]; // strtolower damages UTF-8

			} elseif (isset($meta['fixity']) && $meta['fixity'] !== self::OPTIONAL) {
				$params[$name] = NULL; // cannot be overwriten in 3) and detected by isset() in 4)
			}
		}


		// 3) QUERY
		if ($this->xlat) {
			$params += self::renameKeys($httpRequest->getQuery(), array_flip($this->xlat));
		} else {
			$params += $httpRequest->getQuery();
		}


		// 4) APPLY FILTERS & FIXITY
		foreach ($this->metadata as $name => $meta) {
			if (isset($params[$name])) {
				if (!is_scalar($params[$name])) {

				} elseif (isset($meta[self::FILTER_TABLE][$params[$name]])) { // applyies filterTable only to scalar parameters
					$params[$name] = $meta[self::FILTER_TABLE][$params[$name]];

				} elseif (isset($meta[self::FILTER_IN])) { // applyies filterIn only to scalar parameters
					$params[$name] = call_user_func($meta[self::FILTER_IN], (string) $params[$name]);
					if ($params[$name] === NULL && !isset($meta['fixity'])) {
						return NULL; // rejected by filter
					}
				}

			} elseif (isset($meta['fixity'])) {
				$params[$name] = $meta[self::VALUE];
			}
		}


		// 5) BUILD PresenterRequest
		if (!isset($params[self::PRESENTER_KEY])) {
			throw new \InvalidStateException('Missing presenter in route definition.');
		}
		if (isset($this->metadata[self::MODULE_KEY])) {
			if (!isset($params[self::MODULE_KEY])) {
				throw new \InvalidStateException('Missing module in route definition.');
			}
			$presenter = $params[self::MODULE_KEY] . ':' . $params[self::PRESENTER_KEY];
			unset($params[self::MODULE_KEY], $params[self::PRESENTER_KEY]);

		} else {
			$presenter = $params[self::PRESENTER_KEY];
			unset($params[self::PRESENTER_KEY]);
		}

		return new PresenterRequest(
			$presenter,
			$httpRequest->getMethod(),
			$params,
			$httpRequest->getPost(),
			$httpRequest->getFiles(),
			array(PresenterRequest::SECURED => $httpRequest->isSecured())
		);
	}



	/**
	 * Constructs absolute URL from PresenterRequest object.
	 * @param  PresenterRequest
	 * @param  Nette\Web\Uri
	 * @return string|NULL
	 */
	public function constructUrl(PresenterRequest $appRequest, Nette\Web\Uri $refUri)
	{
		if ($this->flags & self::ONE_WAY) {
			return NULL;
		}

		$params = $appRequest->getParams();
		$metadata = $this->metadata;

		$presenter = $appRequest->getPresenterName();
		$params[self::PRESENTER_KEY] = $presenter;

		if (isset($metadata[self::MODULE_KEY])) { // try split into module and [submodule:]presenter parts
			$module = $metadata[self::MODULE_KEY];
			if (isset($module['fixity']) && strncasecmp($presenter, $module[self::VALUE] . ':', strlen($module[self::VALUE]) + 1) === 0) {
				$a = strlen($module[self::VALUE]);
			} else {
				$a = strrpos($presenter, ':');
			}
			if ($a === FALSE) {
				$params[self::MODULE_KEY] = '';
			} else {
				$params[self::MODULE_KEY] = substr($presenter, 0, $a);
				$params[self::PRESENTER_KEY] = substr($presenter, $a + 1);
			}
		}

		foreach ($metadata as $name => $meta) {
			if (!isset($params[$name])) continue; // retains NULL values

			if (isset($meta['fixity'])) {
				if (is_scalar($params[$name]) && strcasecmp($params[$name], $meta[self::VALUE]) === 0) {
					// remove default values; NULL values are retain
					unset($params[$name]);
					continue;

				} elseif ($meta['fixity'] === self::CONSTANT) {
					return NULL; // missing or wrong parameter '$name'
				}
			}

			if (!is_scalar($params[$name])) {

			} elseif (isset($meta['filterTable2'][$params[$name]])) {
				$params[$name] = $meta['filterTable2'][$params[$name]];

			} elseif (isset($meta[self::FILTER_OUT])) {
				$params[$name] = call_user_func($meta[self::FILTER_OUT], $params[$name]);
			}

			if (isset($meta[self::PATTERN]) && !preg_match($meta[self::PATTERN], rawurldecode($params[$name]))) {
				return NULL; // pattern not match
			}
		}

		// compositing path
		$sequence = $this->sequence;
		$brackets = array();
		$required = 0;
		$uri = '';
		$i = count($sequence) - 1;
		do {
			$uri = $sequence[$i] . $uri;
			if ($i === 0) break;
			$i--;

			$name = $sequence[$i]; $i--; // parameter name

			if ($name === ']') { // opening optional part
				$brackets[] = $uri;

			} elseif ($name[0] === '[') { // closing optional part
				$tmp = array_pop($brackets);
				if ($required < count($brackets) + 1) { // is this level optional?
					if ($name !== '[!') { // and not "required"-optional
						$uri = $tmp;
					}
				} else {
					$required = count($brackets);
				}

			} elseif ($name[0] === '?') { // "foo" parameter
				continue;

			} elseif (isset($params[$name]) && $params[$name] != '') { // intentionally ==
				$required = count($brackets); // make this level required
				$uri = $params[$name] . $uri;
				unset($params[$name]);

			} elseif (isset($metadata[$name]['fixity'])) { // has default value?
				$uri = $metadata[$name]['defOut'] . $uri;

			} else {
				return NULL; // missing parameter '$name'
			}
		} while (TRUE);


		// build query string
		if ($this->xlat) {
			$params = self::renameKeys($params, $this->xlat);
		}

		$sep = ini_get('arg_separator.input');
		$query = http_build_query($params, '', $sep ? $sep[0] : '&');
		if ($query != '') $uri .= '?' . $query; // intentionally ==

		// absolutize path
		if ($this->type === self::RELATIVE) {
			$uri = '//' . $refUri->getAuthority() . $refUri->getBasePath() . $uri;

		} elseif ($this->type === self::PATH) {
			$uri = '//' . $refUri->getAuthority() . $uri;
		}

		if (strpos($uri, '//', 2) !== FALSE) {
			return NULL; // TODO: implement counterpart in match() ?
		}

		$uri = ($this->flags & self::SECURED ? 'https:' : 'http:') . $uri;

		return $uri;
	}



	/**
	 * Parse mask and array of default values; initializes object.
	 * @param  string
	 * @param  array
	 * @return void
	 */
	private function setMask($mask, array $metadata)
	{
		$this->mask = $mask;

		// detect '//host/path' vs. '/abs. path' vs. 'relative path'
		if (substr($mask, 0, 2) === '//') {
			$this->type = self::HOST;

		} elseif (substr($mask, 0, 1) === '/') {
			$this->type = self::PATH;

		} else {
			$this->type = self::RELATIVE;
		}

		foreach ($metadata as $name => $meta) {
			if (!is_array($meta)) {
				$metadata[$name] = array(self::VALUE => $meta, 'fixity' => self::CONSTANT);

			} elseif (array_key_exists(self::VALUE, $meta)) {
				$metadata[$name]['fixity'] = self::CONSTANT;
			}
		}

		// PARSE MASK
		$parts = String::split($mask, '/<([^># ]+) *([^>#]*)(#?[^>\[\]]*)>|(\[!?|\]|\s*\?.*)/'); // <parameter-name [pattern] [#class]> or [ or ] or ?...

		$this->xlat = array();
		$i = count($parts) - 1;

		// PARSE QUERY PART OF MASK
		if (isset($parts[$i - 1]) && substr(ltrim($parts[$i - 1]), 0, 1) === '?') {
			$matches = String::matchAll($parts[$i - 1], '/(?:([a-zA-Z0-9_.-]+)=)?<([^># ]+) *([^>#]*)(#?[^>]*)>/'); // name=<parameter-name [pattern][#class]>

			foreach ($matches as $match) {
				list(, $param, $name, $pattern, $class) = $match;  // $pattern is not used

				if ($class !== '') {
					if (!isset(self::$styles[$class])) {
						throw new \InvalidStateException("Parameter '$name' has '$class' flag, but Route::\$styles['$class'] is not set.");
					}
					$meta = self::$styles[$class];

				} elseif (isset(self::$styles['?' . $name])) {
					$meta = self::$styles['?' . $name];

				} else {
					$meta = self::$styles['?#'];
				}

				if (isset($metadata[$name])) {
					$meta = $metadata[$name] + $meta;
				}

				if (array_key_exists(self::VALUE, $meta)) {
					$meta['fixity'] = self::OPTIONAL;
				}

				unset($meta['pattern']);
				$meta['filterTable2'] = empty($meta[self::FILTER_TABLE]) ? NULL : array_flip($meta[self::FILTER_TABLE]);

				$metadata[$name] = $meta;
				if ($param !== '') {
					$this->xlat[$name] = $param;
				}
			}
			$i -= 5;
		}

		$brackets = 0; // optional level
		$re = '';
		$sequence = array();
		$autoOptional = array(0, 0); // strlen($re), count($sequence)
		do {
			array_unshift($sequence, $parts[$i]);
			$re = preg_quote($parts[$i], '#') . $re;
			if ($i === 0) break;
			$i--;

			$part = $parts[$i]; // [ or ]
			if ($part === '[' || $part === ']' || $part === '[!') {
				$brackets += $part[0] === '[' ? -1 : 1;
				if ($brackets < 0) {
					throw new \InvalidArgumentException("Unexpected '$part' in mask '$mask'.");
				}
				array_unshift($sequence, $part);
				$re = ($part[0] === '[' ? '(?:' : ')?') . $re;
				$i -= 4;
				continue;
			}

			$class = $parts[$i]; $i--; // validation class
			$pattern = trim($parts[$i]); $i--; // validation condition (as regexp)
			$name = $parts[$i]; $i--; // parameter name
			array_unshift($sequence, $name);

			if ($name[0] === '?') { // "foo" parameter
				$re = '(?:' . preg_quote(substr($name, 1), '#') . '|' . $pattern . ')' . $re;
				$sequence[1] = substr($name, 1) . $sequence[1];
				continue;
			}

			// check name (limitation by regexp)
			if (preg_match('#[^a-z0-9_-]#i', $name)) {
				throw new \InvalidArgumentException("Parameter name must be alphanumeric string due to limitations of PCRE, '$name' given.");
			}

			// pattern, condition & metadata
			if ($class !== '') {
				if (!isset(self::$styles[$class])) {
					throw new \InvalidStateException("Parameter '$name' has '$class' flag, but Route::\$styles['$class'] is not set.");
				}
				$meta = self::$styles[$class];

			} elseif (isset(self::$styles[$name])) {
				$meta = self::$styles[$name];

			} else {
				$meta = self::$styles['#'];
			}

			if (isset($metadata[$name])) {
				$meta = $metadata[$name] + $meta;
			}

			if ($pattern == '' && isset($meta[self::PATTERN])) {
				$pattern = $meta[self::PATTERN];
			}

			$meta['filterTable2'] = empty($meta[self::FILTER_TABLE]) ? NULL : array_flip($meta[self::FILTER_TABLE]);
			if (array_key_exists(self::VALUE, $meta)) {
				if (isset($meta['filterTable2'][$meta[self::VALUE]])) {
					$meta['defOut'] = $meta['filterTable2'][$meta[self::VALUE]];

				} elseif (isset($meta[self::FILTER_OUT])) {
					$meta['defOut'] = call_user_func($meta[self::FILTER_OUT], $meta[self::VALUE]);

				} else {
					$meta['defOut'] = $meta[self::VALUE];
				}
			}
			$meta[self::PATTERN] = "#(?:$pattern)$#A" . ($this->flags & self::CASE_SENSITIVE ? '' : 'iu');

			// include in expression
			$re = '(?P<' . str_replace('-', '___', $name) . '>' . $pattern . ')' . $re; // str_replace is dirty trick to enable '-' in parameter name
			if ($brackets) { // is in brackets?
				if (!isset($meta[self::VALUE])) {
					$meta[self::VALUE] = $meta['defOut'] = NULL;
				}
				$meta['fixity'] = self::PATH_OPTIONAL;

			} elseif (isset($meta['fixity'])) { // auto-optional
				$re = '(?:' . substr_replace($re, ')?', strlen($re) - $autoOptional[0], 0);
				array_splice($sequence, count($sequence) - $autoOptional[1], 0, array(']', ''));
				array_unshift($sequence, '[', '');
				$meta['fixity'] = self::PATH_OPTIONAL;

			} else {
				$autoOptional = array(strlen($re), count($sequence));
			}

			$metadata[$name] = $meta;
		} while (TRUE);

		if ($brackets) {
			throw new \InvalidArgumentException("Missing closing ']' in mask '$mask'.");
		}

		$this->re = '#' . $re . '/?$#A' . ($this->flags & self::CASE_SENSITIVE ? '' : 'iu');
		$this->metadata = $metadata;
		$this->sequence = $sequence;
	}



	/**
	 * Returns mask.
	 * @return string
	 */
	public function getMask()
	{
		return $this->mask;
	}



	/**
	 * Returns default values.
	 * @return array
	 */
	public function getDefaults()
	{
		$defaults = array();
		foreach ($this->metadata as $name => $meta) {
			if (isset($meta['fixity'])) {
				$defaults[$name] = $meta[self::VALUE];
			}
		}
		return $defaults;
	}



	/********************* Utilities ****************d*g**/



	/**
	 * Proprietary cache aim.
	 * @return string|FALSE
	 */
	public function getTargetPresenter()
	{
		if ($this->flags & self::ONE_WAY) {
			return FALSE;
		}

		$m = $this->metadata;
		$module = '';

		if (isset($m[self::MODULE_KEY])) {
			if (isset($m[self::MODULE_KEY]['fixity']) && $m[self::MODULE_KEY]['fixity'] === self::CONSTANT) {
				$module = $m[self::MODULE_KEY][self::VALUE] . ':';
			} else {
				return NULL;
			}
		}

		if (isset($m[self::PRESENTER_KEY]['fixity']) && $m[self::PRESENTER_KEY]['fixity'] === self::CONSTANT) {
			return $module . $m[self::PRESENTER_KEY][self::VALUE];
		}
		return NULL;
	}



	/**
	 * Rename keys in array.
	 * @param  array
	 * @param  array
	 * @return array
	 */
	private static function renameKeys($arr, $xlat)
	{
		if (empty($xlat)) return $arr;

		$res = array();
		$occupied = array_flip($xlat);
		foreach ($arr as $k => $v) {
			if (isset($xlat[$k])) {
				$res[$xlat[$k]] = $v;

			} elseif (!isset($occupied[$k])) {
				$res[$k] = $v;
			}
		}
		return $res;
	}



	/********************* Inflectors ****************d*g**/



	/**
	 * camelCaseAction name -> dash-separated.
	 * @param  string
	 * @return string
	 */
	private static function action2path($s)
	{
		$s = preg_replace('#(.)(?=[A-Z])#', '$1-', $s);
		$s = strtolower($s);
		$s = rawurlencode($s);
		return $s;
	}



	/**
	 * dash-separated -> camelCaseAction name.
	 * @param  string
	 * @return string
	 */
	private static function path2action($s)
	{
		$s = strtolower($s);
		$s = preg_replace('#-(?=[a-z])#', ' ', $s);
		$s = substr(ucwords('x' . $s), 1);
		//$s = lcfirst(ucwords($s));
		$s = str_replace(' ', '', $s);
		return $s;
	}



	/**
	 * PascalCase:Presenter name -> dash-and-dot-separated.
	 * @param  string
	 * @return string
	 */
	private static function presenter2path($s)
	{
		$s = strtr($s, ':', '.');
		$s = preg_replace('#([^.])(?=[A-Z])#', '$1-', $s);
		$s = strtolower($s);
		$s = rawurlencode($s);
		return $s;
	}



	/**
	 * dash-and-dot-separated -> PascalCase:Presenter name.
	 * @param  string
	 * @return string
	 */
	private static function path2presenter($s)
	{
		$s = strtolower($s);
		$s = preg_replace('#([.-])(?=[a-z])#', '$1 ', $s);
		$s = ucwords($s);
		$s = str_replace('. ', ':', $s);
		$s = str_replace('- ', '', $s);
		return $s;
	}



	/********************* Route::$styles manipulator ****************d*g**/



	/**
	 * Creates new style.
	 * @param  string  style name (#style, urlParameter, ?queryParameter)
	 * @param  string  optional parent style name
	 * @return void
	 */
	public static function addStyle($style, $parent = '#')
	{
		if (isset(self::$styles[$style])) {
			throw new \InvalidArgumentException("Style '$style' already exists.");
		}

		if ($parent !== NULL) {
			if (!isset(self::$styles[$parent])) {
				throw new \InvalidArgumentException("Parent style '$parent' doesn't exist.");
			}
			self::$styles[$style] = self::$styles[$parent];

		} else {
			self::$styles[$style] = array();
		}
	}



	/**
	 * Changes style property value.
	 * @param  string  style name (#style, urlParameter, ?queryParameter)
	 * @param  string  property name (Route::PATTERN, Route::FILTER_IN, Route::FILTER_OUT, Route::FILTER_TABLE)
	 * @param  mixed   property value
	 * @return void
	 */
	public static function setStyleProperty($style, $key, $value)
	{
		if (!isset(self::$styles[$style])) {
			throw new \InvalidArgumentException("Style '$style' doesn't exist.");
		}
		self::$styles[$style][$key] = $value;
	}

}
