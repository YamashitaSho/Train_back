<?php
namespace App\Services;

require '../vendor/autoload.php';
use Illuminate\Database\Eloquent\Model;

use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\Marshaler;

class GachaLogic extends Model
{
    private $dynamodb;
    private $marshaler;

    #残りキャラに応じた代金の変化
    private $prices = [
        -1,
        10000,
        5000,
        2000,
        1000,
        500,
        300,
        200
    ];
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
    * [API] ガチャが引ける状態かチェックするAPIで呼び出される関数
    *
    * @return array $response チェック結果
    */
    public function checkGacha()
    {
        //ガチャが引ける状態かどうかチェックし、情報を送信する
        #ユーザー情報を読み込む
        $user_id = $this->userinfo->getUserID();
        $user = $this->userinfo->getUserStatus($user_id);

        #重み情報を読み込む
        # $weights = array( 'char_id' => 'weight' ) の形式
        try {
            $result = $this->dynamodb->scan([
                'TableName' => 'chars',
                'ProjectionExpression' => 'char_id, weight',
            ]);
        } catch (DynamoDbException $e) {
            echo "重み情報が読み込めませんでした。\n";
            echo $e->getMessage() . "\n";
        }
        for ($i = 0; $i < $result["Count"]; $i++){
            # $weights[char_id] = (int)weight となるように代入している
            $weights[$result['Items'][$i]['char_id']['N']] = (int)$result['Items'][$i]['weight']['N'];
        }



        #ユーザーが保持しているキャラ情報を読み込む(char_idのみ)
        $eav = $this->marshaler->marshalJson('
            {
                ":user_id": '.$user_id.'
            }
        ');
        try {
            $result = $this->dynamodb->query([
                'TableName' => 'a_chars',
                'KeyConditionExpression' => 'user_id = :user_id',
                'ProjectionExpression' => 'char_id',
                'ExpressionAttributeValues' => $eav
                ]);
        } catch (DynamoDbException $e) {
            echo "ユーザーのキャラ情報を取得できませんでした。\n";
            echo $e->getmessage() . "\n";
        }
        # $own_char : 所持キャラのchar_idのリスト
        # 所持しているキャラについてガチャの重みを0にしていく
        for ($i = 0; $i < $result["Count"]; $i++){
            $own_char[$i] = (int)$result['Items'][$i]['char_id']['N'];
            $weights[$own_char[$i]] = 0;
        }

        # $rest_char : 重みが0でないキャラの数
        $rest_char = 0;
        for ($i = 0; $i < count($weights); $i++){
            if ($weights[$i] > 0){
                $rest_char ++;
            }
        }

        # $gacha_cost : ガチャの料金
        if ($rest_char >= count($this->prices)){
            $gacha_cost = $this->prices[count($this->prices) - 1];
        } else {
            $gacha_cost = $this->prices[$rest_char];
        }

        # $availability : ガチャの可否
        $availability = ($user['money'] >= $gacha_cost);
        if ($rest_char == 0){
            $availability = false;
        }

        $response = array(
            "availability" => $availability,
            "rest_char" => $rest_char,
            "gacha_cost" => $gacha_cost,
            "money" => $user['money']
        );
        return array($response,200);
    }

    /**
    * [API] ガチャを引くAPIで呼び出される関数
    *
    */
    public function drawGacha()
    {
        #ユーザー情報の取得
        $user_id = $this->userinfo->getUserID();
        $user = $this->userinfo->getUserStatus($user_id);
        try {
            $result = $this->dynamodb->scan([
                'TableName' => 'chars',
                'ProjectionExpression' => 'char_id, weight',
            ]);
        } catch (DynamoDbException $e) {
            echo "重み情報が読み込めませんでした。\n";
            echo $e->getMessage() . "\n";
        }
        for ($i = 0; $i < $result["Count"]; $i++){
            # $weights[char_id] = (int)weight となるように代入している
            $weights[$result['Items'][$i]['char_id']['N']] = (int)$result['Items'][$i]['weight']['N'];
        }


        #ユーザーが保持しているキャラ情報を読み込む(char_idのみ)
        $eav = $this->marshaler->marshalJson('
            {
                ":user_id": '.$user_id.'
            }
        ');
        try {
            $result = $this->dynamodb->query([
                'TableName' => 'a_chars',
                'KeyConditionExpression' => 'user_id = :user_id',
                'ProjectionExpression' => 'char_id',
                'ExpressionAttributeValues' => $eav
                ]);
        } catch (DynamoDbException $e) {
            echo "ユーザーのキャラ情報を取得できませんでした。\n";
            echo $e->getmessage() . "\n";
        }
        # $own_char : 所持キャラのchar_idのリスト
        # 所持しているキャラについてガチャの重みを0にしていく
        for ($i = 0; $i < $result["Count"]; $i++){
            $own_char[$i] = (int)$result['Items'][$i]['char_id']['N'];
            $weights[$own_char[$i]] = 0;
        }

        # $rest_char : 重みが0でないキャラの数
        # $weight_sum : 重みの合計
        $rest_char = 0;
        $weight_sum = 0;
        for ($i = 0; $i < count($weights); $i++){
            if ($weights[$i] > 0){
                $rest_char ++;
                $weight_sum += $weights[$i];
            }
        }

        # $gacha_cost : ガチャの料金
        if ($rest_char >= count($this->prices)){
            $gacha_cost = $this->prices[count($this->prices) - 1];
        } else {
            $gacha_cost = $this->prices[$rest_char];
        }

        # $availability : ガチャの可否
        $availability = ($user['money'] >= $gacha_cost);
        if ($rest_char == 0){
            $availability = false;
        }

        if (!$availability){
            return array('Status: Not available gacha', 400);
        }

        # $gacha_result : ガチャの結果
        $gacha_result = mt_rand(0, $weight_sum);
        $prize_char = array();

        # $prize_char : 獲得キャラのchar_id
        for ($i = 0; $gacha_result > 0 ; $i++){
            $prize_char['char_id'] = $i;
            $gacha_result -= $weights[$i];
        }

        $get['TableName'] = 'chars';
        $get['Key']['char_id'] = array('N' => (string)$prize_char['char_id']);
        try {
            $result = $this->dynamodb->getItem($get);
        } catch (DynamoDbException $e) {
            echo "キャラクターを取得することができませんでした。:\n";
            echo $e->getMessage() . "\n";
            $result = array("Unable to get UserStatus");
        }
        # $prize_char : 獲得キャラ
        $prize_char = $this->marshaler->unmarshalItem($result['Item']);
        #料金徴収
        $user['money'] -= $gacha_cost;

        # 獲得キャラデータを書き込み(新規)
        $put_item = [
            'exp' => $prize_char['exp'],
            'level' => $prize_char['level'],
            'status' => $prize_char['status'],
            'user_id' => $user_id,
            'char_id' => $prize_char['char_id'],
            'record' => $this->record->makeRecordStatus()
        ];
        $put_key = [
            'user_id' => $put_item['user_id'],
            'char_id'=> $put_item['char_id']
        ];
        $put_char = [
            'TableName' => 'a_chars',
            'Key' => $this->marshaler->marshalItem($put_key),
            'Item' => $this->marshaler->marshalItem($put_item),
        ];

        try {
            $result = $this->dynamodb->putItem($put_char);
        } catch (DynamoDbException $e) {
            echo "キャラデータの書き込みに失敗:\n";
            echo $e->getMessage() . '\n';
        }

        # ユーザー情報書き込み
        $user['record'] = $this->record->updateRecordStatus($user['record']);
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

        $response = array(
            "char_id" => $prize_char['char_id'],
            "name" => $prize_char['name'],
            "attack" => $prize_char['status']['attack'],
            "endurance" => $prize_char['status']['endurance'],
            "agility" => $prize_char['status']['agility'],
            "debuf" => $prize_char['status']['debuf']
        );

        return array($response,201);
    }
}
