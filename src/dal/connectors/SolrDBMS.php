<?php

/**
 *
 * Solr server connector wrapper for NGS framework
 * using Solarium
 *
 *
     * @author Naghashyan Solutions <info@naghashyan.com>
     * @site https://naghashyan.com
 * @mail levon@naghashyan.com
     * @package ngs.framework
     * @version 5.0.0
     * @year 2007-2026
 * @copyright Naghashyan Solutions LLC
 */

namespace ngs\dal\connectors;

use Solarium\Core\Client\Adapter\Curl;
use Symfony\Component\EventDispatcher\EventDispatcher;

class SolrDBMS extends \Solarium\Client
{
    /**
     * Singleton instance of class
     */
    private static ?SolrDBMS $instance = null;

    /**
     * Tries to connect to a Solr Server
     * SolrDBMS constructor.
     * @param string $db_host
     * @param int $db_port
     * @param string $db_path
     * @param string $db_core
     */
    public function __construct(string $db_host, int $db_port, string $db_path, string $db_core)
    {
        $config = [
            'endpoint' => [
                'localhost' => [
                    'host' => $db_host,
                    'port' => $db_port,
                    'path' => $db_path,
                    'core' => $db_core
                ]
            ]
        ];
        $adapter = new Curl();
        $eventDispatcher = new EventDispatcher();
        parent::__construct($adapter, $eventDispatcher, $config);
    }

    /**
     *
     * Returns an singleton instance of class.
     *
     * @param string $db_host
     * @param int $db_port
     * @param string $db_path
     * @param string $db_core
     * @return SolrDBMS
     */
    public static function getInstance(string $db_host, int $db_port, string $db_path, string $db_core): SolrDBMS
    {
        if (is_null(self::$instance)) {
            self::$instance = new SolrDBMS($db_host, $db_port, $db_path, $db_core);
        }
        return self::$instance;
    }
}
