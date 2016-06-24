<?php
namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use App\Models\UserModel;
use App\Models\ResultModel;

class Resultlogic extends Model
{
    public function __construct($user_id)
    {
        $this->model = new ResultModel($user_id);
    }
    /**
    * [API] バトル結果表示、反映APIの関数
    *
    * DB上のバトルデータから結果情報を取得し、ステータスに反映後、返す。
    */

    public function getResult()
    {
        # ユーザー情報の取得
        $user = $this->model->getUser();
        # バトル情報の取得
        $battle = $this->model->getBattleData($user);

        return $this->makeResponse($user, $battle);
    }


    /**
     * バトルの状態によってフロントに渡すレスポンスを変更する
     * @param array $user
     * @param array $battle
     * @return array $response
     */
    private function makeResponse($user, $battle)
    {
        # レスポンスの取得
        switch ($battle['progress']){
            case ('created'):
                return ["battle did not run", 400];
            case ('in_process'):
                return $this->caseInProcess($user, $battle);
            case ('closed'):
                return $this->caseClosed($user, $battle);
            default:
                return ['Battle Status Not Found', 400];
        }
    }


    /**
     * [Method] レスポンス生成の処理
     */
    private function setResponseBody($user, $data)
    {
        $response = [
            "is_win" => $data['is_win'],
            "get_item" => "",
            "money" => $user['money'],
            "prize" => $data['obtained']['prize'],
            "chars" => $data['friend_position'],
            "obtained" => $data['obtained']['chars'],
            "type" => $data['type']
        ];
        return $response;
    }


    /**
     * [Method] バトルがin_processだった時の処理
     * 戦果書き込み → 連戦処理 → 結果返信
     */
    private function caseInProcess($user, $battle)
    {
        //更新するバトル情報の更新
        $battle['progress'] = 'closed';
        //戦闘後のキャラ情報の取得
        $party = $this->getCharStatusAfterBattle($user, $battle);
        //トランザクションで更新
        $success = $this->model->putBattleResult($user, $party, $battle);
        $success = true;
        if ($success){
            $this->checkNextBattle($user, $battle);
        }

        return [$this->setResponseBody($user, $battle), 201];
    }


    /**
     * [Method] バトルで変更されたキャラ情報を統合する
     * @return chars
     */
    private function getCharStatusAfterBattle ($user, $battle)
    {
        $merged_chars = [];
        $obtain_chars = [];
        // 変更前のキャラステータス
        $party = $this->model->getBattleChar($user['user_id'], $battle['obtained']['chars']);

        //インデックスをchar_idに変更
        foreach($battle['obtained']['chars'] as $obtained_char){
            $obtain_chars[$obtained_char['char_id']] = $obtained_char;
        }
        foreach($party as $key => $char){
            $merged_chars[$key] = $obtain_chars[$char['char_id']] + $char;
        }
        return $merged_chars;
    }


    /**
     * 連戦があるかどうか確認し、あれば発行する
     */
    private function checkNextBattle($user, $battle)
    {
        switch ($battle['type']){
            case ('quest'):
                //クエストは1戦で終了
                return ;
            case ('arena0'):
                //バトルに勝利していればarena1のバトルを発行
                if ($battle['is_win']){
                    $this->runNextBattle($user, $battle, 1);
                }
                return ;
            case ('arena1'):
                //バトルに勝利していればarena2のバトルを発行
                if ($battle['is_win']){
                    $this->runNextBattle($user, $battle, 2);
                }
                return ;
            case ('arena2'):
                //最後のバトルなのでアリーナをクリアした処理として終了
                return ;
        }
    }


    /**
     * アリーナで指定された連戦を実行する
     * @param array $user
     * @param array $battle
     * @param string $type 次のバトルのタイプ
     */
    private function runNextBattle($user, $battle, $type)
    {
        #バトルIDをインクリメント
        $user['battle_id']++;
        #進行するアリーナの取得
        $arena = $battle['arena'];
        #味方キャラの取得
        $friends = $this->model->readCharInParty($user);
        #敵キャラの取得
        $enemies = $this->model->getEnemies($arena['arena']['enemyparty_id'], $type);
        #トランザクションでバトルを作成
        return $this->model->transBattle($user, $friends, $enemies, $arena, 'arena'.$type);
    }


    /**
     * [Method] バトルがクローズドだった時の処理
     * 連戦が設定されている場合は再発行する
     */
    private function caseClosed($user, $battle){
        $this->checkNextBattle($user, $battle);
        return [$this->setResponseBody($user, $battle), 201];
    }


    /**
     * [Method] 報酬金額の設定
     */
    private function setPrize($data)
    {
        $response = 0;
        if ($data['type'] == 'quest'){
            $response = $data['obtained']['gainexp'];
        }
        return $response;
    }
}
