<?php

/**
 * BeforeResultDisplayEventSubscriber class
 * Subscriber for events that occur before displaying results
 *
 * @author AI Assistant
 * @site https://naghashyan.com
 * @year 2023
 * @package ngs.event.subscriber
 * @version 1.0.0
 *
 */

namespace ngs\event\subscriber;

use ngs\event\structure\BeforeResultDisplayEventStructure;
use ngs\util\Pusher;

class BeforeResultDisplayEventSubscriber extends AbstractEventSubscriber
{
    /**
     * Returns the subscriptions for this subscriber
     *
     * @return array
     */
    public function getSubscriptions(): array
    {
        return [
            BeforeResultDisplayEventStructure::class => 'onBeforeResultDisplay'
        ];
    }

    /**
     * Handles events that occur before displaying results
     *
     * @param BeforeResultDisplayEventStructure $event
     * @return void
     */
    public function onBeforeResultDisplay(BeforeResultDisplayEventStructure $event): void
    {
        // Check if HTTP push is enabled
        if (!\NGS()->get('SEND_HTTP_PUSH')) {
            return;
        }

        // Call the pusher to push HTTP/2 headers
        Pusher::getInstance()->push();
    }
}