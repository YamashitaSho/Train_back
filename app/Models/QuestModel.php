<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Models\UserModel;
use App\Models\TransactionModel;
use App\Models\BattleDBModel;
use App\Models\CharDBModel;
use App\Models\EnemyLoader;
use App\Models\EnemyPartyLoader;


/**
 * [Class] クエストに関わるModelクラス
 *
 * 未実装項目 バトル発行はトランザクション処理として行う
 */
class QuestModel extends Model
{
    public function __construct($user_id)
    {
        $this->trans = new TransactionModel();
        $this->user = new UserModel($user_id);
        $this->battle = new BattleDBModel();
        $this->char = new CharDBModel();
        $this->enemy = new EnemyLoader();
        $this->enemyparty = new EnemyPartyLoader();
    }


    public function getUser()
    {
        return $this->user->getUser();
    }


    public function getBattleData($user)
    {
        return $this->battle->getBattleByUser($user);
    }


    /**
    * 敵キャラデータを読み込む
    * @param $party array charの配列
    */
    public function readEnemy($party)
    {
        return $this->enemy->getEnemyStatus($party);
    }


    public function getAllEnemyParties()
    {
        return $this->enemyparty->importAll();
    }

    /**
    * [Method] 味方キャラデータを読み込む
    */
    public function readCharInParty($user)
    {
        return $this->char->readCharInParty($user);
    }


    /**
     * バトル作成のトランザクション実行
     */
    public function postBattle($user, $friends, $enemies)
    {
        $request = [
            $this->battle->getQueryPutBattle($user, $friends, $enemies),
            $this->user->getQueryPutUser($user)
        ];
        return $this->trans->isTransSuccess($user, $request);
    }
}
