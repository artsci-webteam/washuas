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
use Drupal\Core\Url;
use Drupal\Core\Link;


/**
 * Configure settings for this WashU A&S Courses module.
 */
class WashuasCoursesAcademicUnitsForm extends ConfigFormBase {
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
    return 'washuas_courses_academic_units';
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
    $courses = \Drupal::service('washuas_courses.courses');

    //pull the configuration for this form
    $config = $this->config(static::SETTINGS);


    if (empty($config->get('courses_token_url'))) {
      $aText = 'In order to set units you must first save the api settings at the below link.';
      $aURL = new Url('washuas_courses.settings');
      $form['wucursl_settings_link']['#markup'] = Link::fromTextAndUrl($aText,$aURL)->toString();
      $form['actions']['submit']['#attributes']['disabled']  = 'disabled';

      return $form;
    }

    $form['courses_academic_units'] = [
      '#type' => 'checkboxes',
      '#options' => $courses->getAcademicUnitOptions(),
      '#title' => $this->t('Academic Units to import.'),
    ];

    //if we have any units selected then process and add 'em to the form
    $defaults = $config->get('courses_academic_units');
    if (is_array($defaults)){
      foreach($defaults as $key=>$value){
        $defaults[$key]=$key;
      }
      $form['courses_academic_units']['#default_value'] = $defaults;
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
    $unitNames = $form_state->getCompleteForm()['courses_academic_units']['#options'];
    //filter the academic units so it only has the selected options
    $unitIDs = array_filter($form_state->getValue('courses_academic_units'));
    //intersect the names list and values so we have both the selected unit id and value
    $units = array_intersect_key($unitNames,$unitIDs);

    $config
      ->set('courses_academic_units', $units)
      ->save();

    parent::submitForm($form, $form_state);
  }
  /**
   * Submit function to clear the courses cache in cache_form
   */
}
