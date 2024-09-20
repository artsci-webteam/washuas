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
use Drupal\Core\Link;


/**
 * Configure settings for this WashU A&S WUCrsl module.
 */
class WashuasWucrslAcademicUnitsForm extends ConfigFormBase {
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
    return 'washuas_wucrsl_academic_units';
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
    $courses = \Drupal::service('washuas_wucrsl.courses');

    //pull the configuration for this form
    $config = $this->config(static::SETTINGS);


    if (empty($config->get('wucrsl_token_url'))) {
      $aText = 'In order to set units you must first save the api settings at the below link.';
      $aURL = new Url('washuas_wucrsl.settings');
      $form['wucursl_settings_link']['#markup'] = Link::fromTextAndUrl($aText,$aURL)->toString();
      $form['actions']['submit']['#attributes']['disabled']  = 'disabled';

      return $form;
    }

    $form['wucrsl_academic_units'] = [
      '#type' => 'checkboxes',
      '#options' => $courses->getAcademicUnitOptions(),
      '#title' => $this->t('Academic Units to import.'),
    ];

    //if we have any units selected then process and add 'em to the form
    $defaults = $config->get('wucrsl_academic_units');
    if (is_array($defaults)){
      foreach($defaults as $key=>$value){
        $defaults[$key]=$key;
      }
      $form['wucrsl_academic_units']['#default_value'] = $defaults;
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   * @throws EntityStorageException
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    //get the unit names from the form options
    $unitNames = $form_state->getCompleteForm()['wucrsl_academic_units']['#options'];
    //filter the academic units so it only has the selected options
    $unitIDs = array_filter($form_state->getValue('wucrsl_academic_units'));
    //intersect the names list and values so we have both the selected unit id and value
    $units = array_intersect_key($unitNames,$unitIDs);

    $config
      ->set('wucrsl_academic_units', $units)
      ->save();

    parent::submitForm($form, $form_state);
  }
  /**
   * Submit function to clear WUCrsL's 2 day SOAP cache in cache_form
   */
}
