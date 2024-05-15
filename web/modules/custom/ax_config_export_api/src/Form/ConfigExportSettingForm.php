<?php

namespace Drupal\ax_config_export_api\Form;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Config Exporter settings form.
 */
class ConfigExportSettingForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $configStorage;

  /**
   * Constructs a new ConfigExporterSettingsForm instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\StorageInterface $config_storage
   *   The config storage.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, StorageInterface $config_storage) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configStorage = $config_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.storage')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ax_config_export_api_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['ax_config_export_api.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get the configurations.
    $configs = $this->getConfigurations();
    // Get default selected configurations.
    $selected_configs = $this->config('ax_config_export_api.settings')->get('selected_configs') ?: [];

    // Checkbox.
    $form['configurations'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Select configurations to be exposed through the API'),
      '#options' => $configs,
      '#default_value' => $selected_configs,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Returns a list of configurations.
   *
   * @return array
   *   A list of configurations.
   */
  private function getConfigurations() {
    // Find all config, and then filter out entity configurations.
    $config_names = $this->configStorage->listAll();
    $names = array_combine($config_names, $config_names);
    return $names;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get selected configurations.
    $selected = array_values(array_filter($form_state->getValue('configurations')));

    // Save selected configurations.
    $this->config('ax_config_export_api.settings')
      ->set('selected_configs', $selected)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
