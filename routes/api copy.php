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

Route::group(['https'], function () {   //https

    ///machine--------------------------------------------------------------------------------
    
    //驗證token
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('temp/insert', 'MachineController@api_temp_insert');    //寫入體溫
    });
    
    
    //react--------------------------------------------------------------------------------

    Route::post('login', 'ReactController@api_login');  //登入

    Route::post('web/line/login', 'ReactController@api_web_line_login'); //web line login 登入

    Route::post('regist', 'ReactController@api_regist');    //註冊 

    Route::post('mail/send/verify/', 'MailController@send_verify_email');   //寄送信箱驗證信

    Route::post('mail/verify/', 'MailController@verify_email'); //驗證信箱

    Route::post('mail/send/resetpassword', 'MailController@send_reset_password_email'); //寄送修改密碼的郵件

    Route::post('mail/verify/resetpassword/', 'MailController@verify_reset_password_email'); //驗證修改密碼的郵件

    //驗證token
    Route::middleware(['auth:sanctum'])->group(function () {
        //Route::post('waitcheckidentity', 'ReactController@test_token_can');
        //Route::post('waitauth', 'ReactController@api_waitauth'); 
        Route::get('logout', 'ReactController@api_logout');    //登出

        Route::post('read/course', 'ReactController@api_read_course'); //查詢老師/學生

        Route::post('read/user/', 'ReactController@api_read_user'); //查詢老師/學生

        Route::post('forget/password', 'ReactController@api_reset');    //忘記密碼  

        Route::post('course/insert', 'ReactController@api_insert_course');    //新增課程(老師)

        Route::post('course/read/teacher', 'ReactController@api_read_course_teacher');    //查詢課程(老師)

        Route::post('course/add/student', 'ReactController@api_insert_course_student');    //加入課程(學生)

        Route::post('bind/card', 'ReactController@api_card_bind');     //綁定卡片

        Route::post('query/userdata', 'ReactController@api_query_userdata');   //回傳所有使用者的資料及體溫(管理者)正確回傳甚麼呀?
        
        Route::post('course/delete/teacher', 'ReactController@api_delete_course');

        Route::post('push/course/message', 'ReactController@api_push_course_message');
        
        // master-----------------------------------------------------------------
        Route::post('master/read/temp', 'MasterController@api_master_read_temp');

        Route::post('master/read/abtemp', 'MasterController@api_master_read_abtemp');

        Route::post('master/read/course/member', 'MasterController@api_master_read_course_member');

        Route::post('master/push/message', 'MasterController@api_master_push_message');
    });

    //line--------------------------------------------------------------------------------
    Route::post('line/find/card', 'LineController@api_line_find_card'); //查詢卡片有無綁定
    //驗證token
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('line/insert/course', 'LineController@api_line_insert_course');    //加入課程

        Route::post('line/bind/card', 'LineController@api_line_card_bind');     //綁定卡片
        
        Route::post('line/read/temp', 'LineController@api_line_read_temp');     //查詢體溫
        
        Route::post('line/read/course', 'LineController@api_line_read_course'); //查詢課程

        Route::post('line/login', 'LineController@api_line_login'); //line bot 紀錄

        Route::post('line/notify/token', 'LineController@api_line_notify_token_bind'); //綁定notify token
    });

    //create token----------------------------------------------------------------------------------
    Route::post('line/create/token', 'TokenController@line_create_token');          //line

    Route::post('temp/create/token', 'TokenController@temp_create_token');          //machine
    
    //test create token
    Route::post('line/create/token/test', 'TokenController@line_create_token_test'); //testLine

    Route::post('temp/create/token/test', 'TokenController@temp_create_token_test'); //testMachine

    //--------------------------------------------------------------------------------

});


