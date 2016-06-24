<?php
namespace App\Models;

use Aws\DynamoDb\Marshaler;
use App\Models\DynamoDBHandler;
use App\Services\Common\Record;

class BattleDBModel extends DynamoDBHandler
{
    public function __construct()
    {
        parent::__construct();
        $this->marshaler = new Marshaler();
        $this->record = new Record();
    }


    /**
    * ユーザー情報に登録されたバトルを読み出す。
    * @param array $user ユーザーデータ
    */
    public function getBattleByUser($user)
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
        $battle = $this->getItem($get);

        return $battle;
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
     * バトルデータの作成情報の作成
     * @param array $user ユーザー情報
     * @param array $friends 味方PT情報
     * @param array $enemies 敵PT情報
     * @param string $type バトルのタイプ
     * @param array $arena アリーナ用のデータ
     * @return array $put バトルデータ作成情報
     */
    public function getQueryPutBattle($user, $friends, $enemies, $type, $arena = [])
    {
        $item = [
            "user_id" => $user['user_id'],
            "battle_id" => $user['battle_id'],
            "progress" => "created",
            "friend_position" => $friends,
            "enemy_position" => $enemies,
            "record" => $this->record->makeRecordStatus(),
            "type" => $type,
        ];
        if ($type != "quest"){
            $item['arena'] = $arena;
        }
        $key = [
            'user_id' => $user['user_id'],
            'battle_id'=> $user['battle_id']
        ];
        $put = [
            'TableName' => 'a_battles',
            'Key' => $this->marshaler->marshalItem($key),
            'Item' => $this->marshaler->marshalItem($item),
        ];
        return $put;
    }


    /**
     * バトルデータを更新する
     */
    public function updateBattle($user_id, $battle)
    {
        $update = $this->getQueryUpdateBattle($user_id, $battle);
        return $this->putItem($update);
    }

    /**
     * バトルデータ更新情報の作成
     * @param int $user_id ユーザーID
     * @param array $battle 更新するバトル情報
     * @return array $update バトルデータ更新情報
     */
    public function getQueryUpdateBattle($user_id, $battle)
    {
        $battle['record'] = $this->record->UpdateRecordStatus($battle['record']);
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

}