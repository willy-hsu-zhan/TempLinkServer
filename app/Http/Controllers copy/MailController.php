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
    public function verify_email(Request $request){
        $data = $request->json();
        $email_token = $data->get('email_token');
        $user = UserEloquent::where('email_token',$email_token)->first();
        if($user == null){  //找不到相符token，驗證失敗
            return response([
                'message' => '驗證失敗'
            ]);
        }
        else{ //token驗證成功
            $user->email_verified = true; //將信箱認證狀態存到資料庫
            $user->email_token = null;
            $user->save();

            $email = $user->email;

            return response([
                'message' => '驗證成功',
                'email' => "$email"
            ]);
        }
        
    }

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
