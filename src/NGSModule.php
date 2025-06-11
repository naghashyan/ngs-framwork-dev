<?php

declare(strict_types=1);

namespace ngs;

use Composer\Factory;
use ngs\routes\NgsModuleResolver;
use ngs\exceptions\NgsException;
use ngs\util\NgsEnvironmentContext;

class NgsModule
{
    /**
     * Module type constants
     */
    public const MODULE_TYPE_SUBDOMAIN = 'subdomain';
    public const MODULE_TYPE_DOMAIN = 'domain';
    public const MODULE_TYPE_PATH = 'path';

    /**
     * Array of all module types
     */
    public const MODULE_TYPES = [
        self::MODULE_TYPE_SUBDOMAIN,
        self::MODULE_TYPE_DOMAIN,
        self::MODULE_TYPE_PATH
    ];

    protected array $constants = [];
    protected string $moduleDir;
    protected bool $isComposerPackage = false;

    /**
     * Module type (domain, subdomain, path)
     */
    protected string $type = self::MODULE_TYPE_DOMAIN;

    /**
     * Cache for instances created by createDefinedInstance.
     *
     * @var array<string, object>
     */
    protected array $instanceCache = [];

    /**
     * Constructor for NgsModule.
     *
     * @param string|null $moduleDir The path to the module
     * @param string $type Module type (domain, subdomain, path)
     * @param array $configReplacements Array of replacements for %placeholder% values in constants
     * @param array $overrideConstants Constants to override from constants.json
     * @param array $parentConstants Parent constants to be used as base
     * @throws NgsException If modulePath is null and module name cannot be determined
     */
    public function __construct(?string $moduleDir = null, string $type = self::MODULE_TYPE_DOMAIN, array $configReplacements = [], array $overrideConstants = [], array $parentConstants = [])
    {
        if ($moduleDir !== null) {
            $this->moduleDir = $moduleDir;
            // Determine if this is a Composer package by checking if it's in the vendor directory
            $this->isComposerPackage = str_contains($moduleDir, '/vendor/');
        }

        $this->type = $type;

        $this->loadConstants($configReplacements, $overrideConstants, $parentConstants);
    }

    /**
     * Loads constants from the constants file and applies overrides.
     * 
     * @param array $overrideConstants Constants to override from constants.json
     * @param array $parentConstants Parent constants to be used as base
     * @param array $configReplacements Array of replacements for %placeholder% values in constants
     * @throws NgsException If constants.json is missing or required constants are missing
     */
    protected function loadConstants(array $configReplacements = [], array $overrideConstants = [], array $parentConstants = []): void
    {
        // First set the parent constants as the base
        $this->constants = $parentConstants;

        $constantsFile = $this->getConstantsFile();
        $environmentContext = NgsEnvironmentContext::getInstance();
        //debug_print_backtrace();

        if (!file_exists($constantsFile)) {
            throw new NgsException("Constants file not found: {$constantsFile}", 1);
        }

        $constants = json_decode(file_get_contents($constantsFile), true);

        if (!is_array($constants)) {
            throw new NgsException("Invalid constants file format: {$constantsFile}", 1);
        }

        // Process constants from file and add to parent constants
        $this->processConstants($constants, $environmentContext, $configReplacements, $this->constants);

        // Apply override constants
        $this->processConstants($overrideConstants, $environmentContext, $configReplacements, $this->constants);

        // Validate required constants
        if (!isset($this->constants['NAME'])) {
            throw new NgsException("Required constant 'name' is missing in constants.json", 1);
        }

        if (!isset($this->constants['VERSION'])) {
            throw new NgsException("Required constant 'version' is missing in constants.json", 1);
        }
    }

    /**
     * Process a constant value by applying environment-specific values and config replacements.
     *
     * @param mixed $value The value to process
     * @param NgsEnvironmentContext $environmentContext The environment context
     * @param array $configReplacements Array of replacements for %placeholder% values
     * @return mixed The processed value
     */
    protected function processConstantValue(mixed $value, NgsEnvironmentContext $environmentContext, array $configReplacements): mixed
    {
        // Check if there's an environment-specific value for this constant
        $processedValue = $environmentContext->getEnvironmentSpecificValue($value);

        // Apply replacements for %placeholder% values if the value is a string
        if (is_string($processedValue)) {
            $processedValue = $this->applyConfigReplacements($processedValue, $configReplacements);
        }

        return $processedValue;
    }

    /**
     * Process constants from an array and add them to the target array.
     *
     * @param array $constants The constants to process
     * @param NgsEnvironmentContext $environmentContext The environment context
     * @param array $configReplacements Array of replacements for %placeholder% values
     * @param array $targetArray Reference to the array where processed constants will be stored
     */
    protected function processConstants(array $constants, NgsEnvironmentContext $environmentContext, array $configReplacements, array &$targetArray): void
    {
        foreach ($constants as $section => $sectionValues) {
            if (is_array($sectionValues)) {
                // Process each section (constants, directories, classes)
                foreach ($sectionValues as $constName => $constValue) {
                    $processedValue = $this->processConstantValue($constValue, $environmentContext, $configReplacements);
                    $targetArray[$constName] = $processedValue;
                }
            } else {
                // Handle top-level constants (if any)
                $processedValue = $this->processConstantValue($sectionValues, $environmentContext, $configReplacements);
                $targetArray[$section] = $processedValue;
            }
        }
    }



