<?php
/**
 * @file
 *  Contains class BlogFetcher
 */

namespace Drupal\al_migrate\Fetcher;

/**
 * Class BlogFetcher
 * @package Drupal\al_migrate\Fetcher
 */
class BlogFetcher extends EntityFetcher {

  /**
   * Class constructor
   */
  public function __construct() {
    parent::__construct('node', ['type' => 'blog']);
  }
}