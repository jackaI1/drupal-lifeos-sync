<?php

namespace Drupal\lifeos_sync;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;

/**
 * Creates or updates nodes by stable, namespaced source ID.
 */
final class Importer {

  /**
   * Constructs the importer.
   */
  public function __construct(private readonly EntityTypeManagerInterface $entityTypeManager, private readonly LockBackendInterface $lock) {}

  /**
   * Imports a normalized record, preserving the existing node identity.
   */
  public function import(array $item): void {
    $key = 'lifeos_sync.item.' . hash('sha256', $item['id']);
    if (!$this->lock->acquire($key, 30)) {
      throw new \RuntimeException('This source item is already being imported.');
    }
    try {
      $storage = $this->entityTypeManager->getStorage('node');
      $nodes = $storage->loadByProperties(['type' => 'lifeos_item', 'field_lifeos_id' => $item['id']]);
      $node = $nodes ? reset($nodes) : $storage->create(['type' => 'lifeos_item', 'uid' => 0]);
      $node->setTitle($item['title']);
      $node->set('status', $item['published']);
      foreach (['id', 'kind', 'when', 'end', 'course', 'location', 'status'] as $field) {
        $node->set('field_lifeos_' . $field, $item[$field]);
      }
      if ($node->isNew() || $node->hasTranslationChanges()) {
        $node->save();
      }
    }
    finally {
      $this->lock->release($key);
    }
  }

}
