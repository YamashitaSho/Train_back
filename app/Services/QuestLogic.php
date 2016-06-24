<?php
namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use App\Models\QuestModel;


/**
 * [Class] クエスト実行クラス
 * バトルを発行し、バトルの実処理はBattleクラスに移譲する
 */
class QuestLogic extends Model
{
    public function __construct($user_id)
    {
        $this->model = new QuestModel($user_id);
    }


    /**
     * [API] 現在のパーティキャラを取得する関数
     */
    public function getParty()
    {
        $user = $this->model->getUser();
        $chars = $this->model->readCharInParty($user);
        $response = [$chars, 200];
        return $response;
    }


    /**
    * [API] クエストコマンドからのバトルを作成する関数
    *
    * バトル作成可能条件: ユーザーデータで指定されるバトルデータがclosedであること(created, in processの場合は作成せずにすでにある番号を渡す)
    */
    public function joinQuest()
    {
        $user = $this->model->getUser();

        if ($this->canMakeBattle($user)){       #バトル作成可能条件の確認
            #バトルIDを一つ進める
            $user['battle_id'] ++;
            $enemyparty = $this->getQuestEnemy();
            $enemy_position = $this->model->readEnemy($enemyparty['party']);
            $friend_position = $this->model->readCharInParty($user);
            #ユーザーデータの書き込み(トランザクションに要変更)
            $success = $this->model->postBattle($user, $friend_position, $enemy_position);

        if ($success){
            $response = [
                ['battle_id' => $user['battle_id']],
                201
            ];
        } else {
            $response = [
                'battle could not start',
                500
            ];
        }
        } else {
            $response = [
                ['battle_id' => $user['battle_id']],
                201
            ];
        }
        return $response;
    }


    /**
     * [Method] クエストが実行できる状態かを返す
     * @return boolean 実行できる : true
     */
    private function canMakeBattle($user)
    {
        $res = true;
        if ($user['battle_id'] != 0){
            $battle = $this->model->getBattleData($user);
            if ($battle['progress'] != 'closed'){
                $res = false;
            }
        }
        return $res;
    }


    /**
     * [Method] クエストで登場する敵パーティを読み込み、抽選して返す
     */
    private function getQuestEnemy()
    {
        $enemyparties = $this->model->getAllEnemyParties();
        $enemyparty = $this->chooseEnemyParty($enemyparties);
        return $enemyparty;
    }


    /**
     * [Method] 敵パーティを抽選する
     */
    private function chooseEnemyParty($enemyparties)
    {
        $party_weights = [];
        $weight_sum = 0;
        foreach($enemyparties as $party){
            $party_weights[] = $party['weight'];
            $weight_sum += $party['weight'];
        }
        $choose_rand = mt_rand(0, $weight_sum);

        $enemyparty = $enemyparties[0];
        for ($i = 0; $choose_rand > 0 ; $i++){
            $enemyparty = $enemyparties[$i];
            $choose_rand -= $party_weights[$i];
        }

        return $enemyparty;
    }
}