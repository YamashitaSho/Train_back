<?php
namespace App\Services\Common;

require '../vendor/autoload.php';
use Illuminate\Database\Eloquent\Model;

class Record extends Model
{
    /**
    *トランザクションテーブルのレコード情報を作る関数
    *
    * レコードを作成する時に要素の一つとして追加する
    */
    public function makeRecordStatus()
    {
        #タイムスタンプは日本時間で作る
        date_default_timezone_set('Asia/Tokyo');
        $date = date('YmdHis');     #YYYYMMDD24mmss
        $record = [
            'create_date' => $date,
            'update_date' => $date,
            'deleted' => false
        ];
        return $record;
    }

    public function updateRecordStatus($record)
    {
        #タイムスタンプは日本時間で作る
        date_default_timezone_set('Asia/Tokyo');
        $date = date('YmdHis');
        $record['update_date'] = $date;
        return $record;
    }
}
