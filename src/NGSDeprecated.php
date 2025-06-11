<?php

namespace ngs;

/**
 * Base NGSDeprecated class
 * for static function that will
 * visible from any classes
 *
 * @author Levon Naghashyan <levon@naghashyan.com>
 * @site https://naghashyan.com
 * @year 2014-2022
 * @package ngs.framework
 * @version 4.2.0
 *
 *
 * This file is part of the NGS package.
 *
 * @copyright Naghashyan Solutions LLC
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 *
 */

use ngs\exceptions\DebugException;
use ngs\exceptions\NgsException;
use ngs\util\NgsArgs;
use ngs\util\NgsEnvironmentContext;

require_once('routes/NgsModuleResolver.php');
require_once('routes/NgsRoutesResolver.php');
require_once('util/HttpUtils.php');

abstract class NGSDeprecated extends NgsModule
{
    protected $ngsConfig = null;
    protected array $define = [];

    //----------------------------------------------------------------
    protected $dispatcher = null;
    protected $loadMapper = null;
    protected $routesEngine = null;
    protected $moduleRoutesEngine = null;
    protected $sessionManager = null;
    protected $tplEngine = null;
    protected $fileUtils = null;
    protected $httpUtils = null;
    protected $ngsUtils = null;
    protected $jsBuilder = null;
    protected $cssBuilder = null;
    protected $lessBuilder = null;
    protected $sassBuilder = null;
    protected $isModuleEnable = false;


    public function initializeOld()
    {
        $moduleConstatPath = realpath(NGS()->getConfigDir() . '/constants.php');
        if ($moduleConstatPath) {
            require_once $moduleConstatPath;
        }
        $envConstantFile = realpath(NGS()->getConfigDir() . '/constants_' . $this->getShortEnvironment() . '.php');
        if ($envConstantFile) {
            require_once $envConstantFile;
        }

        $moduleRoutesEngine = NGS()->getModulesRoutesEngine();
        $parentModule = $moduleRoutesEngine->getParentModule();

        if ($parentModule && isset($parentModule['ns'])) {
            $_prefix = $parentModule['ns'];
            $envConstantFile = realpath(NGS()->getConfigDir($_prefix) . '/constants_' . $this->getShortEnvironment() . '.php');
            if ($envConstantFile) {
                require_once $envConstantFile;
            }
        }

        $this->getModulesRoutesEngine(true)->initialize();
    }

    /*
     |--------------------------------------------------------------------------
     | DEFINING NGS MODULES
     |--------------------------------------------------------------------------
     */


    //------------------

    /**
     * @return string|null
     * @deprecated
     */
    public function getVersion(): string
    {
        return $this->getDefinedValue('VERSION');
    }

    /**
     * @return string
     * @deprecated
     */
    public function getNGSVersion(): string
    {
        return $this->getDefinedValue('NGSVERSION');
    }

    /**
     * @return bool
     * @deprecated
     * check if ngs js framework enable
     *
     */
    public function isJsFrameworkEnable(): bool
    {
        return $this->getDefinedValue('JS_FRAMEWORK_ENABLE');
    }


    //----------------------------------------------------------------


    /**
     * this method return global ngs root config file
     *
     *
     * @return object config
     */
    public function getNgsConfig(): mixed
    {
        $config = null;
        try {
            $config = $this->getConfig();
        } catch (NgsException $e) {
        }

        return $config;
    }


    public function args()
    {
        return NgsArgs::getInstance();
    }

    /**
     * @param \ngs\Dispatcher $dispatcher
     * @return void
     * @deprecated
     */
    public function setDispatcher(\ngs\Dispatcher $dispatcher): void
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * @return \ngs\Dispatcher
     * @deprecated
     */
    public function getDispatcher(): \ngs\Dispatcher
    {
        return $this->dispatcher;
    }

    /**
     * @return String loads namespace
     * @deprecated
     * this method return loads namespace
     *
     */
    public function getLoadsPackage(): string
    {
        return $this->get('LOADS_DIR');
    }

    /**
     * @return String actions namespace
     * @deprecated
     * this method return actions namespace
     *
     */
    public function getActionPackage(): string
    {
        return $this->get('ACTIONS_DIR');
    }

    /*
     |--------------------------------------------------------------------------
     | DIR FUNCTIONS SECTION
     |--------------------------------------------------------------------------
     */

