<?php
namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use App\Models\BattleModel;

class BattleLogic extends Model
{
    private $CHARS_NUM = 3;     #隊列内のキャラ数
    private $TURN_MAX = 5;

    private $INITIAL_HP = 300;  #初期HP

    public function __construct($user_id)
    {
        $this->battle = new BattleModel($user_id);
    }


    /**
    * [API] バトル経過生成APIの関数
    *
    * usersテーブルにあるbattle_idのバトルを進行し、バトルの初期状態を返す。
    * 該当バトルのステータスを確認し、createdでなければ進行しない。
    * @return $response : [ (array or string), STATUS_CODE ]
    */
    public function setBattle()
    {
        #ユーザー情報の取得
        $user = $this->battle->getUser();
        #紐付いたバトル情報の取得
        $battle = $this->battle->getBattle($user);

        $response = [];
        # バトルの進行状態における分岐
        if ($battle['progress'] == 'created'){
            #created: バトルの進行データを作成し、初期状態を送信
            $battle['progress'] = 'in_process';
            $response = [$this->setResponse($battle), 201];
            #バトル進行データを作成
            $battle = $this->battleMain($battle);
            #バトル結果を書き込み
            $this->battle->writeBattle($battle);

        } else if ( $battle['progress'] == 'in_process'){
            #in process: 保存されていたデータを返す
            $response = [$this->setResponse($battle), 201];

        } else if ( $battle['progress'] == 'closed'){
            #closed: 終了した戦闘 のエラーを返す
            $response = ['status: Already Closed Battle', 400];
        };

        return $response;
    }


    /**
     * [Method] setBattleAPIで返す要素のみを配列に詰めて返す
     */
    private function setResponse($battle)
    {
        $response = [
            "friend_position" => $battle['friend_position'],
            "enemy_position" => $battle['enemy_position'],
            "type" => $battle['type']
        ];
        return $response;
    }


    /**
    * [API] ターン経過取得APIのServiceクラス
    *
    * userに紐付いているバトルについて、戦闘ログとして記録されているデータを返す。
    * ステータスがin_processでなかった場合はエラーを返す。
    */
    public function turnoverBattle()
    {
        $user = $this->battle->getUser();
        $battle = $this->battle->getBattle($user);

        if ($battle['progress'] == 'in_process'){
            $response = [$battle['log'], 200];
        } else {
            $response = ['status: Battle is NOT in Process', 400];
        }
    return $response;
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
    * バトルの進行データ作成処理
    */
    private function battleMain($battle)
    {
        #参加キャラデータを一つのリストに変形
        $battle_chars = $this->setBattleChar($battle);
        # 0ターン目のログ: 戦闘開始時のデータ
        $battle_log[0] = $this->setupBattle($battle);

        $turn = 0;
        # 現在のターンが戦闘終了条件を満たしていない場合、次のターンに進む
        while ( $battle_log[$turn]['is_last_turn'] == false ){
            # ターンを進める
            $turn++;
            # 現在のターンのログを生成する
            $battle_log[$turn] = $this->turnBattle($battle_chars, $battle_log[$turn - 1]);
            # TURN_MAXに達していれば終了フラグを立てて抜ける
            if ($turn == $this->TURN_MAX){
                $battle_log[$turn]["is_last_turn"] = true;
                break;
            }
        }
        #バトルの進行データを初期データに追加
        $battle['log'] = $battle_log;
        #バトルの勝敗を取得
        $battle['is_win'] = $this->isVictory($battle);
        #バトル結果のステータス変化を追加
        $battle['obtained'] = $this->setObtained($battle);

        #受け取った引数と同じ形式で返す
        return $battle;
    }


    /**
    * 戦闘開始時のバトルログ生成
    * @param $battle_log : battle_log(0ターン目)
    */
    private function setupBattle($battle_party)
    {
        # 初期HP設定
        $battle_log = [
            'is_last_turn' => false,
            'friend_hp' => $this->INITIAL_HP,
            'enemy_hp' => $this->INITIAL_HP
        ];
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
    * @param &$log   : ターンごとのログ $target['hp']要素にアクセスし、HPを更新する
    * @param &$chars : キャラのステータス デバフとして攻撃力を下げる
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
            # 敵キャラが入っている隊列
            $target['position'] = $this->CHARS_NUM;
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
    private function isVictory($battle)
    {
        $turn_num = count($battle['log']);
        $last_turn = $battle['log'][$turn_num - 1];
        $result = $last_turn['friend_hp'] >= $last_turn['enemy_hp'];

        return $result;
    }


    /**
    * [関数] 成果物の作成
    */
    private function setObtained($response)
    {
        $gainexp = $this->setGainExp($response['enemy_position'], $response['is_win']);
        $chars = $this->setParamObtained($response['friend_position'], $gainexp);
        $obtained = [
            'gainexp' => $gainexp,
            'chars' => $chars,
            'prize' => $gainexp,
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
    * [関数] 経験値の算出
    * $gainexp : 獲得経験値 勝った場合は全額、 負けたら1/4
    */
    private function setGainExp($enemy_position, $is_win){
        $gainexp = 0;
        $exp_win = $is_win ? 1 : (1/4);
        foreach ($enemy_position as $a_enemy){
            $gainexp += floor($a_enemy['exp'] * $exp_win);
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
