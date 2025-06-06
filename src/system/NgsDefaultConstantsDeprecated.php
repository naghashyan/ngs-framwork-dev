<?php

/**
 * Deprecated NGS constants
 * This file contains constants that are deprecated but maintained for backward compatibility
 *
 * @author Levon Naghashyan <levon@naghashyan.com>
 * @site https://naghashyan.com
 * @year 2023
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
| DEPRECATED CONSTANTS
|--------------------------------------------------------------------------
| These constants are maintained for backward compatibility but should not be used in new code.
| They will be removed in future versions.
*/

// @deprecated since version 4.0.0, use REQUEST_CONTEXT instead
NGS()->define('HTTP_UTILS', 'ngs\util\HttpUtils');
