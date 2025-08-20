<?php

/**
 * NGS abstract load all loads that response is json should extends from this class
 * this class extends from AbstractRequest class
 * this class class content base functions that will help to
 * initialize loads
 *
 * @author Levon Naghashyan <levon@naghashyan.com>
 * @site http://naghashyan.com
 * @year 2014-2016
 * @package ngs.framework
 * @version 3.1.0
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

use ngs\exceptions\NoAccessException;
use ngs\routes\NgsRoute;

abstract class AbstractApiRequest extends AbstractRequest
{
    protected array $params = [];

    public function initialize(NgsRoute $route): void
    {
        //TODO: MJ: check with mj what is this?
        $this->setAction($route->offsetGet('action_method'));
        $this->setRequestValidators($route->offsetGet('request_params'));
        $this->setResponseValidators($route->offsetGet('response_params'));

        parent::initialize($route);
    }

    public function getResponseType(): string
    {
        return self::RESPONSE_TYPE_JSON;
    }
}
