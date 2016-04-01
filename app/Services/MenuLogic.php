<?php
namespace App\Services;

require '../vendor/autoload.php';
use Illuminate\Database\Eloquent\Model;

use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\Marshaler;

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
	}
	public function getMenu()
	{
	/*	$tableName = 'Movies';
	/*	$eav = $this->marshaler->marshalJson('
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

//		try {
//		    $result = $this->dynamodb->query($params);*/

//UserIDはセッション情報から取得する
//全てUsersテーブルに入れておくのが良さそう
		$response = array (
		    "name"  => "ほげ",
		    "money" => 3000,
		    "medal" => "初心者",
		    "char_id" => 2,
		    "quest_count" => 16
		);

		    return array($response, 200);

	/*	} catch (DynamoDbException $e) {
		    echo "Unable to query:\n";
		    echo $e->getMessage() . "\n";
		}*/
	}
}
