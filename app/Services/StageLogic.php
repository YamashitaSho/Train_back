<?php
namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use App\Services\Common\UserInfo;
use App\Models\StageModel;


/**
 * [Class] アリーナ実行クラス
 */
class StageLogic extends Model
{
    public function __construct()
    {
        $this->stage = new StageModel();
        $this->userinfo = new UserModel();
    }


    /**
     * [Method] 参加できるアリーナのリストとユーザーのパーティ情報を返す
     */
    public function getBattlelist()
    {
        $user = $this->userinfo->getUser();
        #キャラデータの読み込み
        $chars = $this->stage->readCharInParty($user);
        #アリーナデータの読み込み
        $response = [
            "money" => $user['money'],
            "chars" => $chars,
            "stages" => [
                "stage_id" => 3,
                "title" => "初級",
                "entry_fee" => 100,
                "prize" => 200,
                "item_name" => "初級者卒業",
                "clearcount" => 2
            ]
        ];
        return [$response,200];
    }


    /**
     * [Method] アリーナに登録されたバトルを発行する
     */
    public function joinBattle()
    {
        $json_string = file_get_contents("php://input");

//      echo $json_string;
        $request = json_decode($json_string,true);
//      var_dump($request);
//      echo "stage_id:".$request["stage_id"];

        //$request["stage_id"]を用いてDBに問い合わせ、バトルを実行、battleIDを発行する

        $response = array(
            "battle_id" => 1
        );
        return [$response,201];
    }
}
