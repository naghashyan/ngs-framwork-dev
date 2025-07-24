<?php

/**
 * Helper class for getting js files
 * have 3 general options connected with site mode (production/development)
 * 1. compress js files
 * 2. merge in one
 * 3. stream seperatly
 *
 * @author Levon Naghashyan <levon@naghashyan.com>
 * @site http://naghashyan.com
 * @year 2019-2023
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
use ngs\NgsModule;


class JsBuilderV2 extends AbstractBuilder
{
    /**
     * @param string $filePath
     * @throws DebugException
     */
    public function streamFile(string $filePath): void
    {
        if ($this->getEnvironment() === NgsEnvironmentContext::ENVIRONMENT_DEVELOPMENT) {
            $this->streamDevFile($filePath);
            return;
        }
        parent::streamFile($filePath);
    }

    public function streamDevFile(string $filePath): void
    {
        $fileUtils = NGS()->createDefinedInstance("FILE_UTILS", \ngs\util\FileUtils::class);
        $file = basename($filePath);
        $jsFile = substr($file, stripos($file, NGS()->get('JS_DIR')) + strlen(NGS()->get('JS_DIR')) + 1);

        // Try to find the file in the same directory as the requested file path
        $baseDir = dirname($filePath);
        $realFile = realpath($baseDir . '/' . $jsFile);
        if (file_exists($realFile)) {
            $fileUtils->sendFile($realFile, ['mimeType' => $this->getContentType(), 'cache' => false]);
            return;
        }

        // If not found, try to resolve using module structure
        $matches = explode('/', $jsFile);
        $moduleJsDir = realpath(NGS()->getModuleDirByNS($matches[0]) . '/' . NGS()->get('JS_DIR'));
        if (!$moduleJsDir) {
            throw new DebugException($jsFile . " File not found");
        }
        unset($matches[0]);
        $jsFile = implode('/', $matches);
        $realFile = realpath($moduleJsDir . '/' . $jsFile);
        if ($realFile === false) {
            throw new DebugException($jsFile . " File not found");
        }
        $fileUtils->sendFile($realFile, ['mimeType' => $this->getContentType(), 'cache' => false]);
    }

    /**
     * @return string
     */
    public function getOutputDir(): string
    {
        $outDir = $this->resolveOutputSubDir('JS_DIR');
        return $outDir;
    }

    protected function doCompress(string $buffer): string
    {
        return \ngs\lib\minify\ClosureCompiler::minify($buffer);
    }

    protected function doDevOutput(array $files)
    {
        header('Content-type: text/javascript');
        foreach ($files['files'] as $value) {
            $module = '';
            if ($value['module'] !== null) {
                $module = $value['module'];
            }
            $requestContext = NGS()->createDefinedInstance("REQUEST_CONTEXT", \ngs\util\RequestContext::class);
            $inputFile = $requestContext->getHttpHostByNs($module) . '/js/' . trim(str_replace('\\', '/', $value['file']));
        }
    }

    protected function getItemDir($module)
    {
        if ($module instanceof \ngs\NgsModule) {
            return realpath($module->getDir() . '/' . NGS()->get('JS_DIR'));
        }
        return realpath(NGS()->getModuleDirByNS($module) . '/' . NGS()->get('JS_DIR'));
    }

    protected function getBuilderFile()
    {
        return realpath(NGS()->getModuleDirByNS('') . '/' . NGS()->get('JS_DIR') . ' / builder.json');
    }

    protected function getEnvironment(): string
    {
        return NGS()->get('JS_BUILD_MODE');
    }

    protected function getContentType()
    {
        return 'text/javascript';
    }
}
