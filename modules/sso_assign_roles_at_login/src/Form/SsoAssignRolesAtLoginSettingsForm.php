<?php

namespace Drupal\sso_assign_roles_at_login\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\washuas\Services\EntityTools;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure example settings for this site.
 */
class SsoAssignRolesAtLoginSettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'sso_assign_roles_at_login.settings';

  /**
   * Drupal\washuas\Services\EntityTools definition.
   *
   * @var EntityTools $entity_tools ;
   */
  protected $entity_tools;

  /**
   * Class constructor.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    parent::__construct($config_factory);
    $this->entityTools = \Drupal::service('sso_assign_roles_at_login.entitytools');
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
  public function getFormId() {
    return 'sso_assign_roles_at_login_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);
    $roles = $this->entityTools->getRoles();

    $form['notice'] = [
      '#markup' => '
        <div class="messages__wrapper light-text">
          <div role="contentinfo" aria-labelledby="message-error-title" class="messages-list__item messages messages--info">
            <div role="alert">
              <div class="messages__header">
                <h2 id="message-error-title" class="messages__title">
                  Notice
                </h2>
              </div>
              <div class="messages__content">
                Please use the sso_assign_roles_at_login.settings.yml file to update email addresses.
              </div>
            </div>
          </div>
        </div>
      ',
    ];

    foreach ($roles as $role) {
      $role_label = $role->label();
      $role_id = $role->id();

      // Open the details form element if there are set config values
      $sso_login_emails_details_opened = '';
      if ($config->get($role_id . '_sso_login_emails_artsci')
        || $config->get($role_id . '_sso_login_emails_research')) {
        $sso_login_emails_details_opened = 'open';
      }

      $form[$role_id] = [
        '#type' => 'details',
        '#title' => $this
          ->t($role_label),
        '#open' => $sso_login_emails_details_opened,
      ];

      // Used to pass to the SSO login service
      $form[$role_id]['role_id'] = [
        '#type' => 'hidden',
        '#default_value' => $role_id,
      ];

      // Artsci email config
      $artsci_sso_login_emails = $config->get($role_id . '_sso_login_emails_artsci');
      if (is_array($artsci_sso_login_emails)) {
        $artsci_sso_login_emails = implode(PHP_EOL, $artsci_sso_login_emails);
      }
      $form[$role_id]['artsci'][$role_id . '_sso_login_emails_artsci'] = [
        '#type' => 'textarea',
        '#title' => $this->t('ArtSci Emails that should be assigned this role when authenticating via SSO'),
        '#default_value' => $artsci_sso_login_emails,
        '#description' => 'List email addresses one per line that should receive this role when signing into the website via SSO',
        '#disabled' => TRUE,
      ];

      // Research email config
      $research_sso_login_emails = $config->get($role_id . '_sso_login_emails_research');
      if (is_array($research_sso_login_emails)) {
        $research_sso_login_emails = implode(PHP_EOL, $research_sso_login_emails);
      }
      $form[$role_id]['research'][$role_id . '_sso_login_emails_research'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Research Emails that should be assigned this role when authenticating via SSO'),
        '#default_value' => $research_sso_login_emails,
        '#description' => 'List email addresses one per line that should receive this role when signing into the website via SSO',
        '#disabled' => TRUE,
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    // Retrieve the configuration.
    foreach ($form_state->getValues() as $config_name => $submitted_value) {
      $config_keys = [];
      // Only set values for the form items, not the drupal form items in the array
      $sso_email_config_values = preg_split('/\n|\r\n?/', $form_state->getValue($config_name));

      foreach ($sso_email_config_values as $value) {
        if (!empty($value)) {
          $config_keys[$config_name][] = $value;
        }
      }

      foreach ($config_keys as $config_key => $config_value) {
        if (str_contains($config_key, '_sso_login_emails')) {
          $config
            ->set($config_key, $config_value)
            ->save();
        }
      }
    }

    parent::submitForm($form, $form_state);
  }

}