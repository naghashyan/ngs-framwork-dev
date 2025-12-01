<?php

declare(strict_types=1);

/**
 * NGS abstract load all loads should extends from this class
 * this class extends from AbstractRequest class
 * this class class content base functions that will help to
 * initialize loads
 *
     * @author Naghashyan Solutions <info@naghashyan.com>
     * @site https://naghashyan.com
     * @year 2007-2026
     * @version 5.0.0
     * @package ngs.framework
 *
 * This file is part of the NGS package.
 *
 * @copyright Naghashyan Solutions LLC
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace ngs\request;

use ngs\exceptions\NoAccessException;
use ngs\routes\NgsModuleResolver;
use ngs\routes\NgsRoute;
use ngs\util\NgsArgs;
use ngs\util\RequestContext;

abstract class AbstractLoad extends AbstractRequest
{
    public const REQUEST_TYPE = "load";

    protected array $parentParams = [];
    /**
     * Data that should be available for JSON responses when using a template engine.
     */
    private array $jsonParam = [];

    /**
     * Fully-qualified request name (e.g. loads.main.home) used by the mapper.
     */
    private string $loadName = '';

    /**
     * Name of the parent load when this load is nested inside another.
     */
    private ?string $parentLoadName = null;

    /**
     * Flag indicating that the current load is executed as a nested load.
     */
    private bool $isNestedLoad = false;

    /**
     * Wrapper load instance when this load is nested inside another load.
     */
    private ?AbstractLoad $ngsWrappingLoad = null;

    /**
     * Rendering strategy for the load (e.g. 'smarty' or 'json').
     */
    private ?string $ngsLoadType = null;

    /**
     * Raw request parameters passed into the current load (used mainly for nested loads).
     */
    private array $ngsRequestParams = [];

    /**
     * Fully qualified class name of the load for mapper bookkeeping.
     */
    private string $loadClassName = '';

    /**
     * Query-string key/value pairs that were resolved during routing.
     */
    private array $ngsQueryParams = [];

    /**
     * Hook for populating load state from the resolved route.
     * Extend this method in child classes to move route arguments into params or custom properties.
     */
    public function initialize(?NgsRoute $route = null): void
    {
        // Template method: concrete loads should populate data using $route arguments.
        // NOTE: No default behavior is defined because load initialization varies per module.
    }

    /**
     * Execute the load, hydrate template metadata, and register nested loads.
     *
     * @throws \ngs\exceptions\DebugException
     */
    public function service(): void
    {
        $this->load();
        $this->loadClassName = get_class($this);
        //initialize template engine pass load params to templater
        NGS()->getTemplateEngine()->setType($this->getNgsLoadType());
        if (!$this->isNestedLoad() && $this->getLoadName()) {
            NGS()->getLoadMapper()->setGlobalParentLoad($this->getLoadName());
        }
        $ns = get_class($this);
        $requestContext = NGS()->createDefinedInstance('REQUEST_CONTEXT', RequestContext::class);
        $resolver = NGS()->createDefinedInstance('MODULES_ROUTES_ENGINE', NgsModuleResolver::class);
        $moduleNS = ($resolver->resolveModule($requestContext->getRequestUri()) ?? NGS())->getName();
        $ns = substr($ns, strpos($ns, $moduleNS) + strlen($moduleNS) + 1);
        $ns = str_replace(['Load', '\\'], ['', '.'], $ns);
        $className = lcfirst(substr($ns, strrpos($ns, '.') + 1));
        $nameSpace = substr($ns, 0, strrpos($ns, '.'));
        $className = preg_replace_callback('/[A-Z]/', function ($m) {
            return '_' . strtolower($m[0]);
        }, $className);
        $nameSpace = str_replace('._', '.', $nameSpace);

        //TODO: ZN: this logic should be refactored
        $nestedLoads = [];
        //$nestedLoads = NGS()->getRoutesEngine()->getNestedRoutes($nameSpace . '.' . $className);
        $loadDefaultLoads = $this->getDefaultLoads();

        $defaultLoads = array_merge($loadDefaultLoads, $nestedLoads);

        //set nested loads for each load
        foreach ($defaultLoads as $key => $value) {
            $this->nest($key, $value);
        }

        NGS()->getLoadMapper()->setPermalink($this->getPermalink());
        NGS()->getLoadMapper()->setNgsQueryParams($this->getNgsQueryParams());
        $this->ngsInitializeTemplateEngine();
    }


    public function validate(): bool
    {
        // Override this in child classes when request-level validation is required.
        return true;
    }

    /**
     * @throws \ngs\exceptions\DebugException
     */
    final public function ngsInitializeTemplateEngine(): void
    {
        if ($this->getNgsLoadType() === 'json') {
            NGS()->define('JS_FRAMEWORK_ENABLE', false);
            NGS()->getTemplateEngine()->assign('ns', $this->getParams());
        } elseif ($this->getNgsLoadType() === 'smarty') {
            NGS()->getTemplateEngine()->assignParams($this->getJsonParams());
            NGS()->getTemplateEngine()->assign('ns', $this->getParams());
        }
    }

    /**
     * in this method implemented
     * nested load functional
     *
     * @access public
     * @param String $namespace
     * @param array $loadArr
     *
     * @return void
     * @throws NoAccessException
     * @throws \JsonException
     *
     */
    final public function nest(string $namespace, array $loadArr): void
    {
        $actionArr = NGS()->getRoutesEngine()->getLoadORActionByAction($loadArr['action']);
        $loadObj = new $actionArr['action']();
        //set that this load is nested
        $loadObj->setIsNestedLoad(true);
        $loadObj->setNgsParentLoadName($this->loadClassName);
        $loadObj->setNgsWrappingLoad($this);
        if (isset($loadArr['args'])) {
            NgsArgs::getInstance($loadObj->getNgsRequestUUID(), $loadArr['args']);
        }
        $loadObj->setLoadName($loadArr['action']);
        $loadObj->initialize();

        if (NGS()->getSessionManager()->validateRequest($loadObj) === false) {
            $loadObj->onNoAccess();
        }

        $loadObj->service();

        if (NGS()->isJsFrameworkEnable() && NGS()->getHttpUtils()->isAjaxRequest()) {
            NGS()->getLoadMapper()->setNestedLoads($this->getLoadName(), $loadArr['action'], $loadObj->getJsonParams());
        }
        if (!isset($this->params['inc'])) {
            $this->params['inc'] = [];
        }
        $this->setNestedLoadParams($namespace, $loadArr['action'], $loadObj);
        $this->params = array_merge($this->getParams(), $loadObj->getParentParams());
    }

    /**
     * @param string $namespace
     * @param string $fileNs
     * @param AbstractLoad $loadObj
     */
    protected function setNestedLoadParams(string $namespace, string $fileNs, AbstractLoad $loadObj): void
    {
        $this->params['inc'][$namespace]['filename'] = $loadObj->getTemplate();
        $this->params['inc'][$namespace]['params'] = $loadObj->getParams();
        $this->params['inc'][$namespace]['namespace'] = $fileNs;
        $this->params['inc'][$namespace]['jsonParam'] = $loadObj->getJsonParams();
        $this->params['inc'][$namespace]['parent'] = $this->getLoadName();
        $this->params['inc'][$namespace]['permalink'] = $loadObj->getPermalink();
    }

    private function setNgsParentLoadName(string $load): void
    {
        $this->parentLoadName = $load;
    }

    public function getNgsParentLoadName(): ?string
    {
        return $this->parentLoadName;
    }

    /**
     * this method add template varialble
     *
     * @access public
     * @param String $name
     * @param mixed $value
     *
     * @return void
     */
    final protected function addParentParam(string $name, mixed $value): void
    {
        $this->parentParams[$name] = $value;
    }

    /**
     * this method add json varialble
     *
     * @access public
     * @param String $name
     * @param mixed $value
     *
     * @return void
     */
    public function addJsonParam(string $name, mixed $value): void
    {
        $this->jsonParam[$name] = $value;
    }


    /**
     * Return parameters collected from parent loads.
     */
    protected function getParentParams(): array
    {
        return $this->parentParams;
    }

    /**
     * Return json params array
     */
    public function getJsonParams(): array
    {
        return $this->jsonParam;
    }

    /**
     * this abstract method should be replaced in childs load
     * for add nest laod
     *
     * @return array
     */
    public function getDefaultLoads(): array
    {
        return [];
    }

    /**
     * this abstract method should be replaced in childs load
     * for set load template
     *
     * @return string
     */
    public function getTemplate(): ?string
    {
        return null;
    }

    /**
     * check if load can be nested
     * @param string $namespace
     * @param AbstractLoad $load
     *
     * @return bool
     */
    public function isNestable(string $namespace, AbstractLoad $load): bool
    {
        return true;
    }

    /**
     * set true if load called from parent (if load is nested)
     *
     * @param boolean $isNestedLoad
     *
     * @return void
     */
    final public function setIsNestedLoad(bool $isNestedLoad): void
    {
        $this->isNestedLoad = $isNestedLoad;
    }

    /**
     * get true if load is nested
     *
     * @return boolean
     */
    final public function isNestedLoad(): bool
    {
        return $this->isNestedLoad;
    }

    protected function setNgsLoadType(?string $ngsLoadType): void
    {
        $this->ngsLoadType = $ngsLoadType;
    }

    /**
     * set load type default it is smarty
     *
     *
     * @return string $type
     */
    public function getNgsLoadType(): string
    {
        if ($this->ngsLoadType !== null) {
            return $this->ngsLoadType;
        }
        //todo add additional header ngs framework checker
        if (
            (isset($_SERVER['HTTP_ACCEPT']) && $_SERVER['HTTP_ACCEPT'] === 'application/json')
            || $this->getTemplate() === null
            || (is_string($this->getTemplate()) && str_contains($this->getTemplate(), '.json'))
        ) {
            $this->ngsLoadType = 'json';
        } else {
            $this->ngsLoadType = 'smarty';
        }
        return $this->ngsLoadType;
    }

    public function getResponseType(): string
    {
        return AbstractRequest::RESPONSE_TYPE_HTML;
    }

    /**
     * set load name
     *
     * @param string $name
     *
     * @return void
     */
    public function setLoadName(string $name): void
    {
        $this->loadName = $name;
    }

    /**
     * get load name
     *
     *
     * @return string loadName
     */
    public function getLoadName(): string
    {
        return $this->loadName;
    }

    /**
     * set wrapping load object(if load is nested)
     *
     * @param AbstractLoad $loadObj
     *
     * @return void
     */
    protected function setNgsWrappingLoad(AbstractLoad $loadObj): void
    {
        $this->ngsWrappingLoad = $loadObj;
    }

    /**
     * get wrapping load if load is nested
     *
     * @return AbstractLoad $ngsWrappingLoad
     */
    protected function getWrappingLoad(): ?AbstractLoad
    {
        return $this->ngsWrappingLoad;
    }

    protected function setNgsQueryParams(array $queryParamsArr): void
    {
        $this->ngsQueryParams = array_merge($queryParamsArr, $this->ngsQueryParams);
    }

    protected function getNgsQueryParams(): array
    {
        return $this->ngsQueryParams;
    }

    /**
     * get permalink
     *
     * @return string
     */
    public function getPermalink(): string
    {
        return '';
    }

    /**
     * @return array
     */
    public function getNgsRequestParams(): array
    {
        return $this->ngsRequestParams;
    }

    /**
     * @param array $ngsRequestParams
     */
    public function setNgsRequestParams(array $ngsRequestParams): void
    {
        $this->ngsRequestParams = $ngsRequestParams;
    }


    /**
     * this function invoked when user hasn't permistion
     *
     * @access
     * @return void
     */
    public function onNoAccess(): void
    {
    }

    /**
     * main load function for ngs loads
     *
     * @return void
     */
    abstract public function load();

    public function afterRequest(): void
    {
        $this->afterLoad();
    }


    public function afterLoad(): void
    {
        return;
    }
}
