<?php

/**
 * Helper class for getting SASS files
 * have 3 general options connected with site mode (production/development)
 * 1. compress css files
 * 2. merge in one
 * 3. stream seperatly
 *
 * @author Levon Naghashyan <levon@naghashyan.com>
 * @site http://naghashyan.com
 * @year 2017-2023
 * @package ngs.framework.util
 * @version 4.5.0
 *
 * This file is part of the NGS package.
 *
 * @copyright Naghashyan Solutions LLC
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace ngs\util {

    use ngs\exceptions\DebugException;
    use ScssPhp\ScssPhp\Compiler;
    use ScssPhp\ScssPhp\Formatter\Crunched;

    class SassBuilder extends AbstractBuilder
    {
        private $sassParser;

        public function streamFile(string $filePath): void
        {
            $file = basename($filePath);
            if ($this->getEnvironment() === "production") {
                $realFilePath = realpath($filePath);
                if (!$realFilePath) {
                    $this->build($file, true);
                }
                NGS()->createDefinedInstance('FILE_UTILS', \ngs\util\FileUtils::class)->sendFile($realFilePath, ["mimeType" => $this->getContentType(), "cache" => true]);
                return;
            }
            $this->build($file, false);
        }

        public function build($file, $mode = false)
        {
            $files = $this->getBuilderArr($this->getBuilderJsonArr(), $file);
            if (count($files) == 0) {
                throw new DebugException("Please add sass files in builder");
            }
            $this->sassParser = new Compiler();
            $this->sassParser->addImportPath(function ($path) {
                if (strpos($path, '@ngs-cms') !== false) {
                    return realpath(NGS()->getModuleDirByNS('ngs-cms') . '/' . NGS()->get('SASS_DIR')) . '/' . str_replace('@ngs-cms/', '', $path) . '.scss';
                }

                if (strpos($path, '@' . NGS()->get('NGS_CMS_NS')) !== false) {
                    return realpath(NGS()->getModuleDirByNS(NGS()->get('NGS_CMS_NS')) . '/' . NGS()->get('SASS_DIR')) . '/' . str_replace('@' . NGS()->get('NGS_CMS_NS') . '/', '', $path) . '.scss';
                }
                //TODO: LM: should be refactored
                if (strpos($path, '@' . NGS()->get('NGS_DASHBOARDS_NS')) !== false) {
                    return realpath(NGS()->getModuleDirByNS(NGS()->get('NGS_DASHBOARDS_NS')) . '/' . NGS()->get('SASS_DIR')) . '/' . str_replace('@' . NGS()->get('NGS_DASHBOARDS_NS') . '/', '', $path) . '.scss';
                }

                return realpath(NGS()->getModuleDirByNS('') . '/' . NGS()->get('SASS_DIR')) . '/' . $path . '.scss';
            });

            if ($mode) {
                $this->sassParser->setFormatter(Crunched::class);
            }

            $requestContext = NGS()->createDefinedInstance('REQUEST_CONTEXT', \ngs\util\RequestContext::class);
            $ngsPathForParser = $requestContext->getHttpHost(true);

            $moduleRoutesEngineForParser = NGS()->createDefinedInstance('MODULES_ROUTES_ENGINE', \ngs\routes\NgsModuleResolver::class);
            $ngsModulePathForParser = '';
            $currentModuleForParser = $moduleRoutesEngineForParser->resolveModule($requestContext->getRequestUri()) ?? NGS();
            $currentModuleNsForParser = $currentModuleForParser->getName();
            if ($currentModuleNsForParser === NGS()->getName()) {
                $ngsModulePathForParser = $requestContext->getHttpHost(true, false);
            } else {
                $ngsModulePathForParser = $requestContext->getHttpHost(true, false) . '/' . $currentModuleNsForParser;
            }
            $this->sassParser->setVariables([
                'NGS_PATH' => $ngsPathForParser,
                'NGS_MODULE_PATH' => $ngsModulePathForParser
            ]);
            if ($mode) {
                $outFileName = $files["output_file"];
                if ($this->getOutputFileName() != null) {
                    $outFileName = $this->getOutputFileName();
                }
                $outFile = $this->getOutputDir() . "/" . $outFileName;
                touch($outFile, fileatime($this->getBuilderFile()));
                file_put_contents($outFile, $this->getCss($files));
                return true;
            }
            header('Content-type: ' . $this->getContentType());
            echo $this->getCss($files);
            exit;
        }

        private function getCss($files)
        {
            $importDirs = [];
            $sassFiles = [];
            $sassStream = "";
            foreach ($files["files"] as $value) {
                $modulePath = "";
                $module = NGS()->createDefinedInstance('MODULES_ROUTES_ENGINE', \ngs\routes\NgsModuleResolver::class)->getDefaultNS();
                if ($value["module"] != null) {
                    $modulePath = $value["module"];
                    $module = $value["module"];
                }
                $requestContext = NGS()->createDefinedInstance('REQUEST_CONTEXT', \ngs\util\RequestContext::class);
                $sassHost = $requestContext->getHttpHostByNs($modulePath) . "/sass/";
                $sassFilePath = realpath(realpath(NGS()->getModuleDirByNS($module) . '/' . NGS()->get('SASS_DIR')) . "/" . $value["file"]);
                if ($sassFilePath == false) {
                    throw new DebugException("Please add or check if correct sass file in builder under section " . $value["file"]);
                }
                $sassStream .= file_get_contents($sassFilePath);
            }
            return $this->sassParser->compile($sassStream);
        }

        public function getOutputDir(): string
        {
            $outDir = $this->resolveOutputSubDir('SASS_DIR');
            return $outDir;
        }

        protected function getOutputFileName()
        {
            return null;
        }

        public function doDevOutput(array $files)
        {
        }

        protected function getItemDir($module)
        {
            return NGS()->getCssDir($module);
        }

        protected function getBuilderFile()
        {
            return realpath(NGS()->getModuleDirByNS('') . '/' . NGS()->get('SASS_DIR') . "/builder.json");
        }

        protected function getEnvironment(): string
        {
            return NGS()->get("SASS_BUILD_MODE");
        }

        protected function getContentType()
        {
            return "text/css";
        }
    }

}
