<?php
/**
 * Default NGS modules routing class
 * This class is used by the dispatcher for matching URLs with module routes
 *
 * @author Levon Naghashyan <levon@naghashyan.com>
 * @author Zaven Naghashyan <zaven@naghashyan.com>
 * @site https://naghashyan.com
 * @year 2015-2023
 * @package ngs.framework.routes
 * @version 5.0.0
 *
 * This file is part of the NGS package.
 *
 * @copyright Naghashyan Solutions LLC
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ngs\routes;

use ngs\exceptions\DebugException;
use ngs\NgsModule;

/**
 * Class NgsModuleResolver - Handles module routing in the NGS framework
 *
 * @package ngs\routes
 */
class NgsModuleResolver
{
    /**
     * Cached routes configuration
     */
    private array $modules = [];


    /**
     * Singleton instance
     */
    private static ?NgsModuleResolver $instance = null;


    /**
     * Constructor
     *
     * @throws DebugException When module.json is not found
     */
    public function __construct()
    {

    }


    /**
     * Get singleton instance of NgsModuleResolver
     *
     * @return NgsModuleResolver The singleton instance
     */
    public static function getInstance(): NgsModuleResolver
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Resolves a module from a URL
     *
     * This method analyzes the URL to determine which module should handle it.
     * It extracts the module segment from the URL and returns the corresponding module instance.
     *
     * @param string $url The URL to resolve
     * @return NgsModule|null The resolved module instance or null if no module is found
     */
    public function resolveModule(string $url): ?NgsModule
    {
        // Normalize URL by removing leading slash
        if (!empty($url) && $url[0] === '/') {
            $url = substr($url, 1);
        }

        // Get domain information
        $requestContext = NGS()->createDefinedInstance('REQUEST_CONTEXT', \ngs\util\RequestContext::class);
        $host = $requestContext->getHttpHost(true) ?? '';
        $mainDomain = $requestContext->getMainDomain();

        $modulesConfig = $this->getModulesConfig();

        // 1) Domain has highest priority
        $mapped = $modulesConfig['default']['domain']['map'] ?? null;
        if ($mapped && (!empty($host) && $host === $mainDomain)) {
            return NGS()->getModule($mapped, NgsModule::MODULE_TYPE_DOMAIN);
        }

        // 2) Subdomain next
        if (!empty($host)) {
            $labels = explode('.', $host);
            if (count($labels) >= 3) {
                $sub = $labels[0];
                $moduleBySubdomain = $this->getModuleBySubDomain($modulesConfig, $sub);
                if ($moduleBySubdomain) {
                    return $moduleBySubdomain;
                }
            }
        }

        // 3) Path last
        // Extract path segments from URL
        $pathSegments = $this->extractPathSegmentsFromUri($url);

        if (!empty($pathSegments)) {
            $dynUrlToken = NGS()->get('DYN_URL_TOKEN');
            // Check if first segment is dynamic URL token and remove it
            if ($pathSegments[0] === $dynUrlToken) {
                array_shift($pathSegments);

                // If no segments left after removing dynamic URL token
                if (empty($pathSegments)) {
                    return null;
                }
            }

            // Check if the first segment matches a path module
            $firstSegment = $pathSegments[0];

            // Support modules.json structure per modules.md: within default.path
            $pathConfig = $modulesConfig['default']['path'] ?? ($modulesConfig[NgsModule::MODULE_TYPE_PATH] ?? []);
            if (isset($pathConfig[$firstSegment])) {
                $moduleConfig = $pathConfig[$firstSegment];
                $moduleName = $this->extractNamespaceFromConfig($moduleConfig);
                return NGS()->getModule($moduleName, NgsModule::MODULE_TYPE_PATH);
            }

            // If first segment equals configured default module name, return it as path type
            $defaultModule = $this->getDefaultModule();
            if ($firstSegment === $defaultModule->getName()) {
                return NGS()->getModule($defaultModule->getName(), NgsModule::MODULE_TYPE_PATH);
            }
        }

        // Use default module as fallback
        return $this->getDefaultModule();
    }

    public function getDefaultModule(): NgsModule
    {
        $modulesConfig = $this->getModulesConfig();
        // modules.md structure: default.default.dir points to module name
        $defaultDir = $modulesConfig['default']['default']['dir'] ?? '';
        try {
            return NGS()->getModule($defaultDir, NgsModule::MODULE_TYPE_DOMAIN);
        } catch (\Throwable $e) {
            // Fallback to root NGS module if config missing or invalid
            return NGS();
        }
    }

