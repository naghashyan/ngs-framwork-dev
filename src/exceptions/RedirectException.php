<?php

/**
 *
 * @author Levon Naghashyan <levon@naghashyan.com>
 * @site http://naghashyan.com
 * @year 2009-2016
 * @package framework
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

namespace ngs\exceptions;

class RedirectException extends \Exception
{
    private $redirectTo;

    /**
     * Return a thingie based on $paramie
     * @abstract
     * @access
     * @param $redirectTo
     * @param $message
     * @return void integer|babyclass
     */
    public function __construct($redirectTo, $message)
    {
        $this->redirectTo = $redirectTo;
        parent::__construct($message, 1);
    }

    public function getRedirectTo()
    {
        return $this->redirectTo;
    }
}
