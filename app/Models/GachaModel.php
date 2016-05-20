<?php
namespace App\Models;

use Aws\DynamoDb\Marshaler;
use App\Models\TransactionModel;
use App\Models\DynamoDBHandler;
use App\Services\Common\Record;


class GachaModel extends DynamoDBHandler
{
    public function __construct()
    {
        $this->record = new Record();
        $this->marshaler = new Marshaler();
        $this->trans = new TransactionModel();

        parent::__construct();
    }
    /**
     * [Method] すでにある重み情報を読み込む
     */
    public function readGachaBox($user_id){
        $gachabox = [];
        $get = [
            'TableName' => 'a_gachas',
            'Key' => [
                'user_id' => [
                    'N' => (string)$user_id
                ]
            ]
        ];
        $result = $this->getItem($get);
        if (!empty($result)){
            foreach ($result['box'] as $char){
                $gachabox[$char['char_id']] = $char['weight'];
            }
        }
        return $gachabox;
    }


    /**
     * [関数] 重み情報を読み込む
     * @var $weights : 要素名[char_id] = [weight]
     */
    public function readWeight ()
    {
        $scan = [
            'TableName' => 'chars',
            'ProjectionExpression' => 'char_id, weight'
        ];
        $chars = $this->scan($scan, 'Failed to Get CharData');
        $weights = [];                         # $weights[$char_id] = weight;
        foreach ($chars as $char) {
            $weights[$char['char_id']] = $char['weight'];
        }
        return $weights;
    }


    /**
    * [関数] 指定されたidのキャラを読み込む
    *
    * @param $prize_id : 抽選結果のchar_id
    */
    public function readPrize ($prize_id)
    {
        $get = [
            'TableName' => 'chars',
            'Key' => [
                'char_id' => [
                    'N' => (string)$prize_id
                ]
            ],
            'ProjectionExpression' => 'char_id, #nm, #stat, exp, #lv',
            'ExpressionAttributeNames' => [
                '#nm' => 'name',
                '#stat' => 'status',
                '#lv' => 'level'
            ]
        ];
        $prize_char = $this->getItem($get, 'Failed to Get CharStatus');

        return $prize_char;
    }


    /**
    * [関数] 所持キャラデータ読み込みの関数
    *
    * 読み込むのは char_id のみ
    */
    public function readChar($user_id)
    {
        $eav = $this->marshaler->marshalJson('
            {
                ":user_id": '.$user_id.'
            }
        ');
        $query = [
            'TableName' => 'a_chars',
            'KeyConditionExpression' => 'user_id = :user_id',
            'ProjectionExpression' => 'char_id',
            'ExpressionAttributeValues' => $eav
        ];
        $chars = $this->queryItem($query, 'Failed to Get OwnCharData');
        return $chars;
    }


    /**
     * [Method] ガチャの重み配列を保存する
     */
    public function putGachaBox($user_id, $gachabox)
    {
        $record = $this->record->makeRecordStatus();
        $box = [];
        $count = 0;
        foreach ($gachabox as $key => $value){
            $box[$count]['char_id'] = $key;
            $box[$count]['weight'] = $value;
            $count++;
        }
        $key = [
            'user_id' => $user_id,
        ];
        $item = [
            'user_id' => $user_id,
            'box' => $box,
            'record' => $record
        ];
        $put = [
            'TableName' => 'a_gachas',
            'Key' => $this->marshaler->marshalItem($key),
            'Item' => $this->marshaler->marshalItem($item)
        ];
        $result = $this->putItem($put);
        return ;
    }


    /**
    * [関数] 入手キャラデータの整形関数
    */
    private function writeChar($user_id, $char, $record)
    {
        $key = [
            'user_id' => $user_id,
            'char_id' => $char['char_id']
        ];
        $item = [
            'exp' => $char['exp'],
            'level' => $char['level'],
            'status' => $char['status'],
            'user_id' => $user_id,
            'char_id' => $char['char_id'],
            'record' => $record
        ];
        $put = [
            'TableName' => 'a_chars',
            'Key' => $this->marshaler->marshalItem($key),
            'Item' => $this->marshaler->marshalItem($item)
        ];
        return $put;
    }


    /**
    * [関数] ユーザーデータ書き込み変数の整形
    */
    private function updateUser($user_id, $gacha_cost, $record)
    {
        $key = [
            'user_id' => [
                'N' => (string)$user_id
            ]
        ];
        $expression_attribute_values = [
            ':gacha_cost' => [
                'N' => (string)$gacha_cost
            ],
            ':record' => [
                'S' => (string)$record['update_date']
            ]
        ];
        $update_expression = 'set money = money - :gacha_cost, #rec.update_date = :record';

        $update = [
            'TableName' => 'a_users',
            'Key' => $key,
            'ExpressionAttributeValues' => $expression_attribute_values,
            'ExpressionAttributeNames' => [
                '#rec' => 'record'
            ],
            'UpdateExpression' => $update_expression
        ];
        return $update;
    }


    /**
     * [Method] ガチャ配列の整形
     */
    private function updateGachaBox($user_id, $prize_index, $record)
    {
        $key = [
            'user_id' => [
                'N' => (string)$user_id
            ]
        ];

        $expression_attribute_values = [
            ':weight' => [
                'N' => '0'
            ],
            ':record' => [
                'S' => (string)$record['update_date']
            ]
        ];
        $update_expression = 'set box['.$prize_index.'].weight = :weight, #rec.update_date = :record';

        $update = [
            'TableName' => 'a_gachas',
            'Key' => $key,
            'ExpressionAttributeValues' => $expression_attribute_values,
            'ExpressionAttributeNames' => [
                '#rec' => 'record'
            ],
            'UpdateExpression' => $update_expression
        ];
        return $update;
    }


    /**
     * [Method]
     */
    private function getPrizeIndex($char_id, $gachabox)
    {
        $prize_index = 0;
        $count = 0;
        foreach ($gachabox as $key => $value){
            if ($key == $char_id){
                $prize_index = $count;
            }
            $count++;
        }
        return $prize_index;
    }

    /**
    * [関数] ガチャ結果書き込み関数
    *
    * フォーマットに従って変数を詰めた後トランザクションクラスに渡し、結果を返す
    */
    public function putGachaResult($user, $prize_char, $gacha_cost, $gachabox)
    {
        $record = $this->record->makeRecordStatus();
        $char_put = $this->writeChar($user['user_id'], $prize_char, $record);
        $user_update = $this->updateUser($user['user_id'], $gacha_cost, $record);

        $prize_index = $this->getPrizeIndex($prize_char['char_id'], $gachabox);
        $gachabox_update = $this->updateGachaBox($user['user_id'], $prize_index, $record);
        $requests = [
            $char_put,
            $user_update,
            $gachabox_update
        ];

        $result = $this->trans->isTransSuccess($user, $record, $requests);
        return $result;
    }
}
