<?php
/**
 * Default NGS routing class
 * This class is used by the dispatcher for matching URLs with routes
 *
 * @author Levon Naghashyan <levon@naghashyan.com>
 * @site https://naghashyan.com
 * @year 2014-2023
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
use ngs\exceptions\NotFoundException;

/**
 * Class NgsRoutes - Handles routing in the NGS framework
 * 
 * @package ngs\routes
 */
class NgsRoutes
{
    /**
     * Cached routes configuration
     */
    protected ?array $routes = null;

    /**
     * Current package name
     */
    private ?string $package = null;

    /**
     * Nested routes configuration
     */
    private ?array $nestedRoutes = null;

    /**
     * Current content load
     */
    private ?string $contentLoad = null;

    /**
     * Dynamic container name
     */
    private string $dynContainer = 'dyn';

    /**
     * Current route configuration
     */
    private ?array $currentRoute = null;

    /**
     * File system interface for accessing route files
     */
    private $fileSystem;

    /**
     * Module routes engine
     */
    private $moduleRoutesEngine;

    /**
     * HTTP utilities
     */
    private $httpUtils;

    /**
     * Constructor
     * 
     * @param object|null $fileSystem File system interface
     * @param object|null $moduleRoutesEngine Module routes engine
     * @param object|null $httpUtils HTTP utilities
     */
    public function __construct($fileSystem = null, $moduleRoutesEngine = null, $httpUtils = null)
    {
        $this->fileSystem = $fileSystem ?? NGS();
        $this->moduleRoutesEngine = $moduleRoutesEngine ?? NGS()->getModulesRoutesEngine();
        $this->httpUtils = $httpUtils ?? NGS()->getHttpUtils();
    }

    /**
     * Returns the dynamic container name
     * This method can be overridden by users if they don't want to use 'dyn' container
     * but this may cause conflicts with routes
     *
     * @return string Dynamic container name
     */
    protected function getDynContainer(): string
    {
        return $this->dynContainer;
    }

    /**
     * Reads routes configuration from JSON file and caches it
     *
     * @param string|null $package Package name
     * @return array|null Routes configuration
     */
    protected function getRouteConfig(?string $package = null): ?array
    {
        if (!$package) {
            $package = $this->fileSystem->get('NGS_ROUTS');
        }

        if (isset($this->routes[$package]) && $this->routes !== null) {
            return $this->routes;
        }

        $routFile = realpath($this->fileSystem->getRoutesDir() . '/' . $package . '.json');

        if (!$routFile || !file_exists($routFile)) {
            $routFile = $this->fileSystem->getRoutesDir() . '/' . $this->fileSystem->get('NGS_ROUTS');
        }

        if (file_exists($routFile)) {
            $this->routes = json_decode(file_get_contents($routFile), true);

            if ($this->fileSystem->get('NGS_MODULE_ROUTS')) {
                $moduleRoutFile = $this->fileSystem->getConfigDir() . '/' . $this->fileSystem->get('NGS_MODULE_ROUTS');
                $moduleRoutes = json_decode(file_get_contents($moduleRoutFile), true);
                $this->routes = array_merge($this->routes, $moduleRoutes);
            }
        }

        return $this->routes;
    }

    /**
     * Sets the current package name
     *
     * @param string $package Package name
     * @return void
     */
    private function setPackage(string $package): void
    {
        $this->package = $package;
    }

    /**
     * Returns the current package name
     *
     * @return string|null Package name
     */
    public function getPackage(): ?string
    {
        return $this->package;
    }

