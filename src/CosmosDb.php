<?php

namespace Jupitern\CosmosDb;

use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

class CosmosDb
{
    private string $host;
    private string $private_key;
    public array $httpClientOptions;

    /**
     * __construct
     *
     * @access public
     * @param string $host URI of hostname
     * @param string $private_key Primary (or Secondary key) private key
     */
    public function __construct(string $host, string $private_key)
    {
        $this->host = $host;
        $this->private_key = $private_key;
    }

    /**
     * set guzzle http client options using an associative array.
     *
     * @param array $options
     */
    public function setHttpClientOptions(array $options = [])
    {
        $this->httpClientOptions = $options;
    }

    /**
     * getAuthHeaders
     *
     * @link http://msdn.microsoft.com/en-us/library/azure/dn783368.aspx
     * @access private
     * @param string $verb Request Method (GET, POST, PUT, DELETE)
     * @param string $resource_type Resource Type
     * @param string $resource_id Resource ID
     * @return array of Request Headers
     */
    private function getAuthHeaders(string $verb, string $resource_type, string $resource_id): array
    {
        $x_ms_date = gmdate('D, d M Y H:i:s T', strtotime('+2 minutes'));
        $master = 'master';
        $token = '1.0';
        $x_ms_version = '2018-12-31';

        $key = base64_decode($this->private_key);
        $string_to_sign = $verb . "\n" .
            $resource_type . "\n" .
            $resource_id . "\n" .
            $x_ms_date . "\n" .
            "\n";

        $sig = base64_encode(hash_hmac('sha256', strtolower($string_to_sign), $key, true));

        return [
            'Accept' => 'application/json',
            'User-Agent' => 'documentdb.php.sdk/1.0.0',
            'Cache-Control' => 'no-cache',
            'x-ms-date' => $x_ms_date,
            'x-ms-version' => $x_ms_version,
            'authorization' => urlencode("type={$master}&ver={$token}&sig={$sig}")
        ];
    }

    /**
     * request
     *
     * use cURL functions
     *
     * @access private
     * @param string $path request path
     * @param string $method request method
     * @param array $headers request headers
     * @param string $body request body (JSON or QUERY)
     * @return ResponseInterface JSON response
     * @throws GuzzleException
     */
    private function request(string $path, string $method, array $headers, $body = NULL): ResponseInterface
    {
        $client = new \GuzzleHttp\Client();

        $options = [
            'headers' => $headers,
            'body' => $body,
        ];

        return $client->request($method, $this->host . $path, array_merge(
            $options,
            (array)$this->httpClientOptions
        ));
    }

    /**
     * selectDB
     *
     * @access public
     * @param string $db_name Database name
     * @return ?CosmosDbDatabase class
     * @throws GuzzleException
     */
    public function selectDB(string $db_name): ?CosmosDbDatabase
    {
        $rid_db = false;
        $object = json_decode($this->listDatabases());

        $db_list = $object->Databases;
        for ($i = 0; $i < count($db_list); $i++) {
            if ($db_list[$i]->id === $db_name) {
                $rid_db = $db_list[$i]->_rid;
            }
        }
        if (!$rid_db) {
            $object = json_decode($this->createDatabase('{"id":"' . $db_name . '"}'));
            $rid_db = $object->_rid;
        }

        return $rid_db ? new CosmosDbDatabase($this, $rid_db) : null;
    }

    /**
     * getInfo
     *
     * @access public
     * @return string JSON response
     * @throws GuzzleException
     */
    public function getInfo(): string
    {
        $headers = $this->getAuthHeaders('GET', '', '');
        $headers['Content-Length'] = '0';
        return $this->request("", "GET", $headers)->getBody()->getContents();
    }

