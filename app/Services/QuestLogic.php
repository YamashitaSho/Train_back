<?php
namespace App\Services;

require '../vendor/autoload.php';
use Illuminate\Database\Eloquent\Model;

use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\Marshaler;

class QuestLogic extends Model
{
    private $dynamodb;
    private $marshaler;
    public function __construct()
    {
        $sdk = new \Aws\Sdk([
            'region'   => 'ap-northeast-1',
            'version'  => 'latest'
        ]);
        date_default_timezone_set('UTC');
        $this->dynamodb = $sdk->createDynamoDb();
        $this->marshaler = new Marshaler();
        $this->userinfo = new UserInfo();
        $this->record = new Record();
    }
    /**
    * [API] クエストコマンドからのバトルを作成する関数
    *
    * バトル作成可能条件: ユーザーデータで指定されるバトルデータがclosedであること(created, in processの場合は無視)
    *
    */
    public function joinQuest()
    {
        /*
        battles に書き込むべき情報
            user_id
            battle_id(not 0)
            progress: created
            friend_position
            enemy_position

        users に書き込むべき情報
            battle_id(上書き)
        */

        #ユーザー情報の取得
        $user_id = $this->userinfo->getUserID();
        $user = $this->userinfo->getUserStatus($user_id);

        #バトル作成可能条件の確認：user['battle_id']がclosedであることの確認
        if ($user['battle_id'] != 0){          #battle_id = 0だった場合はチェックを飛ばして新規作成する
            try {
                $result = $this->dynamodb->getItem([
                    'TableName' => 'a_battles',
                    'Key' => [
                        'user_id' => [
                            'N' =>(string)$user_id
                        ],
                        'battle_id' => [
                            'N' => (string)$user['battle_id'],
                        ]
                    ],
                    'ProjectionExpression' => 'progress',
                ]);
            } catch (DynamoDbException $e) {
                echo $e->getmessage() . "\n";
                return ["status: Failed to Read BattleLog", 500];
            }
            if ($result['Item']['progress']['S'] != "closed"){
                $response = [
                    "battle_id" => $user['battle_id']
                ];
                return [$response, 201];           #ほんとは作ってないから200
            }
        }

        # $battle_idはusersテーブルにあるものの連番として作る
        $battle_id = $user['battle_id'] + 1;
        #パーティに加えられているキャラの読み込み
        try {
            $result = $this->dynamodb->batchGetItem([
                'RequestItems' => [
                    'a_chars' => [
                        'Keys' => [
                            [
                                'user_id' => [ 'N' => (string)$user_id ],
                                'char_id' => [ 'N' => (string)$user['party'][0]['char_id'] ]
                            ],
                            [
                                'user_id' => [ 'N' => (string)$user_id ],
                                'char_id' => [ 'N' => (string)$user['party'][1]['char_id'] ]
                            ],
                            [
                                'user_id' => [ 'N' => (string)$user_id ],
                                'char_id' => [ 'N' => (string)$user['party'][2]['char_id'] ]
                            ],
                        ],
                        'ProjectionExpression' => 'char_id, exp, #lv, #st',
                        'ExpressionAttributeNames' => [
                            '#lv' => 'level',
                            '#st' => 'status',
                        ]
                    ]
                ]
            ]);
        } catch (DynamoDbException $e) {
            return ["status: Failed to Read CharData", 500];
        }
        for ($i = 0; $i < count($result['Responses']['a_chars']); $i++){
            $friend_position[$i] = $this->marshaler->unmarshalItem($result['Responses']['a_chars'][$i]);
        }

        # 敵パーティ情報を読み込む
        $enemyparty_id = 0;                    #テスト用

        try {
            $result = $this->dynamodb->getItem([
                'TableName' => "enemyparties",
                'Key' => [
                    'enemyparty_id' =>
                        [ 'N' => (string)$enemyparty_id ]
                ]
            ]);
        } catch (DynamoDbException $e) {
            return ["status: Failed to Read Enemies", 500];
        }
        $enemyparty = $this->marshaler->unmarshalItem($result['Item']);

        #敵パーティに加えられているキャラの読み込み
        try {
            $result = $this->dynamodb->batchGetItem([
                'RequestItems' => [
                    'enemies' => [
                        'Keys' => [
                            [
                                'enemy_id' => [ 'N' => (string)$enemyparty['party'][0]['enemy_id']]
                            ],
                            [
                                'enemy_id' => [ 'N' => (string)$enemyparty['party'][1]['enemy_id']]
                            ],
                            [
                                'enemy_id' => [ 'N' => (string)$enemyparty['party'][2]['enemy_id']]
                            ],
                        ],
                        'ProjectionExpression' => 'char_id, exp, #nm, #lv, #st',
                        'ExpressionAttributeNames' => [
                            '#lv' => 'level',
                            '#st' => 'status',
                            '#nm' => 'name',
                        ]
                    ]
                ]
            ]);
        } catch (DynamoDbException $e) {
            return ["status: Failed to Read CharData", 500];
        }
        for ($i = 0; $i < count($result['Responses']['enemies']); $i++){
            $enemy_position[$i] = $this->marshaler->unmarshalItem($result['Responses']['enemies'][$i]);
        }
        #print_r($enemy_position);
        # battlesテーブルに書き込むデータのまとめ
        $put_item = [
            "user_id" => $user_id,
            "battle_id" => $battle_id,
            "progress" => "created",
            "friend_position" => $friend_position,
            "enemy_position" => $enemy_position,
            "record" => $this->record->makeRecordStatus(),
            "type" => "quest",
        ];
        $put_key = [
            'user_id' => $put_item['user_id'],
            'battle_id'=> $put_item['battle_id']
        ];
        $put_battle = [
            'TableName' => 'a_battles',
            'Key' => $this->marshaler->marshalItem($put_key),
            'Item' => $this->marshaler->marshalItem($put_item),
        ];
        #書き込む
        try {
            $result = $this->dynamodb->putItem($put_battle);
        } catch (DynamoDbException $e) {
            return ["status: Failed to Write BattleData", 500];
        }

        #ユーザーデータの書き込み

        # ユーザー情報書き込み
        $user['record'] = $this->record->updateRecordStatus($user['record']);
        $user['battle_id'] = $battle_id;
        $put_item = $user;
        $put_key = [
            'user_id' => $user_id
        ];
        $put_user = [
            'TableName' => 'a_users',
            'Key' => $this->marshaler->marshalItem($put_key),
            'Item' => $this->marshaler->marshalItem($put_item),
        ];

        try {
            $result = $this->dynamodb->putItem($put_user);
        } catch (DynamoDbException $e) {
            return ["status: Failed to Write UserData", 500];
        }

        $response = [ "battle_id" => $battle_id ];
        return [$response, 201];
    }
}