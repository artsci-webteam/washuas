<?php

namespace Drupal\washuas_wucrsl\Services;

use Drupal\Core\Config\Schema\ArrayElement;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Class Soap.
 *
 * @file providing helpful SOAP functions to use with the WUCrsl web service
 */

class Soap {

  /**
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */

  protected $loggerFactory;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  //protected
  public function __construct(LoggerChannelFactoryInterface $loggerFactory, MessengerInterface $messenger) {
    $this->loggerFactory = $loggerFactory;
    $this->messenger = $messenger;
  }

  /**
   * This establishes the soap client with the needed parameters
   *
   * @param string $url
   *  the url to send the soap request to
   * @param array $parameters
   *  the parameters for the request
   * @param bool $cacheData
   *  indicates if we'll cache
   * @param bool $enableTracing
   *  indicates if we'll enable tracing
   * @param bool $reset
   *  indicates if we'll pull the client from cache
   *
   * @return \SoapClient
   *  the soap client we'll use for requests
   *
   * @throws
   */
  function getClient($url, $parameters, $cacheData=FALSE, $enableTracing=FALSE, $reset=FALSE) {
    $cli = &drupal_static(__FUNCTION__);

    // Force reset:
    if ($reset) {
      $cli = NULL;
    }

    // Return a cached copy if appropriate:
    if (isset($cli) && !$enableTracing) {
      return $cli;
    }

    // Check SOAP URI
    if (empty($url)) {
      $message = 'Fatal SOAP error: No URI is defined.';
      $this->messenger->addError($message);
      $this->loggerFactory->get('washuas_wucrsl')->error($message);
      throw new \Exception($message);
    }

    // Should we use the WSDL cache?
    if ($cacheData) {
      ini_set('soap.wsdl_cache_enabled', '0');
      ini_set('soap.wsdl_cache_ttl', '0');
    }

    // Build the base options array.
    $options = array(
      'soap_version' => SOAP_1_2,
      'exceptions' => TRUE,
      'trace' => $enableTracing,
      'location' => $url,
    );

    // Stop errors from propogating.
    //set_error_handler('wucrsl_local_error_handler');
    try {
      $separator = (strpos($url, '?') === FALSE) ? '?' : '&';
      $wsdl_uri = $url . $separator . 'WSDL'; // To get WSDL.
      $cli = new \SoapClient($wsdl_uri, $options);
    }
    catch (\SoapFault $e) {
      $vars = array('%msg' => $e->getMessage());
      $message = 'SOAP Fault: %msg '.print_r($vars,true);
      $this->loggerFactory->get('washuas_wucrsl')->error($message);
      $this->messenger->addError($message);
      // DON'T RETURN
    }
    // Restore the old error handler.
    //restore_error_handler();

    if (empty($cli)) {
      $message = "SOAP client could not be created.";
      $this->loggerFactory->get('washuas_wucrsl')->error($message);
      throw new \Exception($message);
    }

    return $cli;
  }

  /**
   * This executes soap functions
   *
   * @param string $url
   *  the url to send the soap request to
   * @param array $parameters
   *  the parameters for the request
   * @param string $soapFunction
   *  the soap function we will call
   *
   * @return array
   *  the results returned by the soap function
   *
   * @throws
   */
  function executeFunction($url,$parameters,$soapFunction) {
    //if we're here then it's time for us to use some SOAP
    try {
      $soap = $this->getClient($url,$parameters);
      $results = $soap->$soapFunction($parameters)->{$soapFunction.'Result'};

      return $results;
    }
    catch (\Exception $e) {
      $message = 'SOAP failure in '.$soapFunction.' '.$e->getMessage();
      $this->loggerFactory->get('washuas_wucrsl')->error($message);
      $this->messenger->addError($message);
      return null;
    }
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
  function getDataFromCache($cacheData,$cacheStorage){
    //if we're not set to use cache then return null
    if ( !$cacheData ){
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
  function saveDataToCache($cacheData,$cacheStorage,$data,$expiration,$tags):void{
    // cache the departments
    if($cacheData){
      \Drupal::cache('data')->set($cacheStorage, $data,$expiration,$tags);
    }
  }
}
