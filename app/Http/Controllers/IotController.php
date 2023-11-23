<?php

namespace App\Support;
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\User as UserEloquent;
use App\Temperature as TempEloquent;
use App\Abnormaltemp as AbnormalTempEloquent;
use App\Machine as MachineEloquent;
use App\Iot as IotEloquent;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Support\Optional;

class IotController extends Controller
{
    public function api_data_insert(Request $request){ 
        $data = $request->json();
        $temp = $data->get("temp");
        $humidity = $data->get("humidity");
        $gps = $data->get("gps");
        $gas = $data->get("gas");
        $light = $data->get("light");
        $reaction = $data->get("reaction");
        $problemText = $data->get("problemType");//陣列拆解
        $dateTime = Carbon::now()->toDateTimeString();
        //return $problemText;
        // $todayDateTime;
        $userdata = new IotEloquent;
        $userdata->temp = $temp;
        $userdata->humidity = $humidity;
        $userdata->gps = $gps;
        $userdata->gas = $gas;
        $userdata->light = $light;
        
        $userdata->reaction = $reaction;
        $userdata->time = $dateTime;
        if($problemText != ["timing"]){
            for($i=0;$i<count($problemText);$i++){
                $userdata->push('problemType', [
                    $problemText[$i]
                ]);
            }
        }
        

        // $problemArray = array();
        // $beginPosition = 0;
        // //$conmaPosition = 0;
        // for( $i=0 ; $i < strlen($problemText) ; $i++ ){
        //     if($problemText[$i] == ","){
                
        //         array_push($problemArray,substr($problemText,$beginPosition,$i+1));
        //         $beginPosition = $i;
        //         $conmaPosition = $i;
        //     }
        //     if($i == strlen($problemText)-1){
        //         array_push($problemArray,substr($problemText,$beginPosition,$beginPosition-$i));
        //     }
        // }
        //return $problemArray;
        // 
        
        $userdata->save();
        //return "OK";
        return response()->json([   //沒有權限
            "state" => true,
            "info" => "新增成功"
        ]);
    }

    public function api_data_query_all(Request $request){ 
        $data = $request->json();
        // return $todayDateTime;
        $projections = ['temp', 'humidity', 'gps', 'gas', 'light', 'problemType','reaction','time'];    //資料欄位
                //get temperature data 
        $userAllData = IotEloquent::all($projections);
        // return $userAllData;
        // $temp = IotEloquent::where('lineID', "$user->lineID")
        // ->where('time','>', "$startDate"." 00:00:00")  //撈此時間點($startDate)之後的體溫
        // ->where('time','<', "$endDate"." 24:00:00")  //撈此時間點($endDate)之前的體溫
        // ->orderBy('time', 'asc')   //排序依據時間點遞增
        // ->get($projections);    //撈資料庫temp,time欄位的資料
        if($userAllData == '[]'){
            return response()->json([
                "info" => "無資料!",
                "state" => false
            ]);
        }else{
            return response()->json([
                "info" => "查詢所有資料成功",
                "data" => $userAllData,
                "state" => true
            ]);
        }
        
    }

