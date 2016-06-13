<?php
namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use App\Models\UserModel;

class MenuLogic extends Model
{
    public function __construct($user_id)
    {
        $this->userinfo = new UserModel($user_id);
        $this->user = $this->userinfo->getUser();
    }
    /**
    * [API] ユーザー情報を取得するAPIで呼ばれる関数
    */
    public function getMenu()
    {

        $money = !empty($this->user['money']) ? $this->user['money'] : 0;
        $medal = !empty($this->user['medal']) ? $this->user['medal'] : "--";
        $char_id = !empty($this->user['party'][0]['char_id']) ? $this->user['party'][0]['char_id'] : 0;
        $quest_count = !empty($this->user['quest_count']) ? $this->user['quest_count'] : 0;

        $response = [
            'money' => $money,
            'medal' => $medal,
            'leader_char_id' => $char_id,
            'quest_count' => $quest_count,
        ];
        return [$response, 200];
    }
}
