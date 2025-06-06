<?php

/**
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

class Dispatcher
{
    private bool $isRedirect = false;

    /**
     * this method manage mathced routes
     *
     * @param array|null $routesArr
     *
     * @return void
     * @throws DebugException
     * @throws \JsonException
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
                    // $templateEngine initialized already
                    if (isset($_GET['ngsValidate']) && $_GET['ngsValidate'] === 'true') {
                        $this->validate($routesArr['action']);
                    } elseif (isset(NGS()->args()->args()['ngsValidate']) && NGS()->args()->args()['ngsValidate']) {
                        $this->validate($routesArr['action']);
                    } else {
                        $this->loadPage($routesArr['action']);
                    }
                    break;
                case 'api_load':
                    // $templateEngine initialized already
                    $this->loadApiPage($routesArr);
                    // no break
                case 'action':
                    // $templateEngine initialized already
                    $this->doAction($routesArr['action']);
                    break;
                case 'api_action':
                    // $templateEngine initialized already
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
                if ($routesArr == null || $this->isRedirect === true) {
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
            var_dump($error);
            die();
        }
    }

    /**
     * manage ngs loads
     * initialize load object
     * verify access
     * display collected output from loads
     *
     * @param string $action
     *
     * @return void
     * @throws DebugException
     * @throws NoAccessException
     */
    public function loadPage(string $action): void
    {
        try {
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

            if (PHP_SAPI === 'fpm-fcgi') {
                session_write_close();
                fastcgi_finish_request();
            }
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
     * load for api load
     *
     * @param array $routesArr
     * @throws DebugException
     * @throws InvalidUserException
     * @throws NoAccessException
     */
    public function loadApiPage(array $routesArr)
    {
        try {
            $action = $routesArr['action'];
            $action = str_replace('-', '\\', $action);
            if (class_exists($action) == false) {
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
            //$loadObj->setLoadName(NGS()->getRoutesEngine()->getContentLoad());
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

            if (php_sapi_name() === 'fpm-fcgi') {
                session_write_close();
                fastcgi_finish_request();
            }
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
            //passing arguments
            $templateEngine = NGS()->createDefinedInstance('TEMPLATE_ENGINE', \ngs\templater\NgsTemplater::class);
            $templateEngine->setType('json');
            $templateEngine->assignJsonParams($loadObj->getParams());
            $this->displayResult();
            if (PHP_SAPI === 'fpm-fcgi') {
                session_write_close();
                fastcgi_finish_request();
            }
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
     * manage ngs action
     * initialize action object
     * verify access
     * display action output
     *
     * @param string $action
     *
     * @return void
     *
     */
    private function doAction(string $action)
    {
        try {
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
            //passing arguments
            $templateEngine = NGS()->createDefinedInstance('TEMPLATE_ENGINE', \ngs\templater\NgsTemplater::class);
            $templateEngine->setType('json');
            $templateEngine->assignJsonParams($actionObj->getParams());
            $this->displayResult();
            if (php_sapi_name() === 'fpm-fcgi') {
                session_write_close();
                fastcgi_finish_request();
            }
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
     * do action for api action
     *
     * @param array $routesArr
     * @throws DebugException
     * @throws InvalidUserException
     * @throws NoAccessException
     */
    private function doApiAction(array $routesArr)
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
            //passing arguments
            $templateEngine = NGS()->createDefinedInstance('TEMPLATE_ENGINE', \ngs\templater\NgsTemplater::class);
            $templateEngine->setType('json');
            $templateEngine->assignJsonParams($actionObj->getParams());
            $this->displayResult();
            if (php_sapi_name() === 'fpm-fcgi') {
                session_write_close();
                fastcgi_finish_request();
            }
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

    private function streamStaticFile($fileArr)
    {
        $moduleDir = NGS()->getModuleDirByNS($fileArr['module']);
        $publicDirForModule = realpath($moduleDir . '/' . NGS()->get('PUBLIC_DIR'));
        $filePath = realpath($publicDirForModule . '/' . $fileArr['file_url']);
        if (file_exists($filePath)) {
            $stramer = NGS()->createDefinedInstance('FILE_UTILS', \ngs\util\FileUtils::class);
        } else {
            switch ($fileArr['file_type']) {
                case 'js':
                    $stramer = NGS()->createDefinedInstance('JS_BUILDER', \ngs\util\JsBuilderV2::class);
                    break;
                case 'css':
                    $stramer = NGS()->createDefinedInstance('CSS_BUILDER', \ngs\util\CssBuilder::class);
                    break;
                case 'less':
                    $stramer = NGS()->createDefinedInstance('LESS_BUILDER', \ngs\util\LessBuilder::class);
                    break;
                case 'sass':
                    $stramer = NGS()->createDefinedInstance('SASS_BUILDER', \ngs\util\SassBuilder::class);
                    break;
                default:
                    $stramer = NGS()->createDefinedInstance('FILE_UTILS', \ngs\util\FileUtils::class);
                    break;
            }
        }
        $stramer->streamFile($fileArr['module'], $fileArr['file_url']);
    }

    /**
     * @param $ex
     * @throws DebugException
     */
    private function handleInvalidUserAndNoAccessException($ex): void
    {
        $httpUtils = NGS()->createDefinedInstance('REQUEST_CONTEXT', \ngs\util\RequestContext::class);
        $templateEngine = NGS()->createDefinedInstance('TEMPLATE_ENGINE', \ngs\templater\NgsTemplater::class);

        if (!$httpUtils->isAjaxRequest() && !NGS()->getDefinedValue('display_json')) {
            $httpUtils->redirect($ex->getRedirectTo());
            return;
        }
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
     * validate request load/action access permissions
     *
     * @param object $request
     *
     * @return boolean
     *
     */
    private function validateRequest($request)
    {
        if (NGS()->createDefinedInstance('SESSION_MANAGER', \ngs\session\AbstractSessionManager::class)->validateRequest($request)) {
            return true;
        }
        return false;
    }


    /**
     * subscribe to all events
     *
     */
    public function getSubscribersAndSubscribeToEvents(bool $loadAll = false)
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
            $ngsRoot = NGS()->get('NGS_ROOT');
            $ngsModulesRoutes = NGS()->get('NGS_MODULS_ROUTS');
            $moduleRouteFile = realpath($ngsRoot . '/' . $confDir . '/' . $ngsModulesRoutes);
            if ($moduleRouteFile) {
                $modulesData = json_decode(file_get_contents($moduleRouteFile), true);
                $modules = $this->getModules($modulesData);
                foreach ($modules as $module) {
                    $moduleSubscribersPath = NGS()->getModuleDirByNS($module) . '/' . $confDir . '/event_subscribers.json';
                    $modulSubscribersFile = realpath($moduleSubscribersPath);
                    if ($modulSubscribersFile && file_exists($modulSubscribersFile)) {
                        $moduleSubscribers = json_decode(file_get_contents($modulSubscribersFile), true);
                        $subscribers = $this->mergeSubscribers($subscribers, $moduleSubscribers);
                    }
                }
            }
        } else {
            $moduleSubscribersPath = NGS()->get('NGS_ROOT') . '/' . $confDir . '/event_subscribers.json';
            $modulSubscribersFile = realpath($moduleSubscribersPath);
            if ($modulSubscribersFile && file_exists($modulSubscribersFile)) {
                $moduleSubscribers = json_decode(file_get_contents($modulSubscribersFile), true);
                $subscribers = $this->mergeSubscribers($subscribers, $moduleSubscribers);
            }
        }

        $this->subscribeToSubscribersEvents($subscribers);
    }


    /**
     * returns modules dirs
     *
     * @param array $modulesData
     * @return array
     */
    private function getModules(array $modulesData)
    {
        if (!isset($modulesData['default'])) {
            return [];
        }
        $result = [];

        foreach ($modulesData['default'] as $type => $modules) {
            if ($type === 'default') {
                if (!in_array($modules['dir'], $result, true)) {
                    $result[] = $modules['dir'];
                }
            } else {
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
     * merge 2 subscribers array without duplication
     *
     * @param array $oldSubscribers
     * @param array $newSubscribers
     * @return array
     */
    private function mergeSubscribers(array $oldSubscribers, array $newSubscribers)
    {
        foreach ($newSubscribers as $newSubscriber) {
            if (!$this->subscriptionExsits($oldSubscribers, $newSubscriber)) {
                $oldSubscribers[] = $newSubscriber;
            }
        }

        return $oldSubscribers;
    }


    /**
     * indicates if subscriber already exists in list
     *
     * @param array $subscriptions
     * @param array $newSubscriptionData
     * @return bool
     */
    private function subscriptionExsits(array $subscriptions, array $newSubscriptionData)
    {
        foreach ($subscriptions as $subscription) {
            if ($subscription['class'] === $newSubscriptionData['class']) {
                return true;
            }
        }

        return false;
    }


    private $allVisibleEvents = [];

    public function getVisibleEvents()
    {
        return $this->allVisibleEvents;
    }

    /**
     * subscribe to each subscriber events
     *
     * @param $subscribers
     * @throws \Exception
     */
    private function subscribeToSubscribersEvents(array $subscribers)
    {
        $eventManager = EventManager::getInstance();
        foreach ($subscribers as $subscriber) {
            /** @var AbstractEventSubscriber $subscriberObject */
            $subscriberObject = new $subscriber['class']();
            if (!$subscriberObject instanceof AbstractEventSubscriber) {
                throw new \Exception('wrong subscriber ' . $subscriber['class']);
            }

            $subscriptions = $subscriberObject->getSubscriptions();
            foreach ($subscriptions as $eventStructClass => $handlerName) {
                /** @var AbstractEventStructure $eventStructExample */
                if (!is_a($eventStructClass, AbstractEventStructure::class, true)) {
                    throw new \InvalidArgumentException();
                }
                $eventStructExample = $eventStructClass::getEmptyInstance();
                $availableParams = $eventStructExample->getAvailableVariables();
                $eventId = $eventStructExample->getEventId();
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
     * display collected output
     * from loads and actions
     *
     *
     * @return void
     */
    private function displayResult(): void
    {
        NGS()->createDefinedInstance('TEMPLATE_ENGINE', \ngs\templater\NgsTemplater::class)->display();
    }
}
