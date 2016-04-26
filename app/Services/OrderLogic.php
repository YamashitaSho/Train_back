<?php
namespace App\Services;

require '../vendor/autoload.php';
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

use App\Services\Common\UserInfo;
use App\Services\Common\Record;
use Aws\DynamoDb\Marshaler;
use App\Services\Common\DynamoDBHandler;

class OrderLogic extends Model
{
    public function __construct()
    {
        $this->marshaler = new Marshaler();
        $this->userinfo = new UserInfo();
        $this->record = new Record();
        $this->dynamo = new DynamoDBHandler();
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

        $chars = $this->readChar($user_id);
        $chars_master = $this->readCharMaster($chars);
        $chars = $this->combineCharData($chars, $chars_master);        #キャラデータ

        $items = $this->readItemMaster($user['items']);#アイテムデータ

        $response = [
            'party' => $user['party'],
            'chars' => $chars,
            'items' => $items,
        ];
        return [$response,200];
    }

    /**
    * [関数] 所持しているキャラの読み込み
    *
    * 読み込むデータ: char_id, level, exp, status
    * $idonly に trueが指定された場合、 読み込むデータは char_id のみ
    */
    private function readChar($user_id, $idonly = false)
    {
        $eav = $this->marshaler->marshalJson('
            {
                ":user_id": '.$user_id.'
            }
        ');
        $query = [
            'TableName' => 'a_chars',
            'KeyConditionExpression' => 'user_id = :user_id',
            'ExpressionAttributeValues' => $eav,
        ];

        if ($idonly) {
            $query['ProjectionExpression'] = 'char_id';
        } else {
            $query['ProjectionExpression'] = 'char_id, exp, #lv, #st';
            $query['ExpressionAttributeNames'] = [
                '#lv' => 'level',
                '#st' => 'status',
            ];
        }

        $chars = $this->dynamo->queryItem($query, 'Failed to get CharData');
        return $chars;
    }

    /**
    * [関数] 所持しているキャラのマスターデータを読み込む。
    *
    * 読み込むデータ: char_id, name, status_max
    */
    private function readCharMaster($chars)
    {
        for ($i = 0; $i < count($chars); $i++){
            $key[$i] = [
                'char_id' => [
                    'N' => (string)$chars[$i]['char_id']
                ]
            ];
        }
        $get = [
            'RequestItems' => [
                'chars' => [
                    'Keys' => $key,
                    'ProjectionExpression' => 'char_id, #nm, status_max',
                    'ExpressionAttributeNames' => [
                        '#nm' => 'name'
                    ]
                ]
            ]
        ];
        $chars_master = $this->dynamo->batchGetItem($get, 'Failed to read CharData(Master)');
        return $chars_master['chars'];
    }
    /**
    * [関数] トランとマスタのキャラデータを統合する
    *
    * $chars と $chars_masterの要素数は同じであること
    */
    private function combineCharData($chars, $chars_master)
    {
        $chars_combine = [];
        $key = 0;
        for ($i = 0; $i < count($chars); $i++){
            $chars_combine[$i] = $chars[$i];
            for ($j = 0; $j < count($chars_master); $j++){
                if ($chars_master[$j]['char_id'] == $chars_combine[$i]['char_id']){
                    $key = $j;
                    break;
                }
            }
            $chars_combine[$i] += $chars_master[$key];
        }
        return $chars_combine;
    }
    /**
    * [関数] アイテムのマスターデータを読み込む
    *
    * 読み込むデータ : item_id, name, text, status
    */
    private function readItemMaster($items)
    {
        for ($i = 0; $i < count($items); $i++){
            $key[$i] = [
                'item_id' => [
                    'N' => (string)$items[$i]['item_id']
                ]
            ];
        }
        $get = [
            'RequestItems' => [
                'items' => [
                    'Keys' => $key,
                    'ProjectionExpression' => 'item_id, #nm, #txt, #st',
                    'ExpressionAttributeNames' => [
                        '#nm' => 'name',
                        '#txt' => 'text',
                        '#st' => 'status'
                    ]
                ]
            ]
        ];
        $items_master = $this->dynamo->batchGetItem($get, 'Failed to read ItemData');
        return $items_master['items'];
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

        #リクエストJSONに slot と new_id が入っているかチェック
        if ( !isset($request['slot'], $request['new_id']) ){
            return ['status: body undefined', 400];
        }
        #編成スロットが正しいかのチェック
        if ($request['slot'] < 0 | $request['slot'] > 2) {
            return ['status: Unavailable Slot', 400];
        }

        if ($type == 'item'){
            return $this->changeItem($request, $user);  #アイテムの交換
        } elseif ($type == 'char'){
            return $this->changeChar($request, $user);  #キャラの交換
        }
        #else URIに正しいタイプ宣言が入っていない
        return ['status: type undefined', 400];
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
        foreach ($user['items'] as $user_item) {
            if ($user_item['item_id'] == $request['new_id']){
                $exist = true;
            }
        }
        if ($exist == false){
            return ["status: item is not possessed", 400];
        }
        #編成の重複チェック
        for ($i = 0; $i < 3; $i++) {
            if ($user['party'][$i]['item_id'] == $request['new_id']) {
                return ["status: item is already ordered", 400];
            }
        }
        #チェックが完了したのでアイテムを入れ替え、書き込む
        $user['party'][$request['slot']]['item_id'] = $request['new_id'];
        $this->updateUser($user);

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
        $chars = $this->readChar($user['user_id'], true);      # 第2引数で読み込む項目をIDに限定
        $exist = false;
        foreach ($chars as $char) {
            if ( $char['char_id'] == $request['new_id']){
                $exist = true;
            }
        }
        if ($exist == false){
            return ["status: Character is not Possessed", 400];
        }
        #編成の重複チェック
        for ($i = 0; $i < 3; $i++) {
            if ($user['party'][$i]['char_id'] == $request['new_id']) {
                return ["status: character is already ordered", 400];
            }
        }
        #チェックが完了したのでキャラクタを入れ替え、書き込む
        $user['party'][$request['slot']]['char_id'] = $request['new_id'];
        $this->updateUser($user);

        return [$request, 201];
    }

    /**
    * ユーザー情報の更新
    *
    * RecordStatus を更新し、 usersテーブルに書き込む
    */
    private function updateUser($user)
    {
        $user['record'] = $this->record->updateRecordStatus($user['record']);
        $key = [
            'user_id' => $user['user_id']
        ];
        $put = [
            'TableName' => 'a_users',
            'Key' => $this->marshaler->marshalItem($key),
            'Item' => $this->marshaler->marshalItem($user),
        ];
        $result = $this->dynamo->putItem($put, 'Failed to Write UserData');
        return;
    }
}
