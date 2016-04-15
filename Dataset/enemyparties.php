<?php
require 'vendor/autoload.php';

$sdk = new Aws\Sdk([
//    'endpoint'   => 'https://dynamodb.ap-northeast-1.amazonaws.com',
    'region'   => 'ap-northeast-1',
    'version'  => 'latest'
]);

$dynamodb = $sdk->createDynamoDb();

date_default_timezone_set('UTC');

use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\Marshaler;

$dynamodb = $sdk->createDynamoDb();
$marshaler = new Marshaler();

$tableName = 'enemyparties';

$data = json_decode(file_get_contents('Dataset/Data/enemyparties.json'), true);

foreach ($data as $record) {

    $params = [
        'TableName' => $tableName,
        'Item' => $marshaler->marshalItem($record)
    ];

    try {
        $result = $dynamodb->putItem($params);
        echo "Added enemyparty_id:".$record['enemyparty_id']."\n";
    } catch (DynamoDbException $e) {
        echo "Failed to Add Data:\n";
        echo $e->getMessage() . "\n";
        break;
    }

}

?>