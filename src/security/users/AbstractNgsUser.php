<?php

/**
 *
 * This class is a template for all authorized user classes.
 *
 * @author Levon Naghashyan <levon@naghashyan.com>
 * @site https://naghashyan.com
 * @mail levon@naghashyan.com
 * @year 2015-2019
 * @package ngs.framework.security.users
 * @version 3.8.0
 *
 * This file is part of the NGS package.
 *
 * @copyright Naghashyan Solutions LLC
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace ngs\security\users;

abstract class AbstractNgsUser
{
    /**
     * Abstract method for set user Id
     * Children of the NgsAbstractUser class should override this method
     *
     * @abstract
     *
     * @return void
     */
    abstract public function setId(int $id);

    /**
     * Abstract method for get user Id
     * Children of the NgsAbstractUser class should override this method
     *
     * @abstract
     *
     * @return integer|null
     */
    abstract public function getId();

    /**
     * Abstract method for validate user,
     * Children of the NgsAbstractUser class should override this method
     *
     * @abstract
     *
     * @return boolean
     */
    abstract public function validate();

    /**
     * Abstract method for getting user LEVEL (type),
     * Children of the NgsAbstractUser class should override this method
     *
     * @return integer
     */
    abstract public function getLevel();

    /**
     * Abstract method for getting userDto,
     * Children of the NgsAbstractUser class should override this method
     *
     * @return Object
     */
    abstract public function getUserDto();
}
