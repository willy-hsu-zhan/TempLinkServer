<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
// use GuzzleHttp\Client;
use App\User as UserEloquent;
use App\Temperature as TempEloquent;
use App\Machine as MachineEloquent;
use App\Abnormaltemp as AbnormaltempEloquent;
use Carbon\Carbon;

class MachineController extends Controller
{
    //寫入溫度
    public function api_temp_insert(Request $request) {
        $user = $request->user();
        if ($user->tokenCan('server:temp')) { //判斷是否有權限
            $data = $request->json();
            Carbon::setLocale('zh-tw');
            
            $apiKey = $data->get("apiKey");

            // $cardID = $data->get("cardID");
            // $lineID = $data->get("lineID");

            $sendID = $data->get("sendID");

            $cardID = null;
            $lineID = null;

            if(strlen($sendID) > 8 ){
                $lineID = $sendID;
            } else {
                $cardID = $sendID;
            }
            
            $temp = $data->get("temp");
            $machine_id = $data->get("machine_id");
            $time = Carbon::now()->toDateTimeString();
            $cardBindState = false; // 卡片綁定狀態
            $notify_token = "";
            $pushAdmin = false;
            $pushState = false;
            // $userData = UserEloquent::where("cardID",$cardID);
            // if($userData == null)
            //save to database
            // if($temp>= 30){
            // if($temp>= 37.5 || $temp<=34.0) {
            if( $temp>= 37.5 ) {
                $pushState = true;
                $abnormaltemp = new AbnormaltempEloquent();
                
                if($cardID != null){//選擇用卡片量測

                    $abnormaltemp->cardID = $cardID;
                    $userData =UserEloquent::where('cardID',"$cardID")->first();
                    
                    if($userData == null){//若查card沒資料即為陌生人
                        
                        $pushAdmin = true;
                        
                    }else{//代表卡片有用戶記錄
                        $cardBindState = true;//代表此卡片有綁定
                        
                        // 檢查identify
                        $judgehasIdentity = object_get($userData, 'identity', "noIdentity");

                        // 檢查notify_token
                        $judgehasNotify = object_get($userData, 'notify_token', "noNotify");
                        //return $judgehasNotify;
                        // if(($userData->identity == "T") ||)($userData->identity == "M"))
                        if($judgehasIdentity != "noIdentity"){//有身分欄位
                            if(($userData->identity == "T") || ($userData->identity == "M")){
                                $pushAdmin = true;
                            }//老師及管理者則直接推播給管理者 不用再檢查notify_token
                        }

                        if($judgehasNotify != "noNotify"){ // 檢查綁定line notify
                            $notify_token = $userData->notify_token;//代表此卡片有綁定notify
                        }else{//代表此卡片沒有綁定line_notify直接推播給管理者
                            $pushAdmin = true;
                        }

                    }
                    
                }else if ($lineID != null){//選擇用line量測

                    $abnormaltemp->lineID = $lineID; // 紀錄lineID

                    // 搜尋對應lineID使用者資料
                    $userData = UserEloquent::where('lineID',"$lineID")->first();

                    // 檢查綁定cardID
                    $judgehasCard = object_get($userData, 'cardID', "noCardID");

                    // 檢查綁定line notify
                    $judgehasNotify = object_get($userData, 'notify_token', "noNotify");

                    if($judgehasCard != "noCardID"){
                        $cardBindState = true;//代表此卡片有綁定
                    }
                    
                    if($judgehasNotify != "noNotify"){
                        $notify_token = $userData->notify_token;
                        // $pushAdmin = true; // 指定推播給管理者
                    }else{//代表此卡片沒有綁定line_notify直接推播給管理者
                        $pushAdmin = true;
                    }
                }
                //伺服器直接對linebot推播
                // $text = "注意!注意! ".$cardID." 於 ".$time." 測量體溫為 ".$temp."，狀態:體溫異常,請相關單位注意，依國家防疫規定處理。";
                // Http::post('https://nuucsiebot.ddns.net:5000/get_alert',[
                //     "message" => "warning",
                //     "lineArray" => $lineArray,
                //     "text" => "$text",
                // ]);
                $abnormaltemp->temp = $temp;
                $abnormaltemp->machine_id = $machine_id;
                $abnormaltemp->time = $time;
                $abnormaltemp->save();
            }
            else{ 
                $tempEloquent = new TempEloquent();

                if($cardID != null){ // 使用cardID

                    $tempEloquent->cardID = $cardID;
                    $userCardData = UserEloquent::where('cardID',"$cardID")->get();
                    if($userCardData != '[]'){
                        $cardBindState = true;//代表此卡片有綁定
                    }
                } else if($lineID != null) { // 使用lineID

                    $tempEloquent->lineID = $lineID;
                    
                    // 搜尋對應lineID使用者資料
                    $userData = UserEloquent::where('lineID',"$lineID")->first();

                    // 檢查綁定cardID
                    $judgehasCard = object_get($userData, 'cardID', "noCardID");

                    // 檢查綁定line notify
                    $judgehasNotify = object_get($userData, 'notify_token', "noNotify");

                    if($judgehasCard != "noCardID"){ // 檢查綁定cardID
                        $cardBindState = true;
                    }
                    
                    if($judgehasNotify != "noNotify"){ // 檢查綁定line notify
                        $notify_token = $userData->notify_token;
                    }
                }

                $tempEloquent->temp = $temp;
                $tempEloquent->machine_id = $machine_id;
                $tempEloquent->time = $time;
                $tempEloquent->save(); 
            }

            // 檢查推播指定管理員
            if($pushAdmin == true){
                $notify_token = "ZdZEYabzVbI5eszjdCR2InNaLJbxmQGhovIWOpboJvg"; //預設管理員lineID
                // $notify_token = "8O3gMQJZrLrg6eJsdk1latgTeeaNcxLmP7vowl8gwbH"; //預設管理員lineID
            }
            
            return response()->json([
                "state" => true,
                "pushState" => $pushState,
                "pushAdmin" => $pushAdmin,
                "message" => "success",
                "notify_token" => "$notify_token",
                "bind" => $cardBindState,  //卡片綁定狀態
                "time" => "$time"
            ]);
            
        } else {
            return response()->json([
                "state" => false,
                "message" => "falied"
            ]);
        }  
    }

