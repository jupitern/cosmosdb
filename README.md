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

## Usage
```php

// make connection
$docdb = new AzureCosmosDb($uri, $key);
$conn = $docdb->selectDB($db);

// get one row as array
$res = \Jupitern\CosmosDb\QueryBuilder::instance()
  ->setConnection($conn)
  ->collection("Users")
  ->select("Users.id, Users.username")
  ->where("Users.id = '4uc234ocu23h4o'")
  ->find()
  ->toArray();

var_dump($res);

// get 5 rows as array
$res = \Jupitern\CosmosDb\QueryBuilder::instance()
  ->setConnection($conn)
  ->collection("Users")
  ->select("Users.id, Users.username")
  ->where("Users.age > 18")
  ->limit(5)
  ->findAll()
  ->toArray();

var_dump($res);

// delete one document
$res = \Jupitern\CosmosDb\QueryBuilder::instance()
  ->setConnection($conn)
  ->collection("Users")
  ->where("Users.age > 18")
  ->delete();

var_dump($res);

// delete all documents
$res = \Jupitern\CosmosDb\QueryBuilder::instance()
  ->setConnection($conn)
  ->collection("Users")
  ->where("Users.age > 18")
  ->deleteAll();

var_dump($res);
