<?php

/**
 * EventSubscriptionManager class
 * Handles event subscription management
 *
     * @author Naghashyan Solutions <info@naghashyan.com>
     * @site https://naghashyan.com
     * @year 2007-2026
     * @package ngs.framework
     * @version 5.0.0
 *
 * This file is part of the NGS package.
 *
 * @copyright Naghashyan Solutions LLC
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ngs\event;

use ngs\event\structure\AbstractEventStructure;
use ngs\event\subscriber\AbstractEventSubscriber;
use JsonException;
use InvalidArgumentException;
use Exception;

/**
 * Class EventSubscriptionManager
 * 
 * Manages event subscriptions and subscribers
 * 
     * @package ngs.framework
 */
class EventSubscriptionManager
{
    /**
     * @var EventSubscriptionManager instance of class
     */
    private static ?EventSubscriptionManager $instance = null;

    /**
     * Storage for all visible events
     * 
     * @var array<string, array>
     */
    private array $allVisibleEvents = [];

    /**
     * Private constructor for singleton pattern
     */
    private function __construct()
    {
    }

    /**
     * Returns a singleton instance of this class
     *
     * @return EventSubscriptionManager
     */
    public static function getInstance(): EventSubscriptionManager
    {
        if (self::$instance === null) {
            self::$instance = new EventSubscriptionManager();
        }
        return self::$instance;
    }

    /**
     * Returns all visible events
     *
     * This method provides access to the collection of visible events
     * that have been registered with the manager.
     *
     * @return array<string, array> Array of visible events indexed by event ID
     */
    public function getVisibleEvents(): array
    {
        return $this->allVisibleEvents;
    }

    /**
     * Subscribes to all events defined in event_subscribers.json files
     *
     * This method loads event subscribers from configuration files and
     * subscribes them to their respective events. It can optionally load
     * subscribers from all modules.
     *
     * @param bool $loadAll Whether to load subscribers from all modules
     *
     * @return void
     * @throws JsonException If there's an issue with JSON parsing
     */
    public function getSubscribersAndSubscribeToEvents(bool $loadAll = false): void
    {
        // Load admin tools subscribers
        $adminToolsSubscribers = NGS()->getConfigDir(NGS()->get('NGS_CMS_NS')) . '/event_subscribers.json';
        $subscribers = [];

        if (file_exists($adminToolsSubscribers)) {
            $subscribers = json_decode(file_get_contents($adminToolsSubscribers), true, 512, JSON_THROW_ON_ERROR);
        }

        if ($loadAll) {
            // Load subscribers from all modules
            $moduleRouteFile = realpath(NGS()->get('NGS_ROOT') . '/' . NGS()->get('CONF_DIR') . '/' . NGS()->get('NGS_MODULS_ROUTS'));

            if ($moduleRouteFile) {
                $modulesData = json_decode(file_get_contents($moduleRouteFile), true, 512, JSON_THROW_ON_ERROR);
                $modules = $this->getModules($modulesData);

                foreach ($modules as $module) {
                    $moduleSubscribers = NGS()->getConfigDir($module) . '/event_subscribers.json';

                    if (file_exists($moduleSubscribers)) {
                        $moduleSubscribersData = json_decode(file_get_contents($moduleSubscribers), true, 512, JSON_THROW_ON_ERROR);
                        $subscribers = $this->mergeSubscribers($subscribers, $moduleSubscribersData);
                    }
                }
            }
        } else {
            // Load subscribers from main configuration
            $moduleSubscribers = NGS()->get('NGS_ROOT') . '/conf/event_subscribers.json';

            if (file_exists($moduleSubscribers)) {
                $moduleSubscribersData = json_decode(file_get_contents($moduleSubscribers), true, 512, JSON_THROW_ON_ERROR);
                $subscribers = $this->mergeSubscribers($subscribers, $moduleSubscribersData);
            }
        }

        // Subscribe to all events
        $this->subscribeToSubscribersEvents($subscribers);
    }

    /**
     * Returns an array of module directories from the modules data
     *
     * This method extracts unique module directories from the modules data
     * configuration array, handling both default and non-default module types.
     *
     * @param array $modulesData The modules data configuration array
     * 
     * @return array<int, string> Array of unique module directory paths
     */
    private function getModules(array $modulesData): array
    {
        // Return empty array if default section is missing
        if (!isset($modulesData['default'])) {
            return [];
        }

        $result = [];

        // Process each module type in the default section
        foreach ($modulesData['default'] as $type => $modules) {
            if ($type === 'default') {
                // Handle the default module type
                if (!in_array($modules['dir'], $result, true)) {
                    $result[] = $modules['dir'];
                }
            } else {
                // Handle other module types
                foreach ($modules as $info) {
                    if (is_array($info) && isset($info['dir']) && !in_array($info['dir'], $result, true)) {
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
     * This method combines two arrays of subscribers, ensuring that
     * no duplicate subscribers are added to the result.
     *
     * @param array $oldSubscribers The original subscribers array
     * @param array $newSubscribers The new subscribers to merge
     * 
     * @return array The merged subscribers array without duplicates
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
     * Checks if a subscription already exists in the subscriptions list
     *
     * This method determines if a subscription with the same class
     * already exists in the list of subscriptions.
     *
     * @param array $subscriptions The list of existing subscriptions
     * @param array $newSubscriptionData The new subscription data to check
     * 
     * @return bool True if the subscription already exists, false otherwise
     */
    private function subscriptionExists(array $subscriptions, array $newSubscriptionData): bool
    {
        foreach ($subscriptions as $subscription) {
            if (isset($subscription['class']) && isset($newSubscriptionData['class']) && 
                $subscription['class'] === $newSubscriptionData['class']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Subscribes to each subscriber's events
     *
     * This method processes the list of subscribers and subscribes each one
     * to their respective events. It also collects information about visible
     * events for later use.
     *
     * @param array $subscribers Array of subscriber configurations
     * 
     * @return void
     * @throws Exception If a subscriber class is not a valid AbstractEventSubscriber
     * @throws InvalidArgumentException If an event structure class is not valid
     */
    private function subscribeToSubscribersEvents(array $subscribers): void
    {
        $eventManager = EventManager::getInstance();

        foreach ($subscribers as $subscriber) {
            // Skip invalid subscriber configurations
            if (!isset($subscriber['class'])) {
                continue;
            }

            /** @var AbstractEventSubscriber $subscriberObject */
            $subscriberObject = new $subscriber['class']();

            if (!$subscriberObject instanceof AbstractEventSubscriber) {
                throw new Exception('Invalid subscriber: ' . $subscriber['class']);
            }

            $subscriptions = $subscriberObject->getSubscriptions();

            foreach ($subscriptions as $eventStructClass => $handlerName) {
                /** @var AbstractEventStructure $eventStructExample */
                if (!is_a($eventStructClass, AbstractEventStructure::class, true)) {
                    throw new InvalidArgumentException('Invalid event structure class: ' . $eventStructClass);
                }

                $eventStructExample = $eventStructClass::getEmptyInstance();
                $availableParams = $eventStructExample->getAvailableVariables();

                // Register visible events
                if ($eventStructExample->isVisible() && !isset($this->allVisibleEvents[$eventStructExample->getEventId()])) {
                    $this->allVisibleEvents[$eventStructExample->getEventId()] = [
                        'name' => $eventStructExample->getEventName(),
                        'bulk_is_available' => $eventStructExample->bulkIsAvailable(),
                        'params' => $availableParams
                    ];
                }

                // Subscribe to the event
                $eventManager->subscribeToEvent($eventStructClass, $subscriberObject, $handlerName);
            }
        }
    }
}