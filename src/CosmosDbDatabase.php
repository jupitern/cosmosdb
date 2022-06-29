<?php

namespace Jupitern\CosmosDb;
use GuzzleHttp\Exception\GuzzleException;

class CosmosDbDatabase
{
    private CosmosDb $document_db;
    private string $rid_db;

    public function __construct(CosmosDb $document_db, string $rid_db)
    {
        $this->document_db = $document_db;
        $this->rid_db = $rid_db;
    }

    /**
     * selectCollection
     *
     * @access public
     * @param string $col_name Collection name
     * @throws GuzzleException
     */
    public function selectCollection(string $col_name): CosmosDbCollection|null
    {
        $rid_col = false;
        $object = json_decode($this->document_db->listCollections($this->rid_db));
        $col_list = $object->DocumentCollections;
        for ($i = 0; $i < count($col_list); $i++) {
            if ($col_list[$i]->id === $col_name) {
                $rid_col = $col_list[$i]->_rid;
            }
        }

        return $rid_col ? new CosmosDbCollection($this->document_db, $this->rid_db, $rid_col) : null;
    }


    /**
     * @param string $col_name
     * @param string|null $partitionKey
     * @return CosmosDbCollection|null
     * @throws GuzzleException
     */
    public function createCollection(string $col_name, string $partitionKey = null): CosmosDbCollection|null
    {
        $col_body = ["id" => $col_name];
        if ($partitionKey) {
            $col_body["partitionKey"] = [
                "paths" => [$partitionKey],
                "kind" => "Hash"
            ];
        }

        $object = json_decode($this->document_db->createCollection($this->rid_db, json_encode($col_body)));

        return $object->_rid ? new CosmosDbCollection($this->document_db, $this->rid_db, $object->_rid) : null;
    }

}
