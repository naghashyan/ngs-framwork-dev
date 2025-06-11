<?php

/**
 * EventManager manager class
 * used to handle events
 *
 * @author Mikael Mkrtchyan
 * @site http://naghashyan.com
 * @mail mikael.mkrtchyan@naghashyan.com
 * @year 2022
 * @package ngs.event
 * @version 1.0
 *
 */

namespace ngs\event;

use ngs\event\structure\AbstractEventStructure;
use ngs\event\structure\EventDispatchedStructure;
use ngs\event\subscriber\AbstractEventSubscriber;

class EventManager implements EventManagerInterface
{
    /**
     * @var EventManager instance of class
     */
    private static $instance = null;

    private array $eventSubscriptions = [];

    /**
     * Stores all visible events with their parameters
     *
     * @var array
     */
    private array $allVisibleEvents = [];

    private function __construct()
    {
    }

    /**
     * Returns an singleton instance of this class
     *
     * @return EventManager
     */
    public static function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new EventManager();
        }
        return self::$instance;
    }


    /**
     * dispatch event
     * will call all handlers subscribet to this event
     *
     * @param AbstractEventStructure $event
     * @return void
     */
    public function dispatch(AbstractEventStructure $event): void
    {
        if (!$event instanceof EventDispatchedStructure) {
            $eventDispatched = new EventDispatchedStructure([], $event);
            $this->dispatch($eventDispatched);
        }

        $handlers = isset($this->eventSubscriptions[get_class($event)]) ? $this->eventSubscriptions[get_class($event)] : [];
        foreach ($handlers as $handler) {
            $subscriber = $handler['subscriber'];
            $method = $handler['method'];
            $subscriber->$method($event);
        }
    }


    /**
     * add subscription to event
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
     * Load subscribers from configuration files
     *
     * @param bool $loadAll Whether to load subscribers from all modules
     * @return array The loaded subscribers
     * @throws \Exception When an invalid subscriber is encountered
     */
    public function loadSubscribers(bool $loadAll = false): array
    {
        return [];
        //TODO: ZN: should be revised and moved to admin cms
        $confDir = \NGS()->get('CONF_DIR');
        $ngsCmsNs = \NGS()->get('NGS_CMS_NS');
        $adminToolsSubscribersPath = \NGS()->getModuleDirByNS($ngsCmsNs) . '/' . $confDir . '/event_subscribers.json';
        $adminToolsSubscribers = realpath($adminToolsSubscribersPath);

        $subscribers = [];
        if ($adminToolsSubscribers && file_exists($adminToolsSubscribers)) {
            $subscribers = json_decode(file_get_contents($adminToolsSubscribers), true);
        }

        if ($loadAll) {
            // Load subscribers from all modules
            $ngsRoot = \NGS()->get('NGS_ROOT');
            $ngsModulesRoutes = \NGS()->get('NGS_MODULS_ROUTS');
            $moduleRouteFile = realpath($ngsRoot . '/' . $confDir . '/' . $ngsModulesRoutes);

            if ($moduleRouteFile) {
                $modulesData = json_decode(file_get_contents($moduleRouteFile), true);
                $modules = $this->getModules($modulesData);

                foreach ($modules as $module) {
                    $moduleSubscribersPath = \NGS()->getModuleDirByNS($module) . '/' . $confDir . '/event_subscribers.json';
                    $moduleSubscribersFile = realpath($moduleSubscribersPath);

                    if ($moduleSubscribersFile && file_exists($moduleSubscribersFile)) {
                        $moduleSubscribers = json_decode(file_get_contents($moduleSubscribersFile), true);
                        $subscribers = $this->mergeSubscribers($subscribers, $moduleSubscribers);
                    }
                }
            }
        } else {
            // Load subscribers from the main module only
            $moduleSubscribersPath = \NGS()->get('NGS_ROOT') . '/' . $confDir . '/event_subscribers.json';
            $moduleSubscribersFile = realpath($moduleSubscribersPath);

            if ($moduleSubscribersFile && file_exists($moduleSubscribersFile)) {
                $moduleSubscribers = json_decode(file_get_contents($moduleSubscribersFile), true);
                $subscribers = $this->mergeSubscribers($subscribers, $moduleSubscribers);
            }
        }

        return $subscribers;
    }

    /**
     * Subscribe to events from the given subscribers
     *
     * @param array $subscribers Array of subscribers to process
     * @return void
     * @throws \Exception When an invalid subscriber is encountered
     * @throws \InvalidArgumentException When an invalid event structure class is provided
     */
    public function subscribeToEvents(array $subscribers): void
    {
        foreach ($subscribers as $subscriber) {
            /** @var AbstractEventSubscriber $subscriberObject */
            $subscriberObject = new $subscriber['class']();

            if (!$subscriberObject instanceof AbstractEventSubscriber) {
                throw new \Exception('Invalid subscriber: ' . $subscriber['class']);
            }

            $subscriptions = $subscriberObject->getSubscriptions();

            foreach ($subscriptions as $eventStructClass => $handlerName) {
                /** @var AbstractEventStructure $eventStructExample */
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
     * Returns all visible events
     *
     * @return array Array of visible events
     */
    public function getVisibleEvents(): array
    {
        return $this->allVisibleEvents;
    }

    /**
     * Returns an array of module directories from the modules data
     *
     * @param array $modulesData The modules configuration data
     * 
     * @return array Array of module directories
     */
    private function getModules(array $modulesData): array
    {
        if (!isset($modulesData['default'])) {
            return [];
        }

        $result = [];

        foreach ($modulesData['default'] as $type => $modules) {
            if ($type === 'default') {
                // Handle the default module
                if (!in_array($modules['dir'], $result, true)) {
                    $result[] = $modules['dir'];
                }
            } else {
                // Handle other module types
                foreach ($modules as $info) {
                    if (is_array($info) && !in_array($info['dir'], $result, true)) {
                        $result[] = $info['dir'];
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Merges two subscriber arrays without duplication
     *
     * @param array $oldSubscribers The existing subscribers array
     * @param array $newSubscribers The new subscribers to merge
     * 
     * @return array The merged subscribers array
     */
    private function mergeSubscribers(array $oldSubscribers, array $newSubscribers): array
    {
        foreach ($newSubscribers as $newSubscriber) {
            if (!$this->subscriptionExists($oldSubscribers, $newSubscriber)) {
                $oldSubscribers[] = $newSubscriber;
            }
        }

        return $oldSubscribers;
    }

    /**
     * Checks if a subscription already exists in the list
     *
     * @param array $subscriptions The existing subscriptions array
     * @param array $newSubscriptionData The new subscription data to check
     * 
     * @return bool True if the subscription exists, false otherwise
     */
    private function subscriptionExists(array $subscriptions, array $newSubscriptionData): bool
    {
        foreach ($subscriptions as $subscription) {
            if ($subscription['class'] === $newSubscriptionData['class']) {
                return true;
            }
        }

        return false;
    }
}
