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
| CONSTANTS
|--------------------------------------------------------------------------
*/

// Version and namespace constants
NGS()->define('VERSION', '1.0.0');
NGS()->define('NGSVERSION', '4.0.0');
NGS()->define('FRAMEWORK_NS', 'ngs');
NGS()->define('DEFAULT_NS', 'ngs');
NGS()->define('NGS_CMS_NS', 'ngs-AdminTools');
NGS()->define('NGS_PROJECT_OWNER', 'www-data');
NGS()->define('IM_TOKEN_COOKIE_KEY', '_im_token');

// Environment settings
$environment = 'production';
if (isset($_SERVER['ENVIRONMENT'])) {
    if ($_SERVER['ENVIRONMENT'] == 'development' || $_SERVER['ENVIRONMENT'] == 'dev') {
        $environment = 'development';
    } elseif ($_SERVER['ENVIRONMENT'] == 'staging') {
        $environment = 'staging';
    }
}
NGS()->define('ENVIRONMENT', $environment);
NGS()->define('JS_FRAMEWORK_ENABLE', true);
NGS()->define('SEND_HTTP_PUSH', true);

// Document root validation and NGS root definition
if (strpos(getcwd(), '/htdocs') == false && strpos(getcwd(), '\htdocs') == false) {
    throw new Exception('please change document root to htdocs');
}
if (strpos(getcwd(), '/htdocs') !== false) {
    $ngsRoot = substr(getcwd(), 0, strrpos(getcwd(), '/htdocs'));
} else {
    $ngsRoot = substr(getcwd(), 0, strrpos(getcwd(), '\htdocs'));
}
NGS()->define('NGS_ROOT', $ngsRoot);

// Build modes
NGS()->define('JS_BUILD_MODE', $environment);
NGS()->define('LESS_BUILD_MODE', $environment);
NGS()->define('SASS_BUILD_MODE', $environment);

// Module settings
NGS()->define('MODULES_ENABLE', true);

// Routes configuration
NGS()->define('NGS_ROUTS', 'routes.json');
NGS()->define('NGS_ROUTS_ARRAY', 'routes.json');
NGS()->define('NGS_MODULS_ROUTS', 'modules.json');

// Smarty settings
NGS()->define('USE_SMARTY', true);

// Other constants
NGS()->define('BULK_UPDATE_LIMIT', 50);

/*
|--------------------------------------------------------------------------
| DIRECTORIES
|--------------------------------------------------------------------------
*/

// Project structure directories
NGS()->define('CLASSES_DIR', 'classes');
NGS()->define('PUBLIC_DIR', 'htdocs');
NGS()->define('PUBLIC_OUTPUT_DIR', 'out');
NGS()->define('WEB_DIR', 'web');
NGS()->define('CONF_DIR', 'conf');
NGS()->define('DATA_DIR', 'data');
NGS()->define('TEMP_DIR', 'temp');
NGS()->define('BIN_DIR', 'bin');
NGS()->define('TEMPLATES_DIR', 'templates');
NGS()->define('LOADS_DIR', 'loads');
NGS()->define('ACTIONS_DIR', 'actions');
NGS()->define('MODULES_DIR', 'modules');

// Asset directories
NGS()->define('CSS_DIR', 'css');
NGS()->define('LESS_DIR', 'less');
NGS()->define('SASS_DIR', 'sass');
NGS()->define('JS_DIR', 'js');

// Smarty directories
NGS()->define('SMARTY_CACHE_DIR', 'cache');
NGS()->define('SMARTY_COMPILE_DIR', 'compile');

/*
|--------------------------------------------------------------------------
| CLASSES
|--------------------------------------------------------------------------
*/

// Core framework classes
NGS()->define('LOAD_MAPPER', 'ngs\routes\NgsLoadMapper');
NGS()->define('SESSION_MANAGER', 'ngs\session\NgsSessionManager');
NGS()->define('TEMPLATE_ENGINE', 'ngs\templater\NgsTemplater');
NGS()->define('FILE_UTILS', 'ngs\util\FileUtils');
NGS()->define('REQUEST_CONTEXT', 'ngs\util\RequestContext');
NGS()->define('MODULES_ROUTES_ENGINE', 'ngs\routes\NgsModuleRoutes');
NGS()->define('ROUTES_ENGINE', 'ngs\routes\NgsRoutes');
NGS()->define('NGS_UTILS', 'ngs\util\NgsUtils');
NGS()->define('NGS_MYSQL_PDO_DRIVER', '\ngs\dal\connectors\MysqlPDO');

// Asset builder classes
NGS()->define('JS_BUILDER', 'ngs\util\JsBuilderV2');
NGS()->define('CSS_BUILDER', 'ngs\util\CssBuilder');
NGS()->define('LESS_BUILDER', 'ngs\util\LessBuilder');
NGS()->define('SASS_BUILDER', 'ngs\util\SassBuilder');
NGS()->define('LESS_ENGINE', 'lib/less.php/Less.php');

// Exception handler classes
NGS()->define('NGS_EXCEPTION_DEBUG', 'ngs\exceptions\DebugException');
NGS()->define('NGS_EXCEPTION_INVALID_USER', 'ngs\exceptions\InvalidUserException');
NGS()->define('NGS_EXCEPTION_NGS_ERROR', 'ngs\exceptions\NgsErrorException');
NGS()->define('NGS_EXCEPTION_NO_ACCESS', 'ngs\exceptions\NoAccessException');
NGS()->define('NGS_EXCEPTION_NOT_FOUND', 'ngs\exceptions\NotFoundException');

/*
|--------------------------------------------------------------------------
| INCLUDE DEPRECATED CONSTANTS
|--------------------------------------------------------------------------
*/
include_once __DIR__ . '/NgsDefaultConstantsDeprecated.php';
