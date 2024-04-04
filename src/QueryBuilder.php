<?php

namespace Jupitern\CosmosDb;

class QueryBuilder
{
    private CosmosDbCollection $collection;
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
    public function setCollection(CosmosDbCollection $collection): static
    {
        $this->collection = $collection;
        return $this;
    }

    /**
     * @param array|string $fields
     * @return $this
     */
    public function select(array|string $fields): static
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
    public function from(string $from): static
    {
        $this->from = $from;
        return $this;
    }

    /**
     * @param string $join
     * @return $this
     */
    public function join(string $join): static
    {
        $this->join .= " {$join} ";
        return $this;
    }

    /**
     * @param string $where
     * @return $this
     */
    public function where(string $where): static
    {
        if (empty($where)) return $this;
        $this->where .= !empty($this->where) ? " and {$where} " : "{$where}";

        return $this;
    }

    /**
     * @param string $field
     * @param mixed $value
     * @return QueryBuilder
     */
    public function whereStartsWith(string $field, mixed $value): static
    {
        return $this->where("STARTSWITH($field, '{$value}')");
    }

    /**
     * @param string $field
     * @param mixed $value
     * @return QueryBuilder
     */
    public function whereEndsWith(string $field, mixed $value): static
    {
        return $this->where("ENDSWITH($field, '{$value}')");
    }

    /**
     * @param string $field
     * @param mixed $value
     * @return QueryBuilder
     */
    public function whereContains(string $field, mixed $value): static
    {
        return $this->where("CONTAINS($field, '{$value}'");
    }

    /**
     * @param string $field
     * @param array $values
     * @return $this|QueryBuilder
     */
    public function whereIn(string $field, array $values): QueryBuilder|static
    {
        if (empty($values)) return $this;

        return $this->where("$field IN('" . implode("', '", $values) . "')");
    }

    /**
     * @param string $field
     * @param array $values
     * @return $this|QueryBuilder
     */
    public function whereNotIn(string $field, array $values): QueryBuilder|static
    {
        if (empty($values)) return $this;

        return $this->where("$field NOT IN('" . implode("', '", $values) . "')");
    }

    /**
     * @param string $order
     * @return $this
     */
    public function order(string $order): static
    {
        $this->order = $order;
        return $this;
    }

    /**
     * @param int $limit
     * @return $this
     */
    public function limit(int $limit): static
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * @param array $params
     * @return $this
     */
    public function params(array $params): static
    {
        $this->params = $params;
        return $this;
    }

    /**
     * @param boolean $isCrossPartition
     * @return $this
     */

    public function findAll(bool $isCrossPartition = false): static
    {
        $this->response = null;
        $this->multipleResults = true;

        $partitionValue = $this->partitionValue != null ? $this->partitionValue : null;

        $limit = $this->limit != null ? "top " . (int)$this->limit : "";
        $fields = !empty($this->fields) ? $this->fields : '*';
        $where = $this->where != "" ? "where {$this->where}" : "";
        $order = $this->order != "" ? "order by {$this->order}" : "";

        $query = "SELECT {$limit} {$fields} FROM {$this->from} {$this->join} {$where} {$order}";

        $this->response = $this->collection->query($query, $this->params, $isCrossPartition, $partitionValue);

        return $this;
    }

