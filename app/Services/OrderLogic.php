<?php
namespace App\Services;

require '../vendor/autoload.php';
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\Marshaler;

class OrderLogic extends Model
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
    * [API] 編成画面情報取得APIで呼ばれる関数
    *
    */
    public function getOrder()
    {
        #ユーザー情報を読み込む
        $user_id = $this->userinfo->getUserID();
        $user = $this->userinfo->getUserStatus($user_id);

        #パーティ情報の読み込み
        $party = $user['party'];

        #手持ちのキャラ情報の読み込み(char_id, exp, level, attack, endurance, agility, debuf)
        $eav = $this->marshaler->marshalJson('
            {
                ":user_id":
                '.$user_id.'
            }
        ');
        try {
            $result = $this->dynamodb->query([
                'TableName' => 'a_chars',
                'KeyConditionExpression' => 'user_id = :user_id',
                'ProjectionExpression' => 'char_id, exp, #lv, #st',
                'ExpressionAttributeValues' => $eav,
                'ExpressionAttributeNames' => [
                    '#lv' => 'level',
                    '#st' => 'status',
                ]
            ]);
        } catch (DynamoDbException $e) {
            echo "ユーザーのキャラ情報を取得できませんでした。\n";
            echo $e->getmessage() . "\n";
            $result = ['Count' => 0];
        }
        for ($i = 0; $i < $result['Count']; $i++){
            $char_id = $result['Items'][$i]['char_id']['N'];
            $chars[$char_id] = $this->marshaler->unmarshalItem($result['Items'][$i]);
        }
        #キャラマスターデータ読み込み
        try {
            $result = $this->dynamodb->scan([
                'TableName' => 'chars',
                'ProjectionExpression' => 'char_id, #nm, status_max',
                'ExpressionAttributeNames' => [
                    '#nm' => 'name',
                ],
            ]);
        } catch (DynamoDbException $e) {
            echo "キャラのマスターデータの読み込みに失敗しました\n";
            echo $e->getMessage() . "\n";
        }
        for ($i = 0; $i < $result['Count']; $i++){
            $char_id = $result['Items'][$i]['char_id']['N'];
            if (array_key_exists($char_id, $chars)){
                $chars[$char_id] += $this->marshaler->unmarshalItem($result['Items'][$i]);
            }
        }

        #アイテムマスターデータ読み込み
        try {
            $result = $this->dynamodb->scan([
                'TableName' => 'items',
                'ProjectionExpression' => 'item_id, #nm, #txt, #st',
                'ExpressionAttributeNames' => [
                    '#nm' => 'name',
                    '#txt' => 'text',
                    '#st' => 'status',
                ],
            ]);
        } catch (DynamoDbException $e) {
            echo "アイテムのマスターデータの読み込みに失敗しました\n";
            echo $e->getMessage() . "\n";
        }
        for ($i = 0; $i < $result['Count']; $i++){
            $item_id = $result['Items'][$i]['item_id']['N'];
            $items[$item_id] = $this->marshaler->unmarshalItem($result['Items'][$i]);
        }

        $response = array(
            "party" => $party,
            "chars" => $chars,
            "items" => $items,
        );
        return array($response,200);
    }

    /**
    * [API] 編成の入れ替えを実行する関数
    *
    */
    public function changeOrder($type)
    {
        $request = \Request::all();
        #ユーザー情報を読み込む
        $user_id = $this->userinfo->getUserID();
        $user = $this->userinfo->getUserStatus($user_id);

        #リクエストJSONが入っているかチェック
        if ( !isset($request['slot'], $request['new_id']) ){
            return array("status: body undefined", 400);
        }
        #パーティ情報の読み込み
        $party = $user['party'];
        $in_use = true;          //使用可能フラグ 使用可:true

        if ($type == "item"){
            return $this->changeItem($request, $user);  #アイテムの交換
        } elseif ($type == "char"){
            return $this->changeChar($request, $user);  #キャラの交換
        }

        #URIに正しいタイプ宣言が入っていない
        return array("status: type undefined", 400);
    }

    /**
    * [関数] アイテム交換を実行できるかどうかを判定し、実行する 。
    *
    * @return 成功 or 失敗
    */
    private function changeItem($request, $user)
    {
        #アイテムの所持チェック
        $exist = false;
        foreach($user['items'] as $user_item) {
            if ($user_item['item_id'] == $request['new_id']){
                $exist = true;
            }
        }
        if ($exist == false){
            return array("status: item is not possessed", 400);
        }

        #編成の重複チェック
        for ($i = 0; $i < 3; $i++) {
            if ($user['party'][$i]['item_id'] == $request['new_id']) {
                return array("status: item is already ordered", 400);
            }
        }

        #チェックが完了したのでアイテムを入れ替える
        #書き込み先はユーザー情報のみ
        $user['party'][$request['slot']]['item_id'] = $request['new_id'];

        # ユーザー情報書き込み
        $user['record'] = $this->record->updateRecordStatus($user['record']);
        $put_item = $user;
        $put_key = [
            'user_id' => $user['user_id']
        ];
        $put_user = [
            'TableName' => 'a_users',
            'Key' => $this->marshaler->marshalItem($put_key),
            'Item' => $this->marshaler->marshalItem($put_item),
        ];

        try {
            $result = $this->dynamodb->putItem($put_user);
        } catch (DynamoDbException $e) {
        #    echo "ユーザーデータの書き込みに失敗:\n";
        #    echo $e->getMessage() . '\n';
            return ["status: Failed to write UserData", 500];
        }

        return [$request, 201];
    }

    /**
    * [関数] キャラ交換を実行できるかどうかを判定し、実行する 。
    *
    * @return 成功 or 失敗
    */
    private function changeChar($request, $user)
    {
        #キャラの所持チェック
        #ユーザーが保持しているキャラ情報を読み込む(char_idのみ)
        $eav = $this->marshaler->marshalJson('
            {
                ":user_id": '.$user['user_id'].'
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

        $exist = false;
        for ($i = 0; $i < $result["Count"]; $i++){
            if ( $result['Items'][$i]['char_id']['N'] == $request['new_id']){
                $exist = true;
            }
        }
        if ($exist == false){
            return array("status: character is not possessed", 400);
        }

        #編成の重複チェック
        for ($i = 0; $i < 3; $i++) {
            if ($user['party'][$i]['char_id'] == $request['new_id']) {
                return array("status: character is already ordered", 400);
            }
        }

        #チェックが完了したのでキャラクタを入れ替える
        #書き込み先はユーザー情報のみ
        $user['party'][$request['slot']]['char_id'] = $request['new_id'];

        # ユーザー情報書き込み
        $user['record'] = $this->record->updateRecordStatus($user['record']);
        $put_item = $user;
        $put_key = [
            'user_id' => $user['user_id']
        ];
        $put_user = [
            'TableName' => 'a_users',
            'Key' => $this->marshaler->marshalItem($put_key),
            'Item' => $this->marshaler->marshalItem($put_item),
        ];

        try {
            $result = $this->dynamodb->putItem($put_user);
        } catch (DynamoDbException $e) {
        #    echo "ユーザーデータの書き込みに失敗:\n";
        #    echo $e->getMessage() . '\n';
            return array("status: Failed to write UserData", 500);
        }
        return array($request, 201);
    }
}
