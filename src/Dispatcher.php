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

use ngs\event\EventManager;
use ngs\event\structure\AbstractEventStructure;
use ngs\event\subscriber\AbstractEventSubscriber;
use ngs\exceptions\InvalidUserException;
use ngs\exceptions\DebugException;
use ngs\exceptions\NgsErrorException;
use ngs\exceptions\NoAccessException;
use ngs\exceptions\NotFoundException;
use ngs\exceptions\RedirectException;
use ngs\util\NgsArgs;
use ngs\util\Pusher;

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
     * Manages matched routes and dispatches requests to appropriate handlers
     *
     * @param array|null $routesArr Routes array from the router
     *
     * @return void
     * @throws DebugException When a debug error occurs
     * @throws \JsonException When JSON processing fails
     */
    public function dispatch(?array $routesArr = null): void
    {
        $this->getSubscribersAndSubscribeToEvents();
        try {
            $routesEngine = NGS()->createDefinedInstance('ROUTES_ENGINE', \ngs\routes\NgsRoutes::class);
            $httpUtils = NGS()->createDefinedInstance('REQUEST_CONTEXT', \ngs\util\RequestContext::class);
            $templateEngine = NGS()->createDefinedInstance('TEMPLATE_ENGINE', \ngs\templater\NgsTemplater::class);

            if ($routesArr === null) {
                $routesArr = $routesEngine->getDynamicLoad($httpUtils->getRequestUri());
            }

            if (array_key_exists('file_url', $routesArr) && str_contains($routesArr['file_url'], 'js/ngs')) {
                $routesArr['file_url'] = str_replace("js/ngs", "js/admin/ngs", $routesArr['file_url']);
            }

            if ($routesArr['matched'] === false) {
                throw new NotFoundException('Load/Action Not found');
            }

            if (isset($routesArr['args'])) {
                NgsArgs::getInstance()->setArgs($routesArr['args']);
            }

            switch ($routesArr['type']) {
                case 'load':
                    if (isset($_GET['ngsValidate']) && $_GET['ngsValidate'] === 'true') {
                        $this->validate($routesArr['action']);
                    } elseif (isset(NGS()->args()->args()['ngsValidate']) && NGS()->args()->args()['ngsValidate']) {
                        $this->validate($routesArr['action']);
                    } else {
                        $this->loadPage($routesArr['action']);
                    }
                    break;

                case 'api_load':
                    $this->loadApiPage($routesArr);
                    // no break intentional - fall through to action case

                case 'action':
                    $this->doAction($routesArr['action']);
                    break;

                case 'api_action':
                    $this->doApiAction($routesArr);
                    exit;

                case 'file':
                    $this->streamStaticFile($routesArr);
                    break;
            }
        } catch (DebugException $ex) {
            $envConstantValue = NGS()->get('ENVIRONMENT');
            $currentEnvironment = 'production'; // Default

            if ($envConstantValue === 'development' || $envConstantValue === 'staging') {
                $currentEnvironment = $envConstantValue;
            }

            if ($currentEnvironment !== 'production') {
                $ex->display();
                return;
            }

            $routesArr = $routesEngine->getNotFoundLoad();
            if ($routesArr === null || $this->isRedirect === true) {
                echo '404';
                exit;
            }

            $this->isRedirect = true;
            $this->dispatch($routesArr);
        } catch (RedirectException $ex) {
            $httpUtils->redirect($ex->getRedirectTo());
        } catch (NotFoundException $ex) {
            try {
                if ($ex->getRedirectUrl() !== '') {
                    $httpUtils->redirect($ex->getRedirectUrl());
                    return;
                }

                $routesArr = $routesEngine->getNotFoundLoad();
                if ($routesArr === null || $this->isRedirect === true) {
                    echo '404';
                    exit;
                }

                $this->isRedirect = true;
                $this->dispatch($routesArr);
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

            if (NGS()->get('ENVIRONMENT') !== 'production') {
                var_dump($error);
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
            // Convert hyphenated namespace to proper namespace format
            $action = str_replace('-', '\\', $action);

            if (class_exists($action) === false) {
                throw new DebugException($action . ' Load Not found');
            }

            $loadObj = new $action();
            $loadObj->initialize();

            if (!$this->validateRequest($loadObj)) {
                $loadObj->onNoAccess();
            }

            $loadObj->setLoadName(NGS()->createDefinedInstance('ROUTES_ENGINE', \ngs\routes\NgsRoutes::class)->getContentLoad());
            $loadObj->service();

            $templateEngine = NGS()->createDefinedInstance('TEMPLATE_ENGINE', \ngs\templater\NgsTemplater::class);
            $templateEngine->setType($loadObj->getNgsLoadType());
            $templateEngine->setTemplate($loadObj->getTemplate());

            $loadMapper = NGS()->createDefinedInstance('LOAD_MAPPER', \ngs\routes\NgsLoadMapper::class);
            $templateEngine->setPermalink($loadMapper->getNgsPermalink());

            if (NGS()->get('SEND_HTTP_PUSH')) {
                Pusher::getInstance()->push();
            }

            $this->displayResult();

            $this->finishRequest();
            $loadObj->afterRequest();
        } catch (NoAccessException $ex) {
            if (isset($loadObj) && is_object($loadObj)) {
                $loadObj->onNoAccess();
            }
            throw $ex;
        } catch (InvalidUserException $ex) {
            $this->handleInvalidUserAndNoAccessException($ex);
        }
    }

    /**
     * Handles API load requests
     *
     * @param array $routesArr The routes array containing action and parameters
     * 
     * @return void
     * @throws DebugException When the load class is not found
     * @throws InvalidUserException When user is invalid
     * @throws NoAccessException When access is denied
     */
    public function loadApiPage(array $routesArr): void
    {
        try {
            $action = $routesArr['action'];
            $action = str_replace('-', '\\', $action);

            if (class_exists($action) === false) {
                throw new DebugException($action . ' Load Not found');
            }

            /** @var NgsApiAction $loadObj */
            $loadObj = new $action();
            $loadObj->setAction($routesArr['action_method']);
            $loadObj->setRequestValidators($routesArr['request_params']);
            $loadObj->setResponseValidators($routesArr['response_params']);
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

            if (NGS()->get('SEND_HTTP_PUSH')) {
                Pusher::getInstance()->push();
            }

            $this->displayResult();
            $this->finishRequest();

            if (is_object($loadObj)) {
                $loadObj->afterRequest();
            }
        } catch (NoAccessException $ex) {
            if (isset($loadObj) && is_object($loadObj)) {
                $loadObj->onNoAccess();
            }
            throw $ex;
        } catch (InvalidUserException $ex) {
            if (isset($loadObj) && is_object($loadObj)) {
                $loadObj->onNoAccess();
            }
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
            if (class_exists($action) === false) {
                throw new DebugException($action . ' Load Not found');
            }

            $loadObj = new $action();
            $loadObj->initialize();

            if (!$this->validateRequest($loadObj)) {
                $loadObj->onNoAccess();
            }

            $loadObj->setLoadName(NGS()->createDefinedInstance('ROUTES_ENGINE', \ngs\routes\NgsRoutes::class)->getContentLoad());
            $loadObj->validate();

            // Passing arguments
            $templateEngine = NGS()->createDefinedInstance('TEMPLATE_ENGINE', \ngs\templater\NgsTemplater::class);
            $templateEngine->setType('json');
            $templateEngine->assignJsonParams($loadObj->getParams());

            $this->displayResult();
            $this->finishRequest();

            $loadObj->afterRequest();
        } catch (NoAccessException $ex) {
            if (isset($loadObj) && is_object($loadObj)) {
                $loadObj->onNoAccess();
            }
            throw $ex;
        } catch (InvalidUserException $ex) {
            if (isset($loadObj) && is_object($loadObj)) {
                $loadObj->onNoAccess();
            }
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
            // Convert hyphenated namespace to proper namespace format
            $action = str_replace('-', '\\', $action);

            if (class_exists($action) === false) {
                throw new DebugException($action . ' Action Not found');
            }

            $actionObj = new $action();
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
            if (isset($actionObj) && is_object($actionObj)) {
                $actionObj->onNoAccess();
            }
            throw $ex;
        } catch (InvalidUserException $ex) {
            $this->handleInvalidUserAndNoAccessException($ex);
        }
    }

    /**
     * Handles API action requests
     *
     * @param array $routesArr The routes array containing action and parameters
     * 
     * @return void
     * @throws DebugException When the action class is not found
     * @throws InvalidUserException When user is invalid
     * @throws NoAccessException When access is denied
     */
    private function doApiAction(array $routesArr): void
    {
        try {
            $action = $routesArr['action'];
            $action = str_replace('-', '\\', $action);

            if (class_exists($action) === false) {
                throw new DebugException($action . ' Action Not found');
            }

            $actionObj = new $action();
            $actionObj->setAction($routesArr['action_method']);
            $actionObj->setRequestValidators($routesArr['request_params']);
            $actionObj->setResponseValidators($routesArr['response_params']);
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
            if (isset($actionObj) && is_object($actionObj)) {
                $actionObj->onNoAccess();
            }
            throw $ex;
        } catch (InvalidUserException $ex) {
            if (isset($actionObj) && is_object($actionObj)) {
                $actionObj->onNoAccess();
            }
            throw $ex;
        }
    }

    /**
     * Streams a static file to the client
     *
     * @param array $fileArr The file information array
     * 
     * @return void
     */
    private function streamStaticFile(array $fileArr): void
    {
        $moduleDir = NGS()->getModuleDirByNS($fileArr['module']);
        $publicDirForModule = realpath($moduleDir . '/' . NGS()->get('PUBLIC_DIR'));
        $filePath = realpath($publicDirForModule . '/' . $fileArr['file_url']);

        if (file_exists($filePath)) {
            $streamer = NGS()->createDefinedInstance('FILE_UTILS', \ngs\util\FileUtils::class);
        } else {
            switch ($fileArr['file_type']) {
                case 'js':
                    $streamer = NGS()->createDefinedInstance('JS_BUILDER', \ngs\util\JsBuilderV2::class);
                    break;

                case 'css':
                    $streamer = NGS()->createDefinedInstance('CSS_BUILDER', \ngs\util\CssBuilder::class);
                    break;

                case 'less':
                    $streamer = NGS()->createDefinedInstance('LESS_BUILDER', \ngs\util\LessBuilder::class);
                    break;

                case 'sass':
                    $streamer = NGS()->createDefinedInstance('SASS_BUILDER', \ngs\util\SassBuilder::class);
                    break;

                default:
                    $streamer = NGS()->createDefinedInstance('FILE_UTILS', \ngs\util\FileUtils::class);
                    break;
            }
        }

        $streamer->streamFile($fileArr['module'], $fileArr['file_url']);
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
        $httpUtils = NGS()->createDefinedInstance('REQUEST_CONTEXT', \ngs\util\RequestContext::class);
        $templateEngine = NGS()->createDefinedInstance('TEMPLATE_ENGINE', \ngs\templater\NgsTemplater::class);

        // For non-AJAX requests, redirect to the specified URL
        if (!$httpUtils->isAjaxRequest() && !NGS()->getDefinedValue('display_json')) {
            $httpUtils->redirect($ex->getRedirectTo());
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
        return NGS()->createDefinedInstance('SESSION_MANAGER', \ngs\session\AbstractSessionManager::class)->validateRequest($request);
    }


    /**
     * Loads event subscribers from configuration files and subscribes to their events
     *
     * @param bool $loadAll Whether to load subscribers from all modules
     * 
     * @return void
     * @throws \Exception When an invalid subscriber is encountered
     */
    public function getSubscribersAndSubscribeToEvents(bool $loadAll = false): void
    {
        $confDir = NGS()->get('CONF_DIR');
        $ngsCmsNs = NGS()->get('NGS_CMS_NS');
        $adminToolsSubscribersPath = NGS()->getModuleDirByNS($ngsCmsNs) . '/' . $confDir . '/event_subscribers.json';
        $adminToolsSubscribers = realpath($adminToolsSubscribersPath);

        $subscribers = [];
        if ($adminToolsSubscribers && file_exists($adminToolsSubscribers)) {
            $subscribers = json_decode(file_get_contents($adminToolsSubscribers), true);
        }

        if ($loadAll) {
            // Load subscribers from all modules
            $ngsRoot = NGS()->get('NGS_ROOT');
            $ngsModulesRoutes = NGS()->get('NGS_MODULS_ROUTS');
            $moduleRouteFile = realpath($ngsRoot . '/' . $confDir . '/' . $ngsModulesRoutes);

            if ($moduleRouteFile) {
                $modulesData = json_decode(file_get_contents($moduleRouteFile), true);
                $modules = $this->getModules($modulesData);

                foreach ($modules as $module) {
                    $moduleSubscribersPath = NGS()->getModuleDirByNS($module) . '/' . $confDir . '/event_subscribers.json';
                    $moduleSubscribersFile = realpath($moduleSubscribersPath);

                    if ($moduleSubscribersFile && file_exists($moduleSubscribersFile)) {
                        $moduleSubscribers = json_decode(file_get_contents($moduleSubscribersFile), true);
                        $subscribers = $this->mergeSubscribers($subscribers, $moduleSubscribers);
                    }
                }
            }
        } else {
            // Load subscribers from the main module only
            $moduleSubscribersPath = NGS()->get('NGS_ROOT') . '/' . $confDir . '/event_subscribers.json';
            $moduleSubscribersFile = realpath($moduleSubscribersPath);

            if ($moduleSubscribersFile && file_exists($moduleSubscribersFile)) {
                $moduleSubscribers = json_decode(file_get_contents($moduleSubscribersFile), true);
                $subscribers = $this->mergeSubscribers($subscribers, $moduleSubscribers);
            }
        }

        $this->subscribeToSubscribersEvents($subscribers);
    }


    /**
     * Returns an array of module directories from the modules data
     *
     * @param array $modulesData The modules configuration data
     * 
     * @return array Array of module directories
     */
    private function getModules(array $modulesData): array
    {
        if (!isset($modulesData['default'])) {
            return [];
        }

        $result = [];

        foreach ($modulesData['default'] as $type => $modules) {
            if ($type === 'default') {
                // Handle the default module
                if (!in_array($modules['dir'], $result, true)) {
                    $result[] = $modules['dir'];
                }
            } else {
                // Handle other module types
                foreach ($modules as $info) {
                    if (is_array($info) && !in_array($info['dir'], $result, true)) {
                        $result[] = $info['dir'];
                    }
                }
            }
        }

        return $result;
    }


    /**
     * Merges two subscriber arrays without duplication
     *
     * @param array $oldSubscribers The existing subscribers array
     * @param array $newSubscribers The new subscribers to merge
     * 
     * @return array The merged subscribers array
     */
    private function mergeSubscribers(array $oldSubscribers, array $newSubscribers): array
    {
        foreach ($newSubscribers as $newSubscriber) {
            if (!$this->subscriptionExists($oldSubscribers, $newSubscriber)) {
                $oldSubscribers[] = $newSubscriber;
            }
        }

        return $oldSubscribers;
    }


    /**
     * Checks if a subscription already exists in the list
     *
     * @param array $subscriptions The existing subscriptions array
     * @param array $newSubscriptionData The new subscription data to check
     * 
     * @return bool True if the subscription exists, false otherwise
     */
    private function subscriptionExists(array $subscriptions, array $newSubscriptionData): bool
    {
        foreach ($subscriptions as $subscription) {
            if ($subscription['class'] === $newSubscriptionData['class']) {
                return true;
            }
        }

        return false;
    }


    /**
     * Stores all visible events with their parameters
     *
     * @var array
     */
    private array $allVisibleEvents = [];

    /**
     * Returns all visible events
     *
     * @return array Array of visible events
     */
    public function getVisibleEvents(): array
    {
        return $this->allVisibleEvents;
    }

    /**
     * Subscribes to each subscriber's events
     *
     * @param array $subscribers Array of subscribers to process
     * 
     * @return void
     * @throws \Exception When an invalid subscriber is encountered
     * @throws \InvalidArgumentException When an invalid event structure class is provided
     */
    private function subscribeToSubscribersEvents(array $subscribers): void
    {
        $eventManager = EventManager::getInstance();

        foreach ($subscribers as $subscriber) {
            /** @var AbstractEventSubscriber $subscriberObject */
            $subscriberObject = new $subscriber['class']();

            if (!$subscriberObject instanceof AbstractEventSubscriber) {
                throw new \Exception('Invalid subscriber: ' . $subscriber['class']);
            }

            $subscriptions = $subscriberObject->getSubscriptions();

            foreach ($subscriptions as $eventStructClass => $handlerName) {
                /** @var AbstractEventStructure $eventStructExample */
                if (!is_a($eventStructClass, AbstractEventStructure::class, true)) {
                    throw new \InvalidArgumentException('Invalid event structure class: ' . $eventStructClass);
                }

                $eventStructExample = $eventStructClass::getEmptyInstance();
                $availableParams = $eventStructExample->getAvailableVariables();
                $eventId = $eventStructExample->getEventId();

                // Store visible events for later use
                if ($eventStructExample->isVisible() && !isset($this->allVisibleEvents[$eventId])) {
                    $this->allVisibleEvents[$eventId] = [
                        'name' => $eventStructExample->getEventName(),
                        'bulk_is_available' => $eventStructExample->bulkIsAvailable(),
                        'params' => $availableParams
                    ];
                }

                $eventManager->subscribeToEvent($eventStructClass, $subscriberObject, $handlerName);
            }
        }
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
     * Displays collected output from loads and actions
     *
     * @return void
     */
    private function displayResult(): void
    {
        NGS()->createDefinedInstance('TEMPLATE_ENGINE', \ngs\templater\NgsTemplater::class)->display();
    }
}
