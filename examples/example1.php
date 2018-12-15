<?php

error_reporting(E_ALL);
ini_set("display_errors", 1);
require __DIR__ .'/../vendor/autoload.php';


$conn = new \Jupitern\CosmosDb\CosmosDb('host', 'pk');
$db = $conn->selectDB('yeapp-staging');
$collection = $db->selectCollection('Users');


$rid = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setDatabase($db)
    ->setCollection($collection)
    ->setPartitionKey('collectiontype')
    ->save(['id' => 'user1', 'name' => 'john doe', 'age' => 30, 'collectiontype' => 'user']);

echo ($rid != null ? "user saved" : "user not saved").PHP_EOL;

echo "STEP: single partition query".PHP_EOL;

// select users with age 30 with single partition query
$users = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setDatabase($db)
    ->setCollection($collection)
    ->select("c.id, c.name, c.age")        // always use "c" as collection name
    ->where("c.age = 30 and c.collectiontype = 'user'")
    ->order("c.name asc")
    ->limit(10)
    ->findAll() // pass boolean to indicate if query is cross partition
    ->toObject();

foreach ($users as $user) {
    var_dump($user);
}

echo "STEP: multi partition query".PHP_EOL;

// select users with age 30 with multi partition query
$users = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setDatabase($db)
    ->setCollection($collection)
    ->select("c.id, c.name, c.age")        // always use "c" as collection name
    ->where("c.age = 30")
    ->order("c.age asc")
    ->limit(10)
    ->findAll(true) // pass boolean to indicate if query is cross partition
    ->toObject();

foreach ($users as $user) {
    var_dump($user);
}

// delete a user
\Jupitern\CosmosDb\QueryBuilder::instance()
    ->setCollection($collection)
    ->setDatabase($db)
    ->setPartitionKey('collectiontype')
    ->where("c.id = 'user123' and c.collectiontype = 'user'")
    ->delete();

// delete all users aged 30
\Jupitern\CosmosDb\QueryBuilder::instance()
    ->setCollection($collection)
    ->setDatabase($db)
    ->setPartitionKey('collectiontype')
    ->where("c.age = 30 and c.collectiontype = 'user'")
    ->deleteAll();

echo "finished";