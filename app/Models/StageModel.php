<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Models\TransactionModel;
use App\Models\CharDBModel;
use App\Models\ArenaLoader;
use App\Models\BattleDBModel;
use App\Models\EnemyPartyLoader;
use App\Models\EnemyLoader;

/**
 * [Class] アリーナに関わるModelクラス
 *
 * 未実装項目 バトル発行はトランザクション処理として行う
 */
class StageModel extends Model
{
    private $user_id;
    public function __construct($user_id)
    {
        $this->user_id = $user_id;
        $this->a_user = new UserModel($user_id);
        $this->trans = new TransactionModel();
        $this->a_char = new CharDBModel();
        $this->arena = new ArenaLoader();
        $this->a_battle = new BattleDBModel();
        $this->enemyparty = new EnemyPartyLoader();
        $this->enemy = new EnemyLoader();
    }


    public function getUser()
    {
        return $this->a_user->getUser();
    }


    /**
    * [Method] パーティに組み込まれている味方キャラデータを読み込む
    */
    public function readCharInParty($user)
    {
        return $this->a_char->readCharInParty($user);
    }


    /**
     * [Method] JOINするアリーナ情報を読み込む
     */
    public function getArena($arena_id)
    {
        return $this->arena->getArena($arena_id);
    }



    /**
     * [Method] アリーナ情報リストを読み込む
     */
    public function getArenas($index)
    {
        return $this->arena->getArenas($index);
    }

    /**
     * [Method] バトル情報を読み込む
     * @param $user ユーザー情報
     */
    public function readBattle($user)
    {
        return $this->a_battle->getBattleByUser($user);
    }


    /**
     * 敵PTを読み込む
     * @param array [enemyparty_ids]
     */
    public function getEnemies($enemyparty_ids)
    {
        $parties = $this->enemyparty->getPartyStatus($enemyparty_ids);
        $enemy = $this->enemy->getEnemyStatus($parties[0]['party']);
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
