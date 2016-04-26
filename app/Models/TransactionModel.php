<?php
namespace App\Models;

use DateTime;
use DateInterval;
use Aws\DynamoDb\Exception\DynamoDbException;
use App\Models\DynamoDBHandler;
use Aws\DynamoDb\Marshaler;

class TransactionModel extends DynamoDBHandler
{
    private $marshaler;
    private $QUEUE_TABLE = 'a_queue';
    private $ERROR_QUEUE_UNAVAILABLE = -1;

    private $RETRY_MAKE_QUEUE = 10;
    private $RETRY_MAX = 10;
    public function __construct()
    {
        parent::__construct();
        $this->marshaler = new Marshaler();
    }


    /**
    * [関数] Transaction処理が成功したかどうかを返す。
    *
    * @param  $user : ユーザー情報
    */
    public function isTransSuccess($user, $record, $requests)
    {
        $is_success = true;
        #キューを作成
        $queue_id = $this->makeQueue($user, $record, $requests);
        #キューを作れなかった
        if ($queue_id == $this->ERROR_QUEUE_UNAVAILABLE){
            $is_success = false;
        }
        #キューIDをユーザー情報に書き込み
        $this->updateQueueId($user['user_id'], $queue_id);
        #キュー情報に従ってトランザクション処理
        $result = $this->runTransaction($requests, $user['user_id'], $queue_id);
        #成否を代入

        return $is_success;
    }


    /**
    * [関数] QUEUE_TABLEにTransaction処理のレコードを作成し、キューIDを返す。
    *
    * queue_idは$userに保存されていたqueue_idからひとつ進めて利用する。
    * すでにレコードが存在していた場合はひとつqueue_idを進めて再試行する。
    */
    private function makeQueue($user, $record, $requests)
    {
        $queue_id = (int)$user['queue_id'] + 1;

        $key = [
            'user_id' => (int)$user['user_id'],
            'queue_id' => $queue_id
        ];
        $item = [
            'user_id' => (int)$user['user_id'],
            'queue_id' => $queue_id,
            'record' => $record,
            'requests' => $requests,
            'status' => 'C'
        ];
        #キーが存在しない場合は書き込まずにエラー
        $condition_expression = '(attribute_not_exists(queue_id))';
        $put = [
            'TableName' => $this->QUEUE_TABLE,
            'Key' => $this->marshaler->marshalItem($key),
            'Item' => $this->marshaler->marshalItem($item),
            'ConditionExpression' => $condition_expression
        ];

        for ($count = 0; $count < $this->RETRY_MAKE_QUEUE; $count++){
            try {
                $result = $this->putItem($put,'queue could not make');
                break;

            } catch (DynamoDBException $e) {

                #レコードが存在して書き込めない : queue_idを増やしてリトライ
                if ($e->getAwsErrorCode() == 'ConditionalCheckFailedException'){
                    $queue_id ++;
                    $key['queue_id'] = $queue_id;
                    $item['queue_id'] = $queue_id;
                    $put['key'] = $this->marshaler->marshalItem($key);
                    $put['Item'] = $this->marshaler->marshalItem($item);
                }
                #それ以外 : そのままリトライ
            }
        }
        # putItemが成功しなければ$resultは空のまま
        if (!isset($result)){
            $queue_id = $this->ERROR_QUEUE_UNAVAILABLE;
        }

        return $queue_id;
    }


    /**
    * 作成したキューIDをa_usersテーブルに書き込む
    */
    private function updateQueueId($user_id, $queue_id)
    {
        $key = [
            'user_id' => [
                'N' => (string)$user_id
            ]
        ];
        $expression_attribute_values = [
            ':queue' => [
                'N' => (string)$queue_id
            ]
        ];
        $update_expression = 'set queue_id = :queue';
        $update = [
            'TableName' => 'a_users',
            'Key' => $key,
            'ExpressionAttributeValues' => $expression_attribute_values,
            'UpdateExpression' => $update_expression
        ];
        $result = $this->updateItem($update);
        return true;
    }


    /**
    * トランザクション処理を行う
    * @return $is_success : 成功したかどうか
    */
    private function runTransaction($requests, $user_id, $queue_id)
    {
        $is_success = true;
        $lock       = true;
        $retry      = 0;
        #ロック解除が確認できるまでリトライ RETRY_MAXに達したら終わり
        for ($retry = 0; $retry < $this->RETRY_MAX ; $retry++){
            #更新先レコードの読込
            $present_data = $this->getPresentData($requests);
            #ロックの状態を判断
            $lock = $this->isLocked($present_data, $queue_id);
            if (!$lock){
                break;
            }
        }

        if (!$lock){
            #ロックが解除された
            while (true){
                #各レコードに書き込み
                $this->putRequest($requests,$queue_id);
                #書き込みが成功したかを判断
                $count = $this->isSucceeded($requests, $queue_id);
                if ($count == 0){
                    $is_success = true;
                    break;
                } elseif ($count == -1 || $retry == $this->RETRY_MAX){
                    break;
                }
                $retry++;
            }
            #ロックを解除
        }
        #書き込み成功
        if ($is_success){
            #掃除
            $this->unlock($requests);
            $this->releaseQueue($user_id, $queue_id, 'S');
        } else {
            #ロールバック
            $this->rollBack($user_id, $queue_id, $requests, $present_data);
        }
        #ロックされていた or 書き込み失敗
        if (($lock) || (!$is_success)){
            $this->releaseQueue($user_id, $queue_id, 'F');
        }
        return $is_success;
    }


    /**
    * [関数] 強い整合性での読み込みを行う
    *
    * キューID
    */
    private function getPresentData($requests)
    {
        $present_data = [];
        foreach ($requests as $request){
            $get = [
                'TableName' => $request['TableName'],
                'Key' => $request['Key'],
                'ConsistentRead' => true
            ];
            $present_data[] = $this->getItem($get);
        }
        return $present_data;
    }


