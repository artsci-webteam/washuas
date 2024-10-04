<?php

namespace Drupal\washuas_courses\Services;

use Drupal\Core\Config\Schema\ArrayElement;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\washuas_courses\Services\Courses;

/**
 * Class Soap.
 *
 * @file providing helpful API functions to use with the Courses web service
 */

class Mule {
  /**
   * Loaded washuas_courses settings.
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
  const SETTINGS = 'washuas_courses.settings';
  protected $token;
  protected $clientID;
  protected $clientSecret;

  //protected
  public function __construct(LoggerChannelFactoryInterface $loggerFactory, MessengerInterface $messenger) {
    $this->loggerFactory = $loggerFactory;
    $this->messenger = $messenger;
    $this->config = \Drupal::config(static::SETTINGS);
    $this->clientID = $this->config->get('courses_client_id');
    $this->clientSecret = $this->config->get('courses_client_secret');
  }

  /**
   * This requests the access token we need to supply to all of our api requests
   *
   * @param string $clientID
   *  the client id we use to connect to mulesoft
   *
   * @param string $clientSecret
   *  the client secret we use to connect to mulesoft
   *
   * @return string
   *  the access token to use for api calls
   *
   */
  public function getAccessToken($clientID,$clientSecret):string{
    $url = $this->config->get('courses_token_url');
    if ( empty($url)){
      return '';
    }
    try {
      $response = \Drupal::httpClient()->post($url, [
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

      return $data['access_token'];
    }
    catch (\Exception $e) {
      $message = 'There was an issue connecting to the mulesoft api token request.';
      $this->loggerFactory->get('washuas_courses')->error($message.$url.' '.$e->getMessage());
      $this->messenger->addError($message);
      return '';
    }
  }

  /**
   * returns an array of the api parameters that we will use to make our calls
   *
   * @param string $api
   *  the environment used to pull configuration from
   *
   * @param string $function
   *  the soap function we will be calling
   *
   * @param array $query
   *   the query we will send along with the parameters to filter data
   *
   * @return array
   *  the parameters we will use for soap calls
   *
   */
  public function getAPIParameters(string $api,string $function,array $query=[]): array {
    //this will be our return values($api,$function,$semester,$unit)
    $token = $this->getAccessToken($this->clientID,$this->clientSecret);
    $timeStamp = new \DateTime('now');
    //this is the array that we'll send with the get request to the mulesoft api
    $parameters['headers'][ 'Authorization'] = 'Bearer ' . $token ;
    $parameters['headers'][ 'X-Correlation-ID'] = 'Shared-'. $timeStamp->format('c');

    //if we have a query then add it
    if (!empty($query)){
      $parameters['query'] = $query;
    }

    return $parameters;
  }

  /**
   * This returns executes and api request and returns the data
   *
   * @param string $url
   *  the url to send the soap request to
   * @param string $api
   *   the api we are calling from the url
   * @param string $function
   * the function we are calling from the api
   * @param string $key
   *    the index at which the data we actually want is located
   * @param array $parameters
   *  the parameters for the request
   *
   * @return array
   *  the results returned by the soap function
   *
   * @throws
   */
  function executeFunction(string $url,string $api,string $function,string $key, array $parameters) {
    //if we're here then it's time for us to use some SOAP
    $apiURL = $url.$api."/".$function;
    try {
      $returnData = [];
      do {
        //make the request to the api
        $response = \Drupal::httpClient()->get($apiURL, $parameters);
        //decode the data from the response
        $data = json_decode((string) $response->getBody(), TRUE);
        //this will store all the data from all the pages
        $returnData = array_merge($returnData,$data[$key]);
        //this will contain the next page url api to use if there is one
        $apiURL = $data["links"]["next"];
        //since we are using the next link any query will return the wrong results
        unset($parameters['query']);
      } while (!empty($apiURL));

      return $returnData;
    }
    catch (\Exception $e) {
      $message = 'There was an issue connecting to the mulesoft api.';
      $this->loggerFactory->get('washuas_courses')->error($message.$apiURL.' '.$e->getMessage());
      $this->messenger->addError($message);
      return null;
    }
  }


  /**
   * This returns the batch requests needed to pull data from the api
   *
   * @param string $url
   *  the url to send the soap request to
   * @param string $api
   *   the api we are calling from the url
   * @param string $function
   * the function we are calling from the api
   * @param string $key
   *    the index at which the data we actually want is located
   * @param array $parameters
   *  the parameters for the request
   *
   * @return array
   *  the results returned by the soap function
   *
   * @throws
   */
  function getBatchRequests(string $url,string $api,string $function,string $key, array $parameters):array {
    //if we're here then it's time for us to use some SOAP
    $requestURL = $this->getRequestURL($url,$api,$function);
    //we will first just pull one record as we need the meta data to get the total record count
    $parameters['query']['count'] = 1;
    try {
      $returnData = [];
      //make the request to the api
      $response = \Drupal::httpClient()->get($requestURL, $parameters);
      //decode the data from the response
      $data = json_decode((string) $response->getBody(), TRUE);
      //get the total records we might receive from the meta
      $totalRecs = $data['meta']['TotalRecords'];
      //create and return the api batches
      return $this->createRequestBatches($totalRecs,$api,$url,$function,$key,$parameters);
    }
    catch (\Exception $e) {
      $message = 'There was an issue connecting to the mulesoft api.';
      $this->loggerFactory->get('washuas_courses')->error($message.$requestURL.' '.$e->getMessage());
      $this->messenger->addError($message);
      return [];
    }
  }

  /**
   * this will create the api requests in batches of 100
   *
   * @param integer $totalRecs
   *  the number of records available in the api
   *
   * @param string $api
   *   the api url we will be calling in our requests
   *
   * @param string $url
   * the api url we will be calling in our requests
   *
   * @param string $function
   *  the api url we will be calling in our requests
   *
   * @param string $key
   *     the index at which the data we actually want is located
   *
   * @param array $parameters
   *    the api url we will be calling in our requests
   *
   * @return array
   *  the initialized batch to add operations to and process
   *
   * @throws
   */
  public function createRequestBatches(int $totalRecs,string $api, string $url,string $function,string $key, array $parameters):array{
    $batch_builder = $this->initRequestBatchBuilder($api,$function);
    //we're going to pull everything in by batches of 100
    $parameters['query']['count'] = 100;
    for ($i=0;$i<$totalRecs;$i+=$parameters['query']['count']){
      $batch_builder->addOperation([$this, 'getRequestBatch'], [$api,$url,$function,$key,$parameters]);
    }

        //batch set and batch process calls go here
    return $batch_builder->toArray();
  }
  /**
   * Get an initialized batch builder with our settings
   *
   * @param string $title
   *  the title to display while batch processing
   *
   * @return BatchBuilder
   *  the initialized batch to add operations to and process
   *
   * @throws
   */
  public function initRequestBatchBuilder(string $api,string $function):BatchBuilder{
    return (new BatchBuilder())
      ->setFile(\Drupal::service('module_handler')->getModule('washuas_courses')->getPath(). '/src/Services/Courses.php')
      ->setTitle(t('Mulesoft API requests to '.$api.'->'.$function))
      ->setFinishCallback([Courses::class, 'processMuleBatches'])
      ->setInitMessage(t('The request is starting to import from mulesoft for '.$api.'->'.$function))
      ->setProgressMessage(t('Processed Mulesoft requests @current out of @total.'))
      ->setErrorMessage(t('The request to mulesoft for '.$api.'->'.$function.' has encountered an error'));
  }

  /**
   * this sends an api request in batch processing
   *
   * @param string $api
   *   the api url we will be calling in our requests
   *
   * @param string $url
   * the api url we will be calling in our requests
   *
   * @param string $function
   *  the api url we will be calling in our requests
   *
   * @param string $key
   *     the index at which the data we actually want is located
   *
   * @param array $parameters
   *    the api url we will be calling in our requests
   *
   * @param array $context
   *     the context array passed to the function by batch processing
   *
   * @return void
   *  this doesn't return anything since the results are saved to the context
   *
   * @throws
   */
  public function getRequestBatch(string $api,string $url,string $function,string $key, array $parameters, &$context):void{
    $requestURL = $this->getRequestURL($url,$api,$function);
    //if this is the first request then the context won't yet have our results
    if (array_key_exists('nextRequestURL',$context['results'])){
      unset($parameters['query']); //since we are using the next link any query will return the wrong results
      $requestURL = $context['results']['nextRequestURL'];
    }
    try {
      //make the request to the api
      $response = \Drupal::httpClient()->get($requestURL, $parameters);
      //decode the data from the response
      $data = json_decode((string) $response->getBody(), TRUE);
      //store the data to our results
      $context['results']['data'][$function] = $data[$key];
      //this saves the next page url api to use if there is one
      $context['results']['nextRequestURL'] = $data["links"]["next"];
    }
    catch (\Exception $e) {
      $message = 'There was an issue connecting to the mulesoft api.';
      $this->loggerFactory->get('washuas_courses')->error($message.$requestURL.' '.$e->getMessage());
      $this->messenger->addError($message);
    }
  }

  /**
   * this sends an api request in batch processing
   *
   * @param string $api
   *   the api url we will be calling in our requests
   *
   * @param string $url
   * the api url we will be calling in our requests
   *
   * @param string $function
   *  the api url we will be calling in our requests
   *
   * @return string
   *  this full request url to send the request to: $url.$api.'/'.$function
   *
   * @throws
   */
  public function getRequestURL(string $url,string $api,string $function):string {
    return $url.$api.'/'.$function;
  }
}
