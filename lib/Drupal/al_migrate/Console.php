<?php
/**
 * @file
 *  Contains class Console
 */

namespace Drupal\al_migrate;

use \Drupal\Component\Utility\Unicode;

/**
 * Class Console
 */
class Console {

  /**
   * Prints migration header
   */
  public function printHeader() {
    $this->log("\nAmazeeLabs D7 -> D8 Migration (blog entries, users, comments\n");
  }

  /**
   * Print a text to STDOUT
   *
   * @param string $text
   *  Text out
   * @param bool $new_line
   *  If this is true a new line character is added at the end of the line
   */
  public function log($text = "\n", $new_line = true) {
    echo $text . ($new_line ? "\n" : '');
  }

  /**
   * Overwrites last line by appending \r in front of the text
   *
   * Also eliminates \r or \n from the text
   *
   * @param string $text
   *  Text to display
   * @param bool $final
   *  If this is set to true, overwriting stops
   */
  public function overwrite($text, $final = false) {
    // Remove unwanted chars
    $text = str_replace(array("\n", "\r"), "", $text);

    /**
     * @var int
     *   Save the last string length to make sure we add enough spaces to
     *   "clear" the old text
     */
    static $last_length = 0;
    $text_length = Unicode::strlen($text);
    if ($text_length < $last_length) {
      $text .= str_pad('', $last_length - $text_length , ' ', STR_PAD_RIGHT);;
    }
    $last_length = $final ? 0 : $text_length;

    $this->log($text . ($final ? "\n" : "\r"), false);
  }

  /**
   * Prints a progress bar
   *
   * @param int $step
   *  Current step/progress
   * @param int $total
   *  Total steps
   * @param string $before_text
   *  Text to display along progress bar
   * @param string $after_text
   *  Text to display along progress bar
   */
  public function progressBar($step, $total, $before_text = '', $after_text = '') {
    $bar_width = 40;

    // Progress chars
    $progress_width = round($bar_width * $step / $total);
    $line = str_pad('', $progress_width - 1, '=') . '>';
    $line = str_pad($line, $bar_width, ' '); // Fill in remaining
    $line = '[' . $line . ']';

    // Replacing strings for text
    $replace = [
      '%percent%' => str_pad(number_format(100 * $step / $total, 1, '.', '') . '%', 6, ' ', STR_PAD_LEFT),
    ];

    if ($before_text) {
      $before_text = str_replace(array_keys($replace), $replace, $before_text);
      $line = "$before_text $line";
    }

    if ($after_text) {
      $after_text = str_replace(array_keys($replace), $replace, $after_text);
      $line = "$line $after_text";
    }

    $this->overwrite($line);
  }
}