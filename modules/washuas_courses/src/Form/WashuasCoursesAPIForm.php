<?php

namespace Drupal\washuas_courses\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure settings for this WashU A&S Courses module.
 */
class WashuasCoursesAPIForm extends FormBase {
  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'washuas_courses.settings';

  public function __construct() {
    $test = true;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'washuas_courses_api_processing';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    return $form;
  }


  /**
   * {@inheritdoc}
   * @throws EntityStorageException
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $batch = \Drupal::service('washuas_courses.courses')->getSectionPullBatches('',[], true);
    batch_set($batch);
  }
}