    /**
     * query
     *
     * @link http://msdn.microsoft.com/en-us/library/azure/dn783363.aspx
     * @access public
     * @param string $rid_id Resource ID
     * @param string $rid_col Resource Collection ID
     * @param string $query Query
     * @param boolean $isCrossPartition used for cross partition query
     * @return array JSON response
     * @throws GuzzleException
     */
	public function query(string $rid_id, string $rid_col, string $query, bool $isCrossPartition = false, $partitionValue = null): array
	{
        $headers = $this->getAuthHeaders('POST', 'docs', $rid_col);
        $headers['Content-Length'] = strlen($query);
        $headers['Content-Type'] = 'application/query+json';
        $headers['x-ms-max-item-count'] = -1;
        $headers['x-ms-documentdb-isquery'] = 'True';
        
        if ($isCrossPartition) {
            $headers['x-ms-documentdb-query-enablecrosspartition'] = 'True';
        }
        
        if ($partitionValue) {
            $headers['x-ms-documentdb-partitionkey'] = '["'.$partitionValue.'"]';
        }
        /*
         * Fix for https://github.com/jupitern/cosmosdb/issues/21 (credits to https://github.com/ElvenSpellmaker).
         *
         * CosmosDB has a max packet size of 4MB and will automatically paginate after that, regardless of x-ms-max-items.
         * If this is the case, a 'x-ms-continuation'-header will be present in the response headers. The value of this
         * header will be a continuation token. If this header is detected, we can rerun our query with an additional
         * 'x-ms-continuation' request header, with the continuation token we received earlier as its value.
         *
         * This fix checks if this header is present on the response headers and handles the additional requests, untill
         * all results are loaded.
         */
        $results = [];
        try {
            $result = $this->request("/dbs/{$rid_id}/colls/{$rid_col}/docs", "POST", $headers, $query);
            $results[] = $result->getBody()->getContents();
            while ($result->getHeader('x-ms-continuation') !== []) {
                $headers['x-ms-continuation'] = $result->getHeader('x-ms-continuation');
                $result = $this->request("/dbs/{$rid_id}/colls/{$rid_col}/docs", "POST", $headers, $query);
                $results[] = $result->getBody()->getContents();
            }
        }
        catch (\GuzzleHttp\Exception\ClientException $e) {
            $responseError = \json_decode($e->getResponse()->getBody()->getContents());

            // -- Retry the request with PK Ranges --
            // The provided cross partition query can not be directly served by the gateway.
            // This is a first chance (internal) exception that all newer clients will know how to
            // handle gracefully. This exception is traced, but unless you see it bubble up as an
            // exception (which only happens on older SDK clients), then you can safely ignore this message.
            if ($isCrossPartition && $responseError->code === "BadRequest" && strpos($responseError->message, "cross partition query can not be directly served by the gateway") !== false) {
                $headers["x-ms-documentdb-partitionkeyrangeid"] = $this->getPkFullRange($rid_id, $rid_col);
                $result = $this->request("/dbs/{$rid_id}/colls/{$rid_col}/docs", "POST", $headers, $query);
                $results[] = $result->getBody()->getContents();
                while ($result->getHeader('x-ms-continuation') !== []) {
                    $headers['x-ms-continuation'] = $result->getHeader('x-ms-continuation');
                    $result = $this->request("/dbs/{$rid_id}/colls/{$rid_col}/docs", "POST", $headers, $query);
                    $results[] = $result->getBody()->getContents();
                }
            } else {
                throw $e;
            }
        }

        return $results;
    }

    /**
     * getPkRanges
     *
     * @param string $rid_id
     * @param string $rid_col
     * @return mixed
     * @throws GuzzleException
     */
	public function getPkRanges(string $rid_id, string $rid_col): mixed
    {
		$headers = $this->getAuthHeaders('GET', 'pkranges', $rid_col);
		$headers['Accept'] = 'application/json';
		$headers['x-ms-max-item-count'] = -1;
		$result = $this->request("/dbs/{$rid_id}/colls/{$rid_col}/pkranges", "GET", $headers);
		return json_decode($result->getBody()->getContents());
	}

    /**
     * getPkFullRange
     *
     * @param $rid_id
     * @param $rid_col
     *
     * @return string
     * @throws GuzzleException
     */
	public function getPkFullRange($rid_id, $rid_col): string
    {
		$result = $this->getPkRanges($rid_id, $rid_col);
		$ids = \array_column($result->PartitionKeyRanges, "id");
		return $result->_rid . "," . \implode(",", $ids);
	}

    /**
     * listDatabases
     *
     * @link http://msdn.microsoft.com/en-us/library/azure/dn803945.aspx
     * @access public
     * @return string JSON response
     * @throws GuzzleException
     */
    public function listDatabases(): string
    {
        $headers = $this->getAuthHeaders('GET', 'dbs', '');
        $headers['Content-Length'] = '0';
        return $this->request("/dbs", "GET", $headers)->getBody()->getContents();
    }

    /**
     * getDatabase
     *
     * @link http://msdn.microsoft.com/en-us/library/azure/dn803937.aspx
     * @access public
     * @param string $rid_id Resource ID
     * @return string JSON response
     * @throws GuzzleException
     */
    public function getDatabase(string $rid_id): string
    {
        $headers = $this->getAuthHeaders('GET', 'dbs', $rid_id);
        $headers['Content-Length'] = '0';
        return $this->request("/dbs/{$rid_id}", "GET", $headers)->getBody()->getContents();
    }

    /**
     * createDatabase
     *
     * @link http://msdn.microsoft.com/en-us/library/azure/dn803954.aspx
     * @access public
     * @param string $json JSON request
     * @return string JSON response
     * @throws GuzzleException
     */
    public function createDatabase(string $json): string
    {
        $headers = $this->getAuthHeaders('POST', 'dbs', '');
        $headers['Content-Length'] = strlen($json);
        return $this->request("/dbs", "POST", $headers, $json)->getBody()->getContents();
    }

