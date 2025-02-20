<?php

namespace Drupal\washuas_courses\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Render\Markup;
use Drupal\Core\Link;

/**
 * Configure settings for this WashU A&S Courses module.
 */
class WashuasCoursesImportForm extends FormBase {
  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'washuas_courses.settings';

  public function __construct() {
    $this->courses = \Drupal::service('washuas_courses.courses');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'washuas_courses_manual_import';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $courses = \Drupal::service('washuas_courses.courses');
    //config returns everything with 0 values for non selected options, this removes those
    $units = \Drupal::service('config.factory')->get(static::SETTINGS)->get('courses_academic_units');;

    if (empty($units)) {
      $aText = 'In order to import courses you must first set the academic units. Click here to set the academic units';
      $aURL = new Url('washuas_courses.units');
      $form['wucursl_units_link']['#markup'] = Link::fromTextAndUrl($aText,$aURL)->toString();
      $form['actions']['submit']['#attributes']['disabled']  = 'disabled';

      return $form;
    }

    $form['units'] = [
      '#type' => 'select',
      '#title' => $this->t('Academic Unit to import'),
      '#options' => $units,
    ];

    $form['semester'] = [
      '#type' => 'select',
      '#title' => $this->t('Semester To Import'),
      '#options' => $courses->getManualImportSemesters(),
      '#default_value' => $courses->getCurrentSemester()["full"],
    ];

    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Import Courses')];

    return $form;
  }


  /**
   * {@inheritdoc}
   * @throws EntityStorageException
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $semester = $form_state->getValue('semester');
    $deptCode = $form_state->getValue('units');
    $deptName = $form_state->getCompleteForm()['units']['#options'][$deptCode];
    $units[$deptCode] = $deptName;
    $batch = \Drupal::service('washuas_courses.courses')->getSectionPullBatches($semester,$units);
    batch_set($batch);
  }
}
