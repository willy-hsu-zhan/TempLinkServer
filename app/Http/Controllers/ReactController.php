<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;
use Illuminate\Support\Facades\Hash;
use App\User as UserEloquent;
use App\Temperature as TempEloquent;
use App\Abnormaltemp as AbnormalTempEloquent;
use Validator;
use Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Mail;
use Illuminate\Support\Str;

class ReactController extends Controller
{   
    //註冊 - 停用
    public function api_regist(Request $request){
        $input = $request->all();
        $data = $request->json();
        $name = $data->get("name");
        $email = $data->get("email");
        $lineID = $data->get("lineID");
        $identity = $data->get("identity");
        $password = $data->get("password");
        $confirm_password = $data->get("confirm_password");

        
        $rules=[
            'name' => [
                'required',
                'max:50',
            ],
            'email' => [
                'required',
                'max:50',
                'email'
            ],
            'password' => [
                'required',
                'same:confirm_password',
                'min:3',
            ],
            
            'confirm_password' => [
                'required',
                'min:3',
            ]
            
        ];
        $user = UserEloquent::where("email", "$email")
        ->first(); 
        
        if($user == null)   //此帳號尚未註冊
        {
            $validator =Validator::make($input,$rules);
                if($validator->fails())
                {
                    return $validator->messages();
                }
                else
                {
                    $count = UserEloquent::count(); //計算user總數
                    $count = $count +1; //帳號ID遞增

                    $userdata = new UserEloquent(); 
                    $userdata->_id = $count;
                    $userdata->name = $name;
                    $userdata->email = $email;

                    // 老師及管理員
                    if ( $identity == "T" || $identity == "M" ) {
                        $userdata->waitAuth = false;
                    } else if ($identity == "S") {
                        $userdata->waitAuth = true;
                    }

                    $userdata->lineID = $lineID;
                    $userdata->identity = $identity;
                    $userdata->password = Hash::make($password);
                    $userdata->save();

                    return response()->json([
                        "state" => true,
                        "registStatus" => true,
                        "info" => "註冊成功"
                    ]);
                }
        } else { // 此帳號已註冊
            return response()->json([
                "state" => false,
                "registStatus" => false,
                "info" => "註冊失敗"
            ]);
        }
    }

    //登入 - 停用
    public function api_login(Request $request){
        $input = $request->all();
        $data = $request->json();
        $email = $data->get("email");
        $password = $data->get("password");
        //$identity = $data->get("identity");
        
        $rules=[
            'email' => [
                'required',
                'max:50',
                'email'
            ],
            'password' => [
                'required',
                'min:3',
            ],
        ];

        $user = UserEloquent::where('email', $request->email)->first();
        if($user != null){  //此帳號存在
            $password = $user->password;
            $validator = Validator::make($input,$rules);
            if($validator->fails()){
                return $validator->messages();
            }
            else{
                if ($user == null || (!$user || !Hash::check($request->password, $password))) {
                    return response([
                        'status' => false,
                        'info' => ['帳號或密碼錯誤!!']
                    ], 404);
                }

                //判斷學生或老師給予分別不同token
                if(($user->identity) == "T"){
                    $user->tokens()->delete();
                    $token = $user->createToken('react-token',  ['react:teacher'])->plainTextToken;//產生老師token
                    //$userID =intval($token);//產生該用戶有的 tokenable_id 與 token 結合作驗證
                    $response = [
                        'token' => $token,
                        "status" => true,
                        'identity' => $user->identity
                    ];
                    return response($response, 201);
                }
                else if(($user->identity) == "S"){
                    $user->tokens()->delete();
                    $token = $user->createToken('react-token',  ['react:student'])->plainTextToken; //產生學生token
                    //$userID =intval($user->tokenable_id) + intval($token);//產生該用戶有的 tokenable_id 與 token 結合作驗證
                    $response = [
                        'token' => $token,
                        "status" => true,
                        'identity' => $user->identity
                    ];
                    return response($response, 201);
                }
                else{
                    $user->tokens()->delete();
                    $token = $user->createToken('react-token',  ['react:master'])->plainTextToken; //產生學生token
                    //$userID =intval($user->tokenable_id) + intval($token);//產生該用戶有的 tokenable_id 與 token 結合作驗證
                    $response = [
                        'token' => $token,
                        "status" => true,
                        'identity' => $user->identity
                    ];
                    return response($response, 201);
                }
                
            }
        }
        else{                   //此帳號不存在 
            return response([
                "status" => false,
                "info" => "登入失敗"
            ]);
        }
    }

    //登出 - 停用
    public function api_logout(Request $request){   
        $user = $request->user();
        $user->tokens()->delete();

        return 'logout success';
    }

    //重設密碼 - 停用
    public function api_reset(Request $request){   
        $input = $request->all();
        $rules=[
            'email' => [
                'required',
                'max:50',
                'email'
            ],  
        ];

        $validator =Validator::make($input,$rules);
            if($validator->fails())
            {
                return $validator->messages();
            }else
            {
                
            }
    }