    //門禁驗證
    public function api_access_control(Request $request) {
        $user = $request->user();
        if ($user->tokenCan('server:temp')) { //判斷是否有權限
            $data = $request->json();
            Carbon::setLocale('zh-tw');
            //12759317.
            
            // $apiKey = $data->get("apiKey");
            $cardID = $data->get("cardID");
            $time = Carbon::now()->toDateTimeString();
            $userData = TempEloquent::where('cardID',"$cardID")->orderBy('time', 'desc')->get() ;
            $judge = Carbon::now()->subHours(3)->toDateTimeString();
            $userAbnormalData = AbnormaltempEloquent::where('cardID',"$cardID")->orderBy('time', 'desc')->get() ;
            //最後一筆cardID資料
            /////////access member
            $access_member = ['12759317','E2F1E007','12510312','CB39978A'];
            $access_member_check = false;
            for($i=0;$i<count($access_member);$i++){
                if($cardID == $access_member[$i]){
                    $access_member_check = true;
                    break;
                }
            }
            /*
            if($access_member_check){
                return response()->json([
                    "state" => true,
                    "message" => "success",
                    "time" => "$time"
                ]);
            }
            */
            /////////
            if($userData != "[]" && $userAbnormalData != "[]"){//正常與異常都有體溫紀錄
                $lastTemp = $userData[0];
                $lastNormalTime = $lastTemp->time;
                // $lastTime;
                //$temp = $lastTemp->temp;
                $lastAbnormalTime = $userAbnormalData[0]->time;
                // return $userAbnormalData[0];
                if($lastAbnormalTime > $lastNormalTime){
                    
                    $lastTime = $lastAbnormalTime;
                }else{
                    
                    $lastTime = $lastNormalTime;
                }
                //save to database
                // return 
                if(($lastTime == $lastNormalTime) && $lastTime > $judge){
                    return response()->json([
                        "state" => true,
                        "message" => "success",
                        "time" => "$time"
                    ]);
                }else if (($lastTime == $lastNormalTime) && $lastTime < $judge){//超過三小時
                    return response()->json([
                        "state" => false,
                        "message" => "請重新量測體溫",
                        "time" => "$time"
                    ]);
                }else if($userAbnormalData != "[]"){
                    return response()->json([
                        "state" => false,
                        "message" => "體溫過高，無法進入",
                        "time" => "$time"
                        ]);
                }
                    
                // if($lastTime > $judge){ //最近一筆體溫過高
                    
                // }else{  // 三小時內無良撤資料
                //     return response()->json([
                //     "state" => false,
                //     "message" => "請重新量測體溫",
                //     "time" => "$time"
                //     ]);
                // }
            }else if($userData != "[]"){
                $lastTemp = $userData[0];
                $lastNormalTime = $lastTemp->time;
                if($lastNormalTime > $judge){
                    return response()->json([
                        "state" => true,
                        "message" => "success",
                        "time" => "$time"
                    ]);
                }else{
                    return response()->json([
                    "state" => false,
                    "message" => "請重新量測體溫",
                    "time" => "$time"
                    ]);
                }
                // return response()->json([
                //     "state" => false,
                //     "message" => "無卡片資料，請去測量體溫",
                //     "time" => "$time"
                // ]);
            }else {
                return response()->json([
                "state" => false,
                "message" => "無卡片資料，請去測量體溫",
                "time" => "$time"
                ]);
            }
            
        } else {
            return response()->json([
                "state" => false,
                "message" => "falied"
            ]);
        }  
    }

