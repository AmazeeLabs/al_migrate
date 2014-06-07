<?php
/**
 * @file
 *  Contains class BlogImport
 */

namespace Drupal\al_migrate\Importer;

use Drupal\al_migrate\Fetcher\BlogFetcher;
use Drupal\al_migrate\Importer\UserImport;
use Drupal\al_migrate\MigrateMap;
use Drupal\al_migrate\Console;
use Drupal\Core\Field;

/**
 * Class BlogImport
 * @package Drupal\al_migrate\Importer
 */
class BlogImport {

  /**
   * @var array
   *   Stats array
   */
  private $stats = null;

  /**
   * @var Console
   */
  private $console = null;


  /**
   * Class constructor
   *
   * @param Console &$console
   * @param array &$stats
   */
  public function __construct(Console &$console, array &$stats) {
    $this->console = &$console;
    $this->stats = &$stats;
  }

  /**
   * Import a node
   *
   * @param stdClass $node
   *  Minimal node object as returned by Fetcher\BlogFetcher
   */
  public function importBlogPost(stdClass $node) {
    $this->console->overwrite("Importing node {$node->nid}: {$node->title}");
    $this->console->progressBar(
      $this->stats['node/import'] + $this->stats['node/skip'],
      $this->stats['node/total'],
      'Importing %percent%',
      "Node {$node->nid}: {$node->title}"
    );

    // Check if node was already imported and skip it if so
    $nid = MigrateMap::getDestinationId($node->nid, 'node');
    if ($nid) {
      $this->stats['node/skip']++;
      return;
    }

    // Get the full node object
    $full_node = (object)(new BlogFetcher())->getEntity($node->nid);

    // Process the author
    $this->processAuthor($full_node);

    // Process taxonomy terms
    $this->processTerms($full_node);

    // Process comments
    // @todo

    $basic = [];
    $basic['type'] = 'blog';
    $basic['uid'] = $full_node->uid;
    $basic['title'] = $full_node->title;
    $basic['langcode'] = MigrateMap::lang($full_node->language);
    $basic['created'] = $full_node->created;
    $basic['changed'] = $full_node->changed;
    $new_node = entity_create('node', $basic);
    $new_node->body->summary = $full_node->body['en-US'][0]['summary'];
    $new_node->body->value = $full_node->body['en-US'][0]['value'];
    $new_node->body->format = 'full_html';
    $new_node->field_lead = $this->generateLead($full_node->body['en-US'][0]['value']);
    $new_node->field_imported = 1;
    $new_node->log = 'D7 -> D8 migration';

    // Blog category is something D7 site didn't have, and all blog entries
    // should be imported as 'Blog' (tid: 10). For Events term id 9 should be used
    $new_node->field_category = 10;

    if (!empty($full_node->path)) {
      $new_node->path->alias = str_replace('http://www.amazeelabs.com/en/', 'blog/', $full_node->path);
    }

    if (!empty($full_node->field_blog_category)) {
      $new_node->field_tags->setValue($full_node->field_blog_category);
    }

    // Save everything and add the mapping entry
    $new_node->save();
    if ($new_node->id()) {
      MigrateMap::addMapping($full_node->nid, $new_node->id(), 'node', 1);
      $this->stats['node/import']++;
    }
  }

  /**
   * Maps the old author with the new one
   *
   * @param stdClass $full_node
   *  Full old node
   */
  public function processAuthor(stdClass &$full_node) {
    $new_uid = MigrateMap::getDestinationId($full_node->uid, 'user');
    if (!$new_uid) {
      $new_uid = UserImport::import($full_node->uid);
    }
    else {
      $this->stats['user/skip']++;
    }
    $full_node->uid = $new_uid;
  }

  /**
   * Maps old blog category term to new blog tags
   *
   * @param stdClass $full_node
   *   Full old node
   */
  private function processTerms(stdClass &$full_node) {
    $fields = [
      'field_blog_category'
    ];

    foreach($fields as $field_name) {
      if (!empty($full_node->{$field_name})) {
        // save old values
        $old = $full_node->{$field_name};

        // reset before adding new values
        $full_node->{$field_name} = [];
        foreach($old as $lang => $values) {
          foreach($values as $value) {
            if ($new_tid = MigrateMap::getDestinationId($value['tid'], 'taxonomy_term')) {
              $full_node->{$field_name}[] = $new_tid;
            }
            else {
              // @todo: import the term
            }
          }
        }

        if (count($full_node->{$field_name}) == 1) {
          $full_node->{$field_name} = $full_node->{$field_name}[0];
        }
      }
    }
  }

  /**
   * Generate lead text
   *
   * @param string $text
   *  Full body text
   *
   * @return string
   *  Trimmed version of the text
   */
  private function generateLead($text) {
    // Try to extract the first paragraph that contains something meaningful
    $paragraphs = [];
    preg_match_all('/<p>(.*)<\/p>/iU', $text, $paragraphs);
    if (!empty($paragraphs[1])) {
      // Go through each paragraph an see what's left after strip_tags
      foreach($paragraphs[1] as $p) {
        $paragraph_text = $this->plainText(strip_tags($p, '<p>'));
        if ($paragraph_text != '' && strlen($paragraph_text) > 30) {
          return $paragraph_text;
        }
      }
    }

    // Fallback to Drupal's function if nothing useful found
    return $this->plainText(strip_tags(text_summary($text)));
  }

  /**
   * Convert HTML entities and chars to plain text
   *
   * @param string $text
   *  Text with HTML entities and chars
   *
   * @return string
   *  Clean text
   */
  private function plainText($text) {
    return
    trim(                         // Remove useless spaces after strip_tags
      htmlspecialchars_decode(    // Remove stuff like  #&39; added by text_summary
        html_entity_decode($text) // Remove HTML entities added by text_summary
        , ENT_QUOTES              // Remove all quotes (single and double)
      )
      , " \t\n\r\0\x0B"           // Standard trim() characters
        . chr(0xC2).chr(0xA0)     // Some extra spaces (&nbsp;)
    );
  }

}