    /**
     * this method do calculate
     * and  return module root dir by namespace
     *
     * @param string $ns
     * @return string
     */
    abstract public function getModuleDirByNS(string $ns = ''): string;


    /**
     * this method do calculate and return NGS Framework
     * dir path by namespace
     * @deprecated
     *
     * @return String config dir path
     */
    public function getFrameworkDir(): string
    {
        return __DIR__;
    }

    /**
     * @return String config dir path
     * @deprecated
     * this method do calculate and return NGS CMS
     * dir path
     *
     *
     */
    public function getNgsCmsDir(): string
    {
        if (is_dir(dirname(__DIR__, 2) . '/ngs-php-cms/src')) {
            return dirname(__DIR__, 2) . '/ngs-php-cms/src';
        }
        return dirname(__DIR__, 2) . '/ngs-admin-tools/src';
    }

    /**
     * @return String config dir path
     * @deprecated
     * this method do calculate and return NGS Dashboards
     * dir path
     *
     *
     */
    public function getNgsDashboardsDir(): string
    {
        return dirname(__DIR__, 2) . '/ngs-dashboards/src';
    }


    /**
     * @param String $ns
     *
     * @return String template dir path
     * @deprecated
     * this method do calculate and return template
     * dir path by namespace
     *
     *
     */
    public function getTemplateDir(string $ns = ''): string
    {
        return realpath($this->getModuleDirByNS($ns) . '/' . $this->get('TEMPLATES_DIR'));
    }

    /**
     * @param String $ns
     *
     * @return String temp dir path
     * @deprecated
     * this method do calculate and return temp
     * dir path by namespace
     *
     *
     */
    public function getTempDir(string $ns = ''): string
    {
        return realpath($this->getModuleDirByNS($ns) . '/' . $this->get('TEMP_DIR'));
    }

    /**
     * @param String $ns
     *
     * @return String data dir path
     * @deprecated
     * this method do calculate and return data
     * dir path by namespace
     *
     *
     */
    public function getDataDir(string $ns = ''): string
    {
        return realpath($this->getModuleDirByNS($ns) . '/' . $this->get('DATA_DIR'));
    }

    /**
     * @param String $ns
     *
     * @return String public dir path
     * @deprecated
     * this method do calculate and return public
     * dir path by namespace
     *
     *
     */
    public function getPublicDir(string $ns = ''): string
    {
        return realpath($this->getModuleDirByNS($ns) . '/' . $this->get('PUBLIC_DIR'));
    }

    /**
     * @param String $ns
     *
     * @return String public dir path
     * @deprecated
     * this method do calculate and return web
     * dir path by namespace
     *
     *
     */
    public function getWEbDir(string $ns = ''): string
    {
        return realpath($this->getModuleDirByNS($ns) . '/' . $this->get('WEB_DIR'));
    }

    /**
     * @param String $ns
     *
     * @return String classes dir path
     * @deprecated
     * this method do calculate and return Classes
     * dir path by namespace
     *
     *
     */
    public function getClassesDir($ns = ''): string
    {
        return realpath($this->getModuleDirByNS($ns) . '/' . $this->get('CLASSES_DIR'));
    }

    //----------------------------------------------------------------

    /**
     * Returns the CSS directory path for the given namespace.
     *
     * @param string $ns Optional namespace context.
     * @return string    CSS_DIR path resolved within namespace.
     * @throws \ngs\exceptions\DebugException on failure.
     * @deprecated since 4.3.0 Use calculateDefinedDir("CSS_DIR") or NgsFactory::getCssDir().
     */
    public function getCssDir(string $ns = ''): string
    {
        return $this->calculateDefinedDir($ns, 'CSS_DIR');
    }

    /**
     * Returns the SASS directory path for the given namespace.
     *
     * @param string $ns Optional namespace context.
     * @return string    SASS_DIR path resolved within namespace.
     * @throws \ngs\exceptions\DebugException on failure.
     * @deprecated since 4.3.0 Use calculateDefinedDir("SASS_DIR") or NgsFactory::getSassDir().
     */
    public function getSassDir(string $ns = ''): string
    {
        return $this->calculateDefinedDir($ns, 'SASS_DIR');
    }

    /**
     * Returns the LESS directory path for the given namespace.
     *
     * @param string $ns Optional namespace context.
     * @return string    LESS_DIR path resolved within namespace.
     * @throws \ngs\exceptions\DebugException on failure.
     * @deprecated since 4.3.0 Use calculateDefinedDir("LESS_DIR") or NgsFactory::getLessDir().
     */
    public function getLessDir(string $ns = ''): string
    {
        return $this->calculateDefinedDir($ns, 'LESS_DIR');
    }

