<?php
namespace App\Support;
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\User as UserEloquent;
use App\Temperature as TempEloquent;
use App\Abnormaltemp as AbnormalTempEloquent;
use App\Machine as MachineEloquent;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Support\Optional;


class LineController extends Controller
{   
    //加入課程(學生)
    public function api_line_insert_course(Request $request) {
        $user = $request->user();
        if ($user->tokenCan('server:line')) {      //判斷是否有權限
            //get data
            $lineData = $request->json();
            $apiKey = $lineData->get("apiKey");
            $lineID = $lineData->get("UID");
            //get collumns' values
            $data = $lineData->get("data");
            $courseId = $data["class_code"];
            $time = Carbon::now()->toDateTimeString();  //取得現在時刻

            $courseName = '';

            $lineUser = UserEloquent::where('lineID', "$lineID")->first(); //取得lineID所屬的user資料
            if($lineUser == null){  //查無此lineId註冊資料
                return response()->json([
                    "state" => false,
                    "info" => "尚未綁定line帳戶",
                    "UID" => "$lineID"
                ]);
            }
            //判斷是否有老師開過此課程//////////////////////////////////
            $userWithoutStu=UserEloquent::where('identity', 'T')
                ->orWhere('identity', 'M')
                ->get();
            $hasCourseId = false;        //判斷是否存在課程的變數
            for($i=0;$i<count($userWithoutStu);$i++){
                $userrrr = $userWithoutStu[$i];
                $courses = $userrrr->course;
                if(is_array($courses)){
                    $count=count($courses);            //計算課程總數

                    for($j=0 ; $j<$count ; $j++){       //查詢是否已經存在相同課程
                        $course = $courses[$j];
                        $Id = $course['Id'];

                        if($Id == $courseId){     //已存在此課程
                            $hasCourseId = true;
                            $courseName = $course['Name'];
                            break;
                        }else{                        //尚未新增此課程
                            continue;
                        }
                    }
                }

                if($hasCourseId == true){
                    break;
                }else{
                    continue;
                }
            }
            //////////////////////////////////////////////////////////////////
            if($hasCourseId == false){  //尚未有老師開設此課程
                return response()->json([
                    "state" => false,
                    "info" => "此課程不存在",
                    "UID" => "$lineID"
                ]);
            }

            $judgeNotNull = object_get($lineUser, 'course', 'nullData'); //判斷是否存在此欄位

            if($judgeNotNull == "nullData"){    //該使用者無課程欄位
                $lineUser->push('course',[
                    'Name' => "$courseName",
                    'Id' => "$courseId"
                ]);

                return response()->json([
                    "state" => true,
                    "info" => "新增成功",
                    "lineData" => [
                        "courseId" => "$courseId",
                        "class_name" => "$courseName",
                        "add_time" => "$time"
                    ],
                    "UID" => "$lineID"
                ]);
            }else{  //該使用者有課程欄位，判斷是否新增過此課程
                 $userCourses = $lineUser->course;
                 $count = count($userCourses);
                 for($i=0;$i<$count;$i++){  //判斷是否新增過此課程
                    $aCourse = $userCourses[$i];
                    $aCourseId = $aCourse["Id"];
                     if($aCourseId == $courseId){
                        return response()->json([   
                            "state" => false,
                            "info" => "已新增過此課程"
                        ]);
                     }
                 }
                 $lineUser->push('course',[
                                    'Name' => "$courseName",
                                    'Id' => "$courseId"
                                ]);

                return response()->json([
                        "state" => true,
                        "info" => "新增成功",
                        "lineData" => [
                            "courseId" => "$courseId",
                            "class_name" => "$courseName",
                            "add_time" => "$time"
                        ],
                        "UID" => "$lineID"
                ]);
            }
           
        }else{
            return response()->json([
                "state" => false,
                "message" => "errorPage"
            ]); 
        }
        
    }

