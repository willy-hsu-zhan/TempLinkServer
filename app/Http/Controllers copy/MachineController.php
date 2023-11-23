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
    //寫入溫度(almost)
    public function api_temp_insert(Request $request) {
        $user = $request->user();
        if ($user->tokenCan('server:temp')) { //判斷是否有權限
            $data = $request->json();
            Carbon::setLocale('zh-tw');
            
            $apiKey = $data->get("apiKey");
            $cardID = $data->get("cardID");
            $temp = $data->get("temp");
            $machine_id = $data->get("machine_id");
            $time = Carbon::now()->toDateTimeString();
            
            //save to database
            if($temp >= 37.5){
                // $text = "注意!注意! ".$cardID." 於 ".$time." 測量體溫為 ".$temp."，狀態:體溫異常,請相關單位注意，依國家防疫規定處理。";
                // Http::post('https://nuucsiebot.ddns.net:5000/get_alert',[
                //     "message" => "warning",
                //     "text" => "$text",
                // ]);

                $abnormaltemp = new AbnormaltempEloquent();
                $abnormaltemp->cardID = $cardID;
                $abnormaltemp->temp = $temp;
                $abnormaltemp->machine_id = $machine_id;
                $abnormaltemp->time = $time;
                $abnormaltemp->save();
                
            }else{
                $tempEloquent = new TempEloquent();
                $tempEloquent->cardID = $cardID;
                $tempEloquent->temp = $temp;
                $tempEloquent->machine_id = $machine_id;
                $tempEloquent->time = $time;
                $tempEloquent->save(); 
            }
            
            $user = UserEloquent::where('cardID', "$cardID")->first();
            
            return response()->json([
                "state" => true,
                "message" => "success",
                "bind" => ($user!=null ? true : false),  //卡片綁定狀態
                "time" => "$time"
            ]);
            
        } else {
            return response()->json([
                "state" => false,
                "message" => "falied"
            ]);
        }  
    }
}
