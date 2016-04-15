<?php
namespace App\Services;

require '../vendor/autoload.php';
use Illuminate\Database\Eloquent\Model;

use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\Marshaler;
use App\Services\UserInfo;

class MenuLogic extends Model
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
        $this->userinfo = new UserInfo();
    }
    /**
    * [API] ユーザー情報を取得するAPIで呼ばれる関数
    */
    public function getMenu()
    {
        $user_id = $this->userinfo->getUserID();
        $user = $this->userinfo->getUserStatus($user_id);

        $response = $user;

        return array($response, 200);
    }
}
