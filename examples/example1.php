<?php

error_reporting(E_ALL);
ini_set("display_errors", 1);
require __DIR__ .'/../vendor/autoload.php';

// consider a collection called "Users" with a partition key "country"

$conn = new \Jupitern\CosmosDb\CosmosDb('host', 'pk');
$db = $conn->selectDB('dbname');
$collection = $db->selectCollection('Users');


$users = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setDatabase($db)
    ->setCollection($collection)
    ->select("c.id, c.name, c.age")
    ->where("c.age = 30 and c.country = 'Portugal'")
    ->order("c.name asc")
    ->limit(10)
    ->findAll() // pass true to indicate if query is cross partition
    ->toObject();

var_dump($users);


$users = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setDatabase($db)
    ->setCollection($collection)
    ->select("Users.id, Users.name, Users.age")
    ->from("Users") // rename collection name from default "c" to "Users"
    ->where("Users.age = 30 and Users.country = 'Portugal'")
    ->order("Users.name asc")
    ->limit(10)
    ->findAll() // pass true to indicate if query is cross partition
    ->toObject();

var_dump($users);


echo "STEP: cross partition query".PHP_EOL;

$users = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setDatabase($db)
    ->setCollection($collection)
    ->select("c.id, c.name, c.age")        // always use "c" as collection name
    ->where("c.age = 30")
//    ->order("c.age asc")                  // not supported in cross partition query
//    ->limit(10)                           // not supported in cross partition query
    ->findAll(true) // pass true to indicate if query is cross partition
    ->toObject();

var_dump($users);
