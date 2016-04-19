<?php
namespace App\Services;

require '../vendor/autoload.php';
use Illuminate\Database\Eloquent\Model;

use App\Models\BattleModel;
use App\Models\UserModel;

class BattleLogic extends Model
{
    private $CHARS_NUM = 3;  #隊列内のキャラ数
    private $TURN_MAX = 5;

    public function __construct()
    {
        $this->battle = new BattleModel();
        $this->userinfo = new UserModel();
    }
    public function setBattle($battle_id)
    {
        //該当バトルIDの戦闘を進行する。
        //該当バトルのステータスを進行中に変更し、キャラのステータスに反映する
        //該当バトルが進行中だった場合はバトル開始情報のみを送信する
        //終了済みだった場合はエラーを返す
        $user = $this->userinfo->getUser();
        $battle = $this->battle->getBattle($user);
        # バトル進行状態のチェック
        if ($battle['progress'] == 'created'){
            #created: 通常の進行
            $battle['progress'] = "in_process";
        } else if ( $battle['progress'] == 'in_process'){
            #in process: ログがすでにあるはずなのでデータをそのまま送信
            return [$battle, 201];
        } else if ( $battle['progress'] == 'closed'){
            #closed すでに結果表示を終えた戦闘なのでエラーを返す
            return ['status: Already Closed Battle', 400];
        };
        $response = $this->battleMain($battle);
        $response['is_win'] = $this->getWhichVictory($response);
        $response['obtained'] = $this->setObtained($response);
       # dd($response);
        $this->battle->writeBattle($user, $response);
        return [$battle, 201];
    }
    public function turnoverBattle($battle_id)
    {
        //進行中のバトルについて、戦闘終了情報が入力されるか5ターン経過するまでのデータを取得し、送信する。
        $user = $this->userinfo->getUser();
        $battle = $this->battle->getBattle($user);

        if ($battle['progress'] == 'in_process'){
            $response = $battle['log'];
        } else {
            return ['status: Battle is NOT in Process', 400];
        }
    return [$response, 200];
    }
    /**
    * バトルキャラ設定
    * バトル参加キャラを一つの配列にまとめる
    */
    private function setBattleChar($battle)
    {
        $response = [];
        $num = count($battle['friend_position']);
        for ($i = 0; $i < $num; $i++){
            $response[$i] = $battle['friend_position'][$i];
            $response[$i]['position'] = 'friend'.($i+1);
        }
        $num = count($battle['enemy_position']);
        for ($i = 0; $i < $num; $i++){
            $response[$i+$this->CHARS_NUM] = $battle['enemy_position'][$i];
            $response[$i+$this->CHARS_NUM]['position'] = 'enemy'.($i+1);
        }
        return $response;
    }
    /**
    * バトルの実処理
    */
    private function battleMain($battle)
    {
        $battle_chars = $this->setBattleChar($battle);
        #    print_r($battle_chars);
        $battle_log[0] = $this->setupBattle($battle);

        $turn = 0;
        while ( $battle_log[$turn]['is_last_turn'] == false ){
            $turn++;
            $battle_log[$turn] = $this->turnBattle($battle_chars, $battle_log[$turn - 1]);
            # TURN_MAXに達していれば終了フラグを立てて抜ける
            if ($turn == $this->TURN_MAX){
                $battle_log[$turn]["is_last_turn"] = true;
                break;
            }
        }
        $battle['log'] = $battle_log;

        return $battle;
    }
    /**
    * 戦闘開始時のバトルログ生成
    * @param $battle_log : battle_log(0ターン目)
    */
    private function setupBattle($battle_party)
    {
        $battle_log = [
            'is_last_turn' => false,
            'friend_hp' => 1000,
            'enemy_hp' => 1000
        ];
        #初期HP設定
        foreach ($battle_party['friend_position'] as $friend){
            $battle_log['friend_hp'] += $friend['status']['endurance'];
        }
        foreach ($battle_party['enemy_position'] as $enemy){
            $battle_log['enemy_hp'] += $enemy['status']['endurance'];
        }
        return $battle_log;
    }
    /**
    * ターンごとのバトルログ生成
    */
    private function turnBattle($chars, $log)
    {
        unset($log['action']);
        $sequence = $this->putOrder($chars);
        foreach ($sequence as $count => $position){
            #行動するキャラのステータス
            $active_char = $chars[$position['position']]['status'];
            #行動するキャラのポジション
            $active_char['position'] = $chars[$position['position']]['position'];
            #キャラの行動ログ($action)をとる
            $action = $this->actionChar($active_char, $log, $chars);
            $log['action'][$count] = $action;
            if ($log['is_last_turn'] == true){
                break;
            }
        }
        return $log;
    }
    /**
    * キャラの行動順決定
    * @return $sequence : 速度が高かった順に['position']要素として隊列番号が格納されている
    */
    private function putOrder($chars)
    {
        $count = 0;
        $sequence = [];
        foreach ($chars as $key => &$char){
            $char['status']["agility"] = $this->setRandom($char['status']["agility"]);
            $sequence[$count]["position"] = $key;
            $sequence[$count]["speed"] = $char['status']["agility"];
            $count++;
        }
        usort($sequence, function($a, $b){
            return $a["speed"] < $b["speed"];
        });
        return $sequence;
    }
    /**
    * キャラの行動ログ生成
    */
    private function actionChar($active_char, &$log ,&$chars)
    {
        $target = $this->decisionSide($active_char['position']);
        $action = [];
        $action['position'] = $active_char['position'];
        $action["damage"] = $this->setRandom($active_char['attack']);
        $log[$target['hp']] -= $action['damage'];
        if ($log[$target['hp']]<=0) {
            $log[$target['hp']] = 0;
            $log['is_last_turn'] = true;
        }

        #デバフ処理
        for ($i=0 ; $i<3 ; $i++){
            $debuf = $this->setRandom($active_char['debuf']/2);
            $action['debuf'][$i] = $debuf;
            $target_attack = &$chars[ $i+$target['position'] ]['status']['attack'];
            $target_attack -= $debuf;
            if ($target_attack < 0 ){
                $target_attack = 0;
            }
        }
        return $action;
    }

