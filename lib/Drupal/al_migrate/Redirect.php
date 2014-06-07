<?php
/**
 * @file
 *  Contains class Redirect
 */

namespace Drupal\al_migrate;

use Drupal\al_migrate\MigrateMap;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class Redirect
 * @package Drupal\al_migrate
 */
class Redirect {

  public function redirect($type, $old_id) {

    switch($type) {
      case 'blog':
        $new_id = MigrateMap::getDestinationId($old_id, 'node');
        if ($new_id) {
          return new RedirectResponse(\Drupal::url('node.view', ['node' => $new_id]), 301);
        }
        break;
    }

    // Default redirect
    return new RedirectResponse(\Drupal::url('view.blog.page_1'), 302);
  }

}