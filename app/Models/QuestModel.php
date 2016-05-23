<?php
namespace App\Models;

use Aws\DynamoDb\Marshaler;
use App\Models\DynamoDBHandler;
use App\Models\TransactionModel;
use App\Services\Common\Record;

/**
 * [Class] クエストに関わるModelクラス
 *
 * 未実装項目 バトル発行はトランザクション処理として行う
 */
class QuestModel extends DynamoDBHandler
{
    public function __construct()
    {
        parent::__construct();
        $this->marshaler = new Marshaler();
        $this->record = new Record();
        $this->trans = new TransactionModel();
    }


    /**
     * [Method] バトル情報を読み込む
     * @param $user ユーザー情報
     */
    public function readBattle($user)
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
            'Key' => $key,
            'ProjectionExpression' => 'progress'
        ];
        $result = $this->getItem($get, 'Failed to Read Battlelog');
        return $result;
    }


    /**
    * [Method] 敵パーティを読み込む
    * @param $enemyparty_id int 敵パーティを指定するID
    */
    public function readEnemyParty($enemyparty_id)
    {
        $key = [
            'enemyparty_id' => [
                'N' => (string)$enemyparty_id
            ]
        ];
        $get = [
            'TableName' => 'enemyparties',
            'Key' => $key
        ];
        $enemyparty = $this->getItem($get, 'Failed to Read Enemyparty');
        return $enemyparty;
    }


    /**
    * 敵キャラデータを読み込む
    * @param $party array charの配列
    * @param $char array enemy_idを含むキャラ情報
    */
    public function readEnemy($party)
    {
        $key = [];
        foreach ($party as $char){
            $key[] = [
                'enemy_id' => [
                    'N' => (string)$char['enemy_id']
                ]
            ];
        }
        $get = [
            'RequestItems' => [
                'enemies' => [
                    'Keys' => $key,
                    'ProjectionExpression' => 'char_id, exp, #nm, #lv, #st',
                    'ExpressionAttributeNames' => [
                        '#lv' => 'level',
                        '#st' => 'status',
                        '#nm' => 'name',
                    ]
                ]
            ]
        ];
        $enemies = $this->batchGetItem($get, 'Failed to Read Enemydata');
        return $enemies['enemies'];
    }


    /**
    * [Method] 味方キャラデータを読み込む
    */
    public function readCharInParty($user)
    {
        $key = [];
        foreach ($user['party'] as $char){
            if ($char['char_id'] != 0){     //キャラID 0 は読み込まない
                $key[] = [
                    'user_id' => [
                        'N' => (string)$user['user_id']
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
                    'ProjectionExpression' => 'char_id, exp, #lv, #st',
                    'ExpressionAttributeNames' => [
                        '#lv' => 'level',
                        '#st' => 'status',
                    ]
                ]
            ]
        ];
        $result = $this->batchGetItem($get, 'Failed to Read Chardata');
        return $result['a_chars'];
    }


    /**
    * [Method] バトルテーブルへバトルの初期状態を書き込む
    */
    public function writeBattle($user, $friends, $enemies)
    {
        $item = [
            "user_id" => $user['user_id'],
            "battle_id" => $user['battle_id'],
            "progress" => "created",
            "friend_position" => $friends,
            "enemy_position" => $enemies,
            "record" => $this->record->makeRecordStatus(),
            "type" => "quest",
        ];
        $key = [
            'user_id' => $user['user_id'],
            'battle_id'=> $user['battle_id']
        ];
        $put = [
            'TableName' => 'a_battles',
            'Key' => $this->marshaler->marshalItem($key),
            'Item' => $this->marshaler->marshalItem($item),
        ];
        $result = $this->putItem($put, 'Failed to Write BattleData');
        return $result;
    }


    /**
    * [Method] ユーザーテーブルへバトル情報を書き込む
    */
    public function writeUser($user)
    {
        $user['record'] = $this->record->updateRecordStatus($user['record']);
        $item = $user;
        $key = [
            'user_id' => $user['user_id']
        ];
        $put = [
            'TableName' => 'a_users',
            'Key' => $this->marshaler->marshalItem($key),
            'Item' => $this->marshaler->marshalItem($item),
        ];
        $result = $this->putItem($put, 'Failed to Write UserData');

        return $result;
    }
}
