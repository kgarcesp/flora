<?php

namespace App\Http\Controllers;

use App\Mail\NofiticationInvoiceMail;
use App\Log;
use App\Distribution;
use App\Application;
use App\Invoice;
use App\Flow;
use App\Supplier;
use App\Company;
use App\Approver;
use App\CostCenter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class LogController extends Controller
{
    public function store(Request $request)
    {
        $input = $request->all();

        $flow_id=DB::SELECT('SELECT flow_id AS flow_id FROM invoices WHERE id=?',[$input['invoice_id']]);

        

       $level_user_flow=DB::SELECT('SELECT invoice_approvers.order AS orden FROM invoice_approvers
                                     WHERE user_id = ? AND flow_id=? LIMIT 1',[Auth::id(),$flow_id[0]->flow_id]);
       // var_dump($level_user_flow[0]->orden);



        $next_user=DB::SELECT('SELECT next_user_id AS user_id
                               FROM invoice_logg
                               WHERE invoice_id=? AND 
                               id = (SELECT MAX(id) FROM invoice_logg WHERE invoice_id=?)',[$request->invoice_id,$request->invoice_id]);

        $level_next_user_flow=DB::SELECT('SELECT invoice_approvers.order AS orden FROM invoice_approvers
                                     WHERE user_id = ? AND flow_id=? LIMIT 1',[$input['approver_id'],$flow_id[0]->flow_id]);
      
       // intval($level_user_flow[0]->orden) <  intval($level_next_user_flow[0]->orden)) && ($input['action'] == "Aprobar")

      /*  if ((intval($level_user_flow[0]->orden)  <  intval($level_next_user_flow[0]->orden)) && ($input['action'] =="Rechazar")) {
       $id=$input['invoice_id'];

        
        $user = Auth::user();
        $id_user = $user->id;
        $application = new Application();
        $modules = $application->getModules($user->id,4);



        //$supplier_name=$request->supplier_name;
        $invoice = Invoice::find($id);
       // $prov = $supplier_name;
        
        $flow = $invoice->flow;


        $approver = Approver::where('user_id','=',$id_user)
                    ->where('flow_id','=',$flow->id)
                    ->first();



       // $init = $approver->order - 2;
       // $end = $approver->order + 3;
       
        $number_interventions=DB::SELECT('SELECT COUNT(next_user_id) AS cantidad
                                         FROM invoice_logg
                                         WHERE next_user_id=? AND
                                               invoice_id=?',[$id_user,$id]);

        $maxorder=DB::SELECT('SELECT max(a.order) AS orden 
                              FROM invoice_approvers a
                              WHERE a.flow_id=?',[$flow->id]);


        $typeapprover='';
        if ($number_interventions[0]->cantidad == 1) {
            $typeapprover=DB::SELECT('SELECT min(a.role_id) AS typeapprover,
                                      a.order AS orden
                                    FROM invoice_approvers a
                                    WHERE a.flow_id=? AND
                                          a.user_id=? AND
                                          a.active = ?
                                    GROUP BY a.role_id,a.order',[$flow->id,$id_user,1]);
        }else{
            $typeapprover=DB::SELECT('SELECT a.role_id AS typeapprover,
                                              a.order AS orden
                                            FROM invoice_approvers a
                                            WHERE a.flow_id=? AND
                                                a.user_id=? AND
                                                a.active = ? AND
                                                a.id=(SELECT MAX(id) FROM invoice_approvers WHERE user_id=? AND flow_id=?)
                                            GROUP BY a.role_id,a.order;',[$flow->id,$id_user,1,$id_user,$flow->id]);            
        }

        $diference=$maxorder[0]->orden - $typeapprover[0]->orden;


        if (Auth::id() != 129) {
        $approvers = Approver::where('user_id','<>',Auth::id())
                                ->where('flow_id','=',$flow->id)
                                ->where('active','=',1)
                                ->orderby('order','asc')->get();
        }else{
        $approvers = Approver::where('user_id','=',215)
                                ->where('flow_id','=',$flow->id)
                                ->where('active','=',1)
                                ->orderby('order','asc')->get();          
        }


        $costCenters = CostCenter::where('active','=',1)
                       ->orderby('name','asc')->get();


        return view('invoice.showerror',['modules' => $modules,'user' => $id_user,'invoice' => $invoice,'approvers' => $approvers,'costCenters' => $costCenters,'approver' => $approver,'typeapprover'=>$typeapprover,'diference'=>$diference]);
        
    
        }elseif ((intval($level_user_flow[0]->orden) >  intval($level_next_user_flow[0]->orden)) && ($input['action'] == "Aprobar")) {
       $id=$input['invoice_id'];


        
        $user = Auth::user();
        $id_user = $user->id;
        $application = new Application();
        $modules = $application->getModules($user->id,4);



        //$supplier_name=$request->supplier_name;
        $invoice = Invoice::find($id);
       // $prov = $supplier_name;
        
        $flow = $invoice->flow;


        $approver = Approver::where('user_id','=',$id_user)
                    ->where('flow_id','=',$flow->id)
                    ->first();



       // $init = $approver->order - 2;
       // $end = $approver->order + 3;
       
        $number_interventions=DB::SELECT('SELECT COUNT(next_user_id) AS cantidad
                                         FROM invoice_logg
                                         WHERE next_user_id=? AND
                                               invoice_id=?',[$id_user,$id]);

        $maxorder=DB::SELECT('SELECT max(a.order) AS orden 
                              FROM invoice_approvers a
                              WHERE a.flow_id=?',[$flow->id]);


        $typeapprover='';
        if ($number_interventions[0]->cantidad == 1) {
            $typeapprover=DB::SELECT('SELECT min(a.role_id) AS typeapprover,
                                      a.order AS orden
                                    FROM invoice_approvers a
                                    WHERE a.flow_id=? AND
                                          a.user_id=? AND
                                          a.active = ?
                                    GROUP BY a.role_id,a.order',[$flow->id,$id_user,1]);
        }else{
            $typeapprover=DB::SELECT('SELECT a.role_id AS typeapprover,
                                              a.order AS orden
                                            FROM invoice_approvers a
                                            WHERE a.flow_id=? AND
                                                a.user_id=? AND
                                                a.active = ? AND
                                                a.id=(SELECT MAX(id) FROM invoice_approvers WHERE user_id=? AND flow_id=?)
                                            GROUP BY a.role_id,a.order;',[$flow->id,$id_user,1,$id_user,$flow->id]);            
        }

        $diference=$maxorder[0]->orden - $typeapprover[0]->orden;


        if (Auth::id() != 129) {
        $approvers = Approver::where('user_id','<>',Auth::id())
                                ->where('flow_id','=',$flow->id)
                                ->where('active','=',1)
                                ->orderby('order','asc')->get();
        }else{
        $approvers = Approver::where('user_id','=',215)
                                ->where('flow_id','=',$flow->id)
                                ->where('active','=',1)
                                ->orderby('order','asc')->get();          
        }


        $costCenters = CostCenter::where('active','=',1)
                       ->orderby('name','asc')->get();


        return view('invoice.showerror',['modules' => $modules,'user' => $id_user,'invoice' => $invoice,'approvers' => $approvers,'costCenters' => $costCenters,'approver' => $approver,'typeapprover'=>$typeapprover,'diference'=>$diference]);
        
       }else{*/
        $user=Auth::id();
        $log = new Log();
        $log->invoice_id = $input['invoice_id'];
        if ($user) {
          $log->user_id = Auth::id();
        }else{
          $log->user_id = $next_user[0]->user_id;
        }
        if ($input['description'] != null) {
            $log->description = $input['description'];
        }else{
            $log->description = 'Factura en proceso...';
        }
        

        if($input['action'] =="Validar")
        {
            $log->state_id = 3;

        }
        else
        {
            if($input['action'] =="Aprobar")
            {
                $log->state_id = 4;

            }
            else
            {
                if($input['action'] =="Finalizar")
                {
                    $log->state_id = 6;

                }
                else
                {
                    $log->state_id = 5;
                }
            }
        }


        if($input['role_id'] == 1)
        {

            Distribution::where('invoice_id',$input['invoice_id'])
            ->where('active',1)
            ->update(['active' => 0]);
             
             $cantidad=intval($input['countfields']);
             for ($i=1; $i <=$cantidad ; $i++) { 
                        $distribution = new Distribution();
                        $distribution->invoice_id = $input['invoice_id'];
                        $distribution->cost_center_id = $input['coce'.$i];
                        $distribution->percentage = $input['percenta'.$i];
                        $distribution->value = str_replace('.','',$input['value'.$i]);
                        $distribution->active = 1;
                        $distribution->save();
             }
        }

        $file = $request->file('file');
        if(isset($file))
        {
            $ext = $file->getClientOriginalExtension();
            $nombre = $input['invoice_id']."_".Str::random(11).".".$ext;
            $log->file = $nombre;
            \Storage::disk('facturas')->put($nombre,  \File::get($file));
        }


        $log->next_user_id = $input['approver_id'];
        $log->save();
        if ($input['egreso'] != 'N/A') {
            $egreso= DB::UPDATE('UPDATE invoices
                                 SET egress=?
                                 WHERE id=?',[$input['egreso'],$input['invoice_id']]);
        }

        $invoice = Invoice::find($input['invoice_id']);

        $log = Log::where('invoice_id','=',$input['invoice_id'])
                    ->orderby('created_at','desc')
                    ->first();  

        $next_user_data=DB::SELECT('SELECT first_name AS name,
                                    email AS email
                                    FROM users
                                    WHERE id=?',[$input['approver_id']]);           
                    
        $user = $log->next_user;
           
        Mail::to($next_user_data[0]->email)->send(new NofiticationInvoiceMail($next_user_data[0]->name,$invoice,$input['approver_id']));


        return redirect()->route('invoices');
    
 }
}
