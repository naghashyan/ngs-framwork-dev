<?php

/**
 * Dispatcher class for handling requests and routing in the NGS framework
 *
 * @author Levon Naghashyan <levon@naghashyan.com>
 * @site https://naghashyan.com
 * @year 2009-2023
 * @package framework
 * @version 4.5.0
 *
 * This file is part of the NGS package.
 *
 * @copyright Naghashyan Solutions LLC
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace ngs;

use ngs\event\EventManagerInterface;
use ngs\event\EventManager;
use ngs\event\structure\BeforeResultDisplayEventStructure;
use ngs\exceptions\InvalidUserException;
use ngs\exceptions\DebugException;
use ngs\exceptions\NgsErrorException;
use ngs\exceptions\NoAccessException;
use ngs\exceptions\NotFoundException;
use ngs\exceptions\RedirectException;
use ngs\routes\NgsRoute;
use ngs\routes\NgsRoutesResolver;
use ngs\routes\NgsFileRoute;
use ngs\util\NgsArgs;
use ngs\util\NgsEnvironmentContext;

/**
 * Class Dispatcher
 * 
 * Handles routing and dispatching of requests to appropriate handlers
 * 
 * @package ngs
 */
class Dispatcher
{
    /**
     * Flag to track if a redirect has occurred
     *
     * @var bool
     */
    private bool $isRedirect = false;

    /**
     * Event manager instance
     *
     * @var EventManagerInterface
     */
    private EventManagerInterface $eventManager;

    /**
     * Constructor
     *
     * @param EventManagerInterface|null $eventManager Event manager instance
     */
    public function __construct(?EventManagerInterface $eventManager = null)
    {
        $this->eventManager = $eventManager ?? EventManager::getInstance();
    }