    /**
     * Returns the JS directory path for the given namespace.
     *
     * @param string $ns Optional namespace context.
     * @return string    JS_DIR path resolved within namespace.
     * @throws \ngs\exceptions\DebugException on failure.
     * @deprecated since 4.3.0 Use calculateDefinedDir("JS_DIR") or NgsFactory::getJsDir().
     */
    public function getJsDir(string $ns = ''): string
    {
        return $this->calculateDefinedDir($ns, 'JS_DIR');
    }



    /*
     |--------------------------------------------------------------------------
     | HOST FUNCTIONS SECTION
     |--------------------------------------------------------------------------
     */

    /**
     * @param string $ns
     * @param bool $withProtocol
     * @return mixed|string|null
     * @throws DebugException
     * @deprecated
     */
    public function getPublicHostByNS(string $ns = '', bool $withProtocol = false)
    {
        if ($ns === '') {
            if ($this->getModulesRoutesEngine()->isDefaultModule()) {
                return $this->getHttpUtils()->getHttpHost(true, $withProtocol);
            }
            $ns = $this->getModulesRoutesEngine()->getModuleNS();
        }
        return $this->getHttpUtils()->getHttpHost(true, $withProtocol) . '/' . $ns;
    }

    /**
     * @param $ns
     * @param $withProtocol
     * @return string
     * @throws DebugException
     * @deprecated
     */
    public function getPublicOutputHost($ns = '', $withProtocol = false)
    {
        return $this->getHttpUtils()->getNgsStaticPath($ns, $withProtocol) . '/' . $this->get('PUBLIC_OUTPUT_DIR');
    }

    /**
     * @param $ns
     * @param $withProtocol
     * @return string
     * @throws DebugException
     * @deprecated
     */
    public function getPublicHost($ns = '', $withProtocol = false)
    {
        return $this->getHttpUtils()->getNgsStaticPath($ns, $withProtocol);
    }

    /**
     * @param $ns
     * @param $withProtocol
     * @return string
     * @throws DebugException
     * @deprecated
     */
    public function getPublicJsOutputHost($ns = '', $withProtocol = false)
    {
        return $this->getHttpUtils()->getNgsStaticPath($ns, $withProtocol) . '/' . $this->getPublicJsOutputDir();
    }

    /**
     * @return string
     * @deprecated
     */
    public function getPublicJsOutputDir()
    {
        if ($this->get('JS_BUILD_MODE') === 'development') {
            return $this->get('WEB_DIR') . '/' . $this->get('JS_DIR');
        }
        return $this->get('PUBLIC_OUTPUT_DIR') . '/' . $this->get('JS_DIR');
    }

    /**
     * @throws DebugException if SESSION_MANAGER Not found
     *
     * @deprecated
     * this method  return ngs framework
     * sessiomanager if defined by user it return it if not
     * return ngs framework default sessiomanager
     *
     */
    public function getSessionManager()
    {
        return $this->createDefinedInstance('SESSION_MANAGER', \ngs\session\AbstractSessionManager::class);
    }

    /**
     * @throws DebugException if ROUTES_ENGINE Not found
     *
     * @deprecated
     * static function that return ngs framework
     * fileutils if defined by user it return it if not
     * return ngs framework default fileutils
     *
     */
    public function getRoutesEngine()
    {
        return $this->createDefinedInstance('ROUTES_ENGINE', \ngs\routes\NgsRoutesResolver::class);
    }

    /**
     * @throws DebugException if MAPPER Not found
     *
     * @deprecated
     * static function that return ngs framework
     * loadmapper if defined by user it return it if not
     * return ngs framework default loadmapper
     *
     */
    public function getLoadMapper(): \ngs\routes\NgsLoadMapper
    {
        return $this->createDefinedInstance('LOAD_MAPPER', \ngs\routes\NgsLoadMapper::class);
    }

    /**
     * Returns the NGS utility instance.
     *
     * If a user-defined utility is configured under the key 'ngsUtils',
     * that instance will be returned; otherwise, the default NgsUtils
     * implementation is used.
     *
     * @return \ngs\util\NgsUtils
     * @throws \ngs\exceptions\DebugException if the NGS_UTILS implementation cannot be created.
     * @deprecated since 4.3.0 Use \ngs\util\NgsFactory::getUtils() instead.
     */
    public function getNgsUtils(): \ngs\util\NgsUtils
    {
        return $this->createDefinedInstance('NGS_UTILS', \ngs\util\NgsUtils::class);
    }

