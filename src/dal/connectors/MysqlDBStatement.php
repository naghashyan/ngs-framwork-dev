<?php

/**
 * MysqlDBStatement
 *
 * @author Levon Naghashyan <levon@naghashyan.com>
 * @site https://naghashyan.com
 * @mail levon@naghashyan.com
 * @package ngs.framework.dal.connectors
 * @version 4.2.1
 * @year 2022
 *
 * This file is part of the NGS package.
 *
 * @copyright Naghashyan Solutions LLC
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace ngs\dal\connectors;

use ngs\exceptions\DebugException;

class MysqlDBStatement extends \PDOStatement
{
    public function execute(array|null $boundInputParams = null): bool
    {
        try {
            return parent::execute($boundInputParams);
        } catch (\PDOException $ex) {
            throw new DebugException($ex->getMessage(), $ex->getCode());
        }
    }
}
