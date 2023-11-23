<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
/////////
Route::post('iot/data/insert', 'IotController@api_data_insert');  

Route::post('iot/data/delete/all', 'IotController@api_data_delete_data');

Route::post('iot/data/query/all', 'IotController@api_data_query_all'); 

Route::post('iot/data/query/normal', 'IotController@api_data_query_normal');

Route::post('iot/data/query/abnormal', 'IotController@api_data_query_abnormal');


Route::post('iot/data/query/date', 'IotController@api_data_query_fromtime');


/////////
Route::group(['https'], function () {   //https

    ///machine--------------------------------------------------------------------------------
    
    //驗證token
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('temp/insert', 'MachineController@api_temp_insert');    //寫入體溫

        Route::post('temp/check', 'MachineController@api_access_control');  // 門禁管制

        Route::post('temp/course/push/alert/check', 'MachineController@api_push_course_alert_check');  //學生體溫異常推播給老師() 
    });
    
    
    //react--------------------------------------------------------------------------------
    //-----------會員登入註冊相關-----------

    Route::post('register', 'ReactController@api_web_line_register');    //註冊並綁定LINEnotify(一般帳戶)  ok

    Route::get('mail/verify/{line_access_token}/{lineID}/{email_token}', 'MailController@verify_email'); //驗證信箱 ok

    Route::post('login', 'ReactController@api_login');  //登入

    Route::post('web/line/login', 'ReactController@api_web_line_login'); //web line login 登入

    Route::post('regist', 'ReactController@api_regist');    //註冊

    Route::post('mail/send/verify/', 'MailController@send_verify_email');   //寄送信箱驗證信

    Route::post('mail/send/resetpassword', 'MailController@send_reset_password_email'); //寄送修改密碼的郵件

    Route::post('mail/verify/resetpassword/', 'MailController@verify_reset_password_email'); //驗證修改密碼的郵件
    //-----------會員登入註冊相關-----------
    //驗證token
    Route::middleware(['auth:sanctum'])->group(function () {
        //Route::post('waitcheckidentity', 'ReactController@test_token_can');
        //Route::post('waitauth', 'ReactController@api_waitauth'); 

        Route::post('course/delete', 'ReactController@api_delete_course');  //刪除課程 (老師學生管理者用) ok

        Route::post('course/insert', 'ReactController@api_insert_course');  //新增課程(老師，管理者) ok

        Route::post('course/edit', 'ReactController@api_edit_course');  //編輯課程(老師，管理者) ok

        Route::post('temp/course/read/all/student', 'ReactController@api_read_course_all_temp_data');    //查詢該堂課所有學生體溫 ok

        Route::post('course/push/message', 'ReactController@api_push_course_message');  //新增課程推播(老師，管理者)    ok 



        //-----------帳戶-----------
        Route::get('logout', 'ReactController@api_logout');    //登出

        Route::post('forget/password', 'ReactController@api_reset');    //忘記密碼 
        //-----------帳戶-----------


        //-----------查詢自己已開課程與所有課程-----------
        Route::post('course/read', 'ReactController@api_read_course'); //查詢老師/學生/管理者自己已選或已開的課程

        //Route::post('course/read/teacher', 'ReactController@api_read_course_teacher'); //查詢課程(老師)　老師已開的課程

        // Route::post('user/read/', 'ReactController@api_read_user'); //查詢老師/學生

        Route::post('course/data/read/student', 'ReactController@api_read_course_data_student');    //查詢課程詳細資料(學生)
        //-----------查詢自己已開課程與所有課程-----------


        //-----------新增課程與學生加入已存在的課程-----------
        

        Route::post('course/insert/student', 'ReactController@api_insert_course_student');  //加入課程(學生)
        //-----------加入課程與學生加入已存在的課程-----------


        //-----------卡片管理-----------
        Route::post('bind/card', 'ReactController@api_card_bind');     //綁定卡片
        //-----------卡片管理-----------


        //-----------課程刪除與編輯-----------
       

        
        //-----------課程刪除與編輯-----------

        //-----------課程推播相關-----------
        
        //-----------課程推播相關-----------

        //-----------查詢其他相關資料-----------////////////////
        Route::post('temp/read/student', 'ReactController@api_read_temp_student');    //查詢體溫(學生)

        

        Route::post('profile/read', 'ReactController@api_read_profile');    //查詢個人資料(老師，學生)
        //-----------查詢其他相關資料-----------
        
        
        // master-----------------------------------------------------------------------------------------
        //-----------體溫相關資料-----------
        Route::post('temp/read/master/', 'MasterController@api_master_read_temp');  //查詢所有體溫

        Route::post('abtemp/read/master', 'MasterController@api_master_read_abtemp');   //查詢異常體溫

        Route::post('userdata/query', 'MasterController@api_query_userdata');   //回傳所有使用者的資料及體溫(管理者)
        //-----------體溫相關資料-----------

        //-----------推播訊息-----------
        Route::post('message/push/', 'MasterController@api_push_message'); //推播訊息 
        //-----------推播訊息-----------
        
        //-----------帳號管理-----------
        Route::post('identity/edit', 'MasterController@api_edit_identity');   //更改使用者權限(管理者)

        Route::post('account/delete', 'MasterController@api_delete_account');   //刪除帳號(管理者)
        //-----------帳號管理-----------

        //-----------查詢刪除課程相關資料-----------
        Route::post('course/data/read', 'MasterController@api_master_read_course_data');    //查詢課程詳細資料和成員(老師，管理者)

        Route::post('course/delete/master', 'MasterController@api_delete_course_master');   //刪除課程(管理者)

        Route::post('course/data/all/read/master', 'MasterController@api_master_read_all_course_data');   //刪除課程(管理者)

        Route::post('convert/card/to/user/master', 'MasterController@api_card_covert_to_user');   //刪除課程(管理者)
        //-----------查詢刪除課程相關資料-----------
        // master-----------------------------------------------------------------------------------------
    });
    //line--------------------------------------------------------------------------------
    
    //驗證token
    Route::middleware(['auth:sanctum'])->group(function () {
        //-----------卡片管理-----------
        Route::post('line/find/card', 'LineController@api_line_find_card'); //查詢卡片有無綁定

        Route::post('line/bind/card', 'LineController@api_line_card_bind');     //綁定卡片
        //-----------卡片管理-----------

        //-----------課程管理-----------
        Route::post('line/course/insert', 'LineController@api_line_insert_course');    //加入課程

        Route::post('line/course/read', 'LineController@api_line_read_course'); //查詢課程
        //-----------課程管理-----------

        //-----------體溫管理-----------
        Route::post('line/temp/read', 'LineController@api_line_read_temp_new');     //查詢體溫
        //-----------體溫管理-----------
        
        //-----------帳戶相關-----------
        Route::post('line/login', 'LineController@api_line_login'); //line login

        Route::post('line/notify/token', 'LineController@api_line_notify_token_bind'); //綁定notify token
        //-----------帳戶相關-----------
    });

    //create token----------------------------------------------------------------------------------
    Route::post('line/create/token', 'TokenController@line_create_token');          //line

    Route::post('temp/create/token', 'TokenController@temp_create_token');          //machine
    
    //test create token
    Route::post('line/create/token/test', 'TokenController@line_create_token_test'); //testLine

    Route::post('temp/create/token/test', 'TokenController@temp_create_token_test'); //testMachine

    //--------------------------------------------------------------------------------

});


