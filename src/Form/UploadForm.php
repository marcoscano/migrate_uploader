<?php

namespace Drupal\migrate_uploader\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\migrate_plus\Entity\Migration;
use Drupal\migrate_uploader\Helpers;
use Drupal\migrate_uploader\MigrateUploaderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form uploader builder class.
 */
class UploadForm extends FormBase {

  /**
   * Plugin manager for migration plugins.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManager
   */
  protected $migrationPluginManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The router builder.
   *
   * @var \Drupal\Core\Routing\RouteBuilderInterface
   */
  protected $routerBuilder;

  /**
   * The Helpers service.
   *
   * @var \Drupal\migrate_uploader\Helpers
   */
  protected $helpers;

  /**
   * Constructs a new MigrationExecuteForm object.
   *
   * @param \Drupal\migrate\Plugin\MigrationPluginManager $migration_plugin_manager
   *   The plugin manager for config entity-based migrations.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Routing\RouteBuilderInterface $router_builder
   *   The router builder service.
   * @param \Drupal\migrate_uploader\Helpers $helpers
   *   The Helpers service.
   */
  public function __construct(MigrationPluginManager $migration_plugin_manager, ModuleHandlerInterface $module_handler, EntityTypeManagerInterface $entity_type_manager, RouteBuilderInterface $router_builder, Helpers $helpers) {
    $this->migrationPluginManager = $migration_plugin_manager;
    $this->moduleHandler = $module_handler;
    $this->entityTypeManager = $entity_type_manager;
    $this->routerBuilder = $router_builder;
    $this->helpers = $helpers;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.migration'),
      $container->get('module_handler'),
      $container->get('entity_type.manager'),
      $container->get('router.builder'),
      $container->get('migrate_uploader.helpers')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'migrate_uploader_upload_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];

    $help_text = [
      '#prefix' => '<h2>',
      '#markup' => $this->t('Upload a file to be used as source'),
      '#suffix' => '</h2>',
    ];
    $context = 'info_upload';
    $this->moduleHandler->alter('migrate_uploader_help_text', $help_text, $context);

    $form['help_text'] = $help_text;

    $migrations = $this->helpers->getActiveMigrations();
    $options = [];
    foreach ($migrations as $group_name => $group) {
      foreach ($group as $migration_id => $migration) {
        // Only include compatible migrations.
        /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
        if ($migration->getSourcePlugin() instanceof MigrateUploaderInterface) {
          $options[$migration_id] = $migration->label();
        }
      }
    }
    if (empty($options)) {
      drupal_set_message($this->t('You need to have at least one active migration that is compatible with Migrate Uploader in order to upload a file.'), 'warning');
      return $form;
    }
    $form['migration'] = [
      '#type' => 'select',
      '#title' => $this->t('Choose the migration to be used with this file'),
      '#options' => $options,
      '#required' => TRUE,
      '#default_value' => key($options),
    ];

    $form['upload'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload'),
      // @TODO: Make this path configurable on the UI.
      '#upload_location' => 'public://migrate_uploader/tmp',
      '#upload_validators' => [
        // @TODO: Make this more flexible (or even non-restricted?)
        'file_validate_extensions' => ['txt csv ods xls xlsx'],
      ],
      '#multiple' => FALSE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if (empty($form_state->getValue(['upload']))) {
      return;
    }

    $fid = reset($form_state->getValue(['upload']));
    $file = $this->entityTypeManager->getStorage('file')->load($fid);
    if (!$file) {
      return;
    }

    /** @var \Drupal\file\FileInterface $file */
    $file->setPermanent();
    $file->save();

    $migration_id = $form_state->getValue('migration');
    $migration = Migration::load($migration_id);
    if (!$migration) {
      // @TODO provide user feedback that this failed.
      return;
    }

    $migration_group = $migration->get('migration_group');
    $migration_group = $migration_group ?: 'default';

    // Move the file out of the temporary folder.
    // @TODO: Make this path configurable on the UI.
    $destination_dir = 'public://migrate_uploader/' . $migration_group . '/' . $migration_id;
    file_prepare_directory($destination_dir, FILE_CREATE_DIRECTORY);
    $destination = $destination_dir . '/' . $file->getFilename();
    $file = file_move($file, $destination, FILE_EXISTS_RENAME);

    $groups = $this->helpers->getActiveMigrations([$migration_id]);
    $migrations = reset($groups);
    /** @var \Drupal\migrate\Plugin\MigrationInterface $migration_plugin */
    $migration_plugin = reset($migrations);
    /** @var \Drupal\migrate_uploader\MigrateUploaderInterface $source_plugin */
    $source_plugin = $migration_plugin->getSourcePlugin();

    $source = $migration->get('source');
    $source[$source_plugin::getFilePathKey()] = $file->getFileUri();
    $migration->set('source', $source);
    $migration->save();
    $this->migrationPluginManager->clearCachedDefinitions();

    // @TODO Do we want to have a DB table with statistics of uploaded files?
    // $this->saveUploadRecord($fid, $migration_id);

    // If there is no other migration on the site without source, redirect the
    // user to the execute page.
    if (!$this->helpers->migrationWithoutSourceExists()) {
      // Clear routing caches so the ones we disabled before can get re-enabled.
      $this->routerBuilder->setRebuildNeeded();

      drupal_set_message($this->t('The upload was successful. You can now execute the migration related to this file.'));

      // Redirect the user to the execute page for this migration.
      $form_state->setRedirect('migrate_tools.execute', [
        'migration_group' => $migration_group,
        'migration' => $migration_id,
      ]);
    }
    else {
      // Inform the user that all migrations need to have at least one uploaded
      // source file in order to be able to use the UI for executing them.
      // @TODO Find a better solution for this.
      drupal_set_message($this->t('Your upload was successful. However, in order to be able to execute migrations through the UI, all active migrations need to have at least one file uploaded. You currently have at least one migration on your site where a file was never uploaded.'), 'warning');
    }
  }

}
