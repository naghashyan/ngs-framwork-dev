<?php

/**
 * EventDispatchedStructure class, can call when event dispatched
 *
     * @author Naghashyan Solutions <info@naghashyan.com>
     * @site https://naghashyan.com
 * @mail miakel.mkrtchyan@naghashyan.com
     * @year 2007-2026
     * @package ngs.framework
     * @version 5.0.0
 *
 */

namespace ngs\event\structure;

class EventDispatchedStructure extends AbstractEventStructure
{
    private ?AbstractEventStructure $event;

    public function __construct(array $params, ?AbstractEventStructure $event)
    {
        parent::__construct($params);
        $this->event = $event;
    }

    public static function getEmptyInstance(): AbstractEventStructure
    {
        return new EventDispatchedStructure([], null);
    }

    /**
     * @return AbstractEventStructure
     */
    public function getEvent(): AbstractEventStructure
    {
        return $this->event;
    }

    /**
     * @param AbstractEventStructure $event
     */
    public function setEvent(AbstractEventStructure $event): void
    {
        $this->event = $event;
    }
}
