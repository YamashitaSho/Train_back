<?php
namespace App\Models;

use Aws\DynamoDb\Marshaler;
use App\Models\DynamoDBHandler;
use App\Services\Common\Record;

class GachaDBModel extends DynamoDBHandler
{
    public function __construct()
    {
        parent::__construct();
        $this->marshaler = new Marshaler();
        $this->record = new Record();
    }


    /**
     * ユーザーのガチャの重み情報を読み込み
     * @param int $user_id ユーザーID
     */
    public function getGachaBox($user_id)
    {
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
     * ガチャの重み情報を作成
     * @param int $user_id ユーザーID
     * @param array $gachabox 重み情報の配列
     * @return object $result 書き込み結果
     */
    public function putGachaBox($user_id, $gachabox)
    {
        $put = $this->getQueryPutGachaBox($user_id, $gachabox);
        $result = $this->putItem($put);
        return $result;
    }


    /**
     * ガチャの重み情報を書き込むリクエストの作成
     * @param int $user_id ユーザーID
     * @param array $gachabox 重み情報の配列
     * @return array $put 書き込みリクエスト
     */
    public function getQueryPutGachaBox($user_id, $gachabox)
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
            'user_id' => (int)$user_id,
        ];
        $item = [
            'user_id' => (int)$user_id,
            'box' => $box,
            'record' => $record
        ];
        $put = [
            'TableName' => 'a_gachas',
            'Key' => $this->marshaler->marshalItem($key),
            'Item' => $this->marshaler->marshalItem($item)
        ];
        return $put;
    }


    /**
     * ガチャの重み情報を更新するリクエストの作成
     * @param int $user_id ユーザーID
     * @param array $prize_index 獲得キャラのID
     */
    public function getQueryUpdateGachaBox($user_id, $prize_index)
    {
        $record = $this->record->makeRecordStatus();
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
}