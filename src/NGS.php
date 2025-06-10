<?php

declare(strict_types=1);

namespace ngs {

    use ngs\exceptions\DebugException;
    use ngs\exceptions\NgsException;
    use ngs\event\EventManager;
    use ngs\event\structure\AbstractEventStructure;
    use ngs\event\subscriber\AbstractEventSubscriber;
    use ngs\routes\NgsModuleResolver;
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

            // Check if we should bypass the document root check
            if (getenv('SKIP_DOCUMENT_ROOT_CHECK') === 'true') {
                // Set NGS_ROOT to the current directory
                $this->define('NGS_ROOT', $currentDir);
                return;
            }

            if (!str_contains($currentDir, DIRECTORY_SEPARATOR . 'htdocs') && 
                !str_contains($currentDir, '/' . 'htdocs')) {
                throw new \Exception('Please change document root to htdocs');
            }

            $separator = str_contains($currentDir, '/htdocs') ? '/' : '\\';
            $ngsRoot = substr($currentDir, 0, strrpos($currentDir, $separator . 'htdocs'));

            $this->define('NGS_ROOT', $ngsRoot);

            $this->moduleDir = $ngsRoot;
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
         * @param string $moduleName Name of the module directory or Composer package
         * @return NGSModule The module instance
         * @throws NgsException If the module cannot be found or loaded
         */
        public function getModule(string $moduleName): NGSModule
        {
            // Check if module is already loaded
            if (isset($this->loadedModules[$moduleName])) {
                return $this->loadedModules[$moduleName];
            }

            // Load the module and cache the instance
            $module = $this->loadModule($moduleName);
            $this->loadedModules[$moduleName] = $module;

            return $module;
        }

        /**
         * Loads a module.
         *
         * @param string $moduleName Name of the module directory or Composer package
         * @return NGSModule|null The module instance or null if not found
         * @throws NgsException If the module cannot be found or loaded
         */
        private function loadModule(string $moduleName): ?NGSModule
        {
            // Load modules configuration
            $modulesConfigFile = $this->getDefinedValue('NGS_ROOT') . '/conf/modules.json';
            if (!file_exists($modulesConfigFile)) {
                throw new NgsException("Modules configuration file not found: {$modulesConfigFile}", 1);
            }

            $modulesConfig = json_decode(file_get_contents($modulesConfigFile), true);
            if (!is_array($modulesConfig)) {
                throw new NgsException("Invalid modules configuration format", 1);
            }

            // Default configuration section
            $defaultConfig = $modulesConfig['default'] ?? [];

            // Determine if this is a Composer package or local module
            $isComposerPackage = str_contains($moduleName, '/');
            $modulePath = null;

            if ($isComposerPackage) {
                // Handle Composer package
                $vendorDir = $this->getDefinedValue('NGS_ROOT') . '/vendor';
                $packagePath = $vendorDir . '/' . $moduleName;

                if (!is_dir($packagePath)) {
                    throw new NgsException("Composer package not found: {$moduleName}", 1);
                }

                $modulePath = $packagePath;
            } else {
                // Handle local project module
                $pathConfig = $defaultConfig['path'] ?? [];

                if (isset($pathConfig[$moduleName]['dir'])) {
                    // Module is explicitly defined in configuration
                    $moduleDir = $pathConfig[$moduleName]['dir'];
                    $modulePath = $this->getDefinedValue('NGS_ROOT') . '/modules/' . $moduleDir;
                } else {
                    // Use default module directory
                    $defaultDir = $defaultConfig['default']['dir'] ?? $moduleName;
                    $modulePath = $this->getDefinedValue('NGS_ROOT') . '/modules/' . $defaultDir;
                }

                if (!is_dir($modulePath)) {
                    throw new NgsException("Module directory not found: {$modulePath}", 1);
                }
            }

            // Create the module instance
            return new NGSModule($modulePath);
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
        public function getConfigFile(): string
        {
            $configDir = $this->getConfigDir();
            $environmentContext = NgsEnvironmentContext::getInstance();

            return $environmentContext->getConfigFilePath($configDir);
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

            foreach ($subscribers as $subscriberElement) {
                $subscriber = null;
                if (is_array($subscriberElement) && isset($subscriberElement['class'])) {
                    $subscriber = $subscriberElement["class"];
                } else {
                    throw new \Exception('Invalid subscriber format: ' . json_encode($subscriberElement));
                }

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
