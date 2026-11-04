<?php

namespace Drupal\lifeos_sync\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\lifeos_sync\Importer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Imports validated records; exceptions leave the job available for retry.
 */
#[QueueWorker(id: 'lifeos_sync', title: new TranslatableMarkup('LifeOS Sync'), cron: ['time' => 30])]
final class LifeosSyncWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a worker with the shared importer.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, private readonly Importer $importer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('lifeos_sync.importer'));
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $this->importer->import($data);
  }

}