    //課程上級推播(體溫異常)
    public function api_push_course_alert_check(Request $request){//已過濾完陌生人
        $user = $request->user();
        if ($user->tokenCan('server:temp')){
            $data = $request->json();
            $notify_token = $data->get("notify_token");

            // $defineMasterLineId = "Ua179317686a396defd9d9845dea95de1";

            // 使用notify_token搜尋使用者
            $userData = UserEloquent::where("notify_token","$notify_token")->first();
            $lineArray = array();

            $time = $time = Carbon::now()->toDateTimeString();
            
            $judgehasCourse = object_get($userData, 'course', 'noCourse');

            if($judgehasCourse != "noCourse"){//有課程欄位
                $courses = $userData->course;
                
                if(is_array($courses)){
                    
                    $userAllData = UserEloquent::all();

                    for( $i=0; $i < count($courses) ; $i++){       //查詢是否已經存在相同課程
                        $course = $courses[$i];
                        $Id = $course['Id']; //學生加入過的課程ID
                        if($Id == "deleted") continue;
                        
                        $courseIdMatch = $Id; //查詢是否已經存在相同課程 數量為陣列元素大小
                        for($j=0 ; $j < count($userAllData) ; $j++){

                            // 過濾老師及管理員
                            if($userAllData[$j]->identity != "T" && $userAllData[$j]->identity != "M")
                                continue;
                                
                            $userInnerData = $userAllData[$j]->course;//老師或管理者開的課程
                            for($k=0; $k < count($userInnerData); $k++){ //老師或管理者開的課程總數

                                $IdMatch = $userInnerData[$k]['Id']; // 老師開課ID
                                if($IdMatch != $courseIdMatch) continue; // 檢查是否與學生加入課程一致 //如果老師或管理者開的課程ID與學生加入的課程ID相符
                                
                                // 檢查老師與管理員是否有綁定line notify
                                $judgehasLineNotify = object_get($userAllData[$j],'notify_token', "noNotify");
                                if($judgehasLineNotify == "noNotify") continue;

                                $line_notify_token = $userAllData[$j]->notify_token;//把老師或管理者LINEID取出放入lineArray推播
                                
                                if( in_array($line_notify_token, $lineArray) ) continue; // 檢查是否已加入清單
                                array_push($lineArray, $line_notify_token); // 將notify_token加入清單
                            }
                        }
                    }
                }
            } else { // 學生沒加入任何課程則推播給管理者
                $line_notify_token = "ZdZEYabzVbI5eszjdCR2InNaLJbxmQGhovIWOpboJvg"; //預設管理員lineID
                // array_push($lineArray, $line_notify_token);

                // $line_notify_token = "8O3gMQJZrLrg6eJsdk1latgTeeaNcxLmP7vowl8gwbH"; //預設管理員lineID
                array_push($lineArray, $line_notify_token);
            }

            return response()->json([
                "state" => true,
                "message" => "success",
                "line_notify" => $lineArray,
                "time" => "$time"
            ]);
        }else{
            return response([
                "state" => false,
                'info' => "errorPage"
            ]);
        }
    }
}