    /**
     * Processes a URL and returns the corresponding route information
     * 
     * This method analyzes the URL to determine the appropriate route handling:
     * - For dynamic container URLs, it uses standard routing
     * - For other URLs, it uses the routes configuration file
     * - For static files, it handles them separately
     *
     * @param string $url The URL to process
     * @param bool $is404 Whether this is a 404 error handling request
     * @return array|null Route information array or null if no route matched
     * @throws DebugException When a debug error occurs
     * @throws NotFoundException When no matching route is found
     */
    public function getDynamicLoad(string $url, bool $is404 = false): ?array
    {
        $loadsArr = ['matched' => false];

        // Normalize URL
        $url = $this->normalizeUrl($url);

        // Parse URL into components
        [$urlMatches, $matches, $package, $fileUrl, $staticFile] = $this->parseUrl($url, $is404);

        // Get URL parts array for routing
        $urlPartsArr = $matches;

        // Process the route based on package type
        if ($package === $this->getDynContainer()) {
            // Handle dynamic container routing
            $loadsArr = $this->handleDynamicContainerRouting($urlPartsArr);
        } else {
            // Handle regular routing
            if ($package === null) {
                $package = 'default';
            }
            $loadsArr = $this->getDynRoutesLoad($url, $package, $urlPartsArr, $is404, $staticFile);
        }

        // Process action type if route matched
        if ($loadsArr['matched']) {
            $loadsArr = $this->processMatchedAction($loadsArr);
        }

        // Handle static file if no route matched
        if ($loadsArr['matched'] === false && $staticFile === true) {
            $loadsArr = $this->handleStaticFile($matches, $urlMatches, $fileUrl);
            $package = $loadsArr['module'];
        }

        $this->setPackage($package);

        return $loadsArr;
    }

    /**
     * Normalizes a URL by removing leading slash
     *
     * @param string $url URL to normalize
     * @return string Normalized URL
     */
    private function normalizeUrl(string $url): string
    {
        if ($url[0] === '/') {
            return substr($url, 1);
        }
        return $url;
    }

    /**
     * Parses a URL into its components
     *
     * @param string $url URL to parse
     * @param bool $is404 Whether this is a 404 error handling request
     * @return array Array containing [urlMatches, matches, package, fileUrl, staticFile]
     */
    private function parseUrl(string $url, bool $is404): array
    {
        $urlMatches = explode('/', $url);
        if ($urlMatches && $urlMatches[0] === '') {
            unset($urlMatches[0]);
        }

        $matches = $urlMatches;
        $staticFile = false;
        $package = '';
        $fileUrl = '';

        if (!$is404) {
            $package = array_shift($matches);
            $fileUrl = $url;
            if (strpos($fileUrl, '/') === 0) {
                $fileUrl = substr($fileUrl, 1);
            }
        } else {
            $package = '404';
        }

        // Check if this is a static file
        if ((strrpos(end($urlMatches), '.')) !== false) {
            $staticFile = true;
            if (strpos(end($urlMatches), '.php')) {
                $staticFile = false;
            }
        }

        return [$urlMatches, $matches, $package, $fileUrl, $staticFile];
    }

    /**
     * Handles routing for URLs with dynamic container
     *
     * @param array $urlPartsArr URL parts array
     * @return array Route information
     */
    private function handleDynamicContainerRouting(array $urlPartsArr): array
    {
        $package = array_shift($urlPartsArr);

        if ($package === $this->moduleRoutesEngine->getModuleNS()) {
            $package = array_shift($urlPartsArr);
        }

        return $this->getStandardRoutes($package, $urlPartsArr);
    }

    /**
     * Processes a matched action to determine its type
     *
     * @param array $loadsArr Route information array
     * @return array Updated route information with action type
     */
    private function processMatchedAction(array $loadsArr): array
    {
        $actionArr = $this->getLoadORActionByAction($loadsArr['action']);
        $loadsArr['type'] = $actionArr['type'];
        $loadsArr['action'] = $actionArr['action'];

        return $loadsArr;
    }

    /**
     * Handles static file routing
     *
     * @param array $matches Matches array
     * @param array $urlMatches URL matches array
     * @param string $fileUrl File URL
     * @return array Static file route information
     */
    private function handleStaticFile(array $matches, array $urlMatches, string $fileUrl): array
    {
        if ($urlMatches[0] == strtolower($this->moduleRoutesEngine->getDefaultNS())) {
            array_shift($urlMatches);
            $fileUrl = substr($fileUrl, strpos($fileUrl, '/') + 1);
        }

        return $this->getStaticFileRoute($matches, $urlMatches, $fileUrl);
    }

    /**
     * Converts a dot-notation action string to a fully qualified class name and determines its type
     * 
     * This method parses an action string like "module.loads.package.action" and converts it to
     * a fully qualified class name like "module\loads\package\ActionLoad" or "module\actions\package\ActionAction".
     *
     * @param string|null $action Action string in dot notation
     * @return array|null Array containing the fully qualified class name and action type, or null if action is null
     */
    public function getLoadORActionByAction(?string $action = null): ?array
    {
        if (!$action) {
            return null;
        }

        // Parse the action string
        $actionParts = $this->parseActionString($action);

        // Determine action type and class prefix
        $typeInfo = $this->determineActionType($actionParts['pathArr']);

        // Format the action name
        $formattedAction = $this->formatActionName($actionParts['action'], $typeInfo['classPrefix']);

        // Build the fully qualified class name
        $fqcn = $this->buildFullyQualifiedClassName(
            $actionParts['module'], 
            $actionParts['pathArr'], 
            $formattedAction
        );

        return ['action' => $fqcn, 'type' => $typeInfo['actionType']];
    }

