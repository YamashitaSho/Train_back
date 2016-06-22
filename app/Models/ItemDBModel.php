<?php
namespace App\Models;

use Aws\DynamoDb\Marshaler;
use App\Models\DynamoDBHandler;

class ItemDBModel extends DynamoDBHandler
{
    public function __construct()
    {
        parent::__construct();
        $this->marshaler = new Marshaler();
    }


    /**
    * [関数] アイテムのデータを読み込む
    *
    * 読み込むデータ : item_id, name, text, status
    */
    public function getItems($items)
    {
        $key = [];
        foreach ($items as $item){
            if ($item['item_id'] != 0){
                $key[] = [
                    'item_id' => [
                        'N' => (string)$item['item_id']
                    ]
                ];
            }
        }
        if (empty($key)){
            $key[] = [
                'item_id' => [
                    'N' => '0'
                ]
            ];
        }
        $get = [
            'RequestItems' => [
                'items' => [
                    'Keys' => $key,
                    'ProjectionExpression' => 'item_id, #nm, #txt, #st',
                    'ExpressionAttributeNames' => [
                        '#nm' => 'name',
                        '#txt' => 'text',
                        '#st' => 'status'
                    ]
                ]
            ]
        ];
        $items_master = $this->batchGetItem($get, 'Failed to read ItemData');
        return $items_master['items'];
    }

}