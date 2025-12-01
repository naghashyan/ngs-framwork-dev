<?php

namespace ngs\routes;

use ngs\exceptions\DebugException;
use ngs\exceptions\NotFoundException;
use ngs\NgsModule;
use ngs\request\AbstractAction;
use ngs\request\AbstractLoad;

/**
 * TODO: ZN:
 * 1. handle the case with the static files
 * 2. handle not found case
 * 3. check test cases for all possible routing scenarios
 * 4. handle nesting case
 * 5. finalize the clean up for the modules management
 * 6. check routing from modules
 * 7. check the constants and config management from modules
 *
 * Class NgsRoutesResolver
 *
 * Resolves URLs to route data and provides per-group 404 info.
 * Always constructs NgsRoute via setters, never with arrays.
 *
     * @package ngs.framework
 */
class NgsRoutesResolver
{
    /**
     * Default package identifier constant
     */
    public const DEFAULT_PACKAGE_IDENTIFIER = 'default';

    /**
     * Default request identifier constant
     */
    public const DEFAULT_REQUEST_IDENTIFIER = 'default';

    /**
     * Default actions prefix
     */
    public const ACTIONS_PREFIX = 'do_';

    public const NOT_FOUND_KEY = '404';

    /**
     * @var array Routes configuration cached per module and package
     */
    protected array $routesConfigs = [];


    /**
     * Get route for the given URL and module
     *
     * This is the main entry point for the router in the new architecture.
     * It processes the URL within the context of the provided module and returns
     * a route object with all the necessary information for dispatching.
     *
     * @param string $url The URL to route
     * @param \ngs\NgsModule $module The module instance to use for routing
     *
     * @return NgsRoute|null The route object or null if no route was found
     */
    public function resolveRoute(\ngs\NgsModule $module, string $url): ?NgsRoute
    {
        // Get URL segments
        $urlSegments = $this->getUrlSegments($url);

        // Determine if URL points to a static file, checking here because after this we might break the segments
        $isStaticFile = $this->isStaticFile($urlSegments);

        $this->checkAndShiftModuleName($module, $urlSegments);

        // Extract package name based on segments and module type
        $package = $this->getPackageName($urlSegments);

        if ($package === NGS()->get('DYN_URL_TOKEN')) {
            $route = $this->handleWithDynamicUrlToken($module, $urlSegments);
        } else {
            $route = $this->handleWithRouteConfig($module, $package, $urlSegments);
        }
//TODO: ZN: should be revised
//        $notFoundRoute = $this->getNotFoundRouteForRequest();
//
//        $route->setNotFoundRequest($notFoundRoute);

        if (!$route->isMatched() && $isStaticFile) {
            $parsed = [
                'ngsRequestMatches' => $urlSegments,
                'segments' => $urlSegments,
                'fileUrl' => implode('/', $urlSegments),
                'module' => $module
            ];
            $route = $this->handleStaticFile($parsed);
            $package = $route->getModule();
        }

        // Attach per-group notFoundRequest from 404 files
        $notFoundRequest = $this->getNotFoundRouteForRequest($module, is_string($package) ? $package : ($package instanceof \ngs\NgsModule ? $package->getName() : self::DEFAULT_PACKAGE_IDENTIFIER));
        if ($notFoundRequest) {
            $route->setNotFoundRequest($notFoundRequest);
        }

        return $route;
    }


    //-----------------------------------------------------------------------------------
    // Route Handling Methods
    //-----------------------------------------------------------------------------------

    /**
     * Handle dynamic URL token routing
     *
     * Processes URLs that start with the dynamic URL token.
     *
     * @param array $urlSegments URL parts array
     * @param \ngs\NgsModule|null $module The module instance to use for routing
     *
     * @return NgsRoute The configured route object
     */
    private function handleWithDynamicUrlToken(\ngs\NgsModule $module, array $urlSegments): NgsRoute
    {
        $routePackage = $this->getPackageName($urlSegments);

        $requestIdentifier = $this->getRequestIdentifier($urlSegments);
        $args = $this->getArguments($urlSegments);

        $route = new NgsRoute();

        $requestPackage = NGS()->get('LOADS_DIR');
        $requestType = AbstractLoad::REQUEST_TYPE;

        if (strrpos($requestIdentifier, self::ACTIONS_PREFIX) !== false) {
            $requestPackage = NGS()->get('ACTIONS_DIR');
            $requestType = AbstractAction::REQUEST_TYPE;
        }

        $moduleName = $module->getName();

        $requestClassPath = $this->getRequestClassPath($moduleName, $requestPackage, $routePackage, $requestIdentifier);

        $route->setRequest($requestClassPath);
        $route->setType($requestType);
        $route->setArgs($args);
        $route->setMatched(true);

        return $route;
    }