    /**
     * Parses an action string into its components
     *
     * @param string $action Action string in dot notation
     * @return array Array containing module, action, and path array
     */
    private function parseActionString(string $action): array
    {
        $pathArr = explode('.', $action);

        // Extract action (last part)
        $actionName = array_splice($pathArr, count($pathArr) - 1);
        $actionName = $actionName[0];

        // Extract module (first part)
        $module = array_splice($pathArr, 0, 1);
        $module = $module[0];

        return [
            'module' => $module,
            'action' => $actionName,
            'pathArr' => $pathArr
        ];
    }

    /**
     * Determines the action type and class prefix based on the path array
     *
     * @param array $pathArr Path array
     * @return array Array containing actionType and classPrefix
     */
    private function determineActionType(array $pathArr): array
    {
        $actionType = '';
        $classPrefix = '';

        foreach ($pathArr as $part) {
            if ($part === $this->fileSystem->getActionPackage()) {
                $actionType = 'action';
                $classPrefix = 'Action';
                break;
            }

            if ($part === $this->fileSystem->getLoadsPackage()) {
                $actionType = 'load';
                $classPrefix = 'Load';
                break;
            }
        }

        return [
            'actionType' => $actionType,
            'classPrefix' => $classPrefix
        ];
    }

    /**
     * Formats an action name according to naming conventions
     *
     * @param string $action Action name
     * @param string $classPrefix Class prefix (Load or Action)
     * @return string Formatted action name
     */
    private function formatActionName(string $action, string $classPrefix): string
    {
        // Remove 'do_' prefix if present
        if (strpos($action, 'do_') === 0) {
            $action = substr($action, 3);
        }

        // Convert snake_case to CamelCase
        $action = preg_replace_callback('/_(\w)/', function ($matches) {
            return strtoupper($matches[1]);
        }, ucfirst($action));

        // Add class prefix
        return $action . $classPrefix;
    }

    /**
     * Builds a fully qualified class name from components
     *
     * @param string $module Module name
     * @param array $pathArr Path array
     * @param string $action Formatted action name
     * @return string Fully qualified class name
     */
    private function buildFullyQualifiedClassName(string $module, array $pathArr, string $action): string
    {
        return $module . '\\' . implode('\\', $pathArr) . '\\' . $action;
    }

    /**
     * returns matched route data
     *
     * @param string $action
     * @param array $route
     * @return array
     */
    protected function getMatchedRouteData(string $action, array $route): array
    {
        return [
          'action' => $action,
          'args' => $route['args'],
          'matched' => true
        ];
    }

    /**
     * Handles standard NGS routing
     * 
     * In standard routing:
     * - First URL part is used for package
     * - Second part for command
     * - Other parts for arguments
     *
     * @param string|null $ns Namespace
     * @param array $urlPartsArr URL parts array
     * @return array Route information
     */
    private function getStandardRoutes(?string $ns, array $urlPartsArr): array
    {
        // Extract command from URL parts
        $command = array_shift($urlPartsArr);
        if ($command === null) {
            $command = 'default';
        }

        // Convert underscores to dots in namespace
        if ($ns !== null && strpos($ns, '_') !== false) {
            $ns = str_replace('_', '.', $ns);
        }

        // Get module namespace and action package
        $module = $this->moduleRoutesEngine->getModuleNS();
        $actionPackage = $this->fileSystem->getLoadsPackage();

        // Check if this is an action command
        if (strrpos($command, 'do_') !== false) {
            $actionPackage = $this->fileSystem->getActionPackage();
        }

        // Build the action string
        $action = $module . '.' . $actionPackage . '.';
        if ($ns) {
            $action .= $ns . '.';
        }
        $action .= $command;

        // Set content load and return route information
        $this->setContentLoad($action);
        return [
            'action' => $action, 
            'args' => $urlPartsArr, 
            'matched' => true
        ];
    }

