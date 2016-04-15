<?php
namespace App\Services\Common;

require '../vendor/autoload.php';
use Illuminate\Database\Eloquent\Model;

use Exception;
use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\Marshaler;

    /**
    * ダイナモDBにアクセスするためのクラス
    *
    * ダイナモDBに投げるリクエストをまとめたクラス
    */
class DynamoDBHandler extends Model
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
    * [関数] DynamoDBにgetItemを投げる関数
    *
    * エラー処理も引き受ける getItemの返り値はunMarshalizeして送り返す
    * @param $get : DynamoDBにそのままリクエストできる形式のarray
    * @param $exception エラー発生時のメッセージ
    */
    public function getItem($get, $exception)
    {
        try {
            $result = $this->dynamodb->getItem($get);
        } catch (DynamoDbException $e) {
            echo $e->getMessage();
            throw new Exception($exception);
        }
        return $this->marshaler->unmarshalItem($result['Item']);
    }

    /**
    * [関数] DynamoDBにbatchGetItemを投げる関数
    *
    * エラー処理も引き受ける batchGetItemの返り値はunMarshalizeして送り返す
    * @param $get : DynamoDBにそのままリクエストできる形式のarray
    * @param $exception エラー発生時のメッセージ
    */
    public function batchGetItem($get, $exception)
    {
        try {
            $result = $this->dynamodb->batchGetItem($get);
        } catch (DynamoDbException $e) {
            echo $e->getMessage();
            throw new Exception($exception);
        }
        $key_list = array_keys($result['Responses']);
        for ($i = 0; $i < count($key_list); $i++){
            for ($j = 0; $j < count($result['Responses'][ $key_list[$i] ]) ; $j++){
                $response[$key_list[$i]][$j] = $this->marshaler->unmarshalItem($result['Responses'][ $key_list[$i] ][$j] );
            }
        }
        return $response;
    }

    /**
    * [関数] DynamoDBにscanを投げる関数
    *
    * エラー処理も引き受ける scanの返り値はunMarshalizeして送り返す
    * @param $scan : DynamoDBにそのままリクエストできる形式のarray
    * @param $exception エラー発生時のメッセージ
    */
    public function scan($scan, $exception)
    {
        try {
            $result = $this->dynamodb->scan($scan);
        } catch (DynamoDbException $e) {
            echo $e->getMessage();
            throw new Exception($exception);
        }

        for ($i = 0; $i < count($result['Items']) ; $i++){
            $response[$i] = $this->marshaler->unmarshalItem($result['Items'][$i]);
        }
        return $response;
    }

    /**
    * [関数] DynamoDBにqueryを投げる関数
    *
    * エラー処理も引き受ける queryの返り値はunMarshalizeして送り返す
    * @param $query : DynamoDBにそのままリクエストできる形式のarray
    * @param $exception エラー発生時のメッセージ
    */
    public function queryItem($query, $exception)
    {
        try {
            $result = $this->dynamodb->query($query);
        } catch (DynamoDbException $e) {
            echo $e->getMessage();
            throw new Exception($exception);
        }

        for ($i = 0; $i < count($result['Items']) ; $i++){
            $response[$i] = $this->marshaler->unmarshalItem($result['Items'][$i]);
        }
        return $response;
    }

    /**
    * [関数] DynamoDBにputItemを投げる関数
    *
    * エラー処理も引き受ける
    * @param $get : DynamoDBにそのままリクエストできる形式のarray
    * @param $exception エラー発生時のメッセージ
    */
    public function putItem($put, $exception)
    {
        try {
            $result = $this->dynamodb->putItem($put);
        } catch (DynamoDbException $e) {
            echo $e->getMessage();
            throw new Exception($exception);
        }
        return;
    }
}
