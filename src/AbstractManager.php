<?php

/**
 * parent class of all ngs managers
 *
 * @author Levon Naghashyan <levon@naghashyan.com>
 * @site http://naghashyan.com
 * @year 2009-2023
 * @package ngs.framework
 * @version 4.5.0
 *
 * This file is part of the NGS package.
 *
 * @copyright Naghashyan Solutions LLC
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace ngs;

use Exception;

abstract class AbstractManager
{
    public function __construct()
    {
    }

    protected $orderFields = [];

    /**
     * @param $dataObject
     * @param $paramsArray
     * @return bool
     */
    public function validateMustBeParameters($dataObject, $paramsArray)
    {
        foreach ($paramsArray as $param) {
            $functionName = "get" . ucfirst($param);
            $paramValue = $dataObject->$functionName();
            if ($paramValue == null || $paramValue == "") {
                throw new Exception("The parameter " . $param . " is missing.");
            }
        }
        return true;
    }

    public function validateOrderFileld($key)
    {
        if ($this->orderFields[$key]) {
            return true;
        }
        return false;
    }

    /**
     * Simple hashcode generator
     *
     * @return string
     */
    public function generateHashcode()
    {
        $str = time();
        return md5($str . "_" . rand(0, 50000));
    }
}