    /**
     * Manages matched routes and dispatches requests to appropriate handlers
     *
     * @param NgsRoute|null $route Route object from the router, used for redirecting the requests
     *
     * @return void
     * @throws DebugException When a debug error occurs
     * @throws \JsonException When JSON processing fails
     */
    public function dispatch(?NgsRoute $route = null): void
    {
        $subscribers = $this->eventManager->loadSubscribers();
        $this->eventManager->subscribeToEvents($subscribers);
        try {
            /** @var NgsRoutesResolver $routesEngine */
            $routesEngine = NGS()->createDefinedInstance('ROUTES_ENGINE', \ngs\routes\NgsRoutesResolver::class);
            $requestContext = NGS()->createDefinedInstance('REQUEST_CONTEXT', \ngs\util\RequestContext::class);
            $templateEngine = NGS()->createDefinedInstance('TEMPLATE_ENGINE', \ngs\templater\NgsTemplater::class);

            if ($route === null) {
                $requestUri = $requestContext->getRequestUri();

                // First: Detect and resolve the module from the request
                $moduleResolver = \ngs\routes\NgsModuleResolver::getInstance();
                $module = $moduleResolver->resolveModule($requestUri);

                if ($module === null) {
                    // Handle 404 (module not found)
                    throw new NotFoundException('Module not found');
                }

                // Second: Pass the module instance to the RoutesResolver
                $route = $routesEngine->resolveRoute($module, $requestUri);
            }

            //TODO: MJ: for what is this?
            if ($route instanceof NgsFileRoute && $route->getFileUrl() !== null && str_contains($route->getFileUrl(), 'js/ngs')) {
                $route->setFileUrl(str_replace("js/ngs", "js/admin/ngs", $route->getFileUrl()));
            }

            if ($route->isMatched() === false) {
                throw new NotFoundException('Request is Not found');
            }

            if (!empty($route->getArgs())) {
                NgsArgs::getInstance()->setArgs($route->getArgs());
            }

            switch ($route->getType()) {
                case 'load':
                    if (isset($_GET['ngsValidate']) && $_GET['ngsValidate'] === 'true') {
                        $this->validate($route->getRequest());
                    } elseif (isset(NGS()->args()->args()['ngsValidate']) && NGS()->args()->args()['ngsValidate']) {
                        $this->validate($route->getRequest());
                    } else {
                        $this->loadPage($route->getRequest());
                    }
                    break;

                case 'api_load':
                    $this->loadApiPage($route);
                    // no break intentional - fall through to action case

                case 'action':
                    $this->doAction($route->getRequest());
                    break;

                case 'api_action':
                    $this->doApiAction($route);
                    exit;

                case 'file':
                    $this->streamStaticFile($route);
                    break;
            }
        } catch (DebugException $ex) {
            $environmentContext = NgsEnvironmentContext::getInstance();
            if (!$environmentContext->isProduction()) {
                $ex->display();
                return;
            }

            // Handle 404 via helper
            $this->redirectToNotFound($routesEngine, $requestContext);
        } catch (RedirectException $ex) {
            $requestContext->redirect($ex->getRedirectTo());
        } catch (NotFoundException $ex) {
            try {
                if ($ex->getRedirectUrl() !== '') {
                    $requestContext->redirect($ex->getRedirectUrl());
                    return;
                }

                // Handle 404 via helper
                $this->redirectToNotFound($routesEngine, $requestContext);
            } catch (\Throwable $exp) {
                header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found", true, 404);
                exit;
            }
        } catch (NgsErrorException $ex) {
            $templateEngine->setHttpStatusCode($ex->getHttpCode());
            $templateEngine->assignJson('code', $ex->getCode());
            $templateEngine->assignJson('msg', $ex->getMessage());
            $templateEngine->assignJson('params', $ex->getParams());
            $templateEngine->display(true);
        } catch (InvalidUserException $ex) {
            $this->handleInvalidUserAndNoAccessException($ex);
        } catch (NoAccessException $ex) {
            $this->handleInvalidUserAndNoAccessException($ex);
        } catch (\Error $error) {
            // Log the error instead of var_dump in production
            error_log($error->getMessage());

            $environmentContext = NgsEnvironmentContext::getInstance();
            if (!$environmentContext->isProduction()) {
                var_dump("Error:" . $error);
            }

            header($_SERVER["SERVER_PROTOCOL"] . " 500 Internal Server Error", true, 500);
            exit;
        }
    }

    /**
     * Manages NGS loads by initializing load objects, verifying access,
     * and displaying collected output from loads
     *
     * @param string $action The action to load
     *
     * @return void
     * @throws DebugException When the load class is not found
     * @throws NoAccessException When access is denied
     */
    public function loadPage(string $action): void
    {
        try {
            $loadObj = $this->instantiateLoad($action);
            $loadObj->initialize();

            if (!$this->validateRequest($loadObj)) {
                $loadObj->onNoAccess();
            }

            $routesEngine = NGS()->createDefinedInstance('ROUTES_ENGINE', \ngs\routes\NgsRoutesResolver::class);

            //TODO:ZN: refactor this part, what is content load?
            //$contentLoad = $routesEngine->getContentLoad();
            //$loadObj->setLoadName($contentLoad);
            $loadObj->service();

            $templateEngine = NGS()->createDefinedInstance('TEMPLATE_ENGINE', \ngs\templater\NgsTemplater::class);
            $templateEngine->setType($loadObj->getNgsLoadType());
            $templateEngine->setTemplate($loadObj->getTemplate());

            $loadMapper = NGS()->createDefinedInstance('LOAD_MAPPER', \ngs\routes\NgsLoadMapper::class);
            $templateEngine->setPermalink($loadMapper->getNgsPermalink());

            // Dispatch before result display event
            $this->eventManager->dispatch(new BeforeResultDisplayEventStructure([]));

            $this->displayResult();

            $this->finishRequest();
            $loadObj->afterRequest();
        } catch (NoAccessException $ex) {
            $this->onNoAccessIfPossible($loadObj ?? null);
            throw $ex;
        } catch (InvalidUserException $ex) {
            $this->handleInvalidUserAndNoAccessException($ex);
        }
    }

