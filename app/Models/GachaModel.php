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
            ]
        ];
        $update_expression = 'set money = money - :gacha_cost';

        $update = [
            'TableName' => 'a_users',
            'Key' => $key,
            'ExpressionAttributeValues' => $expression_attribute_values,
            'UpdateExpression' => $update_expression
        ];
        return $update;
    }
    /**
    * [関数] ガチャ結果書き込み関数
    *
    * フォーマットに従って変数を詰めた後トランザクションクラスに渡し、結果を返す
    */
    public function putGachaResult($user, $prize_char, $gacha_cost)
    {
        $record = $this->record->makeRecordStatus();
        $char_put = $this->writeChar($user['user_id'], $prize_char, $record);
        $user_update = $this->updateUser($user['user_id'], $gacha_cost, $record);
        $requests = [
            $char_put,
            $user_update
        ];

        $result = $this->trans->isTransSuccess($user, $record, $requests);
        return $result;
    }

}
