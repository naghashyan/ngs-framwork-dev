<?php

/**
 * Base ngs class
 * for static function that will
 * vissible from any classes
 *
 * @author Levon Naghashyan <levon@naghashyan.com>
 * @site https://naghashyan.com
 * @year 2015-2019
 * @package ngs.framework.system
 * @version 4.0.0
 *
 * This file is part of the NGS package.
 *
 * @copyright Naghashyan Solutions LLC
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */
//TODO: ZN: this should be removed - already have it in the NGS.php
/*
 |--------------------------------------------------------------------------
 | DEFINING VARIABLES IF SCRIPT RUNNING FROM COMMAND LINE
 |--------------------------------------------------------------------------
 */
if (php_sapi_name() == 'cli' && NGS()->get('CMD_SCRIPT')) {
    $args = null;
    if (isset($argv) && isset($argv[1])) {
        $args = substr($argv[1], strpos($argv[1], '?') + 1);
        $uri = substr($argv[1], 0, strpos($argv[1], '?'));
        $_SERVER['REQUEST_URI'] = $uri;
    }

    if ($args != null) {
        $queryArgsArr = explode('&', $args);
        foreach ($queryArgsArr as $value) {
            $_arg = explode('=', $value);
            if (isset($_REQUEST[$_arg[0]])) {
                if (is_array($_REQUEST[$_arg[0]])) {
                    $tmp = $_REQUEST[$_arg[0]];
                } else {
                    $tmp = [];
                    $tmp[] = $_REQUEST[$_arg[0]];
                }
                $tmp[] = $_arg[1];
                $_REQUEST[$_arg[0]] = $tmp;
            } else {
                $_REQUEST[$_arg[0]] = $_arg[1];
            }
        }
    }

    if (isset($argv[2]) && !isset($_SERVER['ENVIRONMENT'])) {
        $_SERVER['ENVIRONMENT'] = $argv[2];
    }

    $_SERVER['HTTP_HOST'] = '';
}

/*
|--------------------------------------------------------------------------
| DETERMINE ENVIRONMENT
|--------------------------------------------------------------------------
*/
$environment = 'production';
if (isset($_SERVER['ENVIRONMENT'])) {
    if ($_SERVER['ENVIRONMENT'] == 'development' || $_SERVER['ENVIRONMENT'] == 'dev') {
        $environment = 'development';
    } elseif ($_SERVER['ENVIRONMENT'] == 'staging') {
        $environment = 'staging';
    }
}

/*
|--------------------------------------------------------------------------
| DOCUMENT ROOT VALIDATION AND NGS ROOT DEFINITION
|--------------------------------------------------------------------------
*/
if (strpos(getcwd(), '/htdocs') == false && strpos(getcwd(), '\htdocs') == false) {
    throw new Exception('please change document root to htdocs');
}
if (strpos(getcwd(), '/htdocs') !== false) {
    $ngsRoot = substr(getcwd(), 0, strrpos(getcwd(), '/htdocs'));
} else {
    $ngsRoot = substr(getcwd(), 0, strrpos(getcwd(), '\htdocs'));
}
NGS()->define('NGS_ROOT', $ngsRoot);

/*
|--------------------------------------------------------------------------
| LOAD CONSTANTS FROM JSON FILE
|--------------------------------------------------------------------------
*/
$constantsFile = __DIR__ . '/../../conf/constants.json';
if (file_exists($constantsFile)) {
    $constants = json_decode(file_get_contents($constantsFile), true);
    if ($constants) {
        // Function to recursively process constants
        $processConstants = function($data, $prefix = '') use (&$processConstants, $environment) {
            foreach ($data as $key => $value) {
                // Skip keys starting with underscore (metadata)
                if (substr($key, 0, 1) === '_') {
                    continue;
                }

                // If value is an array and has _environment_dependent flag
                if (is_array($value) && isset($value['_environment_dependent']) && $value['_environment_dependent'] === true) {
                    // Use environment-specific value if available, otherwise use default
                    $actualValue = isset($value[$environment]) ? $value[$environment] : $value['default'];
                    NGS()->define($key, $actualValue);
                }
                // If value is an array but doesn't have _environment_dependent flag, it's a group
                elseif (is_array($value) && !isset($value['_environment_dependent'])) {
                    $processConstants($value, $prefix . $key . '.');
                }
                // Otherwise it's a regular constant
                else {
                    NGS()->define($key, $value);
                }
            }
        };

        // Process each section of constants
        foreach ($constants as $section => $sectionConstants) {
            // Skip deprecated section, it will be handled separately
            if ($section === 'deprecated') {
                continue;
            }

            // Process constants in this section
            $processConstants($sectionConstants);
        }
    }
}

/*
|--------------------------------------------------------------------------
| INCLUDE DEPRECATED CONSTANTS
|--------------------------------------------------------------------------
*/
include_once __DIR__ . '/NgsDefaultConstantsDeprecated.php';
