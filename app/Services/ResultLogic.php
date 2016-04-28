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

        #
        # レスポンスの取得
        $response = makeResponse($battle);

        return [$response, 201];
    }


    /**
     * [Method] バトルがin_processだった時の処理
     * 戦果書き込み → 結果返信
     */
    private function caseInProcess(){
        $data['progress'] = 'closed';               #ステータスを終了済みにする
        $data['record'] = $this->record->updateRecordStatus($data['record']);
        #キャラデータ更新
        $chars_num = count($data['obtained']['chars']);
        for ($i = 0; $i < $chars_num ; $i++){
            try {
                $result = $this->dynamodb->getItem([
                    'TableName' => 'a_chars',
                    'Key' => $this->marshaler->marshalItem([
                        'user_id' => (int)$user_id,
                        'char_id' => (int)$data['obtained']['chars'][$i]['char_id']
                    ]),
                ]);
            } catch (DynamoDbException $e) {
                echo $e->getMessage();
                return ['status: Failed to get Chardata', 500];
            }
            $char = $data['obtained']['chars'][$i];
            $char['user_id'] = $user_id;
            $char['record'] = $this->record->updateRecordStatus($this->marshaler->unmarshalItem($result['Item'])['record']);

            try{
                $result = $this->dynamodb->putItem([
                    'TableName' => 'a_chars',
                    'Key' => $this->marshaler->marshalItem([
                        'user_id' => (int)$user_id,
                        'char_id' => (int)$char['char_id']
                    ]),
                    'Item' => $this->marshaler->marshalItem($char)
                ]);
            } catch (DynamoDbException $e) {
                echo $e->getMessage();
                return ['status: Failed to update Chardata', 500];
            }
            $this->dynamodbhandler->putItem($,$);
        }
    }


    /**
     * [Method] バトルがクローズドだった時の処理
     * 結果返信のみ
     */
    private function caseClosed(){

    }

    /**
     * [Method] レスポンス生成の処理
     */
    private function makeResponse(){
        switch ($battle['progress']){
            case ('in_process'):
                break;
            case ('closed'):
                break;

        }
        $response = [
            "is_win" => $data['is_win'],
            "get_item" => "",
            "money" => $user['money'],
            "prize" => 150,
            "chars" => $data['friend_position'],
            "obtained" => $data['obtained']['chars'],
        ];
    }
}
