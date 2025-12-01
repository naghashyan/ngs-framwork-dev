<?php

/**
 * MysqlDBStatement
 *
     * @author Naghashyan Solutions <info@naghashyan.com>
     * @site https://naghashyan.com
 * @mail levon@naghashyan.com
     * @package ngs.framework
     * @version 5.0.0
     * @year 2007-2026
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
