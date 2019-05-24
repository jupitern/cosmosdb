<?php

namespace Jupitern\CosmosDb;

/*
 * Based on the AzureDocumentDB-PHP library written by Takeshi Sakurai.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Microsoft Azure Document DB Library for PHP
 * @link http://msdn.microsoft.com/en-us/library/azure/dn781481.aspx
 * @link https://github.com/jupitern/cosmosdb
 */

class QueryBuilder
{
    private $collection = "";
    private $partitionKey = null;
    private $partitionValue = null;
    private $queryString = "";
    private $fields = "";
    private $from = "c";
    private $join = "";
    private $where = "";
    private $order = null;
    private $limit = null;
    private $triggers = [];
    private $params = [];
    private $response = null;
    private $multipleResults = false;

    /**
     * Initializes the Table.
     *
     * @return static
     */
    public static function instance()
    {
        return new static();
    }

    /**
     * @param CosmosDbCollection $collection
     * @return $this
     */
    public function setCollection(CosmosDbCollection $collection)
    {
        $this->collection = $collection;
        return $this;
    }

    /**
     * @param string|array $fields
     * @return $this
     */
    public function select($fields)
    {
        if (is_array($fields))
            $fields = 'c["' . implode('"], c["', $fields) . '"]';
        $this->fields = $fields;
        return $this;
    }

    /**
     * @param string $from
     * @return $this
     */
    public function from($from)
    {
        $this->from = $from;
        return $this;
    }

    /**
     * @param string $join
     * @return $this
     */
    public function join($join)
    {
        $this->join .= " {$join} ";
        return $this;
    }

    /**
     * @param string $where
     * @return $this
     */
    public function where($where)
    {
        if (empty($where)) return $this;
        $this->where .= !empty($this->where) ? " and {$where} " : "{$where}";

        return $this;
    }

    /**
     * @param string $field
     * @param string $value
     * @return QueryBuilder
     */
    public function whereStartsWith($field, $value)
	{
		return $this->where("STARTSWITH($field, '{$value}')");
	}

    /**
     * @param string $field
     * @param string $value
     * @return QueryBuilder
     */
    public function whereEndsWith($field, $value)
	{
		return $this->where("ENDSWITH($field, '{$value}')");
	}

    /**
     * @param string $field
     * @param string $value
     * @return QueryBuilder
     */
    public function whereContains($field, $value)
	{
		return $this->where("CONTAINS($field, '{$value}'");
	}

    /**
     * @param string $field
     * @param array $values
     * @return $this|QueryBuilder
     */
    public function whereIn($field, $values)
	{
	    if (!is_array($values) || empty($values)) return $this;
		if (is_array($values)) $values = implode("', '", $values);

		return $this->where("$field IN('{$values}')");
	}

    /**
     * @param string $field
     * @param array $values
     * @return $this|QueryBuilder
     */
    public function whereNotIn($field, $values)
    {
        if (!is_array($values) || empty($values)) return $this;
        if (is_array($values)) $values = implode("', '", $values);

        return $this->where("$field NOT IN('{$values}')");
    }

    /**
     * @param string $order
     * @return $this
     */
    public function order($order)
    {
        $this->order = $order;
        return $this;
    }

    /**
     * @param int $limit
     * @return $this
     */
    public function limit($limit)
    {
        $this->limit = (int)$limit;
        return $this;
    }

    /**
     * @param array $params
     * @return $this
     */
    public function params($params)
    {
        $this->params = (array)$params;
        return $this;
    }

    /**
     * @param boolean $isCrossPartition
     * @return $this
     */
    public function findAll($isCrossPartition = false)
    {
        $this->response = null;
        $this->multipleResults = true;

        $limit = $this->limit != null ? "top " . (int)$this->limit : "";
        $fields = !empty($this->fields) ? $this->fields : '*';
        $where = $this->where != "" ? "where {$this->where}" : "";
        $order = $this->order != "" ? "order by {$this->order}" : "";

        $query = "SELECT {$limit} {$fields} FROM {$this->from} {$this->join} {$where} {$order}";

        $this->response = $this->collection->query($query, $this->params, $isCrossPartition);

        return $this;
    }

