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
use ngs\routes\NgsModuleResolver;
use ngs\routes\NgsRoute;
use ngs\routes\NgsRoutesResolver;
use ngs\routes\NgsFileRoute;
use ngs\templater\NgsTemplater;
use ngs\util\AbstractBuilder;
use ngs\util\FileUtils;
use ngs\util\NgsArgs;
use ngs\util\NgsEnvironmentContext;
use ngs\util\RequestContext;
use ngs\request\AbstractRequest;

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

    /** @var RequestContext */
    private RequestContext $requestContext;

    /** @var NgsRoutesResolver */
    private NgsRoutesResolver $routesEngine;

    /** @var NgsModuleResolver */
    private NgsModuleResolver $moduleResolver;

    /** @var NgsTemplater */
    private NgsTemplater $templateEngine;

    /**
     * Constructor
     *
     * @param EventManagerInterface|null $eventManager Event manager instance
     */
    public function __construct(?EventManagerInterface $eventManager = null)
    {
        $this->eventManager = $eventManager ?? EventManager::getInstance();
        // Initialize shared instances as class properties
        $this->requestContext = NGS()->createDefinedInstance('REQUEST_CONTEXT', RequestContext::class);
        $this->routesEngine = NGS()->createDefinedInstance('ROUTES_ENGINE', NgsRoutesResolver::class);
        $this->moduleResolver = NGS()->createDefinedInstance('MODULES_ROUTES_ENGINE', NgsModuleResolver::class);
        $this->templateEngine = NGS()->createDefinedInstance('TEMPLATE_ENGINE', NgsTemplater::class);
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
        //TODO: ZN: revise the events management
        $subscribers = $this->eventManager->loadSubscribers();
        $this->eventManager->subscribeToEvents($subscribers);

        try {

            if ($route === null) {
                $requestUri = $this->requestContext->getRequestUri();

                // First: Detect and resolve the module from the request
                $module = $this->moduleResolver->resolveModule($requestUri);

                if ($module === null) {
                    // Handle 404 (module not found)
                    throw new NotFoundException('Module not found');
                }

                // Second: Pass the module instance to the RoutesResolver
                $route = $this->routesEngine->resolveRoute($module, $requestUri);
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
            $this->redirectToNotFound($module);
        } catch (RedirectException $ex) {
            $this->requestContext->redirect($ex->getRedirectTo());
        } catch (NotFoundException $ex) {
            try {
                if ($ex->getRedirectUrl() !== '') {
                    $this->requestContext->redirect($ex->getRedirectUrl());
                    return;
                }

                // Handle 404 via helper
                $this->redirectToNotFound();
            } catch (\Throwable $exp) {
                header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found", true, 404);
                exit;
            }
        } catch (NgsErrorException $ex) {
            $this->templateEngine->setHttpStatusCode($ex->getHttpCode());
            $this->templateEngine->assignJson('code', $ex->getCode());
            $this->templateEngine->assignJson('msg', $ex->getMessage());
            $this->templateEngine->assignJson('params', $ex->getParams());
            $this->templateEngine->display(true);
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
            $loadObj = $this->instantiateRequestObject($action);
            $loadObj->initialize();

            if (!$this->validateRequest($loadObj)) {
                $loadObj->onNoAccess();
            }

            //TODO:ZN: refactor this part, what is content load?
            //$contentLoad = $this->routesEngine->getContentLoad();
            //$loadObj->setLoadName($contentLoad);
            $loadObj->service();

            $this->templateEngine->setType($loadObj->getNgsLoadType());
            $this->templateEngine->setTemplate($loadObj->getTemplate());

            $loadMapper = NGS()->createDefinedInstance('LOAD_MAPPER', \ngs\routes\NgsLoadMapper::class);
            $this->templateEngine->setPermalink($loadMapper->getNgsPermalink());

            // Dispatch before result display event
            $this->eventManager->dispatch(new BeforeResultDisplayEventStructure([]));

            $this->displayResult();

            $this->finishRequest();
            $loadObj->afterRequest();
        } catch (NoAccessException $ex) {
            $this->onNoAccess($loadObj);
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
            $loadObj = $this->instantiateRequestObject($route->getAction());
            $loadObj->setAction($route->offsetGet('action_method'));
            $loadObj->setRequestValidators($route->offsetGet('request_params'));
            $loadObj->setResponseValidators($route->offsetGet('response_params'));
            $loadObj->initialize();

            if (!$this->validateRequest($loadObj)) {
                $loadObj->onNoAccess();
            }

            $loadObj->service();

            if (method_exists($loadObj, 'getNgsLoadType')) {
                $this->templateEngine->setType($loadObj->getNgsLoadType());
            }

            if (method_exists($loadObj, 'getTemplate')) {
                $this->templateEngine->setTemplate($loadObj->getTemplate());
            }

            $loadMapper = NGS()->createDefinedInstance('LOAD_MAPPER', \ngs\routes\NgsLoadMapper::class);
            $this->templateEngine->setPermalink($loadMapper->getNgsPermalink());

            // Dispatch before result display event
            $this->eventManager->dispatch(new BeforeResultDisplayEventStructure([]));

            $this->displayResult();
            $this->finishRequest();

            if (is_object($loadObj)) {
                $loadObj->afterRequest();
            }
        } catch (NoAccessException $ex) {
            $this->onNoAccess($loadObj);
            throw $ex;
        } catch (InvalidUserException $ex) {
            $this->onNoAccess($loadObj);
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
            $loadObj = $this->instantiateRequestObject($action);
            $loadObj->initialize();

            if (!$this->validateRequest($loadObj)) {
                $loadObj->onNoAccess();
            }

            //TODO: ZN: this logic should be refactored, what is the contentLoad?
//            $contentLoad = $this->routesEngine->getContentLoad();
//            $loadObj->setLoadName($contentLoad);
            $loadObj->validate();

            // Passing arguments
            $this->templateEngine->setType('json');
            $this->templateEngine->assignJsonParams($loadObj->getParams());

            $this->displayResult();
            $this->finishRequest();

            $loadObj->afterRequest();
        } catch (NoAccessException $ex) {
            $this->onNoAccess($loadObj);
            throw $ex;
        } catch (InvalidUserException $ex) {
            $this->onNoAccess($loadObj);
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
            $actionObj = $this->instantiateRequestObject($action);
            $actionObj->initialize();

            if (!$this->validateRequest($actionObj)) {
                $actionObj->onNoAccess();
            }

            $actionObj->service();

            // Passing arguments
            $this->templateEngine->setType('json');
            $this->templateEngine->assignJsonParams($actionObj->getParams());

            $this->displayResult();
            $this->finishRequest();

            $actionObj->afterRequest();
        } catch (NoAccessException $ex) {
            $this->onNoAccess($actionObj);
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
            $actionObj = $this->instantiateRequestObject($route->getAction());
            $actionObj->setAction($route->offsetGet('action_method'));
            $actionObj->setRequestValidators($route->offsetGet('request_params'));
            $actionObj->setResponseValidators($route->offsetGet('response_params'));
            $actionObj->initialize();

            if (!$this->validateRequest($actionObj)) {
                $actionObj->onNoAccess();
            }

            $actionObj->service();

            // Passing arguments
            $this->templateEngine->setType('json');
            $this->templateEngine->assignJsonParams($actionObj->getParams());

            $this->displayResult();
            $this->finishRequest();

            $actionObj->afterRequest();
        } catch (NoAccessException $ex) {
            $this->onNoAccess($actionObj);
            throw $ex;
        } catch (InvalidUserException $ex) {
            $this->onNoAccess($actionObj);
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
            $streamer = NGS()->createDefinedInstance('FILE_UTILS', FileUtils::class);
        } else {
            $fileType = strtolower((string)$route->getFileType());
            $builderKey = strtoupper($fileType) . '_BUILDER';
            if (NGS()->defined($builderKey)) {
                // Validate against common AbstractBuilder to allow any specific builder implementation
                $streamer = NGS()->createDefinedInstance($builderKey, AbstractBuilder::class);
            } else {
                $streamer = NGS()->createDefinedInstance('FILE_UTILS', FileUtils::class);
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
        // For non-AJAX requests, redirect to the specified URL
        if (!$this->requestContext->isAjaxRequest() && !NGS()->getDefinedValue('display_json')) {
            $this->requestContext->redirect($ex->getRedirectTo());
            return;
        }

        // For AJAX requests, return JSON response
        $this->templateEngine->setHttpStatusCode($ex->getHttpCode());
        $this->templateEngine->assignJson('code', $ex->getCode());
        $this->templateEngine->assignJson('msg', $ex->getMessage());

        if ($ex->getRedirectTo() !== '') {
            $this->templateEngine->assignJson('redirect_to', $ex->getRedirectTo());
        }

        if ($ex->getRedirectToLoad() !== '') {
            $this->templateEngine->assignJson('redirect_to_load', $ex->getRedirectToLoad());
        }

        $this->templateEngine->display(true);
    }

    /**
     * Validates request load/action access permissions
     *
     * @param object $request The request object to validate
     *
     * @return bool True if the request is valid, false otherwise
     */
    private function validateRequest(AbstractRequest $request): bool
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
     * @param RequestContext $requestContext
     * @return void
     */
    private function redirectToNotFound(): void
    {
        // Use existing module resolver property
        $module = $this->moduleResolver->resolveModule($requestContext->getRequestUri());

        $route = $this->routesEngine->getNotFoundLoad($module);
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
     * Helper: instantiate a Request (Load/Action) class or throw DebugException
     *
     * @param string $request Fully-qualified or hyphenated class path
     * @param string|null $kind Optional kind label for message context (e.g., 'Load' or 'Action')
     * @return AbstractRequest
     * @throws DebugException
     */
    private function instantiateRequestObject(string $request): AbstractRequest
    {
        $class = $this->normalizeClassName($request);
        if (class_exists($class) === false) {
            throw new DebugException($class .  ' Not found');
        }
        return new $class();
    }


    /**
     * Helper: call onNoAccess on the given request
     *
     * @param AbstractRequest $obj
     * @return void
     */
    private function onNoAccess(AbstractRequest $obj): void
    {
        $obj->onNoAccess();
    }

    /**
     * Displays collected output from loads and actions
     *
     * @return void
     */
    private function displayResult(): void
    {
        $this->templateEngine->display();
    }
}
