<?php
/**
 * NgsRoute class to encapsulate route properties
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

/**
 * Class NgsRoute - Encapsulates route properties
 * 
 * @package ngs\routes
 */
class NgsRoute implements \ArrayAccess
{
    /**
     * Action to execute when the route is matched
     */
    private ?string $action = null;

    /**
     * Arguments for the action
     */
    private array $args = [];

    /**
     * Route pattern
     */
    private ?string $route = null;

    /**
     * Constraints for route parameters
     */
    private array $constraints = [];

    /**
     * HTTP method for the route
     */
    private ?string $method = null;

    /**
     * Namespace for the action
     */
    private ?string $namespace = null;

    /**
     * Nested loads for the route
     */
    private array $nestedLoad = [];

    /**
     * Whether the route was matched
     */
    private bool $matched = false;

    /**
     * Type of the action (load or action)
     */
    private ?string $type = null;

    /**
     * Module name for the route
     */
    private ?string $module = null;

    /**
     * URL of the file for static file routes
     */
    private ?string $fileUrl = null;

    /**
     * Type of the file for static file routes
     */
    private ?string $fileType = null;

    /**
     * Constructor
     *
     * @param array|null $routeData Route data array (for backward compatibility)
     */
    public function __construct(?array $routeData = null) {
        if ($routeData !== null) {
            if (isset($routeData['action'])) {
                $this->setAction($routeData['action']);
            }

            if (isset($routeData['args'])) {
                $this->setArgs($routeData['args']);
            }

            if (isset($routeData['route'])) {
                $this->setRoute($routeData['route']);
            }

            if (isset($routeData['constraints'])) {
                $this->setConstraints($routeData['constraints']);
            }

            if (isset($routeData['method'])) {
                $this->setMethod($routeData['method']);
            }

            if (isset($routeData['namespace'])) {
                $this->setNamespace($routeData['namespace']);
            }

            if (isset($routeData['nestedLoad'])) {
                $this->setNestedLoad($routeData['nestedLoad']);
            }

            if (isset($routeData['matched'])) {
                $this->setMatched($routeData['matched']);
            }

            if (isset($routeData['type'])) {
                $this->setType($routeData['type']);
            }

            if (isset($routeData['module'])) {
                $this->setModule($routeData['module']);
            }

            if (isset($routeData['file_url'])) {
                $this->setFileUrl($routeData['file_url']);
            }

            if (isset($routeData['file_type'])) {
                $this->setFileType($routeData['file_type']);
            }
        }
    }

