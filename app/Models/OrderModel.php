<?php
namespace App\Models;

use Aws\DynamoDb\Marshaler;
use App\Models\DynamoDBHandler;
use App\Services\Common\Record;

class OrderModel extends DynamoDBHandler
{
    public function __construct()
    {
        parent::__construct();
        $this->marshaler = new Marshaler();
        $this->record = new Record();
    }


    /**
    * [関数] 所持しているキャラの読み込み
    *
    * 読み込むデータ: char_id, level, exp, status, name
    * $idonly に trueが指定された場合、 読み込むデータは char_id のみ
    */
    public function readChar($user_id, $idonly = false)
    {
        $eav = $this->marshaler->marshalJson('
            {
                ":user_id": '.$user_id.'
            }
        ');


        $query = [
            'TableName' => 'a_chars',
            'KeyConditionExpression' => 'user_id = :user_id',
            'ExpressionAttributeValues' => $eav,
        ];

        if ($idonly) {
            $query['ProjectionExpression'] = 'char_id';
        } else {
            $query['ProjectionExpression'] = 'char_id, exp, #lv, #st, #nm';
            $query['ExpressionAttributeNames'] = [
                '#lv' => 'level',
                '#st' => 'status',
                '#nm' => 'name',
            ];
        }

        $chars = $this->queryItem($query, 'Failed to get CharData');
        return $chars;
    }


    /**
    * [関数] 所持しているキャラのマスターデータを読み込む。
    *
    * 読み込むデータ: char_id, status_max
    */
    public function readCharMaster($chars)
    {
        $key = [];
        foreach ($chars as $char){
            $key[] = [
                'char_id' => [
                    'N' => (string)$char['char_id']
                ]
            ];
        }
        $get = [
            'RequestItems' => [
                'chars' => [
                    'Keys' => $key,
                    'ProjectionExpression' => 'char_id, status_max'
                ]
            ]
        ];
        $chars_master = $this->batchGetItem($get, 'Failed to read CharData(Master)');
        return $chars_master['chars'];
    }


    /**
    * [関数] アイテムのデータを読み込む
    *
    * 読み込むデータ : item_id, name, text, status
    */
    public function readItem($items)
    {
        $key = [];
        foreach ($items as $item){
            $key[] = [
                'item_id' => [
                    'N' => (string)$item['item_id']
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


    /**
    * ユーザー情報の更新
    *
    * RecordStatus を更新し、 usersテーブルに書き込む
    */
    public function updateUser($user)
    {
        $user['record'] = $this->record->updateRecordStatus($user['record']);
        $key = [
            'user_id' => $user['user_id']
        ];
        $put = [
            'TableName' => 'a_users',
            'Key' => $this->marshaler->marshalItem($key),
            'Item' => $this->marshaler->marshalItem($user),
        ];
        $result = $this->putItem($put, 'Failed to Write UserData');
        return;
    }
}
