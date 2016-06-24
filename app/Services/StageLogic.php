<?php
namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use App\Models\StageModel;
use App\Models\EnemyLoader;
use App\Models\EnemyPartyLoader;
use Illuminate\Http\Request;


/**
 * [Class] アリーナ実行クラス
 */
class StageLogic extends Model
{
    public function __construct($user_id)
    {
        $this->stage = new StageModel($user_id);
        $this->enemy = new EnemyLoader();
        $this->enemyparty = new EnemyPartyLoader();
    }


    /**
     * [GET] 参加できるアリーナのリストとユーザーのパーティ情報を返す
     */
    public function getBattlelist()
    {
        $user = $this->stage->getUser();
        #キャラデータの読み込み
        $chars = $this->stage->readCharInParty($user);
        #アリーナクリア情報のチェック
        if (!isset($user['arena'])){
            #空だった場合は未クリアとみなす
            $user['arena'] = 0;
        }
        $arena_index = [0];
        for ($i = 0; $i < $user['arena']; $i++){
            $arena_index[] = $i+1;
        }
        #アリーナデータの読み込み
        $arenas = $this->stage->getArenas($arena_index);
        #返信するデータに変形
        $stages = [];
        foreach($arenas as $key => $arena){
            $stages[$key] = [
                $arena['arena']+$arena['entry_fee']
            ];
        }
        $response = [
            "money" => $user['money'],
            "chars" => $chars,
            "stages" => $stages
        ];
        return [$response,200];
    }


    /**
     * [POST] アリーナに登録されたバトルを発行する
     */
    public function joinBattle()
    {
        $user = $this->stage->getUser();
        #進行中のバトルがないかを検証
        if ($this->canMakeBattle($user)){
            #ユーザーのアリーナクリア状況のバリデーション
            if (!isset($user['arena'])){
                $user['arena'] = 0;
            }
            #バトルIDをインクリメント
            $user['battle_id']++;

            #リクエストの取得
            $request = \Request::all();
            #リクエストのバリデーション
            if (isset($this->request['arena_id'])){
                if ($this->request['arena_id'] > $user['arena']){
                    //Bad Request
                }
            }
            #進行するアリーナの取得
            $arena = $this->stage->getArena($request['arena_id']);
            #味方キャラの取得
            $friends = $this->stage->readCharInParty($user);
            #敵キャラの取得
            $enemies = $this->stage->getEnemies($arena['arena']['enemyparty_id']);
            $this->stage->transBattle($user, $friends, $enemies, $arena, 'arena0');
        }
        $response = [
            "battle_id" => $user['battle_id']
        ];
        return [$response, 201];
    }


    /**
     * [Method] クエストが実行できる状態かを返す
     * @return boolean 実行できる : true
     */
    private function canMakeBattle($user)
    {
        $res = true;
        if ($user['battle_id'] != 0){
            $battle = $this->stage->readBattle($user);
            if ($battle['progress'] != 'closed'){
                $res = false;
            }
        }
        return $res;
    }

}