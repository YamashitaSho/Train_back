<?php
namespace App\Models;

use Aws\DynamoDb\Marshaler;
use App\Models\DynamoDBHandler;
use App\Models\TransactionModel;
use App\Services\Common\Record;

/**
 * [Class] アリーナに関わるModelクラス
 *
 * 未実装項目 バトル発行はトランザクション処理として行う
 */
class StageModel extends DynamoDBHandler
{
    public function __construct()
    {
        parent::__construct();
        $this->marshaler = new Marshaler();
        $this->record = new Record();
        $this->trans = new TransactionModel();
    }


    /**
    * [Method] パーティに組み込まれている味方キャラデータを読み込む
    */
    public function readCharInParty($user)
    {
        #読み込むキーを設定 : $key
        $key = [];
        foreach ($user['party'] as $char){
            $key[] = [
                'user_id' => [
                    'N' => (string)$user['user_id']
                ],
                'char_id' => [
                    'N' => (string)$char['char_id']
                ]
            ];
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
        return $result['a_chars'];
    }


    /**
     * [Method] JOINするアリーナ情報を読み込む
     */
    public function getArena($arena_id)
    {
        $key = [
            'arena_id' => [
                'N' => (string)$arena_id
            ]
        ];
        $get = [
            'TableName' => 'arenas',
            'Key' => $key
        ];
        $result = $this->getItem($get);
        return $result;
    }



    /**
     * [Method] アリーナ情報リストを読み込む
     */
    public function getArenas($index)
    {
        $key = [];
        foreach ($index as $id){
            $key[] = [
                'arena_id' => [
                    'N' => (string)$id
                ]
            ];
        }

        $get = [
            'RequestItems' => [
                'arenas' => [
                    'Keys' => $key,
                    'ProjectionExpression' => 'arena, entry_fee'
                ]
            ]
        ];
        $result = $this->batchGetItem($get);
        return $result['arenas'];
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
        $result = $this->getItem($get);
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
    * [Method] バトルテーブルへバトルの初期状態を書き込む
    * @param $user array
    * @param $friends array
    * @param $friends array
    * @param $arena array
    * @param $type string
    */
    public function putBattle($user, $friends, $enemies, $arena, $type)
    {
        $item = [
            "user_id" => $user['user_id'],
            "battle_id" => $user['battle_id'],
            "progress" => "created",
            "friend_position" => $friends,
            "enemy_position" => $enemies,
            "record" => $this->record->makeRecordStatus(),
            "type" => $type,
            "arena" => $arena
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
        return $this->putItem($put, 'Failed to Write BattleData');
    }


    /**
    * [Method] ユーザーテーブルへバトル情報を書き込む
    */
    public function updateUser($user)
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

    /**
     * バトル情報を書き込むトランザクションを実行
     */
    public function transBattle($user, $friends, $enemies, $arena, $type)
    {
        $record = $this->record->makeRecordStatus();
        $a_battle = $this->putBattle($user, $friends, $enemies, $arena, $type);
        $a_user = $this->updateUser($user);
        $this->trans->isTransSuccess();
    }
}
