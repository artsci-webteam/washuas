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
    $url = $this->config->get('wucrsl_token_url');
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
      $this->loggerFactory->get('washuas_wucrsl')->error($message.$url.' '.$e->getMessage());
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
   * This executes api requests
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
      $this->loggerFactory->get('washuas_wucrsl')->error($message.$apiURL.' '.$e->getMessage());
      $this->messenger->addError($message);
      return null;
    }
  }
}