    /**
     * @param boolean $isCrossPartition
     * @return $this
     */
    public function find($isCrossPartition = false)
    {
        $this->response = null;
        $this->multipleResults = false;

        $partitionValue = $this->partitionValue != null ? $this->partitionValue : null;

        $fields = !empty($this->fields) ? $this->fields : '*';
        $where = $this->where != "" ? "where {$this->where}" : "";
        $order = $this->order != "" ? "order by {$this->order}" : "";

        $query = "SELECT top 1 {$fields} FROM {$this->from} {$this->join} {$where} {$order}";

        $this->response = $this->collection->query($query, $this->params, $isCrossPartition, $partitionValue);

        return $this;
    }

    /* insert / update */

    /**
     * @param $document
     * @return $this
     */
    public function setDocument($document)
    {
        if (is_array($document) || is_object($document)) {
            $this->document = json_encode($document);
        }

        return $this;
    }

    /**
     * @param $fieldName
     * @return $this
     */
    public function setPartitionKey($fieldName)
    {
        $this->partitionKey = $fieldName;

        return $this;
    }

    /**
     * @return null
     */
    public function getPartitionKey()
	{
		return $this->partitionKey;
    }
    
    /**
     * @param $fieldName
     * @return $this
     */
    public function setPartitionValue($fieldName)
    {
        $this->partitionValue = $fieldName;

        return $this;
    }

    /**
     * @return null
     */
    public function getPartitionValue()
	{
		return $this->partitionValue;
	}

    /**
     * @param $fieldName
     * @return $this
     */
    public function setQueryString(string $string)
    {
        $this->queryString .= $string;
        return $this;
    }

    /**
     * @return null
     */
    public function getQueryString()
	{
		return $this->queryString;
	}