    /**
     * Handles dynamic routing using routes configuration file
     * 
     * This method matches the URL against routes defined in the configuration file.
     * The first part of the URL is used as a key to match against the routes array.
     *
     * @param string $url The URL to process
     * @param string $package The package name
     * @param array $urlPartsArr URL parts array
     * @param bool $is404 Whether this is a 404 error handling request
     * @param bool $staticFile Whether this is a static file request
     * @return array Route information
     * @throws DebugException When a debug error occurs
     * @throws NotFoundException When no matching route is found
     */
    private function getDynRoutesLoad(string $url, string $package, array $urlPartsArr, bool $is404 = false, bool $staticFile = false): array
    {
        // Get routes configuration
        $routes = $this->getRouteConfig($package);

        // Check if package exists in routes
        if (!isset($routes[$package])) {
            return $this->handleNonExistentPackage($routes, $package, $is404);
        }

        // Get matched routes array
        $matchedRoutesArr = $this->getMatchedRoutesArray($routes, $package);

        // Find matching route
        [$foundRoute, $args, $dynRoute] = $this->findMatchingRoute($matchedRoutesArr, $urlPartsArr);

        // Handle case when no matching route is found
        if ($args === null && !isset($foundRoute['action'])) {
            return $this->handleNoMatchingRoute($dynRoute, $package, $urlPartsArr, $staticFile);
        }

        // Process the found route
        $actionInfo = $this->processFoundRoute($foundRoute);

        // Return the matched route data
        return $this->getMatchedRouteData($actionInfo['action'], $actionInfo['route']);
    }

    /**
     * Handles the case when a package doesn't exist in routes
     *
     * @param array $routes Routes configuration
     * @param string $package Package name
     * @param bool $is404 Whether this is a 404 error handling request
     * @return array Route information
     */
    private function handleNonExistentPackage(array $routes, string $package, bool $is404): array
    {
        if (isset($routes['default']['action'], $routes['default']['404']) && $is404 === true) {
            return ['matched' => false, 'package' => '404'];
        }

        return ['matched' => false];
    }

    /**
     * Gets the matched routes array based on package
     *
     * @param array $routes Routes configuration
     * @param string $package Package name
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
     * Finds a matching route in the routes array
     *
     * @param array $matchedRoutesArr Matched routes array
     * @param array $urlPartsArr URL parts array
     * @return array Array containing [foundRoute, args, dynRoute]
     */
    private function findMatchingRoute(array $matchedRoutesArr, array $urlPartsArr): array
    {
        $dynRoute = false;
        $args = null;
        $foundRoute = [];

        foreach ($matchedRoutesArr as $route) {
            $foundRoute = [];

            // Handle default routes
            if (isset($route['default'])) {
                $result = $this->handleDefaultRoute($route, $dynRoute);
                if ($result !== null) {
                    [$foundRoute, $dynRoute, $shouldContinue] = $result;
                    if ($shouldContinue) {
                        continue;
                    }
                    break;
                }
            }

            // Check HTTP method
            if (isset($route['method']) && strtolower($route['method']) !== strtolower($this->getRequestHttpMethod())) {
                continue;
            }

            // Try to match the route
            $foundRoute = $route;
            $args = $this->getMatchedRoute($urlPartsArr, $foundRoute);

            // Initialize args if not set
            if (!isset($foundRoute['args'])) {
                $foundRoute['args'] = [];
            }

            // If route matched, merge args and break
            if ($args !== null && is_array($args)) {
                $foundRoute['args'] = array_merge($foundRoute['args'], $args);
                break;
            }

            // If action is set, unset it for the next iteration
            if (isset($foundRoute['action'])) {
                unset($foundRoute['action']);
            }
        }

        return [$foundRoute, $args, $dynRoute];
    }

    /**
     * Handles default routes
     *
     * @param array $route Route configuration
     * @param bool $dynRoute Whether this is a dynamic route
     * @return array|null Array containing [foundRoute, dynRoute, shouldContinue] or null
     */
    private function handleDefaultRoute(array $route, bool &$dynRoute): ?array
    {
        if ($route['default'] === 'dyn') {
            $dynRoute = true;
            return [[], $dynRoute, true];
        }

        if (isset($route['default']['action'], $route['default']['404']) && isset($_GET['is404']) && $_GET['is404'] === true) {
            return [$route['default']['404'], $dynRoute, false];
        }

        return [$route['default'], $dynRoute, false];
    }