    //查詢課程
    public function api_line_read_course(Request $request){
        $user = $request->user();
        if ($user->tokenCan('server:line')) {       //判斷是否有權限
            $lineData = $request->json();
            $lineID = $lineData->get("UID");
            $lineUser = UserEloquent::where("lineID", "$lineID")
            ->first();
            
            $judgeNotNull = object_get($lineUser, 'course', 'nullData'); //判斷是否存在此欄位
            if($judgeNotNull != "nullData"){    //course欄位存在
                $courses = $lineUser->course; //取得課程資料
                $allCourses = array();
                $count = count($courses);
                for($i=0;$i<$count;$i++){
                    $aCourse = $courses[$i];
                    if($aCourse["Id"] == "deleted"){
                        continue;
                    }else{
                        array_push($allCourses,$aCourse);
                    }
                }
                return response()->json([
                    "course" => $allCourses,
                    "UID" => $lineUser->lineID,
                    "state" => true,
                    "info" => "查詢完成",
                    "message" => "success"
                ]);
            }else{  //course欄位不存在
                return response()->json([
                    "state" => false,
                    "info" => "尚無課程",
                    "message" => "noData"
                ]);
            }
            
        }else{
            return response()->json([
                "state" => false,
                "message" => "errorPage"
            ]);
        }
    }
    
    //查詢體溫 //old version
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
            // return $lineUser->cardID;
            $judgeNotNull = object_get($lineUser, 'cardID', 'nullData');    //若為null 給j udgeNotNull 一個nullData字串
            
            //return optional($user->cardID);
            //return $judgeNotNull;
            //
            
