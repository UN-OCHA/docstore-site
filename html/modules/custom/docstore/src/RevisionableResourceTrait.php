<?php

namespace Drupal\docstore;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\RevisionableEntityBundleInterface;
use Drupal\user\UserInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Trait for revisionable resrouces.
 */
trait RevisionableResourceTrait {

  use ProviderTrait;
  use ResourceTrait;

  /**
   * Load 1 revision of the given resource from the database.
   *
   * @param string $id
   *   Resource id or uuid.
   * @param string $revision_id
   *   Revision id.
   * @param string $entity_type_id
   *   Entity type (ex: node, taxonomy_term, media).
   * @param \Drupal\Core\Config\Entity\ConfigEntityBundleBase|null $bundle_entity
   *   Bundle entity for the resource. If not defined, then search against all
   *   resources of the given type.
   * @param \Drupal\user\UserInterface|null $provider
   *   Provider.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The revision entity.
   */
  public function loadResourceEntityRevision($id, $revision_id, $entity_type_id, ?ConfigEntityBundleBase $bundle_entity = NULL, ?UserInterface $provider = NULL) {
    if (!isset($this->database)) {
      throw new HttpException(500, 'Unable to retrieve revisions for the resource.');
    }

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $provider ?: $this->getProvider();

    // Get the resource bundle.
    $bundle = isset($bundle_entity) ? $bundle_entity->id() : NULL;

    // Exctract information about the given entity type.
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    $entity_type = $storage->getEntityType();
    $bundle_type_id = $entity_type->getBundleEntityType();

    // Skip if the entity type doesn't support revisions.
    if (!$entity_type->isRevisionable()) {
      throw new BadRequestHttpException('This resource does not support revisions');
    }

    // Get the name of the fields for the id, revision_id etc.
    $entity_keys = $entity_type->getKeys();
    if (!isset($entity_keys['id'], $entity_keys['uuid'], $entity_keys['bundle'], $entity_keys['revision'])) {
      throw new BadRequestHttpException('This resource does not support revisions');
    }

    // Get the list of resources accessible to the provider.
    // If bundle is defined and accessible, then the list will only contain it.
    $accessible_resource_types = $this->getAccessibleResourceTypes($bundle_type_id, $provider, $bundle);

    // Get the entity id matching the uuid.
    $query = $storage->getQuery()->allRevisions()->accessCheck(FALSE);

    // Limit to the list of accessible resources.
    $query->condition($entity_keys['bundle'], $accessible_resource_types, 'IN');

    // We want revisions for the given resource id or uuid.
    if (Uuid::isValid($id)) {
      $query->condition($entity_keys['uuid'], $id);
    }
    else {
      $query->condition($entity_keys['id'], $id);
    }

    // Get the latest revision or the one with given revision id.
    if ($revision_id === 'last') {
      $query->latestRevision();
    }
    else {
      $query->condition($entity_keys['revision'], $revision_id);
    }

    // Get the revision ID (key of the result).
    $result = $query->execute();
    if (empty($result)) {
      // @todo use a cacheable exception?
      throw new NotFoundHttpException('Revision not found');
    }

    // Load the revision.
    $revision = $storage->loadRevision(key($result));
    if (empty($result)) {
      // @todo use a cacheable exception?
      throw new NotFoundHttpException('Revision not found');
    }

    return $revision;
  }

