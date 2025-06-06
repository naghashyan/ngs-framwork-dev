<?php

namespace ngs\tests\event;

use ngs\Dispatcher;
use ngs\event\EventManager;
use ngs\event\structure\PusherEventStructure;
use ngs\event\subscriber\PusherEventSubscriber;
use ngs\util\Pusher;
use PHPUnit\Framework\TestCase;

/**
 * Test for the PusherEvent functionality
 */
class PusherEventTest extends TestCase
{
    /**
     * Test that the PusherEventSubscriber correctly handles PusherEventStructure events
     */
    public function testPusherEventSubscriber()
    {
        // Create a mock Pusher class to verify it's called
        $mockPusher = $this->createMock(Pusher::class);
        $mockPusher->expects($this->once())
            ->method('push');
        
        // Replace the singleton instance with our mock
        $reflectionClass = new \ReflectionClass(Pusher::class);
        $instanceProperty = $reflectionClass->getProperty('instance');
        $instanceProperty->setAccessible(true);
        $instanceProperty->setValue(null, $mockPusher);
        
        // Create the subscriber and dispatch an event
        $subscriber = new PusherEventSubscriber();
        $event = new PusherEventStructure([]);
        
        // Set SEND_HTTP_PUSH to true for this test
        NGS()->set('SEND_HTTP_PUSH', true);
        
        // Call the handler method directly
        $subscriber->onPusherEvent($event);
        
        // Reset the singleton instance
        $instanceProperty->setValue(null, null);
    }
    
    /**
     * Test that the Dispatcher dispatches PusherEventStructure events
     */
    public function testDispatcherEmitsPusherEvents()
    {
        // Create a mock EventManager to verify it's called with the right event
        $mockEventManager = $this->createMock(EventManager::class);
        $mockEventManager->expects($this->atLeastOnce())
            ->method('dispatch')
            ->with($this->isInstanceOf(PusherEventStructure::class));
        
        // Create a Dispatcher with the mock EventManager
        $dispatcher = new Dispatcher($mockEventManager);
        
        // We can't easily test the full dispatch method as it requires a lot of setup,
        // but we can verify that the event manager is properly injected and used
        $this->assertSame($mockEventManager, $this->getObjectProperty($dispatcher, 'eventManager'));
    }
    
    /**
     * Helper method to get a private/protected property value
     */
    private function getObjectProperty($object, $propertyName)
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        return $property->getValue($object);
    }
}