    /**
     * Handles API load requests
     *
     * @param NgsRoute $route The routes object containing action and parameters
     * 
     * @return void
     * @throws DebugException When the load class is not found
     * @throws InvalidUserException When user is invalid
     * @throws NoAccessException When access is denied
     */
    public function loadApiPage(NgsRoute $route): void
    {
        try {
            /** @var NgsApiAction $loadObj */
            $loadObj = $this->instantiateLoad($route->getAction());
            $loadObj->setAction($route->offsetGet('action_method'));
            $loadObj->setRequestValidators($route->offsetGet('request_params'));
            $loadObj->setResponseValidators($route->offsetGet('response_params'));
            $loadObj->initialize();

            if (!$this->validateRequest($loadObj)) {
                $loadObj->onNoAccess();
            }

            $loadObj->service();

            $templateEngine = NGS()->createDefinedInstance('TEMPLATE_ENGINE', \ngs\templater\NgsTemplater::class);

            if (method_exists($loadObj, 'getNgsLoadType')) {
                $templateEngine->setType($loadObj->getNgsLoadType());
            }

            if (method_exists($loadObj, 'getTemplate')) {
                $templateEngine->setTemplate($loadObj->getTemplate());
            }

            $loadMapper = NGS()->createDefinedInstance('LOAD_MAPPER', \ngs\routes\NgsLoadMapper::class);
            $templateEngine->setPermalink($loadMapper->getNgsPermalink());

            // Dispatch before result display event
            $this->eventManager->dispatch(new BeforeResultDisplayEventStructure([]));

            $this->displayResult();
            $this->finishRequest();

            if (is_object($loadObj)) {
                $loadObj->afterRequest();
            }
        } catch (NoAccessException $ex) {
            $this->onNoAccessIfPossible($loadObj ?? null);
            throw $ex;
        } catch (InvalidUserException $ex) {
            $this->onNoAccessIfPossible($loadObj ?? null);
            throw $ex;
        }
    }

    /**
     * Validates a load action
     *
     * @param string $action The action to validate
     * 
     * @return void
     * @throws DebugException When the load class is not found
     * @throws NoAccessException When access is denied
     * @throws InvalidUserException When user is invalid
     */
    public function validate(string $action): void
    {
        try {
            $loadObj = $this->instantiateLoad($action);
            $loadObj->initialize();

            if (!$this->validateRequest($loadObj)) {
                $loadObj->onNoAccess();
            }

            $routesEngine = NGS()->createDefinedInstance('ROUTES_ENGINE', \ngs\routes\NgsRoutesResolver::class);
            //TODO: ZN: this logic should be refactored, what is the contentLoad?
//            $contentLoad = $routesEngine->getContentLoad();
//            $loadObj->setLoadName($contentLoad);
            $loadObj->validate();

            // Passing arguments
            $templateEngine = NGS()->createDefinedInstance('TEMPLATE_ENGINE', \ngs\templater\NgsTemplater::class);
            $templateEngine->setType('json');
            $templateEngine->assignJsonParams($loadObj->getParams());

            $this->displayResult();
            $this->finishRequest();

            $loadObj->afterRequest();
        } catch (NoAccessException $ex) {
            $this->onNoAccessIfPossible($loadObj ?? null);
            throw $ex;
        } catch (InvalidUserException $ex) {
            $this->onNoAccessIfPossible($loadObj ?? null);
            throw $ex;
        }
    }

