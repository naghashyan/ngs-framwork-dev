<?php

/**
 * Class for analyzing and extracting information from incoming HTTP requests
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

namespace ngs\util;

class RequestContext
{
    /**
     * Detect if request call from ajax or not
     * 
     * @return bool True if the request is an AJAX request, false otherwise
     */
    public function isAjaxRequest(): bool
    {
        return isset($_SERVER["HTTP_X_REQUESTED_WITH"]) && $_SERVER["HTTP_X_REQUESTED_WITH"] === "XMLHttpRequest";
    }

    /**
     * Get the request protocol (http or https)
     * 
     * @return string The protocol string ("http:" or "https:")
     */
    public function getRequestProtocol(): string
    {
        if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === "on") || $_SERVER['SERVER_PORT'] === 443) {
            $protocol = "https:";
        } else {
            $protocol = "http:";
        }

        return $protocol;
    }

    /**
     * Get the host name from the HTTP host
     * 
     * @param bool $main Whether to get the main domain
     * @return string|null The host name or null if not available
     */
    public function getHost($main = false)
    {
        $httpHost = $this->getHttpHostInternal($main);
        if ($httpHost === null) {
            return null;
        }
        $array = explode(".", $httpHost);
        return (array_key_exists(count($array) - 2, $array) ? $array[count($array) - 2] : "") . "." . $array[count($array) - 1];
    }

    /**
     * Get the HTTP host with various formatting options
     * 
     * @param bool $withPath Whether to include path formatting
     * @param bool $withProtacol Whether to include the protocol
     * @param bool $main Whether to get the main domain
     * @return string|null The formatted HTTP host or null if not available
     */
    public function getHttpHost($withPath = false, $withProtacol = false, $main = false)
    {
        $httpHost = $this->getHttpHostInternal($main);
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

    /**
     * TODO: ZN: refactor and clean up the logic with the NS
     * Get the HTTP host by namespace
     * 
     * @param string $ns The namespace
     * @param bool $withProtocol Whether to include the protocol
     * @return string The HTTP host for the given namespace
     */
    public function getHttpHostByNs($ns = "", $withProtocol = false)
    {
        $moduleRoutesEngine = NGS()->createDefinedInstance('MODULES_ROUTES_ENGINE', \ngs\routes\NgsModuleResolver::class);
        $httpHost = $this->getHttpHost(true, $withProtocol);
        return $httpHost;
        //TODO: ZN: refactor and clean up the logic with the NS
        if ($moduleRoutesEngine->getModuleType() === "path") {
            if ($ns == "") {
                return $httpHost;
            }
            if ($moduleRoutesEngine->getModuleName() === '' || $moduleRoutesEngine->getModuleName() === NGS()->getName()) { // TODO: previously was isDefaultModule(); code path currently unreachable
            }

            return $httpHost . "/" . $moduleRoutesEngine->getModuleUri();
        }
        if ($ns == "") {
            return $httpHost;
        }
        return $this->getHttpHost(true, $withProtocol) . "/" . $ns;
    }

    /**
     * Get the NGS static path
     * 
     * @param string $ns The namespace
     * @param bool $withProtocol Whether to include the protocol
     * @return string The NGS static path
     */
    public function getNgsStaticPath($ns = "", $withProtocol = false)
    {
        $moduleRoutesEngine = NGS()->createDefinedInstance('MODULES_ROUTES_ENGINE', \ngs\routes\NgsModuleResolver::class);
        $httpHost = $this->getHttpHost(true, $withProtocol);
        // Resolve current module name dynamically from the request URI
        $currentModule = $moduleRoutesEngine->resolveModule($this->getRequestUri()) ?? NGS();
        $currentModuleName = $currentModule->getName();
        if ($ns === "") {
            // If no namespace requested, return host for default module; otherwise append current module name
            if ($currentModuleName === NGS()->getName()) {
                return $httpHost;
            }
            $ns = $currentModuleName;
        }
        return $this->getHttpHost(true, $withProtocol) . "/" . $ns;
    }

    /**
     * Get the request URI
     * 
     * @param bool $full Whether to get the full URI
     * @return string The request URI
     */
    public function getRequestUri($full = false)
    {
        $uri = isset($_SERVER["REQUEST_URI"]) ? $_SERVER["REQUEST_URI"] : "";

        if (strpos($uri, "?") !== false) {
            $uri = substr($uri, 0, strpos($uri, "?"));
        }
        return $uri;
//TODO: ZN: refactor and clean up the logic with the NS
        if ($full === false) {
            $moduleRoutesEngine = NGS()->createDefinedInstance('MODULES_ROUTES_ENGINE', \ngs\routes\NgsModuleResolver::class);
            if($moduleRoutesEngine->getModuleType() == "path"){
                $delim = "";
                if (strpos($uri, $moduleRoutesEngine->getModuleUri() . "/") !== false) {
                    $delim = "/";
                }
                $uri = str_replace($moduleRoutesEngine->getModuleUri() . $delim, "", $uri);
            }
        }

        return $uri;
    }

    /**
     * Redirect to a URL
     * 
     * @param string $url The URL to redirect to
     * @param string $module The module namespace
     * @return void
     */
    public function redirect(string $url, string $module = ""): void
    {
        header("location: " . $this->getHttpHostByNs($module, true) . "/" . $url);
    }

    /**
     * Get the main domain
     * 
     * @return string|bool The main domain or false if not available
     */
    public function getMainDomain()
    {
        $pieces = $this->getHttpHostInternal(true) ? parse_url($this->getHttpHostInternal(true)) : '';
        $domain = isset($pieces['path']) ? $pieces['path'] : '';
        if (preg_match('/(?P<domain>[a-z0-9][a-z0-9\-]{1,63}\.[a-z\.]{2,6})$/i', $domain, $regs)) {
            return $regs['domain'];
        }
        return false;
    }

    /**
     * Get the host path
     * 
     * @return string The host path
     */
    public function getHostPath()
    {
        $pieces = parse_url($this->getHttpHostInternal(true));

        return $pieces['path'];
    }

    /**
     * Get the HTTP host internal
     * 
     * @param bool $main Whether to get the main domain
     * @return string|null The HTTP host or null if not available
     */
    protected function getHttpHostInternal($main = false)
    {
        $ngsHost = null;
        if (NGS()->get("HTTP_HOST")) {
            $ngsHost = NGS()->get("HTTP_HOST");
        } elseif (isset($_SERVER["HTTP_HOST"])) {
            $ngsHost = $_SERVER["HTTP_HOST"];
        }
        return $ngsHost;
    }

    /**
     * Get the subdomain
     * 
     * @return string|null The subdomain or null if not available
     */
    public function getSubdomain()
    {
        $domain = $this->getHttpHostInternal(true);
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

    /**
     * Gets the current HTTP request method
     * 
     * @return string HTTP method (lowercase)
     */
    public function getRequestHttpMethod(): string
    {
        if (isset($_SERVER['REQUEST_METHOD'])) {
            return strtolower($_SERVER['REQUEST_METHOD']);
        }
        return 'get';
    }
}
