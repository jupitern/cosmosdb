<?php

error_reporting(E_ALL);
ini_set("display_errors", 1);
require __DIR__ .'/../vendor/autoload.php';

use \Jupitern\CosmosDb\CosmosDb;
use \Jupitern\CosmosDb\QueryBuilder;

try {
    $conn = new CosmosDb('https://localhost:8081', 'C2y6yDjf5/R+ob0N8A7Cgv30VRDJIWEHLM+4QDU5DE2nQ9nDuVTqobD4b8mGGyPMbIZnqyMsEcaGQy67XIw/Jw==');
    $conn->setHttpClientOptions(['verify' => false]); // optional: set guzzle client options.
    $db = $conn->selectDB('testdb');
    $collection = $db->selectCollection('Users');

    if ($collection == null) {
        $collection = $db->createCollection('Users', '/country');
    }

    echo "delete all documents" . PHP_EOL;

    $res = QueryBuilder::instance()
        ->setCollection($collection)
        ->deleteAll(true);

    die();

    $rid = null;
    for ($i = 1; $i <= 30; ++$i) {
        $rid = QueryBuilder::instance()
            ->setCollection($collection)
            ->setPartitionKey('country')
            ->save(['id' => (string)$i, 'name' => 'Person #' . $i, 'age' => rand(10, 40), 'country' => 'Portugal']);

        echo "{$i} => record inserted: $rid" . PHP_EOL;
    }

    // update a record
    $rid = QueryBuilder::instance()
        ->setCollection($collection)
        ->setPartitionKey('country')
        ->save(["_rid" => $rid, 'id' => '30', 'name' => 'Jane Doe Something', 'age' => 36, 'country' => 'Portugal']);

    echo "record updated: $rid" . PHP_EOL;

    echo "get one row as array:" . PHP_EOL;

    // get one row as array
    $res = QueryBuilder::instance()
        ->setCollection($collection)
        ->select("c.id, c.name")
        ->where("c.age > @age and c.country = @country")
        ->params(['@age' => 30, '@country' => 'Portugal'])
        ->find(false) // pass true if is cross partition query
        ->toArray();

    var_dump($res);

    echo "get 5 rows as array with id as array key:" . PHP_EOL;

    // get top 5 rows as array with id as array key
    $res = QueryBuilder::instance()
        ->setCollection($collection)
        ->select("c.id, c.name")
        ->where("c.age > @age and c.country = @country")
        ->params(['@age' => 10, '@country' => 'Portugal'])
        ->limit(5)
        ->findAll() // pass true if is cross partition query
        ->toArray('id');

    var_dump($res);

    echo "get age > 30 rows as array of objects with collection alias and cross partition query:" . PHP_EOL;

    // get rows as array of objects with collection alias and cross partition query
    $res = QueryBuilder::instance()
        ->setCollection($collection)
        ->select("Users.id, Users.name")
        ->from("Users")
        ->where("Users.age > 30")
        ->findAll(true) // pass true if is cross partition query
        ->toObject();

    var_dump($res);

    echo "delete one document:" . PHP_EOL;

    // delete one document that match criteria
    $res = QueryBuilder::instance()
        ->setCollection($collection)
        ->setPartitionKey('country')
        ->where("c.age > @age and c.country = @country")
        ->params(['@age' => 30, '@country' => 'Portugal'])
        ->delete();

    var_dump($res);

    echo "delete all documents age > 20:" . PHP_EOL;

    // delete all documents that match criteria
    $res = QueryBuilder::instance()
        ->setPartitionKey('country')
        ->setCollection($collection)
        ->where("c.age > @age")
        ->params(['@age' => 20])
        ->deleteAll(true);

    var_dump($res);

} catch (\GuzzleHttp\Exception\ClientException $e) {
    $response = json_decode($e->getResponse()->getBody());
    echo "ERROR: ".$response->code ." => ". $response->message .PHP_EOL.PHP_EOL;

    echo $e->getTraceAsString();
}
