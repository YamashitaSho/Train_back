<?php
require '../vendor/autoload.php';

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

$tableName = 'Movies';

$year = 2015;
$title = 'The Big New Movie';


$item = $marshaler->marshalJson('
    {
        "year": ' . $year . ',
        "title": "' . $title . '",
        "info": {
            "plot": "Nothing happens at all.",
            "rating": 0
        }
    }
');

$params = [
    'TableName' => $tableName,
    'Item' => $item,
    'ConditionExpression' =>
        'attribute_not_exists(#yr) and attribute_not_exists(title)',
    'ExpressionAttributeNames' => ["#yr" => "year"]
];

try {
    $result = $dynamodb->putItem($params);
    echo "Added item: $year - $title\n";

} catch (DynamoDbException $e) {
    echo "Unable to add item:\n";
    echo $e->getMessage() . "\n";
}

?>