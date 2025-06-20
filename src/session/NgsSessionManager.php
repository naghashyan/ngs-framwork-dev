<?php

/**
 * default ngs SessionManager class
 *
 * @author Levon Naghashyan <levon@naghashyan.com>
 * @site http://naghashyan.com
 * @year 2009-2016
 * @package framework.session
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

namespace ngs\session;

use ngs\dal\dto\AbstractDto;
use ngs\request\AbstractAction;

class NgsSessionManager extends \ngs\session\AbstractSessionManager
{
    private $requestSessionHeadersArr = [];


    /**
     * set user info into cookie and session
     *
     * @param mixed $user Object| $user
     * @param bool $remember | true
     * @param bool $useDomain | true
     *
     * @return void
     */
    public function setUser($user, $remember = false, $useDomain = true, $useSubdomain = false): void
    {
        $requestContext = NGS()->createDefinedInstance('REQUEST_CONTEXT', \ngs\util\RequestContext::class);
        $sessionTimeout = $remember ? 2078842581 : null;
        $domain = false;
        if ($useDomain) {
            if ($useSubdomain) {
                $domain = "." . $requestContext->getHost();
            } else {
                $domain = $requestContext->getHost();
            }
        }
        $cookieParams = $user->getCookieParams();
        foreach ($cookieParams as $key => $value) {
            setcookie($key, $value, $sessionTimeout, "/", false);
        }
        $sessionParams = $user->getSessionParams();
        foreach ($sessionParams as $key => $value) {
            $_SESSION[$key] = $value;
        }
    }

    /**
     * delete user from cookie and session
     *
     * @param mixed $user Object | false
     * @param bool $useDomain | true
     * @param bool $useSubdomain | false
     *
     * @return void
     */
    public function deleteUser($user = false, $useDomain = true, $useSubdomain = false): void
    {
        $sessionTimeout = time() - 42000;
        $domain = false;
        if ($useDomain) {
            if ($useSubdomain) {
                $domain = "." . $_SERVER['HTTP_HOST'];
            } else {
                $domain = "." . NGS()->get("HTTP_HOST");
            }
        }
        $cookieParams = $user->getCookieParams();
        foreach ($cookieParams as $key => $value) {
            setcookie($key, '', $sessionTimeout, "/", $domain);
        }
        $sessionParams = $user->getSessionParams();
        foreach ($sessionParams as $key => $value) {
            if (isset($_SESSION[$key])) {
                unset($_SESSION[$key]);
            }
        }
    }

    /**
     * Update user hash code
     *
     * @param mixed $user Object| $user
     *
     * @return void
     */
    public function updateUserUniqueId($user, $useSubdomain = false): void
    {
        $requestContext = NGS()->createDefinedInstance('REQUEST_CONTEXT', \ngs\util\RequestContext::class);
        $domain = $requestContext->getHttpHost();
        if ($useSubdomain) {
            $domain = "." . $domain;
        }
        $cookieParams = $user->getCookieParams();
        setcookie("uh", $cookieParams["uh"], null, "/", $domain);
    }

    /**
     * this method for delete user from cookies,
     * Children of the NgsSessionManager class should override this method
     *
     * @abstract
     * @param AbstractDto|AbstractAction $request Object
     * @param Object $user | null
     * @return boolean
     */
    public function validateRequest($request, $user = null): bool
    {
        return false;
    }


    public function setSessionParam($key, $value)
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
            $_SESSION["ngs"] = [];
        }
        $_SESSION["ngs"][$key] = $value;
    }

    public function getSessionParam($key)
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION["ngs"]) && is_array($_SESSION["ngs"])) {
            if (isset($_SESSION["ngs"][$key])) {
                return $_SESSION["ngs"][$key];
            }
        }
        return null;
    }


    /**
     * return harco current logined or not
     * user object
     *
     * @return mixed
     */
    public function getUser($force = false)
    {
        return true;
    }

    public function setNoCache()
    {
        header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
    }

    public function setRequestHeader($name, $value = "")
    {
        $this->requestSessionHeadersArr[$name] = $value;
    }

    public function getRequestHeader()
    {
        return $this->requestSessionHeadersArr;
    }
}
