<?php

namespace App\Http\Controllers\Lists;

use Illuminate\Http\Request;
use App\Services\AuthLogic;
use App\Http\Requests;

use App\Http\Controllers\Controller;

class Auth extends Controller
{


    /**
     * googleからコールバックされてきた時にアクセスされるコントローラ
     */
    public function index($id)
    {
        $param = \Request::all();
        $a = env('DB_HOST');
        printf($a, 'notfound');
        $myModel = new AuthLogic($param);
        return ;
    }
}

