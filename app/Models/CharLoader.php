<?php
namespace App\Models;

use SplFileObject;

class CharLoader
{
    #このクラスで扱うファイルパス
    private $path = "../Dataset/chars.csv";
    #敵の情報
    private $chars = [];
    private $statuses = [];


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
        $all_chars = $this->importAll();
        foreach($all_chars as $char){
            $weights[$char['char_id']] = $char['weight'];
        }
        return $weights;
    }


    /**
     * 指定されたchar_idのキャラを返す。
     * @param int $char_id キャラID
     * @return array $char キャラのステータス
     */
    public function getChar($char_id)
    {
        $index = $this->customIndexes('status');
        $all_chars = $this->importAll();
        $char = $this->narrowingIndex($all_chars[$char_id], $index);
        return $char;
    }


    /**
     * 配列で指定されたchar_idのキャラを返す。
     * @param array $char_ids キャラIDの配列
     * @param array $keyword どのようなデータを取得するかの設定名 指定しなければすべてのパラメータを返す
     * @return array $chars キャラのステータスの配列 [{char_id = 2},{},...]
     */
    public function getChars($char_ids, $keyword = 'all')
    {
        $index = $this->customIndexes($keyword);
        $all_chars = $this->importAll();
        foreach($char_ids as $char_id){
            $chars[] = $this->narrowingIndex($all_chars[$char_id['char_id']], $index);
        }
        return $chars;
    }


    /**
     * インデックスに従って返すパラメータを絞り込む
     * @param array $char キャラの全パラメータ
     * @param array $index 返すパラメータの情報
     * @return array $narrowed_char 絞り込み後のキャラのパラメータ
     */
    private function narrowingIndex($char, $index)
    {
        $narrowed_char = [];
        if (empty($index)){
            $narrowed_char = $char;
        } else {
            foreach ($index as $key){
                $narrowed_char[$key] = $char[$key];
            }
        }
        return $narrowed_char;
    }


    /**
     * 指定されたキーワードから読み込むステータスを返す
     * @param string $keyword キーワード
     * @return string $index 読み込むパラメータの要素名
     */
    private function customIndexes($keyword)
    {
        $index = [];
        switch($keyword){
            case ('all'):
                break;
            case ('status'):
                $index = ['char_id', 'level', 'exp', 'name', 'status'];
                break;
            case ('max'):
                $index = ['char_id', 'status_max'];
                break;
        }
        return $index;
    }
}
