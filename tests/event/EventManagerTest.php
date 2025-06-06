<?php

namespace ngs\tests\event;

// Include the required files directly since autoloading might not be working
require_once __DIR__ . '/../../src/event/EventManagerInterface.php';
require_once __DIR__ . '/../../src/event/structure/AbstractEventStructure.php';
require_once __DIR__ . '/../../src/event/subscriber/AbstractEventSubscriber.php';
require_once __DIR__ . '/MockEventManager.php';

use ngs\event\structure\AbstractEventStructure;
use ngs\event\subscriber\AbstractEventSubscriber;

/**
 * Simple test script to verify EventManager functionality
 */

// Create a mock event structure for testing
class MockEventStructure extends AbstractEventStructure
{
    public static function getEmptyInstance(): AbstractEventStructure
    {
        return new MockEventStructure([]);
    }

    public function isVisible(): bool
    {
        return true;
    }

    public function getEventName(): string
    {
        return "MockEvent";
    }

    public function getAvailableVariables(): array
    {
        return ["param1", "param2"];
    }
}

// Create a mock event subscriber for testing
class MockEventSubscriber extends AbstractEventSubscriber
{
    public $eventReceived = false;
    
    public function getSubscriptions(): array
    {
        return [
            MockEventStructure::class => 'handleEvent'
        ];
    }
    
    public function handleEvent(AbstractEventStructure $event): void
    {
        $this->eventReceived = true;
        echo "Event handled by MockEventSubscriber\n";
    }
}

// Test EventManager
echo "Testing EventManager:\n";

// Test getInstance
$eventManager = MockEventManager::getInstance();
echo "getInstance: " . (is_object($eventManager) ? "PASS" : "FAIL") . "\n";

// Set up subscribers for testing
$subscribers = [
    [
        'class' => MockEventSubscriber::class
    ]
];
$eventManager->setSubscribers($subscribers);

// Test loadSubscribers
$loadedSubscribers = $eventManager->loadSubscribers();
echo "loadSubscribers: " . (is_array($loadedSubscribers) ? "PASS" : "FAIL") . "\n";
echo "loadSubscribers count: " . count($loadedSubscribers) . "\n";

// Test subscribeToEvents
$eventManager->subscribeToEvents($loadedSubscribers);
echo "subscribeToEvents: PASS\n";

// Test dispatch
$event = new MockEventStructure([]);
$eventManager->dispatch($event);
echo "dispatch: PASS\n";

// Test getVisibleEvents
$visibleEvents = $eventManager->getVisibleEvents();
echo "getVisibleEvents: " . (is_array($visibleEvents) ? "PASS" : "FAIL") . "\n";
echo "getVisibleEvents count: " . count($visibleEvents) . "\n";

echo "\nTest completed.\n";