    /**
     * Handles the case when no matching route is found
     *
     * @param bool $dynRoute Whether this is a dynamic route
     * @param string $package Package name
     * @param array $urlPartsArr URL parts array
     * @param bool $staticFile Whether this is a static file request
     * @return array Route information
     * @throws NotFoundException When no matching route is found
     */
    private function handleNoMatchingRoute(bool $dynRoute, string $package, array $urlPartsArr, bool $staticFile): array
    {
        if ($dynRoute === true) {
            return $this->getStandardRoutes($package, $urlPartsArr);
        }

        if ($staticFile) {
            return ['matched' => false];
        }

        if ($this->fileSystem->getEnvironment() === 'development') {
            $this->onNoMatchedRoutes();
        }

        throw new NotFoundException();
    }

    /**
     * Processes a found route
     *
     * @param array $foundRoute Found route configuration
     * @return array Array containing action and route information
     */
    private function processFoundRoute(array $foundRoute): array
    {
        // Determine action namespace
        $actionNS = $this->determineActionNamespace($foundRoute);

        // Build action string
        $_action = $actionNS . '.' . $foundRoute['action'];

        // Set content load
        $this->setContentLoad($_action);

        // Handle nested loads
        if (isset($foundRoute['nestedLoad'])) {
            $this->setNestedRoutes($foundRoute['nestedLoad'], $foundRoute['action']);
        }

        // Set current route
        $this->setCurrentRoute($foundRoute);

        // Initialize args if not set
        if (!isset($foundRoute['args'])) {
            $foundRoute['args'] = [];
        }

        return ['action' => $_action, 'route' => $foundRoute];
    }

    /**
     * Determines the action namespace for a route
     *
     * @param array $foundRoute Found route configuration
     * @return string Action namespace
     */
    private function determineActionNamespace(array $foundRoute): string
    {
        $actionType = substr($foundRoute['action'], 0, strpos($foundRoute['action'], '.'));

        if ($this->moduleRoutesEngine->checkModulByNS($actionType)) {
            $actionNS = $actionType;
            $foundRoute['action'] = substr($foundRoute['action'], strpos($foundRoute['action'], '.') + 1);
        } else if (isset($foundRoute['namespace'])) {
            $actionNS = $foundRoute['namespace'];
        } else {
            $actionNS = $this->moduleRoutesEngine->getModuleNS();
        }

        return $actionNS;
    }


    /**
     * @throws DebugException
     */
    protected function onNoMatchedRoutes()
    {
        throw new DebugException('No Matched Routes');
    }

    /**
     * Matches a URI against a route pattern and extracts parameters
     * 
     * This method handles two types of route patterns:
     * 1. Simple routes without constraints (e.g., "user/list")
     * 2. Routes with constraints (e.g., "user/[:userId]" with constraint userId => "[0-9]+")
     *
     * @param array $uriParams URI parameters array
     * @param array $routeArr Route configuration array
     * @return array|null Matched parameters or null if no match
     * @throws DebugException When constraints and route parameters don't match
     */
    private function getMatchedRoute(array $uriParams, array $routeArr): ?array
    {
        // Ensure route is set
        $route = $routeArr['route'] ?? '';

        // Check if this is a simple route without constraints
        if (strpos($route, '[:') === false && strpos($route, '[/:') === false) {
            return $this->matchSimpleRoute($uriParams, $route);
        }

        // This is a route with constraints
        return $this->matchConstrainedRoute($uriParams, $routeArr);
    }

    /**
     * Matches a simple route without constraints
     *
     * @param array $uriParams URI parameters array
     * @param string $route Route pattern
     * @return array|null Matched parameters or null if no match
     */
    private function matchSimpleRoute(array $uriParams, string $route): ?array
    {
        // Build full URI
        $fullUri = implode('/', $uriParams);

        // Remove leading slash from route if present
        if (isset($route[0]) && strpos($route, '/') === 0) {
            $route = substr($route, 1);
        }

        // Escape slashes and add trailing slash for matching
        $routePattern = str_replace('/', '\/', $route) . '\/';

        // Match the route against the URI
        $newUri = preg_replace('/^' . $routePattern . '$/', '', $fullUri . '/', -1, $count);

        // If no match, return null
        if ($count === 0) {
            return null;
        }

        // Extract parameters from the remaining URI
        preg_match_all('/([^\/\?]+)/', $newUri, $matches);
        return $matches[1];
    }

