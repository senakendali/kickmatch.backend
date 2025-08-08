<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

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
}
