<?php

namespace App\Http\Controllers;
use App\User as UserEloquent;
use App\Temperature as TempEloquent;
use App\Machine as MachineEloquent;
use App\Abnormaltemp as AbnormaltempEloquent;
use Illuminate\Http\Request;

class MasterController extends Controller
{
    public function api_master_read_temp(Request $request){
        $user = $request->user();
        if ($user->tokenCan('react:master')){
            $projections = ['temp', 'time','cardID','machine_id'];    //抓取temp,time欄位
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

    public function api_master_read_abtemp(Request $request){
        $user = $request->user();
        if ($user->tokenCan('react:master')){
            $projections = ['temp', 'time','cardID','machine_id'];    //抓取temp,time欄位
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

    public function api_master_read_course_member(Request $request){
        $user = $request->user();
        $course_T_member = array();
        $course_S_member = array();
        if ($user->tokenCan('react:master')){
            $data = $request->json();
            $courseId = $data->get("courseId");
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
                return response([
                    "info" => "查詢完成!",
                    "student_data" => $course_S_member,
                    "teacher_data" => $course_T_member,
                    ]);
            }else{
                return response([
                    "info" => "此課程不存在!"]);
            }

        }else{
            return response([
                'info' => "errorPage"
            ]);
        }
    }

    public function api_master_push_message(Request $request){
        $user = $request->user();
        if ($user->tokenCan('react:master')){
            $data = $request->json();
            $_id = $data->get("UID");
            $text = $data->get("text");
            $userData = UserEloquent::where('_id', "$_id")->get();
            $notify_token = $userData->where('notify_token', 'exists', true)->get();
            if($notify_token == null){
                return response([
                    'info' => "notify_token未綁定"
                ]); 
            }
            Http::post('https://nuucsiebot.ddns.net:5000/get_news',[
                "notify_token" => "$notify_token",
                "text" => "$text",
            ]);

        }else{
            return response([
                'info' => "errorPage"
            ]);
        }
    }
}