    /**
     * @param boolean $isCrossPartition
     * @return $this
     */
    public function find(bool $isCrossPartition = false): static
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
     * @param $fieldName
     * @return $this
     */
    public function setPartitionKey($fieldName): static
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
    public function setPartitionValue($fieldName): static
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
     * @param string $string
     * @return $this
     */
    public function setQueryString(string $string): static
    {
        $this->queryString .= $string;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getQueryString(): ?string
    {
        return $this->queryString;
    }

    /**
     * @param string $partitionKey
     * @return bool|QueryBuilder
     */
    public function isNested(string $partitionKey): bool|static
    {
        # strip any slashes from the beginning
        # and end of the partition key
        $partitionKey = trim($partitionKey, '/');

        # if the partition key contains slashes, the user
        # is referencing a nested value, so we should search for it
        return str_contains($partitionKey, '/');
    }

    /**
     * @param string $path Instances of / in property names within $path must be escaped as /~1
     * @param mixed $value
     * @return array Fully formed ADD patch operation element
     */

    public function getPatchOpAdd(string $path, mixed $value): array
    {

        $op = [
            'op' => 'add',
            'path' => str_replace('~', '~0', $path),
            'value' => $value
        ];
        return $op;
    }

    /**
     * @param string $path Instances of / in property names within $path must be escaped as /~1
     * @param mixed $value
     * @return array Fully formed SET patch operation element
     */

    public function getPatchOpSet(string $path, mixed $value): array
    {

        $op = [
            'op' => 'set',
            'path' => str_replace('~', '~0', $path),
            'value' => $value
        ];
        return $op;
    }

    /**
     * @param string $path Instances of / in property names within $path must be escaped as /~1
     * @param mixed $value
     * @return array Fully formed REPLACE patch operation element
     */

    public function getPatchOpReplace(string $path, mixed $value): array
    {

        $op = [
            'op' => 'replace',
            'path' => str_replace('~', '~0', $path),
            'value' => $value
        ];
        return $op;
    }

   /**
     * @param string $path Instances of / in property names within $path must be escaped as /~1
     * @return array Fully formed REMOVE patch operation element
     */

    public function getPatchOpRemove(string $path): array
    {

        $op = [
            'op' => 'remove',
            'path' => str_replace('~', '~0', $path),
        ];
        return $op;
    }

    /**
     * @param string $path Instances of / in property names within $path must be escaped as /~1
     * @param int $value
     * @return array Fully formed INCR patch operation element
     */

    public function getPatchOpIncrement(string $path, int $value): array
    {

        $op = [
            'op' => 'replace',
            'path' => str_replace('~', '~0', $path),
            'value' => $value
        ];
        return $op;
    }

    /**
     * @param string $fromPath Source property - Instances of / in property names within $path must be escaped as /~1
     * @param string $toPath Destination property - Instances of / in property names within $path must be escaped as /~1
     * @return array Fully formed MOVE patch operation element
     */

    public function getPatchOpMove(string $fromPath, string $toPath): array
    {

        $op = [
            'op' => 'replace',
            'from' => str_replace('~', '~0', $fromPath),
            'path' => str_replace('~', '~0', $toPath),
        ];
        return $op;
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
            $properties = array_values(array_filter(explode("/", $this->partitionKey)));

            # return the property structure
            # formatted as a cosmos query string
            if ($toString) {

                foreach ($properties as $p) {
                    $this->setQueryString($p);
                }

                return $this->queryString;
            }
            # otherwise, iterate through the document
            # and find the value of the property key
            else {

                foreach ($properties as $p) {
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

    /**
     * @param $rid_doc
     * @param $patchOps Array Array of operations, max 10 per request	
     * @return string|null
     * @throws \Exception
     */
    public function patch(string $rid_doc, array $patchOps)
    {
        if (count($patchOps) > 10) {
            //Throw an equivalent HTTP error rather than waste a request to have the API return the same
            throw new \Exception("400 : PATCH supports maximum of 10 operations per request");
        };

        $partitionValue = $this->partitionKey != null ? $this->partitionValue : null;
        //Conditional patch is possible - check if QueryBuilder was set up with a condition and append
        $condition = ($this->where) ? 'from ' . $this->from . ' where ' . $this->where : '';

        $updates = [];
        if ($condition) {
            $updates['condition'] = $condition;
        };
        $updates['operations'] = $patchOps;
        $json = json_encode($updates);

        $result = $this->collection->patchDocument($rid_doc, $json, $partitionValue, $this->triggersAsHeaders("patch"));
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
    public function delete(bool $isCrossPartition = false): bool
    {
        $this->response = null;

        $select = $this->fields != "" ?
            $this->fields : "c._rid" . ($this->partitionKey != null ? ", c." . $this->partitionKey : "");
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
    public function deleteAll(bool $isCrossPartition = false): bool
    {
        $this->response = null;

        $select = $this->fields != "" ?
            $this->fields : "c._rid" . ($this->partitionKey != null ? ", c." . $this->partitionKey : "");
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
        if (!\in_array($operation, ["all", "create", "delete", "replace", "patch"]))
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

        // Add headers for the current operation type at $operation (create|delete!replace|patch)
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
        /*
         * If the CosmosDB result set contains many documents, CosmosDB might apply pagination. If this is detected,
         * all pages are requested one by one, until all results are loaded. These individual responses are contained
         * in $this->response. If no pagination is applied, $this->response is an array containing a single response.
         *
         * $results holds the documents returned by each of the responses.
         */
        $results = [
            '_rid' => '',
            '_count' => 0,
            'Documents' => []
        ];
        foreach ($this->response as $response) {
            $res = json_decode($response);
            $results['_rid'] = $res->_rid;
            $results['_count'] = $results['_count'] + $res->_count;
            $docs = $res->Documents ?? [];
            $results['Documents'] = array_merge($results['Documents'], $docs);
        }
        return json_encode($results);
    }

    /**
     * @param $arrayKey
     * @return mixed
     */
    public function toObject($arrayKey = null)
    {
        /*
         * If the CosmosDB result set contains many documents, CosmosDB might apply pagination. If this is detected,
         * all pages are requested one by one, until all results are loaded. These individual responses are contained
         * in $this->response. If no pagination is applied, $this->response is an array containing a single response.
         *
         * $results holds the documents returned by each of the responses.
         */
        $results = [];
        foreach ((array)$this->response as $response) {
            $res = json_decode($response);
            if (isset($res->Documents)) {
                array_push($results, ...$res->Documents);
            } else {
                $results[] = $res;
            }
        }

        if ($this->multipleResults && $arrayKey != null) {
            $results = array_combine(array_column($results, $arrayKey), $results);
        }

        return $this->multipleResults ? $results : ($results[0] ?? null);
    }

    /**
     * @param $arrayKey
     * @return array|mixed
     */
    public function toArray($arrayKey = null): array|null
    {
        $results = (array)$this->toObject($arrayKey);

        if ($this->multipleResults && is_array($results)) {
            array_walk($results, function (&$value) {
                $value = (array)$value;
            });
        }

        return $this->multipleResults ? $results : ((array)$results ?? null);
    }

    /**
     * @param $fieldName
     * @param null $default
     * @return mixed
     */
    public function getValue($fieldName, $default = null) 
    {
        return ($this->toObject())->{$fieldName} ?? $default;
    }
}
