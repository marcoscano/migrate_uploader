<?php

namespace Drupal\migrate_uploader_csv\Plugin\migrate\source;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_source_csv\Plugin\migrate\source\CSV;
use Drupal\migrate_uploader\MigrateUploaderInterface;

/**
 * Source for CSV file uploaded through the UI.
 *
 * If the CSV file contains non-ASCII characters, make sure it includes a
 * UTF BOM (Byte Order Marker) so they are interpreted correctly.
 *
 * @MigrateSource(
 *   id = "csv_uploader"
 * )
 */
class CSVUploader extends CSV implements MigrateUploaderInterface {

  /**
   * The placeholder to be used when no path was defined yet.
   */
  const EMPTY_PATH_PLACEHOLDER = '[uploader]';

  /**
   * The file path key used in config.
   */
  const FILE_PATH_KEY = 'path';

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
