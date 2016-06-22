<?php
namespace App\Models;

use Aws\DynamoDb\Marshaler;
use App\Models\DynamoDBHandler;

class CharDBModel extends DynamoDBHandler
{
    public function __construct()
    {
        parent::__construct();
        $this->marshaler = new Marshaler();
    }


    /**
    * [関数] ユーザーが所持しているキャラの読み込み
    *
    * @param int $user_id ユーザーID
    * @return array $chars キャラステータスの配列 キャラ未所持の場合は空配列
    * 読み込むデータ: char_id, level, exp, status, name
    * $idonly に trueが指定された場合、 読み込むデータは char_id のみ
    */
    public function getCharOwned($user_id, $idonly = false)
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
     * パーティに所属している味方キャラを読み込む
     * @param array $user ユーザー情報の配列
     * @return array $chars キャラステータスの配列 未編成の場合は空配列
     */
    public function readCharInParty($user)
    {
        $key = [];
        foreach($user['party'] as $char){
            $key[] = [
                'user_id' => [
                    'N' => (string)$user['user_id']
                ],
                'char_id' => [
                    'N' => (string)$user['char_id']
                ],
            ];
        }
        if (empty($key)){
            return [];
        }
        $get = [
            'RequestItems' => [
                'a_chars' => [
                    'Keys' => $key,
                    'ProjectionExpression' => 'char_id, exp, #lv, #st, #nm',
                    'ExpressionAttributeNames' => [
                        '#nm' => 'name',
                        '#lv' => 'level',
                        '#st' => 'status',
                    ]
                ]
            ]
        ];
        $result = $this->batchGetItem($get, 'Failed to Read Chardata');
        $chars = $result['a_chars'];
        return $chars;
    }
}