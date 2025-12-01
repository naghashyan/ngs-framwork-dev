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

namespace ngs\exceptions {
    class NoAccessException extends InvalidUserException
    {
        protected $httpCode = 403;

        public function __construct($msg = "access denied", $code = -5)
        {
            parent::__construct($msg, $code);
        }
    }
}
