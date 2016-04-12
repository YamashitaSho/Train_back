<?php
namespace App\Services;

require '../vendor/autoload.php';
use Illuminate\Database\Eloquent\Model;

use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\Marshaler;
use App\Services\UserInfo;
use App\Services\Record;

class BattleLogic extends Model
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

    private function setRandom($number)
    {
        $result = mt_rand($number*7/8, $number*9/8);
        return $result;
    }

    private function setGainExp($enemy_position, $is_win){
        $gainexp = 0;
        $exp_win = $is_win ? 1 : 1/4;
        for ($i = 0; $i < count($enemy_position); $i++){
            $gainexp += $enemy_position[$i]['exp'] * $exp_win;
        }
        return $gainexp;
    }
    /**
    * [関数] 参戦キャラに経験値を適用したものを返す
    *
    */
    private function setParamObtained($friend_position, $gainexp){
        $response = $friend_position;
        #キャラごとに処理する
        for ($i = 0; $i < count($friend_position); $i++){
            $a_char = $response[$i];
            try {
                $result = $this->dynamodb->getItem([
                    'TableName' => 'chars',
                    'Key' => [
                        'char_id' => [
                            'N' => (string)$friend_position[$i]['char_id']
                        ]
                    ]
                ]);
            } catch (DynamoDbException $e) {
                echo $e->getMessage();
            }
            $char = $this->marshaler->unmarshalItem($result['Item']);
            $a_char['exp'] += $gainexp;
            while ($a_char['exp'] >= 100){
                $a_char['exp'] -= 100;
                $a_char['level']++;
                $a_char['status'] = $this->setLevelUp($a_char['status'], $char['status_growth_rate']);
            }
            $response[$i] = $a_char;
        }
        return $response;
    }
    /**
    * [関数] キャラのレベルが上がった時の処理
    *
    */
    private function setLevelUp($status, $growth_rate){
        $items = ['attack', 'endurance', 'agility', 'debuf'];
        foreach ($items as $item){
            $rand[$item] = mt_rand(1, 100);
            #echo 'g-rate:'.$growth_rate[$item].' status:'.$status[$item].' rnd:'.$rand[$item].'<br>';
            while( $growth_rate[$item] > 0 ){
                if ($rand[$item] <= $growth_rate[$item]){
                    $status[$item] ++;
                }
                $growth_rate[$item] -= 100;
            }
        }
        return $status;
    }
    public function setBattle($battle_id)
    {
        //該当バトルIDの戦闘を進行する。
        //戦闘初期状態を読み込む
        //該当バトルのステータスを進行中に変更し、キャラのステータスに反映する
        //該当バトルが進行中だった場合はバトル開始情報のみを送信する
        //終了済みだった場合はエラーを返す
        $user_id = $this->userinfo->getUserID();
        $user = $this->userinfo->getUserStatus($user_id);
        #battle_chars 0~2:味方 3~5:敵

        $get['TableName'] = 'a_battles';
        $get['Key'] = [
            'user_id' => [
                'N' => (string)$user_id
            ],
            'battle_id' => [
                'N' => (string)$user['battle_id']
            ]
        ];

        # 作成されているバトル情報の読み込み
        try {
            $result = $this->dynamodb->getItem($get);
        } catch (DynamoDbException $e) {
            echo $e->getMessage() . "\n";
            return ["status: Failed to Read BattleData", 500];
        }
        $response = $this->marshaler->unmarshalItem($result['Item']);

        # バトル進行状態のチェック
        if ($response['progress'] == 'created'){
            #created: 通常の進行
            $response['progress'] = "in_process";
        } else if ( $response['progress'] == 'in_process'){
            #in process: ログがすでにあるはずなのでデータをそのまま送信
            return [$response, 201];
        } else if ( $response['progress'] == 'closed'){
            #closed すでに結果表示を終えた戦闘なのでエラーを返す
            return ['status: Already Closed Battle', 400];
        }
        for ($i = 0; $i < 3; $i++){
            $battle_chars[$i] = $response['friend_position'][$i];
            $battle_chars[$i]['position'] = 'friend'.($i+1);
        }
        for ($i = 0; $i < 3; $i++){
            $battle_chars[$i+3] = $response['enemy_position'][$i];
            $battle_chars[$i+3]['position'] = 'enemy'.($i+1);
        }
        #    print_r($battle_chars);
        $a_turn = array(
            'turn' => 0,
            'is_last_turn' => false,
            'friend_hp' => 1000,
            'enemy_hp' => 1000
        );
        #初期HP設定
        foreach ($response['friend_position'] as $char){
            $a_turn['friend_hp'] += $char['status']['endurance'];
        }
        unset($char);
        foreach ($response['enemy_position'] as $char){
            $a_turn['enemy_hp'] += $char['status']['endurance'];
        }
        unset($char);

        $battle_log[0] = $a_turn;
        #戦闘処理
        #ターンループ カウント:$turn
        while($a_turn['is_last_turn'] == false){
            $a_turn['turn'] ++ ;
            if ($a_turn['turn'] == 5){
                $a_turn["is_last_turn"] = true;              #5ターン目ならば終了フラグを立てる
            }
            #このターンのみの一時的なステータスを(再)定義する
            $chars_turn = $battle_chars;

            #このターンにおける行動スピード、基礎ダメージ、デバフ能力を決定
            $count = 0;
            $sequence = [];                               #$sequenceは行動順管理用の配列
            foreach ($chars_turn as $char){
                $chars_turn[$count]['status']["attack"] = $this->setRandom($chars_turn[$count]['status']["attack"]);
                $chars_turn[$count]['status']["agility"] = $this->setRandom($chars_turn[$count]['status']["agility"]);
                $sequence[$count]["position"] = $count;
                $sequence[$count]["speed"] = $chars_turn[$count]['status']["agility"];
                $count++;
            }
            unset($char);
            #$sequenceを要素"speed"が大きい順にソートする(行動する順にpositionが入った配列になる)
            usort($sequence, function($a, $b){
                return $a["speed"] < $b["speed"];
            });
            unset($a_turn['action']);                                   #行動ログの初期化
            #各キャラの行動(キャラ数分行う どちらかのHPが0になった場合には抜ける)
            for ($action_count = 0; $action_count < count($chars_turn); $action_count++){

                #このターンのキャラクタデータ
                $position = $sequence[$action_count]['position'];       #現在行動しているポジション
                $active_char = $chars_turn[$position]['status'];        #行動するキャラのステータス
                $active_char['position'] = $chars_turn[$position]['position'];

                $action['position'] = $active_char['position'];
                $action["damage"] = $active_char['attack'];

                #友軍と敵軍の判定
                if (strstr($active_char['position'], 'friend')){
                    $target['hp'] = 'enemy_hp';
                    $target['position'] = 3;                            #敵キャラが入っているのは味方の3つ分先
                } else {
                    $target['hp'] = 'friend_hp';
                    $target['position'] = 0;
                }
                #攻撃処理
                $a_turn[$target['hp']] -= $action['damage'];
                if ($a_turn[$target['hp']] < 0) {
                    $a_turn[$target['hp']] = 0;
                }
                #デバフ処理
                for ($i=0 ; $i<3 ; $i++){
                    $debuf = $this->setRandom($active_char['debuf']/2);
                    $chars_turn[$i+$target['position']]['status']['attack'] -= $debuf;
                    if ($chars_turn[$i+$target['position']]['status']["attack"] < 0 ){
                        $chars_turn[$i+$target['position']]['status']['attack'] = 0;
                    }
                    $action['debuf'][$i] = $debuf;
                }
                $a_turn['action'][$action_count] = $action;
                #戦闘終了判定
                if ($a_turn[$target['hp']] <= 0){
                    $a_turn['is_last_turn'] = true;
                    break;
                }
            #    echo "turn:".$a_turn['turn'].", action:".$action_count.", FHP:".$a_turn['friend_hp'].", EHP:".$a_turn['enemy_hp']."\n";
            }
            #print_r($a_turn);
            $battle_log[$a_turn['turn']] = $a_turn;
        }
        #echo "turn:".$a_turn['turn'].", action:".$action_count.", FHP:".$a_turn['friend_hp'].", EHP:".$a_turn['enemy_hp']."\n";
        #勝敗は最終ターンのHPを比較して算出する
        $response['is_win'] = ($a_turn['friend_hp'] >= $a_turn['enemy_hp'] );

        #経験値獲得フェーズ
        # $obtained : 成果物 経験値、レベルアップパラメータ、アイテム
        # 'gainexp' : 獲得経験値 勝った場合は全額、 負けたら1/4
        # 'chars'   : 各キャラの獲得パラメータ(経験値追加後)
        $obtained['gainexp'] = $this->setGainExp($response['enemy_position'], $response['is_win']);
        $obtained['chars'] = $this->setParamObtained($response['friend_position'], $obtained['gainexp']);

        //データベースに書き込み
        $update_item = $response;
        $update_item['user_id'] = (int)$user_id;
        $update_item['battle_id'] = (int)$battle_id;
        $update_item['log'] = $battle_log;
        $update_item['obtained'] = $obtained;
        $update_item['record'] = $this->record->makeRecordStatus();
        $update = [
            'TableName' => 'a_battles',
            'Key' => $this->marshaler->marshalItem([
                'user_id' => (int)$user_id,
                'battle_id' => (int)$battle_id
            ]),
            'Item' => $this->marshaler->marshalItem($update_item),
        ];
        try {
            $result = $this->dynamodb->putItem($update);
        } catch (DynamoDbException $e) {
            echo "status:Failed to putItem:\n";
            echo $e->getMessage() . '\n';
        }

        return [$response, 201];
    }

    public function turnoverBattle($battle_id)
    {
        //進行中のバトルについて、戦闘終了情報が入力されるか5ターン経過するまでのデータを取得し、送信する。
        $user_id = $this->userinfo->getUserID();

        $get_item_array = [
            'TableName' => 'a_battles',
            'Key' => [
                'user_id' => [
                    'N' => (string)$user_id
                ],
                'battle_id' => [
                    'N' => (string)$battle_id
                ]
            ],
            'ProjectionExpression' => '#log, progress',
            'ExpressionAttributeNames' => [
                '#log' => 'log'
            ]
        ];
        try {
            $result = $this->dynamodb->getItem($get_item_array);
        } catch (DynamoDbException $e) {
            echo $e->getMessage() . '\n';
            return ['status: Failed to get BattleLog', 500];
        }
        if ($result['Item']['progress']['S'] == 'in_process'){
            $response = $this->marshaler->unmarshalItem($result['Item'])['log'];
        } else {
            return ['status: Battle is NOT in Process', 400];
        }
    return array($response, 200);
    }
}
