<?php

declare(strict_types=1);

// Important: Provide ngs\NGS() stub first
namespace ngs {
    if (!function_exists('ngs\\NGS')) {
        function NGS() {
            static $instance = null;
            if ($instance === null) {
                $instance = new class {
                    public array $data = [];

                    public function defined(string $key): bool { return array_key_exists($key, $this->data); }
                    public function getDefinedValue(string $key) { return $this->data[$key] ?? null; }
                    public function set(string $key, $value): self { $this->data[$key] = $value; return $this; }

                    private $argsInstance = null;
                    public function args() {
                        if ($this->argsInstance === null) {
                            $this->argsInstance = new class {
                                private array $args = [];
                                public function args(): array { return $this->args; }
                                public function setArgs(array $args): bool { $this->args = $args; return true; }
                                public function getArgs(): array { return $this->args; }
                            };
                        }
                        return $this->argsInstance;
                    }

                    public function createDefinedInstance(string $key, string $class) {
                        switch ($key) {
                            case 'REQUEST_CONTEXT':
                                return new class {
                                    public function getRequestUri(): string { return '/test'; }
                                    public function isAjaxRequest(): bool { return false; }
                                    public array $redirects = [];
                                    public function redirect(string $url): void { $this->redirects[] = $url; }
                                };
                            case 'TEMPLATE_ENGINE':
                                return new class {
                                    public array $log = [];
                                    public int $httpCode = 200;
                                    public array $json = [];
                                    public function setType($type): void { $this->log[] = [__FUNCTION__, $type]; }
                                    public function setTemplate($template): void { $this->log[] = [__FUNCTION__, $template]; }
                                    public function setPermalink($permalink): void { $this->log[] = [__FUNCTION__, $permalink]; }
                                    public function display($json = false): void { $this->log[] = [__FUNCTION__, $json]; }
                                    public function setHttpStatusCode($code): void { $this->httpCode = (int)$code; $this->log[] = [__FUNCTION__, $code]; }
                                    public function assignJson($key, $value): void { $this->json[$key] = $value; $this->log[] = [__FUNCTION__, $key, $value]; }
                                    public function assignJsonParams($params): void { $this->json['params'] = $params; $this->log[] = [__FUNCTION__, $params]; }
                                };
                            case 'LOAD_MAPPER':
                                return new class {
                                    public function getNgsPermalink(): string { return '/test-permalink'; }
                                };
                            case 'SESSION_MANAGER':
                                return new class {
                                    public function validateRequest($request): bool { return true; }
                                };
                            case 'ROUTES_ENGINE':
                                return new class { };
                            default:
                                return new $class();
                        }
                    }
                };

                // defaults used by Dispatcher conditions
                $instance->set('ENVIRONMENT', 'development');
                $instance->set('display_json', false);
                $instance->set('PUBLIC_DIR', 'htdocs');
            }
            return $instance;
        }
    }
}

namespace Tests {

use ngs\Dispatcher;
use ngs\event\EventManagerInterface;
use PHPUnit\Framework\TestCase;

// Create global aliases for mock loads so Dispatcher->instantiateLoad('MockLoad') works
\class_alias(\Tests\Helper\MockLoad::class, 'MockLoad');
\class_alias(\Tests\Helper\MockValidateLoad::class, 'MockValidateLoad');

class DispatcherTest extends TestCase
{
    private function makeDispatcherWithEventMock(?EventManagerInterface &$eventMock = null): Dispatcher
    {
        $eventMock = new class implements EventManagerInterface {
            public bool $loadSubscribersCalled = false;
            public bool $subscribeToEventsCalled = false;
            public bool $dispatchCalled = false;
            public array $visibleEvents = ['e' => ['name' => 'BeforeResultDisplay', 'bulk_is_available' => true, 'params' => []]];
            public function dispatch(\ngs\event\structure\AbstractEventStructure $event): void { $this->dispatchCalled = true; }
            public function subscribeToEvent(string $eventName, $subscriber, string $method): void {}
            public function loadSubscribers(bool $loadAll = false): array { $this->loadSubscribersCalled = true; return []; }
            public function subscribeToEvents(array $subscribers): void { $this->subscribeToEventsCalled = true; }
            public function getVisibleEvents(): array { return $this->visibleEvents; }
        };
        return new Dispatcher($eventMock);
    }

    public function testGetVisibleEvents(): void
    {
        $dispatcher = $this->makeDispatcherWithEventMock($em);
        $events = $dispatcher->getVisibleEvents();
        $this->assertSame($em->visibleEvents, $events);
    }

    public function testLoadPageHappyPathDispatchesEventAndDisplaysTemplate(): void
    {
        $dispatcher = $this->makeDispatcherWithEventMock($em);

        // Call loadPage with our alias 'MockLoad'
        $dispatcher->loadPage('MockLoad');

        // Assert event dispatched before result display
        $this->assertTrue($em->dispatchCalled, 'BeforeResultDisplay event should be dispatched');

        // Inspect our template engine log from ngs\NGS()->createDefinedInstance('TEMPLATE_ENGINE')
        $templater = \ngs\NGS()->createDefinedInstance('TEMPLATE_ENGINE', \stdClass::class);
        $log = $templater->log;

        // Ensure type and template were set, permalink set, and display was called
        $this->assertContains(['setType', 'html'], $log);
        $this->assertContains(['setTemplate', 'test_template'], $log);
        $this->assertContains(['setPermalink', '/test-permalink'], $log);
        $this->assertContains(['display', false], $log);
    }

    public function testValidateSetsJsonTypeAssignsParamsAndDisplays(): void
    {
        $dispatcher = $this->makeDispatcherWithEventMock($em);
        $dispatcher->validate('MockValidateLoad');

        $templater = \ngs\NGS()->createDefinedInstance('TEMPLATE_ENGINE', \stdClass::class);
        $log = $templater->log;

        // Should set type json and display
        $this->assertContains(['setType', 'json'], $log);
        $this->assertContains(['display', false], $log);

        // Should have assigned params coming from the load
        $this->assertArrayHasKey('params', $templater->json);
        $this->assertSame(['validated' => true], $templater->json['params']);
    }
}
}

namespace Tests\Helper {
// Helper classes that emulate Load behavior expected by Dispatcher
class MockLoad
{
    public array $calls = [];
    public function initialize(): void { $this->calls[] = __FUNCTION__; }
    public function service(): void { $this->calls[] = __FUNCTION__; }
    public function getNgsLoadType(): string { return 'html'; }
    public function getTemplate(): string { return 'test_template'; }
    public function afterRequest(): void { $this->calls[] = __FUNCTION__; }
    public function onNoAccess(): void { $this->calls[] = __FUNCTION__; }
}

class MockValidateLoad
{
    public array $calls = [];
    public function initialize(): void { $this->calls[] = __FUNCTION__; }
    public function validate(): void { $this->calls[] = __FUNCTION__; }
    public function getParams(): array { return ['validated' => true]; }
    public function afterRequest(): void { $this->calls[] = __FUNCTION__; }
    public function onNoAccess(): void { $this->calls[] = __FUNCTION__; }
}
}
