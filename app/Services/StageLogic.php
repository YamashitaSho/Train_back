<?php
namespace App\Services;

require '../vendor/autoload.php';
use Illuminate\Database\Eloquent\Model;

use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\Marshaler;

class StageLogic extends Model
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
	public function getBattlelist()
	{
		/*
		$tableName = 'Movies';
		$eav = $this->marshaler->marshalJson('
		    {
		        ":yyyy": 1985
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
		$response = array(
			"money" => 4800,
			"chars" => array(
				array(
				"a_char_id" => 1,
				"char_id" => 2,
				"name" => "丑",
				"attack" => 25,
				"endurance" => 45,
				"agility" => 30,
				"debuf" => 15
				),
				array(
				"a_char_id" => 1,
				"char_id" => 2,
				"name" => "丑",
				"attack" => 25,
				"endurance" => 45,
				"agility" => 30,
				"debuf" => 15
				)
			),
			"stages" => array(
				"stage_id" => 3,
				"title" => "初級",
				"entry_fee" => 100,
				"prize" => 200,
				"item_name" => "初級者卒業",
				"clearcount" => 2
			)
		);
		return array($response,200);
	}

	public function joinBattle()
	{
		$json_string = file_get_contents("php://input");

//		echo $json_string;
		$request = json_decode($json_string,true);
//		var_dump($request);
//		echo "stage_id:".$request["stage_id"];

		//$request["stage_id"]を用いてDBに問い合わせ、バトルを実行、battleIDを発行する

		$response = array(
			"battle_id" => 1
		);
		return array($response,201);
	}
}
