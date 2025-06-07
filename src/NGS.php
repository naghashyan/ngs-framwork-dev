<?php

declare(strict_types=1);

namespace ngs {

    use ngs\exceptions\DebugException;
    use ngs\exceptions\NgsException;
    use ngs\event\EventManager;
    use ngs\event\structure\AbstractEventStructure;
    use ngs\event\subscriber\AbstractEventSubscriber;
    use ngs\routes\NgsModuleRoutes;
    use ngs\util\NgsEnvironmentContext;

    /**
     * Base NGS class for static functions that will be visible from any classes.
     *
     * This class provides core functionality for the NGS framework including
     * configuration management, module handling, and instance creation.
     *
     * @author Levon Naghashyan <levon@naghashyan.com>
     * @author Zaven Naghashyan <zaven@naghashyan.com>
     * @link https://naghashyan.com
     * @package ngs.framework
     * @version 4.5.0
     * @since 2014
     *
     * This file is part of the NGS package.
     *
     * @copyright Naghashyan Solutions LLC
     *
     * For the full copyright and license information, please view the LICENSE
     * file that was distributed with this source code.
     */
    class NGS extends NGSDeprecated
    {
        /**
         * Stores singleton instance of this class.
         *
         * @var NGS|null
         */
        private static ?NGS $instance = null;


        /**
         * Storage for loaded modules.
         *
         * @var array<string|null, object|null>
         */
        private array $loadedModules = [];

        /**
         * Storage for events.
         *
         * @var array<string, array{name: string, bulk_is_available: bool, params: array}>
         */
        private array $events = [];

        /**
         * Configuration storage.
         *
         * @var array<string, mixed>
         */
        protected array $config = [];

        /**
         * Constructor for NGS.
         *
         * @param string|null $moduleDir The path to the module
         * @param array $overrideConstants Constants to override from constants.json
         * @throws \Exception If document root is not set to htdocs
         * @throws NgsException If modulePath is null and module name cannot be determined
         */
        public function __construct()
        {
            // Document root validation and NGS root definition
            $this->validateAndSetDocumentRoot();

            // Load configuration
            $this->loadConfig();

            // Load framework constants
            $frameworkConstantsPath = __DIR__ . '/../conf';
            $environmentContext = NgsEnvironmentContext::getInstance();
            $frameworkConstantsFile = $environmentContext->getConstantsFilePath($frameworkConstantsPath);
            $frameworkConstants = [];

            if (file_exists($frameworkConstantsFile)) {
                $constants = json_decode(file_get_contents($frameworkConstantsFile), true);
                if (is_array($constants)) {
                    $this->processConstants($constants, $environmentContext, [], $frameworkConstants);
                }
            }

            $projectRoot = $this->getDefinedValue("NGS_ROOT");

            parent::__construct($projectRoot, $this->config, [], $frameworkConstants);

            // Initialize module routes
            //$this->getModulesRoutesEngine(true)->initialize();

            // Load event subscribers
            $this->loadEvents();
        }

        /**
         * Validates document root and sets NGS_ROOT constant.
         *
         * @throws \Exception If document root is not set to htdocs
         */
        private function validateAndSetDocumentRoot(): void
        {
            $currentDir = getcwd();

            if (!str_contains($currentDir, DIRECTORY_SEPARATOR . 'htdocs') && 
                !str_contains($currentDir, '/' . 'htdocs')) {
                throw new \Exception('Please change document root to htdocs');
            }

            $separator = str_contains($currentDir, '/htdocs') ? '/' : '\\';
            $ngsRoot = substr($currentDir, 0, strrpos($currentDir, $separator . 'htdocs'));

            $this->define('NGS_ROOT', $ngsRoot);
        }