    public function api_data_query_fromtime(Request $request){ 
        $data = $request->json();
        $startDate = $data->get("startTime");
        $startDate = $startDate." 00:00:00";
        $endDate = $data->get("endTime");
        $endDate = $endDate." 24:00:00";
        $todayDateTime = Carbon::now()->toDateTimeString();
        // $cal = $endTime - $startTime;
        // return $cal;
        // return $todayDateTime;
        //if()
        $projections = ['temp', 'humidity', 'gps', 'gas', 'light', 'problemType','reaction','time'];     //資料欄位
                //get temperature data 
        $userFragmentData = IotEloquent::all($projections);
        $dataCount = count($userFragmentData);
        $dataMember = array();
        for($i=0;$i<$dataCount;$i++){
            if(($startDate <= $userFragmentData[$i]["time"]) && ($userFragmentData[$i]["time"]) <= $endDate){
                // return $userFragmentData[$i];
                array_push($dataMember,$userFragmentData[$i]);
            }
        }
        //where('time','>', "$startDate"." 00:00:00")  //撈此時間點($startDate)之後的體溫
        // ->where('time','<', "$endDate"." 24:00:00")  //撈此時間點($endDate)之前的體溫
        // ->orderBy('time', 'asc')   //排序依據時間點遞增
        // ->get($projections);    //撈資料庫temp,time欄位的資料
        // return $userFragmentData;
        // return $userAllData;
        // $temp = IotEloquent::where('lineID', "$user->lineID")
        
        if($userFragmentData == '[]'){
            return response()->json([
                'info' => "時間內無資料!",
                "state" => false
            ]);
        }else{
            return response()->json([
                'info' => "查詢時間區段資料成功",
                "data" => $dataMember,
                "state" => true
            ]);
        }
    }

    public function api_data_query_abnormal(Request $request){ 
        $data = $request->json();
        $projections = ['temp', 'humidity', 'gps', 'gas', 'light', 'problemType','reaction','time'];     //資料欄位
                //get temperature data 
        $userData = IotEloquent::all($projections);
        $dataCount = count($userData);
        $dataMember = array();
        for($i=0;$i<$dataCount;$i++){
            $judgehasAbnormalField = object_get($userData[$i], 'problemType', "NoProblem");
            if($judgehasAbnormalField != "NoProblem"){
                array_push($dataMember,$userData[$i]);
            }
            // if($userData[$i]["status"] == true){//狀態為true為異常資料
                
            // }
            continue;
        }
        //return $dataMember;
        // ->orderBy('time', 'asc')   //排序依據時間點遞增
        // ->get($projections);    //撈資料庫temp,time欄位的資料
        //return $userFragmentData;
        // return $userAllData;
        // $temp = IotEloquent::where('lineID', "$user->lineID")
        
        if(count($userData) == 0){
            return response()->json([
                'info' => "無異常資料!",
                "state" => false
            ]);
        }else{
            return response()->json([
                'info' => "查詢資料成功",
                "data" => $dataMember,
                "state" => true
            ]);
        }
    }

    public function api_data_query_normal(Request $request){ 
        $data = $request->json();
        $todayDateTime = Carbon::now()->toDateTimeString();
        // return $todayDateTime;
        $projections = ['temp', 'humidity', 'gps', 'gas', 'light', 'problemType','reaction','time'];    //資料欄位
                //get temperature data 
        $userData = IotEloquent::all();
        $dataCount = count($userData);
        $dataMember = array();
        for($i=0;$i<$dataCount;$i++){
            $judgehasAbnormalField = object_get($userData[$i], 'problemType', "NoProblem");
            if($judgehasAbnormalField == "NoProblem"){
                array_push($dataMember,$userData[$i]);
            }
            // if($userData[$i]["status"] == true){//狀態為true為異常資料
                
            // }
            continue;
        }
        // for($i=0;$i<$dataCount;$i++){
        //     if($userData[$i]["status"] == false){//狀態為true為異常資料
        //         array_push($dataMember,$userData[$i]);
        //     }
        //     continue;
        // }

        if(count($userData) == 0){
            return response()->json([
                'info' => "無正常資料!",
                "state" => false
            ]);
        }else{
            return response()->json([
                'info' => "查詢資料成功",
                "data" => $dataMember,
                "state" => true
            ]);
        }
    }

    public function api_data_delete_data(Request $request){ 
        
        $userData = IotEloquent::all();
        $countData = count($userData);
        for($i = 0 ; $i < $countData ; $i++){
            $userData[$i]->delete();
        }
        //$userData->delete();
        
       
        return response()->json([
                'info' => "刪除所有資料成功",
                "state" => true
        ]);
        
    }
}