    /**
     * Get dynamic route based on parsed URL components
     *
     * Processes the parsed URL to find a matching route in the configuration.
     * If a match is found, it creates and configures an NgsRoute object.
     *
     * @param array $parsed Parsed URL components
     *
     * @return NgsRoute The configured route object
     * @throws NotFoundException
     */
    private function handleWithRouteConfig(\ngs\NgsModule $module, string $package, array $segments): NgsRoute
    {
        // Extract request identifier
        $requestIdentifier = $this->getRequestIdentifier($segments);

        // Remaining segments are considered arguments
        $args = $this->getArguments($segments);

        // For pattern matching, consider the full tail (identifier + args)
        $fullIdentifier = $requestIdentifier;
        if ($fullIdentifier !== '' && !empty($args)) {
            $fullIdentifier .= '/' . implode('/', $args);
        }

        $routesConfig = $this->getRoutesConfig($module, $package);

        if ($routesConfig === null) {
            throw new NotFoundException('Routes configuration not found');
        }

        $matchedRouteConfig = $this->getMatchedRouteConfig($routesConfig, $fullIdentifier);

        $matchRequestType = $matchedRouteConfig->getRequestType();

        if ($matchRequestType === AbstractAction::REQUEST_TYPE) {
            $requestPackage = NGS()->get('ACTIONS_DIR');
        } else {
            $requestPackage = NGS()->get('LOADS_DIR');
        }

        $matchedRoutePackage = $matchedRouteConfig->getPackage();
        $matchedRouteIdentifier = $matchedRouteConfig->getRequestIdentifier();
        $moduleName = $module->getName();

        $requestClassPath = $this->getRequestClassPath($moduleName, $requestPackage, $matchedRoutePackage, $matchedRouteIdentifier);

        $route = new NgsRoute();

        $route->setRequest($requestClassPath);
        $route->setType($matchRequestType);
        // Merge named args from matched config with positional args; if route pattern had params, prefer named only
        $finalArgs = $matchedRouteConfig->getArgs();
        $hasParamsInPattern = str_contains($matchedRouteConfig->getRoute() ?? '', ':') || str_contains($matchedRouteConfig->getRoute() ?? '', '[:') || str_contains($matchedRouteConfig->getRoute() ?? '', '[/:');
        if (!$hasParamsInPattern) {
            $finalArgs = array_merge($finalArgs, $args);
        }
        $route->setArgs($finalArgs);
        $route->setMatched(true);
        $route->setModule($module);
        // Expose nestedLoad
        if ($matchedRouteConfig->hasNestedLoad()) {
            $route->setNestedLoad($matchedRouteConfig->getNestedLoad());
        }

        return $route;
    }

    /**
     * Handle static file routing
     *
     * Creates a route for static files like CSS, JS, images, etc.
     *
     * @param array $parsed Parsed URL components
     *
     * @return NgsFileRoute The configured route object for the static file
     */
    private function handleStaticFile(array $parsed): NgsFileRoute
    {
        $ngsRequestMatches = $parsed['ngsRequestMatches'];
        $segments = $parsed['segments'];
        $fileUrl = $parsed['fileUrl'];
        $module = $parsed['module'] ?? null;

        $route = new NgsFileRoute();

        [$package, $fileUrl, $filePieces] = $this->determinePackageAndFileUrl($ngsRequestMatches, $fileUrl);

        $package = $this->validatePackage($package);

        $route->setType('file');
        $route->setFileType(pathinfo(end($segments), PATHINFO_EXTENSION));
        $route->setMatched(true);

        // Use the module instance if provided, otherwise use the package name
        if ($module !== null) {
            $route->setModule($module);
        } else {
            $route->setModule($package);
        }

        $route->setFileUrl($fileUrl);

        $route->processSpecialFileTypes($filePieces);

        return $route;
    }