    /**
     * replaceDatabase
     *
     * @link http://msdn.microsoft.com/en-us/library/azure/dn803943.aspx
     * @access public
     * @param string $rid_id Resource ID
     * @param string $json JSON request
     * @return string JSON response
     * @throws GuzzleException
     */
    public function replaceDatabase(string $rid_id, string $json): string
    {
        $headers = $this->getAuthHeaders('PUT', 'dbs', $rid_id);
        $headers['Content-Length'] = strlen($json);
        return $this->request("/dbs/{$rid_id}", "PUT", $headers, $json)->getBody()->getContents();
    }

    /**
     * deleteDatabase
     *
     * @link http://msdn.microsoft.com/en-us/library/azure/dn803942.aspx
     * @access public
     * @param string $rid_id Resource ID
     * @return string JSON response
     */
    public function deleteDatabase(string $rid_id): string
    {
        $headers = $this->getAuthHeaders('DELETE', 'dbs', $rid_id);
        $headers['Content-Length'] = '0';
        return $this->request("/dbs/{$rid_id}", "DELETE", $headers)->getBody()->getContents();
    }

    /**
     * listUsers
     *
     * @link http://msdn.microsoft.com/en-us/library/azure/dn803958.aspx
     * @access public
     * @param string $rid_id Resource ID
     * @return string JSON response
     * @throws GuzzleException
     */
    public function listUsers(string $rid_id): string
    {
        $headers = $this->getAuthHeaders('GET', 'users', $rid_id);
        $headers['Content-Length'] = '0';
        return $this->request("/dbs/{$rid_id}/users", "GET", $headers)->getBody()->getContents();
    }

    /**
     * getUser
     *
     * @link http://msdn.microsoft.com/en-us/library/azure/dn803949.aspx
     * @access public
     * @param string $rid_id Resource ID
     * @param string $rid_user Resource User ID
     * @return string JSON response
     * @throws GuzzleException
     */
    public function getUser(string $rid_id, string $rid_user): string
    {
        $headers = $this->getAuthHeaders('GET', 'users', $rid_user);
        $headers['Content-Length'] = '0';
        return $this->request("/dbs/{$rid_id}/users/{$rid_user}", "GET", $headers)->getBody()->getContents();
    }

    /**
     * createUser
     *
     * @link http://msdn.microsoft.com/en-us/library/azure/dn803946.aspx
     * @access public
     * @param string $rid_id Resource ID
     * @param string $json JSON request
     * @return string JSON response
     * @throws GuzzleException
     */
    public function createUser(string $rid_id, string $json): string
    {
        $headers = $this->getAuthHeaders('POST', 'users', $rid_id);
        $headers['Content-Length'] = strlen($json);
        return $this->request("/dbs/{$rid_id}/users", "POST", $headers, $json)->getBody()->getContents();
    }

    /**
     * replaceUser
     *
     * @link http://msdn.microsoft.com/en-us/library/azure/dn803941.aspx
     * @access public
     * @param string $rid_id Resource ID
     * @param string $rid_user Resource User ID
     * @param string $json JSON request
     * @return string JSON response
     * @throws GuzzleException
     */
    public function replaceUser(string $rid_id, string $rid_user, string $json): string
    {
        $headers = $this->getAuthHeaders('PUT', 'users', $rid_user);
        $headers['Content-Length'] = strlen($json);
        return $this->request("/dbs/{$rid_id}/users/{$rid_user}", "PUT", $headers, $json)->getBody()->getContents();
    }

    /**
     * deleteUser
     *
     * @link http://msdn.microsoft.com/en-us/library/azure/dn803953.aspx
     * @access public
     * @param string $rid_id Resource ID
     * @param string $rid_user Resource User ID
     * @return string JSON response
     * @throws GuzzleException
     */
    public function deleteUser(string $rid_id, string $rid_user): string
    {
        $headers = $this->getAuthHeaders('DELETE', 'users', $rid_user);
        $headers['Content-Length'] = '0';
        return $this->request("/dbs/{$rid_id}/users/{$rid_user}", "DELETE", $headers)->getBody()->getContents();
    }

    /**
     * listCollections
     *
     * @link http://msdn.microsoft.com/en-us/library/azure/dn803935.aspx
     * @access public
     * @param string $rid_id Resource ID
     * @return string JSON response
     * @throws GuzzleException
     */
    public function listCollections(string $rid_id): string
    {
        $headers = $this->getAuthHeaders('GET', 'colls', $rid_id);
        $headers['Content-Length'] = '0';
        return $this->request("/dbs/{$rid_id}/colls", "GET", $headers)->getBody()->getContents();
    }

    /**
     * getCollection
     *
     * @link http://msdn.microsoft.com/en-us/library/azure/dn803951.aspx
     * @access public
     * @param string $rid_id Resource ID
     * @param string $rid_col Resource Collection ID
     * @return string JSON response
     * @throws GuzzleException
     */
    public function getCollection(string $rid_id, string $rid_col): string
    {
        $headers = $this->getAuthHeaders('GET', 'colls', $rid_col);
        $headers['Content-Length'] = '0';
        return $this->request("/dbs/{$rid_id}/colls/{$rid_col}", "GET", $headers)->getBody()->getContents();
    }

