<?php
namespace App\Services;

require '../vendor/autoload.php';
use Illuminate\Database\Eloquent\Model;

use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\Marshaler;

class OrderLogic extends Model
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
	public function getOrder()
	{
		//編成画面に必要な各情報を取得し、送信する。
	/*	$tableName = 'Movies';
		$eav = $this->marshaler->marshalJson('
		    {
		        ":yyyy": 1965
		    }
		');

		$params = [
		    'TableName' => $tableName,
		    'KeyConditionExpression' => '#yr = :yyyy',
		    'ExpressionAttributeNames'=> [ '#yr' => 'year' ],
		    'ExpressionAttributeValues'=> $eav
		];

		echo "Querying for movies from 1985.\n";

		try {
		    $result = $this->dynamodb->query($params);

		 //   echo $result;

		    foreach ($result['Items'] as $movie) {
		        echo $this->marshaler->unmarshalValue($movie['year']) . ': ' .
		            $this->marshaler->unmarshalValue($movie['title']) . "<br />";
		    }

		    $buf = array();
		    $json = array();
		     foreach ($result['Items'] as $key=>$movie) {
		     //	dd ($movie);
		        $buf['year'] = $this->marshaler->unmarshalValue($movie['year']);
		        $buf['title'] = $this->marshaler->unmarshalValue($movie['title']);
		        $buf['actors'] = $this->marshaler->unmarshalValue($movie['info']['M']['actors']);
		        $json[] = $buf;
		    }

		    echo json_encode($json);

		} catch (DynamoDbException $e) {
		    echo "Unable to query:\n";
		    echo $e->getMessage() . "\n";
		}*/
		//
		$party = array(
			"a_char_id1" => 1,
			"a_char_id2" => 2,
			"a_char_id3" => 3,
			"item_id1" => 1,
			"item_id2" => 2,
			"item_id3" => 3
		);
		$chars = array(
			array(
				"a_char_id1" => 1,
				"char_id" => 2,
				"name" => "丑",
				"is_equipped" => true,
				"attack" => 25,
				"endurance" => 45,
				"agility" => 30,
				"debuf" => 15
			)
		);
		$items = array(
			array(
				"item_id" => 2,
				"name" => "初心者",
				"is_equipped" => true,
				"attack" => 0,
				"endurance" => 5,
				"agility" => 0,
				"debuf" => 0
			)
		);
		$response = array(
			"party" => $party,
			"chars" => $chars,
			"items" => $items
		);
		return array($response,200);
	}
	public function changeOrder($type)
	{
		$response_header = 200;  //HTTPレスポンスヘッダ
		$in_use = true;          //使用中フラグ 使用中:true
		//$type == itemならば
		//アイテムIDを所持しているか、使用中かどうかをチェックする
		//所持していないor使用中→BadRequest
		//else→パーティの装備アイテムに設定する
		if ($type == "item"){
			//ここで使用チェックを$in_useに代入
		} elseif ($type == "char"){
			//使用チェックを$in_useに代入
		}
		if ($in_use) {
			return array("Already In Use", 400);
		}
//		echo $type."\n";
		//$type == charならば
		//投げられたキャラIDが使用中かどうかをチェックする
		//使用中ならば→BadRequestを返す
		//使用中でない→元のキャラの使用中ステータスを取り下げ、新しいキャラに使用中ステータスをつける

		$response = array(
			"result" => true
		);
		//echo json_encode($response, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
		/*
		$tableName = 'Movies';
		$eav = $this->marshaler->marshalJson('
		{
		":yyyy": 1964
		}
		');

		$params = [
		    'TableName' => $tableName,
		    'KeyConditionExpression' => '#yr = :yyyy',
		    'ExpressionAttributeNames'=> [ '#yr' => 'year' ],
		    'ExpressionAttributeValues'=> $eav
		];

//		echo "Querying for movies from 1985.\n";

		try {
		    $result = $this->dynamodb->query($params);

		 //   echo $result;

		  /*  foreach ($result['Items'] as $movie) {
		        echo $this->marshaler->unmarshalValue($movie['year']) . ': ' .
		            $this->marshaler->unmarshalValue($movie['title']) . "<br />";
		    }

		    $buf = array();
		    $json = array();
		     foreach ($result['Items'] as $key=>$movie) {
		     //	dd ($movie);
		        $buf['year'] = $this->marshaler->unmarshalValue($movie['year']);
		        $buf['title'] = $this->marshaler->unmarshalValue($movie['title']);
		        $buf['actors'] = $this->marshaler->unmarshalValue($movie['info']['M']['actors']);
		        $json[] = $buf;
		    }

		    echo json_encode($json);

		} catch (DynamoDbException $e) {
		    echo "Unable to query:\n";
		    echo $e->getMessage() . "\n";
		}
		*/
		return array($response,201);
	}
}
