<?php

namespace ngs\tests\routes;

use ngs\routes\NgsRoutes;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the NgsRoutes class
 */
class NgsRoutesTest extends TestCase
{
    /**
     * @var NgsRoutes
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
     * Set up test environment
     */
    protected function setUp(): void
    {
        // Create mock objects
        $this->mockFileSystem = $this->createMockFileSystem();
        $this->mockModuleRoutesEngine = $this->createMockModuleRoutesEngine();
        $this->mockHttpUtils = $this->createMockHttpUtils();
        
        // Create NgsRoutes instance with mock dependencies
        $this->routes = new NgsRoutes(
            $this->mockFileSystem,
            $this->mockModuleRoutesEngine,
            $this->mockHttpUtils
        );
    }
    
    /**
     * Test normalizing URLs
     */
    public function testNormalizeUrl(): void
    {
        $method = new \ReflectionMethod(NgsRoutes::class, 'normalizeUrl');
        $method->setAccessible(true);
        
        // Test with leading slash
        $this->assertEquals('test/url', $method->invoke($this->routes, '/test/url'));
        
        // Test without leading slash
        $this->assertEquals('test/url', $method->invoke($this->routes, 'test/url'));
    }
    
    /**
     * Test parsing URLs
     */
    public function testParseUrl(): void
    {
        $method = new \ReflectionMethod(NgsRoutes::class, 'parseUrl');
        $method->setAccessible(true);
        
        // Test normal URL
        $result = $method->invoke($this->routes, 'test/url/path', false);
        $this->assertCount(5, $result);
        $this->assertEquals(['test', 'url', 'path'], $result[0]);
        $this->assertEquals(['url', 'path'], $result[1]);
        $this->assertEquals('test', $result[2]);
        $this->assertEquals('test/url/path', $result[3]);
        $this->assertFalse($result[4]);
        
        // Test 404 URL
        $result = $method->invoke($this->routes, 'test/url/path', true);
        $this->assertEquals('404', $result[2]);
        
        // Test static file URL
        $result = $method->invoke($this->routes, 'test/url/file.css', false);
        $this->assertTrue($result[4]);
    }
    
    /**
     * Test getting load or action by action string
     */
    public function testGetLoadORActionByAction(): void
    {
        // Mock getActionPackage and getLoadsPackage methods
        $this->mockFileSystem->method('getActionPackage')
            ->willReturn('actions');
        $this->mockFileSystem->method('getLoadsPackage')
            ->willReturn('loads');
        
        // Test load action
        $result = $this->routes->getLoadORActionByAction('module.loads.package.test_action');
        $this->assertEquals('module\\loads\\package\\TestActionLoad', $result['action']);
        $this->assertEquals('load', $result['type']);
        
        // Test action action
        $result = $this->routes->getLoadORActionByAction('module.actions.package.do_test_action');
        $this->assertEquals('module\\actions\\package\\TestActionAction', $result['action']);
        $this->assertEquals('action', $result['type']);
        
        // Test null action
        $this->assertNull($this->routes->getLoadORActionByAction(null));
    }
    
    /**
     * Test getting static file route
     */
    public function testGetStaticFileRoute(): void
    {
        // Mock checkModuleByName and getModuleName methods
        $this->mockModuleRoutesEngine->method('checkModuleByName')
            ->willReturn(true);
        $this->mockModuleRoutesEngine->method('getModuleName')
            ->willReturn('test_module');
        $this->mockModuleRoutesEngine->method('getModuleType')
            ->willReturn('domain');
        
        $matches = ['test', 'path', 'file.css'];
        $urlMatches = ['test', 'path', 'file.css'];
        $fileUrl = 'test/path/file.css';
        
        $result = $this->routes->getStaticFileRoute($matches, $urlMatches, $fileUrl);
        
        $this->assertEquals('file', $result['type']);
        $this->assertEquals('css', $result['file_type']);
        $this->assertEquals('test', $result['module']);
        $this->assertTrue($result['matched']);
    }
    
    /**
     * Creates a mock file system
     * 
     * @return object Mock file system
     */
    private function createMockFileSystem(): object
    {
        $mock = $this->createMock(\stdClass::class);
        
        $mock->method('get')
            ->willReturnCallback(function ($key) {
                $config = [
                    'NGS_ROUTS' => 'routes.json',
                    'NGS_MODULE_ROUTS' => 'modules.json',
                    'ENVIRONMENT' => 'development'
                ];
                return $config[$key] ?? null;
            });
        
        $mock->method('getRoutesDir')
            ->willReturn(__DIR__ . '/../../../../conf');
        
        $mock->method('getConfigDir')
            ->willReturn(__DIR__ . '/../../../../conf');
        
        $mock->method('getEnvironment')
            ->willReturn('development');
        
        return $mock;
    }
    
    /**
     * Creates a mock module routes engine
     * 
     * @return object Mock module routes engine
     */
    private function createMockModuleRoutesEngine(): object
    {
        $mock = $this->createMock(\stdClass::class);
        
        $mock->method('getModuleName')
            ->willReturn('test_module');
        
        $mock->method('getDefaultNS')
            ->willReturn('default_module');
        
        $mock->method('checkModulByNS')
            ->willReturn(true);

        $mock->method('checkModuleByName')
            ->willReturn(true);
        
        return $mock;
    }
    
    /**
     * Creates a mock HTTP utils
     * 
     * @return object Mock HTTP utils
     */
    private function createMockHttpUtils(): object
    {
        $mock = $this->createMock(\stdClass::class);
        
        $mock->method('getRequestUri')
            ->willReturn('/test/url');
        
        return $mock;
    }
}