    /**
     * createCollection
     *
     * @link http://msdn.microsoft.com/en-us/library/azure/dn803934.aspx
     * @access public
     * @param string $rid_id Resource ID
     * @param string $json JSON request
     * @return string JSON response
     * @throws GuzzleException
     */
    public function createCollection(string $rid_id, string $json): string
    {
        $headers = $this->getAuthHeaders('POST', 'colls', $rid_id);
        $headers['Content-Length'] = strlen($json);
        return $this->request("/dbs/{$rid_id}/colls", "POST", $headers, $json)->getBody()->getContents();
    }

    /**
     * deleteCollection
     *
     * @link http://msdn.microsoft.com/en-us/library/azure/dn803953.aspx
     * @access public
     * @param string $rid_id Resource ID
     * @param string $rid_col Resource Collection ID
     * @return string JSON response
     * @throws GuzzleException
     */
    public function deleteCollection(string $rid_id, string $rid_col): string
    {
        $headers = $this->getAuthHeaders('DELETE', 'colls', $rid_col);
        $headers['Content-Length'] = '0';
        return $this->request("/dbs/{$rid_id}/colls/{$rid_col}", "DELETE", $headers)->getBody()->getContents();
    }

    /**
     * listDocuments
     *
     * @link http://msdn.microsoft.com/en-us/library/azure/dn803955.aspx
     * @access public
     * @param string $rid_id Resource ID
     * @param string $rid_col
     * @return string JSON response
     * @throws GuzzleException
     */
    public function listDocuments(string $rid_id, string $rid_col): string
    {
        $headers = $this->getAuthHeaders('GET', 'docs', $rid_col);
        $headers['Content-Length'] = '0';
        return $this->request("/dbs/{$rid_id}/colls/{$rid_col}/docs", "GET", $headers)->getBody()->getContents();
    }

    /**
     * getDocument
     *
     * @link http://msdn.microsoft.com/en-us/library/azure/dn803957.aspx
     * @access public
     * @param string $rid_id Resource ID
     * @param string $rid_col Resource Collection ID
     * @param string $rid_doc Resource Doc ID
     * @return string JSON response
     * @throws GuzzleException
     */
    public function getDocument(string $rid_id, string $rid_col, string $rid_doc): string
    {
        $headers = $this->getAuthHeaders('GET', 'docs', $rid_doc);
        $headers['Content-Length'] = '0';
        $options = array(
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HTTPGET => true,
        );
        return $this->request("/dbs/{$rid_id}/colls/{$rid_col}/docs/{$rid_doc}", "GET", $headers)->getBody()->getContents();
    }

    /**
     * createDocument
     *
     * @link http://msdn.microsoft.com/en-us/library/azure/dn803948.aspx
     * @access public
     * @param string $rid_id Resource ID
     * @param string $rid_col Resource Collection ID
     * @param string $json JSON request
     * @param string|null $partitionKey
     * @param array $headers Optional headers to send along with the request
     * @return string JSON response
     * @throws GuzzleException
     */
    public function createDocument(string $rid_id, string $rid_col, string $json, string $partitionKey = null, array $headers = []): string
    {
        $authHeaders = $this->getAuthHeaders('POST', 'docs', $rid_col);
        $headers = \array_merge($headers, $authHeaders);
        $headers['Content-Length'] = strlen($json);
        if ($partitionKey !== null) {
            $headers['x-ms-documentdb-partitionkey'] = '["'.$partitionKey.'"]';
        }

        return $this->request("/dbs/{$rid_id}/colls/{$rid_col}/docs", "POST", $headers, $json)->getBody()->getContents();
    }

    /**
     * replaceDocument
     *
     * @link http://msdn.microsoft.com/en-us/library/azure/dn803947.aspx
     * @access public
     * @param string $rid_id Resource ID
     * @param string $rid_col Resource Collection ID
     * @param string $rid_doc Resource Doc ID
     * @param string $json JSON request
     * @param string|null $partitionKey
     * @param array $headers Optional headers to send along with the request
     * @return string JSON response
     * @throws GuzzleException
     */
    public function replaceDocument(string $rid_id, string $rid_col, string $rid_doc, string $json, string $partitionKey = null, array $headers = []): string
    {
        $authHeaders = $this->getAuthHeaders('PUT', 'docs', $rid_doc);
        $headers = \array_merge($headers, $authHeaders);
        $headers['Content-Length'] = strlen($json);
        if ($partitionKey !== null) {
            $headers['x-ms-documentdb-partitionkey'] = '["'.$partitionKey.'"]';
        }

        return $this->request("/dbs/{$rid_id}/colls/{$rid_col}/docs/{$rid_doc}", "PUT", $headers, $json)->getBody()->getContents();
    }

