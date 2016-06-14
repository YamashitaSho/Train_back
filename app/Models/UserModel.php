<?php
namespace App\Models;

use App\Models\DynamoDBHandler;

    /**
    * ユーザー情報に関わるクラス
    *
    * ユーザーデータにアクセスする方法が共通なものをまとめたクラス。
    */
class UserModel extends DynamoDBHandler
{
    private $user;
    private $user_id;
    private $is_read;
    public function __construct($user_id)
    {
        parent::__construct();

        $this->user_id = $user_id;               #セッションから取得する
        $this->user = [];
        $this->is_read = 0;

    }


    /**
     * セッションからユーザーIDを取得する
     */


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
        if ($this->is_read == 0){
            $get = [
                'TableName' => 'a_users',
                'Key' => [
                    'user_id' => [
                        'N' => (string)$this->user_id
                    ]
                ]
            ];
            $this->user = $this->getItem($get);
            $this->is_read = 1;
        }

        return $this->user;
    }
}
