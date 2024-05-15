<?php

namespace Drupal\ax_config_export_api\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Drupal\Core\Cache\CacheableResponseInterface;

/**
 * Provides a resource for exporting configurations.
 *
 * @RestResource(
 *   id = "configuration_export",
 *   label = @Translation("Configurations Export"),
 *   uri_paths = {
 *     "canonical" = "/api/configuration-export/{config_name}"
 *   }
 * )
 */
class ConfigurationExportResource extends ResourceBase {

  /**
   * The currently authenticated user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a ConfigurationExportResource instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The currently authenticated user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, AccountInterface $currentUser, ConfigFactoryInterface $configFactory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->currentUser = $currentUser;
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('current_user'),
      $container->get('config.factory')
    );
  }

  /**
   * Responds to GET requests.
   *
   * Returns details for the specified configuration.
   *
   * @param string|null $config_name
   *   (optional) The name of the configuration.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing the configuration detail.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   When the configuration is not allowed to be viewed.
   */
  public function get($config_name = NULL) {
    // Get the list of allowed configurations.
    $allowed_configurations = $this->configFactory->get('ax_config_export_api.settings')
      ->get('selected_configs') ?? [];

    // Validate if the configuration is allowed to be viewed.
    if (!in_array($config_name, $allowed_configurations)) {
      throw new BadRequestHttpException($this->t('The configuration (@config_name) is not exposed for viewing.', ['@config_name' => $config_name]));
    }

    // Retrieve configuration details.
    $config_data[$config_name] = $this->configFactory->get($config_name)->getRawData();

    // Create and return the response.
    $response = new ResourceResponse($config_data);
    // Add the cache tag to invalidate every response when the
    // ax_config_export_api.settings configuration is updated.
    if ($response instanceof CacheableResponseInterface) {
      $response->getCacheableMetadata()->addCacheTags(['config:ax_config_export_api.settings']);
    }
    return $response;
  }

}