    /**
     * deleteDocument
     *
     * @link http://msdn.microsoft.com/en-us/library/azure/dn803952.aspx
     * @access public
     * @param string $rid_id Resource ID
     * @param string $rid_col Resource Collection ID
     * @param string $rid_doc Resource Doc ID
     * @param string|null $partitionKey
     * @param array $headers Optional headers to send along with the request
     * @return string JSON response
     * @throws GuzzleException
     */
    public function deleteDocument(string $rid_id, string $rid_col, string $rid_doc, string $partitionKey = null, array $headers = []): string
    {
        $authHeaders = $this->getAuthHeaders('DELETE', 'docs', $rid_doc);
        $headers = \array_merge($headers, $authHeaders);
        $headers['Content-Length'] = '0';
        if ($partitionKey !== null) {
            $headers['x-ms-documentdb-partitionkey'] = '["'.$partitionKey.'"]';
        }

        return $this->request("/dbs/{$rid_id}/colls/{$rid_col}/docs/{$rid_doc}", "DELETE", $headers)->getBody()->getContents();
    }

    /**
     * listAttachments
     *
     * @link http://
     * @access public
     * @param string $rid_id Resource ID
     * @param string $rid_col
     * @param string $rid_doc Resource Doc ID
     * @return string JSON response
     * @throws GuzzleException
     */
    public function listAttachments(string $rid_id, string $rid_col, string $rid_doc): string
    {
        $headers = $this->getAuthHeaders('GET', 'attachments', $rid_doc);
        $headers['Content-Length'] = '0';
        return $this->request("/dbs/{$rid_id}/colls/{$rid_col}/docs/{$rid_doc}/attachments", "GET", $headers)->getBody()->getContents();
    }

    /**
     * getAttachment
     *
     * @link http://
     * @access public
     * @param string $rid_id Resource ID
     * @param string $rid_col Resource Collection ID
     * @param string $rid_doc Resource Doc ID
     * @param string $rid_at Resource Attachment ID
     * @return string JSON response
     * @throws GuzzleException
     */
    public function getAttachment(string $rid_id, string $rid_col, string $rid_doc, string $rid_at): string
    {
        $headers = $this->getAuthHeaders('GET', 'attachments', $rid_at);
        $headers['Content-Length'] = '0';
        return $this->request("/dbs/{$rid_id}/colls/{$rid_col}/docs/{$rid_doc}/attachments/{$rid_at}", "GET", $headers)->getBody()->getContents();
    }

    /**
     * createAttachment
     *
     * @link http://msdn.microsoft.com/en-us/library/azure/dn803933.aspx
     * @access public
     * @param string $rid_id Resource ID
     * @param string $rid_col Resource Collection ID
     * @param string $rid_doc Resource Doc ID
     * @param string $content_type Content-Type of Media
     * @param string $filename Attachement file name
     * @param string $file URL encoded Attachement file (Raw Media)
     * @return string JSON response
     * @throws GuzzleException
     */
    public function createAttachment(string $rid_id, string $rid_col, string $rid_doc, string $content_type, string $filename, string $file): string
    {
        $headers = $this->getAuthHeaders('POST', 'attachments', $rid_doc);
        $headers['Content-Length'] = strlen($file);
        $headers['Content-Type'] = $content_type;
        $headers['Slug'] = urlencode($filename);
        return $this->request("/dbs/{$rid_id}/colls/{$rid_col}/docs/{$rid_doc}/attachments", "POST", $headers, $file)->getBody()->getContents();
    }

    /**
     * replaceAttachment
     *
     * @link http://
     * @access public
     * @param string $rid_id Resource ID
     * @param string $rid_col Resource Collection ID
     * @param string $rid_doc Resource Doc ID
     * @param string $rid_at Resource Attachment ID
     * @param string $content_type Content-Type of Media
     * @param string $filename Attachement file name
     * @param string $file URL encoded Attachement file (Raw Media)
     * @return string JSON response
     * @throws GuzzleException
     */
    public function replaceAttachment(string $rid_id, string $rid_col, string $rid_doc, string $rid_at, string $content_type, string $filename, string $file): string
    {
        $headers = $this->getAuthHeaders('PUT', 'attachments', $rid_at);
        $headers['Content-Length'] = strlen($file);
        $headers['Content-Type'] = $content_type;
        $headers['Slug'] = urlencode($filename);
        return $this->request("/dbs/{$rid_id}/colls/{$rid_col}/docs/{$rid_doc}/attachments/{$rid_at}", "PUT", $headers, $file)->getBody()->getContents();
    }

