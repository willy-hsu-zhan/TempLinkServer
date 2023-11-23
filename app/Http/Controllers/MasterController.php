<?php

namespace App\Http\Controllers;
use App\User as UserEloquent;
use App\Temperature as TempEloquent;
use App\Machine as MachineEloquent;
use App\Abnormaltemp as AbnormaltempEloquent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class MasterController extends Controller
{   
    //查詢所有體溫
    public function api_master_read_temp(Request $request){ 
        $user = $request->user();
        if ($user->tokenCan('react:master')){
            $projections = ['temp', 'time','cardID','machine_id', 'lineID'];    //抓取temp,time欄位
            $userData =TempEloquent::all($projections);
            // echo $userData;
            if ( $userData == '[]' ) {                      //期限內無資料
                return response()->json([
                    "state" => false,
                ]);  
            }
            else{                                   //回傳資料(lineID,體溫,時間)
                return response()->json([
                    "state" => true,
                    "userData" => $userData
                ]);  
            }
        }else{
            return response([
                'info' => "errorPage"
            ]);
        }
    }

    //查詢異常體溫
    public function api_master_read_abtemp(Request $request){   
        $user = $request->user();
        if ($user->tokenCan('react:master')){
            $projections = ['temp', 'time','cardID','machine_id','lineID'];    //抓取temp,time欄位
            $userData =AbnormaltempEloquent::all($projections);
            // echo $userData;
            if ( $userData == '[]' ) {                      //期限內無資料
                return response()->json([
                    "state" => false,
                ]);  
            }
            else{                                   //回傳資料(lineID,體溫,時間)
                return response()->json([
                    "state" => true,
                    "userData" => $userData
                ]);  
            }
        }else{
            return response([
                'info' => "errorPage"
            ]);
        }        
    }

    //查詢所有課程
    public function api_master_read_all_course_data(Request $request){  
        $user = $request->user();
        $allCourses = array(); //所有課程陣列
        if ($user->tokenCan('react:master')){
            $data = $request->json();
            $userData =UserEloquent::all(); //取得所有使用者資料

            for($i=0;$i<count($userData);$i++){
                $userContent = $userData[$i]; 
                if(($userContent->identity == 'T') || ($userContent->identity == 'M')){
                    $courses = $userContent->course;
                        if(is_array($courses)){
                            
                            for( $j=0; $j< count($courses); $j++ ){ //計算課程總數
                                $course = $courses[$j];

                                if($course["Id"] == "deleted") continue;

                                // if(in_array($course["Id"], $allCourses)){
                                //     continue;
                                // }
                                // $allCourses[$course["Id"]] = [
                                //     "Id" => $course["Id"],
                                //     "Name" => $course["Name"],
                                //     "Type" => $course["Type"],
                                //     "time" => $course["time"],
                                //     "Text" => $course["Text"],
                                // ];  //將老師或管理員底下的所有課程推入總課程陣列 
                                array_push($allCourses, $course);
                                
                            }
                            
                        }
                }else{
                    continue;
                }
            }

            return response([
                'state' => true,
                'course' => $allCourses
            ]);

        }else{
            return response([
                'state' => false,
                'info' => "errorPage"
            ]);
        }
    }

    //查詢課程詳細資料和成員(老師，管理者)
    public function api_master_read_course_data(Request $request){    
        $user = $request->user();
        $course_T_member = array();
        $course_S_member = array();
        $courseData = array();
        if ($user->tokenCan('react:master') || $user->tokenCan('react:teacher')){
            $data = $request->json();
            $courseId = $data->get("classId");
            $userData =UserEloquent::all();
            $hasCourseId = false;        //判斷是否存在課程的變數
            for($i=0;$i<count($userData);$i++){
            $userrrr = $userData[$i];
            $courses = $userrrr->course;
                if(is_array($courses)){
                    $count=count($courses);            //計算課程總數

                    for($j=0 ; $j<$count ; $j++){       //查詢是否已經存在相同課程
                        $course = $courses[$j];
                        $Id = $course['Id'];

                        if($Id == $courseId){     //已存在此課程
                            $hasCourseId = true;
                            if(($userrrr->identity) == "T"){
                                array_push($course_T_member,$userrrr->name);
                                $courseData = $course; //取得課程資料
                            }else if(($userrrr->identity) == "M"){
                                array_push($course_T_member,$userrrr->name);
                                $courseData = $course; //取得課程資料
                            }else if(($userrrr->identity) == "S"){
                                array_push($course_S_member,$userrrr->name);
                            }
                            break;
                        }else{                        //尚未新增此課程
                            continue;
                        }
                    }
                }
            }

            if($hasCourseId == true){
                $totalMember = count($course_S_member);
                return response([
                    "state" => true,
                    "info" => "查詢完成!",
                    "courseData" => $courseData,
                    "student_data" => $course_S_member,
                    "teacher_data" => $course_T_member,
                    "totalMember" => $totalMember
                    ]);
            }else{
                return response([
                    "state" => false,
                    "info" => "此課程不存在!"]);
            }

        }else{
            return response([
                "state" => false,
                'info' => "errorPage"
            ]);
        }
    }
    
    //推播訊息 
    public function api_push_message(Request $request){  
        $user = $request->user();
        if ($user->tokenCan('react:teacher') || $user->tokenCan('react:master')){
            $data = $request->json();
            $_id = $data->get("UID");
            $int_id = intval($_id);
            $text = $data->get("text");
            $userData = UserEloquent::where('_id', $int_id)->first();
            // return $userData;
            $time = Carbon::now()->toDateTimeString();  //取得現在時刻
            $notify_token = object_get($userData, 'notify_token', 'null');
            if ($notify_token == "null"){
                return response([
                    "state" => false,
                    'info' => "notify_token未綁定"
                ]); 
            }
            //推播給linebot官方
            $response = Http::post('https://nuucsiebot.ddns.net:5000/get_normal_post',[
                "notify_token" => "$notify_token",
                "text" => "$text",
                "title" => "通知$_id",
                'post_time' => "$time"
            ]);

            $result = $response->json();

            return response([
                "state" => true,
                'info' => "推播成功"
            ]); 
        }else{
            return response([
                "state" => false,
                'info' => "errorPage"
            ]);
        }
    }

    //回傳所有使用者的資料及體溫(管理者)
    public function api_query_userdata(Request $request){   
        $user = $request->user();
        if ($user->tokenCan('react:master')) { //判斷是否有權限 #管理者
            $projections = ['cardID', 'name', 'lineID', 'identity', 'created_at', 'email','waitAuth' ];
            $userData = UserEloquent::where('lineID', 'exists', 'true')->get($projections);   //回lineid和cardid
            // $projections = ['cardID', 'machine_id', 'temp', 'time'];
            // $userTempData = TempEloquent::where('cardID', 'exists', true)->get($projections);
            return response([
                "state" => true,
                "userData" => $userData,    //使用者資料(卡號,lineid)but 權限對一職拿到error use admin@gma 對呀 一樣 error 應改渴以了 https://nuucsieweb.ddns.net:5001                   "userTempData" => $userTempData
            ]);      
        } else if ($user->tokenCan('react:student')) { //判斷是否有權限 #學生
            $data = $request->json();
            $line_access_token = $data->get("access_token");
            
            $projections = ['cardID', 'lineID'];
            $userData = UserEloquent::where("line_access_token", "$line_access_token")->get($projections);

            // $cardID = json_decode($userData)[0]->cardID;
            $cardID = $userData->cardID;
            $projections = ['cardID', 'machine_id', 'temp', 'time'];
            $userTempData = TempEloquent::where('cardID', "$cardID")->get($projections);

            return response([
                "state" => true,
                "line_access_token" => $line_access_token,
                "cardID" => $cardID, // 之後要改
                "userTempData" => $userTempData
            ]);

            // $userTempData = TempEloquent::where('cardID', $cardID);

            // if ($userTempData==null) {
            //     return response()->json([ // 沒有資料
            //         "state" => true,
            //         "message" => "no temp data"
            //     ]);
            // } else {
            //     return response()->json([
            //         "state" => true,
            //         "userTempData" => $userTempData,    //使用者資料(卡號,lineid)but 權限對一職拿到error use admin@gma 對呀 一樣 error 應改渴以了 https://nuucsieweb.ddns.net:5001                   "userTempData" => $userTempData
            //     ]);
            // }   
        } else {
            return response()->json([   //沒有權限
                "state" => false,
                "message" => "error"
            ]);
        }
    }

    //更改使用者權限 
    public function api_edit_identity(Request $request){    
        $user = $request->user();
        if ($user->tokenCan('react:master')) { //判斷是否有權限 #管理者
            $data = $request->json();
            $_id = $data->get('UID');
            $newIdentity = $data->get('identity');
            $newWaitAuth = $data->get('waitAuth');
            $int_id = intval($_id);
            $aUser = UserEloquent::where('_id', $int_id)->first();
            
            $aUser->identity = $newIdentity;
            $aUser->waitAuth = $newWaitAuth;
            
            $aUser->save();
            
            return response()->json([
                "state" => true,
                "identity" => $newIdentity,
                "waitAuth" => $newWaitAuth,
                "message" => "編輯成功"
            ]);
        }else{
            return response()->json([   //沒有權限
                "state" => false,
                "message" => "error"
            ]);
        }
    }

    //刪除帳號
    public function api_delete_account(Request $request){    
        //$user = $request->user();
        //if ($user->tokenCan('react:master')) { //判斷是否有權限 #管理者
            $data = $request->json();
            $_id = $data->get('UID');
            if($_id == "1" || $_id == "2" || $_id == "3"){
                return response()->json([   //沒有權限
                    "state" => false,
                    "message" => "error"
                ]);
            }
            $int_id = intval($_id);  
            $userData = UserEloquent::where('_id', $int_id)->first();
            $userData->unset('lineID');
            $userData->unset('name');
            $userData->unset('email');
            $userData->unset('unit');
            $userData->unset('notify_token');
            $userData->unset('email_token');
            $userData->unset('identity');
            $userData->unset('line_access_token');
            $userData->unset('cardID');
            $userData->unset('updated_at');
            $userData->unset('created_at');
        

            // $userData->unset('name');
            
            return response()->json([  
                "state" => true,
                "message" => "刪除成功"
            ]);
        // }else{
        //     return response()->json([   //沒有權限
        //         "state" => false,
        //         "message" => "error"
        //     ]);
        // }
    }

    //刪除課程(管理者)
    public function api_delete_course_master(Request $request){    
        $user = $request->user();
        if ($user->tokenCan('react:master')){
            $data = $request->json();
            $courseId = $data->get("classId");
            $userData =UserEloquent::all();
            $hasCourseId = false;        //判斷是否存在課程的變數
            for($i=0;$i<count($userData);$i++){
                $aUser = $userData[$i];
                $courses = $aUser->course;
                if(is_array($courses)){
                    $count=count($courses);            //計算課程總數

                    for($j=0 ; $j<$count ; $j++){       //查詢是否已經存在相同課程
                        $course = $courses[$j];
                        $Id = $course['Id'];

                        if($Id == $courseId){     //已存在此課程
                            $hasCourseId = true;

                            $aUser->pull('course', [  //刪除所有使用者的特定課程
                                'Id' => "$courseId"
                            ]);
                            $aUser->push('course', [
                                'Id' => "deleted",
                                'Name' => "deleted"
                            ]);

                            break;
                        }else{                        //尚未新增此課程
                            continue;
                        }
                    }
                }
            }

            if($hasCourseId == true){
                return response([
                    "state" => true,
                    "info" => "刪除成功!",
                    ]);
            }else{
                return response([
                    "state" => false,
                    "info" => "此課程不存在!"]);
            }

        }else{
            return response([
                "state" => false,
                'info' => "errorPage"
            ]);
        }
    }

    //回傳使用者資料
    public function api_card_covert_to_user(Request $request){  
        $user = $request->user();
        if ($user->tokenCan('react:master')){
            $data = $request->json();
            $cardID = $data->get("cardID");
            $userData = UserEloquent::where('cardID', "$cardID")->first();
            
            if($userData == null){
                return response([
                    "state" => false,
                    'info' => "尚未註冊"
                ]);
            }else{
                return response([
                    "state" => true,
                    "userData" => $userData
                ]);
            }
            
        }else{
            return response([
                'state' => false,
                'info' => "errorPage"
            ]);
        }
    }


}
