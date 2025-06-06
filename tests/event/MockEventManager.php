<?php

namespace ngs\tests\event;

use ngs\event\EventManagerInterface;
use ngs\event\structure\AbstractEventStructure;
use ngs\event\subscriber\AbstractEventSubscriber;

/**
 * Mock implementation of EventManagerInterface for testing
 */
class MockEventManager implements EventManagerInterface
{
    private static $instance = null;
    private array $eventSubscriptions = [];
    private array $allVisibleEvents = [];
    private array $subscribers = [];

    private function __construct()
    {
    }

    /**
     * Returns an singleton instance of this class
     *
     * @return MockEventManager
     */
    public static function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new MockEventManager();
        }
        return self::$instance;
    }

    /**
     * Dispatch an event to all subscribed handlers
     *
     * @param AbstractEventStructure $event The event to dispatch
     * @return void
     */
    public function dispatch(AbstractEventStructure $event): void
    {
        $handlers = isset($this->eventSubscriptions[get_class($event)]) ? $this->eventSubscriptions[get_class($event)] : [];
        foreach ($handlers as $handler) {
            $subscriber = $handler['subscriber'];
            $method = $handler['method'];
            $subscriber->$method($event);
        }
    }

    /**
     * Subscribe a handler to an event
     *
     * @param string $eventName The event class name to subscribe to
     * @param AbstractEventSubscriber $subscriber The subscriber object
     * @param string $method The method name to call on the subscriber
     * @return void
     */
    public function subscribeToEvent(string $eventName, AbstractEventSubscriber $subscriber, string $method): void
    {
        if (!isset($this->eventSubscriptions[$eventName])) {
            $this->eventSubscriptions[$eventName] = [];
        }

        $this->eventSubscriptions[$eventName][] = ['subscriber' => $subscriber, 'method' => $method];
    }

    /**
     * Load subscribers from configuration files and subscribe to their events
     *
     * @param bool $loadAll Whether to load subscribers from all modules
     * @return array The loaded subscribers
     */
    public function loadSubscribers(bool $loadAll = false): array
    {
        // For testing, return a predefined list of subscribers
        return $this->subscribers;
    }

    /**
     * Set the subscribers for testing
     *
     * @param array $subscribers The subscribers to set
     * @return void
     */
    public function setSubscribers(array $subscribers): void
    {
        $this->subscribers = $subscribers;
    }

    /**
     * Subscribe to events from the given subscribers
     *
     * @param array $subscribers Array of subscribers to process
     * @return void
     */
    public function subscribeToEvents(array $subscribers): void
    {
        foreach ($subscribers as $subscriber) {
            $subscriberObject = new $subscriber['class']();

            if (!$subscriberObject instanceof AbstractEventSubscriber) {
                throw new \Exception('Invalid subscriber: ' . $subscriber['class']);
            }

            $subscriptions = $subscriberObject->getSubscriptions();

            foreach ($subscriptions as $eventStructClass => $handlerName) {
                if (!is_a($eventStructClass, AbstractEventStructure::class, true)) {
                    throw new \InvalidArgumentException('Invalid event structure class: ' . $eventStructClass);
                }

                $eventStructExample = $eventStructClass::getEmptyInstance();
                $availableParams = $eventStructExample->getAvailableVariables();
                $eventId = $eventStructExample->getEventId();

                // Store visible events for later use
                if ($eventStructExample->isVisible() && !isset($this->allVisibleEvents[$eventId])) {
                    $this->allVisibleEvents[$eventId] = [
                        'name' => $eventStructExample->getEventName(),
                        'bulk_is_available' => $eventStructExample->bulkIsAvailable(),
                        'params' => $availableParams
                    ];
                }

                $this->subscribeToEvent($eventStructClass, $subscriberObject, $handlerName);
            }
        }
    }

    /**
     * Get all visible events with their parameters
     *
     * @return array Array of visible events
     */
    public function getVisibleEvents(): array
    {
        return $this->allVisibleEvents;
    }
}