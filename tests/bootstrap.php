<?php

/**
 * @file
 * Loads the test framework from a Composer-managed Drupal installation.
 */

$root = getenv('DRUPAL_ROOT') ?: dirname(__DIR__) . '/web';
require $root . '/core/tests/bootstrap.php';
