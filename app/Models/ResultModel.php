<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Models\TransactionModel;
use App\Models\BattleDBModel;
use App\Models\CharDBModel;
use App\Models\UserModel;
use App\Models\EnemyLoader;
use App\Models\EnemyPartyLoader;

class ResultModel extends Model
{
    public function __construct($user_id)
    {
        $this->trans = new TransactionModel();
        $this->a_battle = new BattleDBModel();
        $this->a_char = new CharDBModel();
        $this->a_user = new UserModel($user_id);
        $this->enemy = new EnemyLoader();
        $this->enemyparty = new EnemyPartyLoader();
    }


    /**
     * 現在のユーザー情報を取得する
     * @return array $user ユーザー情報
     */
    public function getUser()
    {
        return $this->a_user->getUser();
    }


    /**
    * [関数] ユーザー情報に登録されたバトルを読み出す。
    */
    public function getBattleData($user)
    {
        return $this->a_battle->getBattleByUser($user);
    }


    /**
     * [関数]戦闘に参加したキャラデータの受信
     */
    public function getBattleChar($user_id, $party)
    {
        return $this->a_battle->getBattleChar($user_id, $party);
    }


    /**
     * [Method] 書き込むキャラデータの整形
     */
    private function putChar($user, $char)
    {
        $char['user_id'] = $user['user_id'];
        $put = $this->a_char->getQueryUpdateChar($user['user_id'], $char);
        return $put;
    }



    /**
    * [関数] ユーザーデータ書き込み変数の整形
    * 獲得賞金は正なのでここで符号を反転される
    */
    private function updateUser($user, $prize)
    {
        $update = $this->a_user->getQueryUpdateUserUseMoney($user, 0 - $prize);
        return $update;
    }


    /**
     * [Method] 書き込むバトルデータの整形
     */
    private function updateBattle($user, $battle)
    {
        $battle['user_id'] = $user['user_id'];
        $update = $this->a_battle->getQueryUpdateBattle($user['user_id'], $battle);
        return $update;
    }


    /**
    * [関数] 戦闘結果書き込み関数
    *
    * フォーマットに従って変数を詰めた後トランザクションクラスに渡し、結果を返す
    */
    public function putBattleResult($user, $party, $battle)
    {
        $prize = $battle['obtained']['prize'];

        $chars_update = [];
        foreach($party as $char){
            $chars_update[] = $this->putChar($user, $char);
        }
        $user_update = $this->updateUser($user, $prize);
        $battle_update = $this->updateBattle($user, $battle);
        $requests = $chars_update;
        $requests[] = $user_update;
        $requests[] = $battle_update;
        $result = $this->trans->isTransSuccess($user, $requests);
        return $result;
    }


    public function readCharInParty($user)
    {
        return $this->a_char->readCharInParty($user);
    }


    /**
     * 敵PTを読み込む
     * @param array $enemyparty_ids [enemyparty_ids]
     * @param int $type アリーナの情報のうち読み込まれるウェーブ
     */
    public function getEnemies($enemyparty_ids, $type)
    {
        $parties = $this->enemyparty->getPartyStatus($enemyparty_ids);
        $enemy = $this->enemy->getEnemyStatus($parties[$type]['party']);
        return $enemy;
    }


    /**
     * バトル情報を書き込むトランザクションを実行
     */
    public function transBattle($user, $friends, $enemies, $arena, $type)
    {
        #バトルレコードの更新内容
        $a_battle = $this->a_battle->getQueryPutBattle($user, $friends, $enemies, $type, $arena);
        #ユーザーレコードの更新内容
        $a_user = $this->a_user->getQueryPutUser($user);

        $request = [$a_battle, $a_user];
        return $this->trans->isTransSuccess($user, $request);
    }
}
