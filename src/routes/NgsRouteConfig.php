<?php

namespace ngs\routes;

/**
 * Class NgsRouteConfig
 *
 * Data Transfer Object (DTO) for single route configuration.
 * Represents the structure of route configurations found in routes.json and main.json files.
 *
 * @package ngs\routes
 */
class NgsRouteConfig
{
    /**
     * @var string|null The action to execute (e.g., "loads.main.main")
     */
    private ?string $action = null;

    /**
     * @var string|null URL pattern with optional parameters (e.g., "user/[:userId]")
     */
    private ?string $route = null;

    /**
     * @var array Parameter validation rules (e.g., ["userId" => "[0-9]+"])
     */
    private array $constraints = [];

    /**
     * @var string|null HTTP method (GET, POST, PUT, DELETE, etc.)
     */
    private ?string $method = null;

    /**
     * @var array Route arguments
     */
    private array $args = [];

    /**
     * @var string|null Route type (load, action, file, etc.)
     */
    private ?string $type = null;

    /**
     * @var string|null Module name
     */
    private ?string $module = null;

    /**
     * @var string|null File type for static files (css, js, png, etc.)
     */
    private ?string $fileType = null;

    /**
     * @var string|null File URL for static files
     */
    private ?string $fileUrl = null;

    /**
     * @var string|null Request name
     */
    private ?string $request = null;

    /**
     * @var array Nested load configurations
     */
    private array $nestedLoad = [];

    /**
     * @var string|null Cached request type
     */
    private ?string $requestType = null;

    /**
     * @var string|null Cached request identifier
     */
    private ?string $requestIdentifier = null;

    /**
     * @var string|null Cached package
     */
    private ?string $package = null;

    /**
     * Create NgsRouteConfig from array
     *
     * @param array $config Route configuration array
     * @return self
     */
    public static function fromArray(array $config): self
    {
        $routeConfig = new self();

        $routeConfig->setAction($config['action'] ?? null);
        $routeConfig->setRoute($config['route'] ?? null);
        $routeConfig->setConstraints($config['constraints'] ?? []);
        $routeConfig->setMethod($config['method'] ?? null);
        $routeConfig->setArgs($config['args'] ?? []);
        $routeConfig->setType($config['type'] ?? null);
        $routeConfig->setModule($config['module'] ?? null);
        $routeConfig->setFileType($config['fileType'] ?? null);
        $routeConfig->setFileUrl($config['fileUrl'] ?? null);
        $routeConfig->setRequest($config['request'] ?? null);
        $routeConfig->setNestedLoad($config['nestedLoad'] ?? []);

        return $routeConfig;
    }

    /**
     * Convert to array
     *
     * @return array
     */
    public function toArray(): array
    {
        $result = [];

        if ($this->action !== null) {
            $result['action'] = $this->action;
        }
        if ($this->route !== null) {
            $result['route'] = $this->route;
        }
        if (!empty($this->constraints)) {
            $result['constraints'] = $this->constraints;
        }
        if ($this->method !== null) {
            $result['method'] = $this->method;
        }
        if (!empty($this->args)) {
            $result['args'] = $this->args;
        }
        if ($this->type !== null) {
            $result['type'] = $this->type;
        }
        if ($this->module !== null) {
            $result['module'] = $this->module;
        }
        if ($this->fileType !== null) {
            $result['fileType'] = $this->fileType;
        }
        if ($this->fileUrl !== null) {
            $result['fileUrl'] = $this->fileUrl;
        }
        if ($this->request !== null) {
            $result['request'] = $this->request;
        }
        if (!empty($this->nestedLoad)) {
            $result['nestedLoad'] = $this->nestedLoad;
        }

        return $result;
    }

    // Getters and Setters

    /**
     * @return string|null
     */
    public function getAction(): ?string
    {
        return $this->action;
    }

    /**
     * @param string|null $action
     * @return self
     */
    public function setAction(?string $action): self
    {
        $this->action = $action;
        $this->calculateActionDerivedValues();
        return $this;
    }

