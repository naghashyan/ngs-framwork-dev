<?php

/**
 * EventManagerInterface
 * Interface for event management functionality
 *
 * @author AI Assistant
 * @site https://naghashyan.com
 * @year 2023
 * @package ngs.event
 * @version 1.0.0
 *
 */

namespace ngs\event;

use ngs\event\structure\AbstractEventStructure;
use ngs\event\subscriber\AbstractEventSubscriber;

/**
 * Interface EventManagerInterface
 * Defines the contract for event managers
 * 
 * @package ngs\event
 */
interface EventManagerInterface
{
    /**
     * Dispatch an event to all subscribed handlers
     *
     * @param AbstractEventStructure $event The event to dispatch
     * @return void
     */
    public function dispatch(AbstractEventStructure $event): void;

    /**
     * Subscribe a handler to an event
     *
     * @param string $eventName The event class name to subscribe to
     * @param AbstractEventSubscriber $subscriber The subscriber object
     * @param string $method The method name to call on the subscriber
     * @return void
     */
    public function subscribeToEvent(string $eventName, AbstractEventSubscriber $subscriber, string $method): void;

    /**
     * Load subscribers from configuration files and subscribe to their events
     *
     * @param bool $loadAll Whether to load subscribers from all modules
     * @return array The loaded subscribers
     */
    public function loadSubscribers(bool $loadAll = false): array;

    /**
     * Subscribe to events from the given subscribers
     *
     * @param array $subscribers Array of subscribers to process
     * @return void
     */
    public function subscribeToEvents(array $subscribers): void;

    /**
     * Get all visible events with their parameters
     *
     * @return array Array of visible events
     */
    public function getVisibleEvents(): array;
}