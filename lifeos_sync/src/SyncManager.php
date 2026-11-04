<?php

namespace Drupal\lifeos_sync;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;

/**
 * Schedules complete snapshots without accumulating repeated queue backlogs.
 */
final class SyncManager {

  /**
   * Constructs the queue producer.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly StateInterface $state,
    private readonly TimeInterface $time,
    private readonly QueueFactory $queueFactory,
    private readonly LockBackendInterface $lock,
    private readonly Connection $database,
    private readonly FeedClient $feedClient,
  ) {}

  /**
   * Queues a validated snapshot; force bypasses only the time interval.
   */
  public function enqueue(bool $force = FALSE): int {
    if (!$this->lock->acquire('lifeos_sync.enqueue', 60)) {
      return 0;
    }
    try {
      $config = $this->configFactory->get('lifeos_sync.settings');
      $url = $config->get('feed_url');
      $now = $this->time->getCurrentTime();
      if (!$url || (!$force && $now - (int) $this->state->get('lifeos_sync.last_queued', 0) < (int) $config->get('sync_interval'))) {
        return 0;
      }
      $queue = $this->queueFactory->get('lifeos_sync');
      $queue->createQueue();
      if ($queue->numberOfItems() > 0) {
        return 0;
      }
      $items = $this->feedClient->fetch($url);
      // The supported default DatabaseQueue shares this transaction. A failed
      // insertion rolls back the snapshot rather than leaving half a feed.
      $transaction = $this->database->startTransaction();
      try {
        foreach ($items as $item) {
          if ($queue->createItem($item) === FALSE) {
            throw new \RuntimeException('Unable to queue the complete snapshot.');
          }
        }
        $this->state->set('lifeos_sync.last_queued', $now);
      }
      catch (\Throwable $exception) {
        $transaction->rollBack();
        throw $exception;
      }
      unset($transaction);
      return count($items);
    }
    finally {
      $this->lock->release('lifeos_sync.enqueue');
    }
  }

}
