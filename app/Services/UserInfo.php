<?php
namespace App\Services;

require '../vendor/autoload.php';
use Illuminate\Database\Eloquent\Model;

use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\Marshaler;

    /**
    * ユーザー情報に関わるクラス
    *
    * ユーザーデータにアクセスする方法が共通なものをまとめたクラス。
    */
class UserInfo extends Model
{
    private $dynamodb;
    private $marshaler;
    public function __construct()
    {
        $sdk = new \Aws\Sdk([
            'region'   => 'ap-northeast-1',
            'version'  => 'latest'
        ]);
        date_default_timezone_set('UTC');
        $this->dynamodb = $sdk->createDynamoDb();
        $this->marshaler = new Marshaler();
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

        try {
            $result = $this->dynamodb->getItem($get);
        } catch (DynamoDbException $e) {
            echo "ユーザー情報を取得することができませんでした。:\n";
            echo $e->getMessage() . "\n";
            $result = array("Unable to get UserStatus");
        }

        $response = $this->marshaler->unmarshalItem($result['Item']);
        return $response;
    }
}
