<?php

namespace Drupal\docstore_fts\Controller;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Page controller for Key Figures.
 */
class DataController extends ControllerBase {

  /**
   * The HTTP client to fetch the files with.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(ClientInterface $http_client,
    EntityTypeManagerInterface $entityTypeManager,
  ) {
    $this->httpClient = $http_client;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Update all data.
   */
  public function updateAll() {
    $years = range(2000, date('Y'));
    foreach ($years as $year) {
      $this->updateByYear($year);
    }
  }

  /**
   * Update by year.
   *
   * @param int $year
   *   The year.
   */
  public function updateByYear(int $year) : void {
    $plans = $this->getDataFromApi('public/plan/year/' . $year);
    foreach ($plans as $plan) {
      $data_plan = $this->getDataFromApi('public/plan/id/' . $plan['id']);
      $data_flow = $this->getDataFromApi('public/fts/flow', ['planId' => $plan['id']]);

      /*
      $term_year = $this->getCreateTerm('fts_year', $year);
      $term_country = $this->getCreateTerm('fts_country', $plan['locations'][0]['name'], [
        'iso3' => $plan['locations'][0]['iso3'],
      ]);
      */

      $item = [
        'plan_id' => $plan['id'],
        'name' => $plan['planVersion']['name'],
        'code' => $plan['planVersion']['code'],
        'year' => $year,
        'iso3' => strtolower($plan['locations'][0]['iso3']),
        'country' => $plan['locations'][0]['name'],
        'updated' => $plan['updatedAt'],
        'original_requirements' => $plan['origRequirements'],
        'revised_requirements' => $plan['revisedRequirements'],
        'total_requirements' => $data_plan['revisedRequirements'],
        'funding_total' => $data_flow['incoming']['fundingTotal'],
        'unmet_requirements' => max(0, $data_plan['revisedRequirements'] - $data_flow['incoming']['fundingTotal']),
      ];

      if ($plan = $this->loadPlanByPlanId($plan['id'])) {
        // @todo Implement it.
      }
      else {
        $this->createPlan($item);
      }
    }
  }

  /**
   * Load plan by PlanId.
   */
  public function loadPlanByPlanId($plan_id) {
    $query = $this->entityTypeManager->getStorage('node')->getQuery();
    $query->condition('plan_id', $plan_id);
    $nids = $query->execute();

    if (!empty($nids)) {
      return $this->entityTypeManager->getStorage('node')->load(reset($nids));
    }
  }

  /**
   * Create a new plan.
   */
  public function createPlan($item) {
    $item['type'] = 'fts';
    $item['title'] = $item['name'];
    $this->entityTypeManager->getStorage('node')->create($item)->save();
  }

  /**
   * Get or create term.
   */
  protected function getCreateTerm($vocabulary, $name, $data = []) {
    $possible_terms = $this->entityTypeManager
      ->getStorage('taxonomy_term')
      ->getQuery()
      ->condition('vid', $vocabulary)
      ->condition('name', $name)
      ->execute();

    if (!empty($possible_terms)) {
      return $this->entityTypeManager
        ->getStorage('taxonomy_term')
        ->load(reset($possible_terms));
    }

    $item = [
      'name' => $name,
      'vid' => $vocabulary,
    ] + $data;

    $term = $this->entityTypeManager->getStorage('taxonomy_term')->create($item);
    $term->save();
    return $term;
  }

  /**
   * Load data from API.
   *
   * @param string $path
   *   API path.
   * @param array $query
   *   Query options.
   *
   * @return array
   *   Raw results.
   */
  public function getDataFromApi(string $path, array $query = []) : array {
    $endpoint = $this->config('docstore_fts.settings')->get('fts_api_url');
    $endpoint = 'https://api.hpc.tools/v1/';
    if (empty($endpoint)) {
      return [];
    }

    $headers = [];
    if (strpos($endpoint, '@') !== FALSE) {
      $auth = substr($endpoint, 8, strpos($endpoint, '@') - 8);
      $endpoint = substr_replace($endpoint, '', 8, strpos($endpoint, '@') - 7);
      $headers['Authorization'] = 'Basic ' . base64_encode($auth);
    }

    // Construct full URL.
    $fullUrl = $endpoint . $path;

    if (!empty($query)) {
      $fullUrl = $fullUrl . '?' . UrlHelper::buildQuery($query);
    }

    try {
      $this->getLogger('docstore_fts')->notice('Fetching data from @url', [
        '@url' => $fullUrl,
      ]);

      $response = $this->httpClient->request(
        'GET',
        $fullUrl,
        ['headers' => $headers],
      );
    }
    catch (RequestException $exception) {
      $this->getLogger('docstore_fts')->error('Fetching data from $url failed with @message', [
        '@url' => $fullUrl,
        '@message' => $exception->getMessage(),
      ]);

      if ($exception->getCode() === 404) {
        throw new NotFoundHttpException();
      }
      else {
        throw $exception;
      }
    }

    $body = $response->getBody() . '';
    $results = json_decode($body, TRUE);

    return $results['data'];
  }

}
