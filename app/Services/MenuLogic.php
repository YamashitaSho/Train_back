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
        $user = $this->userinfo->getUser();
        $response = [
            'money' => $user['money'],
            'medal' => $user['medal'],
            'leader_char_id' => $user['party'][0]['char_id'],
            'quest_count' => $user['quest_count'],
        ];
        return [$response, 200];
    }
}
