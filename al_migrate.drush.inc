<?php

use Drupal\al_migrate;

/**
 * Implementation of hook_drush_command().
 */
function al_migrate_drush_command() {
  return [
    'al-migrate-import' => [
      'description' => 'Migrates blog nodes from the D7 site.',
      'aliases' => ['almi'],
      'options' => [
        'limit' => [
          'description' => 'Maximum number of items to import',
          'example-value' => '10',
        ],
      ],
      'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_FULL,
    ],
    'al-migrate-rollback' => [
      'description' => 'Rollbacks all imported entities',
      'aliases' => ['almr'],
      'limit' => [
        'description' => 'Maximum number of items to import',
        'example-value' => '10',
      ],
      'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_FULL,
    ],
  ];
}

function drush_al_migrate_import() {
  $console = new al_migrate\Console;
  $console->printHeader();

  $limit = null;
  if (drush_get_option('limit')) {
    $limit = drush_get_option('limit');
  }
  /**
   * @var array
   *  General statistics for this migration
   */
  $stats = [
    'time/start'  => 0, // Start time
    'time/stop'   => 0, // End time
    'node/total'  => 0, // Nodes skipped (already imported)
    'node/skip'   => 0, // Nodes skipped (already imported)
    'node/import' => 0, // Nodes imported
    'user/import' => 0, // Users imported
    'user/skip'   => 0, // Users skipped (already imported)
  ];

  // Fetch a list with all blog nodes
  $stats['time/start'] = microtime(true);
  $blogFetcher = new al_migrate\Fetcher\BlogFetcher();
  $stats['node/total'] = min($blogFetcher->getCount(), $limit ? $limit : $blogFetcher->getCount());
  $blogPostImporter = new al_migrate\Importer\BlogImport($console, $stats);

  // Parse each blog post and import it
  $console->log("Blog import (" . $stats['node/total'] . " nodes)");
  foreach($blogFetcher as $node) {
    $blogPostImporter->importBlogPost((object)$node);

    if ($limit && $stats['node/import'] >= $limit) {
      break;
    }
  }

  $console->overwrite('Done', true);
  $stats['time/stop'] = microtime(true);
  $console->log("\nFinished (" . round($stats['time/stop'] - $stats['time/start'], 5) . ' seconds)');
}

function drush_al_migrate_rollback() {
  $console = new al_migrate\Console();
  $console->printHeader();
  $stats = [
    'node' => ['total' => 0, 'current' => 0],
    'user' => ['total' => 0, 'current' => 0],
  ];

  $console->log('Rolling back blog import');

  $node_mappings = al_migrate\MigrateMap::getAllMappings('node');
  $stats['node']['total'] = count($node_mappings);
  foreach($node_mappings as $item) {
    al_migrate\MigrateMap::rollback($item);
    $stats['node']['current']++;
    $console->progressBar($stats['node']['current'], $stats['node']['total'], 'Rolling back nodes', '%percent%');
  }
  $console->overwrite('Done', true);
}
