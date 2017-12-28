<?php

namespace Drupal\migrate_uploader\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\migrate_uploader\Helpers;
use Symfony\Component\Routing\RouteCollection;

/**
 * Implements the RouteSubscriber class.
 */
class RouteSubscriber extends RouteSubscriberBase {

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
  protected function alterRoutes(RouteCollection $collection) {
    // If there is any migration without a source properly configured, deny
    // access to all routes that expect migrations as complete.
    if (!$this->helpers->migrationWithoutSourceExists()) {
      return;
    }

    $blocked_routes = [
      'entity.migration.source',
      'entity.migration.process',
      'entity.migration.process.run',
      'entity.migration.destination',
    ];
    foreach ($blocked_routes as $route_id) {
      $collection->get($route_id)->setRequirement('_access', 'FALSE');
    }
    // Replace the "execute" callback with our own controller, which shows an
    // informative help text instead.
    $execute_route = $collection->get('migrate_tools.execute');
    if ($execute_route) {
      $defaults = $execute_route->getDefaults();
      unset($defaults['_form']);
      $defaults['_controller'] = '\Drupal\migrate_uploader\Controller\InfoExecuteController::infoText';
      $execute_route->setDefaults($defaults);
    }
  }

}
