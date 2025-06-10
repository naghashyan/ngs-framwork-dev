<?php

/**
 * NGS predefined templater class
 * handle smarty and json responses
 *
 * @author Levon Naghashyan <levon@naghashyan.com>
 * @site https://naghashyan.com
 * @package ngs.framework.templater
 * @version 4.0.0
 * @year 2010-2020
 *
 * This file is part of the NGS package.
 *
 * @copyright Naghashyan Solutions LLC
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ngs\templater;

use ngs\templater\AbstractTemplater;
use ngs\templater\NgsSmartyTemplater;

class NgsTemplater extends AbstractTemplater
{
    /**
     * constructor
     * reading Smarty config and setting up smarty environment accordingly
     */
    private $smarty = null;
    private $template = null;
    private array $params = [];
    private $permalink = null;
    private array $smartyParams = [];
    private int $httpStatusCode = 200;
    private string $type = 'json';
    private bool $ngsFromException = false;

    public function __construct()
    {
    }

    public function setType($type)
    {
        $this->type = $type;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getSmartyTemplater()
    {
        return new NgsSmartyTemplater();
    }

    /**
     * @return bool
     */
    public function isNgsFromException()
    {
        return $this->ngsFromException;
    }

    /**
     * @param bool $ngsFromException
     */
    public function setNgsFromException($ngsFromException)
    {
        $this->ngsFromException = $ngsFromException;
    }


    /**
     * assign single smarty parameter
     *
     * @access public
     *
     * @param String $key
     * @param mixed $value
     *
     * @return void
     */
    public function assign($key, $value): void
    {
        $this->smartyParams[$key] = $value;
    }

    /**
     * assign single json parameter
     *
     * @access public
     *
     * @param String $key
     * @param mixed $value
     *
     * @return void
     */
    public function assignJson($key, $value): void
    {
        $this->params[$key] = $value;
    }

    /**
     * add multiple json parameters
     *
     * @access public
     * @param array $paramsArr
     *
     * @return void
     */
    public function assignJsonParams($paramsArr)
    {
        if (!is_array($paramsArr)) {
            $paramsArr = [$paramsArr];
        }
        $this->params = array_merge($this->params, $paramsArr);
    }

    /**
     * set template
     *
     * @param String $template
     *
     */
    public function setTemplate($template)
    {
        $this->template = $template;
    }

    /**
     * Return a template
     *
     * @return String $template
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * set template
     *
     * @param String $permalink
     *
     */
    public function setPermalink($permalink)
    {
        $this->permalink = $permalink;
    }

    /**
     * Return a template
     *
     * @return String $template|null
     */
    public function getPermalink()
    {
        return $this->permalink;
    }

    /**
     * set response http status code
     *
     * @param integer $httpStatusCode
     *
     */
    public function setHttpStatusCode($httpStatusCode)
    {
        $this->httpStatusCode = $httpStatusCode;
    }

    /**
     * get response http status code
     *
     */
    public function getHttpStatusCode()
    {
        return $this->httpStatusCode;
    }

    /**
     * display response
     * @param bool $fromExaption
     * @access public
     *
     */
    public function display(bool $fromExaption = false)
    {
        $requestContext = NGS()->createDefinedInstance('REQUEST_CONTEXT', \ngs\util\RequestContext::class);
        $loadMapper = NGS()->createDefinedInstance('LOAD_MAPPER', \ngs\routes\NgsLoadMapper::class);
        $this->setNgsFromException($fromExaption);
        $this->beforeDisplay();
        http_response_code($this->getHttpStatusCode());
        if (!$this->getTemplate()) {
            $this->displayJson($this->params);
            return;
        }
        $this->smarty = $this->getSmartyTemplater();
        foreach ($this->smartyParams as $key => $value) {
            $this->smarty->assign($key, $value);
        }
        if ($this->getType() === 'json') {
            $this->displayJson();
            return;
        }
        if (!NGS()->get('JS_FRAMEWORK_ENABLE')) {
            $this->displaySmarty();
            return;
        }
        $ext = pathinfo($this->getTemplate(), PATHINFO_EXTENSION);
        if ($ext !== 'json' && (NGS()->get('JS_FRAMEWORK_ENABLE') && !$requestContext->isAjaxRequest())) {
            $this->smarty->setCustomHeader($this->getCustomHeader());
            $this->displaySmarty();
            return;
        }
        if (NGS()->get('JS_FRAMEWORK_ENABLE') && $requestContext->isAjaxRequest()) {
            $params = [];
            $params['html'] = $this->smarty->fetch($this->getTemplate());
            $params['nl'] = $loadMapper->getNestedLoads();
            $params['pl'] = $this->getPermalink();
            $params['params'] = $this->params;
            $this->displayJson($params);
            return;
        }
        $this->displayJson();
    }


    private function displayJson($params = null): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if ($params !== null) {
            echo json_encode($params, JSON_THROW_ON_ERROR, 512);
            return;
        }
        foreach ($this->params as $key => $value) {
            $this->smarty->assign($key, $value);
        }
        if ($this->getTemplate()) {
            echo($this->smarty->fetch($this->getTemplate()));
        }
        return;
    }

    private function displaySmarty()
    {
        echo $this->fetchSmartyTemplate($this->getTemplate());
    }


    public function fetchSmartyTemplate($templatePath)
    {
        return $this->smarty->fetch($templatePath);
    }


    protected function beforeDisplay()
    {
        return;
    }


    protected function getCustomJsParams()
    {
        return [];
    }

    /**
     * @return string
     */
    protected function getCustomHeader()
    {
        return '';
    }
}
