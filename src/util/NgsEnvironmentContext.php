<?php

namespace ngs\util;

/**
 * NgsEnvironmentContext - A utility class for handling environment-related functionality
 *
 * This class centralizes all environment-related operations that were previously
 * scattered across different classes in the framework.
 *
     * @author Naghashyan Solutions <info@naghashyan.com>
     * @package ngs.framework
     * @version 5.0.0
 *
 * This file is part of the NGS package.
 *
 * @copyright Naghashyan Solutions LLC
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class NgsEnvironmentContext
{
    /**
     * Environment name constants
     */
    public const ENVIRONMENT_DEVELOPMENT = 'development';
    public const ENVIRONMENT_STAGING = 'staging';
    public const ENVIRONMENT_PRODUCTION = 'production';
    
    /**
     * Short environment name constants
     */
    public const SHORT_ENVIRONMENT_DEV = 'dev';
    public const SHORT_ENVIRONMENT_STAGE = 'stage';
    public const SHORT_ENVIRONMENT_PROD = 'prod';

    /**
     * @var NgsEnvironmentContext|null Singleton instance
     */
    private static ?NgsEnvironmentContext $instance = null;

    /**
     * @var string Current environment
     */
    private string $environment;

    /**
     * Private constructor to enforce singleton pattern
     */
    private function __construct()
    {
        $this->initializeEnvironment();
    }

    /**
     * Get singleton instance
     *
     * @return NgsEnvironmentContext
     */
    public static function getInstance(): NgsEnvironmentContext
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize the environment from server settings
     */
    private function initializeEnvironment(): void
    {
        $environment = self::ENVIRONMENT_PRODUCTION; // Default environment
        
        if (isset($_SERVER['ENVIRONMENT'])) {
            if ($_SERVER['ENVIRONMENT'] == self::ENVIRONMENT_DEVELOPMENT || $_SERVER['ENVIRONMENT'] == self::SHORT_ENVIRONMENT_DEV) {
                $environment = self::ENVIRONMENT_DEVELOPMENT;
            } elseif ($_SERVER['ENVIRONMENT'] == self::ENVIRONMENT_STAGING) {
                $environment = self::ENVIRONMENT_STAGING;
            }
        }
        
        $this->environment = $environment;
    }

    /**
     * Get the current environment
     *
     * @return string The current environment ('development', 'staging', or 'production')
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * Get the short version of the environment name
     *
     * @return string Short environment name ('dev', 'stage', or 'prod')
     */
    public function getShortEnvironment(): string
    {
        $env = self::SHORT_ENVIRONMENT_PROD;
        if ($this->environment === self::ENVIRONMENT_DEVELOPMENT) {
            $env = self::SHORT_ENVIRONMENT_DEV;
        } elseif ($this->environment === self::ENVIRONMENT_STAGING) {
            $env = self::SHORT_ENVIRONMENT_STAGE;
        }
        return $env;
    }

    /**
     * Check if the current environment is development
     *
     * @return bool True if development, false otherwise
     */
    public function isDevelopment(): bool
    {
        return $this->environment === self::ENVIRONMENT_DEVELOPMENT;
    }

    /**
     * Check if the current environment is staging
     *
     * @return bool True if staging, false otherwise
     */
    public function isStaging(): bool
    {
        return $this->environment === self::ENVIRONMENT_STAGING;
    }

    /**
     * Check if the current environment is production
     *
     * @return bool True if production, false otherwise
     */
    public function isProduction(): bool
    {
        return $this->environment === self::ENVIRONMENT_PRODUCTION;
    }

    /**
     * Get the config file path for the current environment
     *
     * @param string $configDir The configuration directory
     * @return string The path to the environment-specific config file
     */
    public function getConfigFilePath(string $configDir): string
    {
        return $configDir . '/config_' . $this->getShortEnvironment() . '.json';
    }

    /**
     * Get the constants file path for the current environment
     *
     * @param string $configDir The configuration directory
     * @return string The path to the environment-specific constants file
     */
    public function getConstantsFilePath(string $configDir): string
    {
        $constantsFileName = '/constants';
        if (!empty($this->environment)) {
            $constantsFileName .= '_' . $this->getShortEnvironment() . '.json';
        } else {
            $constantsFileName .= '.json';
        }
        
        return $configDir . $constantsFileName;
    }

    /**
     * Process environment-specific value from a configuration array
     *
     * @param mixed $value The configuration value that might be environment-specific
     * @return mixed The value for the current environment
     */
    public function getEnvironmentSpecificValue($value)
    {
        if (is_array($value) && isset($value[$this->environment])) {
            return $value[$this->environment];
        }
        
        return $value;
    }
}