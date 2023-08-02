<?php

namespace App\Http\Controllers;

use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Mail\SendMail;
use Illuminate\Support\Facades\Mail;



class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $user = Auth::user();
        $modules = [];
        return view('user.show',['modules' => $modules,'user' => $user]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        
         
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $input = $request->all();


        if ( isset($input['notification']) ) {
            if ( $input['notification'] == 'on' ) {
                $input['notification']= 1;
            }else{
               $input['notification']= 0;
            }
        }else{
         $input['notification']= 0;   
        }

        $user = Auth::user();

        $user->email = $input['email'];
        $user->extension = $input['extension'];
        $user->phone = $input['phone'];
        $user->email_aux = $input['email_aux'];
        $user->notification = $input['notification'];

        $user->save();

        return view('welcome');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function directory()
    {
        
    }

    public function resetinside(){
        $modules = [];
        $error=0;
        return view('resetinside',['modules'=>$modules,'error'=>$error]);
    }

    public function updatepasswordinside(Request $request){
        $correo=$request->email;
        $modules = [];
        $user = Auth::user();

        $verification=DB::SELECT('SELECT id AS user_id,
                                         first_name AS name,
                                  count(id) AS cantidad
                                  FROM users
                                  WHERE email = ? OR
                                         email_aux = ?
                                  GROUP BY id,first_name',[$correo,$correo]);
        $cantidafinal=count($verification);
        if ($cantidafinal > 0) {
            $Type='updatepassword';
        $data=[$verification[0]->name,$Type,$verification[0]->user_id];
        $request->session()->put('User', $verification[0]->name);
        $request->session()->put('User_id', $verification[0]->user_id);
        Mail::to($correo)->send(new SendMail($data));
             $error = 0;
             return view('/updatepassword',['modules'=>$modules,'error'=>$error]);
        }else{
            $error = 1;
            return view('/updatepassword',['modules'=>$modules,'error'=>$error]);
        }

        /*$finalpassword = Hash::make($request->password);
        if ($password != $repassword) {
            $error = 1;
         return view('resetinside',['modules'=>$modules,'error'=>$error]);
        }else{
            $error = 3;
            $password_change= DB::UPDATE('UPDATE users
                                          SET password = ?
                                          WHERE id=?',[$finalpassword,$user->id]);
            return view('resetinside',['modules'=>$modules,'error'=>$error]);
        }*/


    }

public function cambiocontraseña(Request $request){
    $id=$request->id;
    $error=$request->error;

    return view('/cambiocontraseñafinal',['id'=>$id,'error'=>$error]);
 }

public function updatepasswordfinal(Request $request){
    $modules = [];
    $id=$request->id_usuario;
    $password=$request->password;
    $repassword=$request->password_confirmation;
    $finalpassword = Hash::make($request->password);
        if ($password != $repassword) {
            $error = 1;
             return view('/cambiocontraseñafinal',['modules'=>$modules,'error'=>$error,'id'=>$id]);
        }else{
            $error = 3;
            $password_change= DB::UPDATE('UPDATE users
                                          SET password = ?
                                          WHERE id=?',[$finalpassword,$id]);
            return view('/cambiocontraseñafinal',['modules'=>$modules,'error'=>$error,'id'=>$id]);
        }
 }


 public function salidalogin(){
    $modules = [];
    $user = Auth::user();
    $error = 0;
    return view('resetinside',['modules'=>$modules,'error'=>$error]);
 }

 public function actualizacionpassword(Request $request){
    $modules = [];
    $user = Auth::user();
    $password = $request->password;
    $repassword = $request->password_confirmation;

    $finalpassword = Hash::make($request->password);
        if ($password != $repassword) {
            $error = 1;
         return view('resetinside',['modules'=>$modules,'error'=>$error]);
        }else{
            $error = 3;
            $password_change= DB::UPDATE('UPDATE users
                                          SET password = ?
                                          WHERE id=?',[$finalpassword,$user->id]);
            return view('resetinside',['modules'=>$modules,'error'=>$error]);
        }
 }

}
