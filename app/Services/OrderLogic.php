<?php
namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use App\Models\OrderModel;
use App\Models\UserModel;

class OrderLogic extends Model
{
    private $REQUEST_TYPE_UNDEFINED = 0;
    private $REQUEST_TYPE_CHAR = 1;
    private $REQUEST_TYPE_ITEM = 2;

    private $UNSET_ID = 0;

    private $ERROR_TYPE_UNDEFINED = ['Request Type Error', 400];
    private $ERROR_JSON_UNAVAILABLE = ['JSON Error', 400];
    private $ERROR_SLOT_UNAVAILABLE = ['Slot Unavailable', 400];


    public function __construct($user_id)
    {
        $this->order = new OrderModel();
        $this->userinfo = new UserModel($user_id);
    }


    /**
    * [API] 編成画面情報取得APIで呼ばれる関数
    *
    */
    public function getOrder()
    {
        #ユーザー情報を読み込む
        $user = $this->userinfo->getUser();
        #所持キャラ
        $chars = $this->order->readChar($user['user_id']);
        if (!empty($chars)) {
            #所持キャラのマスタ
            $chars_master = $this->order->readCharMaster($chars);
            #キャラデータをマスタと統合
            $chars = $this->combineCharData($chars, $chars_master);
        }
        #アイテムデータ
        $items = $this->order->readItem($user['items']);

        if (empty($user['party'])){
            $user['party'] = [['char_id' => 0],['char_id' => 0],['char_id' => 0]];
        }
        $response = [
            'party' => $user['party'],
            'chars' => $chars,
            'items' => $items,
        ];
        return [$response,200];
    }


    /**
    * [API] 編成の入れ替えを実行する関数
    *
    */
    public function changeOrder($type)
    {
        #JSONリクエスト
        $request = \Request::all();
        $response = [];
        #ユーザー情報を読み込む
        $user = $this->userinfo->getUser();
        #URIのリクエストタイプを判別

        $request_type = $this->checkType($type);
        #リクエストJSONの正当性を判断

        $response = $this->validateRequest($request);
        #正当な場合は$responseが空配列
        if (empty($response)){
            switch ($request_type) {
                case $this->REQUEST_TYPE_ITEM:
                    $response = $this->changeItem($request, $user);
                    break;
                case $this->REQUEST_TYPE_CHAR:
                    $response = $this->changeChar($request, $user);
                    break;
                case $this->REQUEST_TYPE_UNDEFINED:
                    $response = $this->ERROR_TYPE_UNDEFINED;
                    break;
            }
        }
        return $response;
    }


    /**
    * [関数] リクエストのタイプを判別する
    *
    * @return $request_type : $REQUEST_TYPE_HOGEのいずれか
    * $REQUEST_TYPE_HOGE : 0 or 1 or 2
    */
    private function checkType($type)
    {
        $request_type = $this->REQUEST_TYPE_UNDEFINED;
        if ($type == 'item'){
            $request_type = $this->REQUEST_TYPE_ITEM;
        } else if ($type == 'char'){
            $request_type = $this->REQUEST_TYPE_CHAR;
        }
        return $request_type;
    }


    private function validateRequest($request)
    {
        $response = [];
        do {
            if ( !isset($request['slot'], $request['new_id']) ){
                $response = $this->ERROR_JSON_UNAVAILABLE;
                break;
            }
            if ($request['slot'] < 0 | $request['slot'] > 2) {
                $response = $this->ERROR_SLOT_UNAVAILABLE;
                break;
            }
        } while (false);
        return $response;
    }


    /**
    * [関数] トランとマスタのキャラデータを統合する
    */
    private function combineCharData($chars, $chars_master)
    {
        $chars_combine = [];
        $key = 0;
        foreach ($chars as $char){
            $char_combine = $char;
            foreach ($chars_master as $char_master){
                if ($char_master['char_id'] == $char['char_id']){
                    $char_combine += $char_master;
                    break;
                }
            }
            $chars_combine[] = $char_combine;
        }
        return $chars_combine;
    }


    /**
    * [関数] アイテム交換を実行できるかどうかを判定し、実行する 。
    *
    * @return 成功 or 失敗
    */
    private function changeItem($request, $user)
    {
        $type = 'item_id';
                #編成解除リクエスト
        if ($this->isAlreadyOrdered($user['party'], $request, $type)){
            $request['new_id'] = $this->UNSET_ID;
        }


        #所持チェック
        if ( !$this->isPossess($user['items'], $request['new_id'], $type) ){
            return ['Item is not Possessed', 400];
        }
        #重複チェック
        if ( !$this->isDuplicate($user['party'], $request['new_id'], $type) ){
            return ['Item is already Ordered', 400];
        }
        #隊列入れ替え、書き込み
        $user['party'][$request['slot']][$type] = $request['new_id'];
        $this->order->updateUser($user);

        return [$request, 201];
    }


    /**
    * [関数] キャラ交換を実行できるかどうかを判定し、実行する 。
    *
    * @return $request, HTTP_STATUS_CODE
    */
    private function changeChar($request, $user)
    {
        $type = 'char_id';

                #編成解除リクエスト
        if ($this->isAlreadyOrdered($user['party'], $request, $type)){
            $request['new_id'] = $this->UNSET_ID;
        }

        $response = [$request, 201];

        #キャラの所持チェック
        #ユーザーが保持しているキャラ情報を読み込む(char_idのみ[第2引数で指定]
        $chars = $this->order->readChar($user['user_id'], true);
        do {
            #所持チェック
            if ( !$this->isPossess($chars, $request['new_id'], $type) ){
                $response = ['Character is not Possessed', 400];
                break;
            }
            #重複チェック
            if ( !$this->isDuplicate($user['party'], $request['new_id'], $type) ){
                $response = ['Character is already Ordered', 400];
                break;
            }
            #隊列入れ替え、書き込み
            $user['party'][$request['slot']][$type] = $request['new_id'];
            $this->order->updateUser($user);
        } while (false);

        return $response;
    }


    /**
     * [Method] キャラ編成解除処理
     * 編成しようとしているキャラがすでに指定スロットに編成されている場合はtrueを返す
     * (この結果を受けて編成を解除するリクエストに変更する)
     */
    private function isAlreadyOrdered($party, $request, $type)
    {
        $response = false;
        if ($party[$request['slot']][$type] == $request['new_id']){
            $response = true;
        }

        return $response;
    }


    /**
     * [Method] item(キャラ、アイテム)の所持を返す
     *
     * @return boolean true : 持っている
     */
    private function isPossess($order, $new_id, $type)
    {
        $response = false;
        foreach ($order as $item){
            if ( ($item[$type] == $new_id) || ($new_id == $this->UNSET_ID) ){
                $response = true;
                break;
            }
        }
        return $response;
    }


    /**
     * [Method] item(キャラ、アイテム)がすでに登録されていないかを返す
     *
     * UNSET_ID については重複していても登録できる。(未設定のID)
     * @return boolean 登録されていない: true
     */
    private function isDuplicate($party, $new_id, $type)
    {
        $response = true;
        foreach ($party as $item){
            if ( ($item[$type] == $new_id) && ($new_id != $this->UNSET_ID)) {
                $response = false;
                break;
            }
        }
        return $response;
    }
}
