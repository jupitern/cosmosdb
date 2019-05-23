# cosmosdb

PHP wrapper for Azure Cosmos DB

## Installation

Include jupitern/cosmosdb in your project, by adding it to your composer.json file.

```php
{
    "require": {
        "jupitern/cosmosdb": "2.*"
    }
}
```

## Changelog

### v2.1.0

- added support for nested partition keys

- pass a known partition value when querying as an alternative to cross-partition queries

### v2.0.0

- support for cross partition queries

- selectCollection method removed from all methods for performance improvements

### v1.4.4

- replaced pear package http_request2 by guzzle

- added method to provide guzzle configuration

### v1.3.0

- added support for parameterized queries

## Note

This package adds additional functionalities to the [AzureDocumentDB-PHP](https://github.com/cocteau666/AzureDocumentDB-PHP) package. All other functionality exists in this package as well.

## Limitations

Use of `limit()` or `order()` in cross-partition queries is currently not supported.

## Usage

### Connecting

```php
$conn = new \Jupitern\CosmosDb\CosmosDb('hostName', 'primaryKey');
$conn->setHttpClientOptions(['verify' => false]); # optional: set guzzle client options.
$db = $conn->selectDB('dbName');
$collection = $db->selectCollection('collectionName');

# if a collection does not exist, it will be created when you
# attempt to select the collection. however, if you have created
# your database with shared throughput, then all collections require a partition key.
# selectCollection() supports a second parameter for this purpose.
$conn = new \Jupitern\CosmosDb\CosmosDb('hostName', 'primaryKey');
$conn->setHttpClientOptions(['verify' => false]); # optional: set guzzle client options.
$db = $conn->selectDB('dbName');
$collection = $db->selectCollection('collectionName', 'myPartitionKey');
```

### Inserting Records

```php

# consider a existing collection called "Users" with a partition key "country"

# insert a record
$rid = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setCollection($collection)
    ->setPartitionKey('country')
    ->save(['id' => '1', 'name' => 'John Doe', 'age' => 22, 'country' => 'Portugal']);

# insert a record against a collection with a nested partition key
# note: this follows the same string format as is used when creating
# a collection with a partition key via the Azure Portal
$rid = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setCollection($collection)
    ->setPartitionKey('/form/person/country')
    ->save(['id' => '2', 'name' => 'Jane doe', 'age' => 35, 'country' => 'Portugal']);
```

### Updating Records

```php
# update a record
$rid = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setCollection($collection)
    ->setPartitionKey('country')
    ->save(["_rid" => $rid, 'id' => '2', 'name' => 'Jane Doe Something', 'age' => 36, 'country' => 'Portugal']);
```

### Querying Records

```php
# query a document and return it as an array
$res = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setCollection($collection)
    ->select("c.id, c.name")
    ->where("c.age > @age and c.country = @country")
    ->params(['@age' => 30, '@country' => 'Portugal'])
    ->find(true) # pass true if is cross partition query
    ->toArray();

# query a document using a known partition value,
# and return as an array. note: setting a known
# partition value will result in a more efficient
# query against your database as it will not rely
# on cross-partition querying
$res = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setCollection($collection)
    ->setPartitionValue('Portugal')
    ->select("c.id, c.name")
    ->where("c.age > @age and c.country = @country")
    ->params(['@age' => 30, '@country' => 'Portugal'])
    ->find()
    ->toArray();

# query the top 5 documents as an array, with the
# document ID as the array key.
# note: refer to limitations section
$res = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setCollection($collection)
    ->select("c.id, c.username")
    ->where("c.age > @age and c.country = @country")
    ->params(['@age' => 10, '@country' => 'Portugal'])
    ->limit(5)
    ->findAll() # cannot limit cross-partition queries
    ->toArray('id');

# query a document using a collection alias and cross partition query
$res = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setCollection($collection)
    ->select("TestColl.id, TestColl.name")
    ->from("TestColl")
    ->where("TestColl.age > 30")
    ->findAll(true) # pass true if is cross partition query
    ->toArray();
```

### Deleting Records

```php
# delete one document that matches criteria (single partition)
$res = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setCollection($collection)
    ->setPartitionKey('country')
    ->where("c.age > 30 and c.country = 'Portugal'")
    ->delete();

# delete all documents that match criteria (cross partition)
$res = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setCollection($collection)
    ->setPartitionKey('country')
    ->where("c.age > 20")
    ->deleteAll(true);
```
