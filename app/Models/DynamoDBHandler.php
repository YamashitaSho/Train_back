<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Aws\DynamoDb\Marshaler;

    /**
    * ダイナモDBにアクセスするためのクラス
    *
    * ダイナモDBに投げるリクエストをまとめたクラス
    * エラー処理は各モデルに任せる方針に変更:20160426
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
    * getItemの返り値はunMarshalizeして送り返す
    * @param $get : DynamoDBにそのままリクエストできる形式のarray
    * @param $exception エラー発生時のメッセージだった 互換性を保ったままテストするために残している
    */
    public function getItem($get, $exception = '')
    {
        $response = [];
        $result = $this->dynamodb->getItem($get);

        if ( !empty($result['Item']) ){
            $response = $this->marshaler->unmarshalItem($result['Item']);
        }
        return $response;
    }

    /**
    * [関数] DynamoDBにbatchGetItemを投げる関数
    *
    * batchGetItemの返り値はunMarshalizeして送り返す
    * @param $get : DynamoDBにそのままリクエストできる形式のarray
    * @param $exception エラー発生時のメッセージ 互換性を保ったままテストするために残している
    */
    public function batchGetItem($get, $exception = '')
    {
        $response = [];
        $result = $this->dynamodb->batchGetItem($get);

        $key_list = array_keys($result['Responses']);

        $key_num = count($key_list);
        for ($i = 0; $i < $key_num; $i++){

            $result_num = count($result['Responses'][ $key_list[$i] ]);
            for ($j = 0; $j < $result_num ; $j++){

                $response[$key_list[$i]][$j] =
                    $this->marshaler->unmarshalItem($result['Responses'][ $key_list[$i] ][$j] );
            }
        }
        return $response;
    }

    /**
    * [関数] DynamoDBにscanを投げる関数
    *
    * scanの返り値はunMarshalizeして送り返す
    * @param $scan : DynamoDBにそのままリクエストできる形式のarray
    * @param $exception エラー発生時のメッセージ 互換性を保ったままテストするために残している
    */
    public function scan($scan, $exception = '')
    {
        $response = [];

        $result = $this->dynamodb->scan($scan);

        for ($i = 0; $i < $result['Count'] ; $i++){
            $response[$i] = $this->marshaler->unmarshalItem($result['Items'][$i]);
        }
        return $response;
    }

    /**
    * [関数] DynamoDBにqueryを投げる関数
    *
    * queryの返り値はunMarshalizeして送り返す
    * @param $query : DynamoDBにそのままリクエストできる形式のarray
    * @param $exception エラー発生時のメッセージ 互換性を保ったままテストするために残している
    */
    public function queryItem($query, $exception = '')
    {
        $result = $this->dynamodb->query($query);

        if ($result['Count'] > 0){
            for ($i = 0; $i < $result['Count'] ; $i++){
                $response[$i] = $this->marshaler->unmarshalItem($result['Items'][$i]);
            }
        } else {
            $response = [];
        }
        return $response;
    }

    /**
    * [関数] DynamoDBにputItemを投げる関数
    *
    * @param $get : DynamoDBにそのままリクエストできる形式のarray
    * @param $exception エラー発生時のメッセージ 互換性を保ったままテストするために残している
    */
    public function putItem($put, $exception = '')
    {
        $result = $this->dynamodb->putItem($put);
        return $result;
    }

    /**
    * [関数] DynamoDBにupdateItemを投げる関数
    */
    public function updateItem($update)
    {
        $result = $this->dynamodb->updateItem($update);
        return $result;
    }


    /**
     * [Method] DynamoDBにdeleteItemを投げる関数
     */
    public function deleteItem($delete)
    {
        $result = $this->dynamodb->deleteItem($delete);
        return $result;
    }
}
