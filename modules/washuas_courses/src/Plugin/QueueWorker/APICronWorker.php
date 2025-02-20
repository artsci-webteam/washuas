<?php

namespace Drupal\washuas_courses\Plugin\QueueWorker;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Process a queue of search engines to submit sitemaps.
 *
 * @QueueWorker(
 *   id = "courses_api_request",
 *   title = @Translation("Courses Cron SOAP Import"),
 *   cron = {"time" = 480}
 * )
 *
 * @see washuas_courses_cron()
 */
class APICronWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Logging channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * {@inheritDoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerChannelInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->logger = $logger;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('logger.channel.audit_log'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function processItem($data) {
    batch_set($data);
  }
}
