<?php

/**
 * @file
 * Loads the test framework from a Composer-managed Drupal installation.
 */

$root = getenv('DRUPAL_ROOT') ?: dirname(__DIR__) . '/web';
require $root . '/core/tests/bootstrap.php';

// Drupal 10's bootstrap changes cwd to web/. PHPUnit 9 discovers suite paths
// afterward; restore the project root. KernelTestBase sets cwd again per test.
chdir(dirname(__DIR__));
