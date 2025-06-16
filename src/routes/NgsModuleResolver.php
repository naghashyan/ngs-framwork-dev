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
     * Cached shuffled routes
     */
    private array $shuffledRoutes = [];

    /**
     * Current module
     */
    private ?NgsModule $currentModule = null;

    /**
     * Dynamic URL token
     *
     * This is used to scan the URL and find the appropriate request object
     * without scanning the routes file
     */
    private string $dynUrlToken = 'dyn';

    /**
     * Module URI
     */
    private ?string $uri = null;

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
        $domain = $requestContext->getHttpHost(true);

        // Handle case when domain is not available
        if (!$domain) {
            return $this->handleNoDomain();
        }

        // Parse domain and get module configuration
        $parsedUrl = parse_url($domain);
        $mainDomain = $requestContext->getMainDomain();
        $moduleConfigArray = $this->getModulesConfig();
        $path = $parsedUrl['path'] ?? '';
        $host = explode('.', $path);

        // Extract path segments from URL
        $pathSegments = $this->extractPathSegmentsFromUri($url);


        if (!empty($pathSegments)) {
            // Check if first segment is dynamic URL token and remove it
            if ($pathSegments[0] === $this->getDynUrlToken()) {
                array_shift($pathSegments);

                // If no segments left after removing dynamic URL token
                if (empty($pathSegments)) {
                    return null;
                }
            }

            // Check if the first segment matches a path module
            $firstSegment = $pathSegments[0];

            if (isset($moduleConfigArray[NgsModule::MODULE_TYPE_PATH][$firstSegment])) {
                $moduleConfig = $moduleConfigArray[NgsModule::MODULE_TYPE_PATH][$firstSegment];
                if(isset($moduleConfig["dir"])) {
                    $moduleName  = $moduleConfig["dir"];
                    $module = NGS()->getModule($moduleName, NgsModule::MODULE_TYPE_PATH);

                    return $module;
                }
                //TODO: ZN: handle the error case

            }

            // Check if it's the default namespace
            if ($firstSegment === $this->getDefaultModule()->getName()) {
                return $this->getMatchedModule(['dir' => $this->getDefaultModule()->getName()], $this->getDefaultModule()->getName(), NgsModule::MODULE_TYPE_PATH);
            }
        }

        // Try to find module by subdomain
        if (count($host) >= 3) {
            $moduleBySubdomain = $this->getModuleBySubDomain($moduleConfigArray, $host[0]);
            if ($moduleBySubdomain) {
                return $moduleBySubdomain;
            }
        }

        // Use default module as fallback
        return $this->handleDefaultModule();
    }

    public function getDefaultModule(): NgsModule
    {
        return NGS();
    }

    /**
     * check module by name
     *
     *
     * @param String $name
     *
     * @return true|false
     */
    public function checkModuleByUri(string $name): bool
    {
        $routes = $this->getModulesConfig();
        if (isset($routes['subdomain'][$name])) {
            return true;
        }

        if (isset($routes['domain'][$name])) {
            return true;
        }

        if (isset($routes['path'][$name])) {
            return true;
        }

        if ($name === $this->getDefaultModule()) {
            return true;
        }
        return false;
    }

    /**
     * check module by name
     *
     *
     * @param String $name
     *
     * @return true|false
     */
    public function checkModuleByName(string $moduleName): bool
    {
        $routes = $this->getShuffledRoutes();
        if (isset($routes[$moduleName])) {
            return true;
        }
        return false;
    }

    /**
     * Returns a list of all modules in the application
     *
     * This method collects all modules from different types (subdomain, domain, path)
     * and caches the result for future use.
     *
     * @return array List of all module namespaces
     */
    public function getAllModulesDirs(): array
    {
        $modules = $this->getModulesConfig();

        $modulesDirectoryList = [];
        $moduleTypes = \ngs\NgsModule::MODULE_TYPES;

        foreach ($moduleTypes as $type) {
            if (isset($modules[$type])) {
                foreach ($modules[$type] as $module) {
                    if (isset($module['dir'])) {
                        $modulesDirectoryList[] = $this->getModulePath($module['dir']);
                    }
                }
            }
        }

        return $modulesDirectoryList;
    }

    /**
     * detect if current module is default module
     *
     * @return Boolean
     */
    public function isDefaultModule(): bool
    {
        return $this->getModuleName() === $this->getDefaultModule();
    }

    /**
     * detect if $ns is current module
     *
     * @param string $namespace
     *
     * @return Boolean
     */
    public function isCurrentModule(string $moduleName): bool
    {
        return $this->getModuleName() === $moduleName;
    }



    /**
     * Handles the case when no domain is available
     *
     * @return NgsModule Default module information
     */
    private function handleNoDomain(): NgsModule
    {
        $uri = '';
        $moduleConfigArray = $this->getModulesConfig();
        $this->currentModule = $this->getMatchedModule($moduleConfigArray['default'], $uri, 'default');
        return $this->currentModule;
    }

    /**
     * Handles module resolution by URI
     *
     * @param NgsModule $moduleByUri Module resolved from URI
     * @param array $host Host parts
     * @param array $moduleConfigArray Module configuration array
     * @return NgsModule Module information
     */
    private function handleModuleByUri(NgsModule $moduleByUri, array $host, array $moduleConfigArray): NgsModule
    {
        $this->currentModule = $moduleByUri;

        // Check for parent module in subdomain
        if (count($host) >= 3) {
            $parentModule = $this->getModuleBySubDomain($moduleConfigArray, $host[0]);
            if ($parentModule) {
                $this->setParentModule($parentModule);
            }
        }

        return $this->currentModule;
    }

    /**
     * Handles module resolution by subdomain
     *
     * @param NgsModule $moduleBySubdomain Module information from subdomain
     * @return NgsModule Module information
     */
    private function handleModuleBySubdomain(NgsModule $moduleBySubdomain): NgsModule
    {
        $this->currentModule = $moduleBySubdomain;
        return $this->currentModule;
    }

    /**
     * Handles fallback to default module
     *
     * @param array $moduleConfigArray Module configuration array
     * @param string $uri Request URI
     * @return NgsModule Default module information
     */
    private function handleDefaultModule(): NgsModule
    {
        return NGS();
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
        $routes = $modulePart;
        if (isset($routes[NgsModule::MODULE_TYPE_SUBDOMAIN][$domain])) {
            return $this->getMatchedModule($routes[NgsModule::MODULE_TYPE_SUBDOMAIN][$domain], $domain, NgsModule::MODULE_TYPE_SUBDOMAIN);
        }
        return null;
    }

    /**
     * Finds a module by URI path
     *
     * This method extracts path segments from the URI and tries to match them
     * against module configurations to find the appropriate module.
     *
     * @param array $modulePart Module configuration array
     * @param string $uri Request URI to analyze
     * @return NgsModule|null Module information or null if no match found
     */
    private function getModuleByURI(array $modulePart, string $uri): ?NgsModule
    {
        // Extract path segments from URI
        $pathSegments = $this->extractPathSegmentsFromUri($uri);
        var_dump(222);
        var_dump($pathSegments);exit;
        if (empty($pathSegments)) {
            return null;
        }

        // Check if first segment is dynamic URL token and remove it
        if ($pathSegments[0] === $this->getDynUrlToken()) {
            array_shift($pathSegments);

            // If no segments left after removing dynamic URL token
            if (empty($pathSegments)) {
                return null;
            }
        }

        // Check if the first segment matches a path module
        $firstSegment = $pathSegments[0];

        if (isset($modulePart[NgsModule::MODULE_TYPE_PATH][$firstSegment])) {
            return $this->getMatchedModule($modulePart[NgsModule::MODULE_TYPE_PATH][$firstSegment], $firstSegment, NgsModule::MODULE_TYPE_PATH);
        }

        // Check if it's the default namespace
        if ($firstSegment === $this->getDefaultModule()) {
            return $this->getMatchedModule(['dir' => $this->getDefaultModule()], $this->getDefaultModule(), NgsModule::MODULE_TYPE_PATH);
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
     * Returns the dynamic URL token
     * This method can be overridden by users if they don't want to use 'dyn' token
     * The token is used to scan the URL and find the appropriate request object
     * without scanning the routes file
     *
     * @return string The dynamic URL token
     */
    protected function getDynUrlToken(): string
    {
        return $this->dynUrlToken;
    }

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

            try {
                $moduleRouteFile = realpath($moduleConfigFilePath);

                $this->modules = json_decode(file_get_contents($moduleRouteFile), true);
            } catch (\Exception $exception) {
                throw new DebugException('module.json not found please add file json into ' . $moduleConfigFilePath);
            }
        }

        return $this->modules;
    }

    /**
     * get shuffled routes json
     * key=>dir value=namespace
     * and set in private shuffledRoutes property for cache
     *
     * @return array
     */
    private function getShuffledRoutes(): array
    {
        if (count($this->shuffledRoutes) > 0) {
            return $this->shuffledRoutes;
        }

        $modulesConfig = $this->getModulesConfig();

        $this->shuffledRoutes = array();
        foreach ($modulesConfig as $type => $moduleConfig) {

            foreach ($moduleConfig as $item) {
                if (isset($item['dir'])) {
                    $this->shuffledRoutes[$item['dir']] = array('path' => $item['dir'], 'type' => $type);
                } elseif (isset($item['extend'])) {
                    $this->shuffledRoutes[$item['extend']] = array('path' => $item['extend'], 'type' => $type);
                }
            }

        }
        return $this->shuffledRoutes;
    }

    /**
     * Calculates the root directory path for a module
     *
     * This method determines the appropriate root directory for a module based on its namespace.
     * It handles special cases like the default module, framework, and CMS modules.
     *
     * @param string $namespace Module namespace (empty string for current module)
     * @return string|null Root directory path or null if not found
     */
    private function getRootDir(): ?string
    {
        return NGS()->get('NGS_ROOT');
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
            if ($this->currentModule !== null) {
                return $this->currentModule->getDir();
            }
            return $this->getRootDir();
        }

        return realpath($rootPath . '/' . $modulesDir . '/' . $moduleName);
    }

    /**
     * ==========================================
     * DEPRECATED METHODS
     * ==========================================
     */

    /**
     * Checks if the specified namespace is the framework module
     *
     * @param string $namespace Module namespace
     * @return bool True if it's the framework module
     * @deprecated This method is deprecated and will be removed in future versions
     */
    private function isFrameworkModule(string $namespace): bool
    {
        return $namespace === NGS()->get('FRAMEWORK_NS');
    }

    /**
     * Checks if the specified namespace is the CMS module
     *
     * @param string $namespace Module namespace
     * @return bool True if it's the CMS module
     * @deprecated This method is deprecated and will be removed in future versions
     */
    private function isCmsModule(string $namespace): bool
    {
        $cmsNs = NGS()->get('NGS_CMS_NS');
        return ($namespace === $cmsNs) ||
            ($namespace === '' && $this->getModuleName() === $cmsNs);
    }

    /**
     * Gets the CMS module path
     *
     * @return string|null CMS path or null if not found
     * @deprecated This method is deprecated and will be removed in future versions
     */
    private function getCmsPath(): ?string
    {
        return NGS()->getNgsCmsDir();
    }

    /**
     * Return current module name
     * @return String
     * @deprecated
     */
    public function getModuleName(): string
    {
        if ($this->currentModule !== null) {
            return $this->currentModule->getName();
        }

        return "";
    }


    /**
     * Return the current module type.
     *
     * @deprecated Module type is now stored inside {@see NgsModule}. This
     * method will be removed in future versions.
     */
    public function getModuleType(): string
    {
        if ($this->currentModule !== null) {
            return $this->currentModule->getType();
        }

        return 'domain';
    }
}
