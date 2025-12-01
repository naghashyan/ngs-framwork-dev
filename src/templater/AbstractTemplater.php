<?php

/**
 * abstract templater for all NGS loads and actions
 *
 * This file demonstrates the rich information that can be included in
 * in-code documentation through DocBlocks and tags.
     * @author Naghashyan Solutions <info@naghashyan.com>
     * @version 5.0.0
     * @package ngs.framework
 *
 * This file is part of the NGS package.
 *
 * @copyright Naghashyan Solutions LLC
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ngs\templater;

abstract class AbstractTemplater
{
    /**
     * this method should assign params to templater
     *
     * @abstract
     * @access public
     * @param String $key
     * @param mixed $value
     * @return void
     */
    abstract public function assign(string $key, mixed $value): void;

    /**
     * this method should display (echo) response result
     *
     * @abstract
     * @access public
     * @return void
     */
    abstract public function display();
}
