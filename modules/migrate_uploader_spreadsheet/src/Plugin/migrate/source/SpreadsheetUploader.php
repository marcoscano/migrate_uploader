<?php

namespace Drupal\migrate_uploader_spreadsheet\Plugin\migrate\source;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_spreadsheet\Plugin\migrate\source\Spreadsheet;
use Drupal\migrate_uploader\MigrateUploaderInterface;

/**
 * Source for Spreadsheet file uploaded through the UI.
 *
 * @MigrateSource(
 *   id = "spreadsheet_uploader"
 * )
 */
class SpreadsheetUploader extends Spreadsheet implements MigrateUploaderInterface {

  /**
   * The placeholder to be used when no path was defined yet.
   */
  const EMPTY_PATH_PLACEHOLDER = '[uploader]';

  /**
   * The file path key used in config.
   */
  const FILE_PATH_KEY = 'file';

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration) {
    // Use a placeholder for the path, if empty.
    if (empty($configuration[self::FILE_PATH_KEY])) {
      $configuration[self::FILE_PATH_KEY] = self::EMPTY_PATH_PLACEHOLDER;
    }

    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
  }

  /**
   * {@inheritdoc}
   */
  public function initializeIterator() {
    if ($this->configuration[self::FILE_PATH_KEY] === self::EMPTY_PATH_PLACEHOLDER) {
      // No path was yet defined for the source.
      return new \ArrayIterator();
    }

    return parent::initializeIterator();
  }

  /**
   * {@inheritdoc}
   */
  public static function getFilePathKey() {
    return self::FILE_PATH_KEY;
  }

  /**
   * {@inheritdoc}
   */
  public function hasValidFilePath() {
    return !empty($this->configuration[self::FILE_PATH_KEY]) && $this->configuration[self::FILE_PATH_KEY] !== self::EMPTY_PATH_PLACEHOLDER;
  }

}
