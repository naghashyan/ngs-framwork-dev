<?php

namespace tests\routes;

use ngs\NgsModule;
use ngs\routes\NgsRoutesResolver;
use PHPUnit\Framework\TestCase;

class NgsRoutesResolverTest extends TestCase
{
    private string $routesDir;

    protected function setUp(): void
    {
        // The runner changes cwd to htdocs; config dir is htdocs/conf
        $confDir = getcwd() . '/conf';
        $this->routesDir = $confDir . '/routes';
        if (!is_dir($this->routesDir)) {
            @mkdir($this->routesDir, 0777, true);
        }

        // Ensure a clean state for account package
        @unlink($this->routesDir . '/account.json');
        @unlink($this->routesDir . '/account.404.json');
    }

    protected function tearDown(): void
    {
        @unlink($this->routesDir . '/account.json');
        @unlink($this->routesDir . '/account.404.json');
    }

    private function writeRoutes(string $package, array $routes): void
    {
        file_put_contents($this->routesDir . '/' . $package . '.json', json_encode($routes, JSON_PRETTY_PRINT));
    }

    private function write404(string $package, array $data): void
    {
        file_put_contents($this->routesDir . '/' . $package . '.404.json', json_encode($data, JSON_PRETTY_PRINT));
    }

    private function makeModule(): NgsModule
    {
        // No moduleDir ensures configDir resolves to getcwd().'/conf'
        return new NgsModule(null, NgsModule::MODULE_TYPE_DOMAIN);
    }

    public function testDefaultRouteWithEmptyStringMatches(): void
    {
        $this->writeRoutes('account', [
            [
                'route' => '',
                'action' => 'loads.account.main.main'
            ]
        ]);
        $this->write404('account', [ 'action' => 'loads.account.errors.not_found' ]);

        $resolver = new NgsRoutesResolver();
        $route = $resolver->resolveRoute($this->makeModule(), '/account');

        $this->assertTrue($route->isMatched());
        $this->assertEquals('loads.account.errors.not_found', $route->getNotFoundRequest());
        // Args should be empty for default
        $this->assertSame([], $route->getArgs());
    }

    public function testOrderIsPreservedAndParameterizedFirst(): void
    {
        $this->writeRoutes('account', [
            [
                'route' => 'update-email[/:code]',
                'action' => 'loads.main.change_email',
                'constraints' => [ 'code' => '[A-Za-z0-9]+' ]
            ],
            [
                'route' => 'update-email',
                'action' => 'loads.account.main.update_email_simple'
            ]
        ]);

        $resolver = new NgsRoutesResolver();
        $route = $resolver->resolveRoute($this->makeModule(), '/account/update-email/ABC123');

        $this->assertTrue($route->isMatched());
        // Named param should be extracted, no positional leftovers
        $this->assertEquals(['code' => 'ABC123'], $route->getArgs());
    }

    public function testUnconstrainedParameterIsAccepted(): void
    {
        $this->writeRoutes('account', [
            [
                'route' => 'static/:slug',
                'action' => 'loads.account.static.static'
            ]
        ]);

        $resolver = new NgsRoutesResolver();
        $route = $resolver->resolveRoute($this->makeModule(), '/account/static/hello-world');

        $this->assertTrue($route->isMatched());
        $this->assertEquals(['slug' => 'hello-world'], $route->getArgs());
    }

    public function testNestedLoadIsExposedOnRoute(): void
    {
        $this->writeRoutes('account', [
            [
                'route' => 'overview',
                'action' => 'loads.account.main.main',
                'nestedLoad' => [
                    'content' => [ 'action' => 'loads.account.main.overview' ]
                ]
            ]
        ]);

        $resolver = new NgsRoutesResolver();
        $route = $resolver->resolveRoute($this->makeModule(), '/account/overview');

        $this->assertTrue($route->isMatched());
        $this->assertArrayHasKey('content', $route->getNestedLoad());
        $this->assertEquals('loads.account.main.overview', $route->getNestedLoad()['content']['action']);
    }

    public function testNotFoundRequestIsLoadedFrom404File(): void
    {
        $this->writeRoutes('account', [
            [ 'route' => '', 'action' => 'loads.account.main.main' ]
        ]);
        $this->write404('account', [ 'request' => 'loads.account.errors.not_found' ]);

        $resolver = new NgsRoutesResolver();
        $route = $resolver->resolveRoute($this->makeModule(), '/account');

        $this->assertEquals('loads.account.errors.not_found', $route->getNotFoundRequest());
    }
}
