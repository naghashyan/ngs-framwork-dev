<?php
/**
 * Helper Abstract class for standart ngs builders
 * have 3 general options connected with site mode (production/development)
 * 1. compress js files
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

abstract class AbstractBuilder
{

    private $builderJsonArr = [];

    /**
     * @param string $module
     * @param string $file
     * @throws DebugException
     */
    public function streamFile(string $module, string $file): void
    {
        $fileUtils = NGS()->createDefinedInstance('FILE_UTILS', \ngs\util\FileUtils::class);
        if ($this->getEnvironment() === 'production') {
            $filePath = realpath((NGS()->getModuleDirByNS('') . '/' . NGS()->get('PUBLIC_DIR')) . '/' . $file);
            if (strpos($file, NGS()->getDefinedValue('PUBLIC_OUTPUT_DIR')) === false) {
                if (!$filePath) {
                    throw new DebugException((NGS()->getModuleDirByNS('') . '/' . NGS()->get('PUBLIC_DIR')) . '/' . $file . ' NOT FOUND');
                }
            } elseif (file_exists($filePath) === false) {
                $this->build($file);
            }
            $fileUtils->sendFile($filePath, array('mimeType' => $this->getContentType(), 'cache' => true));
            return;
        }
        if (strpos($file, 'devout') !== false) {
            $realFile = substr($file, strpos($file, '/') + 1);

            $realFile = realpath((NGS()->getModuleDirByNS($module) . '/' . NGS()->get('PUBLIC_DIR')) . '/' . $realFile);
            if ($realFile === null) {
                throw new DebugException($file . ' not found');
            }
            $buffer = file_get_contents($realFile);
            $buffer = $this->customBufferUpdates($buffer);
            header('Content-type: ' . $this->getContentType());
            echo $buffer;
            return;
        }
        $realFile = realpath((NGS()->getModuleDirByNS($module) . '/' . NGS()->get('PUBLIC_DIR')) . '/' . $file);
        if (file_exists($realFile)) {
            $fileUtils->sendFile($realFile, array('mimeType' => $this->getContentType(), 'cache' => false));
            return;
        }

        $files = $this->getBuilderArr($this->getBuilderJsonArr(), $file);
        if (count($files) == 0) {
            throw new DebugException('Please add file in builder under section ' . $file);
        }
        $this->doDevOutput($files);
    }

    /**
     * get js files from builders json array by filename
     *
     *
     * @param array $builders
     * @param string $file - request file name
     *
     * @return array builder
     */
    protected function getBuilderArr($builders, $file = null)
    {
        $moduleRoutesEngine = NGS()->createDefinedInstance('MODULES_ROUTES_ENGINE', \ngs\routes\NgsModuleRoutes::class);
        $tmpArr = array();
        foreach ($builders as $key => $value) {
            if (strpos($file, $value->output_file) === false) {
                $builders = null;
                if (isset($value->builders)) {
                    $builders = (array)$value->builders;
                    $tempArr = $this->getBuilderArr($builders, $file);
                    if ($tempArr) {
                        return $tempArr;
                    } else {
                        continue;
                    }
                } else {
                    continue;
                }
                $tmpArr = array();
                $tmpArr['output_file'] = (string)$value->output_file;
                $tmpArr['debug'] = false;
                if (isset($value->compress)) {
                    $tmpArr['compress'] = $value->compress;
                }
                if (isset($value->type)) {
                    $tmpArr['type'] = $value->type;
                }
                $tmpArr['files'] = (array)$value->files;
            } else {
                $tmpArr = array();
                $tmpArr['output_file'] = (string)$value->output_file;
                $tmpArr['debug'] = false;
                if (isset($value->compress)) {
                    $tmpArr['compress'] = $value->compress;
                }
                if (isset($value->type)) {
                    $tmpArr['type'] = $value->type;
                }
                $tmpArr['files'] = array();
                if (isset($value->builders) && is_array($value->builders)) {
                    foreach ($value->builders as $builder) {
                        if (!is_array($builder)) {
                            $builder = array($builder);
                        }
                        $tempArr = $this->getBuilderArr($builder, $builder[0]->output_file);
                        if (isset($tempArr['files'])) {
                            $tmpArr['files'] = array_merge($tmpArr['files'], $tempArr['files']);
                        }
                    }
                } else {
                    $module = $moduleRoutesEngine->getModuleNS();
                    if (isset($value->module)) {
                        $module = $value->module;
                    }
                    if ($module == $moduleRoutesEngine->getDefaultNS()) {
                        $module = null;
                    }
                    $type = null;
                    if (isset($value->type)) {
                        $type = $value->type;
                    }
                    $tmpFileArr = array();
                    if (isset($value->files)) {
                        foreach ((array)$value->files as $file) {
                            $_tmpArr = array();
                            $_tmpArr['module'] = $module;
                            $_tmpArr['file'] = $file;
                            $_tmpArr['type'] = $type;
                            $tmpFileArr[] = $_tmpArr;
                        }
                    }

                    if (isset($value->dir)) {
                        $dir = $value->dir;
                        if (!isset($dir->path)) {

                            new DebugException('please provide directory path');
                        }
                        if (!isset($dir->ext)) {
                            new DebugException('please provide extenstion');
                        }
                        if (!isset($dir->recursively) || !is_bool($dir->recursively)) {
                            $dir->recursively = true;
                        }
                        $tmpFileArr = $this->readDirFiles($dir->path, $dir->ext, $dir->recursively, $module, $type);
                    }

                    $tmpArr['files'] = $tmpFileArr;
                }
            }
        }
        return $tmpArr;
    }

    protected function readDirFiles($dirPath, $ext, $recursively, $module, $_type): array
    {
        $realPath = realpath($this->getItemDir($module) . '/' . $dirPath);
        $itemFiles = [];
        if (!is_dir($realPath)) {
            return [];
        }
        $dh = opendir($realPath);
        if ($dh === false) {
            return [];
        }
        while (($file = readdir($dh)) !== false) {
            if ($file != '.' && $file != '..') {
                $type = filetype($realPath . '/' . $file);
                if ($type == 'dir' && $recursively === true) {
                    $tmpArr = $this->readDirFiles($dirPath . '/' . $file, $ext, $recursively, $module, $_type);
                    $itemFiles = array_merge($itemFiles, $tmpArr);
                } elseif ($type == 'file') {
                    $filePath = realpath($realPath . '/' . $file);
                    $fileInfo = pathinfo($filePath);
                    $fileExt = $fileInfo['extension'];
                    if ($fileExt == $ext) {
                        $_tmpArr = [];
                        $_tmpArr['module'] = $module;
                        $_tmpArr['file'] = substr($filePath, strlen($this->getItemDir($module)) + 1);
                        $_tmpArr['type'] = $_type;
                        $itemFiles[] = $_tmpArr;
                    }
                }
            }
        }
        return $itemFiles;
    }

    protected function build($file)
    {

        $files = $this->getBuilderArr($this->getBuilderJsonArr(), $file);

        if (!$files) {
            return;
        }
        $outDir = $this->getOutputDir();
        $buffer = '';
        foreach ($files['files'] as $value) {
            $module = '';
            if ($value['module'] == null) {
                $module = 'ngs';
            }
            $inputFile = realpath($this->getItemDir($module) . '/' . trim($value['file']));
            if (!$inputFile) {
                throw new DebugException($this->getItemDir($module) . '/' . trim($value['file']) . ' not found');
            }
            $buffer .= file_get_contents($inputFile) . '\n\r';
        }
        $buffer = $this->customBufferUpdates($buffer);
        if ($files['compress'] == true) {
            $buffer = $this->doCompress($buffer);
        }
        $outFile = $this->getOutputDir() . '/' . $files['output_file'];
        //set file time same with builder.json
        touch($outFile, fileatime($this->getBuilderFile()));
        file_put_contents($outFile, $buffer);
    }

    protected function customBufferUpdates($buffer)
    {
        return $buffer;
    }


    protected function getEnvironment(): string
    {
        $envConstantValue = NGS()->get('ENVIRONMENT');
        $currentEnvironment = 'production'; // Default
        if ($envConstantValue === 'development' || $envConstantValue === 'staging') {
            $currentEnvironment = $envConstantValue;
        }
        return $currentEnvironment;
    }

    abstract protected function getItemDir($module);

    abstract protected function getBuilderFile();

    public function getBuilderJsonArr()
    {
        if (count($this->builderJsonArr) > 0) {
            return $this->builderJsonArr;
        }
        if (!$this->getBuilderFile()) {
            return [];
        }
        return $this->builderJsonArr = json_decode(file_get_contents($this->getBuilderFile()));
    }

    abstract protected function getContentType();

    abstract protected function doDevOutput(array $files);

    abstract public function getOutputDir(): string;

    protected function doCompress(string $buffer): string
    {
        return $buffer;
    }


    /**
     * Ensures a subdirectory under your public-output folder exists,
     * then returns its absolute real path.
     *
     * Base path is:
     *      {publicDir}/{PUBLIC_OUTPUT_DIR}/{subDirName}
     *
     * @param string $subDirName Name of the subfolder under PUBLIC_OUTPUT_DIR.
     * @return string            Real path to the resolved directory.
     * @throws \RuntimeException on failure to create or resolve the path.
     */
    protected function resolveOutputSubDir(string $subDirName): string
    {
        // 1) get your base “public” dir (may throw if misconfigured)
        $publicDir = $this->resolveDir(NGS()->get('PUBLIC_DIR'));

        // 2) append the configured output folder constant and the user’s subdir
        $outputConstant = NGS()->get('PUBLIC_OUTPUT_DIR');
        $targetPath     = rtrim($publicDir, '/')
            . '/' . trim($outputConstant, '/')
            . '/' . trim($subDirName, '/');

        // 3) let resolveDir() handle creation + realpath verification
        return $this->resolveDir($targetPath);
    }


    /**
     * Ensure a directory exists and return its real path.
     *
     * @param string $dirName Relative or absolute path to the directory.
     * @return string Absolute real path.
     * @throws \RuntimeException if the directory cannot be created or resolved.
     */
    protected function resolveDir(string $dirName): string
    {
        // If it doesn't already exist, try to create it (recursively).
        if (!is_dir($dirName)) {
            if (!mkdir($dirName, 0755, true) && !is_dir($dirName)) {
                throw new \RuntimeException(sprintf(
                    'Directory "%s" could not be created.',
                    $dirName
                ));
            }
        }

        // Now resolve the real path.
        $realPath = realpath($dirName);
        if ($realPath === false) {
            throw new \RuntimeException(sprintf(
                'Directory "%s" exists but realpath() failed.',
                $dirName
            ));
        }

        return $realPath;
    }

}
