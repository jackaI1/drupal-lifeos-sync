<?php

namespace Drupal\Tests\lifeos_sync\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;
use Drupal\views\Views;

/**
 * Exercises the real entity importer and queue producer with a database.
 */
#[Group('lifeos_sync')]
class ImporterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field', 'text', 'filter', 'node', 'datetime', 'views', 'lifeos_sync'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    // Drupal 11 sets root before setup; Drupal 10 needs its bootstrap cwd back.
    if (!isset($this->root)) {
      chdir(static::getDrupalRoot());
    }
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('system', ['sequences']);
    $this->installConfig(['system', 'node', 'lifeos_sync']);
  }

  /**
   * Imports all fixture items, then updates in place and hides closed items.
   */
  public function testImportReplayAndUpdate(): void {
    $normalizer = $this->container->get('lifeos_sync.normalizer');
    $importer = $this->container->get('lifeos_sync.importer');
    $data = $this->fixture();
    $items = $normalizer->normalize($data);
    foreach ($items as $item) {
      $importer->import($item);
      $importer->import($item);
    }
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $this->assertCount(5, $storage->loadMultiple());
    $nodes = $storage->loadByProperties(['field_lifeos_id' => 'task:demo-design']);
    $node = reset($nodes);
    $this->assertSame('2099-11-11T04:59:59', $node->get('field_lifeos_when')->value);
    $nid = $node->id();
    $data['tasks'][1]['title'] = 'Updated proposal';
    $data['tasks'][1]['status'] = 'done';
    $data['schedule'][0]['status'] = 'cancelled';
    foreach ($normalizer->normalize($data) as $item) {
      $importer->import($item);
    }
    $storage->resetCache();
    $this->assertSame('Updated proposal', Node::load($nid)->label());
    $this->assertFalse(Node::load($nid)->isPublished());
    $nodes = $storage->loadByProperties(['field_lifeos_id' => 'schedule:demo-workshop']);
    $this->assertFalse(reset($nodes)->isPublished());
    $this->assertCount(5, $storage->loadMultiple());
  }

  /**
   * Invalid snapshot input never changes entities.
   */
  public function testInvalidDateRejected(): void {
    $data = $this->fixture();
    $normalizer = $this->container->get('lifeos_sync.normalizer');
    $data['tasks'][1]['dueDate'] = '2099-02-30';
    $this->expectException(\InvalidArgumentException::class);
    $normalizer->normalize($data);
  }

  /**
   * Queue production respects intervals, backlog, and fetch failure.
   */
  public function testQueueScheduling(): void {
    $body = json_encode($this->fixture());
    $mock = new MockHandler([new Response(200, [], $body), new Response(503)]);
    $this->container->set('http_client', new Client(['handler' => HandlerStack::create($mock)]));
    $config = $this->container->get('config.factory')->getEditable('lifeos_sync.settings');
    $config->set('feed_url', 'https://example.com/feed.json')->set('sync_interval', 3600)->save();
    $manager = $this->container->get('lifeos_sync.manager');
    $this->assertSame(5, $manager->enqueue());
    $this->assertSame(0, $manager->enqueue(TRUE));
    $queue = $this->container->get('queue')->get('lifeos_sync');
    $worker = $this->container->get('plugin.manager.queue_worker')->createInstance('lifeos_sync');
    while ($item = $queue->claimItem()) {
      $worker->processItem($item->data);
      $queue->deleteItem($item);
    }
    $this->assertCount(5, $this->container->get('entity_type.manager')->getStorage('node')->loadMultiple());
    $this->assertSame(0, $manager->enqueue());
    $before = $this->container->get('state')->get('lifeos_sync.last_queued');
    try {
      $manager->enqueue(TRUE);
      $this->fail('A failed fetch must throw.');
    }
    catch (\RuntimeException $exception) {
      $this->assertSame($before, $this->container->get('state')->get('lifeos_sync.last_queued'));
      $this->assertSame(0, (int) $queue->numberOfItems());
    }
  }

  /**
   * Rejects malformed snapshots and ambiguous timestamps before queue writes.
   */
  public function testRejectsMalformedSnapshots(): void {
    $normalizer = $this->container->get('lifeos_sync.normalizer');
    $base = $this->fixture();
    $invalid = [[], array_replace($base, ['timeZone' => 'Not/AZone'])];
    $copy = $base;
    $copy['tasks'][] = $copy['tasks'][0];
    $invalid[] = $copy;
    $copy = $base;
    $copy['tasks'][0]['dueAt'] = '2099-02-30T12:00:00Z';
    $invalid[] = $copy;
    $copy['tasks'][0]['dueAt'] = '2099-11-08T12:00:00';
    $invalid[] = $copy;
    $copy = $base;
    $copy['schedule'][0]['end'] = $copy['schedule'][0]['start'];
    $invalid[] = $copy;
    foreach ($invalid as $feed) {
      try {
        $normalizer->normalize($feed);
        $this->fail('Invalid input must be rejected.');
      }
      catch (\InvalidArgumentException $exception) {
        $this->assertNotSame('', $exception->getMessage());
      }
    }
  }

  /**
   * Oversized and malformed feeds cannot partially populate the queue.
   */
  public function testFeedBoundsAndAtomicValidation(): void {
    $invalid = $this->fixture();
    $invalid['tasks'][2]['dueAt'] = 'invalid';
    $mock = new MockHandler([
      new Response(200, [], str_repeat('x', 1048577)),
      new Response(200, [], json_encode($invalid)),
      new Response(302, ['Location' => 'https://example.com/redirected']),
    ]);
    $this->container->set('http_client', new Client(['handler' => HandlerStack::create($mock)]));
    $this->container->get('config.factory')->getEditable('lifeos_sync.settings')
      ->set('feed_url', 'https://example.com/feed.json')->save();
    $manager = $this->container->get('lifeos_sync.manager');
    $queue = $this->container->get('queue')->get('lifeos_sync');
    for ($attempt = 0; $attempt < 3; $attempt++) {
      try {
        $manager->enqueue(TRUE);
        $this->fail('An invalid snapshot must fail.');
      }
      catch (\RuntimeException | \InvalidArgumentException $exception) {
        $this->assertSame(0, (int) $queue->numberOfItems());
        $this->assertNull($this->container->get('state')->get('lifeos_sync.last_queued'));
      }
    }
  }

  /**
   * Falsey malformed dates must not clear a previously imported deadline.
   */
  public function testMalformedOptionalDates(): void {
    $normalizer = $this->container->get('lifeos_sync.normalizer');
    foreach (['dueAt', 'dueDate'] as $key) {
      foreach ([FALSE, 0, [], '0'] as $invalid) {
        $feed = $this->fixture();
        $feed['tasks'][0]['dueAt'] = NULL;
        $feed['tasks'][0]['dueDate'] = NULL;
        $feed['tasks'][0][$key] = $invalid;
        try {
          $normalizer->normalize($feed);
          $this->fail('Malformed optional dates must reject the snapshot.');
        }
        catch (\InvalidArgumentException $exception) {
          $this->assertNotSame('', $exception->getMessage());
        }
      }
    }
  }

  /**
   * ISO timestamp offsets cannot contain a 24-hour offset or invalid minutes.
   */
  public function testInvalidOffset(): void {
    $normalizer = $this->container->get('lifeos_sync.normalizer');
    foreach (['+24:00', '-25:00', '+05:60'] as $offset) {
      $feed = $this->fixture();
      $feed['tasks'][0]['dueAt'] = '2099-11-08T12:00:00' . $offset;
      try {
        $normalizer->normalize($feed);
        $this->fail('Invalid timezone offsets must reject the snapshot.');
      }
      catch (\InvalidArgumentException $exception) {
        $this->assertNotSame('', $exception->getMessage());
      }
    }
  }

  /**
   * The actual Views page/block exclude events, closed tasks and past dates.
   */
  public function testUpcomingViews(): void {
    $items = $this->container->get('lifeos_sync.normalizer')->normalize($this->fixture());
    foreach ($items as $item) {
      $this->container->get('lifeos_sync.importer')->import($item);
    }
    $view = Views::getView('lifeos_upcoming');
    $view->setDisplay('page_1');
    $view->execute();
    $this->assertCount(3, $view->result);
    $this->assertSame('Thin-film lab report', $view->result[0]->_entity->label());
    $items[2]['when'] = '2000-01-01T00:00:00';
    $this->container->get('lifeos_sync.importer')->import($items[2]);
    $items[3]['published'] = FALSE;
    $this->container->get('lifeos_sync.importer')->import($items[3]);
    $view = Views::getView('lifeos_upcoming');
    $view->setDisplay('block_1');
    $view->execute();
    $this->assertCount(1, $view->result);
    $this->assertSame('Surface science reading', $view->result[0]->_entity->label());
  }

  /**
   * Loads fictional fixture data.
   */
  private function fixture(): array {
    return json_decode(file_get_contents(dirname(__DIR__, 4) . '/fixtures/lifeos-public.json'), TRUE, 512, JSON_THROW_ON_ERROR);
  }

}