    /**
     * Returns the FileUtils instance.
     *
     * If a user-defined file utility is configured under the key 'fileUtils',
     * that instance will be returned; otherwise, the default FileUtils
     * implementation is used.
     *
     * @return \ngs\util\FileUtils
     * @throws \ngs\exceptions\DebugException if the FILE_UTILS implementation cannot be created.
     * @deprecated since 4.3.0 Use \ngs\util\NgsFactory::getFileUtils() instead.
     */
    public function getFileUtils(): \ngs\util\FileUtils
    {
        return $this->createDefinedInstance('fileUtils', \ngs\util\FileUtils::class);
        ;
    }

    /**
     * Returns the HTTP utilities instance.
     *
     * If a user-defined HTTP utility is configured under the key 'httpUtils',
     * that instance will be returned; otherwise, the default RequestContext
     * implementation is used.
     *
     * @return \ngs\util\RequestContext
     * @throws \ngs\exceptions\DebugException if the REQUEST_CONTEXT implementation cannot be created.
     * @deprecated since 4.3.0 Use \ngs\util\NgsFactory::getHttpUtils() instead.
     */
    public function getHttpUtils(): \ngs\util\RequestContext
    {
        $requestContext = $this->createDefinedInstance('REQUEST_CONTEXT', \ngs\util\RequestContext::class);
        return $requestContext;
    }

    /**
     * Returns the JS builder instance (version 2).
     *
     * If a user-defined JS builder is configured under the key 'jsBuilder',
     * that instance will be returned; otherwise, the default JsBuilderV2
     * implementation is used.
     *
     * @return \ngs\util\JsBuilderV2
     * @throws \ngs\exceptions\DebugException if the JS_BUILDER implementation cannot be created.
     * @deprecated since 4.3.0 Use \ngs\util\NgsFactory::getJsBuilder() instead.
     */
    public function getJsBuilder(): \ngs\util\JsBuilderV2
    {
        return $this->createDefinedInstance('jsBuilder', \ngs\util\JsBuilderV2::class);
    }

    /**
     * Returns the CSS builder instance.
     *
     * If a user-defined CSS builder is configured under the key 'cssBuilder',
     * that instance will be returned; otherwise, the default CssBuilder
     * implementation is used.
     *
     * @return \ngs\util\CssBuilder
     * @throws \ngs\exceptions\DebugException if the CSS_BUILDER implementation cannot be created.
     * @deprecated since 4.3.0 Use \ngs\util\NgsFactory::getCssBuilder() instead.
     */
    public function getCssBuilder(): \ngs\util\CssBuilder
    {
        return $this->createDefinedInstance('cssBuilder', \ngs\util\CssBuilder::class);
    }

    /**
     * Returns the Less builder instance.
     *
     * If a user-defined Less builder is configured under the key 'lessBuilder',
     * that instance will be returned; otherwise, the default less builder
     * implementation is used.
     *
     * @return mixed The user-defined or default Less builder object.
     * @throws \ngs\exceptions\DebugException if the LESS_BUILDER implementation cannot be created.
     * @deprecated since 4.3.0 Use \ngs\util\NgsFactory::getLessBuilder() instead.
     */
    public function getLessBuilder()
    {
        return $this->createDefinedInstance('lessBuilder', \ngs\util\LessBuilder::class);
    }

    /**
     * Returns the Sass builder instance.
     *
     * If a user-defined Sass builder is configured under the key 'sassBuilder',
     * that instance will be returned; otherwise, the default SassBuilder
     * implementation is used.
     *
     * @return \ngs\util\SassBuilder
     * @throws \ngs\exceptions\DebugException if the SASS_BUILDER implementation cannot be created.
     * @deprecated since 4.3.0 Use \ngs\util\NgsFactory::getSassBuilder() instead.
     */
    public function getSassBuilder(): \ngs\util\SassBuilder
    {
        return $this->createDefinedInstance('sassBuilder', \ngs\util\SassBuilder::class);
    }