  /**
   * Load the given resource's revisions.
   *
   * @param string $entity_type_id
   *   Entity type (ex: node, taxonomy_term, media).
   * @param string $id
   *   Entity id or uuid.
   *
   * @return array
   *   List of revisions for the entity.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   400 Bad Request if the resource doesn't support revisions.
   */
  public function getResourceEntityRevisionList($entity_type_id, $id) {
    if (!isset($this->database, $this->entityTypeManager)) {
      throw new HttpException(500, 'Unable to retrieve revisions for the resource.');
    }

    // Get the storage for the entity type.
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    /** @var \Drupal\Core\Entity\ContentEntityTypeInterface $entity_type */
    $entity_type = $storage->getEntityType();

    // Skip if the entity type doesn't support revisions.
    if (!$entity_type->isRevisionable()) {
      throw new BadRequestHttpException('The resource does not support revisions');
    }

    // Tables for the query.
    $base_table = $entity_type->getBaseTable();
    $revision_table = $entity_type->getRevisionTable();

    // Fields for the query.
    $entity_keys = $entity_type->getKeys();
    if (!isset($entity_keys['id'], $entity_keys['uuid'], $entity_keys['revision'])) {
      throw new BadRequestHttpException('This resource does not support revisions');
    }
    $id_field = $entity_keys['id'];
    $uuid_field = $entity_keys['uuid'];
    $revision_id_field = $entity_keys['revision'];

    // Revision fields to return. It's an array with the actual database fields
    // as values and the normalized field names as keys.
    $revision_fields = ['revision_id' => $revision_id_field];
    $revision_fields += $entity_type->getRevisionMetadataKeys();

    // Remove the revision user field as we will instead return the user uuid.
    $revision_user_field = $revision_fields['revision_user'];
    unset($revision_fields['revision_user']);

    // Get all revisions.
    $query = $this->database
      ->select($revision_table, $revision_table)
      ->fields($revision_table, array_values($revision_fields));

    // Limit the number of revisions.
    // @todo review.
    $query->range(0, 50);

    // Join the base table so we can filter on the entity id/uuid.
    $query->innerJoin($base_table, $base_table, "$base_table.$id_field = $revision_table.$id_field");

    // Add a condition on the id or uuid depending on what we were given.
    if (Uuid::isValid($id)) {
      $query->condition($base_table . '.' . $uuid_field, $id);
    }
    else {
      $query->condition($base_table . '.' . $id_field, $id);
    }

    // Join the user table so we can get the uuid instead of returning the id.
    $user_entity_type = $this->entityTypeManager->getStorage('user')->getEntityType();
    $user_table = $user_entity_type->getBaseTable();
    $user_id_field = $user_entity_type->getKey('id');
    $user_uuid_field = $user_entity_type->getKey('uuid');

    $query->innerJoin($user_table, $user_table, "$user_table.$user_id_field = $revision_table.$revision_user_field");
    $query->fields($user_table, [$user_uuid_field]);

    // Get the revisions ordered by most recent first.
    $records = $query->orderBy($revision_table . '.' . $revision_id_field, 'DESC')->execute();

    // Fields to return.
    // @todo use the revision keys like revision_id or the simplified ones?
    $fields = [
      'id' => $revision_fields['revision_id'],
      'created' => $revision_fields['revision_created'],
      'message' => $revision_fields['revision_log_message'],
      'default' => $revision_fields['revision_default'],
      'provider_uuid' => $user_uuid_field,
    ];

    // Ensure the revisions have the same fields regardless of the entity type.
    $revisions = [];
    if (!empty($records)) {
      foreach ($records as $record) {
        $revision = [];
        foreach ($fields as $normalized_field => $field) {
          $value = $record->{$field};
          // Format the revision creation date.
          if ($normalized_field === 'created' && !empty($value)) {
            $date = $this->formatIso8601Date($value);
            if (!empty($date)) {
              $revision['created'] = $date;
            }
          }
          // Mark the revision as draft if not default.
          elseif ($normalized_field === 'default') {
            if (empty($value)) {
              $revision['draft'] = TRUE;
            }
          }
          // Simply copy the value.
          else {
            $revision[$normalized_field] = $value;
          }
        }
        $revisions[] = $revision;
      }
    }

    return $revisions;
  }

  /**
   * Publish entity revision.
   *
   * @param \Drupal\Core\Entity\EditorialContentEntityBase $entity
   *   Entity (ex: term, node).
   * @param array $params
   *   Request parameters.
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   *
   * @todo maybe this should be moved to the ResourceTrait?
   */
  public function publishEntityRevisionFromParameters(EditorialContentEntityBase $entity, array $params, UserInterface $provider) {
    if (!$entity->isDefaultRevision()) {
      $entity->setRevisionCreationTime(time());

      $entity->setRevisionLogMessage('Updated');
      // @todo add some validation.
      if (isset($params['revision_log'])) {
        $entity->setRevisionLogMessage($params['revision_log']);
      }

      $entity->setNewRevision();
      $entity->setRevisionUserId($provider->id());

      $entity->isDefaultRevision(TRUE);
      $entity->save();
    }
  }

  /**
   * Create a new revision for the given entity.
   *
   * @param \Drupal\Core\Entity\EditorialContentEntityBase $entity
   *   Entity (ex: term, node).
   * @param array $params
   *   Request parameters.
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   *
   * @todo check that the entity type is revisionable?
   */
  public function createEntityRevisionFromParameters(EditorialContentEntityBase $entity, array $params, UserInterface $provider) {
    $create_revision = FALSE;

    // Check if instructed to create a new revision.
    if (!empty($params['new_revision'])) {
      $create_revision = TRUE;
    }
    // Otherwise check if revisions should always be created for this type
    // of resource by loading the bundle entity (node type, vocabulary).
    else {
      /** @var \Drupal\Core\Config\Entity\ConfigEntityBundleBase $bundle_entity */
      $bundle_entity = $this->entityTypeManager
        ->getStorage($entity->getEntityType()->getBundleEntityType())
        ->load($entity->bundle());

      // Only Node implements RevisionableEntityBundleInterface so far.
      if ($bundle_entity instanceof RevisionableEntityBundleInterface) {
        $create_revision = $bundle_entity->shouldCreateNewRevision();
      }
      else {
        $create_revision = $bundle_entity->getThirdPartySetting('docstore', 'use_revisions', TRUE);
      }
    }

    // Create a new revision.
    if ($create_revision) {
      // @todo that may not be necessary.
      $entity->setRevisionCreationTime(time());
      $entity->setRevisionLogMessage('Updated');
      // @todo add some validation.
      if (isset($params['revision_log'])) {
        $entity->setRevisionLogMessage($params['revision_log']);
      }

      $entity->setNewRevision(TRUE);
      $entity->setRevisionUserId($provider->id());

      // Save new revision as draft?
      $entity->isDefaultRevision(empty($params['draft']));
    }
    else {
      $entity->setNewRevision(FALSE);
    }
  }

}
