<?php
/**
 * @file
 *  Contains class UserImport
 */

namespace Drupal\al_migrate\Importer;

use Drupal\al_migrate\Fetcher\UserFetcher;
use Drupal\al_migrate\MigrateMap;

/**
 * Class UserImport
 * @package Drupal\al_migrate\Importer
 */
class UserImport {

  /**
   * Import one user ID
   *
   * @param int $uid
   *  Old user ID
   *
   * @return int
   *  User ID in D8
   */
  public static function import($uid) {
    // Make sure we don't import duplicates
    $import_uid = self::getAccountKeepID($uid);

    if (MigrateMap::getDestinationId($import_uid, 'user')) {
      // This is a duplicate, the account we want to keep was already imported,
      // add the mapping for the duplicate id
      if ($uid != $import_uid) {
        MigrateMap::addMapping($uid, MigrateMap::getDestinationId($import_uid, 'user'), 'user');
      }
      return MigrateMap::getDestinationId($import_uid, 'user');
    }

    // get full entity
    $user = (object)(new UserFetcher())->getEntity($import_uid);

    // The mapping might be wrong, or the user might be already imported, check
    // if email or name already exists, and reuse it
    if ($existing_uid = self::getExisting($user)) {
      MigrateMap::addMapping($import_uid, $existing_uid, 'user');
      if ($uid != $import_uid) {
        MigrateMap::addMapping($uid, $existing_uid, 'user');
      }
      watchdog('al_migrate', "User @uid already existed in D8 as @new_uid", array('@uid' => $uid, '@new_uid' => $existing_uid));
      return $existing_uid;
    }

    $new_user = array(
      'name' => $user->name,
      'mail' => $user->mail,
      'pass' => '#1$ecret@amazeelabs',
      'status' => $user->status,
      'field_firstname' => $user->field_first_name['und'][0]['value'],
      'field_lastname' => $user->field_last_name['und'][0]['value'],

      'roles' => self::matchRoles($user->roles),

      // Some hidden values
      'created' => $user->created,
      'access' => $user->access,
      'login' => $user->login,
      'init' => $user->init,

      // Some default values
      'langcode' => 'en',
      'signature_format' => 'basic_html',
      'preferred_langcode' => 'en',
      'preferred_admin_langcode' => 'en',
    );
    $account = entity_create('user', $new_user);
    $account->save();

    // save the mapping
    if ($account->id()) {
      MigrateMap::addMapping($import_uid, $account->id(), 'user', 1);

      // Add also a mapping for duplicate user
      if ($uid != $import_uid) {
        MigrateMap::addMapping($uid, $account->id(), 'user');
      }

      return $account->id();
    }

    return 0;
  }

  /**
   * Returns a list of valid user roles based on the old user roles
   *
   * @param array $old_roles
   *  Names of old user roles
   *
   * @return array
   *  IDs of new user roles
   */
  public static function matchRoles($old_roles) {
    static $valid_roles = null;
    if ($valid_roles === null) {
      $valid_roles = user_role_names();
    }

    return array_keys(array_intersect_key($valid_roles, array_flip($old_roles)));
  }

  /**
   * Returns the office for based on the uid
   *
   * @param int $uid
   *  Old user database
   *
   * @return string
   *  Office ID
   */
  public static function getOffice($uid) {
    $austin =[
        80, // Kathryn
      1273, // Andrew
      1066, // Andrew 2
    ];
    return in_array($uid, $austin) ? 'austin' : 'zurich';
  }

  /**
   * Get the account uid to keep for a duplicate uid
   *
   * @param int $uid
   *  Duplicate uid
   *
   * @return int
   *  Account to keep uid or original uid in case is not a duplicate
   */
  public function getAccountKeepID($uid) {
    $duplicates = [
        27 =>   10, // Vasi
      1272 => 1066, // Andrew
      1669 => 1619, // Boris
      3911 =>  552, // Dagmar
        86 =>   41, // Michael
    ];

    return isset($duplicates[$uid]) ? $duplicates[$uid] : $uid;
  }

  /**
   * Get existing user
   *
   * @param object $user
   *  Old user object
   *
   * @return int
   *  Existing user ID
   */
  private static function getExisting($user) {
    $q = \Drupal::entityQuery('user');
    $q->condition(
      $q->orConditionGroup()
        ->condition('mail', $user->mail)
        ->condition('name', trim($user->name))
        ->condition('name', ucwords(trim($user->name)))
    );
    $q->range(0, 1);
    $existing = $q->execute();

    return empty($existing) ? 0 : array_shift($existing);
  }
}