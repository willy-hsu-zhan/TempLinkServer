<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;
use Illuminate\Support\Facades\Hash;
use App\User as UserEloquent;
use App\Temperature as TempEloquent;
use Validator;
use Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;


class ReactController extends Controller
{   
    //註冊(almost)
    public function api_regist(Request $request){
        $input = $request->all();
        $data = $request->json();
        $name = $data->get("name");
        $email = $data->get("email");
        //$identity = $data->get("identity");
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
                    $userdata->identity = "S";
                    $userdata->password = Hash::make($password);
                    $userdata->save();
                    return response([
                        "registStatus" => "true",
                        "info" => "註冊成功"
                        ]);
                }
        }else   //此帳號已註冊
        {
            return response([
                "registStatus" => "false",
                "info" => "註冊失敗"
                ]);
        }
    }


    //這個 不是 這是之前單純帳號密碼的 現在我們要走的是 由line導向前端頁面再經過後端驗證身分
    // 所以是 ->>>   lineBotServer -> dbServer -> 發還react.token -> lineBotServer傳回給前端頁面
    // 所以問題是 前端頁面帶著react.token 進入到的api 卻是要驗證server:line的token 導致一直不對
    //react.token是我們發還給line的 -> 沒有錯 -> 但是line再把這把token傳給前端那前端網頁就是下面這個login沒錯呀
    // 隊的 但是模式不一樣 現在下面這個是普通的 email / password 登入
    // 但現在的頁面是line login之後的前端頁面 所以沒有email / password
    // 所以可以這樣解 -> 再用一個 api_line_login 就可以好的

    //登入(almost)
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
                elseif(($user->identity) == "S"){
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

    //登出(almost)
    public function api_logout(Request $request){   
        $user = $request->user();
        $user->tokens()->delete();

        return 'logout success';
    }

    //重設密碼(not yet)
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

    //新增課程(老師)(almost)
    public function api_insert_course_teacher(Request $request){   
        $user = $request->user();
        if ($user->tokenCan('react:teacher') || $user->tokenCan('react:master')) {       //判斷是否有權限(老師)
            //get data
            $data = $request->json();
            
            Carbon::setLocale('zh-tw');
            $courseName = $data->get("class");
            //$courseTime = $data->get("courseTime");
            //$courseInfo = $data->get("courseInfo");
            $time = Carbon::now()->toDateTimeString();  //取得現在時刻
            
            //save to database
            $courses = $user->course;   //取得user的course欄位+判斷是否存在課程欄位

            //產生課程ID
            $asciiDef = 65; //ASCII Code (A~Z => 65~90)
            $userId = $user->_id;   //00001~99999
            $digitCount = strlen($userId);  //取得數字位數
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
                    'Id' => "$courseId"
                ]);

                return response([
                    'info' => "insert success!",
                    'Id' => "$courseId"
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
                    'Id' => "$courseId"
                ]);
                

                return response([
                    "info" => "insert success!",
                    'Id' => "$courseId"
                    ]);
                
            }else{                             //若課程已存在
                return response([
                    "info" => "此筆課程已新增!"]);
            }
        }
        else{   //用戶未具權限
            return response([
                "info" => "errorPage"]);
        }
        
    }

    //查詢課程(老師)(not yet)
    public function api_read_course_teacher(Request $request){
        $user = $request->user();
        if ($user->tokenCan('react:teacher') ) { //判斷是否有權限
            $hasClass = $user->course;   //判斷是否存在課程欄位
            //echo $hasClass;
            if($hasClass == null){  //查無課程
                return response([
                    'course' => "",
                    'info' => '查無課程'
                ]);
            }
            else{   ///有課程資料
                $course = $user->course;
                return response([
                    'course' => $course,
                    'info' => 'success'

                ]);
            }
                
        }else{  //沒有權限
            return response([
                'info' => "errorPage"
            ]);   
        }
    }

    //新增課程推播(老師)(not yet)
    public function api_push_course_message(Request $request){
        $user = $request->user();
        if ($user->tokenCan('react:teacher')){
            $data = $request->json();
            $text = $data->get("text");
            $courseName = $data->get("courseName");
            $courses = $user->course;

            $count=count($courses);            //計算課程總數

            for($j=0 ; $j<$count ; $j++){       //查詢是否已經存在相同課程
                $course = $courses[$j];
                $name = $course['Name'];
                
                if($name == $courseName){     //已存在此課程
                    $courseId = $course['Id'];
                    break;
                }else{                        //尚未新增此課程
                    continue;
                }
            }
            $course_S_member = array();
            $course_not_notify_member = array();
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
                            if(($userrrr->identity) == "S"){
                                $notify_token = $userData->where('notify_token', 'exists', true)->get();
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
            }

            if($hasCourseId = true){   
                Http::post('https://nuucsiebot.ddns.net:5000/get_news',[
                    "notify_token" => $course_S_member,
                    "text" => $text,
                ]);
                return response([
                    'info' => "傳送成功",
                    "has_not_notify" => $course_not_notify_member,

                ]);
            }else{
                return response([
                    'info' => "尚無學生加入課程"
                ]);
            }

        }else{
            return response([
                'info' => "errorPage"
            ]);
        }
    }

    public function api_read_user(Request $request){   
        $user = $request->user();
        if ($user->tokenCan('react:teacher')) {       //判斷是否有權限(老師)
            //get data
            $data = $request->json();
            $courseId = $data->get("courseId");
            
        }
        else{   //用戶未具權限
            return response([
                "info" => "errorPage"]);
        }
        
    }

    //新增課程(學生)(yap)
    public function api_insert_course_student(Request $request) {
        $courseName="";
        $user = $request->user();
        if ($user->tokenCan('react:student')) {       //判斷是否有權限(學生) 
            $data = $request->json();
             Carbon::setLocale('zh-tw');
            $courseId = $data->get("courseId");
            //$courseTime = $data->get("courseTime");
            //$courseInfo = $data->get("courseInfo");
            $time = Carbon::now()->toDateTimeString();  //取得現在時刻
            
            
            
            /////////////////////////////////////////////////////////
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
                        "info" => "insert success!",
                        // 'Id' => "$courseId"
                        ]);
                    
                }else{                             //若課程已存在
                    return response([
                        "info" => "此筆課程已新增!"]);
                }
            }
            else{
                return response([
                    "info" => "此課程不存在!"]);
            }
           
        }else{
            return response([
                'message' => 'errorpage'
            ]);
        }
    }
    
    //查詢群組
    public function api_read_course(Request $request){
        $user = $request->user();
        if ($user->tokenCan('react:student') || $user->tokenCan('react:teacher') || $user->tokenCan('react:master')) { //判斷是否有權限
            $courses = $user->course;   //判斷是否存在課程欄位
            //echo $hasClass;
            if($courses == null){     //欄位不存在，直接新增課程
                return response([
                    'info' => "尚無課程!"
                ]);
            }
            else{ 
                // $count=count($courses);            //計算課程總數
                // for($j=0 ; $j<$count ; $j++){       
                //     $course = $courses[$j];
                //     $name = $course['Name'];
                // }
                return response([
                    'info' => "尚無課程!",
                    'course' => $courses
                ]);
            }
                
        }else{  //沒有權限
            return response([
                'info' => "errorPage"
            ]);   
        }
    }

    //綁定卡片(not yet)
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

    //回傳所有使用者的資料及體溫(管理者)
    public function api_query_userdata(Request $request){
        $user = $request->user();
        if ($user->tokenCan('react:master')) { //判斷是否有權限 #管理者
            $data = $request->json();
            $projections = ['cardID', 'lineID', 'identity', 'created_at' ];
            $userData = UserEloquent::all($projections);   //回lineid和cardid
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

    // web line login 登入網頁(學生) # 簡單確認access_token # 已完成
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
            $token = $userdata->createToken('react-token',  ['react:student'])->plainTextToken;
            return response()->json([    //回傳api token
                "state" => true,
                "message" => "login success",
                "token" => "$token"
            ]);
        }
        
    }

    //刪除課程
    public function api_delete_course(Request $request) {
        $user = $request->user();
        if ($user->tokenCan('react:teacher')) { //判斷是否有權限 #管理者
            $data = $request->json();
            $courseName = $data->get('course');
            $usercourse = $user->course;
            $hasCourseName = false;        //判斷是否存在課程的變數

            $count=count($usercourse);            //計算課程總數

            for($j=0 ; $j<$count ; $j++){       //查詢是否已經存在相同課程
                $course = $usercourse[$j];
                $name = $course['Name'];
                
                if($name == $courseName){     //已存在此課程
                    $hasCourseName = true;    
                    break;
                }else{                        //尚未新增此課程
                    continue;
                }
            }
            
            if($hasCourseName == false){
                return response([
                    "state" => false,
                    "message" => "刪除失敗(無此課程)"
                ]);
            }

            $user->pull('course', [
                'Name' => "$courseName"
            ]);

            return response([
                "state" => true,
                "message" => "刪除成功"
            ]);      
        } 
        else {
            return response()->json([   //沒有權限
                "state" => false,
                "message" => "error"
            ]);
        }
    }

    
    
}
