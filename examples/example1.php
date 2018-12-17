<?php

error_reporting(E_ALL);
ini_set("display_errors", 1);
require __DIR__ .'/../vendor/autoload.php';

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
    ->select("Users.id, Users.name")
    ->from("Users")
    ->where("Users.age > 30")
    ->findAll(true) // pass true if is cross partition query
    ->toArray();

var_dump($res);

echo "delete one document:".PHP_EOL;

// delete one document that match criteria
$res = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setDatabase($db)
    ->setCollection($collection)
    ->setPartitionKey('country')
    ->where("c.age > 30 and c.country = 'Portugal'")
    ->delete();

var_dump($res);

echo "delete all documents:".PHP_EOL;

// delete all documents that match criteria
$res = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setDatabase($db)
    ->setPartitionKey('country')
    ->setCollection($collection)
    ->where("c.age > 20")
    ->deleteAll(true);

var_dump($res);
