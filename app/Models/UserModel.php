<?php
namespace App\Models;

use App\Models\DynamoDBHandler;
use App\Services\Common\Record;

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
        $this->record = new Record();

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


    /**
     * ユーザー情報の更新
     * @param array $user 更新後のユーザーデータ
     */
    public function updateUser($user)
    {
        $put = $this->getQueryUpdateUser($user);
        $this->putItem($put);
    }


    /**
     * ユーザー更新情報の作成
     * @param array $user 更新後のユーザーデータ
     * @return array $put ユーザーデータを更新する命令
     */
    public function getQueryUpdateUser($user)
    {
        $user['record'] = $this->record->updateRecordStatus($user['record']);
        $key = [
            'user_id' => $user['user_id']
        ];
        $put = [
            'TableName' => 'a_users',
            'Key' => $this->marshaler->marshalItem($key),
            'Item' => $this->marshaler->marshalItem($user),
        ];
        return $put;
    }
}
