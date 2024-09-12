<?php

namespace Drupal\washuas_wucrsl\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Schema\ArrayElement;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\washuas\Services\EntityTools;
use Drupal\washuas_wucrsl\Services\Soap;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Dotenv\Dotenv;

/**
 * Configure settings for this WashU A&S WUCrsl module.
 */
class WashuasWucrslMuleSoftSettingsForm extends ConfigFormBase {
  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'washuas_wucrsl.settings';

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

  public function __construct(ConfigFactoryInterface $config_factory,MessengerInterface $messenger) {
    parent::__construct($config_factory);
    $this->messenger = $messenger;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'washuas_wucrsl_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    //pull the configuration for this form
    $config = $this->config(static::SETTINGS);

    $form['clear'] = array(
      '#type' => 'submit',
      '#value' => t('Clear all WUCrsL caches'),
      '#submit' => ['::wucrsl_clear_cache_submit'],
    );

    $form['wucrsl_cache_api'] = array(
      '#type' => 'checkbox',
      '#title' => t('Cache all API calls (<em>Imperative</em> for Production Sites)'),
      '#default_value' => $config->get('wucrsl_cache_api') ?? TRUE,
      '#description' => t('Uncheck to always get new information from the MuleSoft API. Check to save the results of API calls in the database. Cron will always clear the cache older than 48 hours.'),
    );

    $form['wucrsl_token_url'] = array(
      '#type' => 'textfield',
      '#title' => t('API Access Token URL(JDM)'),
      '#description' => t('This is the url we use to request an access token. The token is required for API requests'),
      '#size' => 60,
      '#default_value' => $config->get('wucrsl_token_url') ?? 'https://is-login.wustl.edu/connect/token',
      '#maxlength' => 255,
      '#required' => TRUE,
    );

    $form['wucrsl_request_url'] = array(
      '#type' => 'textfield',
      '#title' => t('API request url'),
      '#description' => t('This is the url that any api request to retrieve data are sent to.'),
      '#default_value' => $config->get('wucrsl_request_url') ?? 'https://test.wuapi.wustl.edu/v1/',
      '#size' => 60,
      '#maxlength' => 256,
      '#required' => FALSE,
    );

    $form['wucrsl_client_id'] = array(
      '#type' => 'textfield',
      '#title' => t('Client ID'),
      '#description' => t('The Client ID needed to access the api, this is set in the .env file and thus read only.'),
      '#default_value' => $_ENV['COURSES_CLIENT_ID'] ?? 'client id',
      '#size' => 60,
      '#maxlength' => 256,
      '#required' => FALSE,
      '#attributes' => [
        'readonly' => 'readonly',
      ],
    );


    $form['wucrsl_client_secret'] = array(
      '#type' => 'textfield',
      '#title' => t('Client Secret'),
      '#description' => t('The Client Secret needed to access the api, this is set in the .env file and thus read only.'),
      '#default_value' => $_ENV['COURSES_CLIENT_SECRET'] ?? 'client secret',
      '#size' => 60,
      '#maxlength' => 256,
      '#required' => FALSE,
      '#attributes' => [
        'readonly' => 'readonly',
      ],
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   * @throws EntityStorageException
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    $config
      // Set the global submitted configuration settings.
      ->set('wucrsl_token_url', $form_state->getValue('wucrsl_token_url'))
      ->set('wucrsl_request_url', $form_state->getValue('wucrsl_request_url'))
      ->set('wucrsl_client_id', $form_state->getValue('wucrsl_client_id'))
      ->set('wucrsl_client_secret', $form_state->getValue('wucrsl_client_secret'))
      ->set('wucrsl_cache_api', $form_state->getValue('wucrsl_cache_api'))
      ->save();

    parent::submitForm($form, $form_state);
  }
  /**
   * Submit function to clear WUCrsL's 2 day SOAP cache in cache_form
   */
  public function wucrsl_clear_cache_submit():void {
    //if we're here then it's time to invalidate everything with the wucrsl tag
    \Drupal\Core\Cache\Cache::invalidateTags(["wucrsl"]);
    $this->messenger()->addMessage('All of the corresponding caches for WUCRSL have been cleared');
  }
}
