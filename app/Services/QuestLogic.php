<?php
namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use App\Models\QuestModel;
use App\Models\UserModel;


/**
 * [Class] クエスト実行クラス
 * バトルを発行し、バトルの実処理はBattleクラスに移譲する
 */
class QuestLogic extends Model
{
    public function __construct(){
        $this->quest = new QuestModel();
        $this->userinfo = new UserModel();
    }


    /**
     * [API] 現在のパーティキャラを取得する関数
     */
    public function getParty()
    {
        $user = $this->userinfo->getUser();
        $chars = $this->quest->readCharInParty($user);
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
        $user = $this->userinfo->getUser();
        if ($user['battle_id'] == 0){
            return true;                           #バトルIDが0の場合は再利用しない
        }

        if (!$this->canMakeBattle($user)){       #バトル作成可能条件の確認
            $response = [
                'battle_id' => $user['battle_id']
            ];
            return [$response, 201];               #created、in_processのものが存在する
        }
        #バトルIDを一つ進める
        $user['battle_id'] ++;
        #仮enemyparty_id()
        $enemyparty_id = 0;
        $enemyparty = $this->quest->readEnemyParty($enemyparty_id);
        $enemy_position = $this->quest->readEnemy($enemyparty['party']);
        $friend_position = $this->quest->readCharInParty($user);
        #ユーザーデータの書き込み(トランザクションに要変更)
        $this->quest->writeBattle($user, $friend_position, $enemy_position);
        $this->quest->writeUser($user);

        $response = [ "battle_id" => $user['battle_id']];
        return [$response, 201];
    }


    /**
     * [Method] クエストが実行できる状態かを返す
     * @return boolean 実行できる : true
     */
    private function canMakeBattle($user)
    {
        if ($user['battle_id'] == 0){
            return true;                           #バトルIDが0の場合は再利用しない
        }
        $battle = $this->quest->readBattle($user);
        if ($battle['progress'] != 'closed'){
            return false;
        }
        return true;
    }
}