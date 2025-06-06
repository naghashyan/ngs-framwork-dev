<?php

namespace ngs\tests {

// Include the required files directly since autoloading might not be working
    require_once __DIR__ . '/../src/Dispatcher.php';
    require_once __DIR__ . '/../src/event/EventManagerInterface.php';
    require_once __DIR__ . '/../src/event/EventManager.php';
    require_once __DIR__ . '/../src/event/structure/AbstractEventStructure.php';
    require_once __DIR__ . '/../src/event/structure/EventDispatchedStructure.php';
    require_once __DIR__ . '/../src/event/structure/BeforeResultDisplayEventStructure.php';
    require_once __DIR__ . '/../src/event/subscriber/AbstractEventSubscriber.php';

    use ngs\Dispatcher;
    use ngs\event\EventManagerInterface;
    use ngs\event\structure\AbstractEventStructure;

    /**
     * Simple test script to verify Dispatcher functionality with EventManagerInterface
     */
// Create a mock EventManagerInterface implementation for testing
    class MockEventManager implements EventManagerInterface
    {
        public $loadSubscribersCalled = false;
        public $subscribeToEventsCalled = false;
        public $dispatchCalled = false;
        public $subscribers = [];
        public $visibleEvents = ['test' => ['name' => 'Test Event', 'bulk_is_available' => true, 'params' => []]];

        public function dispatch(AbstractEventStructure $event): void
        {
            $this->dispatchCalled = true;
            echo "MockEventManager: dispatch called\n";
        }

        public function subscribeToEvent(string $eventName, $subscriber, string $method): void
        {
            echo "MockEventManager: subscribeToEvent called with $eventName\n";
        }

        public function loadSubscribers(bool $loadAll = false): array
        {
            $this->loadSubscribersCalled = true;
            echo "MockEventManager: loadSubscribers called with loadAll=" . ($loadAll ? "true" : "false") . "\n";
            return $this->subscribers;
        }

        public function subscribeToEvents(array $subscribers): void
        {
            $this->subscribeToEventsCalled = true;
            echo "MockEventManager: subscribeToEvents called with " . count($subscribers) . " subscribers\n";
        }

        public function getVisibleEvents(): array
        {
            echo "MockEventManager: getVisibleEvents called\n";
            return $this->visibleEvents;
        }
    }
}

namespace {
// Function to simulate NGS() global function
    if (!function_exists('NGS')) {
        function NGS()
        {
            static $instance = null;
            if ($instance === null) {
                $instance = new class {
                    private $data = [];
                    private $argsInstance = null;

                    public function get($key)
                    {
                        return isset($this->data[$key]) ? $this->data[$key] : null;
                    }

                    public function set($key, $value)
                    {
                        $this->data[$key] = $value;
                        return $this;
                    }

                    public function args()
                    {
                        if ($this->argsInstance === null) {
                            $this->argsInstance = new class {
                                private $args = [];

                                public function args()
                                {
                                    return $this->args;
                                }

                                public function setArgs($args)
                                {
                                    $this->args = $args;
                                    return true;
                                }

                                public function getArgs()
                                {
                                    return $this->args;
                                }
                            };
                        }
                        return $this->argsInstance;
                    }

                    public function createDefinedInstance($key, $class)
                    {
                        if ($key === 'ROUTES_ENGINE') {
                            return new class {
                                public function getDynamicLoad($uri)
                                {
                                    return ['matched' => true, 'type' => 'load', 'action' => 'MockAction'];
                                }

                                public function getContentLoad()
                                {
                                    return 'test_content_load';
                                }

                                public function getNotFoundLoad()
                                {
                                    return null;
                                }
                            };
                        }
                        if ($key === 'REQUEST_CONTEXT') {
                            return new class {
                                public function getRequestUri()
                                {
                                    return '/test';
                                }

                                public function isAjaxRequest()
                                {
                                    return false;
                                }

                                public function redirect($url)
                                {
                                    echo "Redirecting to $url\n";
                                }
                            };
                        }
                        if ($key === 'TEMPLATE_ENGINE') {
                            return new class {
                                public function setType($type)
                                {
                                }

                                public function setTemplate($template)
                                {
                                }

                                public function setPermalink($permalink)
                                {
                                }

                                public function display($json = false)
                                {
                                    echo "Template displayed\n";
                                }

                                public function setHttpStatusCode($code)
                                {
                                }

                                public function assignJson($key, $value)
                                {
                                }

                                public function assignJsonParams($params)
                                {
                                }
                            };
                        }
                        if ($key === 'LOAD_MAPPER') {
                            return new class {
                                public function getNgsPermalink()
                                {
                                    return '/test';
                                }
                            };
                        }
                        if ($key === 'SESSION_MANAGER') {
                            return new class {
                                public function validateRequest($request)
                                {
                                    return true;
                                }
                            };
                        }
                        return new $class();
                    }

                    public function getModuleDirByNS($ns)
                    {
                        return __DIR__ . '/mock_module';
                    }
                };

                // Set up mock data
                $instance->set('CONF_DIR', 'conf');
                $instance->set('NGS_CMS_NS', 'ngs\\cms');
                $instance->set('NGS_ROOT', __DIR__);
                $instance->set('NGS_MODULS_ROUTS', 'modules.json');
                $instance->set('ENVIRONMENT', 'development');
                $instance->set('SEND_HTTP_PUSH', false);
            }
            return $instance;
        }
    }

// Create a mock action class
    class MockAction
    {
        private $loadName = '';

        public function initialize()
        {
            echo "MockAction: initialize called\n";
        }

        public function service()
        {
            echo "MockAction: service called\n";
        }

        public function getParams()
        {
            return ['result' => 'success'];
        }

        public function afterRequest()
        {
            echo "MockAction: afterRequest called\n";
        }

        public function onNoAccess()
        {
            echo "MockAction: onNoAccess called\n";
        }

        public function setLoadName($loadName)
        {
            $this->loadName = $loadName;
            echo "MockAction: setLoadName called with " . ($loadName ? $loadName : "empty") . "\n";
        }

        public function getNgsLoadType()
        {
            return 'html';
        }

        public function getTemplate()
        {
            return 'test_template';
        }
    }

// Test Dispatcher with mock EventManager
    echo "Testing Dispatcher with mock EventManager:\n";

// Create a mock EventManager
    $mockEventManager = new \ngs\tests\MockEventManager();

// Create a Dispatcher with the mock EventManager
    $dispatcher = new \ngs\Dispatcher($mockEventManager);

// Test getVisibleEvents
    echo "\nTesting getVisibleEvents:\n";
    $visibleEvents = $dispatcher->getVisibleEvents();
    echo "getVisibleEvents result: " . (count($visibleEvents) > 0 ? "PASS" : "FAIL") . "\n";

// Test dispatch
    echo "\nTesting dispatch:\n";
    try {
        $dispatcher->dispatch();
        echo "dispatch: PASS\n";
    } catch (\Exception $e) {
        echo "dispatch: FAIL - " . $e->getMessage() . "\n";
    }

// Verify that the EventManager methods were called
    echo "\nVerifying EventManager method calls:\n";
    echo "loadSubscribers called: " . ($mockEventManager->loadSubscribersCalled ? "PASS" : "FAIL") . "\n";
    echo "subscribeToEvents called: " . ($mockEventManager->subscribeToEventsCalled ? "PASS" : "FAIL") . "\n";

    echo "\nTest completed.\n";

}
