<?php
namespace App\Models;

require '../vendor/autoload.php';
use Illuminate\Database\Eloquent\Model;

use Aws\DynamoDb\Marshaler;
use App\Models\DynamoDBHandler;
use App\Services\Common\Record;

class GachaModel extends DynamoDBHandler
{
    public function __construct()
    {
        $this->record = new Record();
        $this->marshaler = new Marshaler();

        parent::__construct();
    }
    /**
    * [関数] 重み情報を読み込む
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
    * [関数] キャラデータ書き込み関数
    */
    public function writeChar($char, $user_id)
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
            'record' => $this->record->makeRecordStatus()
        ];
        $put = [
            'TableName' => 'a_chars',
            'Key' => $this->marshaler->marshalItem($key),
            'Item' => $this->marshaler->marshalItem($item)
        ];
        $this->putItem($put, 'Failed to Write CharData');
        return ;
    }

    /**
    * [関数] ユーザーデータ書き込み関数
    */
    public function writeUser($user)
    {
        $user['record'] = $this->record->updateRecordStatus($user['record']);
        $key = [
            'user_id' => $user['user_id']
        ];
        $item = $user;
        $put = [
            'TableName' => 'a_users',
            'Key' => $this->marshaler->marshalItem($key),
            'Item' => $this->marshaler->marshalItem($item)
        ];
        $this->putItem($put, 'Failed to Write UserData');
        return ;
    }
}