    /**
     * Get the constants file path.
     *
     * @return string The path to the constants file
     */
    private function getConstantsFile(): string
    {
        $configDir = $this->getConfigDir();
        $environmentContext = NgsEnvironmentContext::getInstance();

        return $environmentContext->getConstantsFilePath($configDir);
    }


    /**
     * Gets the configuration directory.
     *
     * @return string The configuration directory path
     */
    public function getConfigDir(): string
    {
        return $this->moduleDir . '/conf';
    }

    /**
     * Gets the name of the module.
     *
     * @return string The module name
     */
    public function getName(): string
    {
        return $this->constants['NAME'];
    }

    /**
     * Gets the version of the module from constants.
     *
     * @return string The module version
     * @throws NgsException If version is not defined in constants
     */
    public function getVersion(): string
    {
        if (!isset($this->constants['version'])) {
            throw new NgsException("Module version is not defined in constants", 1);
        }

        return $this->constants['version'];
    }

    /**
     * Gets the value of a defined constant.
     *
     * @param string $key The key to get the value for
     * @param string|null $module The module to get the value from (unused parameter)
     * @return mixed The value or null if not found
     */
    public function getDefinedValue(string $key, ?string $module = null): mixed
    {
        return $this->constants[$key] ?? null;
    }

    /**
     * Alias for getDefinedValue.
     *
     * @param string $key The key to get the value for
     * @param string|null $module The module to get the value from (unused parameter)
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
     */
    public function define(string $key, mixed $value): void
    {
        $this->constants[$key] = $value;
    }

    /**
     * Checks if a key is defined.
     *
     * @param string $key The key to check
     * @return bool True if the key is defined, false otherwise
     */
    public function defined(string $key): bool
    {
        return isset($this->constants[$key]);
    }

    /**
     * Gets a constant value by name.
     *
     * @param string $constantName The name of the constant
     * @return mixed The constant value or null if not found
     */
    public function getConstant(string $constantName): mixed
    {
        return $this->constants[$constantName] ?? null;
    }

    /**
     * Creates or retrieves an instance for the given configuration constant,
     * and validates it against the expected class.
     *
     * @template T of object
     * @param string $constantName Name of the configuration constant
     * @param string $expectedClass Fully qualified class name expected
     * @param bool $forceNew Whether to force creation of a new instance
     * @return object The instantiated and validated service
     * @throws \Exception If the constant is missing, class cannot be instantiated,
     *                    or the instance is not of the expected type
     */
    public function createDefinedInstance(string $constantName, string $expectedClass, bool $forceNew = false): object
    {
        // Look up the class name from constants
        $className = $this->getDefinedValue($constantName);

        if (!$className || !class_exists($className)) {
            throw new \Exception(
                sprintf('Class "%s" for constant "%s" not found.', $className ?? 'null', $constantName)
            );
        }

        // Check if we have a cached instance and forceNew is false
        $cacheKey = $constantName . '_' . $expectedClass;
        if (!$forceNew && isset($this->instanceCache[$cacheKey])) {
            return $this->instanceCache[$cacheKey];
        }

        // Instantiate and validate type
        $instance = new $className();

        if (!$instance instanceof $expectedClass) {
            throw new \Exception(
                sprintf(
                    'Instance of "%s" does not implement expected "%s" for constant "%s".',
                    $className,
                    $expectedClass,
                    $constantName
                )
            );
        }

        // Cache the instance for future use
        $this->instanceCache[$cacheKey] = $instance;

        return $instance;
    }

    /**
     * Extracts the module name from a namespace.
     *
     * @param string $namespace The namespace to extract from
     * @return string The extracted module name or empty string if namespace is empty
     */
    public function getModuleByNS(string $namespace): string
    {
        if (empty($namespace)) {
            return '';
        }

        $parts = explode('\\', $namespace);
        return end($parts);
    }

    /**
     * Applies configuration replacements to a string value.
     * Replaces %placeholder% with the corresponding value from the replacements array.
     *
     * @param string $value The string value to process
     * @param array $replacements Array of replacements where keys are placeholder names without % symbols
     * @return string The processed string with replacements applied
     */
    protected function applyConfigReplacements(string $value, array $replacements): string
    {
        if (empty($replacements) || !str_contains($value, '%')) {
            return $value;
        }

        // Find all %placeholder% patterns in the string
        preg_match_all('/%([^%]+)%/', $value, $matches);

        if (empty($matches[1])) {
            return $value;
        }

        // Apply replacements
        foreach ($matches[1] as $placeholder) {
            if (isset($replacements[$placeholder])) {
                $value = str_replace("%{$placeholder}%", (string)$replacements[$placeholder], $value);
            }
        }

        return $value;
    }

    /**
     * Gets the path to the module directory.
     *
     * @return string The module directory path
     */
    public function getDir(): string
    {
        return $this->moduleDir;
    }

    /**
     * Checks if the module is a Composer package.
     *
     * @return bool True if the module is a Composer package, false otherwise
     */
    public function isComposerPackage(): bool
    {
        return $this->isComposerPackage;
    }

    /**
     * Gets the module type.
     *
     * @return string The module type (domain, subdomain, path)
     */
    public function getType(): string
    {
        return $this->type;
    }
}
