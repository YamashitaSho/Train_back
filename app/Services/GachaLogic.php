<?php
namespace App\Services;

require '../vendor/autoload.php';
use Illuminate\Database\Eloquent\Model;

use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\Marshaler;

class GachaLogic extends Model
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


	public function checkGacha()
    {
        //ガチャが引ける状態かどうかチェックし、情報を送信する

        ///DBから読み込む
        $money = 4800;
        $rest_char = 4;

        $gacha_cost = 1000;            #$rest_charにより決定される
        if ($rest_char == 0){
            $gacha_cost = -1;
        }
        ///

        $response = array(
            "availability" => true,
            "rest_char" => $rest_char,
            "gacha_cost" => $gacha_cost,
            "money" => $money
        );

        #引けない条件を満たした場合はfalse
        if ( ($money < $gacha_cost) or $rest_char == 0){
            $response["availability"] = false;
        }

        return array($response,200);

	}


	public function drawGacha()
	{
		//ガチャを引く
/*		$tableName = 'Movies';
		$eav = $this->marshaler->marshalJson('
		    {
		        ":yyyy": 1992
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
		}*/

		$response = array(
			"char_id" => 2,
			"name" => "丑",
			"attack" => 25,
			"endurance" => 45,
			"agility" => 30,
			"debuf" => 15
		);
		return array($response,201);
	}
}
