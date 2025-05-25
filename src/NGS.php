<?php


/**
 * Base NGS class
 * for static function that will
 * visible from any classes
 *
 * @author Levon Naghashyan <levon@naghashyan.com>
 * @author Zaven Naghashyan <zaven@naghashyan.com>
 * @site https://naghashyan.com
 * @year 2014-2025
 * @package ngs.framework
 * @version 4.5.0
 *
 *
 * This file is part of the NGS package.
 *
 * @copyright Naghashyan Solutions LLC
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 *
 */

use ngs\exceptions\DebugException;

require_once 'NGSDeprecated.php';

class NGS extends NGSDeprecated
{
    private static array $instance = [];

    /**
     * Storage for created instances keyed by constant name.
     *
     * @var object[]
     */
    private array $instances = [];

    private array $loadedModules = [];

    /**
     * Returns an singleton instance of this class
     *
     * @return object NGS
     */
    public static function getInstance(string $module = ''): NGS
    {
        if ($module === '') {
            $module = '_default_';
        }
        if (!isset(self::$instance[$module])) {
            self::$instance[$module] = new self();
        }
        return self::$instance[$module];
    }

    public function initialize()
    {
        $moduleConstatPath = realpath(NGS()->getConfigDir() . '/constants.php');
        if ($moduleConstatPath) {
            require_once $moduleConstatPath;
        }
        $envConstantFile = realpath(NGS()->getConfigDir() . '/constants_' . $this->getShortEnvironment() . '.php');
        if ($envConstantFile) {
            require_once $envConstantFile;
        }

        $moduleRoutesEngine = NGS()->getModulesRoutesEngine();
        $parentModule = $moduleRoutesEngine->getParentModule();

        if ($parentModule && isset($parentModule['ns'])) {
            $_prefix = $parentModule['ns'];
            $envConstantFile = realpath(NGS()->getConfigDir($_prefix) . '/constants_' . $this->getShortEnvironment() . '.php');
            if ($envConstantFile) {
                require_once $envConstantFile;
            }
        }

        $this->getModulesRoutesEngine(true)->initialize();
    }


    /*
     |--------------------------------------------------------------------------
     | DEFINNING NGS MODULES
     |--------------------------------------------------------------------------
     */
    public function getDefinedValue(string $key, string $module = null): mixed
    {
        if (isset($this->define[$key])) {
            return $this->define[$key];
        }
        return null;
    }

    public function get(string $key, string $module = null): mixed
    {
        return $this->getDefinedValue($key);
    }

    public function define(string $key, mixed $value): void
    {
        $this->define[$key] = $value;
    }

    public function defined(string $key): bool
    {
        if (isset($this->define[$key])) {
            return true;
        }
        return false;
    }


    /**
     * global config
     *
     * @params $prefix
     *
     * @param null $prefix
     * @return array config
     * @throws DebugException
     */
    public function getConfig(?string $prefix = null): mixed
    {
        if (NGS()->getModulesRoutesEngine()->getModuleNS() === null) {
            return $this->getNgsConfig();
        }
        if ($prefix == null) {
            $moduleRoutesEngine = NGS()->getModulesRoutesEngine();
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

        return $this->config[$_prefix] = json_decode(file_get_contents($configPerEnvironment));
    }


    //----------------------------------------------------------------

    public function getModuleDirByNS(string $ns = ''): string
    {
        return NGS()->getModulesRoutesEngine()->getRootDir($ns);
    }


    //----------------------------------------------------------------


    /**
     * Creates or retrieves an instance for the given configuration constant,
     * and validates it against the expected class.
     *
     * @template T of object
     * @param string $constantName Name of the configuration constant.
     * @param class-string<T> $expectedClass Fully qualified class name expected.
     * @param bool $forceNew Whether to force creation of a new instance.
     * @return T                   The instantiated and validated service.
     * @throws DebugException      If the constant is missing, class cannot be instantiated,
     *                              or the instance is not of the expected type.
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
            /** @var T $instance */
            return $instance;
        }

        // Look up the class name from constants
        $className = $this->getDefinedValue($constantName);
        if (!class_exists($className)) {
            throw new DebugException(
                sprintf('Class "%s" for constant "%s" not found.', $className, $constantName)
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
        /** @var T $instance */
        return $instance;
    }


    //----------------------------------------------------------------

    /**
     * In case if no argument is provided, we should load the default config (config in the project's config directory)
     *
     * @param string|null $moduleName
     * @return NGSModule|null
     */
    public function getModule(?string $moduleName = null): ?NGSModule
    {
        if (!isset($this->loadedModules[$moduleName])) {
            $this->loadedModules[$moduleName] = $this->loadModule($moduleName);
        }

        return $this->loadedModules[$moduleName];
    }

    private function loadModule(?string $moduleName = null, $environment = ""): string
    {
        $namespace = preg_replace('/-/', '/', $moduleName, 1);
        $module = new $namespace . $moduleName($environment);
        return $module;
    }

}

/**
 * return NGS instance
 *
 * @return NGS NGS
 */
function NGS(string $module = '')
{
    return NGS::getInstance($module);
}

require_once('system/NgsDefaultConstants.php');
NGS()->initialize();
