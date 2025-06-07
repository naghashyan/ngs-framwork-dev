<?php

namespace ngs;

use Composer\Factory;
use Composer\Package\RootPackageInterface;
use ngs\event\EventManager;
use ngs\event\structure\AbstractEventStructure;
use ngs\event\subscriber\AbstractEventSubscriber;
use ngs\routes\NgsModuleRoutes;
use ngs\exceptions\NgsException;

class NGSModule
{
    protected string $name;
    protected string $environment;

    protected array $constants;
    protected ?array $config;
    protected ?NgsModuleRoutes $routesManager;
    private $events = [];
    protected string $parentDir;

    /**
     * @param $moduleName
     */
    public function __construct($moduleName, $environment)
    {
        $this->name = $moduleName;
        $this->environment = $environment;


        $this->loadParams();
        $this->loadConstants();
        $this->loadConfig();
        $this->loadEvents();
    }

    protected function loadConstants()
    {
        $constantsFile = $this->getConstantsFile($this->parentDir);

        if (file_exists($constantsFile)) {
            $constants = json_decode(file_get_contents($constantsFile), true);

            if (is_array($constants)) {
                foreach ($constants as $section => $sectionValues) {
                    if (is_array($sectionValues)) {
                        // Process each section (constants, directories, classes)
                        foreach ($sectionValues as $constName => $constValue) {
                            // Check if there's an environment-specific value for this constant
                            if (is_array($constValue) && isset($constValue[$this->environment])) {
                                // Use the environment-specific value
                                $this->constants[$constName] = $constValue[$this->environment];
                            } else {
                                // Use the default value
                                $this->constants[$constName] = $constValue;
                            }
                        }
                    } else {
                        // Handle top-level constants (if any)
                        $this->constants[$section] = $sectionValues;
                    }
                }
            }
        }
    }

    protected function loadConfig()
    {
        $configFile = $this->getConfigFile($this->parentDir);

        if (file_exists($configFile)) {
            $configs = json_decode(file_get_contents($configFile), true);

            if (is_array($configs)) {
                foreach ($configs as $key => $config) {
                    //                    if (!defined($key)) {
                    //                        define($key, $config);
                    //                    }
                    $this->config[$key] = $config;
                }
            }
        }
    }

    /**
     * Get the config file path.
     *
     * @return string The path to the default config file.
     */
    private function getConfigFile($parentDir = ''): string
    {
        $configDir = $this->getConfigDir($parentDir);

        $configFileName = ("/config_" . $this->environment . "json");

        return $configDir . $configFileName;
    }

    /**
     * Get the constants file path.
     *
     * @return string The path to the default constant file.
     */
    private function getConstantsFile($parentDir = ''): string
    {
        $configDir = $this->getConfigDir($parentDir);

        $constantsFileName = "/constants";
        if (!empty($this->environment)) {
            $constantsFileName .= ("_" . $this->environment . ".json");
        } else {
            $constantsFileName .= ".json";
        }

        $configFile = $configDir . $constantsFileName;

        return $configFile;
    }


    /**
     *
     * @return void
     */
    private function loadParams()
    {
        $composer = Factory::getComposer();
        $rootPackage = $composer->getPackage();
        $extraData = $rootPackage->getExtra();

        $this->parentDir = $extraData['ngs'][$this->getName()]['parent'] ?? null;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getConstant($constantName)
    {
        if (isset($this->constants[$constantName])) {
            return $this->constants[$constantName];
        } else {
            return null;
        }
    }

    public function getRoutesManager(): ?NgsModuleRoutes
    {
        if ($this->routesManager !== null) {
            return $this->routesManager;
        }
        try {
            $manager = $this->getConstant('MODULES_ROUTES_ENGINE');
            $this->routesManager = new $manager();
        } catch (\Exception $e) {
            throw new NgsException('ROUTES ENGINE NOT FOUND, please check in constants.php ROUTES_ENGINE variable', 1);
        }
        return $this->routesManager;
    }

    public function getConfig(): ?array
    {
        return $this->config;
    }

    private function getConfigDir($parentDir = '')
    {
        if (empty($parentDir)) {
            return __DIR__ . '/conf';
        } else {
            return $parentDir . '/conf';
        }
    }

    /**
     * subscribe to all events
     *
     */
    protected function loadEvents()
    {
        $subscribersFile = $this->getConfigDir($this->parentDir) . '/event_subscribers.json';
        $subscribers = [];
        if (file_exists($subscribersFile)) {
            $subscribersList = json_decode(file_get_contents($subscribersFile), true);
        }

        foreach ($subscribersList as $subscriber) {
            $subscribers[] = $subscriber;
        }

        $this->subscribeToEvents($subscribers);
    }

    /**
     * subscribe to each subscriber events
     *
     * @param $subscribers
     * @throws \Exception
     */
    private function subscribeToEvents(array $subscribers)
    {
        $eventManager = EventManager::getInstance();

        foreach ($subscribers as $subscriber) {

            /** @var AbstractEventSubscriber $subscriberObject */

            $subscriberObject = new $subscriber();

            if (!$subscriberObject instanceof AbstractEventSubscriber) {
                throw new \Exception('wrong subscriber ' . $subscriber);
            }

            $subscriptions = $subscriberObject->getSubscriptions();

            foreach ($subscriptions as $eventStructClass => $handlerName) {

                /** @var AbstractEventStructure $eventStructExample */
                if (!is_a($eventStructClass, AbstractEventStructure::class, true)) {
                    throw new \InvalidArgumentException();
                }

                $eventStructExample = $eventStructClass::getEmptyInstance();
                $availableParams = $eventStructExample->getAvailableVariables();

                if ($eventStructExample->isVisible() && !isset($this->events[$eventStructExample->getEventId()])) {
                    $this->events[$eventStructExample->getEventId()] = [
                        'name' => $eventStructExample->getEventName(),
                        'bulk_is_available' => $eventStructExample->bulkIsAvailable(),
                        'params' => $availableParams
                    ];
                }
                $eventManager->subscribeToEvent($eventStructClass, $subscriberObject, $handlerName);
            }
        }
    }
}
