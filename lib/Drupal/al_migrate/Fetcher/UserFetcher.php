<?php
/**
 * @file
 *  Contains class UserFetcher
 */

namespace Drupal\al_migrate\Fetcher;

/**
 * Class BlogFetcher
 * @package Drupal\al_migrate\Fetcher
 */
class UserFetcher extends EntityFetcher {

  /**
   * Class constructor
   */
  public function __construct() {
    parent::__construct('user', []);
  }
}