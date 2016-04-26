<?php
namespace App\Models;

require '../vendor/autoload.php';
use Illuminate\Database\Eloquent\Model;

use Aws\DynamoDb\Marshaler;
use App\Models\DynamoDBHandler;
use App\Services\Common\Record;

class QuestModel extends DynamoDBHandler
{
    public function __construct()
    {
        parent::__construct();
        $this->marshaler = new Marshaler();
        $this->record = new Record();
    }
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
    * 敵パーティ読み込み
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
    * 敵キャラデータ読み込み
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
    * 味方キャラデータ読み込み
    *
    */
    public function readCharInParty($user)
    {
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
                    'ProjectionExpression' => 'char_id, exp, #lv, #st',
                    'ExpressionAttributeNames' => [
                        '#lv' => 'level',
                        '#st' => 'status',
                    ]
                ]
            ]
        ];
        $enemies = $this->batchGetItem($get, 'Failed to Read Chardata');
        return $enemies['a_chars'];
    }
    /**
    * バトルテーブルへの書き込み
    */
    public function writeBattle($user, $friend_position, $enemy_position)
    {
        $item = [
            "user_id" => $user['user_id'],
            "battle_id" => $user['battle_id'],
            "progress" => "created",
            "friend_position" => $friend_position,
            "enemy_position" => $enemy_position,
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
    * ユーザーテーブルへの書き込み
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
