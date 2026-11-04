<?php

namespace Drupal\lifeos_sync\Drush\Commands;

use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\lifeos_sync\SyncManager;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Provides the same feed import pipeline to Drush 13.
 */
final class LifeosSyncCommands extends DrushCommands {

  use AutowireTrait;

  /**
   * Constructs commands with Drupal services.
   */
  public function __construct(
    private readonly SyncManager $syncManager,
    #[Autowire(service: 'queue')]
    private readonly QueueFactory $queueFactory,
    #[Autowire(service: 'plugin.manager.queue_worker')]
    private readonly QueueWorkerManagerInterface $workerManager,
  ) {
    parent::__construct();
  }

  /**
   * Fetches a public snapshot and imports queued records.
   */
  #[CLI\Command(name: 'lifeos:sync', aliases: ['lifeos-sync'])]
  #[CLI\Option(name: 'force', description: 'Bypass the interval; pending jobs still prevent another fetch.')]
  #[CLI\Option(name: 'enqueue-only', description: 'Leave queued records for cron or drush queue:run lifeos_sync.')]
  #[CLI\Usage(name: 'drush lifeos:sync --force', description: 'Queue and import the public feed immediately.')]
  public function sync(array $options = ['force' => FALSE, 'enqueue-only' => FALSE]): int {
    try {
      $queued = $this->syncManager->enqueue((bool) $options['force']);
      $processed = 0;
      if (!$options['enqueue-only']) {
        $queue = $this->queueFactory->get('lifeos_sync');
        $queue->createQueue();
        $worker = $this->workerManager->createInstance('lifeos_sync');
        while ($item = $queue->claimItem(60)) {
          try {
            $worker->processItem($item->data);
            $queue->deleteItem($item);
            $processed++;
          }
          catch (\Throwable $exception) {
            $queue->releaseItem($item);
            throw $exception;
          }
        }
      }
      $this->io()->success(sprintf('Queued %d records; imported %d records.', $queued, $processed));
      return 0;
    }
    catch (\Throwable $exception) {
      $this->io()->error('LifeOS sync failed. Check the public feed and configuration. Queued records remain available for retry.');
      return 1;
    }
  }

}
