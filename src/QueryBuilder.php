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

    /** @var \Jupitern\CosmosDb\CosmosDbDatabase $connection */
    private $connection = null;
    private $collection = "";
    private $fields = "";
    private $join = "";
    private $where = "";
    private $order = null;
    private $limit = null;
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
     * @param CosmosDbDatabase $connection
     * @return $this
     */
    public function setConnection(CosmosDbDatabase $connection)
    {
        $this->connection = $connection;
        return $this;
    }


    /**
     * @param $collection
     * @return $this
     */
    public function collection($collection)
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
     * @param $limit
     * @return $this
     */
    public function params($params)
    {
        $this->params = $params;
        return $this;
    }


    /**
     * @return $this
     */
    public function findAll()
    {
        $this->response = null;
        $this->multipleResults = true;

        $limit = $this->limit != null ? "top " . (int)$this->limit : "";
        $fields = !empty($this->fields) ? $this->fields : '*';
        $where = $this->where != "" ? "where {$this->where}" : "";
        $order = $this->order != "" ? "order by {$this->order}" : "";

        $query = "SELECT {$limit} {$fields} FROM {$this->collection} {$this->join} {$where} {$order}";

        $col = $this->connection->selectCollection($this->collection);
        $this->response = $col->query($query, $this->params);

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

        $query = "SELECT top 1 {$fields} FROM {$this->collection} {$this->join} {$where} {$order}";

        $col = $this->connection->selectCollection($this->collection);
        $this->response = $col->query($query, $this->params);

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
     * @param $document
     * @return null
     * @throws \Exception
     */
    public function save($document)
    {
        $rid = null;

        if (is_array($document) || is_object($document)) {
            if (is_object($document) && isset($document->_rid)) $rid = $document->_rid;
            elseif (is_array($document) && array_key_exists('_rid', $document)) $rid = $document['_rid'];

            $document = json_encode($document);
        }

        $col = $this->connection->selectCollection($this->collection);
        $result = $rid ?
            $col->replaceDocument($rid, $document) :
            $col->createDocument($document);
        $resultObj = json_decode($result);

        if (isset($resultObj->code) && isset($resultObj->message)) {
            throw new \Exception("$resultObj->code : $resultObj->message");
        }

        return $resultObj->_rid ?? null;
    }

    /* DELETE */

    /**
     * @return $this
     */
    public function delete()
    {
        $this->response = null;
        $doc = $this->find()->toObject();

        if ($doc) {
            $col = $this->connection->selectCollection($this->collection);
            $this->response = $col->deleteDocument($doc->_rid);
        }

        return $this;
    }


    /**
     * @return $this
     */
    public function deleteAll()
    {
        $this->response = null;
        $col = $this->connection->selectCollection($this->collection);

        $response = [];
        foreach ((array)$this->findAll()->toObject() as $doc) {
            $response[] = $col->deleteDocument($doc->_rid);
        }

        $this->response = $response;
        return $this;
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
