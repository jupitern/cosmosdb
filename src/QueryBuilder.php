<?php

namespace Jupitern\CosmosDb;

/*
 * Copyright (C) 2017 Nuno Chaves <nunochaves@sapo.pt>
 *
 * Licensed under the Apache License, Version 2.0 (the &quot;License&quot;);
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an &quot;AS IS&quot; BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class QueryBuilder
{

    /** @var \Jupitern\CosmosDb\CosmosDbDatabase $db */
    private $db = null;
    private $collection = "";
    private $partitionKey = null;
    private $fields = "";
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
     * @param CosmosDbDatabase $db
     * @return $this
     */
    public function setDatabase(CosmosDbDatabase $db)
    {
        $this->db = $db;
        return $this;
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
     * @param $fields
     * @return $this
     */
    public function select($fields)
    {
        $this->fields = $fields;
        return $this;
    }


    /**
     * @param $join
     * @return $this
     */
    public function join($join)
    {
        $this->join .= " {$join} ";
        return $this;
    }


    /**
     * @param $where
     * @return $this
     */
    public function where($where)
    {
        if (empty($where)) return $this;
        $this->where .= !empty($this->where) ? " and {$where} " : "{$where}";

        return $this;
    }


    /**
     * @param $order
     * @return $this
     */
    public function order($order)
    {
        $this->order = $order;
        return $this;
    }


    /**
     * @param $limit
     * @return $this
     */
    public function limit($limit)
    {
        $this->limit = $limit;
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

        $query = "SELECT {$limit} {$fields} FROM c {$this->join} {$where} {$order}";

        $this->response = $this->collection->query($query, $this->params, $isCrossPartition);

        return $this;
    }


    /**
     * @return $this
     */
    public function find()
    {
        $this->response = null;
        $this->multipleResults = false;

        $fields = !empty($this->fields) ? $this->fields : '*';
        $where = $this->where != "" ? "where {$this->where}" : "";
        $order = $this->order != "" ? "order by {$this->order}" : "";

        $query = "SELECT top 1 {$fields} FROM c {$this->join} {$where} {$order}";

        $this->response = $this->collection->query($query, $this->params, $this->partitionKey);

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
     * @param $document
     * @return string|null
     * @throws \Exception
     */
    public function save($document)
    {
        $document = (object)$document;

        $rid = is_object($document) && isset($document->_rid) ? $document->_rid : null;
        $partitionValue = $this->partitionKey != null ? $document->{$this->partitionKey} : null;
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

        // Add headers for the current operation type at $operation (create|detete!replace)
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

    /* DELETE */

    /**
     * @return boolean
     */
    public function delete()
    {
        $this->response = null;
        $doc = $this->find()->toObject();

        if ($doc) {
            $partitionValue = $this->partitionKey != null ? $doc->{$this->partitionKey} : null;
            $this->response = $this->collection->deleteDocument($doc->_rid, $partitionValue, $this->triggersAsHeaders("delete"));

            return true;
        }

        return false;
    }


    /**
     * @return boolean
     */
    public function deleteAll()
    {
        $this->response = null;

        $response = [];
        foreach ((array)$this->findAll()->toObject() as $doc) {
            $partitionValue = $this->partitionKey != null ? $doc->{$this->partitionKey} : null;
            $response[] = $this->collection->deleteDocument($doc->_rid, $partitionValue, $this->triggersAsHeaders("delete"));
        }

        $this->response = $response;
        return true;
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