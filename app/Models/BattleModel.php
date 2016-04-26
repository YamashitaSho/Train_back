<?php
namespace App\Models;

require '../vendor/autoload.php';
use Illuminate\Database\Eloquent\Model;

use Aws\DynamoDb\Marshaler;
use App\Models\DynamoDBHandler;
use App\Services\Common\Record;

class BattleModel extends DynamoDBHandler
{
    public function __construct()
    {
        $this->record = new Record();
        $this->marshaler = new Marshaler();

        parent::__construct();
    }
    /**
    * パーティキャラを読み込む
    * $charsの各要素に対して'char_id'に対応するデータを読み込む
    */
    public function getPartyChar($chars)
    {
        $key = [];
        foreach ($chars as $char){
            $key[] = [
                'char_id' => [
                    'N' => (string)$char['char_id']
                ]
            ];
        }
        $get = [
            'RequestItems' => [
                'chars' => [
                    'Keys' => $key,
                    'ProjectionExpression' => 'char_id, status_growth_rate'
                ]
            ]
        ];
        $chars_master = $this->batchGetItem($get, 'Failed to read CharData(Master)');
        return $chars_master['chars'];
    }
    /**
    * バトル情報の読み込み
    */
    public function getBattle($user)
    {
        $get['TableName'] = 'a_battles';
        $get['Key'] = [
            'user_id' => [
                'N' => (string)$user['user_id']
            ],
            'battle_id' => [
                'N' => (string)$user['battle_id']
            ]
        ];
        $battle = $this->getItem($get, ['Failed to read BattleData']);
        return $battle;
    }
    public function writeBattle($user, $battle)
    {
        $battle['user_id'] = (int)$user['user_id'];
        $battle['battle_id'] = (int)$user['battle_id'];
        $battle['record'] = $this->record->updateRecordStatus($battle['record']);
        $item = $battle;
        $key = [
            'user_id' => [
                'N' => (string)$user['user_id']
            ],
            'battle_id' => [
                'N' => (string)$user['battle_id']
            ],
        ];
        $put = [
            'TableName' => 'a_battles',
            'Key' => $key,
            'Item' => $this->marshaler->marshalItem($item),
        ];
        $result = $this->putItem($put, ['Failed to write BattleData']);
        return ;
    }
}
