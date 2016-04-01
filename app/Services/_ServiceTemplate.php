<?php
namespace App\Services;

require '../vendor/autoload.php';
use Illuminate\Database\Eloquent\Model;

use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\Marshaler;

class My extends Model
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
	public function getName()
	{
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

		  /*  foreach ($result['Items'] as $movie) {
		        echo $this->marshaler->unmarshalValue($movie['year']) . ': ' .
		            $this->marshaler->unmarshalValue($movie['title']) . "<br />";
		    }*/

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
	}
}
