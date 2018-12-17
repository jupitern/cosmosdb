# cosmosdb
PHP wrapper for Azure Cosmos DB

## Installation

Include jupitern/table in your project, by adding it to your composer.json file.
```php
{
    "require": {
        "jupitern/cosmosdb": "2.*"
    }
}
```

## Changelog

### v2.0.0
support for cross partition queries

selectCollection method removed from all methods for performance improvements

### v1.4.4
replaced pear package http_request2 by guzzle

added method to provide guzzle configuration

### v1.3.0
added support for parameterized queries


## Note

this package adds functionalities to the package bellow so all functionalities provided in base package are also available

https://github.com/cocteau666/AzureDocumentDB-PHP

## Limitations

in cross partition queries order by or top are not supported at this moment


## Usage

```php

// consider a existing collection called "Users" with a partition key "country"

$conn = new \Jupitern\CosmosDb\CosmosDb('host', 'pk');
$conn->setHttpClientOptions(['verify' => false]); // optional: set guzzle client options.
$db = $conn->selectDB('dbName');
$collection = $db->selectCollection('collectionName');

// insert a record
$rid = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setDatabase($db)
    ->setCollection($collection)
    ->setPartitionKey('country')
    ->save(['id' => '1', 'name' => 'John Doe', 'age' => 22, 'country' => 'Portugal']);

echo "record inserted: $rid".PHP_EOL;

// insert a record
$rid = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setDatabase($db)
    ->setCollection($collection)
    ->setPartitionKey('country')
    ->save(['id' => '2', 'name' => 'Jane doe', 'age' => 35, 'country' => 'Portugal']);

echo "record inserted: $rid".PHP_EOL;

// update a record
$rid = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setDatabase($db)
    ->setCollection($collection)
    ->setPartitionKey('country')
    ->save(["_rid" => $rid, 'id' => '2', 'name' => 'Jane Doe Something', 'age' => 36, 'country' => 'Portugal']);

echo "record updated: $rid".PHP_EOL;

echo "get one row as array:".PHP_EOL;

// get one row as array
$res = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setDatabase($db)
    ->setCollection($collection)
    ->select("c.id, c.name")
    ->where("c.age > @age and c.country = @country")
    ->params(['@age' => 30, '@country' => 'Portugal'])
    ->find(true) // pass true if is cross partition query
    ->toArray();

var_dump($res);

echo "get 5 rows as array with id as array key:".PHP_EOL;

// get top 5 rows as array with id as array key
$res = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setDatabase($db)
    ->setCollection($collection)
    ->select("c.id, c.username")
    ->where("c.age > @age and c.country = @country")
    ->params(['@age' => 10, '@country' => 'Portugal'])
    ->limit(5)
    ->findAll() // pass true if is cross partition query
    ->toArray('id');

var_dump($res);

echo "get rows as array of objects with collection alias and cross partition query:".PHP_EOL;

// get rows as array of objects with collection alias and cross partition query
$res = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setDatabase($db)
    ->setCollection($collection)
    ->select("TestColl.id, TestColl.name")
    ->from("TestColl")
    ->where("TestColl.age > 30")
    ->findAll(true) // pass true if is cross partition query
    ->toArray();

var_dump($res);

echo "delete one document:".PHP_EOL;

// delete one document that match criteria (single partition)
$res = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setDatabase($db)
    ->setCollection($collection)
    ->setPartitionKey('country')
    ->where("c.age > 30 and c.country = 'Portugal'")
    ->delete();

var_dump($res);

echo "delete all documents:".PHP_EOL;

// delete all documents that match criteria (cross partition)
$res = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setDatabase($db)
    ->setPartitionKey('country')
    ->setCollection($collection)
    ->where("c.age > 20")
    ->deleteAll(true);

var_dump($res);

```