    /**
     * Manages NGS actions by initializing action objects, verifying access,
     * and displaying action output
     *
     * @param string $action The action to execute
     *
     * @return void
     * @throws DebugException When the action class is not found
     * @throws NoAccessException When access is denied
     */
    private function doAction(string $action): void
    {
        try {
            $actionObj = $this->instantiateAction($action);
            $actionObj->initialize();

            if (!$this->validateRequest($actionObj)) {
                $actionObj->onNoAccess();
            }

            $actionObj->service();

            // Passing arguments
            $templateEngine = NGS()->createDefinedInstance('TEMPLATE_ENGINE', \ngs\templater\NgsTemplater::class);
            $templateEngine->setType('json');
            $templateEngine->assignJsonParams($actionObj->getParams());

            $this->displayResult();
            $this->finishRequest();

            $actionObj->afterRequest();
        } catch (NoAccessException $ex) {
            $this->onNoAccessIfPossible($actionObj ?? null);
            throw $ex;
        } catch (InvalidUserException $ex) {
            $this->handleInvalidUserAndNoAccessException($ex);
        }
    }

    /**
     * Handles API action requests
     *
     * @param NgsRoute $route The routes object containing action and parameters
     * 
     * @return void
     * @throws DebugException When the action class is not found
     * @throws InvalidUserException When user is invalid
     * @throws NoAccessException When access is denied
     */
    private function doApiAction(NgsRoute $route): void
    {
        try {
            $actionObj = $this->instantiateAction($route->getAction());
            $actionObj->setAction($route->offsetGet('action_method'));
            $actionObj->setRequestValidators($route->offsetGet('request_params'));
            $actionObj->setResponseValidators($route->offsetGet('response_params'));
            $actionObj->initialize();

            if (!$this->validateRequest($actionObj)) {
                $actionObj->onNoAccess();
            }

            $actionObj->service();

            // Passing arguments
            $templateEngine = NGS()->createDefinedInstance('TEMPLATE_ENGINE', \ngs\templater\NgsTemplater::class);
            $templateEngine->setType('json');
            $templateEngine->assignJsonParams($actionObj->getParams());

            $this->displayResult();
            $this->finishRequest();

            $actionObj->afterRequest();
        } catch (NoAccessException $ex) {
            $this->onNoAccessIfPossible($actionObj ?? null);
            throw $ex;
        } catch (InvalidUserException $ex) {
            $this->onNoAccessIfPossible($actionObj ?? null);
            throw $ex;
        }
    }

    /**
     * Streams a static file to the client
     *
     * @param NgsFileRoute $route The file information object
     * 
     * @return void
     */
    private function streamStaticFile(NgsFileRoute $route): void
    {
        $module = $route->getModule();

        $moduleDir = $module->getDir();

        $publicDirForModule = realpath($moduleDir . '/' . NGS()->get('PUBLIC_DIR'));
        $filePath = realpath($publicDirForModule . '/' . $route->getFileUrl());

        if (file_exists($filePath)) {
            $streamer = NGS()->createDefinedInstance('FILE_UTILS', \ngs\util\FileUtils::class);
        } else {
            $fileType = strtolower((string)$route->getFileType());
            $builderKey = strtoupper($fileType) . '_BUILDER';
            if (NGS()->defined($builderKey)) {
                // Validate against common AbstractBuilder to allow any specific builder implementation
                $streamer = NGS()->createDefinedInstance($builderKey, \ngs\util\AbstractBuilder::class);
            } else {
                $streamer = NGS()->createDefinedInstance('FILE_UTILS', \ngs\util\FileUtils::class);
            }
        }

        $streamer->streamFile($filePath);
    }

