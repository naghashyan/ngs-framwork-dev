<?php

namespace ngs\tests;

/**
 * Simple test script to verify constants are correctly loaded from JSON file
 */

// Include the NGS bootstrap file
require_once __DIR__ . '/../src/NGS.php';

// Test constants loading
echo "Testing Constants Loading from JSON:\n";

// Test version constants
echo "\nTesting Version Constants:\n";
echo "VERSION: " . (\ngs\NGS::getInstance()->defined('VERSION') ? \ngs\NGS::getInstance()->get('VERSION') : 'Not defined') . "\n";
echo "NGSVERSION: " . (\ngs\NGS::getInstance()->defined('NGSVERSION') ? \ngs\NGS::getInstance()->get('NGSVERSION') : 'Not defined') . "\n";
echo "FRAMEWORK_NS: " . (\ngs\NGS::getInstance()->defined('FRAMEWORK_NS') ? \ngs\NGS::getInstance()->get('FRAMEWORK_NS') : 'Not defined') . "\n";

// Test environment constants
echo "\nTesting Environment Constants:\n";
echo "ENVIRONMENT: " . (\ngs\NGS::getInstance()->defined('ENVIRONMENT') ? \ngs\NGS::getInstance()->get('ENVIRONMENT') : 'Not defined') . "\n";
echo "JS_FRAMEWORK_ENABLE: " . (\ngs\NGS::getInstance()->defined('JS_FRAMEWORK_ENABLE') ? var_export(\ngs\NGS::getInstance()->get('JS_FRAMEWORK_ENABLE'), true) : 'Not defined') . "\n";

// Test build mode constants
echo "\nTesting Build Mode Constants:\n";
echo "JS_BUILD_MODE: " . (\ngs\NGS::getInstance()->defined('JS_BUILD_MODE') ? \ngs\NGS::getInstance()->get('JS_BUILD_MODE') : 'Not defined') . "\n";
echo "LESS_BUILD_MODE: " . (\ngs\NGS::getInstance()->defined('LESS_BUILD_MODE') ? \ngs\NGS::getInstance()->get('LESS_BUILD_MODE') : 'Not defined') . "\n";
echo "SASS_BUILD_MODE: " . (\ngs\NGS::getInstance()->defined('SASS_BUILD_MODE') ? \ngs\NGS::getInstance()->get('SASS_BUILD_MODE') : 'Not defined') . "\n";

// Test directory constants
echo "\nTesting Directory Constants:\n";
echo "CLASSES_DIR: " . (\ngs\NGS::getInstance()->defined('CLASSES_DIR') ? \ngs\NGS::getInstance()->get('CLASSES_DIR') : 'Not defined') . "\n";
echo "CONF_DIR: " . (\ngs\NGS::getInstance()->defined('CONF_DIR') ? \ngs\NGS::getInstance()->get('CONF_DIR') : 'Not defined') . "\n";
echo "TEMPLATES_DIR: " . (\ngs\NGS::getInstance()->defined('TEMPLATES_DIR') ? \ngs\NGS::getInstance()->get('TEMPLATES_DIR') : 'Not defined') . "\n";

// Test class constants
echo "\nTesting Class Constants:\n";
echo "LOAD_MAPPER: " . (\ngs\NGS::getInstance()->defined('LOAD_MAPPER') ? \ngs\NGS::getInstance()->get('LOAD_MAPPER') : 'Not defined') . "\n";
echo "SESSION_MANAGER: " . (\ngs\NGS::getInstance()->defined('SESSION_MANAGER') ? \ngs\NGS::getInstance()->get('SESSION_MANAGER') : 'Not defined') . "\n";

// Test deprecated constants
echo "\nTesting Deprecated Constants:\n";
echo "HTTP_UTILS: " . (\ngs\NGS::getInstance()->defined('HTTP_UTILS') ? \ngs\NGS::getInstance()->get('HTTP_UTILS') : 'Not defined') . "\n";

echo "\nTest completed.\n";