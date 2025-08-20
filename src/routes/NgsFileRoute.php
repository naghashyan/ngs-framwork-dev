<?php

namespace ngs\routes;

/**
 * Class NgsFileRoute
 *
 * Represents a resolved file route in the NGS framework.
 * Extends NgsRoute to add file-specific functionality.
 * All properties must be set via setters after construction.
 *
 * @property string|null $fileType          File type/extension (e.g. 'css', 'jpg', 'less', 'sass')
 * @property string|null $fileUrl           File path/URL
 */
class NgsFileRoute extends NgsRoute
{
    public const TYPE = 'file';

    /**
     * @var string|null File type/extension, for static file routes
     */
    private ?string $fileType = null;

    /**
     * @var string|null File path/URL, for static file routes
     */
    private ?string $fileUrl = null;

    /**
     * Constructor: always use setters after construction.
     */
    public function __construct()
    {
        parent::__construct();
        // Set type to 'file' by default for file routes
        $this->setType(self::TYPE);
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
     * Check for special file types and update file type accordingly
     *
     * Checks if the route contains special file types like less or sass.
     * This method encapsulates file-specific logic that was previously in NgsRoutesResolver.
     *
     * @param array $filePieces File path pieces
     * @return void
     */
    public function processSpecialFileTypes(array $filePieces): void
    {
        if ($this->getFileType() === 'css') {
            foreach ($filePieces as $urlPath) {
                if ($urlPath === 'less' || $urlPath === 'sass') {
                    $this->setFileType($urlPath);
                    break;
                }
            }
        }
    }
}