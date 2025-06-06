<?php

/**
 * AbstractSolrMapper class is a base class for all mapper lasses.
 * It contains the basic functionality and also DBMS pointer.
 *
 * @author Levon Naghashyan <levon@naghashyan.com>
 * @site https://naghashyan.com
 * @package framework.dal.mappers
 * @version 4.0.0
 * @year 2014-2020
 * @copyright Naghashyan Solutions LLC
 */

namespace ngs\dal\mappers;

use ngs\dal\connectors\SolrDBMS;
use ngs\dal\dto\AbstractDto;
use ngs\exceptions\DebugException;
use Solarium\QueryType\Update\Query\Document;

abstract class AbstractSolrMapper extends AbstractMapper
{
    public SolrDBMS $dbms;

    /**
     * Initializes DBMS pointer.
     */
    public function __construct()
    {
        $host = NGS()->getConfig()->DB->solr->host;
        $user = NGS()->getConfig()->DB->solr->port;
        $path = NGS()->getConfig()->DB->solr->path;
        $this->dbms = new SolrDBMS($host, $user, $path, $this->getTableName());
    }

    /**
     * Create a select query instance.
     *
     * @return \Solarium\QueryType\Select\Query\Query
     */
    protected function getSelectQuery(): \Solarium\QueryType\Select\Query\Query
    {
        return $this->dbms->createSelect();
    }

    /**
     * Create an update query instance.
     *
     * @return \Solarium\QueryType\Update\Query\Query
     */
    protected function getUpdateQuery(): \Solarium\QueryType\Update\Query\Query
    {
        return $this->dbms->createUpdate();
    }

    /**
     * fill Solarium Document From NGS Dto
     *
     * @param AbstractDto $dto
     *
     * @return Document
     */
    private function fillSolariumDocumentFromDto($dto): Document
    {
        $doc = new Document();

        if (method_exists($dto, 'getSchemeArray')) {
            $dto_fields = array_values($dto->getSchemeArray());
            $db_fields = array_keys($dto->getSchemeArray());
            for ($i = 0, $iMax = count($dto_fields); $i < $iMax; $i++) {
                $functionName = 'get' . ucfirst($dto_fields[$i]);
                $val = $dto->$functionName();
                if (!isset($val)) {
                    continue;
                }
                if (!is_array($val)) {
                    $doc->setField($db_fields[$i], $val);
                    continue;
                }

                foreach ($val as $item) {
                    $doc->setField($db_fields[$i], $item);
                }
            }
        }

        return $doc;
    }

    /**
     * Inserts dto into table.
     *
     * @param AbstractDto $dto
     *
     * @return int|null
     */
    public function insertDto($dto): ?int
    {
        //validating input params
        if ($dto === null) {
            throw new DebugException('The input param can not be NULL.');
        }
        return (int) $this->insertDtos([$dto]);
    }

    /**
     * Inserts dtos into table.
     *
     * @param AbstractDto[] $dtos
     *
     * @return bool
     */
    public function insertDtos($dtos): bool
    {
        //validating input params
        if ($dtos === null) {
            throw new DebugException('The input param can not be NULL.');
        }

        $addDocsArr = [];
        $commitStatus = true;
        foreach ($dtos as $key => $dto) {
            $doc = $this->fillSolariumDocumentFromDto($dto);
            $addDocsArr[] = $doc;
            if ($key % NGS()->get('BULK_UPDATE_LIMIT')) {
                if (!$this->addCommit($addDocsArr)) {
                    $commitStatus = false;
                }
            }
        }
        if (count($addDocsArr) > 0) {
            if (!$this->addCommit($addDocsArr)) {
                $commitStatus = false;
            }
        }
        return $commitStatus;
    }

    /**
     *
     * Inserts dtos into table.
     *
     * @param array $docs
     * @return bool
     */
    private function addCommit(array $docs): bool
    {
        $query = $this->getUpdateQuery();
        $query->addDocuments($docs);
        $query->addCommit(true, true, false);
        return $this->dbms->update($query)->getStatus() === 0;
    }

    /**
     * Updates table fields by primary key.
     * DTO must contain primary key value.
     *
     * @param AbstractDto $dto
     * @return int|null
     * @throws DebugException
     */
    public function updateByPK($dto)
    {

        //validating input params
        if ($dto === null) {
            throw new DebugException('The input param can not be NULL.');
        }
        $getPKFunc = $this->getCorrespondingFunctionName($dto->getMapArray(), $this->getPKFieldName(), 'get');
        $pk = $dto->$getPKFunc();
        if (!isset($pk)) {
            throw new DebugException('The primary key is not set.');
        }

        $dto_fields = array_values($dto->getMapArray());
        $db_fields = array_keys($dto->getMapArray());

        $query = $this->getUpdateQuery();
        $doc = $query->createDocument();

        for ($i = 0, $iMax = count($dto_fields); $i < $iMax; $i++) {
            if ($dto_fields[$i] === $this->getPKFieldName()) {
                continue;
            }
            $functionName = 'get' . ucfirst($dto_fields[$i]);
            $val = $dto->$functionName();

            if (isset($val)) {
                if (method_exists($doc, 'setFieldModifier')) {
                    $doc->setFieldModifier($db_fields[$i], 'set');
                }

                if (method_exists($doc, 'setField')) {
                    $doc->setField($db_fields[$i], $val);
                }
            }
        }

        if (method_exists($doc, 'setKey')) {
            $doc->setKey($this->getPKFieldName(), $pk);
        }

        //add document and commit
        $query->addDocument($doc)->addCommit(true, true, false);

        // this executes the query and returns the result
        $result = $this->dbms->update($query);

        return (int) !$result->getStatus();
    }

    /**
     * Selects from table by primary key and returns corresponding DTO
     *
     * @param object $id
     * @return AbstractDto
     */
    public function selectByPK($id)
    {
        //TODO
    }

    /**
     * Delete the row by primary key
     *
     * @param int $id - the unique identifier of table
     * @return bool|null
     */
    public function deleteByPK($id): ?bool
    {
        if (is_numeric($id)) {
            return $this->deleteByPKeys([$id]);
        }
        return null;
    }

    /**
     * Delete the rows by primary keys
     *
     * @param array $ids - the unique identifier of table
     * @return bool
     */
    public function deleteByPKeys($ids): bool
    {
        if ($ids == null || !is_array($ids)) {
            throw new DebugException('The input param can not be NULL.');
        }
        $query = $this->getUpdateQuery();
        $query->addDeleteByIds($ids);
        $query->addCommit(true, true, false);

        return $this->dbms->update($query)->getStatus() === 0;
    }

    /**
     * Executes the query and returns an array of corresponding DTOs
     *
     * @param $query
     * @return array
     */
    public function fetchRows($query)
    {
        $response = $this->dbms->select($query);
        $resultArr = $this->createDtoFromResultArray($response);
        return ['count' => $response->count(), 'dtos' => $resultArr];
    }

    /**
     * create dtos array from mysql fethed reuslt array
     *
     * @param array $results
     * @return array array
     */
    protected function createDtoFromResultArray($results): array
    {
        $resultArr = [];
        foreach ($results as $result) {
            $tmpArr = [];
            foreach ($result as $key => $value) {
                $tmpArr[$key] = $value;
            }
            $dto = $this->createDto();

            $dto->fillDtoFromArray($tmpArr);
            $resultArr[] = $dto;
        }
        return $resultArr;
    }
}
