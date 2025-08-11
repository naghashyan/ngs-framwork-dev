<?php

namespace tests\routes;

use ngs\routes\NgsModuleResolver;
use ngs\NgsModule;
use PHPUnit\Framework\TestCase;

class TestRequestContext extends \ngs\util\RequestContext
{
    public static string $host = '';
    public static string $mainDomain = '';
    public static string $uri = '/';

    public function getHttpHost($withPath = false, $withProtocol = false, $main = false)
    {
        return self::$host;
    }

    public function getMainDomain()
    {
        return self::$mainDomain;
    }

    public function getRequestUri($full = false)
    {
        return self::$uri;
    }
}

class NgsModuleRoutesTest extends TestCase
{
    private string $modulesJsonBackup = '';

    protected function setUp(): void
    {
        // Setup environment to initialize NGS without document root restriction
        putenv('SKIP_DOCUMENT_ROOT_CHECK=true');
        putenv('SKIP_NGS_INIT=true');

        // Ensure NGS is initialized and set our custom RequestContext
        \NGS()->define('NGS_ROOT', getcwd());
        \NGS()->define('REQUEST_CONTEXT', \tests\routes\TestRequestContext::class);

        // Define required constants usually set by defaults
        \NGS()->define('CONF_DIR', 'conf');
        \NGS()->define('NGS_MODULS_ROUTS', 'modules.json');
        \NGS()->define('DYN_URL_TOKEN', 'dyn');

        // Backup existing modules.json
        $confDir = \NGS()->get('NGS_ROOT') . '/conf';
        $modulesFile = $confDir . '/modules.json';
        if (file_exists($modulesFile)) {
            $this->modulesJsonBackup = file_get_contents($modulesFile) ?: '';
        }

        // Write test modules.json according to modules.md
        $modules = [
            'default' => [
                'subdomain' => [
                    'admin' => ['dir' => 'TestModule1']
                ],
                'path' => [
                    'tools' => ['dir' => 'TestModule1']
                ],
                'domain' => [
                    'map' => 'TestModule1'
                ],
                'default' => ['dir' => 'TestModule1']
            ]
        ];
        file_put_contents($modulesFile, json_encode($modules, JSON_PRETTY_PRINT));
    }

    protected function tearDown(): void
    {
        // Restore original modules.json
        $confDir = \NGS()->get('NGS_ROOT') . '/conf';
        $modulesFile = $confDir . '/modules.json';
        if ($this->modulesJsonBackup !== '') {
            file_put_contents($modulesFile, $this->modulesJsonBackup);
        }
    }

    public function testPathRoutingResolvesModule(): void
    {
        // Use a host different from main domain so domain mapping won't take precedence
        TestRequestContext::$host = 'other.com';
        TestRequestContext::$mainDomain = 'example.com';
        TestRequestContext::$uri = '/tools/dashboard';

        $resolver = new NgsModuleResolver();
        $module = $resolver->resolveModule('/tools/dashboard');
        $this->assertInstanceOf(NgsModule::class, $module);
        $this->assertSame('path', $module->getType());
    }

    public function testSubdomainRoutingResolvesModule(): void
    {
        TestRequestContext::$host = 'admin.example.com';
        TestRequestContext::$mainDomain = 'example.com';
        TestRequestContext::$uri = '/';

        $resolver = new NgsModuleResolver();
        $module = $resolver->resolveModule('/');
        $this->assertInstanceOf(NgsModule::class, $module);
        $this->assertSame('subdomain', $module->getType());
    }

    public function testDomainMappingResolvesModule(): void
    {
        TestRequestContext::$host = 'example.com';
        TestRequestContext::$mainDomain = 'example.com';
        TestRequestContext::$uri = '/';

        $resolver = new NgsModuleResolver();
        $module = $resolver->resolveModule('/');
        $this->assertInstanceOf(NgsModule::class, $module);
        $this->assertSame('domain', $module->getType());
    }

    public function testDefaultModuleWhenNoDomain(): void
    {
        TestRequestContext::$host = '';
        TestRequestContext::$mainDomain = 'example.com';
        TestRequestContext::$uri = '/';

        $resolver = new NgsModuleResolver();
        $module = $resolver->resolveModule('/');
        $this->assertInstanceOf(NgsModule::class, $module);
        $this->assertSame('domain', $module->getType());
    }

    public function testGetAllModulesIncludesConfiguredOnes(): void
    {
        $resolver = new NgsModuleResolver();
        $all = $resolver->getAllModules();
        $this->assertIsArray($all);
        $this->assertNotEmpty($all);
        $this->assertContainsOnlyInstancesOf(NgsModule::class, $all);
    }
}