    //新增課程(老師，管理者)
    public function api_insert_course (Request $request){   
        $user = $request->user();
        if ($user->tokenCan('react:teacher') || $user->tokenCan('react:master')) {       //判斷是否有權限(老師)
            //get data
            $data = $request->json();
            
            Carbon::setLocale('zh-tw');
            $courseName = $data->get("className");
            $courseText = $data->get("classText");
            $courseType = $data->get("classType");
            $time = Carbon::now()->toDateTimeString();  //取得現在時刻
            
            //save to database
            $courses = $user->course;   //取得user的course欄位+判斷是否存在課程欄位

            // $t = "AA" + microtime() % 1000 + date("mmss") + microtime() % 1000;

            // Hash::make(microtime());

            // $asciiA = 65;
            // $newClassID = "";
            // $timeValue = time();
            // while ( $timeValue>0 ) {
            //     $v = $timeValue % 26;
            //     $asc = chr($v + $asciiA);
            //     $timeValue = $timeValue / 26;
            // }

            //產生課程ID
            $asciiDef = 65; //ASCII Code (A~Z => 65~90)
            $userId = $user->_id;   //00001~99999
            $digitCount = strlen($userId)%4;  //取得數字位數
            $userIdASC = '';
            for ( $i=0 ; $i<$digitCount; $i++ ) {
                $j = $userId % 10;
                $asc = chr($asciiDef+$j);
                $userIdASC = $asc.$userIdASC;
                $userId = $userId / 10;
            }
            for($i=0; $i<5-$digitCount; $i++){
                $userIdASC = chr($asciiDef+0).$userIdASC;
            }

            if($courses == null){     //欄位不存在，直接新增課程
                $courseId = $userIdASC.chr($asciiDef+0);   //課程編號轉ASCII Code
                $user->push('course',[
                    'Name' => "$courseName",
                    'Id' => "$courseId",
                    'Text' => "$courseText",
                    'Type' => "$courseType",
                    'time' => "$time"
                ]);

                return response([
                    'message' => "success",
                    "state" => true, 
                    'Name' => "$courseName",
                    'Id' => "$courseId",
                    'Text' => "$courseText",
                    'Type' => "$courseType",
                    'time' => "$time"
                    
                ]);
            }
            else{                               //欄位存在,判斷是否重複
                $hasCourseName = false;        //判斷是否存在課程的變數

                $count=count($courses);            //計算課程總數

                for($j=0 ; $j<$count ; $j++){       //查詢是否已經存在相同課程
                    $course = $courses[$j];
                    $name = $course['Name'];
                    
                    if($name == $courseName){     //已存在此課程
                        $hasCourseName = true;    
                        break;
                    }else{                        //尚未新增此課程
                        continue;
                    }
                }
            }
            
            if($hasCourseName == false)    //若課程尚未存在
            {   
                $courseId = $userIdASC.chr($asciiDef+$count);   //課程編號轉ASCII Code
                $user->push('course',[
                    'Name' => "$courseName",
                    'Id' => "$courseId",
                    'Text' => "$courseText",
                    'Type' => "$courseType",
                    'time' => "$time"
                ]);
                

                return response([
                    'message' => "success",
                    'info' => "insert success!",
                    "state" => true, 
                    'Id' => "$courseId",
                    'Text' => "$courseText",
                    'Type' => "$courseType",
                    'time' => "$time"
                ]);
                
            }else{                             //若課程已存在
                return response([
                    "state" => false, 
                    "info" => "此筆課程已存在!"]);
            }
        }
        else{   //用戶未具權限
            return response([
                "state" => false, 
                "info" => "errorPage"]);
        }
        
    }

    //查詢課程(老師)　老師已開的課程(停用)
    public function api_read_course_teacher(Request $request){
        $user = $request->user();
        if ($user->tokenCan('react:teacher') ) { //判斷是否有權限
            $hasClass = $user->course;   //判斷是否存在課程欄位
            //echo $hasClass;
            if($hasClass == null){  //查無課程
                return response([
                    'course' => "",
                    'info' => '查無課程',
                    'state' => false

                ]);
            }
            else{   ///有課程資料
                $course = $user->course;
                return response([
                    'course' => $course,
                    'info' => 'success',
                    'state' => true

                ]);
            }
                
        }else{  //沒有權限
            return response([
                'info' => "errorPage",
                'state' => false
            ]);   
        }
    }

