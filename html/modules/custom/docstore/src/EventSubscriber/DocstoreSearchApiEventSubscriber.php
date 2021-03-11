<?php

namespace Drupal\docstore\EventSubscriber;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\search_api\Event\ItemsIndexedEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Clear cache when indexing is done.
 */
class DocstoreSearchApiEventSubscriber implements EventSubscriberInterface {

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new class instance.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(MessengerInterface $messenger) {
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      SearchApiEvents::ITEMS_INDEXED => 'itemsIndexed',
    ];
  }

  /**
   * Reacts to the items indexed event.
   *
   * @param \Drupal\search_api\Event\ItemsIndexedEvent $event
   *   The items indexed event.
   */
  public function itemsIndexed(ItemsIndexedEvent $event) {
    // The index id is the same as cache name: documents or terms.
    // @todo review if we add indices that don't fit that pattern.
    Cache::invalidateTags([$event->getIndex()->id()]);
  }

}