    /**
     * Calculate and cache derived values from action
     *
     * @return void
     */
    private function calculateActionDerivedValues(): void
    {
        if ($this->action === null) {
            $this->requestType = null;
            $this->requestIdentifier = null;
            $this->package = null;
            return;
        }

        $actionParts = explode('.', $this->action);
        if (empty($actionParts)) {
            $this->requestType = null;
            $this->requestIdentifier = null;
            $this->package = null;
            return;
        }

        // Calculate request type
        $firstElement = $actionParts[0];
        if ($firstElement === NGS()->get('LOADS_DIR')) {
            $this->requestType = \ngs\request\AbstractLoad::REQUEST_TYPE;
        } elseif ($firstElement === NGS()->get('ACTIONS_DIR')) {
            $this->requestType = \ngs\request\AbstractAction::REQUEST_TYPE;
        } else {
            $this->requestType = null;
        }

        // Calculate request identifier (last token)
        $this->requestIdentifier = end($actionParts);

        // Calculate package (intermediate tokens)
        if (count($actionParts) <= 2) {
            $this->package = null; // No intermediate tokens
        } else {
            // Remove first and last elements to get intermediate tokens
            $packageParts = array_slice($actionParts, 1, -1);
            $this->package = implode('.', $packageParts);
        }
    }

    /**
     * @return string|null
     */
    public function getRoute(): ?string
    {
        return $this->route;
    }

    /**
     * @param string|null $route
     * @return self
     */
    public function setRoute(?string $route): self
    {
        $this->route = $route;
        return $this;
    }

    /**
     * @return array
     */
    public function getConstraints(): array
    {
        return $this->constraints;
    }

    /**
     * @param array $constraints
     * @return self
     */
    public function setConstraints(array $constraints): self
    {
        $this->constraints = $constraints;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getMethod(): ?string
    {
        return $this->method;
    }

    /**
     * @param string|null $method
     * @return self
     */
    public function setMethod(?string $method): self
    {
        $this->method = $method;
        return $this;
    }

    /**
     * @return array
     */
    public function getArgs(): array
    {
        return $this->args;
    }

    /**
     * @param array $args
     * @return self
     */
    public function setArgs(array $args): self
    {
        $this->args = $args;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * @param string|null $type
     * @return self
     */
    public function setType(?string $type): self
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getModule(): ?string
    {
        return $this->module;
    }

    /**
     * @param string|null $module
     * @return self
     */
    public function setModule(?string $module): self
    {
        $this->module = $module;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getFileType(): ?string
    {
        return $this->fileType;
    }

    /**
     * @param string|null $fileType
     * @return self
     */
    public function setFileType(?string $fileType): self
    {
        $this->fileType = $fileType;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getFileUrl(): ?string
    {
        return $this->fileUrl;
    }

    /**
     * @param string|null $fileUrl
     * @return self
     */
    public function setFileUrl(?string $fileUrl): self
    {
        $this->fileUrl = $fileUrl;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getRequest(): ?string
    {
        return $this->request;
    }

    /**
     * @param string|null $request
     * @return self
     */
    public function setRequest(?string $request): self
    {
        $this->request = $request;
        return $this;
    }

    /**
     * @return array
     */
    public function getNestedLoad(): array
    {
        return $this->nestedLoad;
    }

    /**
     * @param array $nestedLoad
     * @return self
     */
    public function setNestedLoad(array $nestedLoad): self
    {
        $this->nestedLoad = $nestedLoad;
        return $this;
    }

    /**
     * Check if route has constraints
     *
     * @return bool
     */
    public function hasConstraints(): bool
    {
        return !empty($this->constraints);
    }

    /**
     * Check if route has nested loads
     *
     * @return bool
     */
    public function hasNestedLoad(): bool
    {
        return !empty($this->nestedLoad);
    }

    /**
     * Check if route has arguments
     *
     * @return bool
     */
    public function hasArgs(): bool
    {
        return !empty($this->args);
    }

    /**
     * Check if this is a static file route
     *
     * @return bool
     */
    public function isStaticFile(): bool
    {
        return $this->type === 'file' && $this->fileType !== null;
    }

    /**
     * Check if this is an action route
     *
     * @return bool
     */
    public function isAction(): bool
    {
        return $this->type === 'action' || ($this->action !== null && strpos($this->action, 'actions.') === 0);
    }

    /**
     * Check if this is a load route
     *
     * @return bool
     */
    public function isLoad(): bool
    {
        return $this->type === 'load' || ($this->action !== null && strpos($this->action, 'loads.') === 0);
    }

    /**
     * Get request type based on the action
     * Returns NgsAbstractLoad::REQUEST_TYPE if first element is 'loads'
     * Returns NgsAbstractAction::REQUEST_TYPE if first element is 'actions'
     *
     * @return string|null
     */
    public function getRequestType(): ?string
    {
        return $this->requestType;
    }

    /**
     * Get request identifier (last token from action)
     *
     * @return string|null
     */
    public function getRequestIdentifier(): ?string
    {
        return $this->requestIdentifier;
    }

    /**
     * Get package (intermediate tokens between first and last)
     *
     * @return string|null
     */
    public function getPackage(): ?string
    {
        return $this->package;
    }
}
