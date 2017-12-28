<?php

namespace Drupal\migrate_uploader;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\migrate\Exception\RequirementsException;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\migrate\Plugin\RequirementsInterface;

/**
 * Helper methods when dealing with migrations.
 */
class Helpers {

  /**
   * The migration plugin manager.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManagerInterface
   */
  protected $migrationPluginManager;

  /**
   * Logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(MigrationPluginManagerInterface $migration_plugin_manager, LoggerChannelFactoryInterface $loggerFactory) {
    $this->migrationPluginManager = $migration_plugin_manager;
    $this->loggerFactory = $loggerFactory;
  }

  /**
   * Get active migrations, optionally filtered by some criteria.
   *
   * @param array $migration_ids
   *   (optional) An indexed array of migration IDs, or an empty array to
   *   retrieve all of them. Defaults to an empty array.
   * @param array $source_plugins
   *   (optional) An indexed array of source plugin IDs, or an empty array to
   *   retrieve all of them. Defaults to an empty array.
   * @param array $groups
   *   (optional) An indexed array of group names, or an empty array to retrieve
   *   all of them. Defaults to an empty array.
   * @param array $tags
   *   (optional) An indexed array of tags, or an empty array to retrieve all of
   *   them. Defaults to an empty array.
   *
   * @return array
   *   An associative array, where keys are migration group IDs, and values are
   *   arrays containing the migrations for that group. These will be keyed by
   *   their migration IDs and their values will be the fully-loaded migration
   *   objects, that implement \Drupal\migrate\Plugin\MigrationInterface. Will
   *   return an empty array if no migrations exist or if none matches the
   *   filtering criteria passed in.
   */
  public function getActiveMigrations(array $migration_ids = [], array $source_plugins = [], array $groups = [], array $tags = []) {
    $filter['source_plugins'] = $source_plugins;
    $filter['migration_group'] = $groups;
    $filter['migration_tags'] = $tags;

    $plugins = $this->migrationPluginManager->createInstances([]);
    $matched_migrations = [];

    // Get the set of migrations that may be filtered.
    if (empty($migration_ids)) {
      $matched_migrations = $plugins;
    }
    else {
      // Get the requested migrations.
      $migration_ids = array_intersect(array_keys($plugins), $migration_ids);
      foreach ($plugins as $id => $migration) {
        if (in_array($id, $migration_ids)) {
          $matched_migrations[$id] = $migration;
        }
      }
    }

    // Do not return any migrations which fail to meet requirements.
    /** @var \Drupal\migrate\Plugin\Migration $migration */
    foreach ($matched_migrations as $id => $migration) {
      if ($migration->getSourcePlugin() instanceof RequirementsInterface) {
        try {
          $migration->getSourcePlugin()->checkRequirements();
        }
        catch (RequirementsException $e) {
          unset($matched_migrations[$id]);
        }
      }
    }

    // Filters the matched migrations if a group or a tag has been input.
    if (!empty($filter['migration_group']) || !empty($filter['migration_tags'])) {
      // Get migrations in any of the specified groups and with any of the
      // specified tags.
      foreach ($filter as $property => $values) {
        if (!empty($values)) {
          $filtered_migrations = [];
          foreach ($values as $search_value) {
            foreach ($matched_migrations as $id => $migration) {
              // Cast to array because migration_tags can be an array.
              $configured_values = (array) $migration->get($property);
              $configured_id = (in_array($search_value, $configured_values)) ? $search_value : 'default';
              if (empty($search_value) || $search_value == $configured_id) {
                if (empty($migration_ids) || in_array(Unicode::strtolower($id), $migration_ids)) {
                  $filtered_migrations[$id] = $migration;
                }
              }
            }
          }
          $matched_migrations = $filtered_migrations;
        }
      }
    }

    // Filter by source plugin id, if set.
    if (!empty($filter['source_plugins'])) {
      foreach ($matched_migrations as $migration_id => $migration) {
        if (!in_array($migration->getSourcePlugin()->getPluginId(), $filter['source_plugins'])) {
          unset($matched_migrations[$migration_id]);
        }
      }
    }

    // Sort the matched migrations by group.
    if (!empty($matched_migrations)) {
      foreach ($matched_migrations as $id => $migration) {
        $configured_group_id = empty($migration->get('migration_group')) ? 'default' : $migration->get('migration_group');
        $migrations[$configured_group_id][$id] = $migration;
      }
    }
    return isset($migrations) ? $migrations : [];
  }

  /**
   * Check if there is any migration that never had a file uploaded.
   *
   * @return bool
   *   TRUE if there is at least one migration with an "empty" source path, or
   *   FALSE if a file was uploaded at least once to all migrations on the site.
   */
  public function migrationWithoutSourceExists() {
    $migrations_without_source = FALSE;

    $migrations = (array) $this->getActiveMigrations();
    foreach ($migrations as $group_id => $group) {
      foreach ($group as $migration_id => $migration) {
        /** @var \Drupal\migrate\Plugin\Migration $migration */
        $source_plugin = $migration->getSourcePlugin();
        if ($source_plugin instanceof MigrateUploaderInterface && !$source_plugin->hasValidFilePath()) {
          $migrations_without_source = TRUE;
          break;
        }
      }
    }

    return $migrations_without_source;
  }

}
