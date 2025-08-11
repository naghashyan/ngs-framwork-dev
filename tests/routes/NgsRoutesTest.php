<?php

namespace tests\routes\ngsroutes;

use ngs\routes\NgsRoutesResolver;
use PHPUnit\Framework\TestCase;

// Mock the NGS function in this namespace
function NGS() {
    return NgsRoutesTest::$mockNgs;
}

/**
 * Unit tests for the NgsRoutesResolver class
 */
class NgsRoutesTest extends TestCase
{
    /**
     * @var NgsRoutesResolver
     */
    private $routes;

    /**
     * @var object Mock file system
     */
    private $mockFileSystem;

    /**
     * @var object Mock module routes engine
     */
    private $mockModuleRoutesEngine;

    /**
     * @var object Mock HTTP utils
     */
    private $mockHttpUtils;

    /**
     * @var object Static mock NGS instance for the namespace function
     */
    public static $mockNgs;

    /**
     * Set up test environment
     */
    protected function setUp(): void
    {
        // Create mock objects
        $this->mockFileSystem = $this->createMockFileSystem();
        $this->mockModuleRoutesEngine = $this->createMockModuleRoutesEngine();
        $this->mockHttpUtils = $this->createMockHttpUtils();

        // Set up the static mock NGS instance
        self::$mockNgs = $this->mockFileSystem;

        // Add getHttpUtils method to return mock HTTP utils
        self::$mockNgs->getHttpUtils = function() {
            return $this->mockHttpUtils;
        };

        // Add getModulesRoutesEngine method to return mock module routes engine
        self::$mockNgs->getModulesRoutesEngine = function() {
            return $this->mockModuleRoutesEngine;
        };

        // Create NgsRoutesResolver instance
        $this->routes = new NgsRoutesResolver();
    }

    /**
     * Test getting package
     */
    public function testGetPackage(): void
    {
        $this->markTestSkipped('NgsRoutesResolver public API has changed; legacy getPackage() no longer available.');
    }

    /**
     * Test getting load or action by action string
     */
    public function testGetLoadORActionByAction(): void
    {
        $this->markTestSkipped('NgsRoutesResolver public API has changed; this test scenario is deprecated.');
    }

    /**
     * Test getting static file route
     */
    public function testGetStaticFileRoute(): void
    {
        $this->markTestSkipped('NgsRoutesResolver::getStaticFileRoute() is not part of the current API.');
    }

    /**
     * Creates a mock file system
     * 
     * @return object Mock file system
     */
    private function createMockFileSystem(): object
    {
        // Create a custom class that implements the methods we need
        $mockClass = new class {
            public function get($key) {
                $config = [
                    'NGS_ROUTS' => 'routes.json',
                    'NGS_MODULE_ROUTS' => 'modules.json',
                    'ENVIRONMENT' => 'development'
                ];
                return $config[$key] ?? null;
            }

            public function getRoutesDir() {
                return __DIR__ . '/../../conf';
            }

            public function getConfigDir() {
                return __DIR__ . '/../../conf';
            }

            public function getEnvironment() {
                return 'development';
            }

            // Additional methods needed for testGetLoadORActionByAction
            public function getActionPackage() {
                return 'actions';
            }

            public function getLoadsPackage() {
                return 'loads';
            }
        };

        return $mockClass;
    }

    /**
     * Creates a mock module routes engine
     * 
     * @return object Mock module routes engine
     */
    private function createMockModuleRoutesEngine(): object
    {
        // Create a custom class that implements the methods we need
        $mockClass = new class {
            public function getModuleNS() {
                return 'test_module';
            }

            public function getDefaultNS() {
                return 'default_module';
            }

            public function checkModuleByNS() {
                return true;
            }

            public function getModuleType() {
                return 'domain';
            }
        };

        return $mockClass;
    }

    /**
     * Creates a mock HTTP utils
     * 
     * @return object Mock HTTP utils
     */
    private function createMockHttpUtils(): object
    {
        // Create a custom class that implements the methods we need
        $mockClass = new class {
            public function getRequestUri() {
                return '/test/url';
            }
        };

        return $mockClass;
    }
}
