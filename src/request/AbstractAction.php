<?php

declare(strict_types=1);

/**
 * parent class of all ngs actions
 *
 * @author Levon Naghashyan <levon@naghashyan.com>
 * @site http://naghashyan.com
 * @year 2009-2019
 * @package ngs.framework
 * @version 3.8.0
 *
 * This file is part of the NGS package.
 *
 * @copyright Naghashyan Solutions LLC
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace ngs\request;

abstract class AbstractAction extends \ngs\request\AbstractRequest
{
    public const REQUEST_TYPE = "action";

    /**
     * @return void
     */
    public function initialize(?\ngs\routes\NgsRoute $route = null): void
    {
        // Actions currently do not need additional initialization data from the route.
        // If future route metadata is required, it should be hydrated here.
        $this->addParams($route?->getArgs() ?? []);
    }

    public function getResponseType(): string
    {
        return AbstractRequest::RESPONSE_TYPE_JSON;
    }

    public function afterRequest(): void
    {
        $this->afterAction();
    }

    /**
     * this function invoked when user hasn't permission
     *
     * @return void
     */
    public function onNoAccess(): void
    {
    }

    public function afterAction(): void
    {
        return;
    }
}
