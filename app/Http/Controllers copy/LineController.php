<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\User as UserEloquent;
use App\Temperature as TempEloquent;
use App\Machine as MachineEloquent;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;


class LineController extends Controller
{   
    //加入課程(not yet)
    public function api_line_insert_course(Request $request) {
        $user = $request->user();
        if ($user->tokenCan('server:line')) {      //判斷是否有權限
            //get data
            $lineData = $request->json();
            $apiKey = $data->get("apiKey");
            //get collumns' values
            $lineID = $lineData->get("UID");

            $data = $lineData->get("data");
            $text = $data["text"];
            $class_code = $data["class_code"];

            $userdata = new UserEloquent(); 
            $userdata->UID = $UID;
            //$userdata->UT = Carbon::now()->toDateTimeString();
            $userdata->data = $data;
            $userdata->save();

            return response()->json([
                "state" => true,
                "lineData" => [
                    "apiKey" => "$apiKey",
                    "UID" => "$UID",
                    "data" => [
                        'text' => "$data[text]",
                        'class_code' => "$data[class_code]"
                    ]
                ]
            ]);
        }else{
            return response()->json([
                "state" => false,
                "message" => "errorPage"
            ]); 
        }
        
    }

    //查詢課程(not yet)
    public function api_line_read_course(Request $request){
        $user = $request->user();
        if ($user->tokenCan('server:line')) {       //判斷是否有權限
            $lineData = $request->json();
            $lineID = $lineData->get("UID");
            $lineUser = UserEloquent::where("lineID", "$lineID")
            ->first();
            $course = $lineUser->course;
            return $course;
        }else{
            return response()->json([
                "state" => false,
                "message" => "errorPage"
            ]);
        }
    }
    
    //查詢體溫(almost)
    public function api_line_read_temp(Request $request){
        $user = $request->user();
        if ($user->tokenCan('server:line')) {       //判斷是否有權限
            //request
            $lineData = $request->json();
            
            $lineID = $lineData->get("UID");    //取得lineUserID
            $data = $lineData->get("data");     //取得Data資料          
            $getRecentDay = $data['day'];
            $lineUser =UserEloquent::where("lineID", "$lineID")->first();
            //$todayDate = Carbon::now()->toDateString()." 00:00:00";       
            $cardID = $lineUser->cardID;
            if ($cardID != null) {
                       //取得天數
                $calGetRecentDay = Carbon::parse("$getRecentDay"." days ago")->toDateString()." 00:00:00";
                $todayDate = Carbon::parse('today')->toDateTimeString();
                $projections = ['temp', 'time'];    //抓取temp,time欄位
                //get temperature data 
                $temp =
                TempEloquent::where('cardID', "$cardID")
                ->orWhere("lineID", "$lineID")
                //->where('time','<', '2020-10-12 23:31:30')  //撈此時間點之前的體溫
                ->where('time','>',"$calGetRecentDay")  //撈此時間點(calGetRecentDay)之後的體溫
                ->orderBy('time', 'asc')   //排序依據時間點遞增
                ->get($projections);    //撈資料庫temp,time欄位的資料

                if($temp == '[]'){                      //期限內無資料
                    return response()->json([
                        "state" => true,
                        "message" => $getRecentDay."天內無資料",
                        "UID" => $lineID,
                        "count" => count($temp),
                        "no_data" => true
                    ]);
                }else{
                    return response()->json([
                    "state" => true,
                    "message" => "查詢完成",
                    "UID" => $lineID,
                    "count" => count($temp),
                    "data" => $temp,
                    "no_data" => false
                ]); 
                }
            }else{                                   //找不到此卡片用戶 回傳資料(lineID,體溫,時間)
                return response()->json([
                    "state" => false,
                    "message" => "card not find"
                ]);  
            }
        }else{
            return response()->json([
                "state" => false,
                "message" => "errorPage"
            ]);
        }
        
    }

