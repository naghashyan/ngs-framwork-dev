<?php

/**
 * Helper class for getting js files
 * have 3 general options connected with site mode (production/development)
 * 1. compress js files
 * 2. merge in one
 * 3. stream seperatly
 *
 * @author Levon Naghashyan <levon@naghashyan.com>
 * @site http://naghashyan.com
 * @year 2013-2014
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

use ngs\util\FormValidator;

class RequestValidator
{
    /**
     * @param $request
     * @param $arg
     * @return void
     */
    public function validate($request, $arg)
    {
    }

    /**
     * Validate email adress
     *
     * @param $str
     * @param $retMsg
     * @return bool|string
     */
    public static function validateEmail($str, $retMsg = true)
    {
        $email = FormValidator::secure($str);
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return true;
        }
        return "Please enter valid email";
    }

    /**
     * Validate string
     *
     * @param string $str
     * @param bool $retMsg
     * @return bool|string
     */
    public static function validateString($str, $len = 4, $allowChars = "/^[A-Za-z0-9\_\-\.]*$/", $retMsg = true)
    {
        $str = FormValidator::secure($str);
        if (empty($str)) {
            return "You can't leave this empty.";
        }
        if ($len) {
            if (strlen($str) < $len || strlen($str) > 30) {
                return "Please use between " . $len . " and 30 characters.";
            }
        }

        if ($allowChars) {
            if (!preg_match($allowChars, $str)) {
                return "Please use only letters (a-z), numbers, and periods.";
            }
        }
        return true;
    }

    /**
     * Validate string
     *
     * @param string $pass
     * @param string $rePass
     * @return bool|string
     */
    public static function validatePasswords(string $pass, string $rePass)
    {
        $pass = FormValidator::secure($pass);
        $rePass = FormValidator::secure($rePass);
        if ($pass !== $rePass) {
            return "These passwords don't match. Try again?";
        }
        return true;
    }

    public static function secure($var)
    {
        return trim(htmlspecialchars(strip_tags($var)));
    }
}
