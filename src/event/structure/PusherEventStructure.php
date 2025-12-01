<?php

/**
 * PusherEventStructure class
 * Event structure for pusher events
 *
     * @author Naghashyan Solutions <info@naghashyan.com>
     * @site https://naghashyan.com
     * @year 2007-2026
     * @package ngs.framework
     * @version 5.0.0
 *
 */

namespace ngs\event\structure;

class PusherEventStructure extends AbstractEventStructure
{
    /**
     * Returns an empty instance of the event structure
     *
     * @return AbstractEventStructure
     */
    public static function getEmptyInstance(): AbstractEventStructure
    {
        return new self([]);
    }

    /**
     * Indicates if this event is visible in the UI
     *
     * @return bool
     */
    public function isVisible(): bool
    {
        return false;
    }

    /**
     * Returns the display name of the event
     *
     * @return string
     */
    public function getEventName(): string
    {
        return 'Pusher Event';
    }

    /**
     * Returns the event title
     *
     * @return string
     */
    public function getEventTitle(): string
    {
        return 'HTTP Push Event';
    }

    /**
     * Returns the list of variables available for this event
     *
     * @return array
     */
    public function getAvailableVariables(): array
    {
        return [];
    }
}