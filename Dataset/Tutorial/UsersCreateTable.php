<?php
require '../vendor/autoload.php';

$sdk = new Aws\Sdk([
//    'endpoint'   => 'https://dynamodb.ap-northeast-1.amazonaws.com',
    'region'   => 'ap-northeast-1',
    'version'  => 'latest'
]);

$dynamodb = $sdk->createDynamoDb();

$params = [
    'TableName' => 'users',
    'KeySchema' => [
        [
            'AttributeName' => 'id',
            'KeyType' => 'HASH'
        ]
    ],
    'AttributeDefinitions' => [
        [
            'AttributeName' => 'create_date',
            'AttributeType' => 'S'
        ],
        [
            'AttributeName' => 'update_date',
            'AttributeType' => 'S'
        ],
        [
            'AttributeName' => 'deleted',
            'AttributeType' => 'N'
        ],
        [
            'AttributeName' => 'lastview',
            'AttributeType' => 'S'
        ],
        [
            'AttributeName' => 'user_name',
            'AttributeType' => 'S'
        ],
        [
            'AttributeName' => 'money',
            'AttributeType' => 'N'
        ],
        [
            'AttributeName' => 'party_id',
            'AttributeType' => 'N'
        ],
        [
            'AttributeName' => 'questcount',
            'AttributeType' => 'N'
        ],
        [
            'AttributeName' => 'questitem',
            'AttributeType' => 'N'
        ],
        [
            'AttributeName' => 'char_id1',
            'AttributeType' => 'N'
        ],
        [
            'AttributeName' => 'char_id2',
            'AttributeType' => 'N'
        ],
        [
            'AttributeName' => 'char_id3',
            'AttributeType' => 'N'
        ],
        [
            'AttributeName' => 'char_id4',
            'AttributeType' => 'N'
        ],
        [
            'AttributeName' => 'char_id5',
            'AttributeType' => 'N'
        ],
        [
            'AttributeName' => 'char_id6',
            'AttributeType' => 'N'
        ],
        [
            'AttributeName' => 'char_id7',
            'AttributeType' => 'N'
        ],
        [
            'AttributeName' => 'char_id8',
            'AttributeType' => 'N'
        ],
        [
            'AttributeName' => 'char_id9',
            'AttributeType' => 'N'
        ],
        [
            'AttributeName' => 'char_id10',
            'AttributeType' => 'N'
        ],
        [
            'AttributeName' => 'char_id11',
            'AttributeType' => 'N'
        ],
        [
            'AttributeName' => 'char_id12',
            'AttributeType' => 'N'
        ]
    ],
    'ProvisionedThroughput' => [
        'ReadCapacityUnits' => 10,
        'WriteCapacityUnits' => 10
    ]
];
try {
    $result = $dynamodb->createTable($params);
    echo 'Created table.  Status: ' .
        $result['TableDescription']['TableStatus'] ."\n";
} catch (DynamoDbException $e) {
    echo "Unable to create table:\n";
    echo $e->getMessage() . "\n";
}