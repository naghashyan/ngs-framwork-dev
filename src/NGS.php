<?php

declare(strict_types=1);

namespace ngs {

    use ngs\exceptions\DebugException;
    use ngs\routes\NgsModuleRoutes as NgsModuleRoutes;

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
         * Stores singleton instances of this class.
         *
         * @var array<string, self>
         */
        private static array $instance = [];

        /**
         * Storage for created instances keyed by constant name.
         *
         * @var array<string, object>
         */
        private array $instances = [];

        /**
         * Storage for loaded modules.
         *
         * @var array<string|null, object|string|null>
         */
        private array $loadedModules = [];

        /**
         * Storage for defined values.
         *
         * @var array<string, mixed>
         */
        protected array $define = [];

        /**
         * Storage for configuration values.
         *
         * @var array<string, mixed>
         */
        protected array $config = [];

        /**
         * Returns a singleton instance of this class.
         *
         * @param string $module The module name to get instance for
         * @return self The singleton instance
         */
        public static function getInstance(string $module = ''): self
        {
            if ($module === '') {
                $module = '_default_';
            }

            if (!isset(self::$instance[$module])) {
                self::$instance[$module] = new self();
            }

            return self::$instance[$module];
        }

        /**
         * Initializes the NGS framework by loading configuration files.
         *
         * @return void
         */
        public function initialize(): void
        {
            $moduleConstantPath = realpath($this->getConfigDir() . '/constants.php');
            if ($moduleConstantPath) {
                require_once $moduleConstantPath;
            }

            $envConstantFile = realpath($this->getConfigDir() . '/constants_' . $this->getShortEnvironment() . '.php');
            if ($envConstantFile) {
                require_once $envConstantFile;
            }

            $moduleRoutesEngine = $this->getModulesRoutesEngine();
            $parentModule = $moduleRoutesEngine->getParentModule();

            if ($parentModule && isset($parentModule['ns'])) {
                $_prefix = $parentModule['ns'];
                $envConstantFile = realpath($this->getConfigDir($_prefix) . '/constants_' . $this->getShortEnvironment() . '.php');
                if ($envConstantFile) {
                    require_once $envConstantFile;
                }
            }

            $this->getModulesRoutesEngine(true)->initialize();
        }

        /**
         * Gets the value of a defined constant.
         *
         * @param string $key The key to get the value for
         * @param string|null $module The module to get the value from
         * @return mixed The value or null if not found
         */
        public function getDefinedValue(string $key, ?string $module = null): mixed
        {
            return $this->define[$key] ?? null;
        }

        /**
         * Alias for getDefinedValue.
         *
         * @param string $key The key to get the value for
         * @param string|null $module The module to get the value from
         * @return mixed The value or null if not found
         */
        public function get(string $key, ?string $module = null): mixed
        {
            return $this->getDefinedValue($key);
        }

        /**
         * Defines a value with the given key.
         *
         * @param string $key The key to define
         * @param mixed $value The value to set
         * @return void
         */
        public function define(string $key, mixed $value): void
        {
            $this->define[$key] = $value;
        }

        /**
         * Checks if a key is defined.
         *
         * @param string $key The key to check
         * @return bool True if the key is defined, false otherwise
         */
        public function defined(string $key): bool
        {
            return isset($this->define[$key]);
        }

        /**
         * Gets the configuration for the specified prefix.
         *
         * @param string|null $prefix The prefix to get the configuration for
         * @return mixed The configuration
         * @throws DebugException If there's an error loading the configuration
         */
        public function getConfig(?string $prefix = null): mixed
        {
            if ($this->getModulesRoutesEngine()->getModuleNS() === null) {
                return $this->getNgsConfig();
            }

            if ($prefix === null) {
                $moduleRoutesEngine = $this->getModulesRoutesEngine();
                $parentModule = $moduleRoutesEngine->getParentModule();

                if ($parentModule && isset($parentModule['ns'])) {
                    $_prefix = $parentModule['ns'];
                } else {
                    $_prefix = $moduleRoutesEngine->getModuleNS();
                }
            } else {
                $_prefix = $prefix;
            }

            if (isset($this->config[$_prefix])) {
                return $this->config[$_prefix];
            }

            $configPerEnvironment = $this->getConfigDir($_prefix) . '/config_' . $this->getShortEnvironment() . '.json';
            $configContent = file_get_contents($configPerEnvironment);

            if ($configContent === false) {
                throw new DebugException(sprintf('Could not read configuration file: %s', $configPerEnvironment));
            }

            return $this->config[$_prefix] = json_decode($configContent);
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
         * Creates or retrieves an instance for the given configuration constant,
         * and validates it against the expected class.
         *
         * @template T of object
         * @param string $constantName Name of the configuration constant
         * @param class-string<T> $expectedClass Fully qualified class name expected
         * @param bool $forceNew Whether to force creation of a new instance
         * @return T The instantiated and validated service
         * @throws DebugException If the constant is missing, class cannot be instantiated,
         *                        or the instance is not of the expected type
         */
        public function createDefinedInstance(string $constantName, string $expectedClass, bool $forceNew = false): object
        {
            // Reuse cached instance if available and not forced to recreate
            if (!$forceNew && isset($this->instances[$constantName])) {
                $instance = $this->instances[$constantName];

                if (!$instance instanceof $expectedClass) {
                    throw new DebugException(
                        sprintf(
                            'Cached instance for "%s" is not an instance of "%s".',
                            $constantName,
                            $expectedClass
                        )
                    );
                }

                return $instance;
            }

            // Look up the class name from constants
            $className = $this->getDefinedValue($constantName);

            if (!$className || !class_exists($className)) {
                throw new DebugException(
                    sprintf('Class "%s" for constant "%s" not found.', $className ?? 'null', $constantName)
                );
            }

            // Instantiate and validate type
            $instance = new $className();

            if (!$instance instanceof $expectedClass) {
                throw new DebugException(
                    sprintf(
                        'Instance of "%s" does not implement expected "%s" for constant "%s".',
                        $className,
                        $expectedClass,
                        $constantName
                    )
                );
            }

            // Cache and return
            $this->instances[$constantName] = $instance;

            return $instance;
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
         * @return object|string|null The loaded module
         */
        private function loadModule(?string $moduleName = null, string $environment = ""): object|string|null
        {
            if ($moduleName === null) {
                return null;
            }

            $namespace = preg_replace('/-/', '/', $moduleName, 1);

            if ($namespace === null) {
                return null;
            }

            // This line seems to have a syntax error in the original code
            // Fixing it to properly concatenate the namespace and module name
            $className = $namespace . '\\' . $moduleName . ($environment ? '\\' . $environment : '');

            if (!class_exists($className)) {
                return null;
            }

            return new $className();
        }

    }
} // End of namespace ngs

namespace {
    /**
     * Returns the NGS instance.
     *
     * @param string $module The module name
     * @return \ngs\NGS The NGS instance
     */
    function NGS(string $module = ''): \ngs\NGS
    {
        return \ngs\NGS::getInstance($module);
    }

    if (getenv('SKIP_NGS_INIT') !== 'true') {
        require_once('system/NgsDefaultConstants.php');
        NGS()->initialize();
    }
}