    /**
     * Matches a route with constraints
     *
     * @param array $uriParams URI parameters array
     * @param array $routeArr Route configuration array
     * @return array|null Matched parameters or null if no match
     * @throws DebugException When constraints and route parameters don't match
     */
    private function matchConstrainedRoute(array $uriParams, array $routeArr): ?array
    {
        $routeUrlExp = $routeArr['route'];
        $originalUrl = '/' . implode('/', $uriParams);

        // Process each constraint
        $routeUrlExp = $this->processConstraints($routeUrlExp, $routeArr, $routeArr['route']);

        // Escape slashes
        $routeUrlExp = str_replace('/', '\/', $routeUrlExp);

        // Match the route against the URI
        preg_match('/^\/' . trim($routeUrlExp, '\/') . '$/', $originalUrl, $matches);

        // If no match, return null
        if (!$matches) {
            return null;
        }

        // Extract named parameters
        return $this->extractNamedParameters($matches, $routeArr['constraints']);
    }

    /**
     * Processes constraints in a route pattern
     *
     * @param string $routeUrlExp Route URL expression
     * @param array $routeArr Route configuration array
     * @param string $route Original route pattern
     * @return string Processed route URL expression
     * @throws DebugException When constraints and route parameters don't match
     */
    private function processConstraints(string $routeUrlExp, array $routeArr, string $route): string
    {
        foreach ((array)$routeArr['constraints'] as $item => $constraint) {
            // Verify that the constraint parameter exists in the route
            if (strpos($routeUrlExp, ':' . $item) === false) {
                throw new DebugException(
                    'Constraints and route parameters do not match. Please check in ' . 
                    $this->fileSystem->get('NGS_ROUTS') . ' in this route section: ' . $route
                );
            }

            // Replace constraint placeholders with regex patterns
            $routeUrlExp = $this->replaceConstraintPlaceholder($routeUrlExp, $item, $constraint);
        }

        return $routeUrlExp;
    }

    /**
     * Replaces a constraint placeholder with a regex pattern
     *
     * @param string $routeUrlExp Route URL expression
     * @param string $item Constraint name
     * @param string $constraint Constraint pattern
     * @return string Updated route URL expression
     */
    private function replaceConstraintPlaceholder(string $routeUrlExp, string $item, string $constraint): string
    {
        if (strpos($routeUrlExp, '/:' . $item) === false) {
            // Format: [:item]
            return str_replace(
                '[:' . $item . ']', 
                '(?<' . $item . '>' . $constraint . ')', 
                $routeUrlExp
            );
        } else {
            // Format: [/:item]
            return str_replace(
                '[/:' . $item . ']', 
                '/?(?<' . $item . '>' . $constraint . ')?', 
                $routeUrlExp
            );
        }
    }

    /**
     * Extracts named parameters from regex matches
     *
     * @param array $matches Regex matches
     * @param array $constraints Constraints array
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


    /**
     * Handles routing for static files
     * 
     * This method processes static file requests and determines the appropriate module
     * and file type for the requested file.
     *
     * @param array $matches Matches array from URL parsing
     * @param array $urlMatches URL matches array
     * @param string $fileUrl File URL
     * @return array Static file route information
     */
    public function getStaticFileRoute(array $matches, array $urlMatches, string $fileUrl): array
    {
        // Initialize route information
        $loadsArr = [
            'type' => 'file',
            'file_type' => pathinfo(end($matches), PATHINFO_EXTENSION),
            'matched' => true
        ];

        // Determine package and file URL
        [$package, $fileUrl, $filePieces] = $this->determinePackageAndFileUrl($urlMatches, $fileUrl);

        // Check for special file types (less/sass)
        $loadsArr = $this->checkSpecialFileTypes($loadsArr, $filePieces);

        // Validate package
        $package = $this->validatePackage($package);

        // Set final route information
        $loadsArr['module'] = $package;
        $loadsArr['file_url'] = $fileUrl;

        return $loadsArr;
    }

