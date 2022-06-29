# cosmosdb

PHP wrapper for Azure Cosmos DB

https://docs.microsoft.com/pt-pt/rest/api/cosmos-db/common-tasks-using-the-cosmosdb-rest-api

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

### v2.6.0
- code refactor. min PHP verion supported is now 8.0
- selectCollection no longer creates a colletion if not exist. use createCollection for that
- bug fixes

### v2.5.0
- support partitioned queries using new method "setPartitionValue()"
- support creating partitioned collections
- support for nested partition keys

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
$conn = new \Jupitern\CosmosDb\CosmosDb('https://localhost:8081', 'primaryKey');
$conn->setHttpClientOptions(['verify' => false]); # optional: set guzzle client options.
$db = $conn->selectDB('testdb');

# create a new collection
$collection = $db->createCollection('Users', 'country');

# select existing collection
$collection = $db->selectCollection('Users');
```

### Inserting Records

```php
$rid = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setCollection($collection)
    ->setPartitionKey('country')
    ->save([
        'id' => '2', 
        'name' => 'Jane doe', 
        'age' => 35, 
        'country' => 'Portugal'
    ]);
```

### Updating Records

```php
$rid = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setCollection($collection)
    ->setPartitionKey('country')
    ->save([
        "_rid" => $rid, 
        'id' => '2', 
        'name' => 'Jane Doe Something', 
        'age' => 36, 
        'country' => 'Portugal'
    ]);
```

### Querying Records

```php
# cross partition query to get a single document and return it as an array
$res = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setCollection($collection)
    ->select("c.id, c.name")
    ->where("c.age > @age and c.country = @country")
    ->params(['@age' => 10, '@country' => 'Portugal'])
    ->find(true) # pass true if is cross partition query
    ->toArray();

# query a document using a known partition value
# and return as an array. note: setting a known
# partition value will result in a more efficient
# query against your database as it will not rely
# on cross-partition querying
$res = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setCollection($collection)
    ->setPartitionValue('Portugal')
    ->select("c.id, c.name")
    ->where("c.age > @age")
    ->params(['@age' => 10])
    ->find()
    ->toArray();

# query to get the top 5 documents as an array, with the
# document ID as the array key.
# note: refer to limitations section
$res = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setCollection($collection)
    ->select("c.id, c.name")
    ->where("c.age > @age and c.country = @country")
    ->params(['@age' => 10, '@country' => 'Portugal'])
    ->limit(5)
    ->findAll()
    ->toArray('id');

# query a document using a collection alias and cross partition query
$res = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setCollection($collection)
    ->select("TestColl.id, TestColl.name")
    ->from("TestColl")
    ->where("TestColl.age > @age")
    ->params(['@age' => 10])
    ->findAll(true) # pass true if is cross partition query
    ->toArray();
```

### Deleting Records

```php
# delete one document that matches criteria (single partition)
$res = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setCollection($collection)
    ->setPartitionKey('country')
    ->where("c.age > 20 and c.country = 'Portugal'")
    ->delete();

# delete all documents that match criteria (cross partition)
$res = \Jupitern\CosmosDb\QueryBuilder::instance()
    ->setCollection($collection)
    ->setPartitionKey('country')
    ->where("c.age > 20")
    ->deleteAll(true);
```

### Error handling

```php
try {
    $res = QueryBuilder::instance()
        ->setCollection($collection)
        ->deleteAll(true);
        
} catch (\GuzzleHttp\Exception\ClientException $e) {
    $response = json_decode($e->getResponse()->getBody());
    echo "ERROR: ".$response->code ." => ". $response->message .PHP_EOL.PHP_EOL;

    echo $e->getTraceAsString();
}
```


## Contributing

- welcome to discuss a bugs, features and ideas.

## License

jupitern/cosmosdb is release under the MIT license.

You are free to use, modify and distribute this software, as long as the copyright header is left intact
