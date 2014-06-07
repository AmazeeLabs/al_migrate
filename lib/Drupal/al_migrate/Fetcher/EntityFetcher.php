<?php
/**
 * @file
 *  Contains class EntityFetcher
 */

namespace Drupal\al_migrate\Fetcher;

/**
 * Class EntityFetcher
 * @package Drupal\al_migrate\Fetcher
 */
abstract class EntityFetcher implements \Iterator {

  /**
   * @var string
   *  Entity parameters, bundle
   */
  private $params = array();

  /**
   * @var string
   *  Entity type
   */
  private $entity_type = '';

  /**
   * @var string
   *  API end point
   */
  private $api_end_point = 'http://www.amazeelabs.com/en/blog_migrate';

  /**
   * @var array
   *  Auth arguments
   */
  private $auth = ['admin', 'pass'];

  /**
   * @var array
   *  Fetched items
   */
  private $items = null;

  /**
   * @var int
   *  Iterator position
   */
  private $position = null;


  /**
   * Class constructor
   *
   * @param string $entity_type
   *  Entity type
   * @param array $params
   *  Query arguments for the request
   */
  public function __construct($entity_type, array $params) {
    $this->entity_type = $entity_type;
    $this->params = $params;
  }

  /**
   * Get entity count
   *
   * @todo: find a more optimal way to do this (do not download everything)
   *  - Not sure if Services for D7 provides this out of the box or can be done
   *    only with Views and "Services Views" module
   *
   * @return int
   */
  public function getCount() {
    if ($this->position === null || $this->items === null) {
      // Fetch the items first
      $this->rewind();
    }

    return count($this->items);
  }

  /**
   * Get full entity
   *
   * @todo: Handle request exceptions
   *
   * @param string $entity_id
   *  Entity ID
   *
   * @return array
   *  Full entity object
   */
  public function getEntity($entity_id) {
    $url = $this->api_end_point . '/' . $this->entity_type . "/" . $entity_id;
    $request = \Drupal::service('http_client')->get($url, ['auth' => $this->auth]);
    return $request->json();
  }

  /**
   * Iterator::rewind() implementation
   *
   * @todo: handle request exceptions
   *
   */
  public function rewind() {
    $this->position = 0;

    // Fetch items
    $query = ['pagesize' => 9999, 'parameters' => $this->params];
    $url = $this->api_end_point . "/" . $this->entity_type . '?' .
      \Drupal\Component\Utility\UrlHelper::buildQuery($query);

    $client = \Drupal::service('http_client');
    $request = $client->get($url, ['auth' => $this->auth]);
    $this->items = $request->json();
  }

  /**
   * Iterator::current() implementation
   *
   * @return mixed
   */
  public function current() {
    return $this->items[$this->position];
  }

  /**
   * Iterator::key() implementation
   *
   * @return int
   */
  function key() {
    return $this->position;
  }

  /**
   * Iterator::next() implementation
   */
  public function next() {
    ++$this->position;
  }

  /**
   * Iterator::valid() implementation
   *
   * @return bool
   */
  public function valid() {
    return isset($this->items[$this->position]);
  }

}
