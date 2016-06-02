<?php
namespace App\Models;

use Aws\DynamoDb\Marshaler;
use App\Models\TransactionModel;
use App\Services\Common\Record;
use App\Models\DynamoDBHandler;

class ResultModel extends DynamoDBHandler
{
    private $marshaler;
    public function __construct()
    {
        parent::__construct();
        $this->marshaler = new Marshaler();
        $this->record = new Record();
        $this->trans = new TransactionModel();
    }


    /**
    * [関数] ユーザー情報に登録されたバトルを読み出す。
    */
    public function getBattleData($user)
    {
        $key = [
            'user_id' => [
                'N' => (string)$user['user_id']
            ],
            'battle_id' => [
                'N' => (string)$user['battle_id']
            ]
        ];
        $get = [
            'TableName' => 'a_battles',
            'Key' => $key
        ];
        $battle = $this->getItem($get, 'Failed to Get BattleLog');


        return $battle;
    }


    /**
    * [関数] バトルデータ更新(要トランザクション処理実装)
    */
    public function putBattleData($battle)
    {
        $key = [
            'user_id' => [
                'N' => (string)$battle['user_id']
            ],
            'battle_id' => [
                'N' => (string)$battle['battle_id']
            ]
        ];
        $item = $this->marshaler->marshalItem($battle);
        $put = [
            'TableName' => 'a_battles',
            'Key' => $key,
            'Item' => $item
        ];
        $res = $this->putItem($put, 'Failed to update BattleLog');
        return $res;
    }


    /**
     * [関数]戦闘に参加したキャラデータの受信
     */
    public function getBattleChar($user_id, $party)
    {
        $key = [];
        foreach ($party as $char){
            if ($char['char_id'] != 0){     //キャラID 0 は読み込まない
                $key[] = [
                    'user_id' => [
                        'N' => (string)$user_id
                    ],
                    'char_id' => [
                        'N' => (string)$char['char_id']
                    ]
                ];
            }
        }
        if ( empty($key) ){
            return [];
        }
        $get = [
            'RequestItems' => [
                'a_chars' => [
                    'Keys' => $key,
                    'ProjectionExpression' => 'char_id, exp, #lv, #st, #nm, #rc',
                    'ExpressionAttributeNames' => [
                        '#lv' => 'level',
                        '#st' => 'status',
                        '#nm' => 'name',
                        '#rc' => 'record',
                    ]
                ]
            ]
        ];
        $result = $this->batchGetItem($get, 'Failed to Read Chardata');
        return $result['a_chars'];
    }


    /**
     * [Method] 書き込むキャラデータの整形
     */
    private function updateChar($user_id, $char, $record)
    {
        $char['user_id'] = $user_id;
        $char['record']['update_date'] = $record['update_date'];
        $update = [
            'TableName' => 'a_chars',
            'Key' => $this->marshaler->marshalItem([
                'user_id' => (int)$user_id,
                'char_id' => (int)$char['char_id']
            ]),
            'Item' => $this->marshaler->marshalItem($char)
        ];
        return $update;
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
        $update_expression = 'set money = money + :gacha_cost, #rec.update_date = :record';

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
     * [Method] 書き込むバトルデータの整形
     */
    private function updateBattle($user_id, $battle, $record)
    {
        $battle['user_id'] = $user_id;
        $battle['record']['update_date'] = $record['update_date'];
        $update = [
            'TableName' => 'a_battles',
            'Key' => $this->marshaler->marshalItem([
                'user_id' => (int)$user_id,
                'battle_id' => (int)$battle['battle_id']
            ]),
            'Item' => $this->marshaler->marshalItem($battle)
        ];
        return $update;
    }


    /**
    * [関数] 戦闘結果書き込み関数
    *
    * フォーマットに従って変数を詰めた後トランザクションクラスに渡し、結果を返す
    */
    public function putBattleResult($user, $party, $battle)
    {
        $chars_update = [];
        $record = $this->record->makeRecordStatus();
        $prize = $battle['obtained']['prize'];

        foreach($party as $char){
            $char['record'] = $this->record->updateRecordStatus($char['record']);
            $chars_update[] = $this->updateChar($user['user_id'], $char, $record);
        }
        $user_update = $this->updateUser($user['user_id'], $prize, $record);
        $battle_update = $this->updateBattle($user['user_id'], $battle, $record);

        $requests = $chars_update;
        $requests[] = $user_update;
        $requests[] = $battle_update;
        $result = $this->trans->isTransSuccess($user, $record, $requests);
        return $result;
    }
}
