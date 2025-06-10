<?php

namespace ngs\tests\routes;

use ngs\routes\NgsModuleRoutes;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the NgsModuleRoutes class
 */
class NgsModuleRoutesTest extends TestCase
{
    /**
     * @var NgsModuleRoutes
     */
    private $moduleRoutes;
    
    /**
     * @var object Mock file system
     */
    private $mockFileSystem;
    
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
        $this->mockHttpUtils = $this->createMockHttpUtils();
        
        // Create NgsModuleRoutes instance with mock dependencies
        $this->moduleRoutes = new NgsModuleRoutes(
            $this->mockFileSystem,
            $this->mockHttpUtils
        );
    }
    
    /**
     * Test getting default namespace
     */
    public function testGetDefaultNS(): void
    {
        $this->assertEquals('default_module', $this->moduleRoutes->getDefaultNS());
    }
    
    /**
     * Test checking module by URI
     */
    public function testCheckModuleByUri(): void
    {
        // Test with existing module
        $this->assertTrue($this->moduleRoutes->checkModuleByUri('test_module'));
        
        // Test with non-existing module
        $this->assertFalse($this->moduleRoutes->checkModuleByUri('non_existing_module'));
    }
    
    /**
     * Test checking module by namespace
     */
    public function testCheckModuleByNS(): void
    {
        // Test with existing module
        $this->assertTrue($this->moduleRoutes->checkModuleByNS('test_module'));
        
        // Test with non-existing module
        $this->assertFalse($this->moduleRoutes->checkModuleByNS('non_existing_module'));
    }
    
    /**
     * Test getting module namespace
     */
    public function testGetModuleNS(): void
    {
        $this->assertEquals('test_module', $this->moduleRoutes->getModuleNS());
    }
    
    /**
     * Test getting module type
     */
    public function testGetModuleType(): void
    {
        $this->assertEquals('domain', $this->moduleRoutes->getModuleType());
    }
    
    /**
     * Test getting module URI
     */
    public function testGetModuleUri(): void
    {
        $this->assertEquals('test_uri', $this->moduleRoutes->getModuleUri());
    }
    
    /**
     * Test getting all modules
     */
    public function testGetAllModules(): void
    {
        $modules = $this->moduleRoutes->getAllModules();
        $this->assertIsArray($modules);
        $this->assertContains('test_module', $modules);
    }
    
    /**
     * Test getting root directory
     */
    public function testGetRootDir(): void
    {
        // Test with default module
        $this->assertEquals('/path/to/root', $this->moduleRoutes->getRootDir('default_module'));
        
        // Test with framework module
        $this->assertEquals('/path/to/framework', $this->moduleRoutes->getRootDir('framework'));
        
        // Test with CMS module
        $this->assertEquals('/path/to/cms', $this->moduleRoutes->getRootDir('cms'));
        
        // Test with regular module
        $this->assertNull($this->moduleRoutes->getRootDir('test_module'));
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
                    'NGS_ROOT' => '/path/to/root',
                    'MODULES_DIR' => 'modules',
                    'FRAMEWORK_NS' => 'framework',
                    'NGS_CMS_NS' => 'cms',
                    'NGS_MODULS_ROUTS' => 'modules.json'
                ];
                return $config[$key] ?? null;
            });
        
        $mock->method('getFrameworkDir')
            ->willReturn('/path/to/framework');
        
        $mock->method('getNgsCmsDir')
            ->willReturn('/path/to/cms');
        
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
        
        $mock->method('_getHttpHost')
            ->willReturn('test.example.com');
        
        $mock->method('getMainDomain')
            ->willReturn('example.com');
        
        $mock->method('getRequestUri')
            ->willReturn('/test/url');
        
        return $mock;
    }
}