    /**
     * deleteAttachment
     *
     * @link http://
     * @access public
     * @param string $rid_id Resource ID
     * @param string $rid_col Resource Collection ID
     * @param string $rid_doc Resource Doc ID
     * @param string $rid_at Resource Attachment ID
     * @return string JSON response
     * @throws GuzzleException
     */
    public function deleteAttachment(string $rid_id, string $rid_col, string $rid_doc, string $rid_at): string
    {
        $headers = $this->getAuthHeaders('DELETE', 'attachments', $rid_at);
        $headers['Content-Length'] = '0';
        return $this->request("/dbs/{$rid_id}/colls/{$rid_col}/docs/{$rid_doc}/attachments/{$rid_at}", "DELETE", $headers)->getBody()->getContents();
    }

    /**
     * listOffers
     *
     * @link http://
     * @access public
     * @return string JSON response
     * @throws GuzzleException
     */
    public function listOffers(): string
    {
        $headers = $this->getAuthHeaders('GET', 'offers', '');
        $headers['Content-Length'] = '0';
        return $this->request("/offers", "GET", $headers)->getBody()->getContents();
    }

    /**
     * getOffer
     *
     * @link http://
     * @access public
     * @param string $rid Resource ID
     * @return string JSON response
     * @throws GuzzleException
     */
    public function getOffer(string $rid): string
    {
        $headers = $this->getAuthHeaders('GET', 'offers', $rid);
        $headers['Content-Length'] = '0';
        return $this->request("/offers/{$rid}", "GET", $headers)->getBody()->getContents();
    }

    /**
     * replaceOffer
     *
     * @link http://
     * @access public
     * @param string $rid Resource ID
     * @param string $json JSON request
     * @return string JSON response
     * @throws GuzzleException
     */
    public function replaceOffer(string $rid, string $json): string
    {
        $headers = $this->getAuthHeaders('PUT', 'offers', $rid);
        $headers['Content-Length'] = strlen($json);
        return $this->request("/offers/{$rid}", "PUT", $headers, $json)->getBody()->getContents();
    }

    /**
     * queryingOffers
     *
     * @link http://
     * @access public
     * @param string $json JSON request
     * @return string JSON response
     * @throws GuzzleException
     */
    public function queryingOffers(string $json): string
    {
        $headers = $this->getAuthHeaders('POST', 'offers', '');
        $headers['Content-Length'] = strlen($json);
        $headers['Content-Type'] = 'application/query+json';
        $headers['x-ms-documentdb-isquery'] = 'True';
        return $this->request("/offers", "POST", $headers, $json)->getBody()->getContents();
    }

    /**
     * listPermissions
     *
     * @link http://msdn.microsoft.com/en-us/library/azure/dn803949.aspx
     * @access public
     * @param string $rid_id Resource ID
     * @param string $rid_user Resource User ID
     * @return string JSON response
     * @throws GuzzleException
     */
    public function listPermissions(string $rid_id, string $rid_user): string
    {
        $headers = $this->getAuthHeaders('GET', 'permissions', $rid_user);
        $headers['Content-Length'] = '0';
        return $this->request("/dbs/{$rid_id}/users/{$rid_user}/permissions", "GET", $headers)->getBody()->getContents();
    }

    /**
     * createPermission
     *
     * @link http://msdn.microsoft.com/en-us/library/azure/dn803946.aspx
     * @access public
     * @param string $rid_id Resource ID
     * @param string $rid_user Resource User ID
     * @param string $json JSON request
     * @return string JSON response
     * @throws GuzzleException
     */
    public function createPermission(string $rid_id, string $rid_user, string $json): string
    {
        $headers = $this->getAuthHeaders('POST', 'permissions', $rid_user);
        $headers['Content-Length'] = strlen($json);
        return $this->request("/dbs/{$rid_id}/users/{$rid_user}/permissions", "POST", $headers, $json)->getBody()->getContents();
    }

    /**
     * getPermission
     *
     * @link http://msdn.microsoft.com/en-us/library/azure/dn803949.aspx
     * @access public
     * @param string $rid_id Resource ID
     * @param string $rid_user Resource User ID
     * @param string $rid_permission Resource Permission ID
     * @return string JSON response
     * @throws GuzzleException
     */
    public function getPermission(string $rid_id, string $rid_user, string $rid_permission): string
    {
        $headers = $this->getAuthHeaders('GET', 'permissions', $rid_permission);
        $headers['Content-Length'] = '0';
        return $this->request("/dbs/{$rid_id}/users/{$rid_user}/permissions/{$rid_permission}", "GET", $headers)->getBody()->getContents();
    }

    /**
     * replacePermission
     *
     * @link http://msdn.microsoft.com/en-us/library/azure/dn803949.aspx
     * @access public
     * @param string $rid_id Resource ID
     * @param string $rid_user Resource User ID
     * @param string $rid_permission Resource Permission ID
     * @param string $json JSON request
     * @return string JSON response
     * @throws GuzzleException
     */
    public function replacePermission(string $rid_id, string $rid_user, string $rid_permission, string $json): string
    {
        $headers = $this->getAuthHeaders('PUT', 'permissions', $rid_permission);
        $headers['Content-Length'] = strlen($json);
        return $this->request("/dbs/{$rid_id}/users/{$rid_user}/permissions/{$rid_permission}", "PUT", $headers, $json)->getBody()->getContents();
    }

