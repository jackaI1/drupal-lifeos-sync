<?php

/**
 * @file
 * Configures fictional demo content after Drupal and the module are installed.
 */

use Drupal\block\Entity\Block;
use Drupal\user\Entity\Role;

\Drupal::configFactory()->getEditable('system.site')
  ->set('name', 'LifeOS Sync · Drupal demo')
  ->set('page.front', '/upcoming-deadlines')
  ->save();
\Drupal::configFactory()->getEditable('system.date')
  ->set('timezone.default', 'America/Toronto')
  ->save();
\Drupal::configFactory()->getEditable('lifeos_sync.settings')
  ->set('feed_url', getenv('LIFEOS_FEED_URL') ?: 'http://feed/lifeos-public.json')
  ->save();
$role = Role::load('anonymous');
$role->grantPermission('access content')->save();
if (!Block::load('lifeos_demo_deadlines')) {
  Block::create([
    'id' => 'lifeos_demo_deadlines',
    'theme' => 'olivero',
    'region' => 'sidebar',
    'weight' => 0,
    'plugin' => 'views_block:lifeos_upcoming-block_1',
    'settings' => [
      'id' => 'views_block:lifeos_upcoming-block_1',
      'label' => 'Upcoming deadlines',
      'label_display' => 'visible',
      'provider' => 'views',
      'views_label' => '',
    ],
    'visibility' => [],
  ])->save();
}
