<?php
namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Socialite;
use App;

/**
 * [Class] Auth認可より認証を行う
 */
class AuthLogic extends Model
{
    public function __construct($param)
    {

        $a = env('DB_HOST', 'aa');    //エラーは出ない
        printf($a);             //何も入っていない
        printf("aiueo");        //表示される
        if (isset($param) && isset($param['session_state'])){
//            $this->token_authenticate($param);
        }
    }


    /**
     * codeを元にToken認証
     */
    private function token_authenticate($param)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://accounts.google.com/o/oauth2/token");
        curl_setopt($ch, CURLOPT_HEADER, FALSE);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        $opt = [
            "code" => $param['code'],
            "client_id" => env('GOOGLE_CLIENT_ID'),
            "client_secret" => env('GOOGLE_CLIENT_SECRET'),
            "redirect_uri" => "http://homestead.app:8000/v1/auth/google/",
            "grant_type" => "authorization_code"
        ];
        //print_r($opt);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $opt);

        $response = curl_exec($ch);
        curl_close($ch);
    }
}
