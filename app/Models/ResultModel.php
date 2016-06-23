<?php
namespace App\Models;

use App\Models\TransactionModel;
use App\Models\DynamoDBHandler;
use App\Models\BattleDBModel;
use App\Models\CharDBModel;
use App\Models\UserModel;

class ResultModel extends DynamoDBHandler
{
    public function __construct($user_id)
    {
        parent::__construct();
        $this->trans = new TransactionModel();
        $this->battle = new BattleDBModel();
        $this->char = new CharDBModel();
        $this->user = new UserModel($user_id);
    }


    /**
     * 現在のユーザー情報を取得する
     * @return array $user ユーザー情報
     */
    public function getUser()
    {
        return $this->user->getUser();
    }


    /**
    * [関数] ユーザー情報に登録されたバトルを読み出す。
    */
    public function getBattleData($user)
    {
        return $this->battle->getBattleByUser($user);
    }


    /**
     * [関数]戦闘に参加したキャラデータの受信
     */
    public function getBattleChar($user_id, $party)
    {
        return $this->battle->getBattleChar($user_id, $party);
    }


    /**
     * [Method] 書き込むキャラデータの整形
     */
    private function putChar($user, $char)
    {
        $char['user_id'] = $user['user_id'];
        $put = $this->char->getQueryUpdateChar($user['user_id'], $char);
        return $put;
    }



    /**
    * [関数] ユーザーデータ書き込み変数の整形
    * 獲得賞金は正なのでここで符号を反転される
    */
    private function updateUser($user, $prize)
    {
        $update = $this->user->getQueryUpdateUserUseMoney($user, 0 - $prize);
        return $update;
    }



    /**
     * [Method] 書き込むバトルデータの整形
     */
    private function updateBattle($user, $battle)
    {
        $battle['user_id'] = $user['user_id'];
        $update = $this->battle->getQueryUpdateBattle($user['user_id'], $battle);
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
}
