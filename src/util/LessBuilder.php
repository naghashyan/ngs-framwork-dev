<?php

/**
 * Helper class for getting js files
 * have 3 general options connected with site mode (production/development)
 * 1. compress css files
 * 2. merge in one
 * 3. stream seperatly
 *
 * @author Levon Naghashyan <levon@naghashyan.com>
 * @site http://naghashyan.com
 * @year 2014-2023
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

namespace ngs\util;

use ngs\exceptions\DebugException;

class LessBuilder extends AbstractBuilder
{
    private \Less_Parser $lessParser;

    public function streamFile(string $module, string $file): void
    {
        if ($this->getEnvironment() === 'production') {
            $filePath = realpath((NGS()->get('NGS_ROOT') . '/' . NGS()->get('PUBLIC_DIR')) . '/' . $file);
            if (!$filePath) {
                $this->build($file, true);
            }
            NGS()->createDefinedInstance('FILE_UTILS', \ngs\util\FileUtils::class)->sendFile($filePath, ['mimeType' => $this->getContentType(), 'cache' => true]);
            return;
        }
        $this->build($file, false);
    }

    public function build($file, $mode = false)
    {
        $files = $this->getBuilderArr($this->getBuilderJsonArr(), $file);
        if (count($files) === 0) {
            throw new DebugException('Please add less files in builder');
        }
        $options = [];
        if ($mode) {
            $options['compress'] = true;
        }
        $this->lessParser = new \Less_Parser($options);

        // Prepare components for parser string
        $requestContext = NGS()->createDefinedInstance('REQUEST_CONTEXT', \ngs\util\RequestContext::class);
        $ngsPathForParser = $requestContext->getHttpHost(true); // This is for @NGS_PATH

        $moduleRoutesEngineForParser = NGS()->createDefinedInstance('MODULES_ROUTES_ENGINE', \ngs\routes\NgsModuleResolver::class);
        $ngsModulePathForParser = '';
        if ($moduleRoutesEngineForParser->isDefaultModule()) {
            $ngsModulePathForParser = $requestContext->getHttpHost(true, false);
        } else {
            $currentModuleNsForParser = $moduleRoutesEngineForParser->getModuleNS();
            $ngsModulePathForParser = $requestContext->getHttpHost(true, false) . '/' . $currentModuleNsForParser;
        }
        $this->lessParser->parse('@NGS_PATH: \'' . $ngsPathForParser . '\';@NGS_MODULE_PATH: \'' . $ngsModulePathForParser . '\';');
        $this->setLessFiles($files);
        if ($mode) {
            $outFileName = $files['output_file'];
            if ($this->getOutputFileName() != null) {
                $outFileName = $this->getOutputFileName();
            }
            $outFile = $this->getOutputDir() . '/' . $outFileName;
            touch($outFile, fileatime($this->getBuilderFile()));
            file_put_contents($outFile, $this->lessParser->getCss());
            return true;
        }
        header('Content-type: ' . $this->getContentType());
        echo $this->lessParser->getCss();
        exit;
    }

    private function setLessFiles($files): bool
    {
        $importDirs = [];
        $lessFiles = [];
        foreach ($files['files'] as $value) {
            $modulePath = '';
            $module = '';
            if ($value['module'] !== null) {
                $modulePath = $value['module'];
                $module = $value['module'];
            }
            $lessHost = NGS()->createDefinedInstance('REQUEST_CONTEXT', \ngs\util\RequestContext::class)->getHttpHostByNs($modulePath) . '/less/';
            $lessDir = realpath(NGS()->getModuleDirByNS($module) . '/' . NGS()->get('LESS_DIR'));
            $lessFilePath = realpath($lessDir . '/' . $value['file']);
            if ($lessFilePath === false) {
                throw new DebugException('Please add or check if correct less file in builder under section ' . $value['file']);
            }
            $importDirs[$lessFilePath] = $lessDir;
            $this->lessParser->parseFile($lessFilePath);
        }
        $this->lessParser->SetImportDirs($importDirs);
        return true;
    }

    public function getOutputDir(): string
    {
        $outDir = $this->resolveOutputSubDir('LESS_DIR');
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
        return realpath(NGS()->getModuleDirByNS('') . '/' . NGS()->get('LESS_DIR') . '/builder.json');
    }

    protected function getEnvironment(): string
    {
        return NGS()->get('LESS_BUILD_MODE');
    }

    protected function getContentType()
    {
        return 'text/css';
    }
}
