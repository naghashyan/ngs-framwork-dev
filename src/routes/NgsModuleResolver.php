<?php
/**
 * Default NGS modules routing class
 * This class is used by the dispatcher for matching URLs with module routes
 *
 * @author Levon Naghashyan <levon@naghashyan.com>
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
    private array $routes = [];

    /**
     * Cached shuffled routes
     */
    private array $shuffledRoutes = [];

    /**
     * Default namespace
     */
    private ?string $defaultNS = null;

    /**
     * Current module
     */
    private ?NgsModule $currentModule = null;

    /**
     * List of all modules
     */
    private array $modulesLists = [];

    /**
     * Dynamic URL token
     * 
     * This is used to scan the URL and find the appropriate request object
     * without scanning the routes file
     */
    private string $dynUrlToken = 'dyn';



    /**
     * Module namespace
     */
    private string $moduleName = '';

    /**
     * Module name
     */
    private string $accessName = '';

    /**
     * Parent module
     */
    private ?array $parentModule = null;

    /**
     * Module URI
     */
    private ?string $uri = null;


    /**
     * Constructor
     * 
     * @throws DebugException When module.json is not found
     */
    public function __construct()
    {
        $module = $this->getCurrentModule();
        if (!$module) {
            throw new DebugException('module.json not found please add file json into config folder');
        }

        $this->currentModule = $module;
    }

    public function initialize(): void
    {
    }

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
    private function getRouteConfig(): array
    {
        if (count($this->routes) == 0) {
            $moduleConfigFilePath = NGS()->get('NGS_ROOT') . '/' . NGS()->get('CONF_DIR') . '/' . NGS()->get('NGS_MODULS_ROUTS');
            try {
                $moduleRouteFile = realpath($moduleConfigFilePath);
                $this->routes = json_decode(file_get_contents($moduleRouteFile), true);
            } catch (\Exception $exception) {
                throw new DebugException('module.json not found please add file json into ' . $moduleConfigFilePath);
            }

        }
        return $this->routes;
    }

    /**
     * get shuffled routes json
     * key=>dir value=namespace
     * and set in private shuffledRoutes property for cache
     *
     * @return array
     */
    public function getShuffledRoutes(): array
    {
        if (count($this->shuffledRoutes) > 0) {
            return $this->shuffledRoutes;
        }
        $routes = $this->getRouteConfig();
        $this->shuffledRoutes = array();
        foreach ($routes as $domain => $route) {
            foreach ($route as $type => $routeItem) {
                if ($type === 'default') {
                    $this->shuffledRoutes[$routeItem['dir']] = array('path' => $routeItem['dir'], 'type' => $type, 'domain' => $domain);
                    continue;
                }
                foreach ($routeItem as $item) {
                    if (isset($item['dir'])) {
                        $this->shuffledRoutes[$item['dir']] = array('path' => $item['dir'], 'type' => $type, 'domain' => $domain);
                    } elseif (isset($item['extend'])) {
                        $this->shuffledRoutes[$item['extend']] = array('path' => $item['extend'], 'type' => $type, 'domain' => $domain);
                    }
                }
            }
        }
        return $this->shuffledRoutes;
    }

    public function getDefaultNS(): string
    {
        if ($this->defaultNS !== null) {
            return $this->defaultNS;
        }
        $routes = $this->getRouteConfig();
        if (isset($routes['default']['default'])) {
            $defaultModule = $routes['default']['default'];
            $defaultMatched = $this->getMatchedModule($defaultModule, '', 'default');
            $this->defaultNS = $defaultMatched['ns'];
        } else {
            $this->defaultNS = NGS()->getDefinedValue('DEFAULT_NS');
        }
        return $this->defaultNS;
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
        $routes = $this->getRouteConfig();
        if (isset($routes['subdomain'][$name])) {
            return true;
        }

        if (isset($routes['domain'][$name])) {
            return true;
        }

        if (isset($routes['path'][$name])) {
            return true;
        }

        if ($name === $this->getDefaultNS()) {
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
     * Determines the current module based on the request
     * 
     * This method analyzes the request to determine which module should handle it.
     * It checks the domain, subdomain, and URI to find the appropriate module.
     *
     * @return NgsModule Module information
     */
    protected function getCurrentModule(): NgsModule
    {
        // Return cached result if available
        if ($this->currentModule !== null) {
            return $this->currentModule;
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
        $moduleConfigArray = $this->getModulePartByDomain($mainDomain);
        $path = $parsedUrl['path'] ?? '';
        $host = explode('.', $path);
        $uri = $requestContext->getRequestUri(true);

        // Try to find module by URI
        $moduleByUri = $this->getModuleByURI($moduleConfigArray, $uri);
        if ($moduleByUri) {
            return $this->handleModuleByUri($moduleByUri, $host, $moduleConfigArray);
        }

        // Try to find module by subdomain
        if (count($host) >= 3) {
            $moduleBySubdomain = $this->getModuleBySubDomain($moduleConfigArray, $host[0]);
            if ($moduleBySubdomain) {
                return $this->handleModuleBySubdomain($moduleBySubdomain);
            }
        }

        // Use default module as fallback
        return $this->handleDefaultModule($moduleConfigArray, $uri);
    }

    /**
     * Handles the case when no domain is available
     * 
     * @return NgsModule Default module information
     */
    private function handleNoDomain(): NgsModule
    {
        $uri = '';
        $moduleConfigArray = $this->getModulePartByDomain(null);
        $this->setAccessName('default');
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
        $this->setAccessName($this->getModuleUri());

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
        $this->setAccessName($this->getModuleUri());
        return $this->currentModule;
    }

    /**
     * Handles fallback to default module
     * 
     * @param array $moduleConfigArray Module configuration array
     * @param string $uri Request URI
     * @return NgsModule Default module information
     */
    private function handleDefaultModule(array $moduleConfigArray, string $uri): NgsModule
    {
        $this->setAccessName('default');
        $this->currentModule = $this->getMatchedModule($moduleConfigArray['default'], $uri, 'default');
        return $this->currentModule;
    }

    /**
     * Gets module configuration for a specific domain
     *
     * This method retrieves the module configuration for the specified domain.
     * If no configuration exists for the domain, it falls back to the default configuration.
     * If no default configuration exists, it throws an exception.
     *
     * @param string|null $domain Domain name to get configuration for
     * @return array Module configuration array
     * @throws DebugException When no default section is found in module.json
     */
    private function getModulePartByDomain(?string $domain = null): array
    {
        $routes = $this->getRouteConfig();

        // Check if configuration exists for the specified domain
        if ($domain !== null && isset($routes[$domain])) {
            return $routes[$domain];
        }

        // Fall back to default configuration
        if (isset($routes['default'])) {
            return $routes['default'];
        }

        // No default configuration found
        throw new DebugException('PLEASE ADD DEFAULT SECTION IN module.json');
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
        if (isset($routes['subdomain'][$domain])) {
            return $this->getMatchedModule($routes['subdomain'][$domain], $domain, 'subdomain');
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

        if (isset($modulePart['path'][$firstSegment])) {
            return $this->getMatchedModule($modulePart['path'][$firstSegment], $firstSegment, 'path');
        }

        // Check if it's the default namespace
        if ($firstSegment === $this->getDefaultNS()) {
            return $this->getMatchedModule(['dir' => $this->getDefaultNS()], $this->getDefaultNS(), 'path');
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
        preg_match_all('/(\/([^\/\?]+))/', $uri, $matches);

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

        $this->setModuleName($moduleName);
        $this->setModuleUri($uri);

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

    //Module interface implementation

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

    /**
     * Set module name (folder name)
     *
     * @param string $moduleName
     */
    private function setModuleName(string $moduleName): void
    {
        $this->moduleName = $moduleName;

        // Update the current module if it's not already set
        if ($this->currentModule === null) {
            $moduleDir = $this->getRootDir($moduleName);
            if ($moduleDir) {
                $this->currentModule = new NgsModule($moduleDir);
            }
        }
    }

    /**
     * Return current module name
     *
     * @return String
     */
    public function getModuleName(): string
    {
        return $this->moduleName;
    }

    /**
     * set module name domain or subdomain or path
     *
     * @param $name String
     *
     * @return void
     */
    private function setAccessName(string $name): void
    {
        $this->accessName = $name;
    }

    /**
     * @return array
     */
    public function getParentModule(): ?array
    {
        return $this->parentModule;
    }

    /**
     * @param array $parentModule
     */
    public function setParentModule(array $parentModule): void
    {
        $this->parentModule = $parentModule;
    }

    /**
     * return current name
     *
     * @return String
     */
    public function getAccessName(): string
    {
        return $this->accessName;
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
        // Return cached result if available
        if (!empty($this->modulesLists)) {
            return $this->modulesLists;
        }

        // Initialize empty list
        $modulesList = [];
        $routes = $this->getRouteConfig();

        // Collect modules by type without using array_merge in a loop
        $this->collectModulesByTypes($routes, $modulesList);

        // Add default modules
        $this->collectDefaultModules($routes, $modulesList);

        // Cache and return result
        $this->modulesLists = $modulesList;
        return $this->modulesLists;
    }

    /**
     * Collects modules by types and adds them to the modules list
     * 
     * @param array $routes Routes configuration
     * @param array &$modulesList List to populate with modules
     * @return void
     */
    private function collectModulesByTypes(array $routes, array &$modulesList): void
    {
        $moduleTypes = ['subdomain', 'domain', 'path'];

        foreach ($moduleTypes as $type) {
            if (isset($routes[$type])) {
                foreach ($routes[$type] as $value) {
                    if (isset($value['dir'])) {
                        $modulesList[] = $value['dir'];
                    }
                }
            }
        }
    }

    /**
     * Collects default modules and adds them to the modules list
     * 
     * @param array $routes Routes configuration
     * @param array &$modulesList List to populate with modules
     * @return void
     */
    private function collectDefaultModules(array $routes, array &$modulesList): void
    {
        foreach ($routes as $value) {
            if (isset($value['default'], $value['default']['dir'])) {
                $modulesList[] = $value['default']['dir'];
            }
        }
    }

    /**
     * Returns a list of modules of a specific type
     * 
     * @param array $routes Routes configuration
     * @param string $type Module type (subdomain, domain, path)
     * @return array List of module namespaces of the specified type
     */
    private function getModulesByType(array $routes, string $type): array
    {
        $modulesList = [];

        if (isset($routes[$type])) {
            foreach ($routes[$type] as $value) {
                if (isset($value['dir'])) {
                    $modulesList[] = $value['dir'];
                }
            }
        }

        return $modulesList;
    }

    /**
     * return module dir connedted with namespace
     *
     * @return String
     */
    public function getModuleNsByUri(string $uri): ?string
    {
        $routes = $this->getRouteConfig();
        if (isset($routes['subdomain'][$uri])) {
            return $routes['subdomain'][$uri];
        } elseif (isset($routes['domain'][$uri])) {
            return $routes['domain'][$uri];
        } elseif (isset($routes['path'][$uri])) {
            return $routes['path'][$uri];
        }
        return null;
    }

    //module function for working with modules urls
    public function setModuleUri(string $uri): void
    {
        $this->uri = $uri;
    }

    public function getModuleUri(): string
    {
        return $this->uri;
    }

    /**
     * @param $ns
     *
     * @return null
     */
    public function getModuleUriByName(string $moduleName): ?array
    {
        $routes = $this->getShuffledRoutes();
        if (isset($routes[$moduleName])) {
            return $routes[$moduleName];
        }
        return null;
    }



    /**
     * detect if current module is default module
     *
     * @return Boolean
     */
    public function isDefaultModule(): bool
    {
        return $this->getModuleName() === $this->getDefaultNS();
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
     * Calculates the root directory path for a module
     * 
     * This method determines the appropriate root directory for a module based on its namespace.
     * It handles special cases like the default module, framework, and CMS modules.
     *
     * @param string $namespace Module namespace (empty string for current module)
     * @return string|null Root directory path or null if not found
     */
    public function getRootDir(string $namespace = ''): ?string
    {
        // If namespace is empty and currentModule is set, return its directory
        if ($namespace === '' && $this->currentModule !== null) {
            return $this->currentModule->getDir();
        }

        // Handle default module
        if ($this->isDefaultModuleNamespace($namespace)) {
            return NGS()->get('NGS_ROOT');
        }

        // Handle framework module
        if ($this->isFrameworkModule($namespace)) {
            return NGS()->getFrameworkDir();
        }

        // Handle CMS module
        if ($this->isCmsModule($namespace)) {
            return $this->getCmsPath();
        }

        // Handle regular modules
        return $this->getModulePath($namespace);
    }

    /**
     * Checks if the specified namespace is the default module
     * 
     * @param string $namespace Module namespace
     * @return bool True if it's the default module
     */
    private function isDefaultModuleNamespace(string $namespace): bool
    {
        return ($namespace === '' && $this->getDefaultNS() == $this->getModuleName()) ||
               $this->getDefaultNS() == $namespace;
    }


    /**
     * Gets the path for a regular module
     * 
     * @param string $namespace Module namespace
     * @return string|null Module path or null if not found
     */
    private function getModulePath(string $namespace): ?string
    {
        $rootPath = NGS()->get('NGS_ROOT');
        $modulesDir = NGS()->get('MODULES_DIR');

        if ($namespace === '') {
            if ($this->currentModule !== null) {
                return $this->currentModule->getDir();
            }
            return realpath($rootPath . '/' . $modulesDir . '/' . $this->getModuleName());
        }

        return realpath($rootPath . '/' . $modulesDir . '/' . $namespace);
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

}
