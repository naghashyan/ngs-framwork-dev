<?php

/**
 * PusherEventSubscriber class
 * Subscriber for pusher events
 *
 * @author AI Assistant
 * @site https://naghashyan.com
 * @year 2023
 * @package ngs.event.subscriber
 * @version 1.0.0
 *
 */

namespace ngs\event\subscriber;

use ngs\event\structure\PusherEventStructure;
use ngs\util\Pusher;

class PusherEventSubscriber extends AbstractEventSubscriber
{
    /**
     * Returns the subscriptions for this subscriber
     *
     * @return array
     */
    public function getSubscriptions(): array
    {
        return [
            PusherEventStructure::class => 'onPusherEvent'
        ];
    }

    /**
     * Handles pusher events
     *
     * @param PusherEventStructure $event
     * @return void
     */
    public function onPusherEvent(PusherEventStructure $event): void
    {
        // Check if HTTP push is enabled
        if (!\NGS()->get('SEND_HTTP_PUSH')) {
            return;
        }

        // Call the pusher to push HTTP/2 headers
        Pusher::getInstance()->push();
    }
}