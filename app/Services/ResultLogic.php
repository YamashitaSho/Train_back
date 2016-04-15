<?php
namespace App\Services;

require '../vendor/autoload.php';
use Illuminate\Database\Eloquent\Model;

use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\Marshaler;

class Resultlogic extends Model
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
    * [API] バトル結果表示、反映のAPI
    *
    */
    public function getResult($battle_id)
    {
        //進行中のバトルについて、戦闘終了情報が入力されるか5ターン経過するまでのデータを取得し、送信する。
        $user_id = $this->userinfo->getUserID();
        $user = $this->userinfo->getUserStatus($user_id);

        $get_item_array = [
            'TableName' => 'a_battles',
            'Key' => [
                'user_id' => [
                    'N' => (string)$user_id
                ],
                'battle_id' => [
                    'N' => (string)$battle_id
                ]
            ]
        ];
        #バトルデータの読み出し
        try {
            $result = $this->dynamodb->getItem($get_item_array);
        } catch (DynamoDbException $e) {
            echo $e->getMessage() . '\n';
            return ['status: Failed to get BattleLog', 500];
        }
        $data = $this->marshaler->unmarshalItem($result['Item']);
        if ($data['progress'] != 'in_process'){
            return ['status: Battle is NOT in Process', 400];
        }
        $data['progress'] = 'closed';               #ステータスを終了済みにする
        $data['record'] = $this->record->updateRecordStatus($data['record']);
        #キャラデータ更新
        for ($i = 0; $i < count($data['obtained']['chars']) ; $i++){
            try {
                $result = $this->dynamodb->getItem([
                    'TableName' => 'a_chars',
                    'Key' => $this->marshaler->marshalItem([
                        'user_id' => (int)$user_id,
                        'char_id' => (int)$data['obtained']['chars'][$i]['char_id']
                    ]),
                ]);
            } catch (DynamoDbException $e) {
                echo $e->getMessage();
                return ['status: Failed to get Chardata', 500];
            }
            $char = $data['obtained']['chars'][$i];
            $char['user_id'] = $user_id;
            $char['record'] = $this->record->updateRecordStatus($this->marshaler->unmarshalItem($result['Item'])['record']);

            try{
                $result = $this->dynamodb->putItem([
                    'TableName' => 'a_chars',
                    'Key' => $this->marshaler->marshalItem([
                        'user_id' => (int)$user_id,
                        'char_id' => (int)$char['char_id']
                    ]),
                    'Item' => $this->marshaler->marshalItem($char)
                ]);
            } catch (DynamoDbException $e) {
                echo $e->getMessage();
                return ['status: Failed to update Chardata', 500];
            }
        }

        #バトルデータ更新
        try{
            $result = $this->dynamodb->putItem([
                'TableName' => 'a_battles',
                'Key' => $this->marshaler->marshalItem([
                    'user_id' => (int)$user_id,
                    'battle_id' => (int)$data['battle_id']
                ]),
                'Item' => $this->marshaler->marshalItem($data),
            ]);
        } catch (DynamoDbException $e) {
            echo $e->getMessage();
            return ['status: Failed to update Battlelog', 500];
        }
        $response = [
            "is_win" => $data['is_win'],
            "get_item" => "",
            "money" => $user['money'],
            "prize" => 150,
            "chars" => $data['friend_position'],
            "obtained" => $data['obtained']['chars'],
        ];
        return array($response, 201);
    }
}
