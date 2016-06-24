<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Models\CharLoader;
use App\Models\UserModel;
use App\Models\BattleDBModel;

class BattleModel extends Model
{
    private $user_id;
    public function __construct($user_id)
    {
        $this->user_id = $user_id;
        $this->user = new UserModel($user_id);
        $this->char = new CharLoader();
        $this->battle = new BattleDBModel();
    }


    public function getUser()
    {
        return $this->user->getUser();
    }


    /**
    * パーティキャラを読み込む
    * $charsの各要素に対して'char_id'に対応するデータを読み込む
    */
    public function getPartyChar($chars)
    {
        return $this->char->getChars($chars, 'growth_rate');
    }


    /**
     * バトル情報の読み込み
     */
    public function getBattle($user)
    {
        return $this->battle->getBattleByUser($user);
    }


    /**
     * バトル情報の書き込み
     */
    public function writeBattle($battle)
    {
        return $this->battle->updateBattle($this->user_id, $battle);
    }
}
