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

class CssBuilder extends AbstractBuilder
{
    protected function doBuild($file)
    {
        $files = $this->getBuilderArr(json_decode(file_get_contents($this->getBuilderFile())), $file);
        if (!$files) {
            return;
        }

        $outDir = $this->getOutputDir();
        $buf = '';
        foreach ($files['files'] as $value) {
            $module = '';
            if ($value['module'] == null) {
                $module = 'ngs';
            }
            $filePath = realpath(NGS()->getModuleDirByNS($module) . '/' . NGS()->get('CSS_DIR')) . '/' . trim($value['file']);
            $inputFile = realpath($filePath);
            if (!$inputFile) {
                throw new DebugException($filePath . ' not found');
            }
            $buf .= file_get_contents($inputFile) . '\n\r';
        }

        if ($files['compress'] === true) {
            $buf = $this->doCompress($buf);
        }
        touch($outDir . '/' . $files['output_file'], fileatime($this->getBuilderFile()));
        file_put_contents($outDir . '/' . $files['output_file'], $buf);
    }

    protected function customBufferUpdates($buffer)
    {
        $httpUtilsInst = NGS()->createDefinedInstance('HTTP_UTILS', \ngs\util\HttpUtils::class);
        $ngsPath = $httpUtilsInst->getHttpHost(true);

        $moduleRoutesEngineInst = NGS()->createDefinedInstance('MODULES_ROUTES_ENGINE', \ngs\routes\NgsModuleRoutes::class);
        $ngsModulePath = '';
        if ($moduleRoutesEngineInst->isDefaultModule()) {
            $ngsModulePath = $httpUtilsInst->getHttpHost(true, false);
        } else {
            $currentModuleNs = $moduleRoutesEngineInst->getModuleNS();
            $ngsModulePath = $httpUtilsInst->getHttpHost(true, false) . '/' . $currentModuleNs;
        }

        return str_replace(['@NGS_PATH', '@NGS_MODULE_PATH'], [$ngsPath, $ngsModulePath], $buffer);
    }

    public function getOutputDir(): string
    {
        $outDir = $this->resolveOutputSubDir('CSS_DIR');

        return $outDir;
    }

    /**
     * @param $buffer
     * @return string
     */
    protected function doCompress($buffer): string
    {
        return \ngs\lib\minify\CssCompressor::process($buffer);
    }

    protected function doDevOutput($files)
    {
        header('Content-type: text/css');
        foreach ($files['files'] as $value) {
            $module = '';
            if ($value['module'] != null) {
                $module = $value['module'];
            }
            $inputFile = NGS()->createDefinedInstance('HTTP_UTILS', \ngs\util\HttpUtils::class)->getHttpHostByNs($module) . '/devout/css/' . trim($value['file']);
            echo '@import url("' . $inputFile . '");';
        }
    }

    protected function getItemDir($module)
    {
        return realpath(NGS()->getModuleDirByNS($module) . '/' . NGS()->get('CSS_DIR'));
    }

    protected function getBuilderFile()
    {
        return realpath(NGS()->getModuleDirByNS('') . '/' . NGS()->get('CSS_DIR') . '/builder.json');
    }

    protected function getContentType()
    {
        return 'text/css';
    }
}