    //新增課程推播(老師，管理者) 
    public function api_push_course_message(Request $request){
        $user = $request->user();
        if ($user->tokenCan('react:master') || $user->tokenCan('react:teacher')){
            $data = $request->json();
            $text = $data->get("text");
            $courseId = $data->get("classId");
            
            $course_S_member = array();
            $course_not_notify_member = array();
            $userData =UserEloquent::all();
            $hasCourseId = false;        //判斷是否存在課程的變數
            for($i=0;$i<count($userData);$i++){
            $userrrr = $userData[$i];
            $judgehasCourse = object_get($userrrr, 'course', 'noCourse');
            if($judgehasCourse != "noCourse"){
                $courses = $userrrr->course;
                if(is_array($courses)){
                    $count=count($courses);            //計算課程總數

                    for($j=0 ; $j<$count ; $j++){       //查詢是否已經存在相同課程
                        $course = $courses[$j];
                        $Id = $course['Id'];

                        if($Id == $courseId){     //已存在此課程
                            $courseName = $course['Name'];
                            $hasCourseId = true;
                            if(($userrrr->identity) == "S"){
                                $notify_token = $userData->where('notify_token', 'exists', true)->first();
                                if($notify_token == null){
                                    array_push($course_not_notify_member,$userrrr->name);
                                }else{
                                    array_push($course_S_member,$userrrr->notify_token); 
                                }
                            }
                            break;
                        }else{                        //尚未新增此課程
                            continue;
                        }
                    }
                }
            }else{
                continue;
            }
            
            }

            if($hasCourseId == true){   
                $time = Carbon::now()->toDateTimeString();  //取得現在時刻
                $response = Http::post('https://nuucsiebot.ddns.net:5000/get_news',[
                    "notify_token" => $course_S_member,
                    "post_time" => $time,
                    "title" => "[ID]:".$courseId,
                    "class_name" => $courseName,
                    "text" => $text,
                ]);

                $result = $response->json();
                
                return response([
                    // "state" => true,
                    "state" => $result["state"],
                    // "info" => "傳送成功",
                    "info" => $result["info"],
                    "has_not_notify" => $course_not_notify_member,
                ]);
            }else{
                return response([
                    'state' => false,
                    'info' => "尚無學生加入課程"
                ]);
            }

        }else{
            return response([
                'state' => false,
                'info' => "errorPage"
            ]);
        }
    }