    /**
     * Returns a list of all modules in the application
     *
     * This method collects all modules from different types (subdomain, domain, path)
     * and caches the result for future use.
     *
     * @return array List of all module namespaces
     */
    public function getAllModules(): array
    {
        $modulesConfig = $this->getModulesConfig();

        $modulesList = [];

        // Support documented structure under default
        $defaultBlock = $modulesConfig['default'] ?? [];

        // Collect from path
        $pathConfig = $defaultBlock[NgsModule::MODULE_TYPE_PATH] ?? ($modulesConfig[NgsModule::MODULE_TYPE_PATH] ?? []);
        foreach ($pathConfig as $key => $moduleConfig) {
            try {
                $name = $this->extractNamespaceFromConfig($moduleConfig);
                $modulesList[] = NGS()->getModule($name, NgsModule::MODULE_TYPE_PATH);
            } catch (\Throwable $e) {
                continue;
            }
        }

        // Collect from subdomain
        $subConfig = $defaultBlock[NgsModule::MODULE_TYPE_SUBDOMAIN] ?? ($modulesConfig[NgsModule::MODULE_TYPE_SUBDOMAIN] ?? []);
        foreach ($subConfig as $key => $moduleConfig) {
            try {
                $name = $this->extractNamespaceFromConfig($moduleConfig);
                $modulesList[] = NGS()->getModule($name, NgsModule::MODULE_TYPE_SUBDOMAIN);
            } catch (\Throwable $e) {
                continue;
            }
        }

        // Domain map (single mapping)
        $mapped = $defaultBlock[NgsModule::MODULE_TYPE_DOMAIN]['map'] ?? null;
        if ($mapped) {
            try {
                $modulesList[] = NGS()->getModule($mapped, NgsModule::MODULE_TYPE_DOMAIN);
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return $modulesList;
    }

    /**
     * Handles the case when no domain is available
     *
     * @return NgsModule Default module information
     */
    private function handleNoDomain(): NgsModule
    {
        // When no domain info, return configured default module if possible
        return $this->getDefaultModule();
    }


    /**
     * return module by subdomain
     *
     * @param String $domain
     *
     * @return NgsModule|null
     */
    private function getModuleBySubDomain(array $modulePart, string $domain): ?NgsModule
    {
        // Support both documented structure (default.subdomain) and flat (subdomain)
        $routes = $modulePart['default'][NgsModule::MODULE_TYPE_SUBDOMAIN] ?? ($modulePart[NgsModule::MODULE_TYPE_SUBDOMAIN] ?? []);

        if (isset($routes[$domain])) {
            $moduleName = $this->extractNamespaceFromConfig($routes[$domain]);
            return NGS()->getModule($moduleName, NgsModule::MODULE_TYPE_SUBDOMAIN);
        }

        return null;
    }

    /**
     * Extracts path segments from a URI
     *
     * @param string $uri URI to parse
     * @return array Array of path segments
     */
    private function extractPathSegmentsFromUri(string $uri): array
    {
        $matches = [];
        preg_match_all('/(([^\/\?]+))/', $uri, $matches);

        if (isset($matches[2]) && is_array($matches[2]) && !empty($matches[2])) {
            return $matches[2];
        }

        return [];
    }

    /**
     * Creates a module information array from matched configuration
     *
     * This method extracts the namespace from the matched module configuration
     * and creates a standardized module information array.
     *
     * @param array $matchedArr Matched module configuration array
     * @param string $uri URI associated with this module
     * @param string $type Module type (domain, subdomain, path)
     * @return array Standardized module information array
     * @throws DebugException When required configuration is missing
     */
    protected function getMatchedModule(array $matchedArr, string $uri, string $type): NgsModule
    {
        $moduleName = $this->extractNamespaceFromConfig($matchedArr);

        $moduleDir = $this->getModulePath($moduleName);

        if ($moduleDir === null) {
            throw new DebugException('Module directory not found: ' . $moduleName);
        }

        return new NgsModule($moduleDir, $type);
    }

    /**
     * Extracts the namespace from module configuration
     *
     * @param array $config Module configuration array
     * @return string Extracted namespace
     * @throws DebugException When required configuration is missing
     */
    private function extractNamespaceFromConfig(array $config): string
    {
        // Check for 'dir' property (preferred)
        if (isset($config['dir'])) {
            return $config['dir'];
        }

        // Check for 'namespace' property (alternative)
        if (isset($config['namespace'])) {
            return $config['namespace'];
        }

        // Check for 'extend' property (extension)
        if (isset($config['extend'])) {
            // Set custom routes file if specified
            if (isset($config['route_file'])) {
                NGS()->define('NGS_MODULE_ROUTS', $config['route_file']);
            }

            return $config['extend'];
        }

        // No valid namespace found
        throw new DebugException('PLEASE ADD DIR OR NAMESPACE SECTION IN module.json');
    }


    //--------------------------------------------------


    /**
     * read from file json routes
     * and set in private property for cache
     *
     * @return json Array
     */
    private function getModulesConfig(): array
    {
        if (count($this->modules) == 0) {
            $moduleConfigFilePath = NGS()->get('NGS_ROOT') . '/' . NGS()->get('CONF_DIR') . '/' . NGS()->get('NGS_MODULS_ROUTS');

            if (!file_exists($moduleConfigFilePath)) {
                throw new DebugException('module.json not found please add file json into ' . $moduleConfigFilePath);
            }

            $json = file_get_contents($moduleConfigFilePath);
            $data = json_decode($json, true);
            if (!is_array($data)) {
                throw new DebugException('Invalid modules.json content at ' . $moduleConfigFilePath);
            }
            $this->modules = $data;
        }

        return $this->modules;
    }

    /**
     * Gets the path for a regular module
     *
     * @param string $moduleName Module namespace
     * @return string|null Module path or null if not found
     */
    private function getModulePath(string $moduleName): ?string
    {
        $rootPath = NGS()->get('NGS_ROOT');
        $modulesDir = NGS()->get('MODULES_DIR');

        if ($moduleName === '') {
            return $rootPath;
        }

        return realpath($rootPath . '/' . $modulesDir . '/' . $moduleName);
    }

}
