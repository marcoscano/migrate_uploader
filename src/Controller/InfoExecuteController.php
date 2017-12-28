<?php

namespace Drupal\migrate_uploader\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\migrate_uploader\Helpers;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Produces an informative message to users trying to run incomplete migrations.
 */
class InfoExecuteController extends ControllerBase {

  /**
   * The Helpers service.
   *
   * @var \Drupal\migrate_uploader\Helpers
   */
  protected $helpers;

  /**
   * RouteSubscriber constructor.
   *
   * @param \Drupal\migrate_uploader\Helpers $helpers
   *   The Helpers service.
   */
  public function __construct(Helpers $helpers) {
    $this->helpers = $helpers;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('migrate_uploader.helpers')
    );
  }

  /**
   * Displays an informative message to users.
   *
   * @param string $migration_group
   *   Machine name of the migration's group.
   * @param string $migration
   *   Machine name of the migration.
   *
   * @return array
   *   A render array as expected by drupal_render().
   */
  public function infoText($migration_group, $migration) {
    $build = [
      '#markup' => $this->t('This migration needs a file to be used as source. Please upload a file at the <a href="@upload_url">upload form</a> and try again.', [
        '@upload_url' => Url::fromRoute('migrate_uploader.upload')->toString(),
      ]),
    ];
    $context = 'info_execute';
    $this->moduleHandler()->alter('migrate_uploader_help_text', $build, $context, $migration);
    // If this migration is OK but there are others that aren't, notify the user
    // that all migrations that use upload-like plugins need to have at least
    // one file uploaded to them, otherwise none can be used.
    // @TODO Find a better solution for this.
    if ($this->helpers->migrationWithoutSourceExists()) {
      drupal_set_message($this->t('Please notice that you have at least one migration on your site where a file was never uploaded. In order to run migrations that depend on file uploads, all migrations need to have a file uploaded at least once.'), 'warning');
    }

    return $build;
  }

}