    /**
     * @param boolean $isCrossPartition
     * @return $this
     */
    public function isNested(string $partitionKey)
    {
        # strip any slashes from the beginning
        # and end of the partition key
        $partitionKey = trim($partitionKey, '/');

        # if the partition key contains slashes, the user
        # is referencing a nested value, so we should search for it
        if (strpos($partitionKey, '/') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Find and set the partition value
     * 
     * @param object document
     * @param bool if true, return property structure formatted for use in Azure query string
     * @return string partition value
     */
    public function findPartitionValue(object $document, bool $toString = false)
    {
        # if the partition key contains slashes, the user
        # is referencing a nested value, so we should find it
        if ($this->isNested($this->partitionKey)) {

            # explode the key into its properties
            $properties = explode("/", $this->partitionKey);

            # return the property structure
            # formatted as a cosmos query string
            if ($toString) {

                foreach( $properties as $p ) {
                    $this->setQueryString($p);
                }

                return $this->queryString;
            }
            # otherwise, iterate through the document
            # and find the value of the property key
            else {

                foreach( $properties as $p ) {
                    $document = (object)$document->{$p};
                }

                return $document->scalar;
            }
        }
        # otherwise, assume the key is in the root of the
        # document and return the value of the property key
        else {
            return $document->{$this->partitionKey};
        }
    }

    /**
     * @param $document
     * @return string|null
     * @throws \Exception
     */
    public function save($document)
    {
        $document = (object)$document;

        $rid = is_object($document) && isset($document->_rid) ? $document->_rid : null;
        $partitionValue = $this->partitionKey != null ? $this->findPartitionValue($document) : null;
        $document = json_encode($document);

        $result = $rid ?
            $this->collection->replaceDocument($rid, $document, $partitionValue, $this->triggersAsHeaders("replace")) :
            $this->collection->createDocument($document, $partitionValue, $this->triggersAsHeaders("create"));
        $resultObj = json_decode($result);

        if (isset($resultObj->code) && isset($resultObj->message)) {
            throw new \Exception("$resultObj->code : $resultObj->message");
        }

        return $resultObj->_rid ?? null;
    }

    /* delete */

    /**
     * @param boolean $isCrossPartition
     * @return boolean
     */
    public function delete($isCrossPartition = false)
    {
        $this->response = null;

        $select = $this->fields != "" ?
            $this->fields : "c._rid" . ($this->partitionKey != null ? ", c.{$this->findPartitionValue($document, true)}" : "");

        $document = $this->select($select)->find($isCrossPartition)->toObject();

        if ($document) {
            $partitionValue = $this->partitionKey != null ? $this->findPartitionValue($document) : null;
            $this->response = $this->collection->deleteDocument($document->_rid, $partitionValue, $this->triggersAsHeaders("delete"));

            return true;
        }

        return false;
    }

    /**
     * @param boolean $isCrossPartition
     * @return boolean
     */
    public function deleteAll($isCrossPartition = false)
    {
        $this->response = null;

        $select = $this->fields != "" ?
            $this->fields : "c._rid" . ($this->partitionKey != null ? ", c.{$this->findPartitionValue($document, true)}" : "");

        $response = [];
        foreach ((array)$this->select($select)->findAll($isCrossPartition)->toObject() as $document) {
            $partitionValue = $this->partitionKey != null ? $this->findPartitionValue($document) : null;
            $response[] = $this->collection->deleteDocument($document->_rid, $partitionValue, $this->triggersAsHeaders("delete"));
        }

        $this->response = $response;
        return true;
    }

    /* triggers */

    /**
     * @param string $operation
     * @param string $type
     * @param string $id
     * @return QueryBuilder
     * @throws \Exception
     */
    public function addTrigger(string $operation, string $type, string $id): self
    {
        $operation = \strtolower($operation);
        if (!\in_array($operation, ["all", "create", "delete", "replace"]))
            throw new \Exception("Trigger: Invalid operation \"{$operation}\"");

        $type = \strtolower($type);
        if (!\in_array($type, ["post", "pre"]))
            throw new \Exception("Trigger: Invalid type \"{$type}\"");

        if (!isset($this->triggers[$operation][$type]))
            $this->triggers[$operation][$type] = [];

        $this->triggers[$operation][$type][] = $id;
        return $this;
    }

    /**
     * @param string $operation
     * @return array
     */
    protected function triggersAsHeaders(string $operation): array
    {
        $headers = [];

        // Add headers for the current operation type at $operation (create|delete!replace)
        if (isset($this->triggers[$operation])) {
            foreach ($this->triggers[$operation] as $name => $ids) {
                $ids = \is_array($ids) ? $ids : [$ids];
                $headers["x-ms-documentdb-{$name}-trigger-include"] = \implode(",", $ids);
            }
        }

        // Add headers for the special "all" operations type that should always run
        if (isset($this->triggers["all"])) {
            foreach ($this->triggers["all"] as $name => $ids) {
                $headerKey = "x-ms-documentdb-{$name}-trigger-include";
                $ids = \implode(",", \is_array($ids) ? $ids : [$ids]);
                $headers[$headerKey] = isset($headers[$headerKey]) ? $headers[$headerKey] .= "," . $ids : $headers[$headerKey] = $ids;
            }
        }

        return $headers;
    }

    /* helpers */

    /**
     * @return string
     */
    public function toJson()
    {
        return $this->response;
    }

    /**
     * @return mixed
     */
    public function toObject()
    {
        $res = json_decode($this->response);
        $docs = $res->Documents ?? [];
        if (!is_array($docs) || empty($docs)) return [];

        if ($this->multipleResults) {
            return $docs;
        }

        return isset($docs[0]) ? $docs[0] : null;
    }

    /**
     * @param $arrayKey
     * @return array|mixed
     */
    public function toArray($arrayKey = null)
    {
        $res = json_decode($this->response);
        $docs = $res->Documents ?? [];

        if ($this->multipleResults) {
            return $arrayKey != null ? array_combine(array_column($docs, $arrayKey), $docs) : $docs;
        }

        return isset($docs[0]) ? $docs[0] : null;
    }

    /**
     * @param $fieldName
     * @param null $default
     * @return mixed
     */
    public function getValue($fieldName, $default = null)
    {
        $obj = $this->toObject();
        return isset($obj->{$fieldName}) ? $obj->{$fieldName} : $default;
    }
    
}
