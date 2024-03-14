<?php

namespace Drupal\washuas_secure_content\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\permissions_by_term\Service\AccessStorage;
use Drupal\washuas\Services\EntityTools;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure settings for this WashU A&S Secure Content module.
 */
class WashuasSecureContentSettingsForm extends ConfigFormBase {
  /**
   * @var \Drupal\washuas\Services\EntityTools*/
  private $entity_tools;

  /**
   * @var \Drupal\permissions_by_term\Service\AccessStorage*/
  private $permissions_by_term_access_storage;

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

  public function __construct(ConfigFactoryInterface $config_factory, EntityTools $entity_tools, AccessStorage $permissions_by_term_access_storage, LoggerChannelFactoryInterface $loggerFactory, MessengerInterface $messenger) {
    parent::__construct($config_factory);
    $this->entity_tools = $entity_tools;
    $this->permissions_by_term_access_storage = $permissions_by_term_access_storage;
    $this->loggerFactory = $loggerFactory;
    $this->messenger = $messenger;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('washuas.entitytools'),
      $container->get('permissions_by_term.access_storage'),
      $container->get('logger.factory'),
      $container->get('messenger')
    );
  }

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'washuas_secure_content.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'washuas_secure_content_admin_settings';
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

    /**
     * NOTE: Please try to name field keys using "_visibility_"
     * to work with the config setting logic in the form submit.
     */

    $config = $this->config(static::SETTINGS);

    // Used to populate the roles config checkboxes
    $roles = $this->entity_tools->getRoles();
    $roles = $this->entity_tools->flattenEntityObjectsArray($roles);
    // Remove the administrator role from the role array since PbT always adds this role to perms
    unset($roles['administrator']);

    // Used to populate the content type fieldsets
    $content_types = $this->entity_tools->getContentTypes();
    $content_types = $this->entity_tools->flattenEntityObjectsArray($content_types);

    // Visibility vocab variables
    $visibility_terms = $this->entity_tools->getVocbaularyTermsAsArray('visibility');
    $public_term = $this->entity_tools->getTerm('visibility', 'Public');
    $public_tid = reset($public_term)->id();
    $private_term = $this->entity_tools->getTerm('visibility', 'Private');
    $private_tid = reset($private_term)->id();

    $form['redirection'] = [
      '#type' => 'html_tag',
      '#tag' => 'H2',
      '#value' => $this
        ->t('Redirection Settings:'),
    ];

    $form['enable_redirection'] = [
      '#type' => 'checkbox',
      '#title' => $this
        ->t('Enable redirect to login when access is denied'),
      '#default_value' => $config->get('enable_redirection'),
    ];

    $form['redirection_message'] = [
      '#type' => 'textarea',
      '#title' => $this
        ->t('Redirection message'),
      '#default_value' => $config->get('redirection_message')
        ? $config->get('redirection_message')
        : $this->t('You must be logged in using your WUSTL Key to view this page.'),
      '#states' => array(
        'visible' => array(
          ':input[name="enable_redirection"]' => array('checked' => TRUE),
        ),
      ),
    ];


    $form['fields'] = [
      '#type' => 'html_tag',
      '#tag' => 'H2',
      '#value' => $this
        ->t('Fields:'),
    ];

    $form['default_field_visibility_value'] = [
      '#type' => 'select',
      '#title' => $this
        ->t('Default visibility field value'),
      '#options' => $visibility_terms,
      '#default_value' => $config->get('default_field_visibility_value')
        ? $config->get('default_field_visibility_value')
        : $public_tid,
    ];

    // Used to pass to the Permissions by Term Access Storage service
    $form['private_visibility_term'] = [
      '#type' => 'hidden',
      '#default_value' => $private_tid,
    ];

    $form['default_field_visibility_description'] = [
      '#type' => 'textarea',
      '#title' => $this
        ->t('Default visibility field description'),
      '#default_value' => $config->get('default_field_visibility_description')
        ? $config->get('default_field_visibility_description')
        : $this->t('Please specify if this page should be available to visitors or should require visitors to log in.
                        <br/><br /> Public: available to all visitors
                        <br/> Private: available to only visitors who have logged in'),
    ];

    $form['roles'] = [
      '#type' => 'html_tag',
      '#tag' => 'H2',
      '#value' => $this
        ->t('Roles:'),
    ];


    $form['default_visibility_roles'] = [
      '#type' => 'checkboxes',
      '#options' => $roles,
      '#title' => $this->t('Which roles should be able to view content set to "Private"?'),
      '#default_value' => $config->get('default_visibility_roles') ?? [],
      '#description' => $this->t('Note: the administrator role will always get added as a role that should get access,
                                        <br/> so it is not included in this list.'),
    ];

    $form['content_types'] = [
      '#type' => 'html_tag',
      '#tag' => 'H2',
      '#value' => $this
        ->t('Content Types:'),
    ];

    $form['ct_instructions'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this
        ->t('Please specify which content types should receive the ability to secure pages behind a login.'),
    ];

    foreach ($content_types as $ct_machine_name => $ct_label) {
      $ct_details_opened = $config->get($ct_machine_name . '_visibility_functionality') ? 'open' : '';
      $form[$ct_machine_name] = [
        '#type' => 'details',
        '#title' => $this
          ->t($ct_label),
        '#open' => $ct_details_opened,
      ];

      $form[$ct_machine_name][$ct_machine_name . '_visibility_functionality'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable visibility functionality on ' . $ct_label),
        '#default_value' => $config->get($ct_machine_name . '_visibility_functionality'),
        '#description' => 'Warning: deactivating visibility functionality on "
        ' . $ct_label . '" will remove all existing visibility field values.
        Please be careful when deciding to turn off this functionality.',
      ];
      $form[$ct_machine_name][$ct_machine_name . '_visibility_field_required'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Require visibility field  on ' . $ct_label),
        '#default_value' => $config->get($ct_machine_name . '_visibility_field_required'),
        '#description' => 'Warning: These settings will override required settings on "'. $ct_label . '".',
      ];

      // Used to pass to the Secure service
      $form[$ct_machine_name]['content_type_machine_name'] = [
        '#type' => 'hidden',
        '#default_value' => $ct_machine_name,
      ];
    }

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
      ->set('default_field_visibility_value', $form_state->getValue('default_field_visibility_value'))
      ->set('default_field_visibility_description', $form_state->getValue('default_field_visibility_description'))
      ->set('default_visibility_roles', $form_state->getValue('default_visibility_roles'))

      ->save();

    foreach ($form_state->getValues() as $config_name => $submitted_value) {
      // Make the field required on the CT depending on config
      $required = FALSE;

      // Only set values for the form items, not the drupal form items in the array
      if (str_contains($config_name, 'visibility')) {

        $bundle = '';
        // set the bundle value
        preg_match('/.+?(?=_visibility_functionality)/', $config_name, $matches);
        if (!empty($matches) && is_array($matches)) {
          $bundle = $matches[0];
        }

        // Initialize the required config variable we can use later
        if ($form_state->getValue($bundle . '_visibility_field_required')) {
          if ($submitted_value == 1) {
            $required = TRUE;
          }
        }

        // Add / remove field from nodes
        if (str_contains($config_name, '_visibility_functionality')) {
          if ($submitted_value == 1) {
            $this->entity_tools->addTaxonomyFieldToBundle(
               'node',
               $bundle,
               'field_washuas_secure_content_vis',
               'Visibility',
               $required,
               $form_state->getValue('default_field_visibility_description'),
               'visibility',
               'options_select',
               $form_state->getValue('default_field_visibility_value')
             );
          }

          // If we previously had config activated for a ct, but are now deactivating that config
          $previous_config_value = $config->get($config_name);
          if ($submitted_value == 0 && $previous_config_value == 1) {
            $this->entity_tools->removeFieldFromBundle('node', $bundle, 'field_washuas_secure_content_vis');
          }

          // Set required on visibility fields that have already been enabled in content
          if ($previous_config_value == 1
            && $config->get($bundle . '_visibility_field_required') == 0
            && $form_state->getValue($bundle . '_visibility_field_required') == 1) {
            $this->entity_tools->setFieldRequirement('node', $bundle, 'field_washuas_secure_content_vis', TRUE);
            $config
              ->set($bundle . '_visibility_field_required', 1)
              ->save();
          }
          // remove required on visibility fields that have already been enabled in content
          if ($previous_config_value == 1
            && $config->get($bundle . '_visibility_field_required') == 1
            && $form_state->getValue($bundle . '_visibility_field_required') == 0) {
            $this->entity_tools->setFieldRequirement('node', $bundle, 'field_washuas_secure_content_vis', FALSE);
            $config
              ->set($bundle . '_visibility_field_required', 0)
              ->save();
          }
        }

      }

      // Add / remove permissions by term role settings
      if (str_contains($config_name, 'visibility_roles')) {
        $private_tid = $form_state->getValue('private_visibility_term');
        // delete role permissions settings on the private term before we add / update with our role config
        $this->permissions_by_term_access_storage->deleteAllTermPermissionsByTid($private_tid);
        $this->permissions_by_term_access_storage->addTermPermissionsByRoleIds($submitted_value, $private_tid);
        $message = t(
            "Permissions by term settings have been updated for 'Private' in the Visibility Vocabulary.",
          );
        $this->messenger->addStatus($message);
        $this->loggerFactory->get('washuas_secure_content')->notice($message);
      }
      // Set the submitted content type configuration settings.
      $config
        ->set($config_name, $form_state->getValue($config_name))
        ->save();

    }

    parent::submitForm($form, $form_state);
  }

}
