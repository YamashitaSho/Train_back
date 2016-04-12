<?php
namespace App\Services;

require '../vendor/autoload.php';
use Illuminate\Database\Eloquent\Model;

class Record extends Model
{
    public function __construct()
    {
        date_default_timezone_set('Asia/Tokyo');
    }
    /**
    *トランザクションテーブルのレコード情報を作る関数
    *
    * レコードを作成する時に要素の一つとして追加する
    */
    public function makeRecordStatus()
    {
        $date = date("YmdHis");
        $record = [
            'create_date' => $date,
            'update_date' => $date,
            'deleted' => false
        ];
        return $record;
    }

    public function updateRecordStatus($record)
    {
        $date = date("YmdHis");
        $record['update_date'] = $date;
        return $record;
    }
}
