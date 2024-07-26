<?php

namespace App\Http\Controllers;
use App\Mail\SendUserMail;
use App\Models\User;
use App\Models\UserDetail;
use Illuminate\Support\Facades\DB;

use App\Http\Controllers\API\BaseController;
use App\Http\Controllers\MailerController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Validator;

class AuthController extends BaseController
{
    /**
     * Register api
     *
     * @return \Illuminate\Http\Response
     */
    public function register(Request $request): JsonResponse
    {
        if ($request->method() !== 'POST') {
            return $this->sendError([] ,'Method not allowed', 405);
        }

        $validator = Validator::make($request->all(), [
            'firstname' => 'required',
            'lastname' => 'required',
            'middlename' => 'nullable',
            'email' => 'required|email',
            'date_of_birth' => 'date',
            'password' => 'required',
            'c_password' => 'required|same:password',
            'phone' => [
            'required',
            'regex:/^\+?[1-9]\d{1,14}$/'
        ],
        ]);
   
        if($validator->fails()){
            return $this->sendError('Validation Error.', $validator->errors(), 406);       
        }
   
        $input = $request->all();

        $input['password'] = bcrypt($input['password']);

        // Check if email exists
        $email = DB::table('users')->where('email', $request->email)->exists();
        if($email){
            return $this->sendError('Email already exists', [], 400);
        }


        $uniqueId = Str::random(8);
        $otpCode = rand(1000, 9999);
        $user = User::create([
            'name' => $input['firstname'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);
        $userDetail = UserDetail::create([
            'user_id' => $user->id,
            'phone' => $input['phone'],
            'middlename' => $input['middlename'] ?? null,
            'lastname' => $input['lastname'],
            'uniqueID' => $uniqueId,
            'code' => $otpCode,
            'codetime' => Now(),
            'date_of_birth' => date('Y-m-d', strtotime($input['date_of_birth'])),
        ]);
        // get the code from the user details table and send it to the user email
        $code = $userDetail->code;

        $toAddress = $user->email;
        $data = [
            'name' => $user->name,
            'code' => $code,
        ];

        Mail::to($toAddress)->send(new SendUserMail($toAddress, $data));
        $success =  [];
   
        return $this->sendResponse($success, 'User register successfully, Your one time pin have been sent to your mail.');
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'code' => 'required',
        ]);
   
        if($validator->fails()){
            return $this->sendError('Validation Error.', $validator->errors(), 406);       
        }

        $input = $request->all();
        $user = User::where('email', $input['email'])->first();
        if(!$user){
            return $this->sendError('User not found', 'This user does not exist', 404);
        }

        $userDetail = UserDetail::where('user_id', $user->id)->first();
        if(!$userDetail){
            return $this->sendError('User not found', [], 404);
        }

        $current_time = date('Y-m-d H:i:s');
        $codetime = $userDetail->codetime;

        $expiry_time = date('Y-m-d H:i:s', strtotime($codetime . ' +10 minutes'));


        if($current_time > $expiry_time){
            return $this->sendError('Code expired. Request for a new code', [], 400);
        }

        if($userDetail->code != $input['code']){
            return $this->sendError('Invalid code', [], 400);
        }

        $userDetail->isverified = 1;
        $userVerify = DB::table('users')
            ->where('id', $user->id)
            ->update(['email_verified_at' => Now()]);

        $verify = DB::table('user_table')
            ->where('user_id', $user->id)
            ->update(['isverified' => 1]);
        $success =  [];
   
        return $this->sendResponse($success, 'User verified successfully.');
    }

    public function resendOtp(Request $request): JsonResponse 
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);
   
        if($validator->fails()){
            return $this->sendError('Validation Error.', $validator->errors(), 406);       
        }

        $input = $request->all();
        $user = User::where('email', $input['email'])->first();
        if(!$user){
            return $this->sendError('User not found', [], 404);
        }

        $userDetail = UserDetail::where('user_id', $user->id)->first();
        if(!$userDetail){
            return $this->sendError('User not found', [], 404);
        }

        if($user->email_verified_at != null && $userDetail->isverified == 1){
            return $this->sendError('User already verified', [], 400);
        }

        $otpCode = rand(1000, 9999);
        $update_code = DB::table('user_table')
        ->where('user_id', $user->id)
        ->update(['code' => $otpCode, 'codetime' => Now()]);

        // get the code from the user details table and send it to the user email
        $code = DB::table('user_table')
            ->where('user_id', $user->id)
            ->value('code');

        $toAddress = $user->email;
        $data = [
            'name' => $user->name,
            'code' => $code,
        ];

        Mail::to($toAddress)->send(new SendUserMail($toAddress, $data));
        $success =  [];
   
        return $this->sendResponse($success, 'Code Resent Successfully.');
    }

    public function login(Request $request): JsonResponse 
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);


        
        if($validator->fails()){
            return $this->sendError('Validation Error.', $validator->errors(), 406);       
        }

        $user_mail = User::where('email', $request->email)->first();

        if (!$user_mail) {
            return $this->sendError('User not found', 'This user does not exist', 404);
        }

        // Check if the user is verified
        $user_verify = UserDetail::where('user_id', $user_mail->id)->value('isverified');

        if (!$user_verify) {
            return $this->sendError('User not verified', 'Please verify your account before logging in', 403);
        }

        $input = $request->all();
        if(auth()->attempt(array('email' => $input['email'], 'password' => $input['password']))){
            $user = auth()->user();
            $success =  ['token' => $user->createToken('kudi', ['expires_in' => 14400])->accessToken];
            return $this->sendResponse($success, 'User login successfully.');
        }
        else{
            return $this->sendError('Unauthorised', [], 401);
        }
    }
}
