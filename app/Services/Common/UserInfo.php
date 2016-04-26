<?php
namespace App\Services\Common;

require '../vendor/autoload.php';
use Illuminate\Database\Eloquent\Model;

    /**
    * ユーザー情報に関わるクラス
    *
    * ユーザーデータにアクセスする方法が共通なものをまとめたクラス。
    */
class UserInfo extends Model
{
    public function __construct()
    {
        $this->dynamodbhandler = new DynamoDBHandler();
    }

    /**
    * ユーザーID取得用の関数
    *
    * セッションからUserIDを取得する。(認証未実装につき現在は定数を返す。)
    *
    * @return integer $userid ユーザーID
    */
    public function getUserID()
    {
        #セッションに保存されているuserIDを取り出す
        $userid = 1;
        return $userid;
    }

    /**
    * ユーザーIDに紐付いている情報を取得する関数
    *
    * ユーザーIDを受け取り、a_usersテーブルから紐付いている情報を取得する。
    *
    * @return array $response ユーザーデータ
    */
    public function getUserStatus($user_id)
    {
        #userIDに紐づけられた基本情報をDBから取得する

        $get = [
            'ConsistentRead' => true,
            'TableName' => 'a_users',
            'Key' => [
                'user_id' => [
                    'N' => (string)$user_id
                ]
            ]
        ];
        $response = $this->dynamodbhandler->getItem($get,'Failed to Get UserStatus');
        return $response;
    }
}
