<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Models\ForgetPassword;
use Carbon;
use App\Mail\ForgotPasswordMail;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function countUsersWithRole()
    {
        $count = User::where('role_id', 3)->count();

        return response()->json([
            'total_users' => $count
        ], 200);
    }

    public function changePassword(Request $request){

        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:255',
            //'email' => 'required|string|email|max:255|unique:users,email',
            'password' => [
                'required',
                'string',
                'min:8',               // minimal 8 karakter
                'max:255',
                //'regex:/[a-z]/',       // harus ada huruf kecil
                'regex:/[A-Z]/',       // harus ada huruf besar
                'regex:/[0-9]/',       // harus ada angka
                //'regex:/[@$!%*?&#]/',  // harus ada simbol
            ],
            'confirmPassword' => 'required|same:password',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $data = $request->all();
            $data['email'] = $request->email;
            $data['new-password'] = $request->confirmPassword;

            // 🔸 Buat kontingen
            $tbl_user = User::where('email', '=', $data['email'])->first();
            $tbl_user->password = Hash::make($data['new-password']);
            
            if( $tbl_user->save() ){
                $result = array('status' => 1, 'message' => 'Password berhasil diupdate');
            }

            return response()->json(['data' => $result], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function forgotPassword(Request $request){
        $input = $request->all();
        $email = $input['email'];
        //echo 'email: '.$email; die();

        try {
            if( $email != '' ){
                
                if ( !filter_var($email, FILTER_VALIDATE_EMAIL) ) {
                    $emailErr = "Invalid email format";
                    $data = ["errors" => $emailErr];
                    return response()->json($data, 422);
                }

                // Send Email Funct 
                $date = Carbon\Carbon::now()->format('dmyhis');
                $weburl = $date.$this->randomChar(5);

                $dataEmail = [
                    "email" => $input['email'],
                    "weburl" => $weburl
                ];

                $result = $this->sendEmail($dataEmail);

                if ( $result){
                    $msg = "Halo ".@$input['email'].", Silahkan cek Email Anda untuk langkah selanjutnya. Terima kasih";
                    $data = ["status" => "0","message" => $msg];

                    $this->saveEmail($dataEmail);

                    return response()->json($data, 201);
                }else{
                    $emailErr = 'Gagal kirim email, silahkan dicoba kembali atau menggunakan email yang lain. Terima kasih';
                    $datax['message'] = $emailErr;
                    $data = ["status" => "1","message" => $emailErr];
                    return response()->json($data, 500);
                }

            }else{
                $emailErr = 'Silahkan input Email Anda';
                $datax['message'] = $emailErr;
                $data = ["status" => "1","msg" => 'error',"data" => $datax,"errors" => $emailErr];
                return response()->json($data, 500);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function randomChar($length){
    	$characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    	$charactersLength = strlen($characters);
        $result = '';
        for($i = 0; $i < $length; $i++) {
            $result .= $characters[rand(0, $charactersLength - 1)];
        }
        return $result;
    }

    function sendEmail($data){

        //var_dump($data);die();
        //echo $data['email'];die();
        //return false;
        return true;

        Mail::to($data['email'])->send(new ForgotPasswordMail($data));

        if (Mail::failures()) {
             return false;
        }else{
             return true;
        }
    }

    public function saveEmail($data){
        $save = new ForgetPassword;
        $save->email = @$data['email'];
        $save->url = @$data['weburl'];
        $save->save();
    }

    function resetPassword(Request $request){
        $dateNow = date('Y-m-d H:i:s');
        $input = $request->all();
        
        try {
            //validasi input 
            if ($input['password1'] != $input['password2']){
                $msgErr = 'New Password dan Confirm Password tidak sama, coba ulangi kembali';
                $data = ["status" => "1","message" => $msgErr];
                return response()->json($data, 500);
            }

            $cekEmail = ForgetPassword::where(['status' => 0, 'url' => $input['token']])->first();

            if (empty($cekEmail)) {
                $msgErr = 'Gagal Reset Password, Email Anda tidak ditemukan / Token Anda Expired';
                $data = ["status" => "1", "message" => $msgErr];
                return response()->json($data, 500);
            }

            $email = $cekEmail->email;

            #1 UPDATE TABLE USER
            $tblUser = User::where('email', '=', $email )->first();
            $tblUser->password = Hash::make($input['password2']);
            $tblUser->updated_at = $dateNow;
            //$tblUser->updated_by = $email; //Auth::user()->name;
            
            if( $tblUser->save() ){
                $tblFp = ForgetPassword::where(['email'=>$email,'url'=>$input['token']])->first();
                $tblFp->status = 1;
                $tblFp->updated_at = $dateNow;
                $tblFp->save();

                $msg = "Halo ".@$email.", Password Login Anda sudah direset, silahkan dicoba login kembali. Terima kasih";
                $data = ["status" => "0","message" => $msg];
                return response()->json($data, 201);
            }else{
                $msgErr = 'Gagal reset password, silahkan dicoba kembali';
                $data = ["status" => "1", "message" => $msgErr];
                return response()->json($data, 500);
            }
        }catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
        
    }
}
