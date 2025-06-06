<?php

namespace ngs\tests\util;

// Include the required file directly since autoloading might not be working
require_once __DIR__ . '/../../src/util/RequestContext.php';

use ngs\util\RequestContext;

/**
 * Simple test script to verify RequestContext functionality
 */

// Function to simulate NGS() global function
if (!function_exists('NGS')) {
    function NGS() {
        static $instance = null;
        if ($instance === null) {
            $instance = new class {
                private $data = [];

                public function get($key) {
                    return isset($this->data[$key]) ? $this->data[$key] : null;
                }

                public function set($key, $value) {
                    $this->data[$key] = $value;
                    return $this;
                }

                public function createDefinedInstance($key, $class) {
                    return new class {
                        public function getModuleType() {
                            return "path";
                        }

                        public function getModuleUri() {
                            return "test-module";
                        }

                        public function isDefaultModule() {
                            return true;
                        }

                        public function isCurrentModule($ns) {
                            return true;
                        }

                        public function getModuleNS() {
                            return "test";
                        }
                    };
                }
            };
        }
        return $instance;
    }
}

// Set up test environment
$_SERVER["HTTP_X_REQUESTED_WITH"] = "XMLHttpRequest";
$_SERVER["HTTPS"] = "on";
$_SERVER["HTTP_HOST"] = "example.com";
$_SERVER["REQUEST_URI"] = "/test/path?query=value";

// Test RequestContext
echo "Testing RequestContext:\n";
$requestContext = new RequestContext();

echo "isAjaxRequest: " . ($requestContext->isAjaxRequest() ? "true" : "false") . "\n";
echo "getRequestProtocol: " . $requestContext->getRequestProtocol() . "\n";
echo "getHost: " . $requestContext->getHost() . "\n";
echo "getHttpHost: " . $requestContext->getHttpHost(true, true) . "\n";
echo "getRequestUri: " . $requestContext->getRequestUri() . "\n";

// Test another instance of RequestContext (to verify consistent behavior)
echo "\nTesting another RequestContext instance:\n";
$requestContext2 = new RequestContext();

echo "isAjaxRequest: " . ($requestContext2->isAjaxRequest() ? "true" : "false") . "\n";
echo "getRequestProtocol: " . $requestContext2->getRequestProtocol() . "\n";
echo "getHost: " . $requestContext2->getHost() . "\n";
echo "getHttpHost: " . $requestContext2->getHttpHost(true, true) . "\n";
echo "getRequestUri: " . $requestContext2->getRequestUri() . "\n";

// Verify that both instances return the same results
echo "\nVerifying that both instances return the same results:\n";
echo "isAjaxRequest: " . ($requestContext->isAjaxRequest() === $requestContext2->isAjaxRequest() ? "MATCH" : "DIFFERENT") . "\n";
echo "getRequestProtocol: " . ($requestContext->getRequestProtocol() === $requestContext2->getRequestProtocol() ? "MATCH" : "DIFFERENT") . "\n";
echo "getHost: " . ($requestContext->getHost() === $requestContext2->getHost() ? "MATCH" : "DIFFERENT") . "\n";
echo "getHttpHost: " . ($requestContext->getHttpHost(true, true) === $requestContext2->getHttpHost(true, true) ? "MATCH" : "DIFFERENT") . "\n";
echo "getRequestUri: " . ($requestContext->getRequestUri() === $requestContext2->getRequestUri() ? "MATCH" : "DIFFERENT") . "\n";

echo "\nTest completed.\n";