    //新增課程(學生) 
    public function api_insert_course_student(Request $request) {
        $courseName="";
        $user = $request->user();
        if ($user->tokenCan('react:student') || $user->tokenCan('react:teacher') || $user->tokenCan('react:master')) {       //判斷是否有權限(學生) 
            $data = $request->json();
             Carbon::setLocale('zh-tw');
            $courseId = $data->get("classId");
            //$courseTime = $data->get("courseTime");
            //$courseInfo = $data->get("courseInfo");
            $time = Carbon::now()->toDateTimeString();  //取得現在時刻
            $courseName = '';
            
            
            
            /////////////////////////////////////////////////////////
            $userWithoutStu=UserEloquent::where('identity', 'T')
                ->orWhere('identity', 'M')
                ->get();
            $hasCourseId = false;        //判斷是否存在課程的變數
            for($i=0;$i<count($userWithoutStu);$i++){   //判斷是否有老師開過此課程
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
            ///////////////////////////////////////////
            if($hasCourseId == true){
                $courses = $user->course;   //取得user的course欄位+判斷是否存在課程欄位
        
                $hasCourse = false;        //判斷是否存在課程的變數
                    if(is_array($courses)){
                        $count=count($courses);            //計算課程總數
                        for($j=0 ; $j<$count ; $j++){       //查詢是否已經存在相同課程
                            $course = $courses[$j];
                            $Id = $course['Id'];
                            
                            if($Id == $courseId){     //已存在此課程
                                $hasCourse = true;    
                                break;
                            }else{                        //尚未新增此課程
                                continue;
                            }
                        }
                    }
                  
                if($hasCourse == false)    //若課程尚未存在
                { 
                    $user->push('course',[
                        'Name' => "$courseName",
                        'Id' => "$courseId"
                        
                    ]);
                    
    
                    return response([
                        'state' => true,
                        "info" => "新增成功!",
                        // 'Id' => "$courseId"
                        ]);
                    
                }else{                             //若課程已存在
                    return response([
                        'state' => false,
                        "info" => "此筆課程已新增!"]);
                }
            }
            else{
                return response([
                    'state' => false,
                    "info" => "此課程不存在!"]);
            }
           
        }else{
            return response([
                'state' => false,
                'info' => 'errorpage'
            ]);
        }
    }
    
    //查詢課程(老師，學生，管理者)
    public function api_read_course(Request $request){
        $user = $request->user();
        if (($user->tokenCan('react:student')) || ($user->tokenCan('react:teacher')) || ($user->tokenCan('react:master'))) { //判斷是否有權限
            $courses = $user->course;   //判斷是否存在課程欄位
            if($courses == null){     //欄位不存在
                return response([
                    'state' => false,
                    'info' => "尚無課程!"
                ]);
            }
            $allCourses = array();
            for($i=0;$i< count($courses);$i++){
                $aCourse = $courses[$i];
                if($aCourse["Id"] == "deleted"){
                    continue;
                }else{
                    array_push($allCourses,$aCourse);
                }
            }

            return response([
                'state' => true,
                'course' => $allCourses
            ]);
            
                
        }else{  //沒有權限
            return response([
                'info' => "errorPage"
            ]);   
        }
    }

    //綁定卡片 - 停用
    public function api_card_bind(Request $request) {
        $reactData = $request->json();
        $cardID = $reactData->get("cardID");
        $email = $reactData->get("email");
        //檢查該使用者是否有新增卡片記錄
        $user = UserEloquent::where("email", "$email")
        ->first();
        $hasCardId = $user->cardID;
        /*
        $userEmail = UserEloquent::where("cardID", "$email")
        ->fisrt();
        
        $userCardId = UserEloquent::where("email", "$email")
        ->first();
        */
        //return $hasCardId;
        //判斷cardID是否有資料 
        
        if($hasCardId == null) {
            
            //$user->('')
            //$user->save();
            return response()->json([
                "data" => [
                    "Message" => "新增成功!",
                ]
            ]);
        }else{
            return response()->json([
                "data" => [
                    "Message" => "此卡號已新增",
                ]
            ]);
        }   
        
    }

    //註冊(line login)
    public function api_web_line_register(Request $request){
        $data = $request->json();
        // $input = $request->all();
        // $name = $request->input("name");
        $line_access_token = $data->get("access_token");
        $notify_token = $data->get("notify_token");
        $name = $data->get("name");
        $email = $data->get("email");
        $lineID = $data->get("lineID");
        $identity = $data->get("identity");
        $unit = $data->get("unit");

        // return response([
        //     "state" => true,
        //     "notify_token" => "$notify_token",
        //     "name" => "$name",
        //     "email" => "$email",
        //     "lineID" => "$lineID",
        //     "access_token" => "$line_access_token",
        //     "unit" => "$unit",
        // ]);   

        $rules=[
            'name' => [
                'required',
                'max:50',
            ],
            'email' => [
                'required',
                'max:50',
                'email'
            ],
        ];

        // $validator =Validator::make($input, $rules);
        // if($validator->fails())
        // {
        //     return $validator->messages();
        // }else{
        if (true) {
            
            // UserEloquent::where('_id', true, null);
            $user = UserEloquent::where('email', "$email")->first();    //找出此信箱的user資料
            $lineUser = UserEloquent::where('lineID', "$lineID")->first();    //找出line帳戶是否存在
            if($user != null){  //email已存在
                return response()->json([
                    "state" => false,
                    "info" => "此信箱已存在",
                ]);
            }else if($lineUser != null){    //已綁定過卡片
                $_id = $lineUser->_id;
                $email_token = $_id.Str::random(40);//隨機產生40碼token(加上userid)
                
                $lineUser->name = $name;
                $lineUser->email = $email;
                $lineUser->unit = $unit;
                $lineUser->notify_token = $notify_token;
                $lineUser->email_token = $email_token;
                $lineUser->identity = $identity;

                // 老師及管理員
                if ( $identity == "T" || $identity == "M" ) {
                    $lineUser->waitAuth = false;
                } else if ($identity == "S") {
                    $lineUser->waitAuth = true;
                }

                $lineUser->save();

                $lineUser->line_access_token = $line_access_token;

                $lineUser = UserEloquent::where('email', "$email")->first();    //找出此信箱的user資料

                $data = array(
                    'lineID'=>"$lineID",
                    'line_access_token'=>"$line_access_token",
                    'email_token'=>"$email_token"
                );

                Mail::send('email.send', $data, function($message) use ($email){
                    $message->subject('好疫罩會員信箱認證');
                    $message->to($email);
                });

                return response()->json([
                    "state" => true,
                    "info" => "已綁定過卡片，已寄發驗證信"
                ]);
            }
            else{  //尚未註冊過
                $count = UserEloquent::count(); //計算user總數
                $count = $count + 1; //帳號ID遞增

                $email_token = $count.Str::random(40);//隨機產生40碼token(加上userid)
                $userdata = new UserEloquent;
                // $userdata->_id = $count;
                
                //產生ID的方式
                // $num = microtime(true) * 10000 % 100000000; //取整數後取 0~99999999
                // $numstr = strval($num);
                // $classID = "";
                // 10 to 26
                // $t = $num;
                // while( $t>0 ) {
                //     $v = ($t % 26); //0~25 要遞增1位
                //     $t = intval($t / 26);
                //     $asc = chr($v + 65); // A~Z
                //     $classID = $asc.$classID;
                // }
                // //26 to 16
                // for ( $i=0; $i<strlen($classID); ++$i ) {
                //     $nv = $classID[$i];
                //     echo $nv;
                // }
                // $classIdProduct = "";
                // for ( $i=0; $i<strlen($classID); ++$i ) {
                //     $nv = intval(ord($classID[$i]));
                //     $hexv = strval(dechex($nv));
                //     $classIdProduct = $classIdProduct.$hexv;
                // }
                //產生ID的方式

                // $userdata->_id = $classIdProduct;////////
                ///////////
                $userdata->_id = $count;
                $userdata->lineID = $lineID;
                $userdata->name = $name;
                $userdata->email = $email;
                $userdata->unit = $unit;
                $userdata->notify_token = $notify_token;
                $userdata->email_token = $email_token;
                $userdata->identity = $identity;

                // 老師及管理員
                if ( $identity == "T" || $identity == "M" ) {
                    $userdata->waitAuth = false;
                } else if ($identity == "S") {
                    $userdata->waitAuth = true;
                }
                $userdata->save();

                $userdata->line_access_token = $line_access_token;

                $userdata = UserEloquent::where('email', "$email")->first();    //找出此信箱的user資料

                $data = array(
                    'lineID'=>"$lineID",
                    'line_access_token'=>"$line_access_token",
                    'email_token'=>"$email_token"
                );

                Mail::send('email.send', $data, function($message) use ($email){
                    $message->subject('好疫罩會員信箱認證');
                    $message->to($email);
                });

                return response()->json([
                    "state" => true,
                    "info" => "尚未註冊過，已寄發驗證信"
                ]);
            }

        }
        
    }

    // web line login 登入網頁(學生) # 簡單確認access_token 
    public function api_web_line_login(Request $request) {
        $data = $request->json();
        $line_access_token = $data->get("access_token");

        // 這裡應該反過來 用lineID檢查access_token比較好 網頁會傳甚麼過來
        // 目前lineBOT那邊 會給我們 AuthToken lineID access_token nickname(這不重要)
        $userdata = UserEloquent::where("line_access_token", "$line_access_token")
        ->first();

        // 測試
        // return response()->json([
        //     "state" => true,
        //     "line_access_token" => $line_access_token,
        //     "userdata" => json_encode($userdata) // 有抓到這個
        // ]);

        if ( $userdata == null) { // access_token驗證失敗
            return response()->json([    //回傳api token
                "state" => false,
                "message" => "login error"
            ]);
        } else { // access_token驗證成功 -> 發送AuthToken
            $identity = $userdata->identity;
            $waitAuth = $userdata->waitAuth;
            if($identity == "S"){
                $token = $userdata->createToken('react-token',  ['react:student'])->plainTextToken;
            }else if($identity == "T"){
                $token = $userdata->createToken('react-token',  ['react:teacher'])->plainTextToken;
            }else if($identity == "M"){
                $token = $userdata->createToken('react-token',  ['react:master'])->plainTextToken;
            }
            
            return response()->json([    //回傳api token
                "state" => true,
                "message" => "login success",
                "token" => "$token",
                "waitAuth" => $waitAuth
            ]);
        }
        
    }

    //刪除課程
    public function api_delete_course(Request $request) {
        $user = $request->user();
        if ($user->tokenCan('react:teacher') || $user->tokencan('react:student') || $user->tokencan('react:master')) { //判斷是否有權限 #管理者
            $data = $request->json();
            $courseId = $data->get('classId');
            $judgeHasCourseField = object_get($user, 'course', 'noCourse');
            $courseNameText = ""; //存課程名稱
            $hasCourse = false; //設定變數旗標無課程
            if($judgeHasCourseField != "noCourse"){//先驗證有無此課程(有課程)
                $time = Carbon::now()->toDateTimeString();  //取得現在時刻
                       //判斷是否存在課程的變數
                if(is_array($user->course)){
                    ////
                    $count=count($user->course);            //計算課程總數
                    for($j=0 ; $j<$count ; $j++){       //查詢是否已經存在相同課程
                        $judgeHasCourse = object_get($user, 'course', 'noCourse');
                        if($judgeHasCourse != "noCourse"){
                            $userInnerCourse = $user->course;
                            $courses = $userInnerCourse[$j];
                            $id = $courses['Id'];    //取得課程ID
                            if($id == $courseId){     //已存在此課程
                                $courseNameText = $courses["Name"];
                                $hasCourse = true;    
                                break;
                            }else{                        //尚未新增此課程
                                continue;
                            }
                            
                        }
                        
                    }
                    if($hasCourse == false){//不管是老師學生或管理者 只要沒課程直接response
                        return response([
                            "state" => false,
                            "message" => "刪除失敗(無此課程)"
                        ]);
                    }else{//有此課程 //////先刪除自己的
                        $judgeHasCourse = object_get($user, 'course', 'noCourse');
                        $courseName = $user->course;
                        $user->pull('course', [
                            'Id' => "$courseId",
                        ]);
                        $user->push('course', [
                            'Id' => "deleted",
                            'Name' => "deleted"
                        ]);
                        if($user->identity == "S"){
                            return response([
                                "state" => true,
                                "info" => "刪除成功",
                                "time" => "$time"
                            ]); 
                        }else{
                            //identity = M T 刪除包含學生的課程
                            
                            $allLineDataArray = array();//所有筆資料
                            $memberLineDataArray = array();//存取內部比資料一組一組塞回給allLineDataArray
                            $alluserData = UserEloquent::all();
                            $allDataCount = count($alluserData);
                            for($i=0;$i<$allDataCount;$i++){
                                if(($alluserData[$i]->identity == "M") || ($alluserData[$i]->identity == "T")){
                                    continue;//刪除所有課程中只要身分為其他老師或管理者的，跳過，因為新增課程以判斷
                                }else{//只要刪除學生的
                                    $judgeLoopHasCourse = object_get($user, 'course', 'noCourse');//檢查回圈內的課程有無此欄位
                                    if($judgeLoopHasCourse != "noCourse"){//回圈內的當筆資料有此課程的欄位
                                        $innerData = $alluserData[$i]->course;  //取得課程陣列
                                        if(is_array($innerData)){
                                            $userCourseCount = count($innerData);
                                            for($j=0;$j<$userCourseCount;$j++){
                                                $acourse = $innerData[$j];
                                                if($acourse["Id"] == $courseId){//匹配到老師開課對應的學生
                                                    
                                                    $alluserData[$i]->pull('course', [//刪除資料
                                                        'Id' => "$courseId",
                                                    ]);
                                                    $alluserData[$i]->push('course', [//新增刪除資料欄位並保留總數
                                                        'Id' => "deleted",
                                                        'Name' => "deleted"
                                                    ]);
                                                    //一定有lineID需再找line_access_token決定是否推播
                                                    $judgeHasLineAccessToken = object_get($user, 'course', 'notoken');
                                                    if($judgeHasLineAccessToken != "notoken"){
                                                        // array_push($memberLineDataArray,$alluserData[$i]->lineID);//存取一組line資料
                                                        array_push($memberLineDataArray,$alluserData[$i]->lineID);//兩筆資料
                                                        array_push($memberLineDataArray,$alluserData[$i]->line_access_token);//兩筆資料
                                                        array_push($memberLineDataArray,$alluserData[$i]->line_access_token);//傳到大陣列
                                                        $memberLineDataArray = array(); // array reset
                                                    }else{
                                                        continue;
                                                    }
                                                }
                                            }
                                        }
                                        
                                    }else{
                                        continue; //無此欄位直接進下一個迴圈
                                    }
                                }
                            }
                            //並且推播給個學生已刪除課程
                            // $text = "課號: ".$courseId." 課名: ".$courseNameText." 已於 ".$time." 刪除!";
                            // Http::post('https://nuucsiebot.ddns.net:5000/get_news',[
                            //     "message" => "warning",
                            //     "lineArray" => $allLineDataArray,
                            //     "text" => "$text",
                            // ]);
                            return response()->json([   //沒有權限
                                "state" => true,
                                "lineArray" => $allLineDataArray,
                                // "text" => "$text",
                                "message" => "刪除老師及學生課程成功"
                            ]);
                        }
                    }  
                }
                 
            }else{
                return response()->json([   //沒有權限
                    "state" => false,
                    "message" => "學生無加入任何課程"
                ]);
            }
        } 
        else {
            return response()->json([   //沒有權限
                "state" => false,
                "message" => "error"
            ]);
        }
    }

    //編輯課程
    public function api_edit_course(Request $request) {
        $user = $request->user();
        if ($user->tokenCan('react:master')) { //判斷是否有權限 #管理者
            $data = $request->json();
            $courseName = $data->get('className');
            $courseId = $data->get('classId');
            $courseText = $data->get('classText');
            $courseType = $data->get('classType');
            $time = Carbon::now()->toDateTimeString();  //取得現在時刻
            // return $time;
            // $usercourse = $user->course;
            $hasCourseFlag = false;        //判斷是否存在課程的變數
            $userData =UserEloquent::all();
            $count = count($userData);
            for($i=0;$i<$count;$i++){
                // return $userData[$i];
                // return count($userData[$i]);
                $innerUser = $userData[$i];
                // return $innerUser;
                $courses = $innerUser->course;
                if($innerUser->identity == "S") continue;
                if(is_array($courses)){
                    $innerCount=count($courses);
                    // echo $innerCount.$i."tesst";
                    for($j=0;$j<$innerCount;$j++){
                        $getCourses = $courses[$j];//重新附值 找內部
                        $Id = $getCourses['Id'];
                        if($Id == null) continue;
                        
                        if($Id == $courseId){
                            // return $getCourses['Type'];
                            
                            $hasCourseFlag = true;
                            $swapId = $getCourses['Id'];//swap data
                            $innerUser->pull('course', [
                                'Name' => $getCourses['Name'],//不穩定，沒欄位會出錯
                                'Text' => $getCourses['Text'],
                                'Id' => $getCourses['Id'],
                                'Type' => $getCourses['Type']
                                
                            ]);
                            $innerUser->push('course', [
                                'Name' => $courseName,
                                'Id' => $swapId,
                                'Text' => $courseText,
                                'Type' => $courseType,
                                'time' => $time
                            ]);
                            
                            $innerUser->save();
                            // return $innerUser->course;
                            // return $getCourses['Text'];
                            // $courses['Text'] = $courseText;
                            break;
                        }
                        
                    }
                }

                if($hasCourseFlag == true){
                    break;
                }else{
                    continue;
                }

            }
            
            if($hasCourseFlag){
                return response()->json([   
                    "state" => true,
                    "info" => "編輯成功"
                ]);
            }else{
                return response()->json([   
                    "state" => false,
                    "info" => "查無此課程"
                ]);
            }  
        }else if($user->tokenCan('react:teacher')){
            $data = $request->json();
            $courseName = $data->get('className');
            $courseId = $data->get('classId');
            $courseText = $data->get('classText');
            $courseType = $data->get('classType');
            
            $time = Carbon::now()->toDateTimeString();  //取得現在時刻
            // $usercourse = $user->course;
            $hasCourseFlag = false;        //判斷是否存在課程的變數
        
            $courses = $user->course;
            if(is_array($courses)){
                $innerCount=count($courses);    //計算課程總數
                // echo $innerCount.$i."tesst";
                for($j=0;$j<$innerCount;$j++){
                    $getCourses = $courses[$j];//重新附值 找內部
                    $Id = $getCourses['Id'];
                    if($Id == "deleted"){
                        continue;
                    }else{
                        if($Id == $courseId){
                            // return $getCourses['Type'];
                            $hasCourseFlag = true;
                            $swapId = $getCourses['Id'];//swap data
                            $user->pull('course', [
                                'Name' => $getCourses['Name'],//不穩定，沒欄位會出錯
                                'Text' => $getCourses['Text'],
                                'Id' => $getCourses['Id'],
                                'Type' => $getCourses['Type']
                            ]);
                            $user->push('course', [
                                'Name' => $courseName,
                                'Id' => $swapId,
                                'Text' => $courseText,
                                'Type' => $courseType,
                                'time' => $time
                            ]);
                            
                            $user->save();
                            // return $getCourses['Text'];
                            // $courses['Text'] = $courseText;
                        }
                    }
                }
            }
            if($hasCourseFlag){
                return response()->json([   
                    "state" => true,
                    "info" => "編輯成功"
                ]);
            }else{
                return response()->json([   
                    "state" => false,
                    "info" => "查無此課程"
                ]);
            }  
        }else{
            return response()->json([   //沒有權限
                "state" => false,
                "info" => "error"
            ]);
        }
    } 

    //查詢體溫
    public function api_read_temp_student(Request $request){
        $user = $request->user();
        if ($user->tokenCan('react:student') || $user->tokenCan('react:teacher') || $user->tokenCan('react:master')) {       //判斷是否有權限
            //request
            $data = $request->json();      
            $startDate = $data->get('startDate'); //取得查詢體溫期間(頭)
            $endDate = $data->get('endDate'); //取得查詢體溫期間(尾)
            // $startConvertDate = Carbon::parse("$startDate");
            // $endConvertDate = Carbon::parse("$endDate");
            // return $endConvertDate - $startConvertDate;
            // $cardID = $user->cardID;
            // //取得天數
            // //2020-10-22
            // // $getRecentDay = date('Y-m-d',$endDate) - date('Y-m-d',$startDate);
            //return $getRecentDay;
            //$calGetRecentDay = Carbon::parse("$getRecentDay"." days ago")->toDateString()." 00:00:00";
            //$todayDate = Carbon::parse('today')->toDateTimeString();
            $hasCardID = object_get($user, 'cardID', 'noCard');//若為null 給j udgeNotNull 一個nullData字串
            $hasLineID = object_get($user, 'linID', 'noLine');//若為null 給j udgeNotNull 一個nullData字串

            if($hasCardID == "noCard"){
                //lineID查詢
                $projections = ['temp', 'time', 'machine_id'];    //抓取temp,time,地點(機器編號)欄位
                //get temperature data 
                $temp = TempEloquent::where('lineID', "$user->lineID")
                ->where('time','>', "$startDate"." 00:00:00")  //撈此時間點($startDate)之後的體溫
                ->where('time','<', "$endDate"." 24:00:00")  //撈此時間點($endDate)之前的體溫
                ->orderBy('time', 'asc')   //排序依據時間點遞增
                ->get($projections);    //撈資料庫temp,time欄位的資料

                //get temperature data 
                $abtemp = AbnormalTempEloquent::where('lineID', "$user->lineID")
                ->where('time','>', "$startDate"." 00:00:00")  //撈此時間點($startDate)之後的體溫
                ->where('time','<', "$endDate"." 24:00:00")  //撈此時間點($endDate)之前的體溫
                ->orderBy('time', 'asc')   //排序依據時間點遞增
                ->get($projections);    //撈資料庫temp,time欄位的資料

                if($temp == '[]' && $abtemp == '[]'){                      //期限內無資料
                    return response()->json([
                        "state" => true,
                        "message" => $startDate." ~ ".$endDate."期間內無資料",
                        "count" => count($temp),
                        "no_data" => true
                    ]);
                }else{
                    return response()->json([
                        "state" => true,
                        "message" => "查詢完成",
                        "count" => count($temp),
                        "temp" => $temp,
                        "abtemp" => $abtemp,
                        "no_data" => false
                    ]); 
                }



                return response()->json([
                    "state" => false,
                    "message" => "尚未綁定卡片",
                    "no_data" => true
                ]);
            }else{  //有綁定卡片
                $cardID = $user->cardID;
            }
            
            $projections = ['temp', 'time', 'machine_id'];    //抓取temp,time,地點(機器編號)欄位
            //get temperature data 抓取line及卡片量測資料
            $temp =TempEloquent::where('cardID', "$cardID")
            ->orWhere('lineID', "$user->lineID")
            ->where('time','>', "$startDate"." 00:00:00")  //撈此時間點($startDate)之後的體溫
            ->where('time','<', "$endDate"." 24:00:00")  //撈此時間點($endDate)之前的體溫
            ->orderBy('time', 'asc')   //排序依據時間點遞增
            ->get($projections);    //撈資料庫temp,time欄位的資料

            $abtemp = AbnormalTempEloquent::where('cardID', "$cardID")
            ->orWhere('lineID', "$user->lineID")
            ->where('time','>', "$startDate"." 00:00:00")  //撈此時間點($startDate)之後的體溫
            ->where('time','<', "$endDate"." 24:00:00")  //撈此時間點($endDate)之前的體溫
            ->orderBy('time', 'asc')   //排序依據時間點遞增
            ->get($projections);    //撈資料庫temp,time欄位的資料

            if($temp == '[]' && $abtemp == '[]'){                      //期限內無資料
                return response()->json([
                    "state" => true,
                    "message" => $startDate." ~ ".$endDate."期間內無資料",
                    "count" => count($temp),
                    "no_data" => true
                ]);
            }else{
                return response()->json([
                    "state" => true,
                    "message" => "查詢完成",
                    "count" => count($temp),
                    "temp" => $temp,
                    "abtemp" => $abtemp,
                    "no_data" => false
                ]); 
            }
        }else{
            return response()->json([
                "state" => false,
                "message" => "errorPage"
            ]);
        }
        
    }

    //讀取個人資料//老師跟學生用
    public function api_read_profile(Request $request){     
        $user = $request->user();
        if ($user->tokenCan('react:student') || $user->tokenCan('react:teacher') || $user->tokenCan('react:master') ) {
            $projections = ['_id', 'name', 'identity', 'email', 'created_at' , 'lineID', 'cardID'];
            
            $profile = $user::get($projections);
            $userId = intval($user->_id);
            $userLineId = $user->lineID;
            //註冊後一定會有;lineID
            $userData = UserEloquent::where('lineID',"$userLineId")
            ->get($projections);
            return $userData;
            return response()->json([
                "state" => true,
                "data" => "$profile"
            ]);
        }else{      //未具權限
            return response()->json([
                "state" => false,
                "message" => "errorPage"
            ]);
        }
    }

    //查詢課程詳細資料(學生)
    public function api_read_course_data_student(Request $request){
        $user = $request->user();
        if ($user->tokenCan('react:student') || $user->tokenCan('react:teacher') || $user->tokenCan('react:master')){
            $data = $request->json();
            $courseId = $data->get("classId");
            $userData =UserEloquent::all();
            $course_T_member = array();
            $course_S_member = array();
            $hasCourseId = false;        //判斷是否存在課程的變數
            $courseData = "";
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
                                $courseData = $courses[$j]; //取得課程資料
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
                return response()->json([
                    "state" => true,
                    "info" => "查詢完成!",
                    "courseData" => $courseData,
                    "teacher_data" => $course_T_member,
                    "totalMember" => $totalMember
                    ]);
            }else{
                return response()->json([
                    "state" => false,
                    "info" => "此課程不存在!"]);
            }

        }else{
            return response()->json([
                "state" => false,
                'info' => "errorPage"
            ]);
        }
    }

