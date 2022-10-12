<?php

namespace Drupal\docstore_fts\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\docstore\Controller\DocumentController;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for API endpoints.
 */
class ApiController extends ControllerBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The docstore controller.
   *
   * @var \Drupal\docstore\Controller\DocumentController
   */
  protected $docstoreController;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $configFactory,
      LoggerChannelFactoryInterface $logger_factory,
      DocumentController $docstore_controller
    ) {
    $this->configFactory = $configFactory;
    $this->loggerFactory = $logger_factory;
    $this->docstoreController = $docstore_controller;
  }

  /**
   * Get plans.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   JSON response with the list of resources.
   */
  public function getPlans(Request $request) {
    return $this->docstoreController->getDocuments('fts', $request);
  }

  /**
   * Get a single plan.
   *
   * @param string $id
   *   Plan Id.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   JSON response with the resource's data.
   */
  public function getPlan($id, Request $request) {
    $request->query->add([
      'field[plan_id]' => $id,
    ]);

    return $this->docstoreController->getDocuments('fts', $request);
  }

  /**
   * Get plans by iso3 code.
   *
   * @param string $iso3
   *   Iso 3.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   JSON response with the resource's data.
   */
  public function getPlansByIso3($iso3, Request $request) {
    $request->query->add([
      'filter' => [
        'iso3' => strtolower($iso3),
      ],
    ]);

    return $this->docstoreController->getDocuments('fts', $request);
  }

  /**
   * Get plans by year.
   *
   * @param string $year
   *   Year.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   JSON response with the resource's data.
   */
  public function getPlansByYear($year, Request $request) {
    $request->query->add([
      'filter' => [
        'year' => $year,
      ],
    ]);

    return $this->docstoreController->getDocuments('fts', $request);
  }

}
