<?php

namespace ngs\routes;

use ngs\exceptions\DebugException;
use ngs\exceptions\NotFoundException;

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
     * @var array|null Routes configuration
     */
    protected ?array $routes = null;

    /**
     * @var string|null Current package
     */
    private ?string $package = null;

    /**
     * @var array|null Nested routes
     */
    private ?array $nestedRoutes = null;

    /**
     * @var string|null Content load
     */
    private ?string $contentLoad = null;

    /**
     * @var string Dynamic URL token
     */
    private string $dynUrlToken = 'dyn';

    /**
     * @var NgsRoute|null Current route
     */
    private ?NgsRoute $currentRoute = null;

    /**
     * Get route for the given URL
     *
     * This is the main entry point for the router. It processes the URL and returns
     * a route object with all the necessary information for dispatching.
     *
     * @param string $url The URL to route
     * 
     * @return NgsRoute|null The route object or null if no route was found
     */
    public function getRoute(string $url): ?NgsRoute
    {
        $url = $this->normalizeUrl($url);
        $parsed = $this->parseUrl($url);
        $ngsRequestMatches = $parsed['ngsRequestMatches'];
        $package = $parsed['package'];
        $isStaticFile = $parsed['isStaticFile'];

        if ($package === $this->dynUrlToken) {
            $route = $this->handleDynamicUrlTokenRouting($ngsRequestMatches);
        } else {
            if ($package === null) {
                $package = 'default';
            }
            $route = $this->getDynamicRoute($parsed);
        }

        if ($route->isMatched()) {
            $route = $this->processMatchedRequest($route);
        }

        if (!$route->isMatched() && $isStaticFile) {
            $route = $this->handleStaticFile($parsed);
            $package = $route->getModule();
        }

        // Attach per-group notFoundRequest as a string (request)
        $routesConfig = $this->getRouteConfig($package);
        if (isset($routesConfig[$package]['404']['request'])) {
            $route->setNotFoundRequest($routesConfig[$package]['404']['request']);
        } elseif (isset($routesConfig['default']['404']['request'])) {
            $route->setNotFoundRequest($routesConfig['default']['404']['request']);
        }

        return $route;
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
    protected function getRouteConfig(?string $package = null): ?array
    {
        if (!$package) {
            $package = NGS()->get('NGS_ROUTS');
        }
        if (isset($this->routes[$package]) && $this->routes !== null) {
            return $this->routes;
        }
        $routeFile = realpath($this->getRoutesDir() . '/' . $package . '.json');
        if (!$routeFile || !file_exists($routeFile)) {
            $routeFile = $this->getRoutesDir() . '/' . NGS()->get('NGS_ROUTS');
        }
        if (file_exists($routeFile)) {
            $this->routes = json_decode(file_get_contents($routeFile), true);
            if (NGS()->get('NGS_MODULE_ROUTS')) {
                $moduleRouteFile = NGS()->getConfigDir() . '/' . NGS()->get('NGS_MODULE_ROUTS');
                $moduleRoutes = json_decode(file_get_contents($moduleRouteFile), true);
                $this->routes = array_merge($this->routes, $moduleRoutes);
            }
        }
        return $this->routes;
    }

    //-----------------------------------------------------------------------------------
    // URL Parsing and Normalization Methods
    //-----------------------------------------------------------------------------------

    /**
     * Normalize URL by removing leading slash if present
     *
     * @param string $url The URL to normalize
     * 
     * @return string Normalized URL
     */
    private function normalizeUrl(string $url): string
    {
        if (!empty($url) && $url[0] === '/') {
            return substr($url, 1);
        }
        return $url;
    }

    /**
     * Parse URL into components needed for routing
     *
     * Breaks down the URL into segments and extracts important information
     * such as package name, whether it's a static file, etc.
     *
     * @param string $url The URL to parse
     * 
     * @return array Parsed URL components
     */
    private function parseUrl(string $url): array
    {
        $segments = explode('/', $url);
        if (!empty($segments) && $segments[0] === '') {
            array_shift($segments);
        }

        $ngsRequestMatches = $segments;

        $isStaticFile = false;
        $package = '';
        $fileUrl = '';

        $package = array_shift($ngsRequestMatches) ?? '';
        $fileUrl = ltrim($url, '/');

        $lastSegment = end($segments);
        if ($lastSegment !== false && strrpos($lastSegment, '.') !== false) {
            $isStaticFile = (strpos($lastSegment, '.php') === false);
        }

        return [
            'segments'          => $segments,
            'ngsRequestMatches' => $ngsRequestMatches,
            'package'           => $package,
            'fileUrl'           => $fileUrl,
            'isStaticFile'      => $isStaticFile,
        ];
    }

    //-----------------------------------------------------------------------------------
    // Route Handling Methods
    //-----------------------------------------------------------------------------------

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
    private function getDynamicRoute(array $parsed): NgsRoute
    {
        $package     = $parsed['package'];
        $urlPartsArr = $parsed['ngsRequestMatches'];
        $isStaticFile= $parsed['isStaticFile'];

        $routes = $this->getRouteConfig($package);

        $route = new NgsRoute();

        if ($routes === null || !isset($routes[$package])) {
            // Not found, return empty NgsRoute (matched=false)
            $route->setMatched(false);
            return $route;
        }

        $matchedRoutesArr = $this->getMatchedRoutesArray($routes, $package);

        [$foundRoute, $args, $isDynamicRoute] = $this->findMatchingRoute($matchedRoutesArr, $urlPartsArr);

        if ($args === null && !isset($foundRoute['request'])) {
            return $this->handleNoMatchingRoute($isDynamicRoute, $package, $urlPartsArr, $isStaticFile);
        }

        $args = $foundRoute['args'] ?? [];

        $requestName = $this->determineRequestName($foundRoute);

        $route->setRequest($requestName);
        $route->setArgs($args);
        $route->setMatched(true);
        $route->setType($foundRoute['type'] ?? null);
        $route->setModule($foundRoute['module'] ?? null);
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
        $ngsRequestMatches = $parsed['ngsRequestMatches'];
        $segments          = $parsed['segments'];
        $fileUrl           = $parsed['fileUrl'];

        $route = new NgsRoute();

        [$package, $fileUrl, $filePieces] = $this->determinePackageAndFileUrl($ngsRequestMatches, $fileUrl);

        $package = $this->validatePackage($package);

        $route->setType('file');
        $route->setFileType(pathinfo(end($segments), PATHINFO_EXTENSION));
        $route->setMatched(true);
        $route->setModule($package);
        $route->setFileUrl($fileUrl);

        $route = $this->checkSpecialFileTypesInRoute($route, $filePieces);

        return $route;
    }

    /**
     * Handle dynamic URL token routing
     *
     * Processes URLs that start with the dynamic URL token.
     *
     * @param array $urlPartsArr URL parts array
     * 
     * @return NgsRoute The configured route object
     */
    private function handleDynamicUrlTokenRouting(array $urlPartsArr): NgsRoute
    {
        $package = array_shift($urlPartsArr);

        if ($package === NGS()->createDefinedInstance('MODULES_ROUTES_ENGINE', \ngs\routes\NgsModuleResolver::class)->getModuleName()) {
            $package = array_shift($urlPartsArr);
        }

        return $this->getStandardRoutes($package, $urlPartsArr);
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
     * @param string|null $ns Namespace
     * @param array $urlPartsArr URL parts array
     * 
     * @return NgsRoute The configured route object
     */
    private function getStandardRoutes(?string $ns, array $urlPartsArr): NgsRoute
    {
        $route = new NgsRoute();

        $command = array_shift($urlPartsArr);
        if ($command === null) {
            $command = 'default';
        }

        if ($ns !== null && strpos($ns, '_') !== false) {
            $ns = str_replace('_', '.', $ns);
        }

        $module = NGS()->createDefinedInstance('MODULES_ROUTES_ENGINE', \ngs\routes\NgsModuleResolver::class)->getModuleName();
        $requestPackage = NGS()->getLoadsPackage();

        if (strrpos($command, 'do_') !== false) {
            $requestPackage = NGS()->getActionPackage();
        }

        $request = $module . '.' . $requestPackage . '.';
        if ($ns) {
            $request .= $ns . '.';
        }
        $request .= $command;

        $route->setRequest($request);
        $route->setArgs($urlPartsArr);
        $route->setMatched(true);

        return $route;
    }

    /**
     * Handle the case when no matching route is found
     *
     * Determines what to do when no route matches the URL.
     *
     * @param bool $dynRoute Whether this is a dynamic route
     * @param string $package Package name
     * @param array $urlPartsArr URL parts array
     * @param bool $staticFile Whether this is a static file
     * 
     * @return NgsRoute The configured route object
     * @throws NotFoundException If no route is found and not in development mode
     */
    private function handleNoMatchingRoute(bool $dynRoute, string $package, array $urlPartsArr, bool $staticFile): NgsRoute
    {
        $route = new NgsRoute();

        if ($dynRoute === true) {
            return $this->getStandardRoutes($package, $urlPartsArr);
        }
        if ($staticFile) {
            $route->setMatched(false);
            return $route;
        }
        if (NGS()->getEnvironment() === 'development') {
            $this->onNoMatchedRoutes();
        }
        throw new NotFoundException();
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
     * @param string $package Package name
     * 
     * @return array Matched routes array
     */
    private function getMatchedRoutesArray(array $routes, string $package): array
    {
        if ($package === '404') {
            return [$routes['default'][$package]];
        }
        if ($package === 'default') {
            return [[$package => $routes[$package]]];
        }
        return $routes[$package];
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

            if (isset($route['default'])) {
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
        if ($route['default'] === 'dyn') {
            return [[], true, true];
        }
        if (isset($route['default']['request'], $route['default']['404']) && isset($_GET['is404']) && $_GET['is404'] === true) {
            return [$route['default']['404'], false, false];
        }
        return [$route['default'], false, false];
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
            if ($v === NGS()->getActionPackage()) {
                $requestType = 'action';
                $classPrefix = 'Action';
                break;
            }
            if ($v === NGS()->getLoadsPackage()) {
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
    private function determineRequestName(array $foundRoute): string
    {
        $requestNS = $this->determineRequestNamespace($foundRoute);

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
    private function determineRequestNamespace(array $foundRoute): string
    {
        $requestType = substr($foundRoute['action'], 0, strpos($foundRoute['action'], '.'));
        $moduleRoutesEngine = NGS()->createDefinedInstance('MODULES_ROUTES_ENGINE', \ngs\routes\NgsModuleResolver::class);

        if ($moduleRoutesEngine->checkModuleByName($requestType)) {
            $requestNS = $requestType;
            //TODO: ZN: should be refactored
            $foundRoute['action'] = substr($foundRoute['action'], strpos($foundRoute['action'], '.') + 1);
        } elseif (isset($foundRoute['namespace'])) {
            $requestNS = $foundRoute['namespace'];
        } else {
            $requestNS = $moduleRoutesEngine->getModuleName();
        }

        return $requestNS;
    }

    /**
     * Get routes directory path
     *
     * Returns the full path to the routes configuration directory.
     *
     * @return string Routes directory path
     */
    private function getRoutesDir(): string
    {
        return NGS()->getConfigDir() . '/routes';
    }

}
