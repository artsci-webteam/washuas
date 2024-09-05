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

class Mule {
  /**
   * Loaded washuas_wucrsl settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

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
  const SETTINGS = 'washuas_wucrsl.settings';
  protected $token;
  protected $clientID;
  protected $clientSecret;

  //protected
  public function __construct(LoggerChannelFactoryInterface $loggerFactory, MessengerInterface $messenger) {
    $this->loggerFactory = $loggerFactory;
    $this->messenger = $messenger;
    $this->config = \Drupal::config(static::SETTINGS);
    $this->clientID = $this->config->get('wucrsl_client_id');
    $this->clientSecret = $this->config->get('wucrsl_client_secret');
  }

  public function getAccessToken($clientID,$clientSecret):string{
    $url = $this->config->get('wucrsl_token_url');
    if ( empty($url)){
      return '';
    }
    $response = \Drupal::httpClient()
      ->post($url, [
      'form_params' => [
        'grant_type' => 'client_credentials',
        'client_id' => $clientID,
        'client_secret' => $clientSecret,
      ],
      'headers' => [
        'Content-Type' => 'application/x-www-form-urlencoded',
      ],
    ]);

    $data = json_decode((string) $response->getBody(), TRUE);

    return $data['access_token'] ?? '';
  }

  /**
   * Gets the soap parameters that we will use to make our calls
   *
   * @param string $env
   *  the environment used to pull configuration from
   *
   * @param string $function
   *  the soap function we will be calling
   *
   * @param string $semester
   *  the semester for which we'll pull the data
   *
   * @return array $params
   *  the parameters we will use for soap calls
   *
   */
  public function getAPIParameters($api,$function,$semester=null,$unit=null): array {
    //this will be our return values($api,$function,$semester,$unit)
    $params = [];

    //pull the department from the configuration or set the default to L
    //@todo change the default department and school?
    //Set the environment parameters based on the selected environment of the configuration screen
    $params['ClientID'] = $this->clientID;
    $params['ClientSecret'] = $this->clientSecret;
    //get the current semester if needed
    $current = $this->getCurrentSemester()["sort"];
    switch( $api.'_'.$function ){
      case "organization_academicunits":
      default:
        break;
    }

    return $params;
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
  function executeFunction($url,$api,$function) {
    $token = $this->getAccessToken($this->clientID,$this->clientSecret);

    //if we're here then it's time for us to use some SOAP
    $apiURL = $url.$api."/".$function;
    try {
      $response = \Drupal::httpClient()
        ->get($apiURL, [
          'headers' => [
            'Authorization' => 'Bearer ' . $token,
            //'X-Correlation-ID' => ,
          ],
          'query' => [
            'Count' => '999',
          ],
        ]);


      $data = json_decode((string) $response->getBody(), TRUE);

      return $data ?? null;
    }
    catch (\Exception $e) {
      $message = 'SOAP failure in '.$soapFunction.' '.$e->getMessage();
      $this->loggerFactory->get('washuas_wucrsl')->error($message);
      $this->messenger->addError($message);
      return null;
    }

    $url = $this->config->get('wucrsl_token_url');
    if ( empty($url)){
      return '';
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