    /**
    * キャラが敵軍か友軍かの判定
    */
    private function decisionSide($position)
    {
        $target = [];
        if (strstr($position, 'friend')){
            $target['hp'] = 'enemy_hp';
            $target['position'] = 3;                            #敵キャラが入っているのは味方の3つ分先
        } else {
            $target['hp'] = 'friend_hp';
            $target['position'] = 0;
        }
        return $target;
    }
    /**
    * 勝敗を取得する
    * 味方が勝っていればtrue 敵が勝っていればfalse
    */
    private function getWhichVictory($battle)
    {
        $turn_num = count($battle['log']);
        $last_turn = $battle['log'][$turn_num - 1];
        $result = $last_turn['friend_hp'] >= $last_turn['enemy_hp'];

        return $result;
    }
    /**
    *
    */
    private function setObtained($response)
    {
        $gainexp = $this->setGainExp($response['enemy_position'], $response['is_win']);
        $chars = $this->setParamObtained($response['friend_position'], $obtained['gainexp']);
        $obtained = [
            'gainexp' => $gainexp,
            'chars' => $chars,
        ];
        return $obtained;
    }
    /**
    * 8分の7倍〜8分の9倍にする乱数を生成する
    */
    private function setRandom($number)
    {
        $result = mt_rand($number*7/8, $number*9/8);
        return $result;
    }
    /**
    * 経験値獲得関数
    * $obtained : 成果物 経験値、レベルアップパラメータ、アイテム
    * $gainexp : 獲得経験値 勝った場合は全額、 負けたら1/4
    */
    private function setGainExp($enemy_position, $is_win){
        $gainexp = 0;
        $exp_win = $is_win ? 1 : (1/4);
        foreach ($enemy_position as $a_enemy){
            $gainexp += $a_enemy['exp'] * $exp_win;
        }
        return $gainexp;
    }
    /**
    * [関数] 参戦キャラに経験値を適用したものを返す
    * @param $chars   : 各キャラの経験値追加後のパラメータ
    */
    private function setParamObtained($friend_position, $gainexp)
    {
        #キャラごとに処理する
        $response = [];
        $chars = $this->battle->getPartyChar($friend_position);
        $chars_num = count($friend_position);
        foreach ($friend_position as $count => $friend){
            $a_char = $friend;
            $a_char['exp'] += $gainexp;
            while ($a_char['exp'] >= 100){
                $a_char['exp'] -= 100;
                $a_char['level']++;
                $a_char['status'] = $this->setLevelUp($a_char['status'], $chars[$count]['status_growth_rate']);
            }
            $response[] = $a_char;
        }
        return $response;
    }
    /**
    * [関数] キャラのレベルが上がった時の処理
    *
    */
    private function setLevelUp($status, $growth_rate){
        $params = ['attack', 'endurance', 'agility', 'debuf'];
        foreach ($params as $param){
            $rand[$param] = mt_rand(1, 100);
            while( $growth_rate[$param] > 0 ){
                if ($rand[$param] <= $growth_rate[$param]){
                    $status[$param] ++;
                }
                $growth_rate[$param] -= 100;
            }
        }
        return $status;
    }
}
