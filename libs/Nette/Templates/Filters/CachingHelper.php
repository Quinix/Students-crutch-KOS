<?php

/**
 * This file is part of the Nette Framework.
 *
 * Copyright (c) 2004, 2010 David Grudl (http://davidgrudl.com)
 *
 * This source file is subject to the "Nette license", and/or
 * GPL license. For more information please see http://nette.org
 * @package Nette\Templates
 */



/**
 * Caching template helper.
 *
 * @author     David Grudl
 */
class NCachingHelper extends NObject
{
	/** @var array */
	private $frame;

	/** @var string */
	private $key;



	/**
	 * Starts the output cache. Returns NCachingHelper object if buffering was started.
	 * @param  string
	 * @param  NCachingHelper
	 * @param  array
	 * @return NCachingHelper
	 */
	public static function create($key, & $parents, $args = NULL)
	{
		if ($args) {
			$key .= md5(serialize($args));
		}
		if ($parents) {
			end($parents)->frame[NCache::ITEMS][] = $key;
		}

		$cache = self::getCache();
		if (isset($cache[$key])) {
			echo $cache[$key];
			return FALSE;

		} else {
			$obj = new self;
			$obj->key = $key;
			$obj->frame = array(
				NCache::TAGS => isset($args['tags']) ? $args['tags'] : NULL,
				NCache::EXPIRE => isset($args['expire']) ? $args['expire'] : '+ 7 days',
			);
			ob_start();
			return $parents[] = $obj;
		}
	}



	/**
	 * Stops and saves the cache.
	 * @return void
	 */
	public function save()
	{
		$this->getCache()->save($this->key, ob_get_flush(), $this->frame);
		$this->key = $this->frame = NULL;
	}



	/**
	 * Adds the file dependency.
	 * @param  string
	 * @return void
	 */
	public function addFile($file)
	{
		$this->frame[NCache::FILES][] = $file;
	}



	/********************* backend ****************d*g**/



	/**
	 * @return NCache
	 */
	protected static function getCache()
	{
		return NEnvironment::getCache('Nette.Template.Cache');
	}

}