    //讀取課程所有體溫資料
    public function api_read_course_all_temp_data(Request $request){    
        $user = $request->user();
    
        // $data_menber = array();

        if ($user->tokenCan('react:master') || $user->tokenCan('react:teacher')){
            $data = $request->json();
            $courseId = $data->get("classId");
            $userData =UserEloquent::all();
            $hasCourseId = false;        //判斷是否存在課程的變數
            $courseDataMember = array(); //放全部資料
            
            for($i=0;$i<count($userData);$i++){
                $userrrr = $userData[$i];
                $courses = $userrrr->course;
                if(is_array($courses)){
                    $count=count($courses);            //計算課程總數
                    $course_T_member = array();
                    $course_S_member = array();
                    for($j=0 ; $j<$count ; $j++){       //查詢是否已經存在相符課程
                        $course = $courses[$j];
                        $Id = $course['Id'];
                        if($Id == $courseId){     //課程相符
                            $hasCourseId = true;
                            if(($userrrr->identity) == "T"){
                                array_push($course_T_member,$userrrr->name);
                                $courseData = $course; //取得課程資料
                                break;
                            }else if(($userrrr->identity) == "M"){
                                array_push($course_T_member,$userrrr->name);
                                $courseData = $course; //取得課程資料
                                break;
                            }else if(($userrrr->identity) == "S"){
                                $projections = ['name', '_id' , 'lineID', 'email'];
                                $studentData = UserEloquent::where('lineID', "$userrrr->lineID")->first($projections);
                                array_push($course_S_member,$studentData);  //將學生資訊存入陣列

                                $judgeNotNull = object_get($userrrr, 'cardID', 'noCardData'); //判斷是否存在此欄位
                                ////////////push

                                $studentTempData = null;
                                $projections = ['temp', 'time' , 'machine_id'];

                                if($judgeNotNull != "noCardData"){  //有卡片欄位
                                    $studentTempData = TempEloquent::where('cardID',"$userrrr->cardID")
                                        ->orWhere('lineID',$userrrr->lineID)
                                        ->orderBy('time', 'desc')   //排序依據時間點遞增
                                        ->get($projections);
                                } else {  //無卡片欄位(有lineid)
                                    $studentTempData = TempEloquent::where('lineID',$userrrr->lineID)
                                        ->orderBy('time', 'desc')   //排序依據時間點遞增
                                        ->get($projections);
                                }

                                if($studentTempData == '[]')
                                    $studentLastTemp = null;
                                else
                                    $studentLastTemp = $studentTempData[0];

                                array_push($course_S_member, $studentLastTemp);//
                                //最後塞入
                                array_push($courseDataMember, $course_S_member);//
                                ////////////push
                                
                                break;
                                // array_push($course_S_member,$userrrr->name);
                            }
                        }else{                        //課程不相符
                            continue;
                        }
                    }
                }
            }

            if($hasCourseId == true){
                $totalMember = count($courseDataMember);
                return response()->json([
                        "state" => true,
                        "info" => "查詢完成! 回傳課程所有成員的資料及體溫",
                        "courseDataMember" => $courseDataMember,
                        "totalMember" => $totalMember
                    ]);
            }else{
                return response()->json([
                    "state" => false,
                    "info" => "此課程不存在!",
                    "totalMember" => 0
                ]);
            }

        }else{
            return response()->json([
                "state" => false,
                'info' => "errorPage"
            ]);
        }
    }

    
    
}
    
    