    /**
     * @param string $moduleName
     * @param mixed $requestPackage Loads or Actions
     * @param string|null $routePackage
     * @param string|null $requestIdentifier
     * @return string
     */
    private function getRequestClassPath(string $moduleName, mixed $requestPackage, ?string $routePackage, ?string $requestIdentifier): string
    {
        // Apply logic similar to getRequestInfoByRequest
        $requestName = $requestIdentifier;
        $classPrefix = '';

        // Determine class prefix based on request package type
        if ($requestPackage === NGS()->get('ACTIONS_DIR')) {
            $classPrefix = 'Action';
        } elseif ($requestPackage === NGS()->get('LOADS_DIR')) {
            $classPrefix = 'Load';
        }

        // Handle 'do_' prefix removal (similar to getRequestInfoByRequest logic)
        if (strrpos($requestName, 'do_') !== false) {
            $requestName = str_replace('do_', '', $requestName);
        }

        // Convert snake_case to PascalCase and add class prefix
        $requestName = preg_replace_callback('/_(\w)/', function ($m) {
                return strtoupper($m[1]);
            }, ucfirst($requestName)) . $classPrefix;

        // Build the class path with proper namespace separators (backslashes)
        $requestClassPath = $moduleName . '\\' . $requestPackage . '\\' . $routePackage . '\\' . $requestName;

        return $requestClassPath;
    }

    /**
     * Handle the case when no routes are matched
     *
     * This method is called when no routes match the requested URL.
     * It throws a DebugException in the default implementation.
     *
     * @throws DebugException When no routes match
     */
    protected function onNoMatchedRoutes()
    {
        throw new DebugException('No Matched Routes');
    }

    //-----------------------------------------------------------------------------------
    // Package and Request Handling Methods
    //-----------------------------------------------------------------------------------

    /**
     * Determine package and file URL from URL matches
     *
     * Extracts package name and file URL from URL matches.
     *
     * @param array $urlMatches URL matches
     * @param string $fileUrl Original file URL
     *
     * @return array Array containing [package, fileUrl, filePieces]
     */
    private function determinePackageAndFileUrl(array $urlMatches, string $fileUrl): array
    {
        $filePieces = $urlMatches;
        // In new resolver design, we don't check modules here. Just shift first segment as package.
        $package = array_shift($filePieces);
        $fileUrl = implode('/', $filePieces);

        return [$package, $fileUrl, $filePieces];
    }

    /**
     * Validate package name
     *
     * Ensures the package name is valid and returns the correct package name.
     *
     * @param string $package Package name to validate
     *
     * @return string Valid package name
     */
    private function validatePackage(string $package): string
    {
        // Without a persistent current module API, fall back to the resolved module name
        // when package validation cannot be performed here.
        if ($package === null || $package === '') {
            $resolver = NGS()->createDefinedInstance('MODULES_ROUTES_ENGINE', \ngs\routes\NgsModuleResolver::class);
            $requestContext = NGS()->createDefinedInstance('REQUEST_CONTEXT', \ngs\util\RequestContext::class);
            $currentModule = $resolver->resolveModule($requestContext->getRequestUri()) ?? NGS();
            return $currentModule->getName();
        }

        return $package;
    }


    private function getNotFoundRouteForRequest(NgsModule $module, string $package)
    {
        $routesDir = $this->getRoutesDir($module);
        $candidates = [
            $routesDir . '/' . $package . '.404.json',
            $routesDir . '/404.json'
        ];

        foreach ($candidates as $file) {
            if (file_exists($file)) {
                $data = json_decode(file_get_contents($file), true);
                if (is_array($data)) {
                    // If associative with 'request' or 'action'
                    if (isset($data['request']) && is_string($data['request'])) {
                        return $data['request'];
                    }
                    if (isset($data['action']) && is_string($data['action'])) {
                        return $data['action'];
                    }
                    // If list, take first element's request/action
                    if (isset($data[0]) && is_array($data[0])) {
                        if (isset($data[0]['request']) && is_string($data[0]['request'])) {
                            return $data[0]['request'];
                        }
                        if (isset($data[0]['action']) && is_string($data[0]['action'])) {
                            return $data[0]['action'];
                        }
                    }
                }
            }
        }

        return null;
    }

    //-----------------------------------------------------------------------------------
    // Route Config Management Methods
    //-----------------------------------------------------------------------------------

