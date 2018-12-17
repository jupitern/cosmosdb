<?php

error_reporting(E_ALL);
ini_set("display_errors", 1);
require __DIR__ .'/../vendor/autoload.php';


$conn = new \Jupitern\CosmosDb\CosmosDb(
    'https://yeapp-cosmosdb.documents.azure.com:443/',
    'o74jDAirTdPTvmE7ID0Wq7OrNLDCSwvj6FqHgvsuugqapRKhVvfWxpUUIgK2gh7eCx8BAgTgo3M5tuSf26BX9A=='
);
$db = $conn->selectDB('yeapp-staging');
$collection = $db->selectCollection('Users');

$users = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setDatabase($db)
    ->setCollection($collection)
    ->select("c.id, c.name, c.age")        // always use "c" as collection name
    ->where("c.age = 30 and c.collectiontype = 'user'")
    ->order("c.name asc")
    ->limit(10)
    ->findAll() // pass boolean to indicate if query is cross partition
    ->toObject();

var_dump($users);


$users = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setDatabase($db)
    ->setCollection($collection)
    ->select("Users.id, Users.name, Users.age")
    ->from("Users")
    ->where("Users.age = 30 and Users.collectiontype = 'user'")
    ->order("Users.name asc")
    ->limit(10)
    ->findAll() // pass boolean to indicate if query is cross partition
    ->toObject();

var_dump($users);


echo "STEP: multi partition query".PHP_EOL;

$users = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setDatabase($db)
    ->setCollection($collection)
    ->select("c.id, c.name, c.age")        // always use "c" as collection name
    ->where("c.age = 30")
//    ->order("c.age asc")
//    ->limit(10)
    ->findAll(true) // pass boolean to indicate if query is cross partition
    ->toObject();

var_dump($users);