        /**
         * Returns a singleton instance of this class.
         *
         * @param string $module The module name to get instance for
         * @return self The singleton instance
         */
        public static function getInstance(): self
        {
            if (!isset(self::$instance)) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * Gets the module directory by namespace.
         *
         * @param string $ns The namespace
         * @return string The directory path
         */
        public function getModuleDirByNS(string $ns = ''): string
        {
            return $this->getModulesRoutesEngine()->getRootDir($ns);
        }



        /**
         * Gets a module instance.
         *
         * @param string|null $moduleName The name of the module
         * @return object|null The module instance
         */
        public function getModule(?string $moduleName = null): ?object
        {
            if (!isset($this->loadedModules[$moduleName])) {
                $this->loadedModules[$moduleName] = $this->loadModule($moduleName);
            }

            return $this->loadedModules[$moduleName];
        }

        /**
         * Loads a module.
         *
         * @param string|null $moduleName The name of the module
         * @param string $environment The environment
         * @return object|null The loaded module
         */
        private function loadModule(?string $moduleName = null, string $environment = ""): ?object
        {
            if ($moduleName === null) {
                return null;
            }

            $namespace = preg_replace('/-/', '/', $moduleName, 1);

            if ($namespace === null) {
                return null;
            }

            $className = $namespace . '\\' . $moduleName . ($environment ? '\\' . $environment : '');

            if (!class_exists($className)) {
                return null;
            }

            return new $className();
        }

        /**
         * Loads configuration from the config file.
         */
        public function loadConfig(): void
        {
            $configFile = $this->getConfigFile();

            // Load config file if it exists
            if (file_exists($configFile)) {
                $configContent = file_get_contents($configFile);
                if ($configContent === false) {
                    return;
                }

                $configs = json_decode($configContent, true);

                if (is_array($configs)) {
                    foreach ($configs as $key => $config) {
                        $this->config[$key] = $config;
                    }
                }
            }
        }

        /**
         * Get the config file path.
         *
         * @param string $parentDir The parent directory
         * @return string The path to the config file
         */
        public function getConfigFile(string $parentDir = ''): string
        {
            $configDir = $this->getConfigDir($parentDir);
            $environmentContext = NgsEnvironmentContext::getInstance();

            return $environmentContext->getConfigFilePath($configDir);
        }

        /**
         * Gets the configuration directory.
         *
         * @param string $parentDir The parent directory
         * @return string The configuration directory path
         */
        public function getConfigDir(string $parentDir = ''): string
        {
            return empty($parentDir) ? __DIR__ . '/conf' : $parentDir . '/conf';
        }

        /**
         * Gets the configuration for the specified prefix.
         *
         * @param string|null $prefix The prefix to get the configuration for
         * @return mixed The configuration or null if not found
         */
        public function getConfig(?string $prefix = null): mixed
        {
            if ($prefix === null) {
                return $this->config;
            }

            return $this->config[$prefix] ?? null;
        }

        /**
         * Loads event subscribers from configuration.
         */
        protected function loadEvents(): void
        {
            $subscribersFile = $this->getConfigDir($this->moduleDir) . '/event_subscribers.json';
            $subscribers = [];

            if (file_exists($subscribersFile)) {
                $subscribersContent = file_get_contents($subscribersFile);
                if ($subscribersContent === false) {
                    return;
                }

                $subscribersList = json_decode($subscribersContent, true) ?? [];
                $subscribers = array_values($subscribersList);
            }

            $this->subscribeToEvents($subscribers);
        }

        /**
         * Subscribes to each subscriber's events.
         *
         * @param array $subscribers Array of subscriber class names
         * @throws \Exception If a subscriber is invalid
         * @throws \InvalidArgumentException If an event structure class is invalid
         */
        private function subscribeToEvents(array $subscribers): void
        {
            $eventManager = EventManager::getInstance();

            foreach ($subscribers as $subscriber) {
                if (!class_exists($subscriber)) {
                    throw new \Exception('Subscriber class not found: ' . $subscriber);
                }

                /** @var AbstractEventSubscriber $subscriberObject */
                $subscriberObject = new $subscriber();

                if (!$subscriberObject instanceof AbstractEventSubscriber) {
                    throw new \Exception('Invalid subscriber: ' . $subscriber);
                }

                $subscriptions = $subscriberObject->getSubscriptions();

                foreach ($subscriptions as $eventStructClass => $handlerName) {
                    /** @var AbstractEventStructure $eventStructExample */
                    if (!is_a($eventStructClass, AbstractEventStructure::class, true)) {
                        throw new \InvalidArgumentException('Invalid event structure class: ' . $eventStructClass);
                    }

                    // Use class-string type for static method call
                    $eventStructExample = call_user_func([$eventStructClass, 'getEmptyInstance']);
                    $availableParams = $eventStructExample->getAvailableVariables();

                    if ($eventStructExample->isVisible() && !isset($this->events[$eventStructExample->getEventId()])) {
                        $this->events[$eventStructExample->getEventId()] = [
                            'name' => $eventStructExample->getEventName(),
                            'bulk_is_available' => $eventStructExample->bulkIsAvailable(),
                            'params' => $availableParams
                        ];
                    }

                    $eventManager->subscribeToEvent($eventStructClass, $subscriberObject, $handlerName);
                }
            }
        }
    }
} // End of namespace ngs

namespace {
    /**
     * Returns the NGS instance.
     *
     * @return \ngs\NGS The NGS instance
     */
    function NGS(): \ngs\NGS
    {
        return \ngs\NGS::getInstance();
    }

    if (getenv('SKIP_NGS_INIT') !== 'true') {
        require_once('system/NgsDefaultConstants.php');
    }
}