    /**
     * deletePermission
     *
     * @link http://msdn.microsoft.com/en-us/library/azure/dn803949.aspx
     * @access public
     * @param string $rid_id Resource ID
     * @param string $rid_user Resource User ID
     * @param string $rid_permission Resource Permission ID
     * @return string JSON response
     * @throws GuzzleException
     */
    public function deletePermission(string $rid_id, string $rid_user, string $rid_permission): string
    {
        $headers = $this->getAuthHeaders('DELETE', 'permissions', $rid_permission);
        $headers['Content-Length'] = '0';
        return $this->request("/dbs/{$rid_id}/users/{$rid_user}/permissions/{$rid_permission}", "DELETE", $headers)->getBody()->getContents();
    }

    /**
     * listStoredProcedures
     *
     * @link http://
     * @access public
     * @param string $rid_id Resource ID
     * @param string $rid_col
     * @return string JSON response
     * @throws GuzzleException
     */
    public function listStoredProcedures(string $rid_id, string $rid_col): string
    {
        $headers = $this->getAuthHeaders('GET', 'sprocs', $rid_col);
        $headers['Content-Length'] = '0';
        return $this->request("/dbs/{$rid_id}/colls/{$rid_col}/sprocs", "GET", $headers)->getBody()->getContents();
    }

    /**
     * executeStoredProcedure
     *
     * @link http://
     * @access public
     * @param string $rid_id Resource ID
     * @param string $rid_col Resource Collection ID
     * @param string $rid_sproc Resource ID of Stored Procedurea
     * @param string $json Parameters
     * @return string JSON response
     * @throws GuzzleException
     */
    public function executeStoredProcedure(string $rid_id, string $rid_col, string $rid_sproc, string $json): string
    {
        $headers = $this->getAuthHeaders('POST', 'sprocs', $rid_sproc);
        $headers['Content-Length'] = strlen($json);
        return $this->request("/dbs/{$rid_id}/colls/{$rid_col}/sprocs/{$rid_sproc}", "POST", $headers, $json)->getBody()->getContents();
    }

    /**
     * createStoredProcedure
     *
     * @link http://msdn.microsoft.com/en-us/library/azure/dn803933.aspx
     * @access public
     * @param string $rid_id Resource ID
     * @param string $rid_col Resource Collection ID
     * @param string $json JSON of function
     * @return string JSON response
     * @throws GuzzleException
     */
    public function createStoredProcedure(string $rid_id, string $rid_col, string $json): string
    {
        $headers = $this->getAuthHeaders('POST', 'sprocs', $rid_col);
        $headers['Content-Length'] = strlen($json);
        return $this->request("/dbs/{$rid_id}/colls/{$rid_col}/sprocs", "POST", $headers, $json)->getBody()->getContents();
    }

    /**
     * replaceStoredProcedure
     *
     * @link http://
     * @access public
     * @param string $rid_id Resource ID
     * @param string $rid_col Resource Collection ID
     * @param string $rid_sproc Resource ID of Stored Procedurea
     * @param string $json Parameters
     * @return string JSON response
     * @throws GuzzleException
     */
    public function replaceStoredProcedure(string $rid_id, string $rid_col, string $rid_sproc, string $json): string
    {
        $headers = $this->getAuthHeaders('PUT', 'sprocs', $rid_sproc);
        $headers['Content-Length'] = strlen($json);
        return $this->request("/dbs/{$rid_id}/colls/{$rid_col}/sprocs/{$rid_sproc}", "PUT", $headers, $json)->getBody()->getContents();
    }

    /**
     * deleteStoredProcedure (MethodNotAllowed: MUST FIX)
     *
     * @link http://
     * @access public
     * @param string $rid_id Resource ID
     * @param string $rid_col Resource Collection ID
     * @param string $rid_sproc Resource ID of Stored Procedurea
     * @return string JSON response
     * @throws GuzzleException
     */
    public function deleteStoredProcedure(string $rid_id, string $rid_col, string $rid_sproc): string
    {
        $headers = $this->getAuthHeaders('DELETE', 'sprocs', $rid_sproc);
        $headers['Content-Length'] = '0';
        return $this->request("/dbs/{$rid_id}/colls/{$rid_col}/sprocs/{$rid_sproc}", "DELETE", $headers)->getBody()->getContents();
    }

    /**
     * listUserDefinedFunctions
     *
     * @link http://
     * @access public
     * @param string $rid_id Resource ID
     * @param string $rid_col
     * @return string JSON response
     * @throws GuzzleException
     */
    public function listUserDefinedFunctions(string $rid_id, string $rid_col): string
    {
        $headers = $this->getAuthHeaders('GET', 'udfs', $rid_col);
        $headers['Content-Length'] = '0';
        return $this->request("/dbs/{$rid_id}/colls/{$rid_col}/udfs", "GET", $headers)->getBody()->getContents();
    }