            if ($judgeNotNull != "nullData") {
                       //取得天數
                $cardID = $lineUser->cardID;
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

                $abtemp =
                AbnormalTempEloquent::where('cardID', "$cardID")
                ->orWhere("lineID", "$lineID")
                //->where('time','<', '2020-10-12 23:31:30')  //撈此時間點之前的體溫
                ->where('time','>',"$calGetRecentDay")  //撈此時間點(calGetRecentDay)之後的體溫
                ->orderBy('time', 'asc')   //排序依據時間點遞增
                ->get($projections);    //撈資料庫temp,time欄位的資料


                if($temp == '[]' && $abtemp == '[]'){                      //期限內無資料
                    return response()->json([
                        "state" => true,
                        "info" => $getRecentDay."天內無資料",
                        "UID" => $lineID,
                        "count" => count($temp),
                        "no_data" => [true,true]
                    ]);
                }else if($temp == '[]'){
                    return response()->json([
                    "state" => true,
                    "info" => "查詢完成",
                    "UID" => $lineID,
                    "temp" => $temp,
                    "abtemp" => $abtemp,
                    "count" => count($temp),
                    "no_data" => [true,false]
                    ]); 
                }else if($abtemp == '[]'){
                    return response()->json([
                        "state" => true,
                        "info" => "查詢完成",
                        "UID" => $lineID,
                        "temp" => $temp,
                        "abtemp" => $abtemp,
                        "count" => count($temp),
                        "no_data" => [false,true]
                        ]); 
                }else{
                    return response()->json([
                        "state" => true,
                        "info" => "查詢完成",
                        "UID" => $lineID,
                        "temp" => $temp,
                        "abtemp" => $abtemp,
                        "count" => count($temp),
                        "no_data" => [false,false]
                        ]); 
                }
            }else{                                   //找不到此卡片用戶 回傳資料(lineID,體溫,時間)
                $judgeNotNull = object_get($lineUser, 'lineID', 'noline');//若為null 給j udgeNotNull 一個nullData字串
                if($judgeNotNull != "noline"){
                    $lineID = $lineUser->lineID;
                    $calGetRecentDay = Carbon::parse("$getRecentDay"." days ago")->toDateString()." 00:00:00";
                    $todayDate = Carbon::parse('today')->toDateTimeString();
                    $projections = ['temp', 'time'];    //抓取temp,time欄位
                    //get temperature data 
                    $temp =
                    TempEloquent::where("lineID", "$lineID")
                    //->where('time','<', '2020-10-12 23:31:30')  //撈此時間點之前的體溫
                    ->where('time','>',"$calGetRecentDay")  //撈此時間點(calGetRecentDay)之後的體溫
                    ->orderBy('time', 'asc')   //排序依據時間點遞增
                    ->get($projections);    //撈資料庫temp,time欄位的資料

                    $abtemp =
                    AbnormalTempEloquent::where("lineID", "$lineID")
                    //->where('time','<', '2020-10-12 23:31:30')  //撈此時間點之前的體溫
                    ->where('time','>',"$calGetRecentDay")  //撈此時間點(calGetRecentDay)之後的體溫
                    ->orderBy('time', 'asc')   //排序依據時間點遞增
                    ->get($projections);    //撈資料庫temp,time欄位的資料

                    
                    if($temp == '[]' && $abtemp == '[]'){                      //期限內無資料
                        return response()->json([
                            "state" => true,
                            "info" => $getRecentDay."天內無資料",
                            "UID" => $lineID,
                            "count" => count($temp),
                            "no_data" => [true,true]
                        ]);
                    }else if($temp == '[]'){
                        return response()->json([
                        "state" => true,
                        "info" => "查詢完成",
                        "UID" => $lineID,
                        "temp" => $temp,
                        "abtemp" => $abtemp,
                        "count" => count($temp),
                        "no_data" => [true,false]
                        ]); 
                    }elseif($abtemp == '[]'){
                        return response()->json([
                            "state" => true,
                            "info" => "查詢完成",
                            "UID" => $lineID,
                            "temp" => $temp,
                            "abtemp" => $abtemp,
                            "count" => count($temp),
                            "no_data" => [false,true]
                            ]); 
                    }else{
                        return response()->json([
                            "state" => true,
                            "info" => "查詢完成",
                            "UID" => $lineID,
                            "temp" => $temp,
                            "abtemp" => $abtemp,
                            "count" => count($temp),
                            "no_data" => [false,false]
                            ]); 
                    }
                    // return response()->json([
                    //     "state" => false,
                    //     "UID" => $lineID,
                    //     "message" => "card not find"
                    // ]);  
                }else{
                    return response()->json([
                        "state" => false,
                        "info" => "沒有line資料"
                    ]);
                }
            }
        }else{
            return response()->json([
                "state" => false,
                "message" => "errorPage"
            ]);
        }
        
    }

    //查詢體溫 //NEW version
    public function api_line_read_temp_new(Request $request){
        $user = $request->user();
        if ($user->tokenCan('server:line')) {       //判斷是否有權限
            //request
            $lineData = $request->json();
            $lineID = $lineData->get("UID");    //取得lineUserID
            $data = $lineData->get("data");     //取得Data資料          
            $getRecentDay = $data['day'];
            $lineUser =UserEloquent::where("lineID", "$lineID")->first();
            $judgeNoCard = object_get($lineUser, 'cardID', 'nullCard');    //若為null 給j udgeNotNull 一個nullData字串
            $calGetRecentDay = Carbon::parse("$getRecentDay"." days ago")->toDateString()." 00:00:00";
            $todayDate = Carbon::parse('today')->toDateTimeString();
            $projections = ['temp', 'time'];    //抓取temp,time欄位
            if ($judgeNoCard != "nullCard") { //有卡片資料的話，lineID 跟 cardID 都要再兩張表裡面找
                //有八種狀況
                $cardID = $lineUser->cardID;
                //return $cardID;
                $hasLineInNormalData = TempEloquent::where("lineID", "$lineID")->first();
                $hasCardInNormalData = TempEloquent::where("cardID", "$cardID")->first();
                $hasLineInAbNormalData = AbnormalTempEloquent::where("lineID", "$lineID")->first();
                $hasCardInAbNormalData = AbnormalTempEloquent::where("cardID", "$cardID")->first();
                if($hasLineInNormalData != '[]'){
                    //再找其他三比 LineInNormalData 有
                    if($hasCardInNormalData != '[]'){
                        //再找其他兩比LineInNormalData 有 CardInNormalData 有
                        if($hasLineInAbNormalData != '[]'){
                            //LineInNormalData 有 CardInNormalData 有 LineInAbNormalData有
                            //再找其他一比/
                            if($hasCardInAbNormalData != '[]'){//LN O CN O LA O CA O
                                //再找其他一比 //全部都有
                                $temp =
                                TempEloquent::where('cardID', "$cardID")
                                ->orWhere("lineID", "$lineID")
                                //->where('time','<', '2020-10-12 23:31:30')  //撈此時間點之前的體溫
                                ->where('time','>',"$calGetRecentDay")  //撈此時間點(calGetRecentDay)之後的體溫
                                ->orderBy('time', 'asc')   //排序依據時間點遞增
                                ->get($projections);    //撈資料庫temp,time欄位的資料

                                $abtemp =
                                AbnormalTempEloquent::where('cardID', "$cardID")
                                ->orWhere("lineID", "$lineID")
                                //->where('time','<', '2020-10-12 23:31:30')  //撈此時間點之前的體溫
                                ->where('time','>',"$calGetRecentDay")  //撈此時間點(calGetRecentDay)之後的體溫
                                ->orderBy('time', 'asc')   //排序依據時間點遞增
                                ->get($projections);    //撈資料庫temp,time欄位的資料
                                
                            }else{//LN O CN O LA O CA X
                                $temp =
                                TempEloquent::where('cardID', "$cardID")
                                ->orWhere("lineID", "$lineID")
                                //->where('time','<', '2020-10-12 23:31:30')  //撈此時間點之前的體溫
                                ->where('time','>',"$calGetRecentDay")  //撈此時間點(calGetRecentDay)之後的體溫
                                ->orderBy('time', 'asc')   //排序依據時間點遞增
                                ->get($projections);    //撈資料庫temp,time欄位的資料

                                $abtemp =
                                AbnormalTempEloquent::where('lineID', "$lineID")
                                //->where('time','<', '2020-10-12 23:31:30')  //撈此時間點之前的體溫
                                ->where('time','>',"$calGetRecentDay")  //撈此時間點(calGetRecentDay)之後的體溫
                                ->orderBy('time', 'asc')   //排序依據時間點遞增
                                ->get($projections);    //撈資料庫temp,time欄位的資料
                            }
                        }else{// LN O CN O LA X 
                            if($hasCardInAbNormalData != '[]'){//LN O CN O LA X CA O 
                                //再找其他一比 //全部都有
                                $temp =
                                TempEloquent::where('cardID', "$cardID")
                                ->orWhere("lineID", "$lineID")
                                //->where('time','<', '2020-10-12 23:31:30')  //撈此時間點之前的體溫
                                ->where('time','>',"$calGetRecentDay")  //撈此時間點(calGetRecentDay)之後的體溫
                                ->orderBy('time', 'asc')   //排序依據時間點遞增
                                ->get($projections);    //撈資料庫temp,time欄位的資料

                                $abtemp =
                                AbnormalTempEloquent::where('cardID', "$cardID")
                                //->where('time','<', '2020-10-12 23:31:30')  //撈此時間點之前的體溫
                                ->where('time','>',"$calGetRecentDay")  //撈此時間點(calGetRecentDay)之後的體溫
                                ->orderBy('time', 'asc')   //排序依據時間點遞增
                                ->get($projections);    //撈資料庫temp,time欄位的資料
                            }else{//LN O CN O LA X CA X
                                $temp =
                                TempEloquent::where('cardID', "$cardID")
                                ->orWhere("lineID", "$lineID")
                                //->where('time','<', '2020-10-12 23:31:30')  //撈此時間點之前的體溫
                                ->where('time','>',"$calGetRecentDay")  //撈此時間點(calGetRecentDay)之後的體溫
                                ->orderBy('time', 'asc')   //排序依據時間點遞增
                                ->get($projections);    //撈資料庫temp,time欄位的資料
                                $abtemp = '[]';
                            }
                        }
                    }else{
                        //LN O CN X
                        //LineInNormalData 有 CardInNormalData 沒有 再判斷Abnormal
                        if($hasLineInAbNormalData != '[]'){
                            //LN O CN X LA O
                            //LineInNormalData 有 CardInNormalData 沒有 LineInAbNormalData有
                            //再找其他一比/
                            if($hasCardInAbNormalData != '[]'){
                                //LN O CN X LA O CA O
                                $temp =
                                TempEloquent::where('lineID', "$lineID")
                                //->where('time','<', '2020-10-12 23:31:30')  //撈此時間點之前的體溫
                                ->where('time','>',"$calGetRecentDay")  //撈此時間點(calGetRecentDay)之後的體溫
                                ->orderBy('time', 'asc')   //排序依據時間點遞增
                                ->get($projections);    //撈資料庫temp,time欄位的資料

                                $abtemp =
                                AbnormalTempEloquent::where('cardID', "$cardID")
                                ->orWhere("lineID", "$lineID")
                                //->where('time','<', '2020-10-12 23:31:30')  //撈此時間點之前的體溫
                                ->where('time','>',"$calGetRecentDay")  //撈此時間點(calGetRecentDay)之後的體溫
                                ->orderBy('time', 'asc')   //排序依據時間點遞增
                                ->get($projections);    //撈資料庫temp,time欄位的資料
                            }else{
                                //LN O CN X LA O CA X
                                $temp =
                                TempEloquent::where('lineID', "$lineID")
                                //->where('time','<', '2020-10-12 23:31:30')  //撈此時間點之前的體溫
                                ->where('time','>',"$calGetRecentDay")  //撈此時間點(calGetRecentDay)之後的體溫
                                ->orderBy('time', 'asc')   //排序依據時間點遞增
                                ->get($projections);    //撈資料庫temp,time欄位的資料

                                $abtemp =
                                AbnormalTempEloquent::where('lineID', "$lineID")
                                //->where('time','<', '2020-10-12 23:31:30')  //撈此時間點之前的體溫
                                ->where('time','>',"$calGetRecentDay")  //撈此時間點(calGetRecentDay)之後的體溫
                                ->orderBy('time', 'asc')   //排序依據時間點遞增
                                ->get($projections);    //撈資料庫temp,time欄位的資料
                            }
                            
                        }else{
                            //LN O CN X LA X
                            if($hasCardInAbNormalData != '[]'){
                                //LN O CN X LA X CA O
                                //LineInNormalData 有 CardInNormalData 沒有 LineInAbNormalData沒有 CardInAbNormalData有
                                $temp =
                                TempEloquent::where('lineID', "$lineID")
                                //->where('time','<', '2020-10-12 23:31:30')  //撈此時間點之前的體溫
                                ->where('time','>',"$calGetRecentDay")  //撈此時間點(calGetRecentDay)之後的體溫
                                ->orderBy('time', 'asc')   //排序依據時間點遞增
                                ->get($projections);    //撈資料庫temp,time欄位的資料

                                $abtemp =
                                AbnormalTempEloquent::where('cardID', "$cardID")
                                //->where('time','<', '2020-10-12 23:31:30')  //撈此時間點之前的體溫
                                ->where('time','>',"$calGetRecentDay")  //撈此時間點(calGetRecentDay)之後的體溫
                                ->orderBy('time', 'asc')   //排序依據時間點遞增
                                ->get($projections);    //撈資料庫temp,time欄位的資料
                            }else{
                                //LN O CN X LA X CA X
                                $temp =
                                TempEloquent::where('lineID', "$lineID")
                                //->where('time','<', '2020-10-12 23:31:30')  //撈此時間點之前的體溫
                                ->where('time','>',"$calGetRecentDay")  //撈此時間點(calGetRecentDay)之後的體溫
                                ->orderBy('time', 'asc')   //排序依據時間點遞增
                                ->get($projections);    //撈資料庫temp,time欄位的資料

                                $abtemp = '[]';
                            }
                            //LineInNormalData 有 CardInNormalData 沒有 LineInAbNormalData沒有 CardInAbNormalData沒有
                        }
                    }
                }else{
                    //LN X
                    //再找其他三比 LineInNormalData 沒有
                    if($hasCardInNormalData != '[]'){
                        //LN X CN O
                        //再找其他兩比LineInNormalData 沒有 CardInNormalData 有
                        if($hasLineInAbNormalData != '[]'){
                            //LN X CN O LA O
                            //LineInNormalData 沒有 CardInNormalData 有 LineInAbNormalData有
                            //再找其他一比/
                            if($hasCardInAbNormalData != '[]'){
                                //LN X CN O LA O CA O
                                $temp =
                                TempEloquent::where('cardID', "$cardID")
                                //->where('time','<', '2020-10-12 23:31:30')  //撈此時間點之前的體溫
                                ->where('time','>',"$calGetRecentDay")  //撈此時間點(calGetRecentDay)之後的體溫
                                ->orderBy('time', 'asc')   //排序依據時間點遞增
                                ->get($projections);    //撈資料庫temp,time欄位的資料

                                $abtemp =
                                AbnormalTempEloquent::where('cardID', "$cardID")
                                ->orWhere("lineID", "$lineID")
                                //->where('time','<', '2020-10-12 23:31:30')  //撈此時間點之前的體溫
                                ->where('time','>',"$calGetRecentDay")  //撈此時間點(calGetRecentDay)之後的體溫
                                ->orderBy('time', 'asc')   //排序依據時間點遞增
                                ->get($projections);    //撈資料庫temp,time欄位的資料
                                //LineInNormalData 沒有 CardInNormalData 有 LineInAbNormalData有 CardInAbNormalData 有
                            }else{//LN X CN O LA O CA X
                                $temp =
                                TempEloquent::where('cardID', "$cardID")
                                //->where('time','<', '2020-10-12 23:31:30')  //撈此時間點之前的體溫
                                ->where('time','>',"$calGetRecentDay")  //撈此時間點(calGetRecentDay)之後的體溫
                                ->orderBy('time', 'asc')   //排序依據時間點遞增
                                ->get($projections);    //撈資料庫temp,time欄位的資料

                                $abtemp =
                                AbnormalTempEloquent::where('lineID', "$lineID")
                                //->where('time','<', '2020-10-12 23:31:30')  //撈此時間點之前的體溫
                                ->where('time','>',"$calGetRecentDay")  //撈此時間點(calGetRecentDay)之後的體溫
                                ->orderBy('time', 'asc')   //排序依據時間點遞增
                                ->get($projections);    //撈資料庫temp,time欄位的資料
                            }
                            //LineInNormalData 沒有 CardInNormalData 有 LineInAbNormalData有 CardInAbNormalData 沒有
                        }else{
                            //LN X CN O LA X
                            if($hasCardInAbNormalData != '[]'){
                                //LN X CN O LA O CA O
                                $temp =
                                TempEloquent::where('cardID', "$cardID")
                                //->where('time','<', '2020-10-12 23:31:30')  //撈此時間點之前的體溫
                                ->where('time','>',"$calGetRecentDay")  //撈此時間點(calGetRecentDay)之後的體溫
                                ->orderBy('time', 'asc')   //排序依據時間點遞增
                                ->get($projections);    //撈資料庫temp,time欄位的資料

                                $abtemp =
                                AbnormalTempEloquent::where('cardID', "$cardID")
                                ->orWhere("lineID", "$lineID")
                                //->where('time','<', '2020-10-12 23:31:30')  //撈此時間點之前的體溫
                                ->where('time','>',"$calGetRecentDay")  //撈此時間點(calGetRecentDay)之後的體溫
                                ->orderBy('time', 'asc')   //排序依據時間點遞增
                                ->get($projections);    //撈資料庫temp,time欄位的資料
                                //LineInNormalData 沒有 CardInNormalData 有 LineInAbNormalData有 CardInAbNormalData 有
                            }else{//LN X CN O LA O CA X
                                $temp =
                                TempEloquent::where('cardID', "$cardID")
                                //->where('time','<', '2020-10-12 23:31:30')  //撈此時間點之前的體溫
                                ->where('time','>',"$calGetRecentDay")  //撈此時間點(calGetRecentDay)之後的體溫
                                ->orderBy('time', 'asc')   //排序依據時間點遞增
                                ->get($projections);    //撈資料庫temp,time欄位的資料

                                $abtemp =
                                AbnormalTempEloquent::where('lineID', "$lineID")
                                //->where('time','<', '2020-10-12 23:31:30')  //撈此時間點之前的體溫
                                ->where('time','>',"$calGetRecentDay")  //撈此時間點(calGetRecentDay)之後的體溫
                                ->orderBy('time', 'asc')   //排序依據時間點遞增
                                ->get($projections);    //撈資料庫temp,time欄位的資料
                            }
                        }
                    }else{
                        //LN X CN X
                        //LineInNormalData 沒有 CardInNormalData 沒有 再判斷Abnormal
                        if($hasLineInAbNormalData != '[]'){
                            //LN X CN X LA O
                            //LineInNormalData 沒有 CardInNormalData 沒有 LineInAbNormalData有
                            //再找其他一比/
                            if($hasCardInAbNormalData != '[]'){
                                //LN X CN X LA O CA O
                                $temp = '[]';

                                $abtemp =
                                AbnormalTempEloquent::where('cardID', "$cardID")
                                ->orWhere("lineID", "$lineID")
                                //->where('time','<', '2020-10-12 23:31:30')  //撈此時間點之前的體溫
                                ->where('time','>',"$calGetRecentDay")  //撈此時間點(calGetRecentDay)之後的體溫
                                ->orderBy('time', 'asc')   //排序依據時間點遞增
                                ->get($projections);    //撈資料庫temp,time欄位的資料
                                //LineInNormalData 沒有 CardInNormalData 沒有 LineInAbNormalData有 CardInAbNormalData有
                            }else{//LN X CN X LA O CA X
                                $temp = '[]';

                                $abtemp =
                                AbnormalTempEloquent::where('lineID', "$lineID")
                                //->where('time','<', '2020-10-12 23:31:30')  //撈此時間點之前的體溫
                                ->where('time','>',"$calGetRecentDay")  //撈此時間點(calGetRecentDay)之後的體溫
                                ->orderBy('time', 'asc')   //排序依據時間點遞增
                                ->get($projections);    //撈資料庫temp,time欄位的資料
                            }
                            //LineInNormalData 沒有 CardInNormalData 沒有 LineInAbNormalData有 CardInAbNormalData沒有
                        }else{
                            //LN X CN X LA X
                            if($hasCardInAbNormalData != '[]'){
                                //LN X CN X LA X CA O
                                $temp = '[]';

                                $abtemp =
                                AbnormalTempEloquent::where('cardID', "$cardID")
                                //->where('time','<', '2020-10-12 23:31:30')  //撈此時間點之前的體溫
                                ->where('time','>',"$calGetRecentDay")  //撈此時間點(calGetRecentDay)之後的體溫
                                ->orderBy('time', 'asc')   //排序依據時間點遞增
                                ->get($projections);    //撈資料庫temp,time欄位的資料
                                //LineInNormalData 沒有 CardInNormalData 沒有 LineInAbNormalData沒有 CardInAbNormalData有
                            }else{
                                //LN X CN X LA X CA X
                                $temp = '[]';
                                $abtemp = '[]';
                            }
                            //LineInNormalData 沒有 CardInNormalData 沒有 LineInAbNormalData沒有 CardInAbNormalData沒有
                        }
                    }
                }

            }else{                                   //找不到此卡片用戶 回傳資料(lineID,體溫,時間)
                $hasLineInNormalData = TempEloquent::where("lineID", "$lineID")->first();
                $hasLineInAbNormalData = AbnormalTempEloquent::where("lineID", "$lineID")->first();
                if($hasLineInNormalData != '[]'){
                    if($hasLineInAbNormalData != '[]'){
                        //line的都有
                        $temp =
                        TempEloquent::where('lineID', "$lineID")
                        //->where('time','<', '2020-10-12 23:31:30')  //撈此時間點之前的體溫
                        ->where('time','>',"$calGetRecentDay")  //撈此時間點(calGetRecentDay)之後的體溫
                        ->orderBy('time', 'asc')   //排序依據時間點遞增
                        ->get($projections);    //撈資料庫temp,time欄位的資料

                        $abtemp =
                        AbnormalTempEloquent::where('lineID', "$lineID")
                        //->where('time','<', '2020-10-12 23:31:30')  //撈此時間點之前的體溫
                        ->where('time','>',"$calGetRecentDay")  //撈此時間點(calGetRecentDay)之後的體溫
                        ->orderBy('time', 'asc')   //排序依據時間點遞增
                        ->get($projections);    //撈資料庫temp,time欄位的資料
                    }else{//AB LINE沒有
                        $temp =
                        TempEloquent::where('lineID', "$lineID")
                        //->where('time','<', '2020-10-12 23:31:30')  //撈此時間點之前的體溫
                        ->where('time','>',"$calGetRecentDay")  //撈此時間點(calGetRecentDay)之後的體溫
                        ->orderBy('time', 'asc')   //排序依據時間點遞增
                        ->get($projections);    //撈資料庫temp,time欄位的資料
                        $abtemp = '[]';
                    }
                }else{
                    if($hasLineInAbNormalData != '[]'){
                        //line的ab有 normal 沒有
                        $temp = '[]';
                        $abtemp =
                        AbnormalTempEloquent::where('lineID', "$lineID")
                        //->where('time','<', '2020-10-12 23:31:30')  //撈此時間點之前的體溫
                        ->where('time','>',"$calGetRecentDay")  //撈此時間點(calGetRecentDay)之後的體溫
                        ->orderBy('time', 'asc')   //排序依據時間點遞增
                        ->get($projections);    //撈資料庫temp,time欄位的資料
                    }else{
                        $temp = '[]';
                        $abtemp = '[]';
                    }
                    //都沒有
                }
            }
            /////////////////////////////////////////
            $count = count($temp) + count($abtemp);
            if($temp == '[]' && $abtemp == '[]'){                      //期限內無資料
                return response()->json([
                    "state" => true,
                    "info" => $getRecentDay."天內無資料",
                    "UID" => $lineID,
                    "count" => $count,
                    "no_data" => [true,true]
                ]);
            }else if($temp == '[]'){
                return response()->json([
                "state" => true,
                "info" => "查詢完成",
                "UID" => $lineID,
                "temp" => $temp,
                "abtemp" => $abtemp,
                "count" => $count,
                "no_data" => [true,false]
                ]); 
            }else if($abtemp == '[]'){
                return response()->json([
                    "state" => true,
                    "info" => "查詢完成",
                    "UID" => $lineID,
                    "temp" => $temp,
                    "abtemp" => $abtemp,
                    "count" => $count,
                    "no_data" => [false,true]
                    ]); 
            }else{
                return response()->json([
                    "state" => true,
                    "info" => "查詢完成",
                    "UID" => $lineID,
                    "temp" => $temp,
                    "abtemp" => $abtemp,
                    "count" => $count,
                    "no_data" => [false,false]
                    ]); 
            }
        }else{
            return response()->json([
                "state" => false,
                "message" => "errorPage"
            ]);
        }
        
    }

    //課程內容
    public function api_line_read_course_data(Request $request){
        $user = $request->user();
        if ($user->tokenCan('server:line')) {       //判斷是否有權限
            //request
            $lineData = $request->json();
            $data = $lineData->get("data");
            $courseId = $data["class_code"];

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
                                $courseData = $course; //取得課程資料
                                break;
                            }else if(($userrrr->identity) == "M"){
                                $courseData = $course; //取得課程資料
                                break;
                            }else if(($userrrr->identity) == "S"){
                                continue;
                            }
                        }else{                        //尚未新增此課程
                            continue;
                        }
                    }
                }
            }
        }else{
            return response()->json([
                "state" => false,
                "message" => "errorPage"
            ]);
        }
    }

    //查詢卡片
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

    //綁定卡片(linelogin)
    public function api_line_card_bind(Request $request){
        $user = $request->user();
        if ($user->tokenCan('server:line')) {       //判斷是否有權限
            $lineData = $request->json();
            $lineID = $lineData->get("UID");//LineID
            $cardID = $lineData->get("CID");

            $lineUser = UserEloquent::where("lineID", "$lineID")->first();  //取得使用者資料(使用lineId)
            if($lineUser == null){  //尚未註冊Line
                $count = UserEloquent::count(); //計算user總數
                $count = $count + 1; //帳號ID遞增

                $userData = new UserEloquent;   //存入cardID和lineID
                //$userData->_id = $count;
                $userData->_id = $count;
                $userData->lineID = $lineID;
                $userData->cardID = $cardID;
                $userData->save();

                return response([
                    "data" => [
                        "state" => true,
                        "info" => "綁定成功",
                        "UID" => "$lineID",
                    ]
                ]);
            }else{  //已有line帳戶資料
                $lineUser->cardID = $cardID;
                $lineUser->save();
            }
        }else{  //沒有權限
            return response([
                "data" => [
                    "state" => false,
                    "info" => "fail",
                ]
                ]); 
        }
    }

    //綁定line notify
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

    //line login 
    public function api_line_login(Request $request) {
        $user = $request->user();
        if ($user->tokenCan('server:line')) {   //判斷是否有權限
            $data = $request->json();
            $lineID = $data->get("UID");
            //$line_access_token = $data->get("access_token");
            $userData = UserEloquent::where("lineID", "$lineID")    //取得此lineId的使用者資料
            ->first();
            
            if($userData == null){  //尚未有此用戶資料 新增此用戶
                return response([    //回傳api token
                    "state" => false,
                    "info" => "尚未註冊(lineID資料不存在)",
                    'message' => "login false no data"
                ]);
            }else{  //有用戶資料
                $judgeEmail = object_get($userData, 'email', 'noEmail');    //判斷有無email欄位(沒email表示尚未註冊)
                if($judgeEmail == "noEmail"){   //尚未註冊
                    return response([    //回傳api token
                        "state" => false,
                        "info" => "尚未註冊(卡片已綁定)",
                        'message' => "login false no data(card binded)"
                    ]);
                }
                $userIdentity = $userData->identity;    //從userData取得身分
                //$userData->line_access_token = $line_access_token;  //綁定access token

                    //判斷學生或老師給予分別不同token
                if(($userData->identity) == "T"){
                    $userData->tokens()->delete();
                    $token = $userData->createToken('react-token',  ['react:teacher'])->plainTextToken;//產生老師token
                    return response([    //回傳api token
                        "state" => true,
                        "info" => "登入成功",
                        'message' => "login success",
                        "identity" => "T",
                        'token' => $token,
                    ]);
                }
                else if(($userData->identity) == "S"){
                    $userData->tokens()->delete();
                    $token = $userData->createToken('react-token',  ['react:student'])->plainTextToken; //產生學生token
                    return response([    //回傳api token
                        "state" => true,
                        "info" => "登入成功",
                        'message' => "login success",
                        "identity" => "S",
                        'token' => $token,
                    ]);
                }
                else if(($userData->identity) == "M"){
                    $userData->tokens()->delete();
                    $token = $userData->createToken('react-token',  ['react:master'])->plainTextToken; //產生學生token
                    return response([    //回傳api token
                        "state" => true,
                        "info" => "登入成功",
                        'message' => "login success",
                        "identity" => "M",
                        'token' => $token,
                    ]);
                }
            }
        
        
        } else {
            return response()->json([
                "state" => false,
                "info" => "登入失敗",
                'message' => "linebotServer token error"
            ]); 
        }
    }

    
}