    /**
     * Get route configuration for the specified package
     *
     * Loads route configuration from JSON files and caches it.
     * If module routes are defined, they are merged with the main routes.
     *
     * @param string|null $package Package name, or null to use default
     *
     * @return array|null Route configuration array or null if not found
     */
    private function getRoutesConfig(\ngs\NgsModule $module, ?string $package = null): ?array
    {
        if (!$package) {
            $package = NGS()->get('NGS_ROUTS');
        }

        // Create cache key combining module name and package
        $cacheKey = $module->getName() . ':' . $package;

        // Return cached routes if available
        if (isset($this->routesConfigs[$cacheKey])) {
            return $this->routesConfigs[$cacheKey];
        }

        $routeFile = realpath($this->getRoutesDir($module) . '/' . $package . '.json');

        if (!$routeFile || !file_exists($routeFile)) {
            $routeFile = $this->getRoutesDir($module) . '/' . NGS()->get('NGS_ROUTS');
        }

        if (file_exists($routeFile)) {
            $this->routesConfigs[$cacheKey] = json_decode(file_get_contents($routeFile), true);
        } else {
            $this->routesConfigs[$cacheKey] = null;
        }

        return $this->routesConfigs[$cacheKey];
    }

    /**
     * Get routes directory path
     *
     * Returns the full path to the routes configuration directory.
     *
     * @return string Routes directory path
     */
    private function getRoutesDir(\ngs\NgsModule $module): string
    {
        return $module->getConfigDir() . '/routes';
    }

    /**
     * Get matched route configuration for a request identifier
     *
     * Finds the appropriate route configuration that matches the request identifier.
     * Handles exact matching, parameterized routes, and constraint validation.
     *
     * @param array $routesConfigArray Array of route configurations
     * @param string $requestIdentifier URL segment to match
     *
     * @return NgsRouteConfig Matched route configuration
     * @throws NotFoundException When no route matches
     */
    private function getMatchedRouteConfig(array $routesConfigArray, string $requestIdentifier): NgsRouteConfig
    {
        // Single-pass in-order matching: check each route as defined
        foreach ($routesConfigArray as $routeConfig) {
            $routePattern = $routeConfig['route'] ?? '';
            $constraints = $routeConfig['constraints'] ?? [];

            $hasParams = strpos($routePattern, ':') !== false || str_contains($routePattern, '[:') || str_contains($routePattern, '[/:');

            if ($hasParams) {
                $matchResult = $this->matchParameterizedRoutePattern($requestIdentifier, $routePattern, $constraints);
                if ($matchResult !== null) {
                    // Merge extracted named parameters into args
                    if (!empty($matchResult)) {
                        $existingArgs = $routeConfig['args'] ?? [];
                        $routeConfig['args'] = array_merge($existingArgs, $matchResult);
                    }
                    return NgsRouteConfig::fromArray($routeConfig);
                }
            } else {
                // Exact match (including possibility of empty string default)
                if ($routePattern === $requestIdentifier) {
                    return NgsRouteConfig::fromArray($routeConfig);
                }
            }
        }

        throw new NotFoundException('No matching route found for: ' . $requestIdentifier);
    }

    /**
     * Match a parameterized route pattern against a request identifier
     *
     * @param string $requestIdentifier The URL segment to match
     * @param string $routePattern The route pattern with parameters
     * @param array $constraints Parameter constraints (regex patterns)
     *
     * @return array|null Extracted parameters or null if no match
     */
    private function matchParameterizedRoutePattern(string $requestIdentifier, string $routePattern, array $constraints): ?array
    {
        // Convert route pattern to regex (uses default [^/]+ for params without constraints)
        $regexPattern = $this->convertRoutePatternToRegex($routePattern, $constraints);

        if (preg_match($regexPattern, $requestIdentifier, $matches)) {
            $extractedParams = [];
            // Collect all named capture groups from the match
            foreach ($matches as $key => $value) {
                if (is_string($key) && $value !== '') {
                    $extractedParams[$key] = $value;
                }
            }
            return $extractedParams;
        }

        return null;
    }

