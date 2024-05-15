<?php

namespace Drupal\ax_config_export_api\Plugin\rest\resource;

use Psr\Log\LoggerInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Cache\CacheableResponseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Provides a get endpoint for fetching the allowed configurations list.
 *
 * @RestResource(
 *   id = "allowed_configs",
 *   label = @Translation("Allowed Configurations"),
 *   uri_paths = {
 *     "canonical" = "/api/allowed-configs"
 *   }
 * )
 */
class ConfigurationListResource extends ResourceBase {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a ConfigurationListResource instance.
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
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, ConfigFactoryInterface $configFactory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
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
      $container->get('config.factory')
    );
  }

  /**
   * Responds to GET requests.
   *
   * Returns the list of allowed configurations.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing the list of allowed configurations.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   When no configurations are allowed by the admin to export.
   */
  public function get() {
    // Get the allowed configurations from the settings.
    $allowed_configurations = $this->configFactory->get('ax_config_export_api.settings')
      ->get('selected_configs') ?? [];

    // Check if any configurations are allowed.
    if (empty($allowed_configurations)) {
      throw new BadRequestHttpException('No configurations have been allowed for viewing by the site administrator.');
    }

    // Create the response.
    $response = new ResourceResponse($allowed_configurations);
    // Add cache tags for invalidation when the configuration is updated.
    if ($response instanceof CacheableResponseInterface) {
      $response->getCacheableMetadata()->addCacheTags(['config:ax_config_export_api.settings']);
    }

    return $response;
  }

}
