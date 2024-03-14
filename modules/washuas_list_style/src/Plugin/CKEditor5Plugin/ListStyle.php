<?php

declare(strict_types=1);

namespace Drupal\washuas_list_style\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5Plugin\ListPlugin;
use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\EditorInterface;

/**
 * CKEditor 5 ListStyle plugin.
 */
class ListStyle extends ListPlugin {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return parent::defaultConfiguration() + ['styles' => TRUE];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['styles'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow the user to use style attribute'),
      '#default_value' => $this->configuration['styles'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::validateConfigurationForm($form, $form_state);
    $form_value = $form_state->getValue('styles');
    $form_state->setValue('styles', (bool) $form_value);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['styles'] = $form_state->getValue('styles');
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $static_plugin_config = parent::getDynamicPluginConfig($static_plugin_config, $editor);

    // Add proper configuration to use type attribute based list styles on ul
    // and ol elements.
    if ($this->configuration["styles"]) {
      $static_plugin_config["list"]["properties"]["styles"] = [];
      $static_plugin_config["list"]["properties"]["styles"]['useAttribute'] = TRUE;
      $static_plugin_config['list']['allow'] = [
        [
          'name' => 'ul',
          'attributes' => ['type' => TRUE],
          'classes' => TRUE,
          'styles' => TRUE,
        ],
        [
          'name' => 'ol',
          'attributes' => ['type' => TRUE],
          'classes' => TRUE,
          'styles' => TRUE,
        ],
      ];
    }
    return $static_plugin_config;
  }

}
