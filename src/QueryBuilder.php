<?php

namespace Jupitern\CosmosDb;

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
        $this->join = $join;
        return $this;
    }


    /**
     * @param $where
     * @return $this
     */
    public function where($where)
    {
        $this->where = $where;
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
     * @return $this
     */
    public function findAll()
    {
        $this->response = null;
        $this->multipleResults = true;

        $limit = $this->limit != null ? "top ".(int)$this->limit : "";
        $fields = !empty($this->fields) ? $this->fields : '*';
        $where = $this->where != "" ? "where {$this->where}" : "";
        $order = $this->order != "" ? "order by {$this->order}" : "";

        $query = "SELECT {$limit} {$fields} FROM {$this->collection} {$this->join} {$where} {$order}";

        $col = $this->connection->selectCollection($this->collection);
        $this->response = $col->query($query);

        return $this;
    }


    /**
     * @return $this
     */
    public function find()
    {
        $this->response = null;
        $this->multipleResults = false;

        $this->limit = 1;
        $this->findAll();

        return $this;
    }


    /**
     * @return $this
     */
    public function delete()
    {
        $this->response = null;
        $this->multipleResults = false;

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
        $this->multipleResults = true;
        $col = $this->connection->selectCollection($this->collection);

        $response = [];
        foreach ((array)$this->findAll()->toObject() as $doc) {
            $response[] = $col->deleteDocument($doc->_rid);
        }

        $this->response = $response;
        return $this;
    }


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

        return count($docs) > 1 ? $docs : $docs[0];
    }


    /**
     * @return array|mixed
     */
    public function toArray()
    {
        $res = json_decode($this->response);
        $docs = $res->Documents ?? [];

        return $this->multipleResults == true && count($docs) > 0 ? $docs : $docs[0];
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
