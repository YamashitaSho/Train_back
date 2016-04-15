<?php
namespace App\Models;

require '../vendor/autoload.php';
use Illuminate\Database\Eloquent\Model;

    /**
    * ユーザー情報に関わるクラス
    *
    * ユーザーデータにアクセスする方法が共通なものをまとめたクラス。
    */
class UserModel extends DynamoDBHandler
{
    private $user;
    private $user_id;
    public function __construct()
    {
        parent::__construct();

        $this->user_id = 1;               #セッションから取得する

    }

    /**
    * ユーザーIDに紐付いている情報を取得する関数
    *
    * ユーザーIDを受け取り、a_usersテーブルから紐付いている情報を取得する。
    *
    * @return array $response ユーザーデータ
    */
    public function getUser()
    {
        #userIDに紐づけられた基本情報をDBから取得する
        #$user_id = $this->user_id;
        $get = [
            'TableName' => 'a_users',
            'Key' => [
                'user_id' => [
                    'N' => (string)$this->user_id
                ]
            ]
        ];
        $this->user = $this->getItem($get,'Failed to Get UserStatus');
        return $this->user;
    }
}