    /**
     * Determines the package and file URL for a static file
     *
     * @param array $urlMatches URL matches array
     * @param string $fileUrl File URL
     * @return array Array containing [package, fileUrl, filePieces]
     */
    private function determinePackageAndFileUrl(array $urlMatches, string $fileUrl): array
    {
        $filePieces = $urlMatches;

        if ($this->moduleRoutesEngine->checkModuleByNS($filePieces[0])) {
            $package = array_shift($filePieces);
            $fileUrl = implode('/', $filePieces);
        } else {
            $package = array_shift($filePieces);
        }

        return [$package, $fileUrl, $filePieces];
    }

    /**
     * Checks for special file types like less or sass
     *
     * @param array $loadsArr Route information array
     * @param array $filePieces File path pieces
     * @return array Updated route information
     */
    private function checkSpecialFileTypes(array $loadsArr, array $filePieces): array
    {
        // Check if CSS is loaded from less/sass
        if ($loadsArr['file_type'] === 'css') {
            foreach ($filePieces as $urlPath) {
                if ($urlPath === 'less' || $urlPath === 'sass') {
                    $loadsArr['file_type'] = $urlPath;
                    break;
                }
            }
        }

        return $loadsArr;
    }

    /**
     * Validates and potentially adjusts the package name
     *
     * @param string $package Package name
     * @return string Validated package name
     */
    private function validatePackage(string $package): string
    {
        if (!$this->moduleRoutesEngine->checkModuleByNS($package) ||
            $this->moduleRoutesEngine->getModuleType() === 'path') {
            return $this->moduleRoutesEngine->getModuleNS();
        }

        return $package;
    }

    /**
     * Sets nested routes for a package
     * 
     * This method processes nested loads configuration and prepares it for use.
     * It recursively processes nested loads to ensure all actions have proper namespaces.
     *
     * @param array $nestedLoads Nested loads configuration
     * @param string $package Package name
     * @return void
     */
    private function setNestedRoutes(array $nestedLoads, string $package): void
    {
        foreach ($nestedLoads as $key => $value) {
            // Determine action namespace
            $actionNS = $value['namespace'] ?? $this->moduleRoutesEngine->getModuleNS();

            // Store original action as package
            $value['package'] = $value['action'];

            // Build full action string with namespace
            $value['action'] = $actionNS . '.' . $value['action'];
            $nestedLoads[$key]['action'] = $value['action'];

            // Process nested loads recursively
            if (isset($value['nestedLoad']) && is_array($value['nestedLoad'])) {
                $this->setNestedRoutes($value['nestedLoad'], $value['package']);
                unset($nestedLoads[$key]['nestedLoad']);
            }
        }

        // Store processed nested loads
        $this->nestedRoutes[$package] = $nestedLoads;
    }

    /**
     * Gets nested routes for a namespace
     * 
     * @param string $ns Namespace
     * @return array Nested routes for the namespace or empty array if none exist
     */
    public function getNestedRoutes(string $ns): array
    {
        if ($this->nestedRoutes === null || !isset($this->nestedRoutes[$ns])) {
            return [];
        }
        return $this->nestedRoutes[$ns];
    }

    /**
     * Sets the current content load
     * 
     * @param string $contentLoad Content load
     * @return void
     */
    private function setContentLoad(string $contentLoad): void
    {
        $this->contentLoad = $contentLoad;
    }

    /**
     * Gets the current content load
     * 
     * @return string|null Current content load
     */
    public function getContentLoad(): ?string
    {
        return $this->contentLoad;
    }

    /**
     * Sets the current route
     * 
     * @param array $currentRoute Current route
     * @return void
     */
    private function setCurrentRoute(array $currentRoute): void
    {
        $this->currentRoute = $currentRoute;
    }

    /**
     * Gets the current route
     * 
     * @return array|null Current route
     */
    public function getCurrentRoute(): ?array
    {
        return $this->currentRoute;
    }

    /**
     * Gets the 404 not found route
     * 
     * This method processes the current request URI as a 404 request
     * to determine the appropriate 404 handler.
     * 
     * @return array|null 404 route information
     */
    public function getNotFoundLoad(): ?array
    {
        return $this->getDynamicLoad($this->httpUtils->getRequestUri(), true);
    }

    /**
     * Gets the current HTTP request method
     * 
     * @return string HTTP method (lowercase)
     */
    protected function getRequestHttpMethod(): string
    {
        if (isset($_SERVER['REQUEST_METHOD'])) {
            return strtolower($_SERVER['REQUEST_METHOD']);
        }
        return 'get';
    }

}
