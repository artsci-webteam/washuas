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

use Drupal\Core\Url;
use Drupal\Core\Render\Markup;
use Drupal\Core\Link;

/**
 * Configure settings for this WashU A&S WUCrsl module.
 */
class WashuasWucrslDepartmentsForm extends ConfigFormBase {
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

  public function __construct(ConfigFactoryInterface $config_factory, MessengerInterface $messenger) {
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
    return 'washuas_wucrsl_department_settings';
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
    if (empty($config->get('wucrsl_soap_url'))) {
      $aText = 'In order to select departments you must first set and save the soap api settings. Click here to save the settings';
      $aURL = new Url('washuas_wucrsl.settings');
      $form['wucursl_settings_link']['#markup'] = Link::fromTextAndUrl($aText,$aURL)->toString();
      $form['actions']['submit']['#attributes']['disabled']  = 'disabled';

      return $form;
    }

    $courses = \Drupal::service('washuas_wucrsl.courses');

    //pull the configuration for this form
    $config = $this->config(static::SETTINGS);

    $departments = $courses->getDepartments()['options'];
    $form['wucrsl_department'] = [
      '#type' => 'checkboxes',
      '#options' => (empty($departments)) ? []:$departments,
      '#title' => $this->t('Departments to import.'),
      '#default_value' => (empty($config->get('wucrsl_department'))) ? []:$config->get('wucrsl_department'),
     ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   * @throws EntityStorageException
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    $config
      ->set('wucrsl_department', $form_state->getValue('wucrsl_department'))
      ->save();

    parent::submitForm($form, $form_state);
  }
  /**
   * Submit function to clear WUCrsL's 2 day SOAP cache in cache_form
   */
}
