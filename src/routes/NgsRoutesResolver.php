<?php

namespace ngs\routes;

use ngs\exceptions\DebugException;
use ngs\exceptions\NotFoundException;
use ngs\NgsModule;
use ngs\request\AbstractAction;
use ngs\request\AbstractLoad;

/**
 * Class NgsRoutesResolver
 *
 * Resolves URLs to route data and provides per-group 404 info.
 * Always constructs NgsRoute via setters, never with arrays.
 *
 * @package ngs\routes
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
    protected array $routes = [];


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

        // Determine if URL points to a static file, checking here becuase after this we might break the segments
        $isStaticFile = $this->isStaticFile($urlSegments);

        $this->checkAndShiftModuleName($module, $urlSegments);

        // Extract package name based on segments and module type
        $package = $this->getPackageName($urlSegments);

        if ($package === NGS()->get('DYN_URL_TOKEN')) {
            $route = $this->handleDynamicUrlTokenRouting($module, $urlSegments);
        }
        else{
            $route = $this->handleWithRouteConfig($module, $package, $urlSegments);
        }

        if ($route->isMatched()) {
            $route = $this->processMatchedRequest($route);
        }

        if (!$route->isMatched() && $isStaticFile) {
            $route = $this->handleStaticFile($urlSegments, [], $fileUrl, $isStaticFile, $module);
            $package = $route->getModule();
        }

        // Set the module in the route
        $route->setModule($module);

        // Attach per-group notFoundRequest
        $routesConfig = $this->getRouteConfig($module);

        if (isset($routesConfig[$package]['404']['request'])) {
            $route->setNotFoundRequest($routesConfig[$package]['404']['request']);
        } elseif (isset($routesConfig[self::DEFAULT_REQUEST_IDENTIFIER]['404']['request'])) {
            $route->setNotFoundRequest($routesConfig[self::DEFAULT_REQUEST_IDENTIFIER]['404']['request']);
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
    private function handleDynamicUrlTokenRouting(\ngs\NgsModule $module, array $urlSegments): NgsRoute
    {
        $package = $this->getPackageName($urlSegments);

        $requestIdentifier = $this->getRequestIdentifier($urlSegments);
        $args = $this->getArguments($urlSegments);

        $route = $this->createRoute($module, $package, $requestIdentifier, $args);

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
     */
    private function handleWithRouteConfig(\ngs\NgsModule $module, string $package, array $segments): NgsRoute
    {
        // Extract request identifier
        $requestIdentifier = $this->getRequestIdentifier($segments);

        // Remaining segments are considered arguments
        $args = $this->getArguments($segments);

        $routes = $this->getRouteConfig($module, $package);

        $route = new NgsRoute();

        if ($routes === null) {
            // Not found, return empty NgsRoute (matched=false)
            $route->setMatched(false);
            return $route;
        }

        $matchedRoutesConfig = $this->getMatchedRoutesArray($routes, $requestIdentifier);
        [$foundRoute, $args, $isDynamicRoute] = $this->findMatchingRoute($matchedRoutesConfig, $segments);

        if ($args === null && !isset($foundRoute['request'])) {
           //TODO: ZN: handle this case
        }

        $args = $foundRoute['args'] ?? [];

        $requestName = $this->determineRequestName($module, $foundRoute);

        $route->setRequest($requestName);
        $route->setArgs($args);
        $route->setMatched(true);
        $route->setType($foundRoute['type'] ?? null);

        // Use the module instance if provided, otherwise use the module from the found route
        if ($module !== null) {
            $route->setModule($module);
        } else {
            $route->setModule($foundRoute['module'] ?? null);
        }

        $route->setFileType($foundRoute['fileType'] ?? null);
        $route->setFileUrl($foundRoute['fileUrl'] ?? null);

        return $route;
    }

    /**
     * Handle static file routing
     *
     * Creates a route for static files like CSS, JS, images, etc.
     *
     * @param array $parsed Parsed URL components
     *
     * @return NgsRoute The configured route object for the static file
     */
    private function handleStaticFile(array $parsed): NgsRoute
    {
        $fileUrl = ltrim($url, '/');
        $ngsRequestMatches = $parsed['ngsRequestMatches'];
        $segments = $parsed['segments'];
        $fileUrl = $parsed['fileUrl'];
        $module = $parsed['module'] ?? null;

        $route = new NgsRoute();

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

        $route = $this->checkSpecialFileTypesInRoute($route, $filePieces);

        return $route;
    }



    /**
     * Process a matched request
     *
     * Updates the route with additional information based on the request.
     *
     * @param NgsRoute $route The route to process
     *
     * @return NgsRoute The processed route
     */
    private function processMatchedRequest(NgsRoute $route): NgsRoute
    {
        $requestInfo = $this->getRequestInfoByRequest($route->getRequest());
        $route->setType($requestInfo['type'] ?? null);
        $route->setRequest($requestInfo['request'] ?? null);
        return $route;
    }

    /**
     * Get standard routes for a namespace and URL parts
     *
     * Creates a route based on standard routing conventions.
     *
     * @param string|null $routePackage Package of the request
     * @param array $urlSegments URL segments array
     *
     * @return NgsRoute The configured route object
     */
    private function createRoute(\ngs\NgsModule $module, string $routePackage, string $requestIdentifier, array $args): NgsRoute
    {
        $route = new NgsRoute();

        $requestPackage = NGS()->get('LOADS_DIR');
        $requestType = AbstractLoad::REQUEST_TYPE;

        if (strrpos($requestIdentifier, self::ACTIONS_PREFIX) !== false) {
            $requestPackage = NGS()->get('ACTIONS_DIR');
            $requestType = AbstractAction::REQUEST_TYPE;
        }

        $moduleName = $module->getName();

        $requestClassPath = $moduleName . '.' . $requestPackage . '.' . $routePackage . '.' . $requestIdentifier;

        $route->setRequest($requestClassPath);
        $route->setType($requestType);
        $route->setArgs($args);
        $route->setMatched(true);

        $notFoundRoute = $this->getNotFoundRouteForRequest();

        $route->setNotFoundRequest($notFoundRoute);

        return $route;
    }

    //-----------------------------------------------------------------------------------
    // Route Matching Methods
    //-----------------------------------------------------------------------------------

    /**
     * Get matched routes array for a package
     *
     * Retrieves the appropriate routes array based on the package name.
     *
     * @param array $routes Routes configuration
     * @param string $requestIdentifier Package name
     *
     * @return array Matched routes array
     */
    private function getMatchedRoutesArray(array $routes, ?string $requestIdentifier): array
    {
        if($requestIdentifier === null){
            $requestIdentifier = self::DEFAULT_REQUEST_IDENTIFIER;
        }

        if ($requestIdentifier === '404') {
            return [$routes[self::DEFAULT_REQUEST_IDENTIFIER][$requestIdentifier]];
        }
        if ($requestIdentifier === self::DEFAULT_REQUEST_IDENTIFIER) {
            return [[$requestIdentifier => $routes[$requestIdentifier]]];
        }
        return $routes[$requestIdentifier];
    }

    /**
     * Find a matching route in the routes array
     *
     * Iterates through the routes array to find a matching route.
     *
     * @param array $matchedRoutesArr Routes array to search in
     * @param array $urlPartsArr URL parts to match against
     *
     * @return array Array containing [foundRoute, args, isDynamicRoute]
     */
    private function findMatchingRoute(array $matchedRoutesArr, array $urlPartsArr): array
    {
        $isDynamicRoute = false;
        $args = null;
        $foundRoute = [];

        foreach ($matchedRoutesArr as $route) {
            $foundRoute = [];

            if (isset($route[self::DEFAULT_REQUEST_IDENTIFIER])) {
                $result = $this->processDefaultRoute($route);
                if ($result !== null) {
                    [$foundRoute, $routeIsDynamic, $shouldContinue] = $result;
                    $isDynamicRoute = $routeIsDynamic;
                    if ($shouldContinue) {
                        continue;
                    }
                    break;
                }
            }

            if (!$this->isHttpMethodMatching($route)) {
                continue;
            }

            $matchResult = $this->tryMatchRoute($route, $urlPartsArr);
            if ($matchResult['matched']) {
                $foundRoute = $matchResult['route'];
                $args = $matchResult['args'];
                break;
            }
        }

        return [$foundRoute, $args, $isDynamicRoute];
    }

    /**
     * Process default route
     *
     * Handles special cases for default routes.
     *
     * @param array $route Route configuration
     *
     * @return array|null Array containing [foundRoute, isDynamicRoute, shouldContinue] or null
     */
    private function processDefaultRoute(array $route): ?array
    {
        if ($route[self::DEFAULT_REQUEST_IDENTIFIER] === NGS()->get('DYN_URL_TOKEN')) {
            return [[], true, true];
        }
        if (isset($route[self::DEFAULT_REQUEST_IDENTIFIER]['request'], $route[self::DEFAULT_REQUEST_IDENTIFIER]['404']) && isset($_GET['is404']) && $_GET['is404'] === true) {
            return [$route[self::DEFAULT_REQUEST_IDENTIFIER]['404'], false, false];
        }
        return [$route[self::DEFAULT_REQUEST_IDENTIFIER], false, false];
    }

    /**
     * Check if HTTP method matches the route
     *
     * Verifies that the current HTTP method matches the method specified in the route.
     *
     * @param array $route Route configuration
     *
     * @return bool True if the method matches or no method is specified
     */
    private function isHttpMethodMatching(array $route): bool
    {
        if (!isset($route['method'])) {
            return true;
        }
        $requestContext = NGS()->createDefinedInstance('REQUEST_CONTEXT', \ngs\util\RequestContext::class);
        $requestMethod = $requestContext->getRequestHttpMethod();
        return strtolower($route['method']) === strtolower($requestMethod);
    }

    /**
     * Try to match a route against URL parts
     *
     * Attempts to match the given route against the URL parts.
     *
     * @param array $route Route configuration
     * @param array $urlPartsArr URL parts to match against
     *
     * @return array Match result with 'matched', 'route', and 'args' keys
     */
    private function tryMatchRoute(array $route, array $urlPartsArr): array
    {
        $foundRoute = $route;
        $args = $this->getMatchedRoute($urlPartsArr, $foundRoute);

        if (!isset($foundRoute['args'])) {
            $foundRoute['args'] = [];
        }

        if ($args !== null && is_array($args)) {
            $foundRoute['args'] = array_merge($foundRoute['args'], $args);
            return [
                'matched' => true,
                'route' => $foundRoute,
                'args' => $args
            ];
        }

        return [
            'matched' => false,
            'route' => [],
            'args' => null
        ];
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

    /**
     * Get matched route parameters from URI
     *
     * Determines if the route is simple or constrained and calls the appropriate matcher.
     *
     * @param array $uriParams URI parameters
     * @param array $routeArr Route configuration
     *
     * @return array|null Matched parameters or null if no match
     */
    private function getMatchedRoute(array $uriParams, array $routeArr): ?array
    {
        $route = $routeArr['route'] ?? '';

        if (strpos($route, '[:') === false && strpos($route, '[/:') === false) {
            return $this->matchSimpleRoute($uriParams, $route);
        }

        return $this->matchConstrainedRoute($uriParams, $routeArr);
    }

    /**
     * Match a simple route without constraints
     *
     * Matches a route that doesn't contain any parameter constraints.
     *
     * @param array $uriParams URI parameters
     * @param string $route Route pattern
     *
     * @return array|null Matched parameters or null if no match
     */
    private function matchSimpleRoute(array $uriParams, string $route): ?array
    {
        $fullUri = implode('/', $uriParams);

        if (isset($route[0]) && strpos($route, '/') === 0) {
            $route = substr($route, 1);
        }

        $routePattern = str_replace('/', '\/', $route) . '\/';

        $newUri = preg_replace('/^' . $routePattern . '$/', '', $fullUri . '/', -1, $count);

        if ($count === 0) {
            return null;
        }

        preg_match_all('/([^\/\?]+)/', $newUri, $matches);
        return $matches[1];
    }

    /**
     * Match a constrained route
     *
     * Matches a route that contains parameter constraints.
     *
     * @param array $uriParams URI parameters
     * @param array $routeArr Route configuration
     *
     * @return array|null Matched parameters or null if no match
     */
    private function matchConstrainedRoute(array $uriParams, array $routeArr): ?array
    {
        $routeUrlExp = $routeArr['route'];
        $originalUrl = '/' . implode('/', $uriParams);

        $routeUrlExp = $this->processConstraints($routeUrlExp, $routeArr, $routeArr['route']);
        $routeUrlExp = str_replace('/', '\/', $routeUrlExp);

        preg_match('/^\/' . trim($routeUrlExp, '\/') . '$/', $originalUrl, $matches);

        if (!$matches) {
            return null;
        }

        return $this->extractNamedParameters($matches, $routeArr['constraints']);
    }

    //-----------------------------------------------------------------------------------
    // Constraint Processing Methods
    //-----------------------------------------------------------------------------------

    /**
     * Process constraints in a route
     *
     * Replaces constraint placeholders with regex patterns.
     *
     * @param string $routeUrlExp Route URL expression
     * @param array $routeArr Route configuration
     * @param string $route Original route string
     *
     * @return string Processed route URL expression
     * @throws DebugException If constraints and route parameters don't match
     */
    private function processConstraints(string $routeUrlExp, array $routeArr, string $route): string
    {
        foreach ((array)$routeArr['constraints'] as $item => $constraint) {
            if (strpos($routeUrlExp, ':' . $item) === false) {
                throw new DebugException(
                    'Constraints and route parameters do not match. Please check in ' .
                    NGS()->get('NGS_ROUTS') . ' in this route section: ' . $route
                );
            }

            $routeUrlExp = $this->replaceConstraintPlaceholder($routeUrlExp, $item, $constraint);
        }

        return $routeUrlExp;
    }

    /**
     * Replace constraint placeholder with regex pattern
     *
     * Replaces a constraint placeholder with the appropriate regex pattern.
     *
     * @param string $routeUrlExp Route URL expression
     * @param string $item Constraint name
     * @param string $constraint Constraint pattern
     *
     * @return string Route URL expression with replaced placeholder
     */
    private function replaceConstraintPlaceholder(string $routeUrlExp, string $item, string $constraint): string
    {
        if (strpos($routeUrlExp, '/:' . $item) === false) {
            return str_replace(
                '[:' . $item . ']',
                '(?<' . $item . '>' . $constraint . ')',
                $routeUrlExp
            );
        } else {
            return str_replace(
                '[/:' . $item . ']',
                '/?(?<' . $item . '>' . $constraint . ')?',
                $routeUrlExp
            );
        }
    }

    /**
     * Extract named parameters from matches
     *
     * Extracts named parameters from regex matches based on constraints.
     *
     * @param array $matches Regex matches
     * @param array $constraints Constraints configuration
     *
     * @return array Named parameters
     */
    private function extractNamedParameters(array $matches, array $constraints): array
    {
        $urlMatchArgs = [];

        foreach ($constraints as $item => $constraint) {
            if (isset($matches[$item])) {
                $urlMatchArgs[$item] = $matches[$item];
            }
        }

        return $urlMatchArgs;
    }

    //-----------------------------------------------------------------------------------
    // Package and Request Handling Methods
    //-----------------------------------------------------------------------------------

    /**
     * Get request information by request string
     *
     * Parses a request string into module, type, and class name.
     *
     * @param string|null $request Request string
     *
     * @return array|null Request information or null if request is empty
     */
    private function getRequestInfoByRequest(?string $request = null): ?array
    {
        if (!$request) {
            return null;
        }
        $pathArr = explode('.', $request);
        $requestName = array_splice($pathArr, count($pathArr) - 1);
        $requestName = $requestName[0];
        $module = array_splice($pathArr, 0, 1);
        $module = $module[0];
        $requestType = '';
        $classPrefix = '';

        foreach ($pathArr as $v) {
            if ($v === NGS()->get('ACTIONS_DIR')) {
                $requestType = 'action';
                $classPrefix = 'Action';
                break;
            }
            if ($v === NGS()->get('LOADS_DIR')) {
                $requestType = 'load';
                $classPrefix = 'Load';
                break;
            }
        }
        if (strrpos($requestName, 'do_') !== false) {
            $requestName = str_replace('do_', '', $requestName);
        }
        $requestName = preg_replace_callback('/_(\w)/', function ($m) {
                return strtoupper($m[1]);
            }, ucfirst($requestName)) . $classPrefix;
        return [
            'request' => $module . '\\' . implode('\\', $pathArr) . '\\' . $requestName,
            'type' => $requestType
        ];
    }

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
        $moduleRoutesEngine = NGS()->createDefinedInstance('MODULES_ROUTES_ENGINE', \ngs\routes\NgsModuleResolver::class);

        if ($moduleRoutesEngine->checkModuleByName($filePieces[0])) {
            $package = array_shift($filePieces);
            $fileUrl = implode('/', $filePieces);
        } else {
            $package = array_shift($filePieces);
        }

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
        $moduleRoutesEngine = NGS()->createDefinedInstance('MODULES_ROUTES_ENGINE', \ngs\routes\NgsModuleResolver::class);

        if (!$moduleRoutesEngine->checkModuleByName($package) ||
            $moduleRoutesEngine->getModuleType() === 'path') {
            return $moduleRoutesEngine->getModuleName();
        }

        return $package;
    }

    /**
     * Check for special file types in route
     *
     * Checks if the route contains special file types like less or sass.
     *
     * @param NgsRoute $route Route object
     * @param array $filePieces File path pieces
     *
     * @return NgsRoute Updated route object
     */
    private function checkSpecialFileTypesInRoute(NgsRoute $route, array $filePieces): NgsRoute
    {
        if ($route->getFileType() === 'css') {
            foreach ($filePieces as $urlPath) {
                if ($urlPath === 'less' || $urlPath === 'sass') {
                    $route->setFileType($urlPath);
                    break;
                }
            }
        }

        return $route;
    }

    /**
     * Determine request name from found route
     *
     * Constructs the full request name from the namespace and action.
     *
     * @param array $foundRoute Found route configuration
     *
     * @return string Full request name
     */
    private function determineRequestName(\ngs\NgsModule $module, array $foundRoute): string
    {
        $requestNS = $this->determineRequestNamespace($module);

        $requestName = $requestNS . '.' . $foundRoute['action'];

        return $requestName;
    }

    /**
     * Determine request namespace from found route
     *
     * Extracts the namespace part from the action or uses the default namespace.
     *
     * @param array $foundRoute Found route configuration
     *
     * @return string Request namespace
     */
    private function determineRequestNamespace(\ngs\NgsModule $module): string
    {
        return $module->getName();
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
     * Match a route against URL and routes configuration
     *
     * This method is designed for testing purposes. It takes a URL string and routes array
     * and returns matching route information with extracted parameters.
     *
     * @param string $url The URL to match
     * @param array $routes Routes configuration array
     *
     * @return array|null Array with 'route' and 'params' keys if matched, null otherwise
     */
    public function matchRoute(string $url, array $routes): ?array
    {
        // Handle empty URL as default route
        if ($url === '' && isset($routes['default'])) {
            return [
                'route' => $routes['default'],
                'params' => []
            ];
        }

        // Convert URL to segments
        $urlSegments = $this->getUrlSegments($url);

        // Try to match each route
        foreach ($routes as $routeName => $routeConfig) {
            // Skip default route if URL is not empty
            if ($routeName === 'default' && $url !== '') {
                continue;
            }

            // Handle routes without explicit route pattern (exact match)
            if (!isset($routeConfig['route'])) {
                if ($url === '') {
                    return [
                        'route' => $routeConfig,
                        'params' => []
                    ];
                }
                continue;
            }

            $routePattern = $routeConfig['route'];

            // Handle exact match
            if ($routePattern === $url) {
                return [
                    'route' => $routeConfig,
                    'params' => []
                ];
            }

            // Handle parameterized routes
            if (strpos($routePattern, '[:') !== false || strpos($routePattern, '[/:') !== false) {
                $matchResult = $this->matchParameterizedRoute($url, $routeConfig);
                if ($matchResult !== null) {
                    return $matchResult;
                }
            }
        }

        return null;
    }

    /**
     * Match a parameterized route
     *
     * @param string $url The URL to match
     * @param array $routeConfig Route configuration
     *
     * @return array|null Match result or null
     */
    private function matchParameterizedRoute(string $url, array $routeConfig): ?array
    {
        $routePattern = $routeConfig['route'];
        $constraints = $routeConfig['constraints'] ?? [];

        // Process the route pattern to create regex
        $regexPattern = $this->createRegexFromRoute($routePattern, $constraints);

        // Try to match the URL
        if (preg_match($regexPattern, $url, $matches)) {
            // Extract named parameters
            $params = [];
            foreach ($constraints as $paramName => $constraint) {
                if (isset($matches[$paramName]) && $matches[$paramName] !== '') {
                    $params[$paramName] = $matches[$paramName];
                }
            }

            return [
                'route' => $routeConfig,
                'params' => $params
            ];
        }

        return null;
    }

    /**
     * Create regex pattern from route with parameters
     *
     * @param string $routePattern Route pattern
     * @param array $constraints Parameter constraints
     *
     * @return string Regex pattern
     */
    private function createRegexFromRoute(string $routePattern, array $constraints): string
    {
        $pattern = $routePattern;

        // Replace required parameters [:param]
        foreach ($constraints as $paramName => $constraint) {
            if (strpos($pattern, '[:' . $paramName . ']') !== false) {
                $pattern = str_replace(
                    '[:' . $paramName . ']',
                    '(?<' . $paramName . '>' . $constraint . ')',
                    $pattern
                );
            }
        }

        // Replace optional parameters [/:param]
        foreach ($constraints as $paramName => $constraint) {
            if (strpos($pattern, '[/:' . $paramName . ']') !== false) {
                $pattern = str_replace(
                    '[/:' . $paramName . ']',
                    '(?:/(?<' . $paramName . '>' . $constraint . '))?',
                    $pattern
                );
            }
        }

        // Escape forward slashes for regex
        $pattern = str_replace('/', '\/', $pattern);

        return '/^' . $pattern . '$/';
    }

    private function getNotFoundRouteForRequest(NgsModule $module, string $package){
        $routeConfig = $this->getRouteConfig($module, $package);


    }

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
    private function getRouteConfig(\ngs\NgsModule $module, ?string $package = null): ?array
    {
        if (!$package) {
            $package = NGS()->get('NGS_ROUTS');
        }

        // Create cache key combining module name and package
        $cacheKey = $module->getName() . ':' . $package;

        // Return cached routes if available
        if (isset($this->routes[$cacheKey])) {
            return $this->routes[$cacheKey];
        }

        $routeFile = realpath($this->getRoutesDir($module) . '/' . $package . '.json');

        if (!$routeFile || !file_exists($routeFile)) {
            $routeFile = $this->getRoutesDir($module) . '/' . NGS()->get('NGS_ROUTS');
        }

        if (file_exists($routeFile)) {
            $this->routes[$cacheKey] = json_decode(file_get_contents($routeFile), true);
        } else {
            $this->routes[$cacheKey] = null;
        }

        return $this->routes[$cacheKey];
    }


    //-----------------------------------------------------------------------------------
    // URL Parsing and Normalization Methods
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
        }
        else if (strpos($package, '_') !== false) {
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
            $requestIdentifier = self::DEFAULT_REQUEST_IDENTIFIER;
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
