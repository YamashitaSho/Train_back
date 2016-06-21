<?php
namespace App\Models;

use SplFileObject;

class CharLoader
{
    #このクラスで扱うファイルパス
    private $path = "../Dataset/Data/chars.csv";
    #敵の情報
    private $chars = [];


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
     * char_idをキーとする配列に整形する
     * @return array
     */
    public function importAll()
    {
        if (empty($this->chars)){
            $records = $this->getAll();
            foreach ($records as $key => $line){
                #インデックスの取得
                if ($key == 0){
                    $index = array_flip($line);
                    continue;
                }
                #データ整形
                $this->chars[$line[$index['char_id']]] = [
                    'char_id' => (int)$line[$index['char_id']],
                    'level' => (int)$line[$index['level']],
                    'exp' => (int)$line[$index['exp']],
                    'name' => $line[$index['name']],
                    'status' => [
                        'attack' => (int)$line[$index['attack']],
                        'endurance' => (int)$line[$index['endurance']],
                        'agility' => (int)$line[$index['agility']],
                        'debuf' => (int)$line[$index['debuf']],
                    ],
                    'status_growth_rate' => [
                        'attack' => (int)$line[$index['attack_growth_rate']],
                        'endurance' => (int)$line[$index['endurance_growth_rate']],
                        'agility' => (int)$line[$index['agility_growth_rate']],
                        'debuf' => (int)$line[$index['debuf_growth_rate']],
                    ],
                    'status_max' => [
                        'attack' => (int)$line[$index['attack_max']],
                        'endurance' => (int)$line[$index['endurance_max']],
                        'agility' => (int)$line[$index['agility_max']],
                        'debuf' => (int)$line[$index['debuf_max']],
                    ],
                    'weight' => (int)$line[$index['weight']]
                ];
            }
        }
        return $this->chars;
    }


    /**
     * キャラのマスタからガチャの重みの配列を取得し、返す。
     * @return array $weights char_idをキーとする重みの配列
     */
    public function getWeights()
    {
        $chars = $this->importAll();
        foreach($chars as $char){
            $weights[$char['char_id']] = $char['weight'];
        }
        return $weights;
    }


    /**
     * 指定されたchar_idのキャラを返す。
     * @return array $char キャラのステータス
     */
    public function getChar($char_id)
    {
        $chars = $this->importAll();
        return $chars[$char_id];
    }
}
