<?php
namespace App\Models;

use Aws\DynamoDb\Marshaler;
use App\Models\DynamoDBHandler;
use App\Services\Common\Record;

class ArenaLoader extends DynamoDBHandler
{
    private $path = '../Dataset/Data/arenas.json';
    private $arenas = [];

    public function importAll()
    {
        if (empty($this->arenas)){
            $json = file_get_contents($this->path);
            $this->arenas = json_decode($json, true);
        }
        return $this->arenas;
    }


    /**
     * IDで指定されたarena情報を返す
     * @param int $arena_id
     */
    public function getArena($arena_id)
    {
        $arena = $this->importAll();
        return $arena[$arena_id];
    }


    /**
     * リストで指定されたarena情報をすべて返す
     * @param array $arena_ids
     */
    public function getArenas($arena_ids)
    {
        $arena = $this->importAll();
        foreach($arena_ids as $arena_id){
            $arenas[] = $arena[$arena_id];
        }
        return $arenas;
    }
}