<?php

/**
 * AbstractEventSubscriber class
 *
     * @author Naghashyan Solutions <info@naghashyan.com>
     * @site https://naghashyan.com
 * @mail miakel.mkrtchyan@naghashyan.com
     * @year 2007-2026
     * @package ngs.framework
     * @version 5.0.0
 *
 */

namespace ngs\event\subscriber;

abstract class AbstractEventSubscriber
{
    public function __construct()
    {
    }

    /**
     * should return arrak,
     * key => eventStructClass
     * value => public method of this class, which will be called when (key) event will be triggered
     *
     * @return array
     */
    abstract public function getSubscriptions(): array;
}
