<?php

/**
 * BeforeResultDisplayEventStructure class
 * Event structure for events that occur before displaying results
 *
 * @author AI Assistant
 * @site https://naghashyan.com
 * @year 2023
 * @package ngs.event.structure
 * @version 1.0.0
 *
 */

namespace ngs\event\structure;

class BeforeResultDisplayEventStructure extends AbstractEventStructure
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
        return 'Before Result Display';
    }

    /**
     * Returns the event title
     *
     * @return string
     */
    public function getEventTitle(): string
    {
        return 'Before Result Display Event';
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