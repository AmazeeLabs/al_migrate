<?php
/**
 * @file
 *  Contains class MigrateMap
 */

namespace Drupal\al_migrate;
use MyProject\Proxies\__CG__\OtherProject\Proxies\__CG__\stdClass;

/**
 * Class MigrateMap
 * @package Drupal\al_migrate
 */
class MigrateMap {
  /**
   * @var array
   *  Static mapping
   */
  private static $static_map = [
    // No need for database queries or so
    'lang' => [
      'en-US' => 'en',
      'de' => 'de',
    ],

    // Added mainly to avoid name conflicts. EntityQuery is case sensitive :( and
    // searching if user name already exist doesn't work well
    'user' => [
          1 =>  1, // Obvious, do not import
        166 => 17, // Danny
      55211 => 26, // Emma
       5153 => 24, // Corina
         80 => 20, // Kathryn
         10 => 18, // Vasi
         41 => 14, // Michael
    ],

    // For terms that already exist in D8
    'taxonomy_term' => [
      // Tags vocabulary
      2 => 13, // Business
      1 => 14, // Drupal
      6 => 16, // Essence of a web week
      3 => 12, // Events
      5 => 17, // Fun
      4 => 15, // Team
    ],
  ];


  /**
   * Get a destination ID for a migration
   *
   * @param $source_id
   * @param $migration
   *
   * @return mixed
   *  Destination ID if exists or false otherwise
   */
  public static function getDestinationId($source_id, $migration) {
    // If there is a statical mapping, use it
    if (isset(self::$static_map[$migration][$source_id])) {
      return self::$static_map[$migration][$source_id];
    }

    $q = db_select('al_migrate_maps', 'm');
    $q->condition('source_id', $source_id);
    $q->condition('migration', $migration);
    $q->addField('m', 'destination_id', 'id');
    return $q->execute()->fetchField();
  }

  /**
   * Add a new mapping into database
   *
   * @param string $source_id
   * @param string $destination_id
   * @param string $migration
   * @param int $rollback
   *   Use this to enable rollback for this mapping
   */
  public static function addMapping($source_id, $destination_id, $migration, $rollback = 0) {
    $record = (object)array(
      'source_id' => $source_id,
      'destination_id' => $destination_id,
      'migration' => $migration,
      'updated' => time(),
      'rollback' => $rollback,
    );
    drupal_write_record('al_migrate_maps', $record);
  }

  /**
   * Get the corresponding language on D8 for an old language code
   *
   * If no corresponding language is found, language code for english is returned
   *
   * @param string $language_code
   *  Old language code
   *
   * @return string
   *  New language code
   */
  public static function lang($language_code) {
    $mapping = self::getDestinationId($language_code, 'lang');
    return $mapping ? $mapping : 'en';
  }

  /**
   * Get all the mappings for all or a specific migration
   *
   * @param string $migration
   *  Migration name
   *
   * @return array
   *  Mapping objects
   */
  public static function getAllMappings($migration = null) {
    $q = db_select('al_migrate_maps', 'm')->fields('m');;
    if ($migration !== null) {
      $q->condition('migration', $migration);
    }

    return (array)$q->execute()->fetchAll();
  }

  /**
   * Rollback item
   *
   * @param stdClass $item
   *  Mapping object
   */
  public static function rollback(stdClass $item) {
    if ($item->rollback) {
      switch($item->migration) {
        case 'user':
        case 'node':
          entity_delete_multiple($item->migration, array($item->destination_id));
          break;
        default:
          watchdog('al_migrate', 'Unknown migration to rollback: @migration', ['@migration' => $item->migration]);
      }
    }

    db_delete('al_migrate_maps')
      ->condition('source_id', $item->source_id)
      ->condition('migration', $item->migration)
      ->execute();
  }
}