    /**
     * Convert route pattern with parameters to regex pattern
     *
     * @param string $routePattern Route pattern (e.g., "user/:id/order/:orderId")
     * @param array $constraints Parameter constraints
     *
     * @return string Regex pattern
     */
    private function convertRoutePatternToRegex(string $routePattern, array $constraints): string
    {
        $pattern = $routePattern;

        // Helper to get constraint or default
        $getConstraint = function (string $name) use ($constraints): string {
            return $constraints[$name] ?? '[^/]+'; // default if no constraint provided
        };

        // Replace optional parameters [/:param]
        if (preg_match_all('/\[\/:(\w+)\]/', $pattern, $m)) {
            foreach ($m[1] as $paramName) {
                $constraint = $getConstraint($paramName);
                $pattern = str_replace(
                    '[/:'.$paramName.']',
                    '(?:/(?<'.$paramName.'>'.$constraint.'))?',
                    $pattern
                );
            }
        }

        // Replace required parameters [:param]
        if (preg_match_all('/\[:(\w+)\]/', $pattern, $m)) {
            foreach ($m[1] as $paramName) {
                $constraint = $getConstraint($paramName);
                $pattern = str_replace(
                    '[:'.$paramName.']',
                    '(?<'.$paramName.'>'.$constraint.')',
                    $pattern
                );
            }
        }

        // Replace :param (not already replaced)
        if (preg_match_all('/:(\w+)/', $pattern, $m)) {
            foreach ($m[1] as $paramName) {
                // skip if already converted to named group
                if (strpos($pattern, '?<'.$paramName.'>') !== false) {
                    continue;
                }
                $constraint = $getConstraint($paramName);
                $pattern = preg_replace('/:'.$paramName.'\b/', '(?<'.$paramName.'>'.$constraint.')', $pattern);
            }
        }

        // Escape forward slashes for regex
        $pattern = str_replace('/', '\/', $pattern);

        // Return complete regex pattern
        return '/^' . $pattern . '$/';
    }

    //-----------------------------------------------------------------------------------
    // URL Parsing Methods
    //-----------------------------------------------------------------------------------

    /**
     * Extract URL segments from a URL string.
     *
     * @param string $url
     * @return array URL segments
     */
    private function getUrlSegments(string $url): array
    {
        // Normalize URL by removing leading slashes
        $normalizedUrl = ltrim($url, '/');
        // Split normalized URL into segments
        $segments = explode('/', $normalizedUrl);

        return $segments;
    }

    /**
     * Check if URL segments point to a non-PHP static file.
     *
     * @param array $segments
     * @return bool
     */
    private function isStaticFile(array $segments): bool
    {
        // Get the last segment from URL segments
        $lastSegment = end($segments);

        // Check for a file extension presence
        $hasExtension = $lastSegment && strrpos($lastSegment, '.') !== false;

        // Exclude PHP files for security reasons
        $isNonPhpFile = strpos($lastSegment, '.php') === false;

        return $hasExtension && $isNonPhpFile;
    }

    /**
     * In case if the module type is Path, we need to shift it out for further processing of URL
     * @param \ngs\NgsModule $module
     * @param array &$urlSegments
     * @return string|null The package name or 'default' if not present
     */
    private function checkAndShiftModuleName(\ngs\NgsModule $module, array &$urlSegments): ?string
    {
        if ($module->getType() === NgsModule::MODULE_TYPE_PATH) {
            return array_shift($urlSegments); // Remove module identifier for PATH type
        }

        return $module->getName();
    }

    /**
     * Extract the package name from URL segments.
     * This method modifies the segments array by reference to remove the package name.
     * @param array &$urlSegments
     * @return string|null The package name or 'default' if not present
     */
    private function getPackageName(array &$urlSegments): ?string
    {
        $package = array_shift($urlSegments) ?? null;

        if ($package === null || $package === '') {
            $package = self::DEFAULT_PACKAGE_IDENTIFIER;
        } else if (strpos($package, '_') !== false) {
            $package = str_replace('_', '.', $package);
        }

        return $package;
    }

    /**
     * Extract the request identifier from URL segments.
     * This method modifies the segments array by reference to remove the identifier.
     *
     * @param array &$urlSegments
     * @return string|null The request identifier or null if not present
     */
    private function getRequestIdentifier(array &$urlSegments): ?string
    {
        $requestIdentifier = array_shift($urlSegments);

        if ($requestIdentifier === null) {
            // Per docs, missing request maps to empty string (default route "")
            $requestIdentifier = '';
        }

        return $requestIdentifier;
    }

    /**
     * Retrieve remaining URL segments as arguments.
     * This method modifies the segments array by reference to ensure it is empty after extraction.
     *
     * @param array &$urlSegments
     * @return array The remaining URL segments as arguments
     */
    private function getArguments(array &$urlSegments): array
    {
        $args = $urlSegments;
        $urlSegments = []; // Clear segments array after extracting arguments
        return $args;
    }
}
