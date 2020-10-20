<?php

namespace Drupal\docstore\EventSubscriber;

use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\StorageTransformEvent;
use Drupal\Core\Site\Settings;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The event subscriber preventing config to be exported.
 */
final class DocstoreConfigEventSubscriber implements EventSubscriberInterface {

  /**
   * Storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  private $active;

  /**
   * Drupal settings.
   *
   * @var \Drupal\Core\Site\Settings
   */
  private $settings;

  /**
   * Config manager.
   *
   * @var \Drupal\Core\Config\ConfigManagerInterface
   */
  private $manager;

  /**
   * DocstoreConfigEventSubscriber constructor.
   *
   * @param \Drupal\Core\Config\StorageInterface $active
   *   The active config storage.
   * @param \Drupal\Core\Site\Settings $settings
   *   The drupal settings.
   * @param \Drupal\Core\Config\ConfigManagerInterface $manager
   *   The config manager.
   */
  public function __construct(StorageInterface $active, Settings $settings, ConfigManagerInterface $manager) {
    $this->active = $active;
    $this->settings = $settings;
    $this->manager = $manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // React early on export and late on import.
    return [
      'config.transform.import' => ['onConfigTransformImport', -500],
      'config.transform.export' => ['onConfigTransformExport', 500],
    ];
  }

  /**
   * Transform the storage which is used to import the configuration.
   *
   * Make sure existing fields and vocabularies aren't deleted.
   *
   * @param \Drupal\Core\Config\StorageTransformEvent $event
   *   The transformation event.
   */
  public function onConfigTransformImport(StorageTransformEvent $event) {
    $storage = $event->getStorage();
    if (!$storage->exists('core.extension')) {
      return;
    }
  }

  /**
   * Transform the storage which is used to export the configuration.
   *
   * Make sure provider fields and vocabularies aren't exported.
   *
   * @param \Drupal\Core\Config\StorageTransformEvent $event
   *   The transformation event.
   */
  public function onConfigTransformExport(StorageTransformEvent $event) {
    $storage = $event->getStorage();
    if (!$storage->exists('core.extension')) {
      return;
    }

    foreach (array_merge([StorageInterface::DEFAULT_COLLECTION], $storage->getAllCollectionNames()) as $collectionName) {

      $collection = $storage->createCollection($collectionName);
      foreach ($this->getConfigNames() as $configName) {
        $collection->delete($configName);
      }

      // Remove config created by providers.
      foreach ($collection->listAll() as $configName) {
        // Ignore index.
        if ($configName === 'search_api.index.documents') {
          $collection->delete($configName);
        }

        // Ignore all vocabularies.
        if (strpos($configName, 'taxonomy.vocabulary.') === 0) {
          $collection->delete($configName);
        }

        // Ignore all taxonomy fields.
        if (strpos($configName, 'field.storage.taxonomy_term.') === 0) {
          // Except base_provider_uuid and created.
          if ($configName === 'field.storage.taxonomy_term.base_provider_uuid') {
            continue;
          }

          if ($configName === 'field.storage.taxonomy_term.created') {
            continue;
          }

          $collection->delete($configName);
        }

        if (strpos($configName, 'field.field.taxonomy_term.') === 0) {
          $collection->delete($configName);
        }

        // Ignore all document fields, except base_ fields.
        if (strpos($configName, 'field.storage.node.') === 0 && strpos($configName, 'field.storage.node.base_') === FALSE) {
          $collection->delete($configName);
        }

        if (strpos($configName, 'field.field.node.document.') === 0 && strpos($configName, 'field.field.node.document.base_') === FALSE) {
          $collection->delete($configName);
        }
      }
    }

    $extension = $storage->read('core.extension');
    // Remove all the excluded modules from the extensions list.
    $extension['module'] = array_diff_key($extension['module'], array_flip($this->getExcludedModules()));

    $storage->write('core.extension', $extension);
  }

  /**
   * Get the modules set as excluded in the drupal settings.
   *
   * @return string[]
   *   List of modules.
   */
  private function getExcludedModules() {
    return $this->settings->get("config_exclude_modules", [
      'devel',
      'devel_php',
      'stage_file_proxy',
      'dblog',
    ]);
  }

  /**
   * Get all the configuration which depends on one of the excluded modules.
   *
   * @return string[]
   *   List of config names.
   */
  private function getConfigNames() {
    $modules = $this->getExcludedModules();

    $dependencyManager = $this->manager->getConfigDependencyManager();
    $config = [];

    foreach ($modules as $module) {
      foreach ($dependencyManager->getDependentEntities('module', $module) as $dependent) {
        $config[] = $dependent->getConfigDependencyName();
      }
      $config = array_merge($config, $this->active->listAll($module . '.'));
    }

    foreach ($this->manager->findConfigEntityDependents('config', array_unique($config)) as $dependent) {
      $config[] = $dependent->getConfigDependencyName();
    }

    return array_unique($config);
  }

}
