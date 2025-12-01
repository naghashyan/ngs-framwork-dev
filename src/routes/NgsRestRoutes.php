<?php

/**
 * default ngs routing class
 * this class by default used from dispacher
 * for matching url with routes
 *
     * @author Naghashyan Solutions <info@naghashyan.com>
     * @site https://naghashyan.com
     * @year 2007-2026
     * @package ngs.framework
     * @version 5.0.0
 *
 * This file is part of the NGS package.
 *
 * @copyright Naghashyan Solutions LLC
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace ngs\routes;

use ngs\exceptions\DebugException;
use ngs\exceptions\NotFoundException;

class NgsRestRoutes extends NgsRoutesResolver
{
    private $httpMethod = "get";


    /**
     * this method return pakcage and command from url
     * check url if set dynamic container return manage using standart routing
     * if not manage url using routes file if matched succsess return NgsRoute if not false
     * this method can be overrided from users for they custom routing scenarios
     *
     * @param String $url
     *
     * @return \ngs\routes\NgsRoute|null
     */
    public function getRoute($url, $is404 = false): ?\ngs\routes\NgsRoute
    {
        $loadsArr = parent::getRoute($url);
        $currentRoute = $this->getCurrentRoute();
        if ($currentRoute !== null && $currentRoute->getMethod() !== null) {
            $this->setRequestHttpMethod($currentRoute->getMethod());
        }

        // Set the method on the route object
        if ($loadsArr !== null) {
            $loadsArr->setMethod($this->getRequestHttpMethod());
        }

        if (strtolower($this->getRequestHttpMethod()) != strtolower($_SERVER["REQUEST_METHOD"])) {
            throw new DebugException("HTTP request is " . $_SERVER["REQUEST_METHOD"] . " but in routes set " . $this->getRequestHttpMethod());
        }
        return $loadsArr;
    }

    public function getRequestHttpMethod(): string
    {
        return $this->httpMethod;
    }

    protected function setRequestHttpMethod($httpMethod)
    {
        $this->httpMethod = $httpMethod;
    }
}
