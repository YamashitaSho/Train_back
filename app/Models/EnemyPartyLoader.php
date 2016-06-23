<?php
namespace App\Models;

use SplFileObject;

class EnemyPartyLoader
{
    #このクラスで扱うファイルパス
    private $path = "../Dataset/enemyparties.csv";
    #敵の情報
    private $enemyparties = [];


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
     * 扱いやすい形式に整形する
     * @return array
     */
    public function importAll()
    {
        if (empty($this->enemyparties)){
            $records = $this->getAll();
            foreach ($records as $key => $line){
                #インデックスの取得
                if ($key == 0){
                    $index = array_flip($line);
                    continue;
                }
                #データ整形
                $this->enemyparties[$line[$index['enemyparty_id']]] = [
                    'enemyparty_id' => $line[$index['enemyparty_id']],
                    'text' => $line[$index['text']],
                    'party' => [
                        ['enemy_id' => $line[$index['enemy_id1']]],
                        ['enemy_id' => $line[$index['enemy_id2']]],
                        ['enemy_id' => $line[$index['enemy_id3']]]
                    ],
                    'weight' => $line[$index['weight']]
                ];
            }
        }
        return $this->enemyparties;
    }


    /**
     * 敵パーティIDの配列から敵パーティ情報の配列を返す
     * @param array $enemyparty_ids 敵パーティIDの配列
     * @return array $res 敵パーティの配列
     */
    public function getPartyStatus($enemyparty_ids)
    {
        $enemyparties = $this->importAll();
        foreach($enemyparty_ids as $enemyparty_id){
            $res[] = $enemyparties[$enemyparty_id];
        }
        return $res;
    }
}
