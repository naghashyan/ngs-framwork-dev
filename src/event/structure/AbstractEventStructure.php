<?php

/**
 * AbstractEventStructure manager class
 *
     * @author Naghashyan Solutions <info@naghashyan.com>
     * @site https://naghashyan.com
 * @mail miakel.mkrtchyan@naghashyan.com
     * @year 2007-2026
     * @package ngs.framework
     * @version 5.0.0
 *
 */

namespace ngs\event\structure;

abstract class AbstractEventStructure
{
    private array $params = [];
    private array $attachemts = [];
    private ?array $customReceivers = [];
    private array $notificationParams = [];
    private string $emailSubject = "";

    public function __construct(array $params, array $attachemts = [], string $emailSubject = "")
    {
        $this->params = $params;
        $this->attachemts = $attachemts;
        $this->emailSubject = $emailSubject;
    }


    abstract public static function getEmptyInstance(): AbstractEventStructure;

    /**
     * can be added notification from UI
     *
     * @return bool
     */
    public function isVisible(): bool
    {
        return false;
    }


    public function getParams()
    {
        return $this->params;
    }

    /**
     * indicates if bulk supported for this event
     *
     * @return bool
     */
    public function bulkIsAvailable(): bool
    {
        return true;
    }

    /**
     * returns display name of the event
     * @return string
     */
    public function getEventName(): string
    {
        return get_class($this);
    }

    /**
     * returns display name of the event
     * @return string
     */
    public function getEventId(): string
    {
        $name = $this->getEventName();
        return md5($name);
    }

    /**
     * returns display name of the event
     * @return string
     */
    public function getEventClass(): string
    {
        $class = get_class($this);
        $classParts = explode("\\", $class);

        return $classParts[count($classParts) - 1];
    }

    /**
     * return title which will be seen in notification as title
     *
     * @return string
     */
    public function getEventTitle(): string
    {
        return "";
    }

    /**
     * returns list of varialbes which can be used in notification template
     *
     * @return array
     */
    public function getAvailableVariables(): array
    {
        return [];
    }

    /**
     * @return array
     */
    public function getAttachemts(): array
    {
        return $this->attachemts;
    }

    /**
     * @param array $attachemts
     */
    public function setAttachemts(array $attachemts): void
    {
        $this->attachemts = $attachemts;
    }


    /**
     * @return string
     */
    public function getEmailSubject(): string
    {
        return $this->emailSubject;
    }

    /**
     * @param string $emailSubject
     */
    public function setEmailSubject(string $emailSubject): void
    {
        $this->emailSubject = $emailSubject;
    }

    /**
     * @return array
     */
    public function getCustomReceivers(): ?array
    {
        return $this->customReceivers;
    }

    /**
     * @param ?array $customReceivers
     */
    public function setCustomReceivers(?array $customReceivers): void
    {
        $this->customReceivers = $customReceivers;
    }

    /**
     * @return array
     */
    public function getNotificationParams(): array
    {
        return $this->notificationParams;
    }

    /**
     * @param array $notificationParams
     */
    public function setNotificationParams(array $notificationParams): void
    {
        $this->notificationParams = $notificationParams;
    }
}
