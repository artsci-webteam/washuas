<?php

namespace Drupal\washuas_wucrsl\Services;

use Drupal\Core\Config\Schema\ArrayElement;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Config;


/**
 * Class Soap.
 *
 * @file providing helpful SOAP functions to use with the WUCrsl web service
 */

class Cache {
  /**
   * Loaded washuas_wucrsl settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;
  protected $useCache;
  const SETTINGS = 'washuas_wucrsl.settings';

  //protected
  public function __construct() {
    $this->config = \Drupal::config(static::SETTINGS);
    $this->useCache = $this->config->get('wucrsl_cache_api');
  }

  /**
   * This pulls data from cache
   *
   * @param bool $cacheData
   *  indicates if we'll attempt to pull data from cache
   * @param string $cacheStorage
   *  where we will store the cachedData
   * @param string $neededIndex
   *  an optional index to check for to see if we can pull from cache or need fresh soap data
   *
   * @return array
   *  the results stored in cache
   *
   * @throws
   */
  function getDataFromCache($cacheStorage){
    //if we're not set to use cache then return null
    if ( !$this->useCache){
      return null;
    }

    //check the single page cache
    $results = &drupal_static($cacheStorage);

    //if single page cache gives results then we are done
    if ( !empty($results) ) {
      return $results;
    }

    //pull the data from drupal cache
    $cache = \Drupal::cache('data')->get($cacheStorage);

    //return the cached data if we have it or a null
    return ( !empty($cache->data) ) ? $cache->data : null ;
  }

  /**
   * This saves data to cache
   *
   * @param bool $cacheData
   *  indicates if we'll attempt to pull data from cache
   * @param string $cacheStorage
   *  the soap function we will call
   * @param array $data
   *  an optional index to check for to see if we can pull from cache or need fresh soap data
   * @param timestamp $expiration
   *  the time at which cache expires
   * @param array $tags
   *   the time at which cache expires
   * @return void
   *
   * @throws
   */
  function saveDataToCache($cacheStorage,$data):void{
    // cache the data
    if($this->useCache){
      \Drupal::cache('data')->set($cacheStorage, $data,strtotime('midnight') + (48*60*60),["wucrsl"]);
    }
  }
}
