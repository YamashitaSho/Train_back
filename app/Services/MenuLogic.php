<?php
namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use App\Models\UserModel;

class MenuLogic extends Model
{
    public function __construct()
    {
        $this->userinfo = new UserModel();
    }
    /**
    * [API] ユーザー情報を取得するAPIで呼ばれる関数
    */
    public function getMenu()
    {
        $response = $this->userinfo->getUser();
        return [$response, 200];
    }
}
