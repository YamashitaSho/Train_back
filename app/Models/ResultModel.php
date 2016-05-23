<?php
namespace App\Models;

use Aws\DynamoDb\Marshaler;
use App\Services\Common\Record;
use App\Models\DynamoDBhandler;

class ResultModel extends DynamoDBhandler
{
    private $marshaler;
    public function __construct()
    {
        $this->marshaler = new Marshaler();
        $this->record = new Record();
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
        $this->putItem($put, 'Failed to update BattleLog');
        return ;
    }
}
