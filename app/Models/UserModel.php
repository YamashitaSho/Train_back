<?php
namespace App\Models;

use Aws\DynamoDb\Marshaler;
use App\Models\DynamoDBHandler;
use App\Services\Common\Record;

    /**
    * [CLASS]ユーザー情報に関わるクラス
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
        $this->marshaler = new Marshaler();
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
     * @return object DynamoDBの更新結果
     */
    public function updateUser($user)
    {
        $put = $this->getQueryPutUser($user);
        return $this->putItem($put);
    }


    /**
     * ユーザー更新情報の作成
     * @param array $user 更新後のユーザーデータ
     * @return array $put ユーザーデータを更新する命令
     */
    public function getQueryPutUser($user)
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


    /**
     * ユーザー更新情報の作成
     * @param array $user 更新後のユーザーデータ
     * @param int $cost 消費したmoney
     * @return array $put ユーザーデータを更新する命令
     */
    public function getQueryUpdateUserUseMoney($user, $cost)
    {
        $user['record'] = $this->record->updateRecordStatus($user['record']);
        $key = [
            'user_id' => $user['user_id']
        ];
        $expression_attribute_values = [
            ':cost' => [
                'N' => (string)$cost
            ],
            ':record' => [
                'S' => (string)$user['record']['update_date']
            ]
        ];
        $update_expression = 'set money = money - :cost, #rec.update_date = :record';
        $expression_attribute_names = [
            '#rec' => 'record'
        ];
        $update = [
            'TableName' => 'a_users',
            'Key' => $this->marshaler->marshalItem($key),
            'ExpressionAttributeValues' => $expression_attribute_values,
            'UpdateExpression' => $update_expression,
            'ExpressionAttributeNames' => $expression_attribute_names
        ];
        return $update;
    }
}
