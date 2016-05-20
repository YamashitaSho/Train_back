<?php
namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use App\Models\UserModel;
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
    private $usermodel;
    private $gacha;

    public function __construct()
    {
        $this->gacha = new GachaModel();
        $this->usermodel = new UserModel();
    }

    /**
    * [関数] ガチャが引けるかどうかをチェックするAPIで呼び出される関数
    */
    public function checkGacha()
    {
        #ユーザー情報
        $user = $this->usermodel->getuser();

        #全キャラの重み配列
        $weights = $this->gacha->readWeight();
        #手持ちキャラのリスト
        $own = $this->gacha->readChar($user['user_id']);
        #実際のガチャ配列
        $gachabox = $this->makeGachaBox($weights, $own);

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
        $user = $this->usermodel->getuser();

        #全キャラの重み配列
        $weights = $this->gacha->readWeight();
        #手持ちキャラのリスト
        $own = $this->gacha->readChar($user['user_id']);
        #実際のガチャ配列
        $gachabox = $this->makeGachaBox($weights, $own);

        #残りキャラ数
        $rest_char = $this->getRestChars($gachabox);
        #ガチャコスト
        $gacha_cost = $this->getGachaCost($rest_char);
        #ガチャの可否
        $availability = $this->getAvailability($user['money'], $gacha_cost, $rest_char);

        if ($availability){
            #$prize_id : 獲得キャラのchar_id(抽選する)
            $prize_id = $this->turnGacha($gachabox);
            #獲得キャラの情報
            $prize_char = $this->gacha->readPrize($prize_id);

            #DBの更新
            $result = $this->gacha->putGachaResult($user, $prize_char, $gacha_cost);

            if ($result){
                $response = [
                    [
                        'char_id' => $prize_char['char_id'],
                        'name' => $prize_char['name'],
                        'status' => $prize_char['status']
                    ],
                    201
                ];
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
    * [関数] ガチャを引く重み配列を作成する
    *
    * @param $weights : 全キャラの重み配列(char_idの連想配列)
    * @param $own_chars : 所持キャラのchar_id情報
    */
    private function makeGachaBox($weights, $own_chars)
    {
        foreach ($own_chars as $own_char){
            $weights[$own_char['char_id']] = 0;
        }
        $gachabox = $weights;
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
    * @return $prize_id : 獲得キャラのchar_id
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
        return $prize_id;
    }

    /**
    * [関数] 料金を徴収、更新処理を行うAPI
    *
    * 書き込みに失敗した場合のトランザクション処理も行わなければならない(未実装)。
    */
    private function updateData($prize_char, $user, $gacha_cost)
    {
/*        $user['money'] -= $gacha_cost;
        $this->gacha->writeChar($prize_char, $user['user_id']);
        $this->gacha->writeUser($user);
*/
        return 0;
    }
}
