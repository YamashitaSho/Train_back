<?php
namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use App\Models\UserModel;
use App\Models\ResultModel;

class Resultlogic extends Model
{
    public function __construct()
    {
        $this->userinfo = new UserModel();
        $this->result = new ResultModel();
    }
    /**
    * [API] バトル結果表示、反映APIの関数
    *
    * DB上のバトルデータから結果情報を取得し、ステータスに反映後、返す。
    */

    public function getResult($battle_id)
    {
        # ユーザー情報の取得
        $user = $this->userinfo->getUser();
        # バトル情報の取得
        $battle = $this->result->getBattleData($user);

        $response = [];
        # レスポンスの取得
        switch ($battle['progress']){
            case ('created'):
                $response = ["battle did not run", 400];
                break;
            case ('in_process'):
                $res = $this->caseInProcess($user, $battle);
                $response = [$this->makeResponse($user, $battle), 201];
                break;
            case ('closed'):
                $response = [$this->makeResponse($user, $battle), 201];
                break;
        }
        return $response;
    }


    /**
     * [Method] バトルがin_processだった時の処理
     * 戦果書き込み → 結果返信
     */
    private function caseInProcess($user, $battle)
    {
        //更新するバトル情報の更新
        $battle['progress'] = 'closed';
        //更新するキャラ情報の取得
        $party = $this->mergeCharsStatus($user, $battle);
        //トランザクションで更新
        $this->result->putBattleResult($user, $party, $battle);
    }


    /**
     * [Method] バトルで変更されたキャラ情報を統合する
     * @return chars
     */
    private function mergeCharsStatus ($user, $battle)
    {
        $merged_chars = [];
        $obtain_chars = [];
        // 変更前のキャラステータス
        $party = $this->result->getBattleChar($user['user_id'], $battle['obtained']['chars']);

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
     * [Method] バトルがクローズドだった時の処理
     * 結果返信のみ
     */
    private function caseClosed(){
        printf("");
    }

    /**
     * [Method] レスポンス生成の処理
     */
    private function makeResponse($user, $data)
    {
        $response = [
            "is_win" => $data['is_win'],
            "get_item" => "",
            "money" => $user['money'],
            "prize" => $data['obtained']['prize'],
            "chars" => $data['friend_position'],
            "obtained" => $data['obtained']['chars'],
        ];

        return $response;
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
