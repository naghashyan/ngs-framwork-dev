<?php

declare(strict_types=1);

/**
 * parent class for all ngs requests (loads/action)
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
use ngs\routes\NgsRoute;
use ngs\util\NgsArgs;
use ngs\util\Pusher;

abstract class AbstractRequest
{
    public const RESPONSE_TYPE_JSON = 'json';
    public const RESPONSE_TYPE_HTML = 'html';

    /**
     * Logical grouping identifier for the current request (e.g. for route groups).
     *
     * @var string|null
     */
    protected ?string $requestGroup = null;

    /**
     * Key-value payload that will be passed to template engines or API responses.
     */
    protected array $params = [];

    /**
     * HTTP status code that should be used for the request response.
     */
    protected int $ngsStatusCode = 200;

    /**
     * Resource hints to be sent via HTTP/2 push (grouped by resource type).
     */
    private array $ngsPushParams = ['link' => [], 'script' => [], 'img' => []];

    /**
     * Unique identifier for the request used when storing per-request arguments.
     */
    private ?string $ngsRequestUuid = null;

    abstract public function initialize(?NgsRoute $route = null): void;

    abstract public function getResponseType(): string;

    abstract public function validate(): bool;

    abstract public function service(): void;

    abstract public function getTemplate(): ?string;

    /**
     * default http status code
     * for OK response
     *
     *
     * @return void
     */
    public function setStatusCode(int $statusCode): void
    {
        $this->ngsStatusCode = $statusCode;
    }

    /**
     * default http status code
     * for OK response
     *
     *
     * @return integer 200
     */
    public function getStatusCode(): int
    {
        return $this->ngsStatusCode;
    }

    /**
     * default http status code
     * for ERROR response
     *
     *
     * @return integer 403
     */
    public function getErrorStatusCode(): int
    {
        return 403;
    }

    public function setRequestGroup(?string $requestGroup): void
    {
        $this->requestGroup = $requestGroup;
    }

    public function getRequestGroup(): ?string
    {
        return $this->requestGroup;
    }

    /**
     * @throws NoAccessException
     * @throws \ngs\exceptions\DebugException
     */
    public function redirectToLoad(string $load, array $args, int $statusCode = 200): void
    {
        $this->setStatusCode($statusCode);
        NgsArgs::getInstance()->setArgs($args);
        $actionArr = NGS()->getRoutesEngine()->getLoadORActionByAction($load);
        NGS()->getDispatcher()->loadPage($actionArr['action']);
    }

    /**
     * Add multiple parameters to the request payload.
     *
     * @param array|string $paramsArr
     */
    final public function addParams(array|string $paramsArr): void
    {
        $params = is_array($paramsArr) ? $paramsArr : [$paramsArr];
        $this->params = array_merge($this->params, $params);
    }

    /**
     * add single parameter
     *
     * @access public
     *
     * @param String $name
     * @param mixed $value
     *
     * @return void
     */
    final protected function addParam(string $name, mixed $value): void
    {
        $this->params[$name] = $value;
    }

    /**
     * this method return
     * assigned parameters
     *
     * @access public
     *
     * @return array
     *
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * do cancel load or actions
     *
     * @access public
     *
     * @throw NoAccessException
     */
    protected function cancel(): void
    {
    }


    abstract protected function onNoAccess(): void;

    // public abstract function getValidator(): void;

    protected function getValidator(): void
    {
        // TODO: Implement getValidator() method.
    }

    /**
     * public method helper method for do http redirect
     *
     * @access public
     *
     * @param string $url
     *
     * @return void
     * @throws \ngs\exceptions\DebugException
     */
    protected function redirect(string $url): void
    {
        NGS()->getHttpUtils()->redirect($url);
    }

    /**
     * set http2 push params
     * it will add in response header
     * supported types img, script and link
     *
     * @param string $type
     * @param string $value
     * @return bool
     */
    protected function setHttpPushParam(string $type, string $value): bool
    {
        if (!array_key_exists($type, $this->ngsPushParams)) {
            return false;
        }

        $this->ngsPushParams[$type][] = $value;

        return true;
    }

    /**
     * set http2 push params
     * it will add in response header
     * suppored types img, script and link
     *
     * @return void
     */
    protected function insertHttpPushParams(): void
    {
        foreach ($this->ngsPushParams['script'] as $script) {
            Pusher::getInstance()->src($script);
        }
        foreach ($this->ngsPushParams['link'] as $link) {
            Pusher::getInstance()->link($link);
        }
        foreach ($this->ngsPushParams['img'] as $img) {
            Pusher::getInstance()->img($img);
        }
    }

    protected function getNgsRequestUUID(): string
    {
        if (!$this->ngsRequestUuid) {
            $this->ngsRequestUuid = uniqid('ngs_', true);
        }
        return $this->ngsRequestUuid;
    }

    final public function args(): NgsArgs
    {
        return NgsArgs::getInstance($this->getNgsRequestUUID());
    }

    abstract protected function afterRequest(): void;
}