    /**
     * Converts the route object to an array
     *
     * @return array Route data array
     */
    public function toArray(): array
    {
        $result = [];

        if ($this->action !== null) {
            $result['action'] = $this->action;
        }

        if (!empty($this->args)) {
            $result['args'] = $this->args;
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

        if ($this->namespace !== null) {
            $result['namespace'] = $this->namespace;
        }

        if (!empty($this->nestedLoad)) {
            $result['nestedLoad'] = $this->nestedLoad;
        }

        if ($this->type !== null) {
            $result['type'] = $this->type;
        }

        if ($this->module !== null) {
            $result['module'] = $this->module;
        }

        if ($this->fileUrl !== null) {
            $result['file_url'] = $this->fileUrl;
        }

        if ($this->fileType !== null) {
            $result['file_type'] = $this->fileType;
        }

        $result['matched'] = $this->matched;

        return $result;
    }

    /**
     * Gets the action
     *
     * @return string|null Action
     */
    public function getAction(): ?string
    {
        return $this->action;
    }

    /**
     * Sets the action
     *
     * @param string $action Action
     * @return self
     */
    public function setAction(string $action): self
    {
        $this->action = $action;
        return $this;
    }

    /**
     * Gets the arguments
     *
     * @return array Arguments
     */
    public function getArgs(): array
    {
        return $this->args;
    }

    /**
     * Sets the arguments
     *
     * @param array $args Arguments
     * @return self
     */
    public function setArgs(array $args): self
    {
        $this->args = $args;
        return $this;
    }

    /**
     * Adds arguments to the existing arguments
     *
     * @param array $args Arguments to add
     * @return self
     */
    public function addArgs(array $args): self
    {
        $this->args = array_merge($this->args, $args);
        return $this;
    }

    /**
     * Gets the route pattern
     *
     * @return string|null Route pattern
     */
    public function getRoute(): ?string
    {
        return $this->route;
    }

    /**
     * Sets the route pattern
     *
     * @param string $route Route pattern
     * @return self
     */
    public function setRoute(string $route): self
    {
        $this->route = $route;
        return $this;
    }

    /**
     * Gets the constraints
     *
     * @return array Constraints
     */
    public function getConstraints(): array
    {
        return $this->constraints;
    }

    /**
     * Sets the constraints
     *
     * @param array $constraints Constraints
     * @return self
     */
    public function setConstraints(array $constraints): self
    {
        $this->constraints = $constraints;
        return $this;
    }

    /**
     * Gets the HTTP method
     *
     * @return string|null HTTP method
     */
    public function getMethod(): ?string
    {
        return $this->method;
    }

    /**
     * Sets the HTTP method
     *
     * @param string $method HTTP method
     * @return self
     */
    public function setMethod(string $method): self
    {
        $this->method = $method;
        return $this;
    }

    /**
     * Gets the namespace
     *
     * @return string|null Namespace
     */
    public function getNamespace(): ?string
    {
        return $this->namespace;
    }

    /**
     * Sets the namespace
     *
     * @param string $namespace Namespace
     * @return self
     */
    public function setNamespace(string $namespace): self
    {
        $this->namespace = $namespace;
        return $this;
    }

    /**
     * Gets the nested loads
     *
     * @return array Nested loads
     */
    public function getNestedLoad(): array
    {
        return $this->nestedLoad;
    }

    /**
     * Sets the nested loads
     *
     * @param array $nestedLoad Nested loads
     * @return self
     */
    public function setNestedLoad(array $nestedLoad): self
    {
        $this->nestedLoad = $nestedLoad;
        return $this;
    }

    /**
     * Gets whether the route was matched
     *
     * @return bool Whether the route was matched
     */
    public function isMatched(): bool
    {
        return $this->matched;
    }

    /**
     * Sets whether the route was matched
     *
     * @param bool $matched Whether the route was matched
     * @return self
     */
    public function setMatched(bool $matched): self
    {
        $this->matched = $matched;
        return $this;
    }

    /**
     * Gets the type of the action
     *
     * @return string|null Type of the action
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * Sets the type of the action
     *
     * @param string $type Type of the action
     * @return self
     */
    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Gets the module name
     *
     * @return string|null Module name
     */
    public function getModule(): ?string
    {
        return $this->module;
    }

    /**
     * Sets the module name
     *
     * @param string $module Module name
     * @return self
     */
    public function setModule(string $module): self
    {
        $this->module = $module;
        return $this;
    }

    /**
     * Gets the file URL
     *
     * @return string|null File URL
     */
    public function getFileUrl(): ?string
    {
        return $this->fileUrl;
    }

    /**
     * Sets the file URL
     *
     * @param string $fileUrl File URL
     * @return self
     */
    public function setFileUrl(string $fileUrl): self
    {
        $this->fileUrl = $fileUrl;
        return $this;
    }

    /**
     * Gets the file type
     *
     * @return string|null File type
     */
    public function getFileType(): ?string
    {
        return $this->fileType;
    }

    /**
     * Sets the file type
     *
     * @param string $fileType File type
     * @return self
     */
    public function setFileType(string $fileType): self
    {
        $this->fileType = $fileType;
        return $this;
    }

    /**
     * Magic method to access properties as array elements
     *
     * @param string $key Property name
     * @return mixed Property value
     */
    public function __get(string $key)
    {
        $getter = 'get' . ucfirst($key);
        if (method_exists($this, $getter)) {
            return $this->$getter();
        }

        return null;
    }

    /**
     * Magic method to set properties as array elements
     *
     * @param string $key Property name
     * @param mixed $value Property value
     * @return void
     */
    public function __set(string $key, $value): void
    {
        $setter = 'set' . ucfirst($key);
        if (method_exists($this, $setter)) {
            $this->$setter($value);
        }
    }

    /**
     * Magic method to check if a property exists
     *
     * @param string $key Property name
     * @return bool Whether the property exists
     */
    public function __isset(string $key): bool
    {
        $getter = 'get' . ucfirst($key);
        if (method_exists($this, $getter)) {
            return $this->$getter() !== null;
        }

        return false;
    }

    /**
     * ArrayAccess implementation to access properties as array elements
     *
     * @param mixed $offset Property name
     * @return mixed Property value
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->__get($offset);
    }

    /**
     * ArrayAccess implementation to set properties as array elements
     *
     * @param mixed $offset Property name
     * @param mixed $value Property value
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value): void
    {
        $this->__set($offset, $value);
    }

    /**
     * ArrayAccess implementation to check if a property exists
     *
     * @param mixed $offset Property name
     * @return bool Whether the property exists
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset): bool
    {
        return $this->__isset($offset);
    }

    /**
     * ArrayAccess implementation to unset a property
     *
     * @param mixed $offset Property name
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset): void
    {
        // Not implemented
    }
}
