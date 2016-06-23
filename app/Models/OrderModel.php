<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Models\UserModel;
use App\Models\CharDBModel;
use App\Models\CharLoader;
use App\Models\ItemLoader;


class OrderModel extends Model
{
    public function __construct($user_id)
    {
        $this->user = new UserModel($user_id);
        $this->char = new CharDBModel();
        $this->item = new ItemDBModel();
        $this->charloader = new CharLoader();
    }


    /**
     * ユーザー情報を読み込む
     */
    public function getUser()
    {
        return $this->user->getUser();
    }


    /**
    * [関数] 所持しているキャラの読み込み
    *
    * 読み込むデータ: char_id, level, exp, status, name
    * $idonly に trueが指定された場合、 読み込むデータは char_id のみ
    */
    public function readChar($user_id, $idonly = false)
    {
        return $this->char->getCharOwned($user_id, $idonly);
    }


    public function getCharsStatusMax($chars)
    {
        return $this->charloader->getChars($chars, 'max');
    }


    public function readItem($items)
    {
        return $this->item->getItems($items);
    }


    public function updateUser($user)
    {
        return $this->user->updateUser($user);
    }
}
