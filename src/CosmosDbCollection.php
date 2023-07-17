<?php

namespace Jupitern\CosmosDb;

class CosmosDbCollection
{
    private CosmosDb $document_db;
    private string $rid_db;
    private string $rid_col;

    /**
     * __construct
     *
     * @access public
     * @param CosmosDb $document_db CosmosDb object
     * @param string $rid_db Database ID
     * @param string $rid_col Collection ID
     */
    public function __construct(CosmosDb $document_db, string $rid_db, string $rid_col)
    {
        $this->document_db = $document_db;
        $this->rid_db = $rid_db;
        $this->rid_col = $rid_col;
    }

    /**
     * query
     * @access public
     * @param string $query Query
     * @param array $params
     * @param boolean $isCrossPartition used for cross partition query
     * @return array JSON strings
     */
    public function query($query, $params = [], $isCrossPartition = false, $partitionValue = null)
    {
        $paramsJson = [];
        foreach ($params as $key => $val) {
            $val = is_int($val) || is_float($val) ? $val : '"' . str_replace('"', '\\"', $val) . '"';

            $paramsJson[] = '{"name": "' . str_replace('"', '\\"', $key) . '", "value": ' . $val . '}';
        }

        $query = '{"query": "' . str_replace('"', '\\"', $query) . '", "parameters": [' . implode(',', $paramsJson) . ']}';

        return $this->document_db->query($this->rid_db, $this->rid_col, $query, $isCrossPartition, $partitionValue);
    }

    /**
     * getPkRanges
     *
     * @return mixed
     */
    public function getPkRanges()
    {
        return $this->document_db->getPkRanges($this->rid_db, $this->rid_col);
    }

    /**
     * getPkFullRange
     *
     * @return mixed
     */
    public function getPkFullRange()
    {
        return $this->document_db->getPkFullRange($this->rid_db, $this->rid_col);
    }

    /**
     * createDocument
     *
     * @access public
     * @param string $json JSON formatted document
     * @param string $partitionKey
     * @param array $headers Optional headers to send along with the request
     * @return string JSON strings
     */
    public function createDocument($json, $partitionKey = null, array $headers = [])
    {
        return $this->document_db->createDocument($this->rid_db, $this->rid_col, $json, $partitionKey, $headers);
    }

    /**
     * replaceDocument
     *
     * @access public
     * @param  string $rid document ResourceID (_rid)
     * @param string $json JSON formatted document
     * @param string $partitionKey
     * @param array $headers Optional headers to send along with the request
     * @return string JSON strings
     */
    public function replaceDocument($rid, $json, $partitionKey = null, array $headers = [])
    {
        return $this->document_db->replaceDocument($this->rid_db, $this->rid_col, $rid, $json, $partitionKey, $headers);
    }

    /**
     * patchDocument
     *
     * @access public
     * @param  string $rid document ResourceID (_rid)
     * @param string $json JSON formatted operations array
     * @param string $partitionKey
     * @param array $headers Optional headers to send along with the request
     * @return string JSON strings
     */
    public function patchDocument($rid_doc, $operations, $partitionKey = null, array $headers = [])
    {
        return $this->document_db->patchDocument($this->rid_db, $this->rid_col, $rid_doc, $operations, $partitionKey, $headers);
    }

    /**
     * deleteDocument
     *
     * @access public
     * @param  string $rid document ResourceID (_rid)
     * @param string $partitionKey
     * @param array $headers Optional headers to send along with the request
     * @return string JSON strings
     */
    public function deleteDocument($rid, $partitionKey = null, array $headers = [])
    {
        return $this->document_db->deleteDocument($this->rid_db, $this->rid_col, $rid, $partitionKey, $headers);
    }

    /*
      public function createUser($json)
      {
        return $this->document_db->createUser($this->rid_db, $json);
      }

      public function listUsers()
      {
        return $this->document_db->listUsers($this->rid_db, $rid);
      }

      public function deletePermission($uid, $pid)
      {
        return $this->document_db->deletePermission($this->rid_db, $uid, $pid);
      }

      public function listPermissions($uid)
      {
        return $this->document_db->listPermissions($this->rid_db, $uid);
      }

      public function getPermission($uid, $pid)
      {
        return $this->document_db->getPermission($this->rid_db, $uid, $pid);
      }
    */

    public function listStoredProcedures()
    {
        return $this->document_db->listStoredProcedures($this->rid_db, $this->rid_col);
    }

    public function executeStoredProcedure($sproc_name, $json)
    {
        return $this->document_db->executeStoredProcedure($this->rid_db, $this->rid_col, $sproc_name, $json);
    }

    public function createStoredProcedure($json)
    {
        return $this->document_db->createStoredProcedure($this->rid_db, $this->rid_col, $json);
    }

    public function replaceStoredProcedure($sproc_name, $json)
    {
        return $this->document_db->replaceStoredProcedure($this->rid_db, $this->rid_col, $sproc_name, $json);
    }

    public function deleteStoredProcedure($sproc_name)
    {
        return $this->document_db->deleteStoredProcedure($this->rid_db, $this->rid_col, $sproc_name);
    }

    public function listUserDefinedFunctions()
    {
        return $this->document_db->listUserDefinedFunctions($this->rid_db, $this->rid_col);
    }

    public function createUserDefinedFunction($json)
    {
        return $this->document_db->createUserDefinedFunction($this->rid_db, $this->rid_col, $json);
    }

    public function replaceUserDefinedFunction($udf, $json)
    {
        return $this->document_db->replaceUserDefinedFunction($this->rid_db, $this->rid_col, $udf, $json);
    }

    public function deleteUserDefinedFunction($udf)
    {
        return $this->document_db->deleteUserDefinedFunction($this->rid_db, $this->rid_col, $udf);
    }

    public function listTriggers()
    {
        return $this->document_db->listTriggers($this->rid_db, $this->rid_col);
    }

    public function createTrigger($json)
    {
        return $this->document_db->createTrigger($this->rid_db, $this->rid_col, $json);
    }

    public function replaceTrigger($trigger, $json)
    {
        return $this->document_db->replaceTrigger($this->rid_db, $this->rid_col, $trigger, $json);
    }

    public function deleteTrigger($trigger)
    {
        return $this->document_db->deleteTrigger($this->rid_db, $this->rid_col, $trigger);
    }

}
