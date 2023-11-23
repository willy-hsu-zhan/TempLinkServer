<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;
use Illuminate\Support\Facades\Hash;
use App\User as UserEloquent;
use App\Machine as MachineEloquent;
use Validator;
use Log;
use Carbon\Carbon;
class TokenController extends Controller
{

    //新增line token
    public function line_create_token(Request $request){   
        $apiKeyDefault = "CSIE61226122";
        $data = $request->json();
        $apiKey = $data->get("apiKey");
        if ( $apiKey == $apiKeyDefault ) {
            $line = UserEloquent::where('server', 'LineServer')->first();
            $line->tokens()->delete();
            return $line->createToken('LineServerToken', ['server:line'])->plainTextToken;
        } else {
            return "error";
        }

    }

    //新增machine token
    public function temp_create_token(Request $request){
        $apiKeyDefault = "CSIE61226122";
        $data = $request->json();
        $apiKey = $data->get("apiKey");
        // $machine_id = $data->get("machineID");
        if ( $apiKey == $apiKeyDefault ) {
            $user = UserEloquent::where('server', 'temp')->first();
            $user->tokens()->delete();
            return $user->createToken('TempServerToken', ['server:temp'])->plainTextToken;
        } else {
            return "error";
        }
    }

    //test---------------------------------------------------------------------------------------

    //測試新增machine token
    public function temp_create_token_test(Request $request){   
            $user = UserEloquent::where('server', 'test')->first();
            $user->tokens()->delete();
            return $user->createToken('TestServerToken', ['server:temp'])->plainTextToken;
    }

    //測試新增line token
    public function line_create_token_test(Request $request){   
        $line = UserEloquent::where('server', 'test')->first();
        //$line->tokens()->delete();
        return $line->createToken('TestServerToken', ['server:line'])->plainTextToken;
    }
    
    //-------------------------------------------------------------------------------------------

}
