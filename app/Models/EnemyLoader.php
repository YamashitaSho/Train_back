<?php
namespace App\Models;

use SplFileObject;

class EnemyLoader
{
    #このクラスで扱うファイルパス
    private $path = "../Dataset/Data/enemies.csv";
    #敵の情報
    private $enemies = [];


    /**
     * 敵の個体情報CSVを読み込み、返す
     * データは配列の配列として代入される
     * @return array
     */
    public function getAll()
    {
        $file = new SplFileObject($this->path);
        $file->setFlags(SplFileObject::READ_CSV);
        foreach ($file as $key => $line){
            if (!is_null($line[0])){
                $records[] = $line;
            }
        }
        return $records;
    }


    /**
     * すべての敵の個体情報の読み出し
     * enemy_idをキーとする配列に整形する
     * @return array
     */
    public function importAll()
    {
        if (empty($this->enemies)){
            $records = $this->getAll();
            foreach ($records as $key => $line){
                #インデックスの取得
                if ($key == 0){
                    $index = array_flip($line);
                    continue;
                }
                #データ整形
                $this->enemies[$line[$index['enemy_id']]] = [
                    'enemy_id' => $line[$index['enemy_id']],
                    'char_id' => $line[$index['char_id']],
                    'level' => $line[$index['level']],
                    'exp' => $line[$index['exp']],
                    'name' => $line[$index['name']],
                    'status' => [
                        'attack' => $line[$index['attack']],
                        'endurance' => $line[$index['endurance']],
                        'agility' => $line[$index['agility']],
                        'debuf' => $line[$index['debuf']],
                    ]
                ];
            }
        }
        return $this->enemies;
    }


    /**
     * 敵パーティ情報から敵の個体情報を返す
     * @param array $enemy_ids 敵IDの配列
     * @return array $res 敵情報の配列
     */
    public function getEnemyStatus($enemy_ids)
    {
        $enemies = $this->importAll();
        foreach($enemy_ids as $enemy_id){
            $res[] = $this->enemies[$enemy_id];
        }
        return $res;
    }
}
