<?php

/**
 * Helper wrapper class for php curl
 *
 * @author Levon Naghashyan <levon@naghashyan.com>
 * @site http://naghashyan.com
 * @year 2014-2016
 * @package ngs.framework.util
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

namespace ngs\util;

class HttpUtils
{
    /**
     * detect if request call from ajax or not
     * @static
     * @access
     * @return bool|true|false
     */
    public function isAjaxRequest(): bool
    {
        return isset($_SERVER["HTTP_X_REQUESTED_WITH"]) && $_SERVER["HTTP_X_REQUESTED_WITH"] === "XMLHttpRequest";
    }

    public function getRequestProtocol(): string
    {
        if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === "on") || $_SERVER['SERVER_PORT'] === 443) {
            $protocol = "https:";
        } else {
            $protocol = "http:";
        }

        return $protocol;
    }

    public function getHost($main = false)
    {
        $httpHost = $this->_getHttpHost($main);
        if ($httpHost === null) {
            return null;
        }
        $array = explode(".", $httpHost);
        return (array_key_exists(count($array) - 2, $array) ? $array[count($array) - 2] : "") . "." . $array[count($array) - 1];
    }

    public function getHttpHost($withPath = false, $withProtacol = false, $main = false)
    {
        $httpHost = $this->_getHttpHost($main);
        if ($httpHost == null) {
            return null;
        }
        if ($withPath) {
            $httpHost = "//" . $httpHost;
            if ($withProtacol) {
                $httpHost = $this->getRequestProtocol() . $httpHost;
            }
        }
        return $httpHost;
    }

    public function getHttpHostByNs($ns = "", $withProtocol = false)
    {
        $moduleRoutesEngine = NGS()->createDefinedInstance('MODULES_ROUTES_ENGINE', \ngs\routes\NgsModuleRoutes::class);
        $httpHost = $this->getHttpHost(true, $withProtocol);
        if ($moduleRoutesEngine->getModuleType() === "path") {
            if ($ns == "") {
                return $httpHost;
            }
            if ($moduleRoutesEngine->isDefaultModule()) {

            }

            return $httpHost . "/" . $moduleRoutesEngine->getModuleUri();
        }
        if ($ns == "") {
            return $httpHost;
        }
        return $this->getHttpHost(true, $withProtocol) . "/" . $ns;
    }

    public function getNgsStaticPath($ns = "", $withProtocol = false)
    {
        $moduleRoutesEngine = NGS()->createDefinedInstance('MODULES_ROUTES_ENGINE', \ngs\routes\NgsModuleRoutes::class);
        $httpHost = $this->getHttpHost(true, $withProtocol);
        if ($moduleRoutesEngine->getModuleType() === "path") {
            if ($ns == "" || $moduleRoutesEngine->isCurrentModule($ns)) {
                return $httpHost . "/" . $moduleRoutesEngine->getModuleUri();
            }
        }
        if ($ns == "") {
            if ($moduleRoutesEngine->isDefaultModule()) {
                return $httpHost;
            }
            $ns = $moduleRoutesEngine->getModuleNS();
        }
        return $this->getHttpHost(true, $withProtocol) . "/" . $ns;
    }

    public function getRequestUri($full = false)
    {
        $uri = isset($_SERVER["REQUEST_URI"]) ? $_SERVER["REQUEST_URI"] : "";

        if (strpos($uri, "?") !== false) {
            $uri = substr($uri, 0, strpos($uri, "?"));
        }
        $moduleRoutesEngine = NGS()->createDefinedInstance('MODULES_ROUTES_ENGINE', \ngs\routes\NgsModuleRoutes::class);
        if ($full === false && $moduleRoutesEngine->getModuleType() == "path") {
            $delim = "";
            if (strpos($uri, $moduleRoutesEngine->getModuleUri() . "/") !== false) {
                $delim = "/";
            }
            $uri = str_replace($moduleRoutesEngine->getModuleUri() . $delim, "", $uri);
        }

        return $uri;
    }

    /**
     * @param string $url
     * @param string $module
     * @return void
     */
    public function redirect(string $url, string $module = ""): void
    {
        header("location: " . $this->getHttpHostByNs($module, true) . "/" . $url);
    }

    public function getMainDomain()
    {
        $pieces = $this->_getHttpHost(true) ? parse_url($this->_getHttpHost(true)) : '';
        $domain = isset($pieces['path']) ? $pieces['path'] : '';
        if (preg_match('/(?P<domain>[a-z0-9][a-z0-9\-]{1,63}\.[a-z\.]{2,6})$/i', $domain, $regs)) {
            return $regs['domain'];
        }
        return false;
    }


    public function getHostPath()
    {
        $pieces = parse_url($this->_getHttpHost(true));

        return $pieces['path'];
    }

    public function _getHttpHost($main = false)
    {
        $ngsHost = null;
        if (NGS()->get("HTTP_HOST")) {
            $ngsHost = NGS()->get("HTTP_HOST");
        } elseif (isset($_SERVER["HTTP_HOST"])) {
            $ngsHost = $_SERVER["HTTP_HOST"];
        }
        return $ngsHost;
    }

    public function getSubdomain()
    {
        $domain = $this->_getHttpHost(true);
        if (!$domain) {
            return null;
        }
        $parsedUrl = parse_url($domain);
        if (!isset($parsedUrl['path'])) {
            return null;
        }
        $host = explode('.', $parsedUrl['path']);
        if (count($host) >= 3) {
            return $host[0];
        }
        return null;
    }

}
