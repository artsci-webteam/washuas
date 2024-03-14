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


/**
 * Configure settings for this WashU A&S WUCrsl module.
 */
class WashuasWucrslSettingsForm extends ConfigFormBase {
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

    $form['advanced'] = array(
      '#type' => 'fieldset',
      '#title' => t('Server Settings (Advanced)'),
      '#description' => t('Configuration for the web services layer.'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    );

    $form['advanced']['wucrsl_allow_cache'] = array(
      '#type' => 'checkbox',
      '#title' => t('Cache WSDL File (Recommended)'),
      '#default_value' => $config->get('wucrsl_allow_cache') ?? TRUE,
      '#description' => t('Uncheck to do <code>ini_set("soap.wsdl_cache_enabled", 0)</code> for all SOAP connections.'),
    );

    $form['advanced']['wucrsl_cache_soap'] = array(
      '#type' => 'checkbox',
      '#title' => t('Cache all SOAP calls (<em>Imperative</em> for Production Sites)'),
      '#default_value' => $config->get('wucrsl_cache_soap') ?? TRUE,
      '#description' => t('Uncheck to always get new information from the WUCrsL feed. Check to save the results of SOAP calls in the database. Cron will always clear a WUCrsL cache older than 48 hours.'),
    );

    $form['advanced']['soap_urls'] = array(
      '#type' => 'fieldset',
      '#title' => t('SOAP Server Environments'),
      '#description' => t('This is the server environment you will be using and all of the variables for each environment.'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    );

    $form['advanced']['soap_urls']['wucrsl_soap_env'] = [
      '#type' => 'select',
      '#title' => $this
        ->t('Soap URL/Environment to pull the data from.'),
      '#options' => array(
        'dev' => 'Development',
        'prod' => 'Production',
      ),
      '#default_value' => $config->get('wucrsl_soap_env')
        ? $config->get('wucrsl_soap_env')
        : 'dev'
    ];

    $form['advanced']['soap_urls']['dev'] = array(
      '#type' => 'fieldset',
      '#title' => t('Development Settings'),
      '#description' => t('These are the settings that apply when development is selected'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    );

    $form['advanced']['soap_urls']['dev']['wucrsl_dev_soap_url'] = array(
      '#type' => 'textfield',
      '#title' => t('SOAP Server URL'),
      '#description' => t('The URL to the web service provider.'),
      '#size' => 60,
      '#default_value' => $config->get('wucrsl_dev_soap_url') ?? 'https://istest.wustl.edu/sis_ws_courses/SISCourses.asmx',
      '#maxlength' => 255,
      '#required' => TRUE,
    );

    $form['advanced']['soap_urls']['dev']['wucrsl_dev_soap_client_id'] = array(
      '#type' => 'textfield',
      '#title' => t('Client UUID'),
      '#description' => t('The long identification string used to establish an identity with the remote server.'),
      '#default_value' => $config->get('wucrsl_dev_soap_client_id') ?? 'D31733E5-9081-479B-B536-B1C88101B5A2',
      '#size' => 60,
      '#maxlength' => 256,
      '#required' => FALSE,
    );

    $form['advanced']['soap_urls']['dev']['wucrsl_dev_soap_client_pw'] = array(
      '#type' => 'textfield',
      '#title' => t('Client Password'),
      '#description' => t('Security token used to establish authenticity with the remote server.'),
      '#default_value' => $config->get('wucrsl_dev_soap_client_pw') ?? 'password',
      '#size' => 60,
      '#maxlength' => 256,
      '#required' => FALSE,
    );

    $form['advanced']['soap_urls']['prod'] = array(
      '#type' => 'fieldset',
      '#title' => t('Production Settings'),
      '#description' => t('These are the settings that apply when production is selected'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    );

    $form['advanced']['soap_urls']['prod']['wucrsl_prod_soap_url'] = array(
      '#type' => 'textfield',
      '#title' => t('Production SOAP Server URL'),
      '#description' => t('The URL to the production web service provider.'),
      '#size' => 60,
      '#default_value' => $config->get('wucrsl_prod_soap_url') ?? 'https://acadinfo.wustl.edu/sis_ws_courses/siscourses.asmx',
      '#maxlength' => 255,
      '#required' => TRUE,
    );

    $form['advanced']['soap_urls']['prod']['wucrsl_prod_soap_client_id'] = array(
      '#type' => 'textfield',
      '#title' => t('Client UUID'),
      '#description' => t('The long identification string used to establish an identity with the remote server.'),
      '#default_value' => $config->get('wucrsl_prod_soap_client_id') ?? 'D31733E5-9081-479B-B536-B1C88101B5A2',
      '#size' => 60,
      '#maxlength' => 256,
      '#required' => FALSE,
    );

    $form['advanced']['soap_urls']['prod']['wucrsl_prod_soap_client_pw'] = array(
      '#type' => 'textfield',
      '#title' => t('Client Password'),
      '#description' => t('Security token used to establish authenticity with the remote server.'),
      '#default_value' => $config->get('wucrsl_prod_soap_client_pw') ?? 'password',
      '#size' => 60,
      '#maxlength' => 256,
      '#required' => FALSE,
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
      ->set('wucrsl_allow_cache', $form_state->getValue('wucrsl_allow_cache'))
      ->set('wucrsl_cache_soap', $form_state->getValue('wucrsl_cache_soap'))
      ->set('wucrsl_soap_env', $form_state->getValue('wucrsl_soap_env'))

      //switching things up so that you're able to keep both dev and prod settings saved
      ->set('wucrsl_dev_soap_url', $form_state->getValue('wucrsl_dev_soap_url'))
      ->set('wucrsl_dev_soap_client_id', $form_state->getValue('wucrsl_dev_soap_client_id'))
      ->set('wucrsl_dev_soap_client_pw', $form_state->getValue('wucrsl_dev_soap_client_pw'))

      ->set('wucrsl_prod_soap_url', $form_state->getValue('wucrsl_prod_soap_url'))
      ->set('wucrsl_prod_soap_client_id', $form_state->getValue('wucrsl_prod_soap_client_id'))
      ->set('wucrsl_prod_soap_client_pw', $form_state->getValue('wucrsl_prod_soap_client_pw'))
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
