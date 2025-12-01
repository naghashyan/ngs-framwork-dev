<?php

/**
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
