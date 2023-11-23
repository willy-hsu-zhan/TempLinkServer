<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Mail;
use App\User as UserEloquent;

class MailController extends Controller
{
    //寄送信箱驗證信
    public function send_verify_email(Request $request){
        $Data = $request->json();
        $email = $Data->get('email');
        $user = UserEloquent::where('email', "$email")->first();    //找出此信箱的user資料
        
        if($user == null){  //此信箱尚未被使用
            $count = UserEloquent::count(); //計算user總數
            $count = $count +1; //帳號ID遞增
            
            $user = new UserEloquent;
            $user->_id = $count;
            $user->email = $email;
            $user->email_token = $count.Str::random(40);    //隨機產生40碼token(加上userid)
            $user->save();
            $data = compact('user');

            Mail::send('email.verify', $data, function($message) use ($email){
                $message->subject('好疫罩會員信箱認證');
                $message->to($email);
            });
            return response([
                'message' => "Email 已寄出"
                ]);
        }
        elseif( ($user->email_verified) == null){ //此信箱尚未認證
            $id = $user->_id;
            $user->email_token = $id.Str::random(40);    //隨機產生40碼token(加上userid)
            $user->save();
            $data = compact('user');

            Mail::send('email.verify', $data, function($message) use ($email){
                $message->subject('好疫罩會員信箱認證');
                $message->to($email);
            });
            return response([
                'message' => "Email 已寄出"
                ]);
        }
        else{   //此信箱已被使用
            return response([
                'message' => '此信箱已註冊'
            ]);
        }
        
    }

    //驗證信箱
    public function verify_email(Request $request, $line_access_token, $lineID, $email_token){
        $user = UserEloquent::where('lineID',$lineID)->first();
        $judgeNotEmail = object_get($user, "email", 'noEmail'); //判斷是否存在此欄位
        $judegeNotLineAccessToken = object_get($user, "line_access_token", 'notAccessToken'); //判斷是否存在此欄位
        if($user == null){  //找不到line用戶
            // return response()->json([
            //     //'state' => false,
            //     'message' => 'find not any users',
            //     //'lineID' => "$lineID"
            // ]);
            return response("<body style='background-color: black; color: white; margin-left: 15%; margin-top: 50%; font-size: 5vw;''><h1>找不到此用戶！</h1></body>");
        }else if($judgeNotEmail == "noEmail"){  //有line用戶，沒email欄位
            // return response()->json([
            //     //'state' => false,
            //     'message' => 'this account has no data',
            //     //'lineID' => "$lineID"
            // ]);
            return response("<body style='background-color: black; color: white; margin-left: 15%; margin-top: 50%; font-size: 5vw;''><h1>此帳戶無資料！</h1></body>");
        }else if($judegeNotLineAccessToken != "notAccessToken"){    //存在line_access_token欄位，用戶已驗證過信箱(email)
            // return response()->json([
            //     //'state' => false,
            //     'message' => 'has vetified',
            //     //'lineID' => "$lineID"
            // ]);
            return response("<body style='background-color: black; color: white; margin-left: 15%; margin-top: 50%; font-size: 5vw;''><h1>信箱已驗證！</h1></body>");
        }else{  
            $email = $user->email;
            $judgeNotNull = object_get($user, 'email_token', 'nullData'); //判斷是否存在此欄位
            if($judgeNotNull == "nullData"){
                // return response()->json([
                //     //'state' => false,
                //     'message' => 'send Mail not yet',
                //     //'lineID' => "$lineID",
                //     //'email' => "$email"
                // ]);
                return response("<body style='background-color: black; color: white; margin-left: 15%; margin-top: 50%; font-size: 5vw;''><h1>信箱尚未寄出！</h1></body>");
            }else{
                if($user->email_token == "$email_token"){
                    $user->line_access_token = $line_access_token; //將line_access_token存到資料庫(代表信箱驗證成功)
                    $user->email_token = null;
                    $user->save();

                    // return response()->json([
                    //     //'state' => true,
                    //     'message' => 'vetified success',
                    //     // 'lineID' => "$lineID",
                    //     //'email' => "$email"
                    // ]);

                    return response("<body style='background-color: black; color: white; margin-left: 15%; margin-top: 50%; font-size: 5vw;''><h1>信箱驗證成功！</h1></body>");
                }else{  //email_token不相符
                    // return response()->json([
                    //     //'state' => false,
                    //     'message' => 'vetified failed',
                    //     // 'lineID' => "$lineID",
                    //     //'email' => "$email"
                    // ]);
                    return response("<body style='background-color: black; color: white; margin-left: 15%; margin-top: 50%; font-size: 5vw;''><h1>信箱驗證失敗！</h1></body>");
                }
            }
        }
    }
    
    //寄送重設密碼信箱　
    public function send_reset_password_email(Request $request){
        $Data = $request->json();
        $email = $Data->get('email');
        $user = UserEloquent::where('email', "$email")->first();    //找出此信箱的user資料
        
        if($user == null){  //此信箱尚未被使用
            $count = UserEloquent::count(); //計算user總數
            $count = $count +1; //帳號ID遞增
            
            $user = new UserEloquent;
            $user->_id = $count;
            $user->email = $email;
            $user->forget_token = $count.Str::random(40);    //隨機產生40碼token(加上userid)
            $user->save();
            $data = compact('user');

            Mail::send('email.verify', $data, function($message) use ($email){
                $message->subject('好疫罩會員忘記密碼');
                $message->to($email);
            });
            return response([
                'message' => "Email 已寄出"
                ]);
        }
        elseif( ($user->email_verified) == null){ //此信箱尚未認證
            $id = $user->_id;
            $user->forget_token = $id.Str::random(40);    //隨機產生40碼token(加上userid)
            $user->save();
            $data = compact('user');

            Mail::send('email.forget', $data, function($message) use ($email){
                $message->subject('好疫罩會員忘記密碼');
                $message->to($email);
            });
            return response([
                'message' => "Email 已寄出"
                ]);
        }
    }

    //重設密碼
    public function verify_reset_password_email(Request $request){
        $data = $request->json();
        $forget_token = $data->get('forget_token');
        $user = UserEloquent::where('forget_token',$forget_token)->first();
        if($user == null){  //找不到相符token，驗證失敗
            return response([
                'message' => '驗證失敗'
            ]);
        }
        else{ //token驗證成功
            $user->forget_token = null;
            $user->save();

            $email = $user->email;

            return response([
                'message' => '驗證成功',
                'email' => "$email"
            ]);
        }
        
    }

    
        

}
