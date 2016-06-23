<?php
namespace App\Models;

use App\Models\TransactionModel;
use App\Models\UserModel;
use App\Models\GachaDBModel;
use App\Models\CharLoader;
use App\Models\CharDBModel;



class GachaModel
{
    private $user_id;
    public function __construct($user_id)
    {
        $this->user_id = $user_id;
        $this->user = new UserModel($user_id);
        $this->trans = new TransactionModel();
        $this->gacha = new GachaDBModel();
        $this->char = new CharLoader();
        $this->a_char = new CharDBModel();

        parent::__construct();
    }


    public function getUser()
    {
        return $this->user->getUser();
    }


    /**
     * キャラマスタの重み情報を読み込む
     */
    public function getWeights()
    {
        return $this->char->getWeights();
    }


    /**
     * 保存された重み情報を読み込む
     */
    public function getGachaBox(){
        return $this->gacha->getGachaBox($this->user_id);
    }


    /**
    * [関数] 指定されたidのキャラを読み込む
    * @param int $prize_id 抽選結果のchar_id
    */
    public function getChar($char_id)
    {
        return $this->char->getChar($char_id);
    }


    /**
    * [関数] 所持キャラデータ読み込みの関数
    *
    * 読み込むのは char_id のみ
    */
    public function readChar()
    {
        return $this->a_char->getCharOwned($this->user_id, true);
    }


    /**
     * [Method] ガチャの重み配列を保存する
     */
    public function putGachaBox($gachabox)
    {
        return $this->gacha->putGachaBox($this->user_id, $gachabox);
    }


    /**
     * ガチャの結果に従って重み情報を更新する
     */
    private function getPrizeIndex($char_id, $gachabox)
    {
        $prize_index = 0;
        $count = 0;
        foreach ($gachabox as $key => $value){
            if ($key == $char_id){
                $prize_index = $count;
            }
            $count++;
        }
        return $prize_index;
    }


    /**
    * [関数] ガチャ結果書き込み関数
    * @param array $user ユーザー情報
    * @param array $prize_char 入手キャラのステータス
    * @param int $gacha_cost ガチャ費用(正の値)
    * @param array $gachabox ガチャの配列
    * @return boolean $result トランザクションの成否
    */
    public function putGachaResult($user, $prize_char, $gacha_cost, $gachabox)
    {
        $prize_index = $this->getPrizeIndex($prize_char['char_id'], $gachabox);

        $prize_char['user_id'] = $user['user_id'];
        $char_put = $this->a_char->getQueryPutChar($user['user_id'], $prize_char);
        $user_update = $this->user->getQueryUpdateUserUseMoney($user, $gacha_cost);
        $gachabox_update = $this->gacha->getQueryUpdateGachaBox($user['user_id'], $prize_index);

        $requests = [
            $char_put,
            $user_update,
            $gachabox_update
        ];

        $result = $this->trans->isTransSuccess($user, $requests);
        return $result;
    }
}
