<?php
namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use App\Models\GachaModel;

class GachaLogic extends Model
{
    #残りキャラに応じた代金の変化
    private $prices = [
        -1,
        10000,
        5000,
        2000,
        1000,
        500,
        300,
        200
    ];
    private $gacha;


    public function __construct($user_id)
    {
        $this->gacha = new GachaModel($user_id);
    }


    /**
    * [関数] ガチャが引けるかどうかをチェックするAPIで呼び出される関数
    */
    public function checkGacha()
    {
        #ユーザー情報
        $user = $this->gacha->getuser();

        #重み配列を読み込む
        $gachabox = $this->getGachaBox($user['user_id']);
        #残りキャラ数
        $rest_char = $this->getRestChars($gachabox);
        #ガチャ料金
        $gacha_cost = $this->getGachaCost($rest_char);
        #ガチャの可否
        $availability = $this->getAvailability($user['money'], $gacha_cost, $rest_char);
        $response = [
            'availability' => $availability,
            'rest_char' => $rest_char,
            'gacha_cost' => $gacha_cost,
            'money' => $user['money']
        ];
        return [$response, 200];
    }


    /**
    * [関数] ガチャを引くAPIで呼び出される関数
    */
    public function drawGacha()
    {
        #ユーザー情報
        $user = $this->gacha->getuser();

        #重み配列を読み込む
        $gachabox = $this->getGachaBox($user['user_id']);

        #残りキャラ数
        $rest_char = $this->getRestChars($gachabox);
        #ガチャコスト
        $gacha_cost = $this->getGachaCost($rest_char);
        #ガチャの可否
        $availability = $this->getAvailability($user['money'], $gacha_cost, $rest_char);

        if ($availability){
            #$prize_char : 獲得キャラ(抽選する)
            $prize_char = $this->turnGacha($gachabox);
            #DBの更新
            $result = $this->gacha->putGachaResult($user, $prize_char, $gacha_cost, $gachabox);

            if ($result){
                $response = [$prize_char, 201];
            } else {
                #トランザクションが失敗
                $response = ['Service Unavailable', 503];
            }
        } else {
            $response = ['Gacha is Unavailable', 400];
        }
        return $response;
    }


    /**
     * [Method] ガチャの重み配列を取得する
     * @return ガチャの重みの配列 [{char_id => weight}]
     */
    private function getGachaBox($user_id)
    {

        $gachabox = $this->gacha->getGachaBox();
        #ガチャ配列が保存されていない
        if (empty($gachabox)){

            #実際のガチャ配列
            $gachabox = $this->makeGachaBox($user_id);
            #ガチャ配列を保存しておく
            $this->gacha->putGachaBox($gachabox);
        }

        return $gachabox;
    }


    /**
    * [関数] ガチャを引く重み配列を作成する
    *
    * @param $gachabox  : 全キャラの重み配列(char_idの連想配列)
    * @param $own_chars : 所持キャラのchar_id情報
    */
    private function makeGachaBox($user_id)
    {
        #全キャラの重み配列
        $gachabox = $this->gacha->getWeights();
        #手持ちキャラのリスト
        $own_chars = $this->gacha->readChar();
        foreach ($own_chars as $own_char){
            $gachabox[$own_char['char_id']] = 0;
        }
        return $gachabox;
    }


    /**
    * [関数] ガチャで排出されるキャラの数を返す
    */
    private function getRestChars($gachabox)
    {
        $rest_char = 0;
        foreach ($gachabox as $weight){
            if ($weight > 0){
                $rest_char ++;
            }
        }
        return $rest_char;
    }


    /**
    * [関数] ガチャの使用料金を返す
    */
    private function getGachaCost($rest_char)
    {
        $prices_num = count($this->prices);
        $gacha_cost = 0;
        if ( $rest_char >= $prices_num ) {
            $gacha_cost = $this->prices[ $prices_num - 1 ];
        } else {
            $gacha_cost = $this->prices[ $rest_char ];
        }

        return $gacha_cost;
    }


    /**
    * [関数] ガチャが使用可能かどうか判定する
    * @return $availability : boolean
    */
    private function getAvailability($money, $gacha_cost, $rest_char)
    {
        $availability = ($money >= $gacha_cost);
        if ($rest_char == 0){
            $availability = false;
        }
        return $availability;
    }


    /**
    * [関数] ガチャの抽選を行う
    * @return $prize_char : 獲得キャラ
    */
    private function turnGacha($gachabox)
    {
        $weight_sum = array_sum($gachabox);
        $gacha_rand = mt_rand(0, $weight_sum);
        $prize_id = 0;
        for ($i = 0; $gacha_rand > 0 ; $i++){
            $prize_id = $i;
            $gacha_rand -= $gachabox[$i];
        }

        $prize_char = $this->gacha->getChar($prize_id);
        return $prize_char;
    }


}