    /**
     * createUserDefinedFunction
     *
     * @link http://msdn.microsoft.com/en-us/library/azure/dn803933.aspx
     * @access public
     * @param string $rid_id Resource ID
     * @param string $rid_col Resource Collection ID
     * @param string $json JSON of function
     * @return string JSON response
     * @throws GuzzleException
     */
    public function createUserDefinedFunction(string $rid_id, string $rid_col, string $json): string
    {
        $headers = $this->getAuthHeaders('POST', 'udfs', $rid_col);
        $headers['Content-Length'] = strlen($json);
        return $this->request("/dbs/{$rid_id}/colls/{$rid_col}/udfs", "POST", $headers, $json)->getBody()->getContents();
    }

    /**
     * replaceUserDefinedFunction
     *
     * @link http://
     * @access public
     * @param string $rid_id Resource ID
     * @param string $rid_col Resource Collection ID
     * @param string $rid_udf Resource ID of User Defined Function
     * @param string $json Parameters
     * @return string JSON response
     * @throws GuzzleException
     */
    public function replaceUserDefinedFunction(string $rid_id, string $rid_col, string $rid_udf, string $json): string
    {
        $headers = $this->getAuthHeaders('PUT', 'udfs', $rid_udf);
        $headers['Content-Length'] = strlen($json);
        return $this->request("/dbs/{$rid_id}/colls/{$rid_col}/udfs/{$rid_udf}", "PUT", $headers, $json)->getBody()->getContents();
    }

    /**
     * deleteUserDefinedFunction
     *
     * @link http://
     * @access public
     * @param string $rid_id Resource ID
     * @param string $rid_col Resource Collection ID
     * @param string $rid_udf Resource ID of User Defined Function
     * @return string JSON response
     * @throws GuzzleException
     */
    public function deleteUserDefinedFunction(string $rid_id, string $rid_col, string $rid_udf): string
    {
        $headers = $this->getAuthHeaders('DELETE', 'udfs', $rid_udf);
        $headers['Content-Length'] = '0';
        return $this->request("/dbs/{$rid_id}/colls/{$rid_col}/udfs/{$rid_udf}", "DELETE", $headers)->getBody()->getContents();
    }

    /**
     * listTriggers
     *
     * @link http://
     * @access public
     * @param string $rid_id Resource ID
     * @param string $rid_col
     * @return string JSON response
     * @throws GuzzleException
     */
    public function listTriggers(string $rid_id, string $rid_col): string
    {
        $headers = $this->getAuthHeaders('GET', 'triggers', $rid_col);
        $headers['Content-Length'] = '0';
        return $this->request("/dbs/{$rid_id}/colls/{$rid_col}/triggers", "GET", $headers)->getBody()->getContents();
    }

    /**
     * createTrigger
     *
     * @link http://msdn.microsoft.com/en-us/library/azure/dn803933.aspx
     * @access public
     * @param string $rid_id Resource ID
     * @param string $rid_col Resource Collection ID
     * @param string $json JSON of function
     * @return string JSON response
     * @throws GuzzleException
     */
    public function createTrigger(string $rid_id, string $rid_col, string $json): string
    {
        $headers = $this->getAuthHeaders('POST', 'triggers', $rid_col);
        $headers['Content-Length'] = strlen($json);
        return $this->request("/dbs/{$rid_id}/colls/{$rid_col}/triggers", "POST", $headers, $json)->getBody()->getContents();
    }

    /**
     * replaceTrigger
     *
     * @link http://
     * @access public
     * @param string $rid_id Resource ID
     * @param string $rid_col Resource Collection ID
     * @param string $rid_trigger Resource ID of Trigger
     * @param string $json Parameters
     * @return string JSON response
     * @throws GuzzleException
     */
    public function replaceTrigger(string $rid_id, string $rid_col, string $rid_trigger, string $json): string
    {
        $headers = $this->getAuthHeaders('PUT', 'triggers', $rid_trigger);
        $headers['Content-Length'] = strlen($json);
        return $this->request("/dbs/{$rid_id}/colls/{$rid_col}/triggers/{$rid_trigger}", "PUT", $headers, $json)->getBody()->getContents();
    }

    /**
     * deleteTrigger
     *
     * @link http://
     * @access public
     * @param string $rid_id Resource ID
     * @param string $rid_col Resource Collection ID
     * @param string $rid_trigger Resource ID of Trigger
     * @return string JSON response
     * @throws GuzzleException
     */
    public function deleteTrigger(string $rid_id, string $rid_col, string $rid_trigger): string
    {
        $headers = $this->getAuthHeaders('DELETE', 'triggers', $rid_trigger);
        $headers['Content-Length'] = '0';
        return $this->request("/dbs/{$rid_id}/colls/{$rid_col}/triggers/{$rid_trigger}", "DELETE", $headers)->getBody()->getContents();
    }

}
