# cosmosdb
PHP wrapper for Azure Cosmos DB

## Installation

Include jupitern/table in your project, by adding it to your composer.json file.
```php
{
    "require": {
        "jupitern/cosmosdb": "1.*"
    }
}
```

## Note

this package adds funccionalities to the package bellow so all funccionalities provided in base package are also available

https://github.com/cocteau666/AzureDocumentDB-PHP

## Usage

```php

$conn = app()->resolve('DocDB');

// insert a record
$rid = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setConnection($conn)
    ->collection("Users")
    ->save(['id' => '1', 'name' => 'John Doe', 'age' => 22]);

echo "record inserted: $rid";

// insert a record
$rid = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setConnection($conn)
    ->collection("Users")
    ->save(['id' => '2', 'name' => 'Jane doe', 'age' => 35]);

echo "record inserted: $rid";

// update a record
$res = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setConnection($conn)
    ->collection("Users")
    ->save(["_rid" => $rid, 'id' => '2', 'name' => 'Jane Doe Something', 'age' => 36]);

echo "record updated: $rid";

// get one row as array
$res = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setConnection($conn)
    ->collection("Users")
    ->select("Users.id, Users.name")
    ->where("Users.age > @age")
    ->params(['@age' => 30])
    ->find()
    ->toArray();

var_dump($res);

// get 5 rows as array
$res = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setConnection($conn)
    ->collection("Users")
    ->select("Users.id, Users.username")
    ->where("Users.age > 20")
    ->limit(5)
    ->findAll()
    ->toArray();

var_dump($res);

// delete one document that match criteria
$res = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setConnection($conn)
    ->collection("Users")
    ->where("Users.age > 30")
    ->delete();

var_dump($res);

// delete all documents that match criteria
$res = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setConnection($conn)
    ->collection("Users")
    ->where("Users.age > 20")
    ->deleteAll();

var_dump($res);