    /**
     * Handles InvalidUserException and NoAccessException by redirecting or displaying error message
     *
     * @param InvalidUserException|NoAccessException $ex The exception to handle
     * 
     * @return void
     * @throws DebugException When a debug error occurs
     */
    private function handleInvalidUserAndNoAccessException($ex): void
    {
        $requestContext = NGS()->createDefinedInstance('REQUEST_CONTEXT', \ngs\util\RequestContext::class);
        $templateEngine = NGS()->createDefinedInstance('TEMPLATE_ENGINE', \ngs\templater\NgsTemplater::class);

        // For non-AJAX requests, redirect to the specified URL
        if (!$requestContext->isAjaxRequest() && !NGS()->getDefinedValue('display_json')) {
            $requestContext->redirect($ex->getRedirectTo());
            return;
        }

        // For AJAX requests, return JSON response
        $templateEngine->setHttpStatusCode($ex->getHttpCode());
        $templateEngine->assignJson('code', $ex->getCode());
        $templateEngine->assignJson('msg', $ex->getMessage());

        if ($ex->getRedirectTo() !== '') {
            $templateEngine->assignJson('redirect_to', $ex->getRedirectTo());
        }

        if ($ex->getRedirectToLoad() !== '') {
            $templateEngine->assignJson('redirect_to_load', $ex->getRedirectToLoad());
        }

        $templateEngine->display(true);
    }

    /**
     * Validates request load/action access permissions
     *
     * @param object $request The request object to validate
     *
     * @return bool True if the request is valid, false otherwise
     */
    private function validateRequest(object $request): bool
    {
        $sessionManager = NGS()->createDefinedInstance('SESSION_MANAGER', \ngs\session\AbstractSessionManager::class);
        return $sessionManager->validateRequest($request);
    }


    /**
     * Returns all visible events
     *
     * @return array Array of visible events
     */
    public function getVisibleEvents(): array
    {
        return $this->eventManager->getVisibleEvents();
    }

    /**
     * Handles session closing and fastcgi request finishing
     * 
     * @return void
     */
    private function finishRequest(): void
    {
        if (PHP_SAPI === 'fpm-fcgi') {
            session_write_close();
            fastcgi_finish_request();
        }
    }

    /**
     * Helper: redirects to a not found route or echoes 404 and exits
     *
     * @param NgsRoutesResolver $routesEngine
     * @param \ngs\util\RequestContext $requestContext
     * @return void
     */
    private function redirectToNotFound(NgsRoutesResolver $routesEngine, \ngs\util\RequestContext $requestContext): void
    {
        $moduleResolver = \ngs\routes\NgsModuleResolver::getInstance();
        $module = $moduleResolver->resolveModule($requestContext->getRequestUri());

        $route = $routesEngine->getNotFoundLoad($module);
        if ($route === null || $this->isRedirect === true) {
            echo '404';
            exit;
        }

        $this->isRedirect = true;
        $this->dispatch($route);
    }

    /**
     * Helper: normalize class name from hyphenated route
     */
    private function normalizeClassName(string $class): string
    {
        return str_replace('-', '\\', $class);
    }

    /**
     * Helper: instantiate a Load class or throw DebugException
     *
     * @param string $action
     * @return object
     * @throws DebugException
     */
    private function instantiateLoad(string $action): object
    {
        $class = $this->normalizeClassName($action);
        if (class_exists($class) === false) {
            throw new DebugException($class . ' Load Not found');
        }
        return new $class();
    }

    /**
     * Helper: instantiate an Action class or throw DebugException
     *
     * @param string $action
     * @return object
     * @throws DebugException
     */
    private function instantiateAction(string $action): object
    {
        $class = $this->normalizeClassName($action);
        if (class_exists($class) === false) {
            throw new DebugException($class . ' Action Not found');
        }
        return new $class();
    }

    /**
     * Helper: call onNoAccess on object if method exists
     *
     * @param mixed $obj
     * @return void
     */
    private function onNoAccessIfPossible($obj): void
    {
        if (is_object($obj) && method_exists($obj, 'onNoAccess')) {
            $obj->onNoAccess();
        }
    }

    /**
     * Displays collected output from loads and actions
     *
     * @return void
     */
    private function displayResult(): void
    {
        $templateEngine = NGS()->createDefinedInstance('TEMPLATE_ENGINE', \ngs\templater\NgsTemplater::class);
        $templateEngine->display();
    }
}
