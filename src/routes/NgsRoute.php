<?php

namespace ngs\routes;

/**
 * Class NgsRoute
 *
 * Represents a resolved route in the NGS framework.
 * All properties must be set via setters after construction.
 *
 * @property string|null $request           The fully qualified request identifier (e.g. 'loads.main.home')
 * @property array       $args              Positional or named parameters extracted from the URL or route constraints
 * @property bool        $matched           True if the route was matched/resolved, false otherwise
 * @property string|null $type              Type of request (e.g. 'load', 'action', 'file')
 * @property string|null $module            Module/namespace for this request
 * @property string|null $fileType          If file route: extension/type (e.g. 'css', 'jpg')
 * @property string|null $fileUrl           If file route: the file path/URL
 * @property string|null $notFoundRequest   The per-group 404 request identifier (if defined for this route group)
 */
class NgsRoute
{
    /**
     * @var string|null Fully qualified request identifier (e.g. 'loads.main.home')
     */
    private ?string $request = null;

    /**
     * @var array Route parameters (from URL segments, constraints, etc.)
     */
    private array $args = [];

    /**
     * @var bool True if a route was matched for this URL
     */
    private bool $matched = false;

    /**
     * @var string|null Route type (e.g. 'load', 'action', 'file')
     */
    private ?string $type = null;

    /**
     * @var \ngs\NgsModule|string|null Module instance or namespace for the route
     */
    private $module = null;

    /**
     * @var string|null File type/extension, for static file routes
     */
    private ?string $fileType = null;

    /**
     * @var string|null File path/URL, for static file routes
     */
    private ?string $fileUrl = null;

    /**
     * @var string|null Per-group 404 request identifier (for rendering the correct not found page)
     */
    private ?string $notFoundRequest = null;

    /**
     * Constructor: always use setters after construction.
     */
    public function __construct()
    {
        // Intentionally empty.
    }

    // === GETTERS AND SETTERS ===

    /**
     * Get the resolved request string.
     */
    public function getRequest(): ?string
    {
        return $this->request;
    }

    /**
     * Set the resolved request string.
     */
    public function setRequest(?string $request): void
    {
        $this->request = $request;
    }

    /**
     * Get the route arguments.
     */
    public function getArgs(): array
    {
        return $this->args;
    }

    /**
     * Set the route arguments.
     */
    public function setArgs(array $args): void
    {
        $this->args = $args;
    }

    /**
     * Was the route matched?
     */
    public function isMatched(): bool
    {
        return $this->matched;
    }

    /**
     * Set the matched flag.
     */
    public function setMatched(bool $matched): void
    {
        $this->matched = $matched;
    }

    /**
     * Get the route type.
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * Set the route type.
     */
    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    /**
     * Get the module or namespace.
     * 
     * @return \ngs\NgsModule|string|null The module instance or namespace
     */
    public function getModule()
    {
        return $this->module;
    }

    /**
     * Set the module or namespace.
     * 
     * @param \ngs\NgsModule|string|null $module The module instance or namespace
     */
    public function setModule($module): void
    {
        $this->module = $module;
    }

    /**
     * Get the module name.
     * 
     * For backward compatibility with code that expects a string.
     * 
     * @return string|null The module name
     */
    public function getModuleName(): ?string
    {
        if ($this->module instanceof \ngs\NgsModule) {
            return $this->module->getName();
        }

        return $this->module;
    }

    /**
     * Get the file type (for file/static routes).
     */
    public function getFileType(): ?string
    {
        return $this->fileType;
    }

    /**
     * Set the file type (for file/static routes).
     */
    public function setFileType(?string $fileType): void
    {
        $this->fileType = $fileType;
    }

    /**
     * Get the file URL (for file/static routes).
     */
    public function getFileUrl(): ?string
    {
        return $this->fileUrl;
    }

    /**
     * Set the file URL (for file/static routes).
     */
    public function setFileUrl(?string $fileUrl): void
    {
        $this->fileUrl = $fileUrl;
    }

    /**
     * Get the per-group not-found request identifier.
     */
    public function getNotFoundRequest(): ?string
    {
        return $this->notFoundRequest;
    }

    /**
     * Set the per-group not-found request identifier.
     */
    public function setNotFoundRequest(?string $notFoundRequest): void
    {
        $this->notFoundRequest = $notFoundRequest;
    }
}
