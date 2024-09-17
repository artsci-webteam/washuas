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
   * @return array $query
   *  the parameters we will use for soap calls
   *
   */
  public function getAPIParameters($api,$function,$query=[]): array {
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
  function executeFunction($url, $api, $function,$key, array $parameters) {
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
      $message = 'MuleSoft API Connection Error at '.$apiURL.' '.$e->getMessage();
      $this->loggerFactory->get('washuas_wucrsl')->error($message);
      $this->messenger->addError($message);
      return null;
    }
  }
}
