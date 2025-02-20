<?php

namespace Drupal\washuas_courses\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Schema\ArrayElement;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\washuas\Services\EntityTools;
use Drupal\washuas_courses\Services\Soap;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Dotenv\Dotenv;

/**
 * Configure settings for this WashU A&S Courses module.
 */
class WashuasCoursesMuleSoftSettingsForm extends ConfigFormBase {
  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'washuas_courses.settings';

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
    return 'washuas_courses_admin_settings';
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
      '#value' => t('Clear all Courses caches'),
      '#submit' => ['::courses_clear_cache_submit'],
    );

    $form['courses_cache_api'] = array(
      '#type' => 'checkbox',
      '#title' => t('Cache all API calls (<em>Imperative</em> for Production Sites)'),
      '#default_value' => $config->get('courses_cache_api') ?? TRUE,
      '#description' => t('Uncheck to always get new information from the MuleSoft API. Check to save the results of API calls in the database. Cron will always clear the cache older than 48 hours.'),
    );

    $form['courses_token_url'] = array(
      '#type' => 'textfield',
      '#title' => t('API Access Token URL(JDM)'),
      '#description' => t('This is the url we use to request an access token. The token is required for API requests'),
      '#size' => 60,
      '#default_value' => $config->get('courses_token_url') ?? 'https://is-login.wustl.edu/connect/token',
      '#maxlength' => 255,
      '#required' => TRUE,
    );

    $form['courses_request_url'] = array(
      '#type' => 'textfield',
      '#title' => t('API request url'),
      '#description' => t('This is the url that any api request to retrieve data are sent to.'),
      '#default_value' => $config->get('courses_request_url') ?? 'https://test.wuapi.wustl.edu/v1/',
      '#size' => 60,
      '#maxlength' => 256,
      '#required' => TRUE,
    );

    $form['courses_client_id'] = array(
      '#type' => 'textfield',
      '#title' => t('Client ID'),
      '#description' => t("The Client ID needed to access the api, this is set in the .env file and thus read only. If this value is missing be sure to add it to the .env with: COURSES_CLIENT_ID='client_id'"),
      '#default_value' => $_ENV['COURSES_CLIENT_ID'] ?? 'client id',
      '#size' => 60,
      '#maxlength' => 256,
      '#required' => TRUE,
      '#attributes' => [
        'readonly' => 'readonly',
      ],
    );


    $form['courses_client_secret'] = array(
      '#type' => 'textfield',
      '#title' => t('Client Secret'),
      '#description' => t("The Client Secret needed to access the api, this is set in the .env file and thus read only. If this value is missing be sure to add it to the .env with: COURSES_CLIENT_SECRET='client_secret'"),
      '#default_value' => $_ENV['COURSES_CLIENT_SECRET'] ?? 'client secret',
      '#size' => 60,
      '#maxlength' => 256,
      '#required' => TRUE,
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
      ->set('courses_token_url', $form_state->getValue('courses_token_url'))
      ->set('courses_request_url', $form_state->getValue('courses_request_url'))
      ->set('courses_client_id', $form_state->getValue('courses_client_id'))
      ->set('courses_client_secret', $form_state->getValue('courses_client_secret'))
      ->set('courses_cache_api', $form_state->getValue('courses_cache_api'))
      ->save();

    parent::submitForm($form, $form_state);
  }
  /**
   * Submit function to clear courses cache in cache_form
   */
  public function courses_clear_cache_submit():void {
    //if we're here then it's time to invalidate everything with the courses tag
    \Drupal\Core\Cache\Cache::invalidateTags(["courses"]);
    $this->messenger()->addMessage('All of the corresponding caches for Courses are cleared');
  }
}