    public function api_line_find_card(Request $request){
        $lineData = $request->json();
        $lineID = $lineData->get("UID");    //取得lineUserID      
        $lineUser =UserEloquent::where("lineID", "$lineID")->first();     
        $cardID = $lineUser->cardID;

        

        if($cardID == null){
            return response()->json([
                "state" => false
            ]); 
        }else{                                   //回傳資料(lineID,體溫,時間)
            return response()->json([
                "state" => true,
                "cardID" => $cardID
            ]);  
        }
    }

    //綁定卡片(almost,linelogin)
    public function api_line_card_bind(Request $request){
        $user = $request->user();
        if ($user->tokenCan('server:line')) {       //判斷是否有權限
            $lineData = $request->json();
            $lineID = $lineData->get("UID");
            $cardID = $lineData->get("CID");

            $user = new UserEloquent();
            $user->lineID = $lineID;
            $user->save();
            $user = UserEloquent::where("lineID", "$lineID")
            ->first();
            $temp = TempEloquent::where('cardID', "$cardID")->first();
            if($temp == null)   //此卡片尚無量測資料
            {
                $user->cardID = $cardID;
                $user->save();
                return response([
                "data" => [
                    "state" => true,
                    "Message" => "此卡號尚無資料!",
                    "UID" => "$lineID",
                ]
                ]);
            }
            else{   //此卡片有量測資料
                $user->cardID = $cardID;
                $user->save();
                return response([
                "data" => [
                    "state" => true,
                    "Message" => "success",
                    "UID" => "$lineID",
                ]
                ]);
            }
        }else{
            return response([
                "data" => [
                    "Message" => "success",
                    "UID" => "$lineID",
                ]
                ]); 
        }
    }

    public function api_line_notify_token_bind(Request $request){
        $user = $request->user();
        if ($user->tokenCan('server:line')) {       //判斷是否有權限
            $lineData = $request->json();
            $lineID = $lineData->get("UID");
            $notify_token = $lineData->get("access_token");

            $user = UserEloquent::where('lineId', "$lineID")->first();

            if($user == null){  //使用者尚未存在(新增使用者)
                $count = UserEloquent::count(); //計算user總數
                $count = $count +1; //帳號ID遞增

                $user = new UserEloquent();
                $user->_id = $count;
                $user->lineID = $lineID;
                $user->notify_token = $notify_token;
                $user->save();

                return response([
                    "state" => true,
                    'message' => 'bind success'
                ]);
            }
            else{
                $user->notify_token = $notify_token;
                $user->save();

                return response([
                    "state" => true,
                    'message' => 'bind success'
                ]);
            }

        }else{
            return response()->json([
                "state" => false
            ]); 
        }
    }

    //line bot 紀錄
    public function api_line_login(Request $request) {//這個 但是前端使用者拿到的AuthToken是react.token 不是server.line
        $user = $request->user();
        if ($user->tokenCan('server:line')) {   //判斷是否有權限
            $data = $request->json();
            $lineID = $data->get("UID");
            $line_access_token = $data->get("access_token");

            $userdata = UserEloquent::where("lineID", "$lineID")
            ->first();
            if($userdata == null){  //尚未有此用戶資料 新增此用戶
                $count = UserEloquent::count(); //計算user總數
                $count = $count +1; //帳號ID遞增

                $userdata = new UserEloquent;
                $userdata->_id = $count;
                $userdata->lineId = $lineID;
                $userdata->line_access_token = $line_access_token;   //存入access token
                $userdata->save();

                return response([    //回傳api token
                    "state" => true,
                    'message' => "login success",
                    
                ]);
            }
            else{
                $userdata->line_access_token = $line_access_token;  //綁定access token
                $userdata->save();
                return response([    //回傳api token
                    "state" => true,
                    'message' => "login success"
                ]);
            }
        
        
        } else {
            return response()->json([
                "state" => false,
                'message' => "login error"
            ]); 
        }
    }

    
}