    /**
    * [Method] データがロックされているかを返す
    */
    private function isLocked($present_data, $queue_id)
    {
        $lock = false;
        foreach ($present_data as $present){
            #自分のものでないロックが存在するか
            if ( (isset($present['Lock'])) && ($present['Lock'] != $queue_id)){
                #タイムスタンプが新しいか
                if ( (isset($present['record']['update_date']) ) &&
                    ( $this->isNew($present['record']['update_date']) )){
                    $lock = true;
                    break;
                }
            }
        }
        return $lock;
    }


    /**
    * [Method] タイムスタンプの日時が新しいかどうかを判定する
    * @param   $update_at  : format YmdHis
    * @param   $stamp_date : Date on Timestamp
    * @param   $target_date: Time
    */
    private function isNew($update_at)
    {
        date_default_timezone_set('Asia/Tokyo');
        $is_new = false;

        $format = 'YmdHis';
        $stamp_date = DateTime::createFromFormat($format,$update_at);

        $target_date = new DateTime;
        $interval = DateInterval::createFromDateString('-30 seconds');
        $target_date->add($interval);

        #タイムスタンプと現在時刻を比較する 新しければtrue
        $diff = $stamp_date->diff($target_date);
        $is_new = ($diff->format('$R') == '+') ? true : false;

        return $is_new;
    }


    /**
     * [Method] 書き込みを実行する
     *
     */
    private function putRequest($requests, $queue_id)
    {

        foreach($requests as $request){
            if (isset($request['Item'])){
                $request['Item']['Lock'] = ['N' => (string)$queue_id];
                $this->putItem($request);
            }
            if (isset($request['UpdateExpression'])){
                $request['ExpressionAttributeValues'] += [
                    ':queue_id' => [
                        'N' => (string)$queue_id
                    ]
                ];
                if (isset($request['ExpressionAttributeNames'])){
                    $request['ExpressionAttributeNames'] += [
                        '#lk' => 'Lock'
                    ];
                } else {
                    $request['ExpressionAttributeNames'] = [
                        '#lk' => 'Lock'
                    ];
                }
                $request['UpdateExpression'] = $request['UpdateExpression'].', #lk = :queue_id';
               # dd($request);
                $this->updateItem($request);
            }
        }
        return ;
    }


    /**
     *  [Method]
     *
     * @param  $status : まだ書き込むべき項目数
     */
    private function isSucceeded($requests, $queue_id)
    {
        $status = 0;
        foreach ($requests as $request){
            $lock = $this->getLock($request['TableName'], $request['Key']);
            if (isset($lock)){
                if ($lock == $queue_id){
                    #自分のキューIDがあっても何もしない
                } else {
                    #他のqueue_idが見つかったら異常を返す
                    $status = -1;
                    break;
                }
            } else {
                #書き込みしていない項目の数をカウントする
                $status ++;
            }
        }
        return $status;
    }


    /**
     * [Method] 書き込みしたレコードのロックを解除する
     */
    private function unlock($requests)
    {
        foreach ($requests as $request){
            $update = [
                'TableName' => $request['TableName'],
                'Key' => $request['Key'],
                'UpdateExpression' => 'remove #lk',
                'ExpressionAttributeNames' => [
                    '#lk' => 'Lock'
                ]
            ];
            $this->updateItem($update);
        }
        return ;
    }


    /**
     * [Method] キューのステータスを更新する
     */
    private function releaseQueue($user_id, $queue_id, $status)
    {
        $update = [
            'TableName' => 'a_queue',
            'Key' => [
                'user_id' => [
                    'N' => (string)$user_id
                ],
                'queue_id' => [
                    'N' => (string)$queue_id
                ]
            ],
            'ExpressionAttributeValues' => [
                ':status' => [
                    'S' => $status
                ]
            ],
            'UpdateExpression' => 'set #st = :status',
            'ExpressionAttributeNames' => [
                '#st' => 'status'
            ]
        ];
        $this->updateItem($update);
        return ;
    }


    /**
     * [Method] 項目をロールバックする
     */
    private function rollBack($user_id, $queue_id, $requests, $present_data)
    {
       foreach ($requests as $key => $request){
            $lock = $this->getLock($request['TableName'], $request['Key']);
            if (isset($lock)){
                if ($lock == $queue_id){
                    #自分のキューIDがあったらロールバック
                    if (empty($present_data[$key])){
                        #成功するまで項目削除のリトライ
                    } else {
                        #成功するまで以前の項目を書き込む
                    }
                } else {
                    #他のqueue_idについては無視
                }
            } else {
                #書き込みしていない項目についても無視
            }
        }
        return ;
    }

    /**
     * [Method] 一つのレコードについてロック状態を読み込む
     */
    private function getLock($tablename, $key)
    {
        $get = [
            'TableName' => $tablename,
            'Key' => $key,
            'ProjectionExpression' => '#lk',
            'ConsistentRead' => true,
            'ExpressionAttributeNames' => [
                '#lk' => 'Lock'
            ]
        ];
        $result = $this->getItem($get);
        $lock = (isset($result['Lock'])) ? $result['Lock'] : null;
       # dd($lock);
        return $lock;
    }

    /**
     * [Method] バックアップからリストアする(失敗を許さない)
     */
    private function restoreBackUp($tablename, $key, $present_data)
    {
        $put = [
            'TableName' => $tablename,
            'Key' => $key,
            'Item' => $present_data
        ];
        do{
            try {
                $this->putItem($put);
            } catch (DynamoDbException $e){
                usleep(100000);
                continue;
            }
        } while (false);
        return ;
    }
}
