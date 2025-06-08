<?php

/**
 * Default NGS routing class
 * This class is used by default from dispatcher for matching URL with routes
 *
 * @author Levon Naghashyan <levon@naghashyan.com>
 * @site https://naghashyan.com
 * @year 2014-2023
 * @package ngs.framework.routes
 * @version 4.2.0
 *
 * This file is part of the NGS package.
 *
 * @copyright Naghashyan Solutions LLC
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ngs\routes;

use ngs\exceptions\DebugException;
use ngs\exceptions\NotFoundException;
use ngs\util\NgsEnvironmentContext;

class NgsRoutes
{
    protected ?array $routes = null;
    private ?string $package = null;
    private ?array $nestedRoutes = null;
    private ?string $contentLoad = null;
    private string $dynContainer = 'dyn';
    private ?array $currentRoute = null;

    public function __construct(string $routesFile = "")
    {
    }


    /**
     * Return URL dynamic part
     * This method can be overridden by other users
     * if they don't want to use 'dyn' container,
     * but this approach may cause conflicts with routes
     *
     * @return string
     */
    protected function getDynContainer(): string
    {
        return $this->dynContainer;
    }

    /**
     * Read routes from JSON file and cache them in a property
     *
     * @return array|null
     */
    protected function getRouteConfig(): ?array
    {
        if ($this->routes !== null) {
            return $this->routes;
        }

        $routFile = NGS()->getConfigDir() . '/' . NGS()->get('NGS_ROUTS');
        if (file_exists($routFile)) {
            $fileContent = file_get_contents($routFile);
            if ($fileContent === false) {
                return null;
            }

            $this->routes = json_decode($fileContent, true);

            $moduleRoutsConstant = 'NGS_MODULS_ROUTS';
            if (NGS()->get($moduleRoutsConstant)) {
                $moduleRoutFile = NGS()->getConfigDir() . '/' . NGS()->get($moduleRoutsConstant);
                $moduleFileContent = file_get_contents($moduleRoutFile);

                if ($moduleFileContent !== false) {
                    $moduleRoutes = json_decode($moduleFileContent, true);
                    if (is_array($moduleRoutes)) {
                        $this->routes = array_merge($this->routes, $moduleRoutes);
                    }
                }
            }
        }

        return $this->routes;
    }

    /**
     * Set URL package
     *
     * @param string $package The package name
     * @return void
     */
    private function setPackage(string $package): void
    {
        $this->package = $package;
    }

    /**
     * Return URL package
     *
     * @return string|null The package name
     */
    public function getPackage(): ?string
    {
        return $this->package;
    }

    /**
     * Return package and command from URL
     * Check URL: if dynamic container is set, manage using standard routing;
     * otherwise manage URL using routes file. If matched successfully, return array; if not, return null.
     * This method can be overridden by users for custom routing scenarios.
     *
     * @param string $url The URL to process
     * @param bool $is404 Whether this is a 404 request
     * @return array|null The load information array or null
     * @throws DebugException
     * @throws NotFoundException
     */
    public function getDynamicLoad(string $url, bool $is404 = false): ?array
    {
        $loadsArr = ['matched' => false];

        // Check if URI exists; if not, get default route
        if ($url !== '' && $url[0] === '/') {
            $url = substr($url, 1);
        }

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

        $urlPartsArr = $matches;
        if ($package === $this->getDynContainer()) {
            $package = array_shift($urlPartsArr);
            $moduleRoutesEngine = NGS()->getModulesRoutesEngine();

            if ($package === $moduleRoutesEngine->getModuleNS()) {
                $package = array_shift($urlPartsArr);
            }

            $loadsArr = $this->getStandartRoutes($package, $urlPartsArr);
        } else {
            if ($package === null) {
                $package = 'default';
            }

            $loadsArr = $this->getDynRoutesLoad($url, $package, $urlPartsArr, $is404, $staticFile);
        }

        if ($loadsArr['matched']) {
            $actionArr = $this->getLoadORActionByAction($loadsArr['action']);
            $loadsArr['type'] = $actionArr['type'];
            $loadsArr['action'] = $actionArr['action'];
        }

        if (!empty($matches) && (strrpos(end($matches), '.')) !== false) {
            $staticFile = true;
        }

        // If static file
        if ($loadsArr['matched'] === false && $staticFile === true) {
            $moduleRoutesEngine = NGS()->getModulesRoutesEngine();

            if (isset($urlMatches[0]) && $urlMatches[0] == strtolower($moduleRoutesEngine->getDefaultNS())) {
                array_shift($urlMatches);
                $fileUrl = substr($fileUrl, strpos($fileUrl, '/') + 1);
            }

            $loadsArr = $this->getStaticFileRoute($matches, $urlMatches, $fileUrl);
            $package = $loadsArr['module'];
        }

        $this->setPackage($package);
        return $loadsArr;
    }

    /**
     * Return file path and namespace from action
     *
     * @param string|null $action The action string
     * @return array|null The action information array or null
     */
    public function getLoadORActionByAction(?string $action = null): ?array
    {
        if (!$action) {
            return null;
        }

        $pathArr = explode('.', $action);
        $action = array_splice($pathArr, count($pathArr) - 1);
        $action = $action[0];
        $module = array_splice($pathArr, 0, 1);
        $module = $module[0];
        $actionType = '';
        $classPrefix = '';

        foreach ($pathArr as $v) {
            $actionsDir = NGS()->get('ACTIONS_DIR');
            $loadsDir = NGS()->get('LOADS_DIR');

            if ($v === $actionsDir) {
                $actionType = 'action';
                $classPrefix = 'Action';
                break;
            } elseif ($v === $loadsDir) {
                $actionType = 'load';
                $classPrefix = 'Load';
                break;
            }
        }

        if (strrpos($action, 'do_') !== false) {
            $action = str_replace('do_', '', $action);
        }

        $action = preg_replace_callback('/_(\w)/', function ($m) {
            return strtoupper($m[1]);
        }, ucfirst($action)) . $classPrefix;

        return [
            'action' => $module . '\\' . implode('\\', $pathArr) . '\\' . $action, 
            'type' => $actionType
        ];
    }

    /**
     * Returns matched route data
     *
     * @param string $action The action string
     * @param array $route The route array
     * @return array The matched route data
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
     * NGS standard routing: URL first part used for package,
     * second part for command, and other parts for args
     *
     * @param string $ns The namespace
     * @param array $urlPartsArr The URL parts array
     *
     * @return array The route information
     */
    private function getStandartRoutes(string $ns, array $urlPartsArr): array
    {
        $command = array_shift($urlPartsArr);
        if ($command === null) {
            $command = 'default';
        }

        if (strpos($ns, '_') !== false) {
            $ns = str_replace('_', '.', $ns);
        }

        $moduleRoutesEngine = NGS()->getModulesRoutesEngine();
        $module = $moduleRoutesEngine->getModuleNS();

        $loadsDir = NGS()->get('LOADS_DIR');
        $actionsDir = NGS()->get('ACTIONS_DIR');

        $actionPackage = $loadsDir;
        if (strrpos($command, 'do_') !== false) {
            $actionPackage = $actionsDir;
        }

        $action = $module . '.' . $actionPackage . '.';

        if ($ns) {
            $action .= $ns . '.';
        }

        $action .= $command;
        $this->setContentLoad($action);

        return [
            'action' => $action, 
            'args' => $urlPartsArr, 
            'matched' => true
        ];
    }

    /**
     * NGS dynamic routing using routes JSON file for URL matching
     * First URL part is used for JSON array key matching
     *
     * @param string $url The URL to process
     * @param string $package The package name
     * @param array $urlPartsArr The URL parts array
     * @param bool $is404 Whether this is a 404 request
     * @param bool $staticFile Whether this is a static file
     * @return array The route information
     * @throws DebugException
     * @throws NotFoundException
     */
    private function getDynRoutesLoad(string $url, string $package, array $urlPartsArr, bool $is404 = false, bool $staticFile = false): array
    {
        $routes = $this->getRouteConfig();
        if (!isset($routes[$package])) {
            if (isset($routes['default']['action'], $routes['default']['404']) && $is404 === true) {
                $package = '404';
            } else {
                return ['matched' => false];
            }
        }

        $matchedRoutesArr = [];

        if ($package === '404') {
            $matchedRoutesArr[] = $routes['default'][$package];
        } elseif ($package === 'default') {
            $matchedRoutesArr[][$package] = $routes[$package];
        } else {
            $matchedRoutesArr = $routes[$package];
        }

        $dynRoute = false;
        $args = null;
        $foundRoute = [];

        foreach ($matchedRoutesArr as $route) {
            $foundRoute = [];
            if (isset($route['default'])) {
                if ($route['default'] === 'dyn') {
                    $dynRoute = true;
                    continue;
                }
                if (isset($route['default']['action'], $route['default']['404']) && $is404 === true) {
                    $foundRoute = $route['default']['404'];
                } else {
                    $foundRoute = $route['default'];
                    break;
                }
            }

            if (isset($route['method']) && strtolower($route['method']) !== strtolower($this->getRequestHttpMethod())) {
                continue;
            }

            $foundRoute = $route;

            $args = $this->getMatchedRoute($urlPartsArr, $foundRoute);

            if (!isset($foundRoute['args'])) {
                $foundRoute['args'] = [];
            }

            if ($args !== null && is_array($args)) {
                $foundRoute['args'] = array_merge($foundRoute['args'], $args);
                break;
            }

            if (isset($foundRoute['action'])) {
                unset($foundRoute['action']);
            }
        }

        if ($args === null && !isset($foundRoute['action'])) {
            if ($dynRoute === true) {
                return $this->getStandartRoutes($package, $urlPartsArr);
            }
            if ($staticFile) {
                return ['matched' => false];
            }

            $environmentContext = NgsEnvironmentContext::getInstance();
            if ($environmentContext->isDevelopment()) {
                $this->onNoMatchedRoutes();
            }
            throw new NotFoundException();
        }

        $actionType = substr($foundRoute['action'], 0, strpos($foundRoute['action'], '.'));
        $moduleRoutesEngine = NGS()->getModulesRoutesEngine();

        if ($moduleRoutesEngine->checkModulByNS($actionType)) {
            $actionNS = $actionType;
            $foundRoute['action'] = substr($foundRoute['action'], strpos($foundRoute['action'], '.') + 1);
        } elseif (isset($foundRoute['namespace'])) {
            $actionNS = $foundRoute['namespace'];
        } else {
            $actionNS = $moduleRoutesEngine->getModuleNS();
        }

        $_action = $actionNS . '.' . $foundRoute['action'];
        $this->setContentLoad($_action);

        if (isset($foundRoute['nestedLoad'])) {
            $isDomainDefault = !array_key_exists("defaultDomain", $foundRoute) || $foundRoute['defaultDomain'];
            $this->setNestedRoutes($foundRoute['nestedLoad'], $foundRoute['action'], $isDomainDefault);
        }

        $this->setCurrentRoute($foundRoute);

        if (!isset($foundRoute['args'])) {
            $foundRoute['args'] = [];
        }

        return $this->getMatchedRouteData($_action, $foundRoute);
    }


    /**
     * Handle the case when no routes are matched
     * 
     * @throws DebugException
     */
    protected function onNoMatchedRoutes(): void
    {
        throw new DebugException('No Matched Routes');
    }

    /**
     * Manage constraints from URL parts
     * If constraints are found in route rules, use other parts of URL for matching
     *
     * @param array $uriParams The URI parameters
     * @param array $routeArr The route array
     *
     * @return array|null The matched parameters or null if no match
     * @throws DebugException
     */
    private function getMatchedRoute(array $uriParams, array $routeArr): ?array
    {
        if (!isset($routeArr['route'])) {
            $routeArr['route'] = '';
        }

        $route = $routeArr['route'];

        // Simple route without constraints
        if (strpos($route, '[:') === false && strpos($route, '[/:') === false) {
            $fullUri = implode('/', $uriParams);

            if (isset($route[0]) && strpos($route, '/') === 0) {
                $route = substr($route, 1);
            }

            $route = str_replace('/', '\/', $route) . '\/';

            $newUri = preg_replace('/^' . $route . '$/', '', $fullUri . '/', -1, $count);
            if ($count === 0) {
                return null;
            }

            preg_match_all('/([^\/\?]+)/', $newUri, $matches);
            return $matches[1];
        }

        // Route with constraints
        $routeUrlExp = $routeArr['route'];
        $originalUrl = '/' . implode('/', $uriParams);
        $routsConstant = 'NGS_ROUTS';

        foreach ((array)$routeArr['constraints'] as $item => $constraint) {
            if (strpos($routeUrlExp, ':' . $item) === false) {
                throw new DebugException(
                    'Constraints and routes params not matched, please check in ' . 
                    NGS()->get($routsConstant) . ' in this route section ' . $route
                );
            }

            if (strpos($routeUrlExp, '/:' . $item) === false) {
                $routeUrlExp = str_replace(
                    '[:' . $item . ']', 
                    '(?<' . $item . '>' . $constraint . ')', 
                    $routeUrlExp
                );
            } else {
                $routeUrlExp = str_replace(
                    '[/:' . $item . ']', 
                    '/?(?<' . $item . '>' . $constraint . ')?', 
                    $routeUrlExp
                );
            }
        }

        $routeUrlExp = str_replace('/', '\/', $routeUrlExp);
        preg_match('/^\/' . trim($routeUrlExp, '\/') . '$/', $originalUrl, $matches);

        if (!$matches) {
            return null;
        }

        $urlMatchArgs = [];
        foreach ((array)$routeArr['constraints'] as $item => $constraint) {
            if (isset($matches[$item])) {
                $urlMatchArgs[$item] = $matches[$item];
            }
        }

        return $urlMatchArgs;
    }


    /**
     * Get static file route information
     *
     * @param array $matches The matches array
     * @param array $urlMatches The URL matches array
     * @param string $fileUrl The file URL
     * @return array The static file route information
     */
    public function getStaticFileRoute(array $matches, array $urlMatches, string $fileUrl): array
    {
        $loadsArr = [];
        $loadsArr['type'] = 'file';
        $loadsArr['file_type'] = pathinfo(end($matches), PATHINFO_EXTENSION);

        $filePieces = $urlMatches;
        $moduleRoutesEngine = NGS()->getModulesRoutesEngine();

        if ($moduleRoutesEngine->checkModuleByNS($filePieces[0])) {
            $package = array_shift($filePieces);
            $fileUrl = implode('/', $filePieces);
        } else {
            $package = array_shift($filePieces);
        }

        // Checking if CSS loaded from less or sass
        $filePieceIndex = 0;
        if (!$moduleRoutesEngine->isDefaultModule() && $moduleRoutesEngine->getModuleType() != 'path') {
            $filePieceIndex = 1;
        }

        if (isset($filePieces[$filePieceIndex])) {
            if ($filePieces[$filePieceIndex] === 'less') {
                $loadsArr['file_type'] = 'less';
            } elseif ($filePieces[$filePieceIndex] === 'sass') {
                $loadsArr['file_type'] = 'sass';
            }
        }

        if (!$moduleRoutesEngine->checkModuleByNS($package)) {
            $package = $moduleRoutesEngine->getDefaultNS();
        }

        if ($moduleRoutesEngine->getModuleType() === 'path') {
            $package = $moduleRoutesEngine->getModuleNS();
        }

        $loadsArr['module'] = $package;
        $loadsArr['file_url'] = $fileUrl;
        $loadsArr['matched'] = true;

        return $loadsArr;
    }

    /**
     * Set URL nested loads
     *
     * @param array $nestedLoads The nested loads array
     * @param string $package The package name
     * @param bool|null $isDomainDefault Whether this is a domain default
     * @return void
     */
    private function setNestedRoutes(array $nestedLoads, string $package, ?bool $isDomainDefault = true): void
    {
        foreach ($nestedLoads as $key => $value) {
            $actionNS = "";

            if ($isDomainDefault) {
                if (isset($value['namespace'])) {
                    $actionNS = $value['namespace'];
                } else {
                    $moduleRoutesEngine = NGS()->getModulesRoutesEngine();
                    $actionNS = $moduleRoutesEngine->getModuleNS();
                }
            }

            $value['package'] = $value['action'];
            $value['action'] = $actionNS . '.' . $value['action'];
            $nestedLoads[$key]['action'] = $value['action'];

            if (isset($value['nestedLoad']) && is_array($value['nestedLoad'])) {
                $this->setNestedRoutes($value['nestedLoad'], $value['package']);
                unset($nestedLoads[$key]['nestedLoad']);
            }
        }

        if ($this->nestedRoutes === null) {
            $this->nestedRoutes = [];
        }

        $this->nestedRoutes[$package] = $nestedLoads;
    }

    /**
     * Get nested routes for a namespace
     *
     * @param string $ns The namespace
     * @return array The nested routes
     */
    public function getNestedRoutes(string $ns): array
    {
        if ($this->nestedRoutes === null || !isset($this->nestedRoutes[$ns])) {
            return [];
        }
        return $this->nestedRoutes[$ns];
    }

    /**
     * Set content load
     *
     * @param string $contentLoad The content load
     * @return void
     */
    private function setContentLoad(string $contentLoad): void
    {
        $this->contentLoad = $contentLoad;
    }

    /**
     * Get content load
     *
     * @return string|null The content load
     */
    public function getContentLoad(): ?string
    {
        return $this->contentLoad;
    }

    /**
     * Set current route
     *
     * @param array $currentRoute The current route
     * @return void
     */
    private function setCurrentRoute(array $currentRoute): void
    {
        $this->currentRoute = $currentRoute;
    }

    /**
     * Get current route
     *
     * @return array|null The current route
     */
    public function getCurrentRoute(): ?array
    {
        return $this->currentRoute;
    }

    /**
     * Get not found load
     *
     * @return array|null The not found load
     * @throws DebugException
     * @throws NotFoundException
     */
    public function getNotFoundLoad(): ?array
    {
        $httpUtils = NGS()->getHttpUtils();
        return $this->getDynamicLoad($httpUtils->getRequestUri(), true);
    }

    /**
     * Get request HTTP method
     *
     * @return string The HTTP method
     */
    protected function getRequestHttpMethod(): string
    {
        if (isset($_SERVER['REQUEST_METHOD'])) {
            return strtolower($_SERVER['REQUEST_METHOD']);
        }
        return 'get';
    }
}
