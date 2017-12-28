<?php

namespace Drupal\migrate_uploader;

/**
 * Defines an interface for migrate_uploader compatible plugins.
 */
interface MigrateUploaderInterface {

  /**
   * Checks whether this migration source has a valid path.
   *
   * @return bool
   *   TRUE if a file was previously uploaded for this source, FALSE otherwise.
   */
  public function hasValidFilePath();

  /**
   * Retrieve the key used to store the file path in the config.
   *
   * @return string
   *   A key that is used for the file path in the config object.
   */
  public static function getFilePathKey();

}