    /**
     * @param string $fileType
     *
     * @return \ngs\util\CssBuilder|\ngs\util\FileUtils|\ngs\util\JsBuilder|\ngs\util\SassBuilder|Object
     *
     * @throws DebugException
     * @deprecated
     */
    public function getFileStreamerByType($fileType)
    {
        switch ($fileType) {
            case 'js':
                return $this->getJsBuilder();
            case 'css':
                return $this->getCssBuilder();
            case 'less':
                return $this->getLessBuilder();
            case 'sass':
                return $this->getSassBuilder();
            default:
                return $this->getFileUtils();
        }
    }

    /**
     * Returns the module routes engine, creating it if necessary.
     * @param bool $forceNew Create a fresh instance if true; otherwise reuse.
     * @return \ngs\routes\NgsModuleResolver
     * @throws DebugException If the MODULES_ROUTES_ENGINE constant is missing or invalid.
     * @deprecated
     */
    public function getModulesRoutesEngine(bool $forceNew = false): \ngs\routes\NgsModuleResolver
    {
        /** @var \ngs\routes\NgsModuleResolver $engine */
        $engine = $this->createDefinedInstance(
            'MODULES_ROUTES_ENGINE',
            \ngs\routes\NgsModuleResolver::class,
            $forceNew
        );
        return $engine;
    }

    /**
     * Returns the template engine, creating it if necessary.
     *
     * @param bool $forceNew Create a fresh instance if true; otherwise reuse.
     * @return \ngs\templater\NgsTemplater
     * @throws DebugException If the TEMPLATE_ENGINE constant is missing or invalid.
     * @deprecated
     */
    public function getTemplateEngine(bool $forceNew = false): \ngs\templater\NgsTemplater
    {
        /** @var \ngs\templater\NgsTemplater $templater */
        $templater = $this->createDefinedInstance(
            'TEMPLATE_ENGINE',
            \ngs\templater\NgsTemplater::class,
            $forceNew
        );
        return $templater;
    }

    /**
     * @return String $namespace
     * @deprecated
     * return project prefix
     */
    public function getEnvironment(): string
    {
        return NgsEnvironmentContext::getInstance()->getEnvironment();
    }

    /**
     * return short env prefix
     * @static
     * @access
     * @return String $env
     */
    public function getShortEnvironment(): string
    {
        return NgsEnvironmentContext::getInstance()->getShortEnvironment();
    }


    /**
     * @return \ngs\util\NgsDynamic
     */
    public function getDynObject(): \ngs\util\NgsDynamic
    {
        return new \ngs\util\NgsDynamic();
    }


    public function cliLog($log, $color = 'white', $bold = false)
    {
        $colorArr = ['black' => '0;30', 'blue' => '0;34', 'green' => '0;32', 'cyan' => '0;36',
            'red' => '0;31', 'purple' => '0;35', 'prown' => '0;33', 'light_gray' => '0;37 ',
            'gark_gray' => '1;30', 'light_blue' => '1;34', 'light_green' => '1;32', 'light_cyan' => '1;36',
            'light_red' => '1;31', 'light_purple' => '1;35', 'yellow' => '1;33', 'white' => '1;37'];
        $colorCode = $colorArr['white'];
        if ($colorArr[$color]) {
            $colorCode = $colorArr[$color];
        }
        $colorCode .= '0m';
        echo '\033[' . $colorCode . $log . '  \033[' . $colorArr['white'] . '0m \n';
    }

}


function initializeAllNgsFrameworkConstants()
{
    $mainPackage = 'ngs-php-framework';
    $currentDir = __DIR__;
    $frameworkPosition = strpos($currentDir, '\\' . $mainPackage);
    if ($frameworkPosition === false) {
        $frameworkPosition = strpos($currentDir, '/' . $mainPackage);
    }
    $ngsPackageDir = substr($currentDir, 0, $frameworkPosition);
    $allInstalledNgsPackages = [$ngsPackageDir . '/' . $mainPackage];
    $dir = new DirectoryIterator($ngsPackageDir);

    foreach ($dir as $fileinfo) {
        if ($fileinfo->isDir() && !$fileinfo->isDot() && $fileinfo->getFilename() !== $mainPackage) {
            $allInstalledNgsPackages[] = $ngsPackageDir . '/' . $fileinfo->getFilename();
        }
    }

    $defaultsFile = 'system/NgsDefaultConstants.php';

    foreach ($allInstalledNgsPackages as $installedPacakge) {
        require_once($installedPacakge . '/src/' . $defaultsFile);
    }
}
