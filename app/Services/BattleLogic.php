<?php
namespace App\Services;

require '../vendor/autoload.php';
use Illuminate\Database\Eloquent\Model;

use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\Marshaler;

class BattleLogic extends Model
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
	public function setBattle($battle_id)
	{
		//該当バトルIDの戦闘を進行する。
        //戦闘初期状態を読み込み、送信する
		//該当バトルのステータスを進行中に変更し、キャラのステータスに反映する
		//該当バトルが進行中だった場合はバトル開始情報のみを送信する
		//終了済みだった場合はエラーを返す

        #battle_chars 0~2:味方 3~5:敵
        $battle_chars = array(
                array(
                    "position" => "friend1",
                    "char_id" => 1,
                    "name" => "子",
                    "attack" => 50,
                    "endurance" => 30,
                    "agility" => 30,
                    "debuf" => 30,
                    "exp" => 50,
                    "level" => 4
                ),
                array(
                    "position" => "friend2",
                    "char_id" => 2,
                    "name" => "丑",
                    "attack" => 50,
                    "endurance" => 30,
                    "agility" => 30,
                    "debuf" => 30,
                    "exp" => 50,
                    "level" => 4
                ),
                array(
                    "position" => "friend3",
                    "char_id" => 3,
                    "name" => "寅",
                    "attack" => 50,
                    "endurance" => 30,
                    "agility" => 30,
                    "debuf" => 30,
                    "exp" => 50,
                    "level" => 4
                ),
                array(
                    "position" => "enemy1",
                    "char_id" => 4,
                    "name" => "卯",
                    "attack" => 50,
                    "endurance" => 30,
                    "agility" => 30,
                    "debuf" => 30,
                    "exp" => 50,
                    "level" => 4
                ),
                array(
                    "position" => "enemy2",
                    "char_id" => 5,
                    "name" => "辰",
                    "attack" => 50,
                    "endurance" => 30,
                    "agility" => 30,
                    "debuf" => 30,
                    "exp" => 50,
                    "level" => 4
                ),
                array(
                    "position" => "enemy3",
                    "char_id" => 6,
                    "name" => "巳",
                    "attack" => 50,
                    "endurance" => 30,
                    "agility" => 30,
                    "debuf" => 30,
                    "exp" => 50,
                    "level" => 4
                )
        );
        $response = array(
            "type" => "quest",
            "is_win" => true,
            "friend_position" => array(
                array(
                    "char_id" => 1,
                    "name" => "子",
                    "attack" => 30,
                    "endurance" => 30,
                    "agility" => 30,
                    "debuf" => 30,
                    "exp" => 50,
                    "level" => 4
                ),
                array(
                    "char_id" => 2,
                    "name" => "丑",
                    "attack" => 30,
                    "endurance" => 30,
                    "agility" => 30,
                    "debuf" => 30,
                    "exp" => 50,
                    "level" => 4
                ),
                array(
                    "char_id" => 3,
                    "name" => "寅",
                    "attack" => 30,
                    "endurance" => 30,
                    "agility" => 30,
                    "debuf" => 30,
                    "exp" => 50,
                    "level" => 4
                )
            ),
            "enemy_position" => array(
                array(
                    "char_id" => 4,
                    "name" => "卯",
                    "attack" => 30,
                    "endurance" => 30,
                    "agility" => 30,
                    "debuf" => 30,
                    "exp" => 50,
                    "level" => 4
                ),
                array(
                    "char_id" => 5,
                    "name" => "辰",
                    "attack" => 50,
                    "endurance" => 30,
                    "agility" => 30,
                    "debuf" => 30,
                    "exp" => 50,
                    "level" => 4
                ),
                array(
                    "char_id" => 6,
                    "name" => "巳",
                    "attack" => 30,
                    "endurance" => 30,
                    "agility" => 30,
                    "debuf" => 30,
                    "exp" => 50,
                    "level" => 4
                )
            )
        );

        $turndata = array(
            "turn" => 0,
            "is_last_turn" => false,
            "friend_hp" => 100,
            "enemy_hp" => 100
        );
        #初期HP設定
        foreach ($response["friend_position"] as $char_status){
            $turndata["friend_hp"] += $char_status["endurance"];
        }
        foreach ($response["enemy_position"] as $char_status){
            $turndata["enemy_hp"] += $char_status["endurance"];
        }

        $battle_log[0] = $turndata;
        #戦闘処理
        #ターンループ カウント:$turn
        $turn = 1;
        while($turndata["is_last_turn"] == false){
            $turndata["turn"] = $turn;
            if ($turn == 5){
                $turndata["is_last_turn"] = true;              #5ターンで強制終了
            }
            #このターンのみの一時的なステータスを(再)定義する
            $chars_turn = $battle_chars;
     //   return array($chars_turn, 201);
            #このターンにおける行動スピード、基礎ダメージ、デバフ能力を決定
            $count = 0;
            $sequence = array();                               #$sequenceは行動順管理用の配列
            foreach ($chars_turn as &$char_status){
                $char_status["attack"] = ($char_status["attack"]) + mt_rand(0 - $char_status["attack"]/8, $char_status["attack"]/8);
                $char_status["agility"] = $char_status["agility"] + mt_rand(0, $char_status["agility"]/3);
                $char_status["debuf"] = ($char_status["debuf"]) - mt_rand($char_status["debuf"]*3/8, $char_status["debuf"]*5/8);
                $sequence[$count]["position"] = $count;
                $sequence[$count]["speed"] = $char_status["agility"];
                $count++;
            }
            unset($char_status);
            #$sequenceを要素"speed"が大きい順にソートする(行動する順にpositionが入った配列になる)
            usort($sequence, function($a, $b){
                return $a["speed"] < $b["speed"];
            });

            #各キャラ行動(ループで)
            $action_count = 0;
            $turndata["action"] = array();


            foreach ($chars_turn as $char_status){
                $position = $sequence[$action_count]["position"];      #現在行動しているポジション
                echo $position." ";
                $turndata["action"][$action_count]["position"] = $chars_turn[$position]["position"];
                $turndata["action"][$action_count]["damage"] = $chars_turn[$position]["attack"];

                #友軍の処理
                if (strstr($chars_turn[$position]["position"], "friend")){
                    #攻撃処理
                    $turndata["enemy_hp"] -= $chars_turn[$position]["attack"];

                    #デバフ処理
                    $debuf = $chars_turn[$position]["debuf"];
                    for ($i=0 ; $i<3 ; $i++){
                        $chars_turn[$i+3]["attack"] -= $debuf;
                        if ($chars_turn[$i+3]["attack"] < 0 ){ $chars_turn[$i+3]["attack"] = 0;}
                        $turndata["action"][$action_count]["debuf"][$i] = $debuf;
                    }

                    #戦闘終了判定
                    if ($turndata["enemy_hp"] <= 0){
                        $turndata["enemy_hp"] = 0;
                        $response["is_win"] = true;
                        $turndata["is_last_turn"] = true;
                        break;
                    }
                }
                #敵軍の処理
                if (strstr($chars_turn[$position]["position"], "enemy")){
                    #攻撃処理
                    $turndata["friend_hp"] -= $chars_turn[$position]["attack"];

                    #デバフ処理
                    $debuf = $chars_turn[$position]["debuf"];
                    for ($i=0 ; $i<3 ; $i++){
                        $chars_turn[$i]["attack"] -= $debuf;
                        if ($chars_turn[$i]["attack"] < 0 ){ $chars_turn[$i]["attack"] = 0;}
                        $turndata["action"][$action_count]["debuf"][$i] = $debuf;
                    }

                    #戦闘終了判定
                    if ($turndata["friend_hp"] <= 0){
                        $turndata["friend_hp"] = 0;
                        $response["is_win"] = false;
                        $turndata["is_last_turn"] = true;
                        break;
                    }
                }
                $action_count ++;
            }
            unset($char_status);
            echo "\n";
            $battle_log[$turn] = $turndata;
            $turn ++;
        }

		return array($battle_log, 201);
	}
	public function turnoverBattle($battle_id)
	{
		//進行中のバトルについて、戦闘終了情報が入力されるか5ターン経過するまでのデータを取得し、送信する。
		/*
		$tableName = 'a_battles';
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
		    return array($response, 200);
		} catch (DynamoDbException $e) {
		    echo "Unable to query:\n";
		    echo $e->getMessage() . "\n";
		}*/
        $response = array(                     #連想配列の配列
            array(
                #毎ターン存在するもの
                "turn" => 1,                   #DB上における turn == 0 は初期状態
                "is_last_turn" => true,        #最終ターンか
                "hp" => 190,
                "enemy_hp" => 190,
                #初期ターンは存在しないもの
                "action" => array(
                    array(
                        "position" => "enemy2", // position? or enemy_position?
                        "damage" => 24,                      // HP減少量
                        "debuf" => array(
                            10,
                            8,
                            11
                        )
                    ),
                    array(
                        "position" => "friend1", // position? or enemy_position?
                        "damage" => 18,                      // HP減少量
                        "debuf" => array(
                            16,
                            12,
                            15
                        )
                    )
                )
            )
        );
    return array($response, 200);
    }
}
