<?php

namespace App\Http\Controllers\Auth;

use App\Models\DynamoDBHandler;
use Aws\DynamoDB\Marshaler;
use App\Services\Common\Record;

class LoginControl extends DynamoDBHandler
{


    public function __construct()
    {
        parent::__construct();
        $this->marshaler = new Marshaler();
        $this->record = new Record();
    }


    public function loginService ($user)
    {
        $ids = $this->existGoogleId($user->id);
        if (empty($ids['user_id'])){
            $ids['user_id'] = $this->issueUserId();
            $this->saveNewGoogleId($user, $ids['user_id']);
            $this->saveNewUserRecord($ids['user_id']);
        };
        return $ids['user_id'];
    }


    /**
     * googleIdが新しいかを確認する
     */
    private function existGoogleId ($id)
    {
        $get = [
            'TableName' => 'a_auth',
            'Key' => [
                'google_id' => [
                    'S' => $id
                ]
            ]
        ];
        $result = $this->getItem($get);
        return $result;
    }


    /**
     * 新規ユーザーIDを発行する
     */
    private function issueUserId ()
    {
        $key = [
            'name' => [
                'S' => 'a_users'
            ]
        ];
        $expression_attribute_values = [
            ':value' => [
                'N' => '1'
            ],

        ];
        $update_expression = 'set current_number = current_number + :value';
        $update = [
            'TableName' => 'sequences',
            'Key' => $key,
            'UpdateExpression' => $update_expression,
            'ExpressionAttributeValues' => $expression_attribute_values,
            'ReturnValues' => 'ALL_NEW'
        ];
        $result = $this->updateItem($update);
        return $result['Attributes']['current_number']['N'];
    }


    /**
     * 新しいgoogleIdを保存する
     */
    private function saveNewGoogleId ($user, $user_id)
    {
        $key = [
            'google_id' => [
                'S' => $user->id
            ]
        ];
        $item = [
            'google_id' => [
                'S' => $user->id
            ],
            'name' => [
                'S' => $user->name
            ],
            'email' => [
                'S' => $user->email
            ],
            'user_id' => [
                'N' => $user_id
            ]
        ];
        $put = [
            'TableName' => 'a_auth',
            'Key' => $key,
            'Item' => $item
        ];
        $result = $this->putItem($put);
        return !boolval($result);
    }


    /**
     * 新しいUserレコードを作成する
     * 丸々テンプレートをコピーしてmarshalItemするとuser_idがString属性になり不可
     */
    private function saveNewUserRecord ($id)
    {
        $template = $this->getUserRecordTemplate();
        $template['record'] = $this->record->makeRecordStatus();
        $key = [
            'user_id' => [
                'N' => (string)$id
            ]
        ];
        $item = $this->marshaler->marshalItem($template);
        $item['user_id']['N'] = (string)$id;

        $put = [
            'TableName' => 'a_users',
            'Key' => $key,
            'Item' => $item
        ];
        $result = $this->putItem($put);
        return !boolval($result);
    }


    /**
     * Userレコードのテンプレートを取得する
     */
    private function getUserRecordTemplate ()
    {
        $key = [
        'user_id' => ['N' => '0']
        ];
        $get = [
            'TableName' => 'a_users',
            'Key' => $key
        ];
        $res = $this->getItem($get);
        return $res;
    }
}
