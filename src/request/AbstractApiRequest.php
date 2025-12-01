<?php

declare(strict_types=1);

/**
 * NGS abstract load all loads that response is json should extends from this class
 * this class extends from AbstractRequest class
 * this class class content base functions that will help to
 * initialize loads
 *
     * @author Naghashyan Solutions <info@naghashyan.com>
     * @site https://naghashyan.com
     * @year 2007-2026
     * @package ngs.framework
     * @version 5.0.0
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

use ngs\routes\NgsRoute;

abstract class AbstractApiRequest extends AbstractRequest
{
    /**
     * HTTP method-specific action name that will be executed inside the API request.
     */
    protected ?string $actionMethod = null;

    /**
     * Validation rules for request payload/parameters (format decided per consumer).
     *
     * @var array<string, mixed>
     */
    protected array $requestValidators = [];

    /**
     * Validation rules for response payload (format decided per consumer).
     *
     * @var array<string, mixed>
     */
    protected array $responseValidators = [];

    public function initialize(?NgsRoute $route = null): void
    {
        $routeArgs = $route?->getArgs() ?? [];

        // Route argument keys are treated as metadata for the API dispatcher.
        $this->actionMethod = $routeArgs['action_method'] ?? null; // TODO: confirm final name for route-provided action method.
        $this->requestValidators = $routeArgs['request_params'] ?? [];
        $this->responseValidators = $routeArgs['response_params'] ?? [];

        $this->addParams($routeArgs);
    }

    public function getResponseType(): string
    {
        return self::RESPONSE_TYPE_JSON;
    }

    public function getActionMethod(): ?string
    {
        return $this->actionMethod;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRequestValidators(): array
    {
        return $this->requestValidators;
    }

    /**
     * @return array<string, mixed>
     */
    public function getResponseValidators(): array
    {
        return $this->responseValidators;
    }
}
