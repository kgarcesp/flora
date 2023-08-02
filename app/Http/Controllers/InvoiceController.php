<?php

namespace App\Http\Controllers;

use App\Mail\NofiticationInvoiceMail;
use App\Invoice;
use App\Flow;
use App\Supplier;
use App\Log;
use App\Company;
use App\Approver;
use App\CostCenter;
use App\Distribution;
use App\Application;
use App\User;
Use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendMail;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use PDF;



class InvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {

        $input = $request->all();

        $user = Auth::user();
        $application = new Application();
       
        if ($user) {

        $modules = $application->getModules($user->id,4);


        $invoice = new Invoice();
        $invoices = $invoice->getActives(Auth::id());
        $countInvoices = count($invoices);


        return view('invoice.index',['modules' => $modules,'user' => $user,'invoices' => $invoices,'countInvoices' => $countInvoices]);
      }else{
         return view('welcome');
      }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {

        $user = Auth::user();
        $application = new Application();
        $modules = $application->getModules($user->id,4);

        $companies = Company::where('active','=',1)->get();
        
        $flows = DB::SELECT('SELECT f.id AS id,
                    f.name AS name
              FROM invoice_flows f
              INNER JOIN invoice_approvers a
              ON a.flow_id = f.id 
              WHERE f.active=? AND
                    a.user_id = ? 
            GROUP BY f.id', [
              1,
              $user->id,
            ]);

        $cuentas = DB::SELECT('SELECT id AS id, Cuenta AS cuenta FROM cuentas_cecos');

        $suppliers = Supplier::where('active','=',1)
              ->orderby('name','asc')
              ->get();

        $day = intval(date("j"));

        $typeerror=0;

        if ($day> 25) {
            $typeerror=1;
        }
        return view('invoice.create',[
          'modules' => $modules,
          'user' => $user,
          'companies' => $companies,
          'flows' => $flows,
          'suppliers' => $suppliers,
          'typeerror'=>$typeerror,
          'cuentas'=>$cuentas
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $input = $request->all();
        $supplier_info='';
        $typeerror='';

        if ($input['supplier_id'] == '') {
            $supplier_info = $input['id_supplier'];
        }
        else{
            $supplier_info = $input['supplier_id'];
        }

        $validate = DB::SELECT('SELECT COUNT(id) AS amount FROM 
              invoices 
              WHERE number = ? AND
              supplier_id = ?', [ 
                $input['number'], 
                $supplier_info,
        ]);

        if( ( $validate[0]->amount ) != 0 ){
                $user = Auth::user();
                $application = new Application();
                $modules = $application->getModules($user->profile->id,4);

                $companies = Company::where('active','=',1)->get();
                $flows = DB::SELECT('SELECT f.id AS id,
                                    f.name AS name
                             FROM invoice_flows f
                             INNER JOIN invoice_approvers a
                             ON a.flow_id = f.id 
                             WHERE f.active=? AND
                                   a.user_id = ? 
                            GROUP BY f.id',[1,$user->id]);

                $suppliers = Supplier::where('active','=',1)
                            ->orderby('name','asc')
                            ->get();

                $typeerror=2;

                $cuentas = DB::SELECT('SELECT id AS id, Cuenta AS cuenta FROM cuentas_cecos');

                return view('invoice.duplicate', [
                  'modules' => $modules,
                  'user' => $user,
                  'companies' => $companies,
                  'flows' => $flows,
                  'suppliers' => $suppliers,
                  'typeerror'=>$typeerror,
                  'cuentas' => $cuentas,
                ]);
        }
        else{
                $user = Auth::user();
                
                $file = $request->file('file');
                if ($request->hasFile('file')) 
                {
                    
                    $subtotal=str_replace('.','',$input['subtotal']);
                    $iva=str_replace('.','',$input['iva']);
                    $total=str_replace('.','',$input['total']);

                    $invoice = new Invoice();
                    $invoice->number = $input['number'];
                    $invoice->flow_id = $input['flow_id'];
                    $invoice->supplier_id = $supplier_info;
                    $invoice->create_date = $input['create_date'];
                    $invoice->due_date = $input['due_date'];
                    $invoice->company = $input['company_id'];
                    // $invoice->cuenta = $input['cuenta'];

                    $invoice->concept = $input['concept'];
                    $invoice->subtotal = str_replace(',','.',$subtotal);
                    $invoice->iva = str_replace(',','.',$iva);
                    $invoice->total = str_replace(',','.',$total);
                    $invoice->currency = $input['currency'];
                    $invoice->priority = $input['priority'];            
                    
                    $ext = $file->getClientOriginalExtension();
                    $nombre = $input['supplier_id']."_".$input['number']."_".Str::random(6).".".$ext;
                    $invoice->file = $nombre;
                    \Storage::disk('facturas')->put($nombre,  \File::get($file));


                    $invoice->save();

                    
                    $log = new Log();
                    $log->invoice_id = $invoice->id;
                    $log->user_id = Auth::id();
                    $log->state_id = $input['state_id'];
                    if ($input['description'] != null) {
                        $log->description = $input['description'];
                    }else{
                        $log->description = 'Factura en proceso...';
                    }

                    $approver = Approver::where('flow_id','=',$input['flow_id'])
                                        ->where('order','=',1)->first();

                    
                    // $log->next_user_id = $approver->user_id;
                    $log->next_user_id = $user->id;
                    $log->save();

                    return redirect()->route('invoices');
                }
                else{
                    $user = Auth::user();
                    $application = new Application();
                    $modules = $application->getModules($user->profile->id,4);

                    $companies = Company::where('active','=',1)->get();
                    $flows = Flow::where('active','=',1)->get();
                    $suppliers = Supplier::where('active','=',1)
                                ->orderby('name','asc')
                                ->get();
                    return view('invoice.error', [
                      'modules' => $modules,
                      'user' => $user,
                      'companies' => $companies,
                      'flows' => $flows,
                      'suppliers' => $suppliers
                    ]);            
                }

        }


    }


    /**
     * Display the specified resource.
     *
     * @param  \App\Invoice  $invoice
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        $id_user = $request->id_user;
        $user = Auth::user();
        $application = new Application();
        $modules = $application->getModules($id_user,4);

        $validador_contable_aprueba = config('app.global')['validador_contable_aprueba'];

        $id=$request->id;


        $supplier_name=$request->supplier_name;
        $invoice = Invoice::find($id);
        $prov = $supplier_name;
        
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

        $approvers_up = Approver::where('user_id','<>',Auth::id())
                                  ->where('flow_id','=',$flow->id)
                                  ->where('order','>',$typeapprover[0]->orden)
                                  ->where('active','=',1)
                                  ->orderby('order','asc')->get();

        $approvers_down = Approver::where('user_id','<>',Auth::id())
                                ->where('flow_id','=',$flow->id)
                                ->where('order','<=',$typeapprover[0]->orden)
                                ->where('active','=',1)
                                ->orderby('order','asc')->get();

        if (Auth::id() != 129) {
          $approvers = Approver::where('user_id','<>',Auth::id())
                                  ->where('flow_id','=',$flow->id)
                                  ->where('active','=',1)
                                  ->orderby('order','asc')->get();
          
          // Aca se estaban llenando: $approvers_up y $approvers_down
          

        }
        else{
          $approvers = Approver::where('user_id','=', $validador_contable_aprueba )
                                ->where('flow_id','=',$flow->id)
                                ->where('active','=',1)
                                ->orderby('order','asc')->get();          
        }


        $costCenters = CostCenter::where('active','=',1)
                       ->orderby('name','asc')->get();
               
        
       
        return view('invoice.show',[
          'modules' => $modules,
          'user' => $id_user,
          'invoice' => $invoice,
          'approvers' => $approvers,
          'costCenters' => $costCenters,
          'approver' => $approver,
          'typeapprover'=>$typeapprover,
          'diference'=>$diference,
          'approvers_up'=>$approvers_up,
          'approvers_down'=>$approvers_down
        ]);
    }

    public function log($id)
    {
        $user = Auth::user();
        $application = new Application();
        $modules = $application->getModules($user->id,4);

        $invoice = Invoice::find($id);
        
        $costCenters = CostCenter::where('active','=',1)
                       ->orderby('name','asc')->get();

        return view('invoice.log',['modules' => $modules,'user' => $user,'invoice' => $invoice,'costCenters' => $costCenters,]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Invoice  $invoice
     * @return \Illuminate\Http\Response
     */
    public function edit()
    {
        //
        echo "Ingreso aqui";
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Invoice  $invoice
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Invoice $invoice)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Invoice  $invoice
     * @return \Illuminate\Http\Response
     */
    public function destroy(Invoice $invoice)
    {
        //
    }

    public function listPendingInvoices()
    {
        $user = Auth::user();
        $application = new Application();
        $modules = $application->getModules($user->id,4);

        $invoice = new Invoice();
        $invoices = $invoice->getPendingInvoices();
        $countInvoices = count($invoices);

        return view('invoice.pending',['modules' => $modules,'user' => $user,'invoices' => $invoices,'countInvoices' => $countInvoices]);
    }

    public function Notify($id)
    {
        $invoice = Invoice::find($id);

        $log = Log::where('invoice_id','=',$id)
                    ->orderby('created_at','desc')
                    ->first();            
                    
        $user = $log->next_user;
           
        Mail::to($user->email)->send(new NofiticationInvoiceMail($user->name,$invoice,$user->id));

        return redirect()->route('invoice.pending');
    }


    public function resolutionscreate(){
        $user = Auth::user();
        $application = new Application();
        $modules = $application->getModules($user->id,4);

        $companies = Company::where('active','=',1)->get();
        $flows = Flow::where('active','=',1)->get();
        $suppliers = Supplier::where('active','=',1)
                    ->orderby('name','asc')
                    ->get();

        $error=0;
        $error_fecha=0;

        return view('invoice.resolutions',['modules' => $modules,'user' => $user,'companies' => $companies,'flows' => $flows,'suppliers' => $suppliers,'error'=>$error,'error_fecha'=>$error_fecha]);
    }

    public function resolutionsinit(){
        $user = Auth::user();
        $application = new Application();
        $modules = $application->getModules($user->id,4);

        $resolutions=DB::SELECT('SELECT * FROM resolutions');

        $cantidad_resolutions=count($resolutions);


        return view('invoice.resolutionsinit',['modules' => $modules,'user' => $user,'resolutions'=>$resolutions,'cantidad_resolutions'=>$cantidad_resolutions]);

    }


    public function resolutionsinactive(Request $request){
        $user = Auth::user();
        $application = new Application();
        $modules = $application->getModules($user->id,4);

        $id_resolution=$request->id_resolution;

        $inactivate=DB::UPDATE('UPDATE resolutions
                                SET active= 0
                                WHERE id=?',[$id_resolution]);

        $resolutions=DB::SELECT('SELECT * FROM resolutions');

        $cantidad_resolutions=count($resolutions);


        return view('invoice.resolutionsinit',['modules' => $modules,'user' => $user,'resolutions'=>$resolutions,'cantidad_resolutions'=>$cantidad_resolutions]);

    }


    public function resolutionsstore(Request $request){
        $user = Auth::user();
        $application = new Application();
        $modules = $application->getModules($user->id,4);
        $input = $request->all();
        $error_fecha=0;
        $cantidad= DB::SELECT("SELECT count(id) AS cantidad FROM resolutions
                               WHERE id_company=? AND active = ?",[$input['company_id'],1]);

        if ($request->begin_date > $request->end_date) {
          $error_fecha=1;
          $error = 0;
        $companies = Company::where('active','=',1)->get();
        $flows = Flow::where('active','=',1)->get();
        $suppliers = Supplier::where('active','=',1)
                    ->orderby('name','asc')
                    ->get();
        

        return view('invoice.resolutions',['modules' => $modules,'user' => $user,'companies' => $companies,'flows' => $flows,'suppliers' => $suppliers,'error'=>$error,'error_fecha'=>$error_fecha]);
        }elseif(($cantidad[0]->cantidad > 0)) {
        $companies = Company::where('active','=',1)->get();
        $flows = Flow::where('active','=',1)->get();
        $suppliers = Supplier::where('active','=',1)
                    ->orderby('name','asc')
                    ->get();
        $error_fecha=0;
        $error=1;

        return view('invoice.resolutions',['modules' => $modules,'user' => $user,'companies' => $companies,'flows' => $flows,'suppliers' => $suppliers,'error'=>$error,'error_fecha'=>$error_fecha]);
        }else{

        $insert= DB::INSERT('INSERT INTO resolutions (id_company, resolution_number, 
                                         begin_date,finish_date,int_number,end_number,active,prefijo)
                              VALUES (?,?,?,?,?,?,?,?)',[$input['company_id'],$input['number'],$request->begin_date,$request->end_date,$request->int_number,$request->end_number,1,$input['prefijo']]);
         return view('process',['modules' => $modules]);

        }
    }


    public function equivalente(Request $request){
        $user = Auth::user();
        $application = new Application();
        $modules = $application->getModules($user->id,5);
        $companies = Company::where('active','=',1)->get();
        $error=0;
        return view('invoice/equivalente',['modules' => $modules,'companies' => $companies,'error'=>$error]);
    }


    public function logequivalent(Request $request){
        $user = Auth::user();
        $application = new Application();
        $modules = $application->getModules($user->id,5);
        $companies = Company::where('active','=',1)->get();
        $input = $request->all();
        $cantidad=intval($request->countfields);
        $cantidadadjuntos=intval($request->countfieldsadd);
        $validacion_consecutivo='';
        $consecutivo=DB::SELECT('SELECT (l.id_consecutive+1) 
                                       AS consecutivo_actual
                                            FROM equivalent_log l
                                            WHERE l.id = (SELECT max(id) FROM equivalent_log WHERE company = ?)',[$input['compañia1']]);
        $cantidad_consecutivo=count($consecutivo);
        if ($cantidad_consecutivo == 0) {
          $validacion_consecutivo=0;
        }else{
          $validacion_consecutivo= intval($consecutivo[0]->consecutivo_actual);
        }
        $consecutivo_f=DB::SELECT('SELECT end_number AS consecutivo_final
                                  FROM resolutions
                                  WHERE id_company=?',[$input['compañia1']]);
        $calculo=intval($consecutivo_f[0]->consecutivo_final) - $validacion_consecutivo;
        $consecutivofinal=$validacion_consecutivo;
        $resolution_id= DB::SELECT('SELECT id AS id
                                    FROM resolutions
                                    WHERE active=1 AND
                                    id_company=?',[$input['compañia1']]);
        if (count($resolution_id) == 0) {
        $error=1;
        return view('invoice/equivalente',['modules' => $modules,'companies' => $companies,'error'=>$error]);
        }elseif($calculo < 0){
        $error=2;
        return view('invoice/equivalente',['modules' => $modules,'companies' => $companies,'error'=>$error]);          
        }else{
          $error=0;
          $maxid=DB::SELECT('SELECT max(id) AS maxid FROM equivalent_log WHERE company = ?',[$input['compañia1']]);

              if ($maxid[0]->maxid == NULL) {
                $consecutivofinal=1;
              }else{
                $consecutivo=DB::SELECT('SELECT (l.id_consecutive+1) 
                                           AS consecutivo_actual
                                            FROM equivalent_log l
                                            WHERE l.id = (SELECT max(id) FROM equivalent_log WHERE company = ?)',[$input['compañia1']]);
                $consecutivofinal=$consecutivo[0]->consecutivo_actual;
              }
        
                 $guardar=DB::INSERT('INSERT INTO equivalent_log (company,city,supplier,address,created_date,id_supplier,phone,id_consecutive,id_resolution) 
                  VALUES(?,?,?,?,?,?,?,?,?)',[$input['compañia1'],$input['ciudad'],$input['supplier_name'],$input['address'],$input['date_data'],$input['supplier_id'],$input['phone'],$consecutivofinal,$resolution_id[0]->id]);

                $maxidlog=DB::SELECT('SELECT max(id) AS id_equivalent FROM equivalent_log');


                 for ($i=1; $i <=$cantidad; $i++) {
                  $dato_valor=str_replace('.','',$input['valor'.$i]);
                  $dato_valor_final=str_replace(',','.',$dato_valor);
                 $guardar_flujo=DB::INSERT('INSERT INTO equivalent_flow (id_user,equivalent_state,next_user_id,equivalent_id,equivalent_description,equivalent_value,currency,created_at) 
                  VALUES(?,?,?,?,?,?,?,?)',[$user->id,1,$user->id,$maxidlog[0]->id_equivalent,$input['concept'.$i],$dato_valor_final,$input['currency'.$i],date('Y-m-d H:i:s')]);
                 }

                 $flow_users_insert=DB::INSERT("INSERT INTO  equivalent_users_flow (user_id,next_user_id,state,id_equivalent,equivalent_text,created_at) VALUES (?,?,?,?,?,?)",[$user->id,$user->id,1,$maxidlog[0]->id_equivalent,$request->descripton,date('Y-m-d')]);

                  $pendingDocuments=DB::SELECT('SELECT l.id AS id, 
                                                  CONCAT(r.prefijo,l.id_consecutive) AS 
                                                   numero_documento,
                                                   l.supplier AS proveedor,
                                                   l.id_supplier AS id_proveedor,
                                                   l.created_date AS fecha_documento,
                                                   SUM(f.equivalent_value) AS Total,
                                                   s.name AS estado,
                                            (SELECT MAX(id) FROM equivalent_users_flow ef WHERE ef.id_equivalent = l.id) LOG
                                            FROM equivalent_log l
                                            INNER JOIN equivalent_flow f 
                                            ON f.equivalent_id=l.id 
                                            INNER JOIN resolutions r
                                            ON r.id = l.id_resolution
                                            INNER JOIN equivalent_users_flow ef 
                                            ON ef.id_equivalent = l.id
                                            INNER JOIN equivalent_states s
                                            ON s.id=ef.state
                                            WHERE ef.next_user_id = ? AND
                                                  ef.id =(SELECT MAX(id) FROM equivalent_users_flow ef WHERE ef.id_equivalent = l.id) AND
                                                  ef.state <> 6
                                            GROUP BY ef.id_equivalent
                                            ORDER BY l.id DESC',[$user->id]);
                  $countDocuments=count($pendingDocuments);


                            $file = $request->file('file1');
                            if ($request->hasFile('file1')) 
                            {
                               for ($i=1; $i <=$cantidadadjuntos; $i++) {
                                $file = $request->file('file'.$i);
                                      $ext = $file->getClientOriginalExtension();
                                      $nombre = Str::random(6).".".$ext;
                                      \Storage::disk('equivalentes')->put($nombre,  \File::get($file));
                                  $guardado_datos=DB::INSERT("INSERT INTO attacheds(files,id_relation,name_module, created_at) VALUES (?,?,?,?)",[$nombre,$maxidlog[0]->id_equivalent,'equivalentes',date('Y-m-d')]);
                               }
                             }



                $companies = Company::where('active','=',1)->get();
                return view('invoice/equivalents',['modules' => $modules,'companies' => $companies,'countDocuments'=>$countDocuments,'pendingDocuments'=>$pendingDocuments]);


        }
    }

    public function equivalents(Request $request){
        $user = Auth::user();

        if ($user) {
        $application = new Application();
        $modules = $application->getModules($user->id,5);

          $pendingDocuments=DB::SELECT('SELECT l.id AS id,
                                           CONCAT(r.prefijo,l.id_consecutive) AS 
                                           numero_documento,
                                           l.supplier AS proveedor,
                                           l.id_supplier AS id_proveedor,
                                           l.created_date AS fecha_documento,
                                           SUM(f.equivalent_value) AS Total,
                                           s.name AS estado,
                                    (SELECT MAX(id) FROM equivalent_users_flow ef WHERE ef.id_equivalent = l.id) LOG
                                    FROM equivalent_log l
                                    INNER JOIN equivalent_flow f 
                                    ON f.equivalent_id=l.id 
                                    INNER JOIN resolutions r
                                    ON r.id = l.id_resolution
                                    INNER JOIN equivalent_users_flow ef 
                                    ON ef.id_equivalent = l.id
                                    INNER JOIN equivalent_states s
                                    ON s.id=ef.state
                                    WHERE ef.next_user_id = ? AND
                                          ef.id =(SELECT MAX(id) FROM equivalent_users_flow ef WHERE ef.id_equivalent = l.id) AND
                                          ef.state <> 6
                                    GROUP BY ef.id_equivalent
                                    ORDER BY l.id DESC',[$user->id]);

          $countDocuments=count($pendingDocuments);

        $companies = Company::where('active','=',1)->get();
        return view('invoice/equivalents',['modules' => $modules,'companies' => $companies,'countDocuments'=>$countDocuments,'pendingDocuments'=>$pendingDocuments]);
        }else{
        $user_id=$request->user_id;
        $application = new Application();
        $modules = $application->getModules($user_id,5);

          $pendingDocuments=DB::SELECT('SELECT l.id AS id,
                                           CONCAT(r.prefijo,l.id_consecutive) AS 
                                           numero_documento,
                                           l.supplier AS proveedor,
                                           l.id_supplier AS id_proveedor,
                                           l.created_date AS fecha_documento,
                                           SUM(f.equivalent_value) AS Total,
                                           s.name AS estado,
                                    (SELECT MAX(id) FROM equivalent_users_flow ef WHERE ef.id_equivalent = l.id) LOG
                                    FROM equivalent_log l
                                    INNER JOIN equivalent_flow f 
                                    ON f.equivalent_id=l.id 
                                    INNER JOIN resolutions r
                                    ON r.id = l.id_resolution
                                    INNER JOIN equivalent_users_flow ef 
                                    ON ef.id_equivalent = l.id
                                    INNER JOIN equivalent_states s
                                    ON s.id=ef.state
                                    WHERE ef.next_user_id = ? AND
                                          ef.id =(SELECT MAX(id) FROM equivalent_users_flow ef WHERE ef.id_equivalent = l.id) AND
                                          ef.state <> 6
                                    GROUP BY ef.id_equivalent
                                    ORDER BY l.id DESC',[$user_id]);

          $countDocuments=count($pendingDocuments);

        $companies = Company::where('active','=',1)->get();
        return view('invoice/equivalents',['modules' => $modules,'companies' => $companies,'countDocuments'=>$countDocuments,'pendingDocuments'=>$pendingDocuments]);




        }
    }



    public function imprimir(Request $request){
        $user = Auth::user();

        if ($user) {
        $application = new Application();
        $modules = $application->getModules($user->id,5);
        $input = $request->all();

        $company =DB::SELECT('SELECT a.company AS company,
                                     a.city AS city,
                                     a.supplier AS supplier,
                                     a.address AS address,
                                     a.created_date AS date,
                                     a.id_supplier AS id_supplier,
                                     a.phone AS phone,
                                     a.id_consecutive AS consecutivo,
                                     b.resolution_number AS resolution,
                                     b.int_number AS inicio,
                                     b.end_number AS final,
                                     b.finish_date AS finish_date,
                                     b.prefijo AS prefijo,
                                     TIMESTAMPDIFF(MONTH, b.begin_date, b.finish_date) AS meses
                              FROM 
                              equivalent_log a
                              INNER JOIN resolutions b
                              ON b.id=a.id_resolution
                              WHERE a.id=?',[$input['document_id']]);
        $prefix=$company[0]->prefijo;
        $consecutivo=$company[0]->consecutivo;

        $information = DB::SELECT('SELECT equivalent_description AS description,
                                          equivalent_value AS value,
                                          currency AS currency
                                  FROM equivalent_flow
                                  WHERE equivalent_id = ?',[$input['document_id']]);
        $Total = DB::SELECT('SELECT SUM(equivalent_value) AS Total
                             FROM equivalent_flow
                             WHERE equivalent_id=?
                             GROUP BY equivalent_id',[$input['document_id']]);
        $Total_final= $Total[0]->Total;
       // $information=DB::SELECT('SELECT ')
        $company_final=$company[0]->company;
        $nombre_usuario=$user->first_name.' '.$user->last_name;

        $data=compact('company_final','company','information','Total_final','prefix','consecutivo','nombre_usuario');
        $pdf = PDF::loadView('pdf.equivalentepdf', $data);
        return $pdf->stream();
        }else{
        $user=$request->user_id;
        $application = new Application();
        $modules = DB::select('SELECT module_id AS module_id,
                                       module_name AS module_name,
                                       function_id AS function_id,
                                       function_name AS function_name,
                                       route AS function_route
                                FROM permission
                                WHERE id_user=? AND aplication_id=? AND active = ?
                                ORDER BY module_id ASC',[$user,5,1]);
        $input = $request->all();

        $next_user_name= DB::SELECT('SELECT u.name AS name
                                     FROM users u
                                     INNER JOIN equivalent_flow f
                                     ON f.id_user = u.id
                                     WHERE f.id=(SELECT min(f.id) FROM equivalent_flow f WHERE f.equivalent_id = ?)',[$input['document_id']]);

        $company =DB::SELECT('SELECT a.company AS company,
                                     a.city AS city,
                                     a.supplier AS supplier,
                                     a.address AS address,
                                     a.created_date AS date,
                                     a.id_supplier AS id_supplier,
                                     a.phone AS phone,
                                     a.id_consecutive AS consecutivo,
                                     b.resolution_number AS resolution,
                                     b.int_number AS inicio,
                                     b.end_number AS final,
                                     b.finish_date AS finish_date,
                                     b.prefijo AS prefijo,
                                     TIMESTAMPDIFF(MONTH, b.begin_date, b.finish_date) AS meses
                              FROM 
                              equivalent_log a
                              INNER JOIN resolutions b
                              ON b.id=a.id_resolution
                              WHERE a.id=?',[$input['document_id']]);
        $prefix=$company[0]->prefijo;
        $consecutivo=$company[0]->consecutivo;

        $information = DB::SELECT('SELECT equivalent_description AS description,
                                          equivalent_value AS value,
                                          currency AS currency
                                  FROM equivalent_flow
                                  WHERE equivalent_id = ?',[$input['document_id']]);
        $Total = DB::SELECT('SELECT SUM(equivalent_value) AS Total
                             FROM equivalent_flow
                             WHERE equivalent_id=?
                             GROUP BY equivalent_id',[$input['document_id']]);
        $Total_final= $Total[0]->Total;
       // $information=DB::SELECT('SELECT ')
        $company_final=$company[0]->company;
        $nombre_usuario=$next_user_name[0]->name;
        $data=compact('company_final','company','information','Total_final','prefix','consecutivo','nombre_usuario');
        $pdf = PDF::loadView('pdf.equivalentepdf', $data);
        return $pdf->stream();


        }

    }


  public function adjuntosfilesequivalentes(Request $request){
        $adjuntosfiles= DB::SELECT("SELECT DATE_FORMAT(i.created_at, '%Y-%m-%d') AS date,
                           CASE
                           WHEN i.files IS NOT NULL THEN i.files
                           ELSE ''  
                           END AS file
                FROM attacheds i
                WHERE i.id_relation = ? AND
                      i.files IS NOT NULL",[$request->id]);

        echo json_encode($adjuntosfiles);



    }


  public function gestionequivalents(Request $request){
    $user = Auth::user();
    if ($user) {
    $application = new Application();
    $modules = $application->getModules($user->id,5);
    $id_documento= $request->id_documento;

    $datos= DB::SELECT('SELECT CONCAT(r.prefijo,l.id_consecutive) AS documento,
                               SUM(f.equivalent_value) AS total,
                               l.created_date AS date,
                               l.id AS id,
                               f.currency AS currency
                        FROM resolutions r
                        INNER JOIN equivalent_log l
                        ON l.id_resolution = r.id
                        INNER JOIN equivalent_flow f
                        ON f.equivalent_id = l.id
                        WHERE l.id= ?',[$id_documento]);

    $users_flow =DB::SELECT('SELECT u.name AS name,
                                    f.created_at AS date,
                                    f.equivalent_text AS description,
                                    CASE 
                                        WHEN f.state = 1 THEN "Radicada"
                                        WHEN f.state = 2 THEN "Cancelada"
                                        WHEN f.state = 3 THEN "Validada"
                                        WHEN f.state = 4 THEN "Aprobada"
                                        WHEN f.state = 5 THEN "Rechazada"
                                        WHEN f.state = 6 THEN "Finalizada"
                                    END AS estado,
                                      CASE 
                                        WHEN f.equivalent_text IS NOT NULL THEN f.equivalent_text
                                        ELSE ""
                                    END AS description
                                    FROM users u
                                    INNER JOIN equivalent_users_flow f
                                    ON u.id= f.user_id
                                    INNER JOIN equivalent_flow l
                                    ON l.equivalent_id= f.id_equivalent
                              WHERE l.id= (SELECT MAX(l.id) FROM equivalent_flow l WHERE l.equivalent_id = ?)',[$id_documento]);
    $approvers=DB::SELECT('SELECT u.name AS name,
                                  u.id AS user_id
                            FROM users u
                            INNER JOIN equivalent_approvers a
                            ON a.user_id = u.id
                            WHERE a.user_id <> ?',[$user->id]);
    $user_rol=DB::SELECT('SELECT rol_id AS rol
                          FROM equivalent_approvers
                          WHERE user_id = ?',[$user->id]);
    $cantidad_flow=count($users_flow);

    return view('invoice/gestionequivalents',['modules' => $modules,'datos'=>$datos,'flow'=>$users_flow,'cantidad_flow'=>$cantidad_flow,'approvers'=>$approvers,'user_rol'=>$user_rol[0]->rol]);

    }else{
    $user=$request->user_id;
    $application = new Application();
    $modules = DB::select('SELECT module_id AS module_id,
                                       module_name AS module_name,
                                       function_id AS function_id,
                                       function_name AS function_name,
                                       route AS function_route
                                FROM permission
                                WHERE id_user=? AND aplication_id=? AND active = ?
                                ORDER BY module_id ASC',[$user,5,1]);
    $id_documento= $request->id_documento;
    $datos= DB::SELECT('SELECT CONCAT(r.prefijo,l.id_consecutive) AS documento,
                               SUM(f.equivalent_value) AS total,
                               l.created_date AS date,
                               l.id AS id,
                               f.currency AS currency
                        FROM resolutions r
                        INNER JOIN equivalent_log l
                        ON l.id_resolution = r.id
                        INNER JOIN equivalent_flow f
                        ON f.equivalent_id = l.id
                        WHERE l.id= ?',[$id_documento]);

    $users_flow =DB::SELECT('SELECT u.name AS name,
                                    f.created_at AS date,
                                    f.equivalent_text AS description,
                                    CASE 
                                        WHEN f.state = 1 THEN "Radicada"
                                        WHEN f.state = 2 THEN "Cancelada"
                                        WHEN f.state = 3 THEN "Validada"
                                        WHEN f.state = 4 THEN "Aprobada"
                                        WHEN f.state = 5 THEN "Rechazada"
                                        WHEN f.state = 6 THEN "Finalizada"
                                    END AS estado,
                                      CASE 
                                        WHEN f.equivalent_text IS NOT NULL THEN f.equivalent_text
                                        ELSE ""
                                    END AS description
                                    FROM users u
                                    INNER JOIN equivalent_users_flow f
                                    ON u.id= f.user_id
                                    INNER JOIN equivalent_flow l
                                    ON l.equivalent_id= f.id_equivalent
                              WHERE l.id= (SELECT MAX(l.id) FROM equivalent_flow l WHERE l.equivalent_id = ?)',[$id_documento]);
    $approvers=DB::SELECT('SELECT u.name AS name,
                                  u.id AS user_id
                            FROM users u
                            INNER JOIN equivalent_approvers a
                            ON a.user_id = u.id
                            WHERE a.user_id <> ?',[$user]);
    $user_rol=DB::SELECT('SELECT rol_id AS rol
                          FROM equivalent_approvers
                          WHERE user_id = ?',[$user]);
    $cantidad_flow=count($users_flow);

    return view('invoice/gestionequivalents',['modules' => $modules,'datos'=>$datos,'flow'=>$users_flow,'cantidad_flow'=>$cantidad_flow,'approvers'=>$approvers,'user_rol'=>2]);



    }


  }



  public function loggestionequivalents(Request $request){
    $user = Auth::user();
    $application = new Application();
    $modules = $application->getModules($user->id,5);
    $estado_final=0;
    if ($request->action == 'Rechazar') {
      $estado_final=5;
    }elseif($request->action == 'Validar'){
      $estado_final=3;
    }elseif ($request->action == 'Aprobar') {
      $estado_final=4;
    }else{
      $estado_final =6;
    }

    $insertdata= DB::INSERT('INSERT INTO equivalent_users_flow (user_id,next_user_id,state,id_equivalent,equivalent_text,created_at) VALUES (?,?,?,?,?,?)',[$user->id,$request->approver_id,$estado_final,$request->id_documento,$request->description,date('Y-m-d')]);


            $pendingDocuments=DB::SELECT('SELECT l.id AS id,
                                           CONCAT(r.prefijo,l.id_consecutive) AS 
                                           numero_documento,
                                           l.supplier AS proveedor,
                                           l.id_supplier AS id_proveedor,
                                           l.created_date AS fecha_documento,
                                           SUM(f.equivalent_value) AS Total,
                                           s.name AS estado,
                                    (SELECT MAX(id) FROM equivalent_users_flow ef WHERE ef.id_equivalent = l.id) LOG
                                    FROM equivalent_log l
                                    INNER JOIN equivalent_flow f 
                                    ON f.equivalent_id=l.id 
                                    INNER JOIN resolutions r
                                    ON r.id = l.id_resolution
                                    INNER JOIN equivalent_users_flow ef 
                                    ON ef.id_equivalent = l.id
                                    INNER JOIN equivalent_states s
                                    ON s.id=ef.state
                                    WHERE ef.next_user_id = ? AND
                                          ef.id =(SELECT MAX(id) FROM equivalent_users_flow ef WHERE ef.id_equivalent = l.id) AND
                                          ef.state <> 6
                                    GROUP BY ef.id_equivalent
                                    ORDER BY r.prefijo DESC',[$user->id]);
          $countDocuments=count($pendingDocuments);

          $name_user=DB::SELECT('SELECT first_name AS name,
                                        email AS email
                                 FROM users 
                                 WHERE id=?',[$request->approver_id]);

       if($estado_final != 3) {
        $user_creator= $user->name;
        $assignmentuser = $request->approver_id;
        $user_name = $name_user[0]->name;
        $Type = 'Factura equivalente';
        $MailSend= $name_user[0]->email;

        $request->session()->put('assignmentuser', $user_name);
        
        $data=[$assignmentuser,$Type,$user_creator,$user_name];
        
        if ($MailSend != NULL) {
          Mail::to($MailSend)->send(new SendMail($data));
        }


       }

        $companies = Company::where('active','=',1)->get();
        return view('invoice/equivalents',['modules' => $modules,'companies' => $companies,'countDocuments'=>$countDocuments,'pendingDocuments'=>$pendingDocuments]);

  }



  public function aprobacion_masiva(Request $request){

        $input = $request->all();
        $user = Auth::user();
        $application = new Application();
        $modules = $application->getModules($user->id,4);

        $invoices_ids=DB::SELECT('SELECT i.id AS id FROM invoices i
                                  INNER JOIN invoice_logg l
                                  ON i.id=(SELECT MAX(l.invoice_id) FROM invoice_logg l WHERE l.invoice_id = i.id)
                                  WHERE i.egress IS NOT NULL
                                  AND l.state_id= 4
                                  GROUP BY i.id');
        $invoices_id_final=json_decode( json_encode($invoices_ids), true);

        $cantidad_facturas=count($invoices_id_final);
        $i=0;
        for ($i=0; $i <$cantidad_facturas ; $i++) { 
         $insert_invoices=DB::INSERT('INSERT INTO invoice_logg (invoice_id, user_id, state_id, description,next_user_id)
           VALUES (?, ?, ?, ?,?)',[$invoices_id_final[$i]['id'],$user->id,6,'Aprobada',$user->id]);
        }
        $invoice = new Invoice();
        $invoices = $invoice->getActives(Auth::id());
        $countInvoices = count($invoices);


        return view('invoice.index',['modules' => $modules,'user' => $user,'invoices' => $invoices,'countInvoices' => $countInvoices]);
  }


  public function adjuntosfilesanticipos(Request $request){
    $function_name=DB::SELECT('SELECT name AS name FROM functions WHERE id=?',[24]);
    $adjuntosfiles= DB::SELECT("SELECT DATE_FORMAT(i.created_at, '%Y-%m-%d') AS date,
                        CASE
                        WHEN i.files IS NOT NULL THEN i.files
                        ELSE ''  
                        END AS file
            FROM attacheds i
            WHERE i.id_relation = ? AND
                    i.name_module=? AND
                  i.files IS NOT NULL",[$request->id,$function_name[0]->name]);

    echo json_encode($adjuntosfiles);
  }


  /*
  * Funcion que retorna el Log de Anticipo / Legalizacion
  * @param id: (int) numero de anticipo
  * @param type: (string) tipo que indica si es "Anticipo" o "Legalizacion"
  * @return json: (Object) Logs del registro
  */
  public function anticiposLog ( Request $request ){
    $id = $request->id;
    $type = $request->type;
    
    $anticiposLogs = DB::SELECT("SELECT 
        DATE_FORMAT(al.created_at, '%Y-%m-%d') AS date_,
        al.user_id,
        u1.name as init_user,
        al.next_user_id,
        u2.name as next_user
      FROM anticipos_log al
      JOIN users u1 ON u1.id = al.user_id
      JOIN users u2 ON u2.id = al.next_user_id
      WHERE al.id_document = ? AND
      al.type_document = ?   
      ORDER BY al.id DESC", [ $id, $type ]);

    echo json_encode([ 'data' => $anticiposLogs, 'success' => true ]);
  }



  public function adjuntosfileslegalizaciones(Request $request){
        $function_name=DB::SELECT('SELECT name AS name FROM functions WHERE id=?',[27]);
        $adjuntosfiles= DB::SELECT("SELECT DATE_FORMAT(i.created_at, '%Y-%m-%d') AS date,
                           CASE
                           WHEN i.files IS NOT NULL THEN i.files
                           ELSE ''  
                           END AS file
                FROM attacheds i
                WHERE i.id_relation = ? AND
                       i.name_module=? AND
                      i.files IS NOT NULL",[$request->id,$function_name[0]->name]);

        echo json_encode($adjuntosfiles);



    }


  public function anticipos(Request $request){

    $user = Auth::user();
    $application = new Application();
    $modules = $application->getModules($user->id,4);

    $suppliers = Supplier::where('active','=',1)
                    ->orderby('name','asc')
                    ->get(); 
 

   $directores=DB::SELECT("SELECT id AS id,
                                  name AS name,
                                  profile_name AS profile
                               FROM users
                               WHERE ((SUBSTRING(LTRIM(RTRIM(profile_name)),1,8)=? 
                               OR    SUBSTRING(LTRIM(RTRIM(profile_name)),1,9)=?) 
                               OR (id) = ?
                               OR (id) = ?
                               OR (id) = ?
                               OR (id) = ?)
                               AND active = ?",['DIRECTOR','DIRECTORA',6,239,226,275,1]);


    return view('anticipos/anticipos',['modules' => $modules,'user' => $user, 'suppliers'=>$suppliers,'directores'=>$directores]);
    

  }


  public function save(Request $request){

    $user = Auth::user();
    $application = new Application();
    $modules = $application->getModules($user->id,4);


   if($request->file('file1') == NULL){
             if ($request->supplier_id) {

                $save=DB::INSERT('INSERT INTO anticipos (id_user,empresa,fecha_pago,valor_anticipo,forma_pago,concepto,proveedor,estado) VALUES (?,?,?,?,?,?,?,?)',[$user->id,$request->empresa,$request->fecha_anticipo,$request->valor_anticipo,$request->forma_pago,$request->concepto_anticipo,$request->supplier_id,0]);
              }else{
                    $save=DB::INSERT('INSERT INTO anticipos (id_user,empresa,fecha_pago,valor_anticipo,forma_pago,concepto,proveedor,estado) VALUES (?,?,?,?,?,?,?,?)',[$user->id,$request->empresa,$request->fecha_anticipo,$request->valor_anticipo,$request->forma_pago,$request->concepto_anticipo,'',0]);
             }
             $ultimo_registro=DB::SELECT('SELECT max(id) AS id FROM anticipos');
             $leader_id=$request->id_director;
             $leader_name= DB::SELECT('SELECT first_name AS name, email AS email FROM users WHERE id=?',[$leader_id]);
             $function_name=DB::SELECT('SELECT name AS name FROM functions WHERE id=?',[24]);
             $guardado_datos=DB::INSERT("INSERT INTO attacheds(files,id_user,next_user_id,id_relation,id_function,name_module, created_at) VALUES (?,?,?,?,?,?,?)",['',$user->id,$leader_id,$ultimo_registro[0]->id,24,$function_name[0]->name,date('Y-m-d')]);
             $guardado_datos_log=DB::INSERT("INSERT INTO anticipos_log(user_id,next_user_id,id_document,type_document, created_at) VALUES (?,?,?,?,?)",[$user->id,$leader_id,$ultimo_registro[0]->id,$function_name[0]->name,date('Y-m-d')]);

             $anticipos = DB::SELECT('SELECT
                                           a.id AS id, 
                                           a.fecha_pago AS fecha_pago,
                                           a.valor_anticipo AS valor_anticipo,
                                           a.forma_pago AS forma_pago,
                                           a.concepto AS concepto,
                                             us.name AS gestionando,
                                      CASE
                                      WHEN a.estado = 0 THEN "En proceso..."
                                      WHEN a.estado = 1 THEN "Aprobado"
                                      WHEN a.estado = 2 THEN "Pagado"
                                      WHEN a.estado = 3 THEN "Rechazado" 
                                      WHEN a.estado = 4 THEN "Proceso legalización"
                                      WHEN a.estado = 5 THEN "Legalización aprobada"
                                      WHEN a.estado = 6 THEN "Legalización cerrada"
                                      WHEN a.estado = 7 THEN "Legalización finalizada"
                                      WHEN a.estado = 8 THEN "Legalización rechazada"       
                                      END AS estado,
                                      ad.files AS files
                                      FROM anticipos a
                                      INNER JOIN attacheds ad
                                      ON ad.id_user = ?
                                      INNER JOIN users us
                                      ON ad.next_user_id=us.id 
                                      WHERE ad.name_module= ? AND
                                            ad.id =(SELECT MAX(id) FROM attacheds ad WHERE ad.id_relation = a.id)
                                            GROUP BY a.id',[$user->id,$function_name[0]->name]);

                 $count= DB::SELECT('SELECT count(id) AS count FROM anticipos
                                  WHERE id_user=?',[$user->id]);

                 $data_anticipo=DB::SELECT('SELECT a.empresa AS empresa,
                                                   a.fecha_pago AS fecha_pago,
                                                   a.valor_anticipo AS valor_anticipo,
                                                   a.forma_pago AS forma_pago,
                                                   a.concepto AS concepto,
                                                   u.name AS name
                                                   FROM anticipos a
                                                   INNER JOIN users u
                                                   ON u.id=a.id_user
                                                   WHERE a.id=?',[$ultimo_registro[0]->id]);


                  $assignmentuser = $leader_name[0]->name;
                  $Type = 'anticipo';
                  $MailSend= $leader_name[0]->email;

                  $request->session()->put('assignmentuser', $leader_name[0]->name);
                  
                  $data=[$assignmentuser,$Type,$ultimo_registro[0]->id,$leader_name[0]->name,$data_anticipo[0]->empresa,$data_anticipo[0]->fecha_pago,$data_anticipo[0]->valor_anticipo,$data_anticipo[0]->forma_pago,$data_anticipo[0]->concepto,$data_anticipo[0]->name,$leader_id,$leader_id];

                  if ($MailSend != NULL) {
                    Mail::to($MailSend)->send(new SendMail($data));
                  }

                  header("Location: https://flora.tierragro.com/anticipos/historial",true,303);  
                  exit();  
              //   https://flora.tierragro.com/anticipos/historial
              //   return view('anticipos/historialnew',['modules' => $modules,'user' => $user,'anticipos'=>$anticipos,'count'=>$count]);
             //    die();
   }else{

                 $cantidadadjuntos=intval($request->countfieldsadd);
                 $leader_id=$request->id_director;
                 $leader_name= DB::SELECT('SELECT first_name AS name, email AS email FROM users WHERE id=?',[$leader_id]);
                if ($request->hasFile('file1'))
                {   
                     if ($request->supplier_id) {
                     $save=DB::INSERT('INSERT INTO anticipos (id_user,empresa,fecha_pago,valor_anticipo,forma_pago,concepto,proveedor,estado) VALUES (?,?,?,?,?,?,?,?)',[$user->id,$request->empresa,$request->fecha_anticipo,$request->valor_anticipo,$request->forma_pago,$request->concepto_anticipo,$request->supplier_id,0]);
                   }else{
                     $save=DB::INSERT('INSERT INTO anticipos (id_user,empresa,fecha_pago,valor_anticipo,forma_pago,concepto,proveedor,estado) VALUES (?,?,?,?,?,?,?,?)',[$user->id,$request->empresa,$request->fecha_anticipo,$request->valor_anticipo,$request->forma_pago,$request->concepto_anticipo,'',0]);
                   }
                     $ultimo_registro=DB::SELECT('SELECT max(id) AS id FROM anticipos');
                     $leader_id=$request->id_director;
                     $leader_name= DB::SELECT('SELECT first_name AS name, email AS email FROM users WHERE id=?',[$leader_id]);
                     $function_name=DB::SELECT('SELECT name AS name FROM functions WHERE id=?',[24]);
                     for ($i=1; $i <=$cantidadadjuntos; $i++) {
                      $file = $request->file('file'.$i);
                            $ext = $file->getClientOriginalExtension();
                            $nombre = Str::random(6).".".$ext;
                            \Storage::disk('facturas')->put($nombre,  \File::get($file));
                        $guardado_datos=DB::INSERT("INSERT INTO attacheds(files,id_user,next_user_id,id_relation,id_function,name_module, created_at) VALUES (?,?,?,?,?,?,?)",[$nombre,$user->id,$leader_id,$ultimo_registro[0]->id,24,$function_name[0]->name,date('Y-m-d')]);
                        $guardado_datos_log=DB::INSERT("INSERT INTO anticipos_log(user_id,next_user_id,id_document,type_document, created_at) VALUES (?,?,?,?,?)",[$user->id,$leader_id,$ultimo_registro[0]->id,$function_name[0]->name,date('Y-m-d')]);
                     }
                     

                     $anticipos = DB::SELECT('SELECT
                                                   a.id AS id, 
                                                   a.fecha_pago AS fecha_pago,
                                                   a.valor_anticipo AS valor_anticipo,
                                                   a.forma_pago AS forma_pago,
                                                   a.concepto AS concepto,
                                                     us.name AS gestionando,
                                              CASE
                                              WHEN a.estado = 0 THEN "En proceso..."
                                              WHEN a.estado = 1 THEN "Aprobado"
                                              WHEN a.estado = 2 THEN "Pagado"
                                              WHEN a.estado = 3 THEN "Rechazado" 
                                              WHEN a.estado = 4 THEN "Proceso legalización"
                                              WHEN a.estado = 5 THEN "Legalización aprobada"
                                              WHEN a.estado = 6 THEN "Legalización cerrada"
                                              WHEN a.estado = 7 THEN "Legalización finalizada"
                                              WHEN a.estado = 8 THEN "Legalización rechazada"       
                                              END AS estado,
                                              ad.files AS files
                                              FROM anticipos a
                                              INNER JOIN attacheds ad
                                              ON ad.id_user = ?
                                              INNER JOIN users us
                                              ON ad.next_user_id=us.id 
                                              WHERE ad.name_module= ? AND
                                                    ad.id =(SELECT MAX(id) FROM attacheds ad WHERE ad.id_relation = a.id)
                                                    GROUP BY a.id',[$user->id,$function_name[0]->name]);

                       $count= DB::SELECT('SELECT count(id) AS count FROM anticipos
                                        WHERE id_user=?',[$user->id]);

                       $data_anticipo=DB::SELECT('SELECT a.empresa AS empresa,
                                                         a.fecha_pago AS fecha_pago,
                                                         a.valor_anticipo AS valor_anticipo,
                                                         a.forma_pago AS forma_pago,
                                                         a.concepto AS concepto,
                                                         u.name AS name
                                                         FROM anticipos a
                                                         INNER JOIN users u
                                                         ON u.id=a.id_user
                                                         WHERE a.id=?',[$ultimo_registro[0]->id]);


                        $assignmentuser = $leader_name[0]->name;
                        $Type = 'anticipo';
                        $MailSend= $leader_name[0]->email;

                        $request->session()->put('assignmentuser', $leader_name[0]->name);
                        
                        $data=[$assignmentuser,$Type,$ultimo_registro[0]->id,$leader_name[0]->name,$data_anticipo[0]->empresa,$data_anticipo[0]->fecha_pago,$data_anticipo[0]->valor_anticipo,$data_anticipo[0]->forma_pago,$data_anticipo[0]->concepto,$data_anticipo[0]->name,$leader_id,$leader_id];

                        if ($MailSend != NULL) {
                          Mail::to($MailSend)->send(new SendMail($data));
                        }


                      header("Location: https://flora.tierragro.com/anticipos/historial",true,303);  
                      exit();  
                      //  header("HTTP/1.1 303 See Other");
                      //  return view('anticipos/historialnew',['modules' => $modules,'user' => $user,'anticipos'=>$anticipos,'count'=>$count]);
                      //  die();


               }else{

                        $suppliers = Supplier::where('active','=',1)
                              ->orderby('name','asc')
                              ->get(); 
           
   $directores=DB::SELECT("SELECT id AS id,
                                  name AS name,
                                  profile_name AS profile
                               FROM users
                               WHERE ((SUBSTRING(LTRIM(RTRIM(profile_name)),1,8)=? 
                               OR    SUBSTRING(LTRIM(RTRIM(profile_name)),1,9)=?) 
                               OR (id) = ?
                               OR (id) = ?
                               OR (id) = ?
                               OR (id) = ?)
                               AND active = ?",['DIRECTOR','DIRECTORA',6,239,226,275,1]);

                         return view('anticipos/anticiposerror',['modules' => $modules,'user' => $user, 'suppliers'=>$suppliers,'directores'=>$directores]);

               }

   }

/*

    if ($request->supplier_id) {

    $save=DB::INSERT('INSERT INTO anticipos (id_user,empresa,fecha_pago,valor_anticipo,forma_pago,concepto,proveedor,estado) VALUES (?,?,?,?,?,?,?,?)',[$user->id,$request->empresa,$request->fecha_anticipo,$request->valor_anticipo,$request->forma_pago,$request->concepto_anticipo,$request->supplier_id,0]);
  }else{
        $save=DB::INSERT('INSERT INTO anticipos (id_user,empresa,fecha_pago,valor_anticipo,forma_pago,concepto,proveedor,estado) VALUES (?,?,?,?,?,?,?,?)',[$user->id,$request->empresa,$request->fecha_anticipo,$request->valor_anticipo,$request->forma_pago,$request->concepto_anticipo,'',0]);
  }

    $ultimo_registro=DB::SELECT('SELECT max(id) AS id FROM anticipos');


      
      $cantidadadjuntos=intval($request->countfieldsadd);

      $leader_id=$request->id_director;
      $leader_name= DB::SELECT('SELECT first_name AS name, email AS email FROM users WHERE id=?',[$leader_id]);

      $file = $request->file('file1');
      
      $function_name=DB::SELECT('SELECT name AS name FROM functions WHERE id=?',[24]);
      if ($request->hasFile('file1'))
      {
         for ($i=1; $i <=$cantidadadjuntos; $i++) {
          $file = $request->file('file'.$i);
                $ext = $file->getClientOriginalExtension();
                $nombre = Str::random(6).".".$ext;
                \Storage::disk('facturas')->put($nombre,  \File::get($file));
            $guardado_datos=DB::INSERT("INSERT INTO attacheds(files,id_user,next_user_id,id_relation,id_function,name_module, created_at) VALUES (?,?,?,?,?,?,?)",[$nombre,$user->id,$leader_id,$ultimo_registro[0]->id,24,$function_name[0]->name,date('Y-m-d')]);
         }


   $anticipos = DB::SELECT('SELECT
                                 a.id AS id, 
                                 a.fecha_pago AS fecha_pago,
                                 a.valor_anticipo AS valor_anticipo,
                                 a.forma_pago AS forma_pago,
                                 a.concepto AS concepto,
                                   us.name AS gestionando,
                            CASE
                            WHEN a.estado = 0 THEN "En proceso..."
                            WHEN a.estado = 1 THEN "Aprobado"
                            WHEN a.estado = 2 THEN "Pagado"
                            WHEN a.estado = 3 THEN "Rechazado" 
                            WHEN a.estado = 4 THEN "Proceso legalización"
                            WHEN a.estado = 5 THEN "Legalización aprobada"
                            WHEN a.estado = 6 THEN "Legalización cerrada"
                            WHEN a.estado = 7 THEN "Legalización finalizada"
                            WHEN a.estado = 8 THEN "Legalización rechazada"       
                            END AS estado,
                            ad.files AS files
                            FROM anticipos a
                            INNER JOIN attacheds ad
                            ON ad.id_user = ?
                            INNER JOIN users us
                            ON ad.next_user_id=us.id 
                            WHERE ad.name_module= ? AND
                                  ad.id =(SELECT MAX(id) FROM attacheds ad WHERE ad.id_relation = a.id)
                                  GROUP BY a.id',[$user->id,$function_name[0]->name]);

       $count= DB::SELECT('SELECT count(id) AS count FROM anticipos
                        WHERE id_user=?',[$user->id]);

       $data_anticipo=DB::SELECT('SELECT a.empresa AS empresa,
                                         a.fecha_pago AS fecha_pago,
                                         a.valor_anticipo AS valor_anticipo,
                                         a.forma_pago AS forma_pago,
                                         a.concepto AS concepto,
                                         u.name AS name
                                         FROM anticipos a
                                         INNER JOIN users u
                                         ON u.id=a.id_user
                                         WHERE a.id=?',[$ultimo_registro[0]->id]);


        $assignmentuser = $leader_name[0]->name;
        $Type = 'anticipo';
        $MailSend= $leader_name[0]->email;

        $request->session()->put('assignmentuser', $leader_name[0]->name);
        
        $data=[$assignmentuser,$Type,$ultimo_registro[0]->id,$leader_name[0]->name,$data_anticipo[0]->empresa,$data_anticipo[0]->fecha_pago,$data_anticipo[0]->valor_anticipo,$data_anticipo[0]->forma_pago,$data_anticipo[0]->concepto,$data_anticipo[0]->name,$leader_id,$leader_id];

        if ($MailSend != NULL) {
          Mail::to($MailSend)->send(new SendMail($data));
        }


    return view('anticipos.historial',['modules' => $modules,'user' => $user,'anticipos'=>$anticipos,'count'=>$count]);

       }else{
              $suppliers = Supplier::where('active','=',1)
                              ->orderby('name','asc')
                              ->get(); 
           
             $directores=DB::SELECT("SELECT id AS id,
                                            name AS name
                                         FROM users
                                         WHERE (SUBSTRING(LTRIM(RTRIM(profile_name)),1,8)=? 
                                         OR    SUBSTRING(LTRIM(RTRIM(profile_name)),1,9)=?)
                                         AND active = ?",['DIRECTOR','DIRECTORA',1]);

              return view('anticipos/anticiposerror',['modules' => $modules,'user' => $user, 'suppliers'=>$suppliers,'directores'=>$directores]);

           // $guardado_datos=DB::INSERT("INSERT INTO attacheds(files,id_user,id_relation,id_function,name_module,created_at) VALUES (?,?,?,?,?,?,?)",['N/A',$user->id,$leader_id,$ultimo_registro[0]->id,24,$function_name[0]->name,date('Y-m-d')]);
       }
*/

  } 






  public function historial(Request $request){

    $user = Auth::user();
    $application = new Application();
    $modules = $application->getModules($user->id,4);


    $ultimo_registro = DB::SELECT('SELECT max(id) AS id FROM anticipos');

    $cantidadadjuntos = intval($request->countfieldsadd);

    $leader_id = DB::SELECT('SELECT leader_id AS leader_id FROM users
        WHERE id=?',[$user->id]);

    
    $estados_anticipos = config('app.global')['estados_anticipos'];

    $id_function_anticipos = config('app.global')['id_function_acticipos'];

    $id_function_legalizacion_acticipos = config('app.global')['id_function_legalizacion_acticipos'];

    $functNameAnticipos = DB::SELECT('SELECT name FROM functions WHERE id = ?', [
      $id_function_anticipos,
    ]);

    $functNameLegalizacionAnticipos = DB::SELECT('SELECT name FROM functions WHERE id = ?', [
      $id_function_legalizacion_acticipos,
    ]);

    $anticipos = DB::SELECT('SELECT
          a.id AS id, 
          a.fecha_pago AS fecha_pago,
          a.valor_anticipo AS valor_anticipo,
          a.forma_pago AS forma_pago,
          a.concepto AS concepto,
          us.name AS gestionando,
          CASE
            WHEN a.estado = 0 THEN "En proceso..."
            WHEN a.estado = 1 THEN "Aprobado"
            WHEN a.estado = 2 THEN "Pagado"
            WHEN a.estado = 3 THEN "Rechazado" 
            WHEN a.estado = 4 THEN "Proceso legalización"
            WHEN a.estado = 5 THEN "Legalización aprobada"
            WHEN a.estado = 6 THEN "Legalización cerrada"
            WHEN a.estado = 7 THEN "Legalización finalizada"
            WHEN a.estado = 8 THEN "Legalización rechazada"       
          END AS estado,
          ad.files AS files
        FROM anticipos a
        INNER JOIN attacheds ad
        ON ad.id_user = ?
        INNER JOIN users us
        ON ad.next_user_id = us.id
        WHERE 
          ( ad.name_module = ? OR ad.name_module = ? )
          AND
          a.estado <> '.$estados_anticipos['Legalización cerrada'].' 
          AND
          a.estado <> '.$estados_anticipos['Legalización finalizada'].'  
          AND
          ad.id =( SELECT MAX(id) FROM attacheds ad WHERE ad.id_relation = a.id )
        GROUP BY 
          a.id,us.name,ad.files 
        ORDER BY a.id DESC', [
          $user->id,
          $functNameAnticipos[0]->name,
          $functNameLegalizacionAnticipos[0]->name,
      ]);

    $count= DB::SELECT('SELECT count(id) AS count FROM anticipos
                        WHERE id_user=?',[$user->id]);
    return view('anticipos/historial', [
      'modules' => $modules,
      'user' => $user,
      'anticipos'=>$anticipos,
      'count'=>$count
    ]);
    

  } 




    public function historialcorreo(Request $request){


    $id_user_correo=$request->id_user;

    //$user = Auth::user();
    $application = new Application();
    $modules = $application->getModules($id_user_correo,4);


    $ultimo_registro=DB::SELECT('SELECT max(id) AS id FROM anticipos');


      
      $cantidadadjuntos=intval($request->countfieldsadd);

      $leader_id=DB::SELECT('SELECT leader_id AS leader_id FROM users
                             WHERE id=?',[$id_user_correo]);

   $function_name=DB::SELECT('SELECT name AS name FROM functions WHERE id=?',[24]);
   $anticipos = DB::SELECT('SELECT
                                 a.id AS id, 
                                 a.fecha_pago AS fecha_pago,
                                 a.valor_anticipo AS valor_anticipo,
                                 a.forma_pago AS forma_pago,
                                 a.concepto AS concepto,
                                us.name AS gestionando,
                            CASE
                            WHEN a.estado = 0 THEN "En proceso..."
                            WHEN a.estado = 1 THEN "Aprobado"
                            WHEN a.estado = 2 THEN "Pagado"
                            WHEN a.estado = 3 THEN "Rechazado" 
                            WHEN a.estado = 4 THEN "Proceso legalización"
                            WHEN a.estado = 5 THEN "Legalización aprobada"
                            WHEN a.estado = 6 THEN "Legalización cerrada"
                            WHEN a.estado = 7 THEN "Legalización finalizada"
                            WHEN a.estado = 8 THEN "Legalización rechazada"       
                            END AS estado,
                            ad.files AS files
                            FROM anticipos a
                            INNER JOIN attacheds ad
                            ON ad.id_user = ?
                            INNER JOIN users us
                            ON ad.next_user_id=us.id
                            WHERE ad.name_module= ?
                                  AND
                                  ad.id =(SELECT MAX(id) FROM attacheds ad WHERE ad.id_relation = a.id)
                            GROUP BY a.id',[$id_user_correo,$function_name[0]->name]);

    $count= DB::SELECT('SELECT count(id) AS count FROM anticipos
                        WHERE id_user=?',[$id_user_correo]);
    return view('anticipos.historial',['modules' => $modules,'user' => $id_user_correo,'anticipos'=>$anticipos,'count'=>$count]);

  }


  public function historialnew(Request $request){

    $user = Auth::user();
    $application = new Application();
    $modules = $application->getModules($user->id,4);


    $ultimo_registro=DB::SELECT('SELECT max(id) AS id FROM anticipos');


      
      $cantidadadjuntos=intval($request->countfieldsadd);

      $leader_id=DB::SELECT('SELECT leader_id AS leader_id FROM users
                             WHERE id=?',[$user->id]);

   $function_name=DB::SELECT('SELECT name AS name FROM functions WHERE id=?',[24]);
   $anticipos = DB::SELECT('SELECT
                                 a.id AS id, 
                                 a.fecha_pago AS fecha_pago,
                                 a.valor_anticipo AS valor_anticipo,
                                 a.forma_pago AS forma_pago,
                                 a.concepto AS concepto,
                                us.name AS gestionando,
                            CASE
                            WHEN a.estado = 0 THEN "En proceso..."
                            WHEN a.estado = 1 THEN "Aprobado"
                            WHEN a.estado = 2 THEN "Pagado"
                            WHEN a.estado = 3 THEN "Rechazado" 
                            WHEN a.estado = 4 THEN "Proceso legalización"
                            WHEN a.estado = 5 THEN "Legalización aprobada"
                            WHEN a.estado = 6 THEN "Legalización cerrada"
                            WHEN a.estado = 7 THEN "Legalización finalizada"
                            WHEN a.estado = 8 THEN "Legalización rechazada"       
                            END AS estado,
                            ad.files AS files
                            FROM anticipos a
                            INNER JOIN attacheds ad
                            ON ad.id_user = ?
                            INNER JOIN users us
                            ON ad.next_user_id=us.id
                            WHERE ad.name_module= ?
                                  AND
                                  ad.id =(SELECT MAX(id) FROM attacheds ad WHERE ad.id_relation = a.id)
                            GROUP BY a.id,us.name,ad.files ORDER BY a.id DESC',[$user->id,$function_name[0]->name]);

    $count= DB::SELECT('SELECT count(id) AS count FROM anticipos
                        WHERE id_user=?',[$user->id]);
    return view('anticipos/historial',['modules' => $modules,'user' => $user,'anticipos'=>$anticipos,'count'=>$count]);
    

  }   


  public function gestion(Request $request){

        $user = Auth::user();
        $application = new Application();
        $modules = $application->getModules($user->id,4);



       $function_name=DB::SELECT('SELECT name AS name FROM functions WHERE id=?',[24]);
        $anticipos=DB::SELECT('SELECT
                                     a.id AS id,
                                     a.id_user AS id_user,
                                     usn.cedula AS cedula,
                                     usnu.name AS ultimo_aprobador,
                                     usnu.profile_name AS cargo_aprobador,
                                     a.empresa AS empresa, 
                                     a.fecha_pago AS fecha_pago,
                                     a.valor_anticipo AS valor_anticipo,
                                     a.forma_pago AS forma_pago,
                                     a.concepto AS concepto,
                                    us.name AS gestionando,
                                    usn.name AS name,
                                    p.name AS proveedor,
                                CASE
                                WHEN a.estado = 0 THEN "En proceso..."
                                WHEN a.estado = 1 THEN "Aprobado"
                                WHEN a.estado = 2 THEN "Pagado"
                                WHEN a.estado = 3 THEN "Rechazado" 
                                WHEN a.estado = 4 THEN "Proceso legalización"
                                WHEN a.estado = 5 THEN "Legalización aprobada"
                                WHEN a.estado = 6 THEN "Legalización cerrada"
                                WHEN a.estado = 7 THEN "Legalización finalizada"
                                WHEN a.estado = 8 THEN "Legalización rechazada"    
                                END AS estado,
                                ad.files AS adjunto
                                FROM anticipos a
                                INNER JOIN attacheds ad
                                ON ad.id_relation = a.id
                                INNER JOIN users us
                                ON ad.next_user_id=?
                                INNER JOIN users usn
                                ON a.id_user =usn.id
                                LEFT JOIN suppliers p
                                ON p.id=a.proveedor
                                LEFT JOIN anticipos_log l
                                ON l.id= (SELECT MAX(id) FROM anticipos_log l WHERE l.id_document = a.id)
                                LEFT JOIN users usnu
                                ON usnu.id = l.user_id
                                WHERE ad.name_module= ? AND
                                      ad.id =(SELECT MAX(id) FROM attacheds ad WHERE ad.id_relation = a.id) 
                                GROUP BY a.id',[$user->id,$function_name[0]->name]);
        $count= count($anticipos);
        $id_usuario=$user->id;
        return view('anticipos/gestion',['modules' => $modules,'user' => $user,'anticipos'=>$anticipos,'count'=>$count,'id_usuario'=>$id_usuario]);
    
   }
     





  public function gestioncorreo(Request $request){
    $user=$request->id_user;
    $application = new Application();
    $modules = $application->getModules($user,4);



   $function_name=DB::SELECT('SELECT name AS name FROM functions WHERE id=?',[24]);
    $anticipos=DB::SELECT('SELECT
                                 a.id AS id,
                                 a.id_user AS id_user,
                                 usn.cedula AS cedula,
                                 usnu.name AS ultimo_aprobador,
                                 usnu.profile_name AS cargo_aprobador,
                                 a.empresa AS empresa,  
                                 a.fecha_pago AS fecha_pago,
                                 a.valor_anticipo AS valor_anticipo,
                                 a.forma_pago AS forma_pago,
                                 a.concepto AS concepto,
                                us.name AS gestionando,
                                usn.name AS name,
                                p.name AS proveedor,
                            CASE
                            WHEN a.estado = 0 THEN "En proceso..."
                            WHEN a.estado = 1 THEN "Aprobado"
                            WHEN a.estado = 2 THEN "Pagado"
                            WHEN a.estado = 3 THEN "Rechazado" 
                            WHEN a.estado = 4 THEN "Proceso legalización"
                            WHEN a.estado = 5 THEN "Legalización aprobada"
                            WHEN a.estado = 6 THEN "Legalización cerrada"
                            WHEN a.estado = 7 THEN "Legalización finalizada"
                            WHEN a.estado = 8 THEN "Legalización rechazada"    
                            END AS estado,
                            ad.files AS adjunto
                            FROM anticipos a
                            INNER JOIN attacheds ad
                            ON ad.id_relation = a.id
                            INNER JOIN users us
                            ON ad.next_user_id=?
                            INNER JOIN users usn
                            ON a.id_user =usn.id
                            LEFT JOIN suppliers p
                            ON p.id=a.proveedor
                            LEFT JOIN anticipos_log l
                            ON l.id= (SELECT MAX(id) FROM anticipos_log l WHERE l.id_document = a.id)
                            LEFT JOIN users usnu
                            ON usnu.id = l.user_id
                            WHERE ad.name_module= ? AND
                                  ad.id =(SELECT MAX(id) FROM attacheds ad WHERE ad.id_relation = a.id)
                            GROUP BY a.id',[$user,$function_name[0]->name]);
    $count= count($anticipos);
    $id_usuario=$user;
    return view('anticipos/gestioncorreo',['modules' => $modules,'user' => $user,'anticipos'=>$anticipos,'count'=>$count,'id_usuario'=>$id_usuario]);
    
  } 




  public function gestionaraceptar(Request $request){

    // Obtener el ID del User con la opción: Cierre de legalización
    $id_function_cierre_legalizacion = config('app.global')['id_function_cierre_legalizacion'];
    $rows_permissions = DB::SELECT('SELECT id_user FROM permission  
      WHERE function_id = ' . $id_function_cierre_legalizacion );

    $validador_contable = $rows_permissions[ 0 ]->id_user;

    // Obtener el ID del User con la opción: Pagar anticipos
    $id_function_pagar_anticipos = config('app.global')['id_function_pagar_anticipos'];
    $rows_permissions2 = DB::SELECT('SELECT id_user FROM permission  
      WHERE function_id = ' . $id_function_pagar_anticipos );

    $validador_tesoreria = $rows_permissions2[ 0 ]->id_user;

    $user = Auth::user();

    if( $user ){
      $application = new Application();
      $modules = $application->getModules($user->id,4);


      $leader_id = DB::SELECT('SELECT leader_id AS leader_id FROM users WHERE id=?',[$user->id]);

      $function_name = DB::SELECT('SELECT name AS name FROM functions WHERE id = ?', [
        config('app.global')['id_function_acticipos']
      ]);

      $ultimo_registro = DB::SELECT('SELECT max(id) AS id FROM attacheds
        WHERE id_relation=? AND
          name_module=?',[
            $request->id, 
            $function_name[0]->name
          ]);

      $anticipos1 = DB::SELECT('SELECT
          a.id AS id,
          a.id_user AS id_user, 
          a.fecha_pago AS fecha_pago,
          a.valor_anticipo AS valor_anticipo,
          a.forma_pago AS forma_pago,
          a.concepto AS concepto,
          us.name AS gestionando,
          usn.name AS name,
          CASE
          WHEN a.estado = 0 THEN "En proceso..."
          WHEN a.estado = 1 THEN "Aprobado"
          WHEN a.estado = 2 THEN "Pagado"
          WHEN a.estado = 3 THEN "Rechazado" 
          WHEN a.estado = 4 THEN "Proceso legalización"
          WHEN a.estado = 5 THEN "Legalización aprobada"
          WHEN a.estado = 6 THEN "Legalización cerrada"
          WHEN a.estado = 7 THEN "Legalización finalizada"
          WHEN a.estado = 8 THEN "Legalización rechazada"   
          END AS estado,
          ad.files AS adjunto
        FROM anticipos a
        INNER JOIN attacheds ad
        ON ad.id_relation = a.id
        INNER JOIN users us
        ON ad.next_user_id=?
        INNER JOIN users usn
        ON a.id_user =usn.id
        WHERE ad.name_module= ?
        GROUP BY a.id',[
          $user->id,
          $function_name[0]->name
        ]);

    
      $anticipo_valor = DB::SELECT('SELECT a.valor_anticipo
          FROM anticipos a
          WHERE a.id = ?
          GROUP BY a.id',[
            $request->id,
          ]);

      $valor_anticipo_real=str_replace('.', '', $anticipo_valor[0]->valor_anticipo);


      $cargo_usuario=DB::SELECT("SELECT profile_name AS profile
        FROM users
        WHERE id=?",[
          $user->id
        ]);


      $pos = strpos($cargo_usuario[0]->profile," ");
      $cargo_final =substr($cargo_usuario[0]->profile,0,$pos);


      if( (intval($valor_anticipo_real > 5000000)) && (($cargo_final == 'DIRECTOR') || ($cargo_final == 'DIRECTORA')) ){
        //echo '<h4>Entra en 1 </h4>';
        $update = DB::UPDATE('UPDATE attacheds
          SET next_user_id = ?
          WHERE id=?',[
            $leader_id[0]->leader_id,
            $ultimo_registro[0]->id
          ]);

        $guardado_datos_log = DB::INSERT("INSERT INTO anticipos_log(user_id,next_user_id,id_document,type_document, created_at) VALUES (?,?,?,?,?)",[
          $user->id,
          $leader_id[0]->leader_id,
          $request->id, //$ultimo_registro[0]->id,
          $function_name[0]->name,
          date('Y-m-d')
        ]);

        $leader_name= DB::SELECT('SELECT id AS id,first_name AS name, email AS email FROM users WHERE id=?',[
          $leader_id[0]->leader_id
        ]);

      }
      elseif( (intval($valor_anticipo_real > 5000000)) && (($cargo_final == 'GERENTE')) )
      {
        //echo '<h4>Entra en 2 </h4>';
        $update=DB::UPDATE('UPDATE attacheds
          SET next_user_id = ?
          WHERE id=?',[
            $validador_contable,
            $ultimo_registro[0]->id
          ]);

        $guardado_datos_log = DB::INSERT("INSERT INTO anticipos_log(user_id,next_user_id,id_document,type_document, created_at) VALUES (?,?,?,?,?)",[
          $user->id,
          $validador_contable,
          $request->id, //$ultimo_registro[0]->id,
          $function_name[0]->name,
          date('Y-m-d')
        ]);

        $leader_name = DB::SELECT('SELECT id AS id,first_name AS name, email AS email FROM users WHERE id=?',[
          $validador_contable,
        ]);

      }
      elseif( (intval($valor_anticipo_real < 5000000)) && (($cargo_final == 'GERENTE')) ){
        //echo '<h4>Entra en 3 </h4>';
        $update=DB::UPDATE('UPDATE attacheds
          SET next_user_id = ?
          WHERE id=?',[
            $validador_contable,
            $ultimo_registro[0]->id
        ]);

        $guardado_datos_log=DB::INSERT("INSERT INTO anticipos_log(user_id,next_user_id,id_document,type_document, created_at) VALUES (?,?,?,?,?)",[
          $user->id,
          $validador_contable,
          $request->id, //$ultimo_registro[0]->id,
          $function_name[0]->name,
          date('Y-m-d')
        ]);
        $leader_name= DB::SELECT('SELECT id AS id,first_name AS name, email AS email FROM users WHERE id=?',[
          $validador_contable,
        ]);

      }
      elseif( (intval($valor_anticipo_real <= 5000000)) && (($cargo_final == 'DIRECTOR') || ($cargo_final == 'DIRECTORA')) ){
        $update=DB::UPDATE('UPDATE attacheds
            SET next_user_id = ?
            WHERE id=?',[
              $validador_contable,
              $ultimo_registro[0]->id
        ]);
        $guardado_datos_log=DB::INSERT("INSERT INTO anticipos_log(user_id,next_user_id,id_document,type_document, created_at) VALUES (?,?,?,?,?)",[
          $user->id,
          $validador_contable,
          $request->id, //$ultimo_registro[0]->id,
          $function_name[0]->name,
          date('Y-m-d')
        ]);
        $leader_name= DB::SELECT('SELECT id as id,first_name AS name, email AS email FROM users WHERE id=?',[
          $validador_contable,
        ]);
      }
      //elseif( ($cargo_usuario[0]->profile == 'ANALISTA CONTABLE') && ($user->id == 1926) ){
      elseif( $user->id == $validador_contable ){
        $update=DB::UPDATE('UPDATE attacheds
          SET next_user_id = ?
          WHERE id=?',[
            $validador_tesoreria,
            $ultimo_registro[0]->id
        ]);
        $guardado_datos_log=DB::INSERT("INSERT INTO anticipos_log(user_id,next_user_id,id_document,type_document, created_at) VALUES (?,?,?,?,?)",[
          $user->id,
          $validador_tesoreria,
          $request->id, //$ultimo_registro[0]->id,
          $function_name[0]->name,
          date('Y-m-d')
        ]);
        $leader_name= DB::SELECT('SELECT id as id,first_name AS name, email AS email FROM users WHERE id=?',[
          $validador_tesoreria
        ]);
      }
      else{        
        $update=DB::UPDATE('UPDATE attacheds
          SET next_user_id = ?
          WHERE id=?',[
            $leader_id[0]->leader_id,
            $ultimo_registro[0]->id
        ]);
        $guardado_datos_log=DB::INSERT("INSERT INTO anticipos_log(user_id,next_user_id,id_document,type_document, created_at) VALUES (?,?,?,?,?)",[
          $user->id,
          $leader_id[0]->leader_id,
          $request->id, //$ultimo_registro[0]->id,
          $function_name[0]->name,
          date('Y-m-d')
        ]);
        $leader_name= DB::SELECT('SELECT id as id,first_name AS name, email AS email FROM users WHERE id=?',[
          $leader_id[0]->leader_id
        ]);
      }


      $update=DB::UPDATE('UPDATE anticipos
        SET estado = 1
        WHERE id=?',[
          $request->id
      ]);


      $anticipos=DB::SELECT('SELECT
        a.id AS id,
        a.id_user AS id_user,
        usn.cedula AS cedula,
        usnu.name AS ultimo_aprobador,
        usnu.profile_name AS cargo_aprobador,
        a.empresa AS empresa,  
        a.fecha_pago AS fecha_pago,
        a.valor_anticipo AS valor_anticipo,
        a.forma_pago AS forma_pago,
        a.concepto AS concepto,
        us.name AS gestionando,
        usn.name AS name,
        p.name AS proveedor,
        CASE
          WHEN a.estado = 0 THEN "En proceso..."
          WHEN a.estado = 1 THEN "Aprobado"
          WHEN a.estado = 2 THEN "Pagado"
          WHEN a.estado = 3 THEN "Rechazado" 
          WHEN a.estado = 4 THEN "Proceso legalización"
          WHEN a.estado = 5 THEN "Legalización aprobada"
          WHEN a.estado = 6 THEN "Legalización cerrada"
          WHEN a.estado = 7 THEN "Legalización finalizada"
          WHEN a.estado = 8 THEN "Legalización rechazada"      
        END AS estado,
        ad.files AS adjunto
        FROM anticipos a
        INNER JOIN attacheds ad
        ON ad.id_relation = a.id
        INNER JOIN users us
        ON ad.next_user_id=?
        INNER JOIN users usn
        ON a.id_user =usn.id
        LEFT JOIN suppliers p
        ON p.id=a.proveedor
        LEFT JOIN anticipos_log l
        ON l.id= (SELECT MAX(id) FROM anticipos_log l WHERE l.id_document = a.id)
        LEFT JOIN users usnu
        ON usnu.id = l.next_user_id
        WHERE ad.name_module= ? AND
        ad.id =(SELECT MAX(id) FROM attacheds ad WHERE ad.id_relation = a.id) 
        GROUP BY a.id',[
          $user->id,
          $function_name[0]->name
        ]);


      $count= DB::SELECT('SELECT count(a.id) AS count 
        FROM anticipos a
        INNER JOIN users u
        ON u.id=a.id_user
        INNER JOIN attacheds atc
        ON atc.id_relation= a.id
        WHERE atc.next_user_id=? AND
        a.estado <> ?',[
          $user->id,
          config('app.global')['estados_anticipos']['Legalización cerrada'],//6
      ]);


      $data_anticipo=DB::SELECT('SELECT a.id AS id,
          a.empresa AS empresa,
          a.fecha_pago AS fecha_pago,
          a.valor_anticipo AS valor_anticipo,
          a.forma_pago AS forma_pago,
          a.concepto AS concepto,
          u.name AS name
        FROM anticipos a
        INNER JOIN users u
        ON u.id=a.id_user
        WHERE a.id=?',[
          $request->id
      ]);


      // $Case = Ticket::orderby('id','DESC')->limit(1)->get();
      // $TicketUser= User::where('id','=',$request->user_id);
      $Type='';
      $assignmentuser = $leader_name[0]->name;

      if( $user->id != $validador_contable ){
        $Type = 'anticipo';
      }
      else{
        $Type = 'pagoanticipocorreo';
      }

      /*
      echo '<pre>';
      print_r([
        'user_id' => $user->id,
        'leader_id' => $leader_id,
        'valor_anticipo_real' => $valor_anticipo_real,
        'cargo_usuario' => $cargo_usuario,
        'cargo_final' => $cargo_final,
      ]);
      echo '</pre>'; */
      //exit();


      //$CaseNumber =$Case[0]->id;
      $MailSend= $leader_name[0]->email;

      $request->session()->put('assignmentuser', $leader_name[0]->name);


      $data = [
        $assignmentuser,
        $Type,
        $request->id,
        $leader_name[0]->name,
        $data_anticipo[0]->empresa,
        $data_anticipo[0]->fecha_pago,
        $data_anticipo[0]->valor_anticipo,
        $data_anticipo[0]->forma_pago,
        $data_anticipo[0]->concepto,
        $data_anticipo[0]->name,
        $data_anticipo[0]->id,
        $leader_name[0]->id
      ];


      if ($MailSend != NULL) {
        Mail::to($MailSend)->send(new SendMail($data));
      }

      $id_usuario=$user->id;
    
      return view('anticipos/gestion',[
        'modules' => $modules,
        'user' => $user,
        'anticipos'=>$anticipos,
        'count'=>$count,
        'id_usuario'=>$id_usuario
      ]);

    }
    else{
    
      $user = $request->id_user_proceso;

      $application = new Application();
      $modules = $application->getModules($user,4);

      $leader_id = DB::SELECT('SELECT leader_id AS leader_id FROM users
          WHERE id=?',[
            $user
          ]);

      $function_name = DB::SELECT('SELECT name AS name FROM functions WHERE id=?',[
        config('app.global')['id_function_acticipos']
      ]);

      $ultimo_registro = DB::SELECT('SELECT max(id) AS id FROM attacheds
          WHERE id_relation=? AND
          name_module=?',[
            $request->id,
            $function_name[0]->name,
      ]);

    

      $anticipos1 = DB::SELECT('SELECT
          a.id AS id,
          a.id_user AS id_user, 
          a.fecha_pago AS fecha_pago,
          a.valor_anticipo AS valor_anticipo,
          a.forma_pago AS forma_pago,
          a.concepto AS concepto,
          us.name AS gestionando,
          usn.name AS name,
          CASE
            WHEN a.estado = 0 THEN "En proceso..."
            WHEN a.estado = 1 THEN "Aprobado"
            WHEN a.estado = 2 THEN "Pagado"
            WHEN a.estado = 3 THEN "Rechazado" 
            WHEN a.estado = 4 THEN "Proceso legalización"
            WHEN a.estado = 5 THEN "Legalización aprobada"
            WHEN a.estado = 6 THEN "Legalización cerrada"
            WHEN a.estado = 7 THEN "Legalización finalizada"
            WHEN a.estado = 8 THEN "Legalización rechazada"   
            END AS estado,
          ad.files AS adjunto
        FROM anticipos a
        INNER JOIN attacheds ad
        ON ad.id_relation = a.id
        INNER JOIN users us
        ON ad.next_user_id=?
        INNER JOIN users usn
        ON a.id_user =usn.id
        WHERE ad.name_module= ?
        GROUP BY a.id',[
          $user,
          $function_name[0]->name
        ]);

      $valor_anticipo_real=str_replace('.', '', $anticipos1[0]->valor_anticipo);


      $cargo_usuario=DB::SELECT("SELECT profile_name AS profile
          FROM users
          WHERE id=?",[
            $user
          ]);



      $pos = strpos($cargo_usuario[0]->profile," ");
      $cargo_final =substr($cargo_usuario[0]->profile,0,$pos);



      if( (intval($valor_anticipo_real > 5000000)) && (($cargo_final == 'DIRECTOR') || ($cargo_final == 'DIRECTORA')) ){
        $update=DB::UPDATE('UPDATE attacheds
          SET next_user_id = ?
          WHERE id=?',[
            $leader_id[0]->leader_id,
            $ultimo_registro[0]->id
          ]);
        $leader_name= DB::SELECT('SELECT id AS id,first_name AS name, email AS email FROM users WHERE id=?',[
          $leader_id[0]->leader_id
        ]);

      }
      elseif((intval($valor_anticipo_real > 5000000)) && (($cargo_final == 'GERENTE'))){
       $update=DB::UPDATE('UPDATE attacheds
          SET next_user_id = ?
          WHERE id=?',[
            $validador_contable,
            $ultimo_registro[0]->id
          ]);
       $leader_name= DB::SELECT('SELECT id AS id,first_name AS name, email AS email FROM users WHERE id=?',[
        $validador_contable
        ]);

      }
      elseif((intval($valor_anticipo_real <= 5000000)) && (($cargo_final == 'DIRECTOR') || ($cargo_final == 'DIRECTORA'))){
        $update=DB::UPDATE('UPDATE attacheds
            SET next_user_id = ?
            WHERE id=?',[
              $validador_contable,
              $ultimo_registro[0]->id
            ]);
        $leader_name= DB::SELECT('SELECT id as id,first_name AS name, email AS email FROM users WHERE id=?',[
          $validador_contable
        ]);

      }
      elseif((intval($valor_anticipo_real < 5000000)) && (($cargo_final == 'GERENTE'))){
       $update=DB::UPDATE('UPDATE attacheds
          SET next_user_id = ?
          WHERE id=?',[
            $validador_contable,
            $ultimo_registro[0]->id
          ]);       
      }
      // ????
      //elseif( ($cargo_final == 'ANALISTA CONTABLE') && ($user == 1926) ){
      elseif( $user == $validador_contable ){ 
          $update=DB::UPDATE('UPDATE attacheds
            SET next_user_id = ?
            WHERE id=?',[
              $validador_tesoreria,
              $ultimo_registro[0]->id
            ]);
          $leader_name= DB::SELECT('SELECT id as id,first_name AS name, email AS email FROM users WHERE id=?',[
            $validador_tesoreria
          ]);

      }
      else{
        $update=DB::UPDATE('UPDATE attacheds
          SET next_user_id = ?
          WHERE id=?',[
            $leader_id[0]->leader_id,
            $ultimo_registro[0]->id
          ]);
        $leader_name= DB::SELECT('SELECT id as id,first_name AS name, email AS email FROM users WHERE id=?',[
          $leader_id[0]->leader_id
        ]);

      }


    $update=DB::UPDATE('UPDATE anticipos
        SET estado = 1
        WHERE id=?',[
          $request->id
        ]);


    $anticipos=DB::SELECT('SELECT
          a.id AS id,
          a.id_user AS id_user,
          usn.cedula AS cedula,
          usnu.name AS ultimo_aprobador,
          usnu.profile_name AS cargo_aprobador,
          a.empresa AS empresa,  
          a.fecha_pago AS fecha_pago,
          a.valor_anticipo AS valor_anticipo,
          a.forma_pago AS forma_pago,
          a.concepto AS concepto,
          us.name AS gestionando,
          usn.name AS name,
          p.name AS proveedor,
          CASE
            WHEN a.estado = 0 THEN "En proceso..."
            WHEN a.estado = 1 THEN "Aprobado"
            WHEN a.estado = 2 THEN "Pagado"
            WHEN a.estado = 3 THEN "Rechazado" 
            WHEN a.estado = 4 THEN "Proceso legalización"
            WHEN a.estado = 5 THEN "Legalización aprobada"
            WHEN a.estado = 6 THEN "Legalización cerrada"
            WHEN a.estado = 7 THEN "Legalización finalizada"
            WHEN a.estado = 8 THEN "Legalización rechazada"      
          END AS estado,
          ad.files AS adjunto
        FROM anticipos a
        INNER JOIN attacheds ad
        ON ad.id_relation = a.id
        INNER JOIN users us
        ON ad.next_user_id=?
        INNER JOIN users usn
        ON a.id_user =usn.id
        LEFT JOIN suppliers p
        ON p.id=a.proveedor
        LEFT JOIN anticipos_log l
        ON l.id= (SELECT MAX(id) FROM anticipos_log l WHERE l.id_document = a.id)
        LEFT JOIN users usnu
        ON usnu.id = l.next_user_id
        WHERE ad.name_module= ? AND
        ad.id =(SELECT MAX(id) FROM attacheds ad WHERE ad.id_relation = a.id) 
        GROUP BY a.id',[
          $user,
          $function_name[0]->name
        ]);


    $count= DB::SELECT('SELECT count(a.id) AS count 
      FROM anticipos a
      INNER JOIN users u
      ON u.id=a.id_user
      INNER JOIN attacheds atc
      ON atc.id_relation= a.id
      WHERE atc.next_user_id=? AND
      a.estado <> ?',[
        $user,
        6
      ]);


    $data_anticipo = DB::SELECT('SELECT a.id AS id,
        a.empresa AS empresa,
        a.fecha_pago AS fecha_pago,
        a.valor_anticipo AS valor_anticipo,
        a.forma_pago AS forma_pago,
        a.concepto AS concepto,
        u.name AS name
        FROM anticipos a
        INNER JOIN users u
        ON u.id=a.id_user
        WHERE a.id=?',[
          $request->id
        ]);


       // $Case = Ticket::orderby('id','DESC')->limit(1)->get();
       // $TicketUser= User::where('id','=',$request->user_id);
    $Type='';
    $assignmentuser = $leader_name[0]->name;

    if( $user != $validador_contable ){
      $Type = 'anticipo';
    }else{
      $Type = 'pagoanticipocorreo';
    }

    //$CaseNumber =$Case[0]->id;
    $MailSend= $leader_name[0]->email;

    $request->session()->put('assignmentuser', $leader_name[0]->name);

    
    $data = [
      $assignmentuser,
      $Type,
      $request->id,
      $leader_name[0]->name,
      $data_anticipo[0]->empresa,
      $data_anticipo[0]->fecha_pago,
      $data_anticipo[0]->valor_anticipo,
      $data_anticipo[0]->forma_pago,
      $data_anticipo[0]->concepto,
      $data_anticipo[0]->name,
      $data_anticipo[0]->id,
      $leader_name[0]->id
    ];


    if ($MailSend != NULL) {
      Mail::to($MailSend)->send(new SendMail($data));
    }

    $id_usuario = $user;
    return view('anticipos/gestion',[
      'modules' => $modules,
      'user' => $user,
      'anticipos'=>$anticipos,
      'count'=>$count,
      'id_usuario'=>$id_usuario]
    );
  }
}
  





  public function gestionarrechazar(Request $request){

    $user = Auth::user();
    if ($user) {
    $application = new Application();
    $modules = $application->getModules($user->id,4);

    $function_name=DB::SELECT('SELECT name AS name FROM functions WHERE id=?',[24]);
    $original_user=DB::SELECT('SELECT id_user AS original_user
                               FROM anticipos
                               WHERE id=?',[$request->invoice_id]);


    $update=DB::UPDATE('UPDATE anticipos
                                  SET estado = 3,
                                      motivo_rechazo = ?,
                                      id_user_rechazo = ?
                                  WHERE id=?',[$request->motivo_rechazo,$user->id,$request->invoice_id]);

    $update_attacheds=DB::UPDATE('UPDATE attacheds
                        SET next_user_id = ?
                        WHERE id_relation=? AND
                              name_module = ? AND
                              next_user_id = ?',[$original_user[0]->original_user,$request->invoice_id,$function_name[0]->name,$user->id]);
    $guardado_datos_log=DB::INSERT("INSERT INTO anticipos_log(user_id,next_user_id,id_document,type_document, created_at) VALUES (?,?,?,?,?)",[$user->id,$original_user[0]->original_user,$request->invoice_id,$function_name[0]->name,date('Y-m-d')]);



    $anticipos=DB::SELECT('SELECT
                                 a.id AS id,
                                 a.id_user AS id_user,
                                 usn.cedula AS cedula,
                                 usnu.name AS ultimo_aprobador,
                                 usnu.profile_name AS cargo_aprobador,
                                 a.empresa AS empresa,  
                                 a.fecha_pago AS fecha_pago,
                                 a.valor_anticipo AS valor_anticipo,
                                 a.forma_pago AS forma_pago,
                                 a.concepto AS concepto,
                                us.name AS gestionando,
                                usn.name AS name,
                                p.name AS proveedor,
                            CASE
                            WHEN a.estado = 0 THEN "En proceso..."
                            WHEN a.estado = 1 THEN "Aprobado"
                            WHEN a.estado = 2 THEN "Pagado"
                            WHEN a.estado = 3 THEN "Rechazado" 
                            WHEN a.estado = 4 THEN "Proceso legalización"
                            WHEN a.estado = 5 THEN "Legalización aprobada"
                            WHEN a.estado = 6 THEN "Legalización cerrada"
                            WHEN a.estado = 7 THEN "Legalización finalizada"
                            WHEN a.estado = 8 THEN "Legalización rechazada"      
                            END AS estado,
                            ad.files AS adjunto
                            FROM anticipos a
                            INNER JOIN attacheds ad
                            ON ad.id_relation = a.id
                            INNER JOIN users us
                            ON ad.next_user_id=?
                            INNER JOIN users usn
                            ON a.id_user =usn.id
                            LEFT JOIN suppliers p
                            ON p.id=a.proveedor
                            LEFT JOIN anticipos_log l
                           ON l.id= (SELECT MAX(id) FROM anticipos_log l WHERE l.id_document = a.id)
                            LEFT JOIN users usnu
                            ON usnu.id = l.next_user_id
                            WHERE ad.name_module= ? AND
                                  ad.id =(SELECT MAX(id) FROM attacheds ad WHERE ad.id_relation = a.id) 
                            GROUP BY a.id',[$user->id,$function_name[0]->name]);
    $count= DB::SELECT('SELECT count(a.id) AS count 
                        FROM anticipos a
                               INNER JOIN users u
                               ON u.id=a.id_user
                        WHERE u.leader_id=? AND
                              a.estado = ?',[$user->id,0]);


       $data_anticipo=DB::SELECT('SELECT a.empresa AS empresa,
                                         a.fecha_pago AS fecha_pago,
                                         a.valor_anticipo AS valor_anticipo,
                                         a.forma_pago AS forma_pago,
                                         a.concepto AS concepto,
                                         u.first_name AS name,
                                         a.motivo_rechazo AS motivo_rechazo,
                                         u.email AS email,
                                         u.id AS id_user,
                                         ur.name AS usuario_rechazo
                                         FROM anticipos a
                                         INNER JOIN users u
                                         ON u.id=a.id_user
                                         INNER JOIN users ur
                                         ON ur.id = a.id_user_rechazo
                                         WHERE a.id=?',[$request->invoice_id]);



       // $Case = Ticket::orderby('id','DESC')->limit(1)->get();
       // $TicketUser= User::where('id','=',$request->user_id);

      //  $assignmentuser = $leader_name[0]->name;
        $Type = 'anticiporechazo';
        //$CaseNumber =$Case[0]->id;
        $MailSend= $data_anticipo[0]->email;

        $request->session()->put('assignmentuser', $data_anticipo[0]->name);

        
        $data=[$data_anticipo[0]->name,$Type,$request->invoice_id,$data_anticipo[0]->name,$data_anticipo[0]->empresa,$data_anticipo[0]->fecha_pago,$data_anticipo[0]->valor_anticipo,$data_anticipo[0]->forma_pago,$data_anticipo[0]->concepto,$data_anticipo[0]->name,$data_anticipo[0]->motivo_rechazo,$data_anticipo[0]->usuario_rechazo,$data_anticipo[0]->id_user];


        if ($MailSend != NULL) {
          Mail::to($MailSend)->send(new SendMail($data));
        }


    $id_usuario=$user->id;
    return view('anticipos/gestion',['modules' => $modules,'user' => $user,'anticipos'=>$anticipos,'count'=>$count,'id_usuario'=>$id_usuario]);
    }else{
    $user=$request->id_usuario;
    $application = new Application();
    $modules = $application->getModules($user,4);

    $function_name=DB::SELECT('SELECT name AS name FROM functions WHERE id=?',[24]);
    $original_user=DB::SELECT('SELECT id_user AS original_user
                               FROM anticipos
                               WHERE id=?',[$request->invoice_id]);


    $update=DB::UPDATE('UPDATE anticipos
                                  SET estado = 3,
                                      motivo_rechazo = ?,
                                      id_user_rechazo = ?
                                  WHERE id=?',[$request->motivo_rechazo,$user,$request->invoice_id]);

    $update_attacheds=DB::UPDATE('UPDATE attacheds
                        SET next_user_id = ?
                        WHERE id_relation=? AND
                              name_module = ? AND
                              next_user_id = ?',[$original_user[0]->original_user,$request->invoice_id,$function_name[0]->name,$user]);
    $guardado_datos_log=DB::INSERT("INSERT INTO anticipos_log(user_id,next_user_id,id_document,type_document, created_at) VALUES (?,?,?,?,?)",[$user->id,$original_user[0]->original_user,$request->invoice_id,$function_name[0]->name,date('Y-m-d')]);



    $anticipos=DB::SELECT('SELECT
                                 a.id AS id,
                                 a.id_user AS id_user,
                                 usn.cedula AS cedula,
                                 usnu.name AS ultimo_aprobador,
                                 usnu.profile_name AS cargo_aprobador,
                                 a.empresa AS empresa,  
                                 a.fecha_pago AS fecha_pago,
                                 a.valor_anticipo AS valor_anticipo,
                                 a.forma_pago AS forma_pago,
                                 a.concepto AS concepto,
                                us.name AS gestionando,
                                usn.name AS name,
                                p.name AS proveedor,
                            CASE
                            WHEN a.estado = 0 THEN "En proceso..."
                            WHEN a.estado = 1 THEN "Aprobado"
                            WHEN a.estado = 2 THEN "Pagado"
                            WHEN a.estado = 3 THEN "Rechazado" 
                            WHEN a.estado = 4 THEN "Proceso legalización"
                            WHEN a.estado = 5 THEN "Legalización aprobada"
                            WHEN a.estado = 6 THEN "Legalización cerrada"
                            WHEN a.estado = 7 THEN "Legalización finalizada"
                            WHEN a.estado = 8 THEN "Legalización rechazada"      
                            END AS estado,
                            ad.files AS adjunto
                            FROM anticipos a
                            INNER JOIN attacheds ad
                            ON ad.id_relation = a.id
                            INNER JOIN users us
                            ON ad.next_user_id=?
                            INNER JOIN users usn
                            ON a.id_user =usn.id
                            LEFT JOIN suppliers p
                            ON p.id=a.proveedor
                            LEFT JOIN anticipos_log l
                           ON l.id= (SELECT MAX(id) FROM anticipos_log l WHERE l.id_document = a.id)
                            LEFT JOIN users usnu
                            ON usnu.id = l.next_user_id
                            WHERE ad.name_module= ? AND
                                  ad.id =(SELECT MAX(id) FROM attacheds ad WHERE ad.id_relation = a.id) 
                            GROUP BY a.id',[$user,$function_name[0]->name]);
    $count= DB::SELECT('SELECT count(a.id) AS count 
                        FROM anticipos a
                               INNER JOIN users u
                               ON u.id=a.id_user
                        WHERE u.leader_id=? AND
                              a.estado = ?',[$user,0]);


       $data_anticipo=DB::SELECT('SELECT a.empresa AS empresa,
                                         a.fecha_pago AS fecha_pago,
                                         a.valor_anticipo AS valor_anticipo,
                                         a.forma_pago AS forma_pago,
                                         a.concepto AS concepto,
                                         u.first_name AS name,
                                         a.motivo_rechazo AS motivo_rechazo,
                                         u.email AS email,
                                         u.id AS id_user,
                                         ur.name AS usuario_rechazo
                                         FROM anticipos a
                                         INNER JOIN users u
                                         ON u.id=a.id_user
                                         INNER JOIN users ur
                                         ON ur.id = a.id_user_rechazo
                                         WHERE a.id=?',[$request->invoice_id]);



       // $Case = Ticket::orderby('id','DESC')->limit(1)->get();
       // $TicketUser= User::where('id','=',$request->user_id);

      //  $assignmentuser = $leader_name[0]->name;
        $Type = 'anticiporechazo';
        //$CaseNumber =$Case[0]->id;
        $MailSend= $data_anticipo[0]->email;

        $request->session()->put('assignmentuser', $data_anticipo[0]->name);

        
        $data=[$data_anticipo[0]->name,$Type,$request->invoice_id,$data_anticipo[0]->name,$data_anticipo[0]->empresa,$data_anticipo[0]->fecha_pago,$data_anticipo[0]->valor_anticipo,$data_anticipo[0]->forma_pago,$data_anticipo[0]->concepto,$data_anticipo[0]->name,$data_anticipo[0]->motivo_rechazo,$data_anticipo[0]->usuario_rechazo,$data_anticipo[0]->id_user];

        if ($MailSend != NULL) {
          Mail::to($MailSend)->send(new SendMail($data));
        }


    $id_usuario=$request->id_usuario;
    return view('anticipos/gestion',['modules' => $modules,'user' => $user,'anticipos'=>$anticipos,'count'=>$count,'id_usuario'=>$id_usuario]);
  }
    

  }      





  public function gestionarrechazarlegalizacion(Request $request){

    $user = Auth::user();
    $application = new Application();
    $modules = $application->getModules($user->id,4);
    $function_name=DB::SELECT('SELECT name AS name FROM functions WHERE id=?',[27]);


    $original_user=DB::SELECT('SELECT id_user AS original_user
        FROM anticipos
        WHERE id=?',[$request->invoice_id]);


    $update=DB::UPDATE('UPDATE anticipos
        SET estado = 8,
            motivo_rechazo_legalización = ?,
            id_user_rechazo_legalizacion = ?
        WHERE id=?', [
          $request->motivo_rechazo,$user->id,$request->invoice_id
        ]);

    $update_attacheds=DB::UPDATE('UPDATE attacheds
        SET next_user_id = ?
        WHERE id_relation=? AND
              name_module = ? AND
              next_user_id = ?',[$original_user[0]->original_user,$request->invoice_id,$function_name[0]->name,$user->id]);

    $guardado_datos_log=DB::INSERT("INSERT INTO anticipos_log(user_id,next_user_id,id_document,type_document, created_at) VALUES (?,?,?,?,?)",[$user->id,$original_user[0]->original_user,$request->invoice_id,$function_name[0]->name,date('Y-m-d')]);



    $anticipos=DB::SELECT('SELECT
        a.id AS id,
        a.id_user AS id_user,
        usn.cedula AS cedula,
        usnu.name AS ultimo_aprobador,
        usnu.profile_name AS cargo_aprobador,
        a.empresa AS empresa,  
        a.fecha_pago AS fecha_pago,
        a.valor_anticipo AS valor_anticipo,
        a.forma_pago AS forma_pago,
        a.concepto AS concepto,
        us.name AS gestionando,
        usn.name AS name,
        p.name AS proveedor,
        CASE
          WHEN a.estado = 0 THEN "En proceso..."
          WHEN a.estado = 1 THEN "Aprobado"
          WHEN a.estado = 2 THEN "Pagado"
          WHEN a.estado = 3 THEN "Rechazado" 
          WHEN a.estado = 4 THEN "Proceso legalización"
          WHEN a.estado = 5 THEN "Legalización aprobada"
          WHEN a.estado = 6 THEN "Legalización cerrada"
          WHEN a.estado = 7 THEN "Legalización finalizada"
          WHEN a.estado = 8 THEN "Legalización rechazada"      
        END AS estado,
        ad.files AS adjunto
      FROM anticipos a
      INNER JOIN attacheds ad
      ON ad.id_relation = a.id
      INNER JOIN users us
      ON ad.next_user_id=?
      INNER JOIN users usn
      ON a.id_user =usn.id
      LEFT JOIN suppliers p
      ON p.id=a.proveedor
      LEFT JOIN anticipos_log l
      ON l.id= (SELECT MAX(id) FROM anticipos_log l WHERE l.id_document = a.id)
      LEFT JOIN users usnu
      ON usnu.id = l.next_user_id
      WHERE ad.name_module= ? AND
            ad.id =(SELECT MAX(id) FROM attacheds ad WHERE ad.id_relation = a.id)
      GROUP BY a.id',[$user->id,$function_name[0]->name]);

    $count= DB::SELECT('SELECT count(a.id) AS count 
        FROM anticipos a
                INNER JOIN users u
                ON u.id=a.id_user
        WHERE u.leader_id=? AND
              a.estado = ?',[$user->id,0]);


    $data_anticipo=DB::SELECT('SELECT a.empresa AS empresa,
        a.fecha_pago AS fecha_pago,
        a.valor_anticipo AS valor_anticipo,
        a.forma_pago AS forma_pago,
        a.concepto AS concepto,
        u.first_name AS name,
        a.motivo_rechazo_legalización AS motivo_rechazo,
        u.email AS email,
        ur.name AS usuario_rechazo
        FROM anticipos a
        INNER JOIN users u
        ON u.id=a.id_user
        INNER JOIN users ur
        ON ur.id = a.id_user_rechazo_legalizacion
        WHERE a.id=?',[$request->invoice_id]);



      $Type = 'legalizacionrechazo';
      $MailSend= $data_anticipo[0]->email;

      $request->session()->put('assignmentuser', $data_anticipo[0]->name);

        
      $data=[$data_anticipo[0]->name,$Type,$request->invoice_id,$data_anticipo[0]->name,$data_anticipo[0]->empresa,$data_anticipo[0]->fecha_pago,$data_anticipo[0]->valor_anticipo,$data_anticipo[0]->forma_pago,$data_anticipo[0]->concepto,$data_anticipo[0]->name,$data_anticipo[0]->motivo_rechazo,$data_anticipo[0]->usuario_rechazo];


      if ($MailSend != NULL) {
        Mail::to($MailSend)->send(new SendMail($data));
      }

      $id_usuario = $user->id;

      return view('anticipos/gestion', [
        'modules' => $modules,
        'user' => $user,
        'anticipos'=>$anticipos,
        'count'=>$count,
        'id_usuario'=>$id_usuario,
      ]);
  } 


  public function pagaranticipos(Request $request){

    $user = Auth::user();
    if ($user) {
    $application = new Application();
    $modules = $application->getModules($user->id,4);

    $function_name=DB::SELECT('SELECT name AS name FROM functions WHERE id=?',[24]);


    $anticipos=DB::SELECT('SELECT
                                 a.id AS id,
                                 a.id_user AS id_user, 
                                 a.fecha_pago AS fecha_pago,
                                 a.valor_anticipo AS valor_anticipo,
                                 a.forma_pago AS forma_pago,
                                 a.concepto AS concepto,
                                us.name AS gestionando,
                                usn.name AS name,
                                p.name AS proveedor,
                            CASE
                            WHEN a.estado = 0 THEN "En proceso..."
                            WHEN a.estado = 1 THEN "Aprobado"
                            WHEN a.estado = 2 THEN "Pagado"
                            WHEN a.estado = 3 THEN "Rechazado" 
                            WHEN a.estado = 4 THEN "Proceso legalización"
                            WHEN a.estado = 5 THEN "Legalización aprobada"
                            WHEN a.estado = 6 THEN "Legalización cerrada"
                            WHEN a.estado = 7 THEN "Legalización finalizada"
                            WHEN a.estado = 8 THEN "Legalización rechazada"       
                            END AS estado,
                            ad.files AS adjunto
                            FROM anticipos a
                            INNER JOIN attacheds ad
                            ON ad.id_relation = a.id
                            INNER JOIN users us
                            ON ad.next_user_id=?
                            INNER JOIN users usn
                            ON a.id_user =usn.id
                            LEFT JOIN suppliers p
                            ON p.id=a.proveedor
                            WHERE ad.name_module= ? AND
                                  a.estado = ?
                            GROUP BY a.id',[$user->id,$function_name[0]->name,1]);




   $count= DB::SELECT('SELECT count(a.id) AS count 
                        FROM anticipos a
                               INNER JOIN users u
                               ON u.id=a.id_user
                        WHERE a.estado = ?',[1]);
    return view('anticipos/pagar',['modules' => $modules,'user' => $user,'anticipos'=>$anticipos,'count'=>$count]);
    }else{
    $user=2101;
    $application = new Application();
    $modules = $application->getModules($user,4);

    $function_name=DB::SELECT('SELECT name AS name FROM functions WHERE id=?',[24]);


    $anticipos=DB::SELECT('SELECT
                                 a.id AS id,
                                 a.id_user AS id_user, 
                                 a.fecha_pago AS fecha_pago,
                                 a.valor_anticipo AS valor_anticipo,
                                 a.forma_pago AS forma_pago,
                                 a.concepto AS concepto,
                                us.name AS gestionando,
                                usn.name AS name,
                                p.name AS proveedor,
                            CASE
                            WHEN a.estado = 0 THEN "En proceso..."
                            WHEN a.estado = 1 THEN "Aprobado"
                            WHEN a.estado = 2 THEN "Pagado"
                            WHEN a.estado = 3 THEN "Rechazado" 
                            WHEN a.estado = 4 THEN "Proceso legalización"
                            WHEN a.estado = 5 THEN "Legalización aprobada"
                            WHEN a.estado = 6 THEN "Legalización cerrada"
                            WHEN a.estado = 7 THEN "Legalización finalizada"
                            WHEN a.estado = 8 THEN "Legalización rechazada"       
                            END AS estado,
                            ad.files AS adjunto
                            FROM anticipos a
                            INNER JOIN attacheds ad
                            ON ad.id_relation = a.id
                            INNER JOIN users us
                            ON ad.next_user_id=?
                            INNER JOIN users usn
                            ON a.id_user =usn.id
                            LEFT JOIN suppliers p
                            ON p.id=a.proveedor
                            WHERE ad.name_module= ? AND
                                  a.estado = ?
                            GROUP BY a.id',[$user,$function_name[0]->name,1]);

   $count= DB::SELECT('SELECT count(a.id) AS count 
                        FROM anticipos a
                               INNER JOIN users u
                               ON u.id=a.id_user
                        WHERE a.estado = ?',[1]);
    return view('anticipos/pagar',['modules' => $modules,'user' => $user,'anticipos'=>$anticipos,'count'=>$count]);
  }

  }



  public function pagar(Request $request){
    $user = Auth::user();
    $application = new Application();
    $modules = $application->getModules($user->id,4);

    $function_name=DB::SELECT('SELECT name AS name FROM functions WHERE id=?',[24]);


    $anticipos=DB::SELECT('SELECT
                                 a.id AS id,
                                 a.id_user AS id_user, 
                                 a.fecha_pago AS fecha_pago,
                                 a.valor_anticipo AS valor_anticipo,
                                 a.forma_pago AS forma_pago,
                                 a.concepto AS concepto,
                                us.name AS gestionando,
                                usn.name AS name,
                                p.name AS proveedor,
                            CASE
                            WHEN a.estado = 0 THEN "En proceso..."
                            WHEN a.estado = 1 THEN "Aprobado"
                            WHEN a.estado = 2 THEN "Pagado"
                            WHEN a.estado = 3 THEN "Rechazado" 
                            WHEN a.estado = 4 THEN "Proceso legalización"
                            WHEN a.estado = 5 THEN "Legalización aprobada"
                            WHEN a.estado = 6 THEN "Legalización cerrada"
                            WHEN a.estado = 7 THEN "Legalización finalizada"
                            WHEN a.estado = 8 THEN "Legalización rechazada"       
                            END AS estado,
                            ad.files AS adjunto
                            FROM anticipos a
                            INNER JOIN attacheds ad
                            ON ad.id_relation = a.id
                            INNER JOIN users us
                            ON ad.next_user_id=?
                            INNER JOIN users usn
                            ON a.id_user =usn.id
                            LEFT JOIN suppliers p
                            ON p.id=a.proveedor
                            WHERE ad.name_module= ? AND
                                  a.estado = ?
                            GROUP BY a.id',[$user->id,$function_name[0]->name,1]);

   $count= DB::SELECT('SELECT count(a.id) AS count 
                        FROM anticipos a
                               INNER JOIN users u
                               ON u.id=a.id_user
                        WHERE a.estado = ?',[1]);
    return view('anticipos/pagar',['modules' => $modules,'user' => $user,'anticipos'=>$anticipos,'count'=>$count]);


  }



  public function pagado(Request $request){

    $user = Auth::user();

    if ($user) {
    $application = new Application();
    $modules = $application->getModules($user->id,4);

    $function_name=DB::SELECT('SELECT name AS name FROM functions WHERE id=?',[24]);



    $anticipos=DB::SELECT('SELECT
                                 a.id AS id,
                                 a.id_user AS id_user, 
                                 a.fecha_pago AS fecha_pago,
                                 a.valor_anticipo AS valor_anticipo,
                                 a.forma_pago AS forma_pago,
                                 a.concepto AS concepto,
                                us.name AS gestionando,
                                usn.name AS name,
                                usn.email AS email,
                                p.name AS proveedor,
                            CASE
                            WHEN a.estado = 0 THEN "En proceso..."
                            WHEN a.estado = 1 THEN "Aprobado"
                            WHEN a.estado = 2 THEN "Pagado"
                            WHEN a.estado = 3 THEN "Rechazado" 
                            WHEN a.estado = 4 THEN "Proceso legalización"
                            WHEN a.estado = 5 THEN "Legalización aprobada"
                            WHEN a.estado = 6 THEN "Legalización cerrada"
                            WHEN a.estado = 7 THEN "Legalización finalizada"
                            WHEN a.estado = 8 THEN "Legalización rechazada"       
                            END AS estado,
                            ad.files AS adjunto
                            FROM anticipos a
                            INNER JOIN attacheds ad
                            ON ad.id_relation = a.id
                            INNER JOIN users us
                            ON ad.next_user_id=?
                            INNER JOIN users usn
                            ON a.id_user =usn.id
                            LEFT JOIN suppliers p
                            ON p.id=a.proveedor
                            WHERE ad.name_module= ? AND
                                  a.estado = ? AND
                                  a.id= ?
                            GROUP BY a.id',[$user->id,$function_name[0]->name,1,$request->id]);


    $count= DB::SELECT('SELECT count(a.id) AS count 
                        FROM anticipos a
                               INNER JOIN users u
                               ON u.id=a.id_user
                        WHERE a.estado = ?',[1]);
    $array = json_decode(json_encode($anticipos), true);


       $data_anticipo=DB::SELECT('SELECT a.empresa AS empresa,
                                         a.fecha_pago AS fecha_pago,
                                         a.valor_anticipo AS valor_anticipo,
                                         a.forma_pago AS forma_pago,
                                         a.concepto AS concepto,
                                         u.name AS name
                                         FROM anticipos a
                                         INNER JOIN users u
                                         ON u.id=a.id_user
                                         WHERE a.id=?',[$request->id]);

        //$assignmentuser = $leader_name[0]->name;
        $Type = 'anticipopago';


        //var_dump($array[0]['email']);
        $MailSend = $array[0]['email'];
        
        //$CaseNumber =$Case[0]->id;
        //$MailSend= $array[0]['email'];
       // var_dump($array[0]['name']);

        $request->session()->put('assignmentuser', $array[0]['name']);
        
        $data=[$array[0]['name'],$Type,$request->id,$array[0]['name'],$data_anticipo[0]->empresa,$data_anticipo[0]->fecha_pago,$data_anticipo[0]->valor_anticipo,$data_anticipo[0]->forma_pago,$data_anticipo[0]->concepto,$data_anticipo[0]->name,$array[0]['id_user']];



        if ($MailSend != NULL) {
          Mail::to($MailSend)->send(new SendMail($data));
        }

        $update=DB::UPDATE('UPDATE anticipos
                    SET estado = 2
                    WHERE id=?',[$request->id]);
        $guardado_datos_log=DB::INSERT("INSERT INTO anticipos_log(user_id,next_user_id,id_document,type_document, created_at) VALUES (?,?,?,?,?)",[$user->id,$array[0]['id_user'],$request->id,$function_name[0]->name,date('Y-m-d')]);



    $anticipos=DB::SELECT('SELECT
                                 a.id AS id,
                                 a.id_user AS id_user, 
                                 a.fecha_pago AS fecha_pago,
                                 a.valor_anticipo AS valor_anticipo,
                                 a.forma_pago AS forma_pago,
                                 a.concepto AS concepto,
                                us.name AS gestionando,
                                usn.name AS name,
                                usn.email AS email,
                                p.name AS proveedor,
                            CASE
                            WHEN a.estado = 0 THEN "En proceso..."
                            WHEN a.estado = 1 THEN "Aprobado"
                            WHEN a.estado = 2 THEN "Pagado"
                            WHEN a.estado = 3 THEN "Rechazado" 
                            WHEN a.estado = 4 THEN "Proceso legalización"
                            WHEN a.estado = 5 THEN "Legalización aprobada"
                            WHEN a.estado = 6 THEN "Legalización cerrada"
                            WHEN a.estado = 7 THEN "Legalización finalizada"
                            WHEN a.estado = 8 THEN "Legalización rechazada"        
                            END AS estado,
                            ad.files AS adjunto
                            FROM anticipos a
                            INNER JOIN attacheds ad
                            ON ad.id_relation = a.id
                            INNER JOIN users us
                            ON ad.next_user_id=?
                            INNER JOIN users usn
                            ON a.id_user =usn.id
                            LEFT JOIN suppliers p
                            ON p.id=a.proveedor
                            WHERE ad.name_module= ? AND
                                  a.estado = ?
                            GROUP BY a.id',[$user->id,$function_name[0]->name,1]);


       return view('anticipos/pagar',['modules' => $modules,'user' => $user,'anticipos'=>$anticipos,'count'=>$count]);     
    }else{
    $user = 2101;
    $application = new Application();
    $modules = $application->getModules($user,4);

    $function_name=DB::SELECT('SELECT name AS name FROM functions WHERE id=?',[24]);



    $anticipos=DB::SELECT('SELECT
                                 a.id AS id,
                                 a.id_user AS id_user, 
                                 a.fecha_pago AS fecha_pago,
                                 a.valor_anticipo AS valor_anticipo,
                                 a.forma_pago AS forma_pago,
                                 a.concepto AS concepto,
                                us.name AS gestionando,
                                usn.name AS name,
                                usn.email AS email,
                                p.name AS proveedor,
                            CASE
                            WHEN a.estado = 0 THEN "En proceso..."
                            WHEN a.estado = 1 THEN "Aprobado"
                            WHEN a.estado = 2 THEN "Pagado"
                            WHEN a.estado = 3 THEN "Rechazado" 
                            WHEN a.estado = 4 THEN "Proceso legalización"
                            WHEN a.estado = 5 THEN "Legalización aprobada"
                            WHEN a.estado = 6 THEN "Legalización cerrada"
                            WHEN a.estado = 7 THEN "Legalización finalizada"
                            WHEN a.estado = 8 THEN "Legalización rechazada"       
                            END AS estado,
                            ad.files AS adjunto
                            FROM anticipos a
                            INNER JOIN attacheds ad
                            ON ad.id_relation = a.id
                            INNER JOIN users us
                            ON ad.next_user_id=?
                            INNER JOIN users usn
                            ON a.id_user =usn.id
                            LEFT JOIN suppliers p
                            ON p.id=a.proveedor
                            WHERE ad.name_module= ? AND
                                  a.estado = ?
                            GROUP BY a.id',[$user,$function_name[0]->name,1]);
    $count= DB::SELECT('SELECT count(a.id) AS count 
                        FROM anticipos a
                               INNER JOIN users u
                               ON u.id=a.id_user
                        WHERE a.estado = ?',[1]);
    $array = json_decode(json_encode($anticipos), true);


       $data_anticipo=DB::SELECT('SELECT a.empresa AS empresa,
                                         a.fecha_pago AS fecha_pago,
                                         a.valor_anticipo AS valor_anticipo,
                                         a.forma_pago AS forma_pago,
                                         a.concepto AS concepto,
                                         u.name AS name
                                         FROM anticipos a
                                         INNER JOIN users u
                                         ON u.id=a.id_user
                                         WHERE a.id=?',[$request->id]);

        //$assignmentuser = $leader_name[0]->name;
        $Type = 'anticipopago';


        //var_dump($array[0]['email']);
        $MailSend = $array[0]['email'];
        
        //$CaseNumber =$Case[0]->id;
        //$MailSend= $array[0]['email'];
       // var_dump($array[0]['name']);

        $request->session()->put('assignmentuser', $array[0]['name']);
        
        $data=[$array[0]['name'],$Type,$request->id,$array[0]['name'],$data_anticipo[0]->empresa,$data_anticipo[0]->fecha_pago,$data_anticipo[0]->valor_anticipo,$data_anticipo[0]->forma_pago,$data_anticipo[0]->concepto,$data_anticipo[0]->name,$array[0]['id_user']];



        if ($MailSend != NULL) {
          Mail::to($MailSend)->send(new SendMail($data));
        }

        $update=DB::UPDATE('UPDATE anticipos
                    SET estado = 2
                    WHERE id=?',[$request->id]);



    $anticipos=DB::SELECT('SELECT
                                 a.id AS id,
                                 a.id_user AS id_user, 
                                 a.fecha_pago AS fecha_pago,
                                 a.valor_anticipo AS valor_anticipo,
                                 a.forma_pago AS forma_pago,
                                 a.concepto AS concepto,
                                us.name AS gestionando,
                                usn.name AS name,
                                usn.email AS email,
                                p.name AS proveedor,
                            CASE
                            WHEN a.estado = 0 THEN "En proceso..."
                            WHEN a.estado = 1 THEN "Aprobado"
                            WHEN a.estado = 2 THEN "Pagado"
                            WHEN a.estado = 3 THEN "Rechazado" 
                            WHEN a.estado = 4 THEN "Proceso legalización"
                            WHEN a.estado = 5 THEN "Legalización aprobada"
                            WHEN a.estado = 6 THEN "Legalización cerrada"
                            WHEN a.estado = 7 THEN "Legalización finalizada"
                            WHEN a.estado = 8 THEN "Legalización rechazada"        
                            END AS estado,
                            ad.files AS adjunto
                            FROM anticipos a
                            INNER JOIN attacheds ad
                            ON ad.id_relation = a.id
                            INNER JOIN users us
                            ON ad.next_user_id=?
                            INNER JOIN users usn
                            ON a.id_user =usn.id
                            LEFT JOIN suppliers p
                            ON p.id=a.proveedor
                            WHERE ad.name_module= ? AND
                                  a.estado = ?
                            GROUP BY a.id',[$user,$function_name[0]->name,1]);


       return view('anticipos/pagar',['modules' => $modules,'user' => $user,'anticipos'=>$anticipos,'count'=>$count]);
  }
    

  }




    public function pagadocorreo(Request $request){

    $user = $request->id_user_proceso;
    $id_anticipo=$request->id;
    $application = new Application();
    $modules = $application->getModules($user,4);

    $function_name=DB::SELECT('SELECT name AS name FROM functions WHERE id=?',[24]);



    $anticipos=DB::SELECT('SELECT
                                 a.id AS id,
                                 a.id_user AS id_user, 
                                 a.fecha_pago AS fecha_pago,
                                 a.valor_anticipo AS valor_anticipo,
                                 a.forma_pago AS forma_pago,
                                 a.concepto AS concepto,
                                us.name AS gestionando,
                                usn.name AS name,
                                usn.email AS email,
                                p.name AS proveedor,
                            CASE
                            WHEN a.estado = 0 THEN "En proceso..."
                            WHEN a.estado = 1 THEN "Aprobado"
                            WHEN a.estado = 2 THEN "Pagado"
                            WHEN a.estado = 3 THEN "Rechazado" 
                            WHEN a.estado = 4 THEN "Proceso legalización"
                            WHEN a.estado = 5 THEN "Legalización aprobada"
                            WHEN a.estado = 6 THEN "Legalización cerrada"
                            WHEN a.estado = 7 THEN "Legalización finalizada"
                            WHEN a.estado = 8 THEN "Legalización rechazada"       
                            END AS estado,
                            ad.files AS adjunto
                            FROM anticipos a
                            INNER JOIN attacheds ad
                            ON ad.id_relation = a.id
                            INNER JOIN users us
                            ON ad.next_user_id=?
                            INNER JOIN users usn
                            ON a.id_user =usn.id
                            LEFT JOIN suppliers p
                            ON p.id=a.proveedor
                            WHERE ad.name_module= ? AND
                                  a.estado = ? AND
                                  a. id = ?
                            GROUP BY a.id',[$user,$function_name[0]->name,1,$request->id]);
    $count= DB::SELECT('SELECT count(a.id) AS count 
                        FROM anticipos a
                               INNER JOIN users u
                               ON u.id=a.id_user
                        WHERE a.estado = ?',[1]);
    $array = json_decode(json_encode($anticipos), true);


       $data_anticipo=DB::SELECT('SELECT a.empresa AS empresa,
                                         a.fecha_pago AS fecha_pago,
                                         a.valor_anticipo AS valor_anticipo,
                                         a.forma_pago AS forma_pago,
                                         a.concepto AS concepto,
                                         u.name AS name
                                         FROM anticipos a
                                         INNER JOIN users u
                                         ON u.id=a.id_user
                                         WHERE a.id=?',[$request->id]);

        //$assignmentuser = $leader_name[0]->name;
        $Type = 'anticipopago';


        //var_dump($array[0]['email']);
        $MailSend = $array[0]['email'];
        
        //$CaseNumber =$Case[0]->id;
        //$MailSend= $array[0]['email'];
       // var_dump($array[0]['name']);

        $request->session()->put('assignmentuser', $array[0]['name']);
        
        $data=[$array[0]['name'],$Type,$request->id,$array[0]['name'],$data_anticipo[0]->empresa,$data_anticipo[0]->fecha_pago,$data_anticipo[0]->valor_anticipo,$data_anticipo[0]->forma_pago,$data_anticipo[0]->concepto,$data_anticipo[0]->name];



        if ($MailSend != NULL) {
          Mail::to($MailSend)->send(new SendMail($data));
        }

        $update=DB::UPDATE('UPDATE anticipos
                    SET estado = 2
                    WHERE id=?',[$request->id]);



    $anticipos=DB::SELECT('SELECT
                                 a.id AS id,
                                 a.id_user AS id_user, 
                                 a.fecha_pago AS fecha_pago,
                                 a.valor_anticipo AS valor_anticipo,
                                 a.forma_pago AS forma_pago,
                                 a.concepto AS concepto,
                                us.name AS gestionando,
                                usn.name AS name,
                                usn.email AS email,
                                p.name AS proveedor,
                            CASE
                            WHEN a.estado = 0 THEN "En proceso..."
                            WHEN a.estado = 1 THEN "Aprobado"
                            WHEN a.estado = 2 THEN "Pagado"
                            WHEN a.estado = 3 THEN "Rechazado" 
                            WHEN a.estado = 4 THEN "Proceso legalización"
                            WHEN a.estado = 5 THEN "Legalización aprobada"
                            WHEN a.estado = 6 THEN "Legalización cerrada"
                            WHEN a.estado = 7 THEN "Legalización finalizada"
                            WHEN a.estado = 8 THEN "Legalización rechazada"        
                            END AS estado,
                            ad.files AS adjunto
                            FROM anticipos a
                            INNER JOIN attacheds ad
                            ON ad.id_relation = a.id
                            INNER JOIN users us
                            ON ad.next_user_id=?
                            INNER JOIN users usn
                            ON a.id_user =usn.id
                            LEFT JOIN suppliers p
                            ON p.id=a.proveedor
                            WHERE ad.name_module= ? AND
                                  a.estado = ?
                            GROUP BY a.id',[0,$function_name[0]->name,1]);


       return view('anticipos/pagar',['modules' => $modules,'user' => $user,'anticipos'=>$anticipos,'count'=>$count]);
    

  }


  public function legalizacion(Request $request){

    $user = Auth::user();
    $application = new Application();
    $modules = $application->getModules($user->id,4);

    $function_name=DB::SELECT('SELECT name AS name FROM functions WHERE id=?',[24]);


    $anticipos=DB::SELECT('SELECT
                                 a.id AS id,
                                 a.id_user AS id_user, 
                                 a.fecha_pago AS fecha_pago,
                                 a.valor_anticipo AS valor_anticipo,
                                 a.forma_pago AS forma_pago,
                                 a.concepto AS concepto,
                                us.name AS gestionando,
                                usn.name AS name,
                            CASE
                            WHEN a.estado = 0 THEN "En proceso..."
                            WHEN a.estado = 1 THEN "Aprobado"
                            WHEN a.estado = 2 THEN "Pagado"
                            WHEN a.estado = 3 THEN "Rechazado" 
                            WHEN a.estado = 4 THEN "Proceso legalización"
                            WHEN a.estado = 5 THEN "Legalización aprobada"
                            WHEN a.estado = 6 THEN "Legalización cerrada"
                            WHEN a.estado = 7 THEN "Legalización finalizada"
                            WHEN a.estado = 8 THEN "Legalización rechazada"        
                            END AS estado,
                            ad.files AS adjunto
                            FROM anticipos a
                            INNER JOIN attacheds ad
                            ON ad.id_relation = a.id
                            INNER JOIN users us
                            ON ad.id_user=?
                            INNER JOIN users usn
                            ON a.id_user =usn.id
                            WHERE ad.name_module= ? AND 
                                  (a.estado = ? OR a.estado=?)
                            GROUP BY a.id',[$user->id,$function_name[0]->name,2,8]);
    $count= DB::SELECT('SELECT count(a.id) AS count 
                        FROM anticipos a
                               INNER JOIN users u
                               ON u.id=a.id_user
                        WHERE a.estado = ?',[2]);
    return view('anticipos/legalizacion',['modules' => $modules,'user' => $user,'anticipos'=>$anticipos,'count'=>$count]);
    

  }



  public function legalizar_final(Request $request){


    $user = Auth::user();
    $application = new Application();
    $modules = $application->getModules($user->id,4);
    $costCenters = CostCenter::where('active','=',1)
                       ->orderby('name','asc')->get();
    $id_documento=$request->id;
    $cuentas = DB::SELECT('SELECT id AS id,
                           Cuenta AS cuenta
                           FROM cuentas_cecos');

    $empresa = DB::SELECT('SELECT empresa AS empresa
                           FROM anticipos
                           WHERE id=?',[$request->id]);


    switch ($empresa[0]->empresa) {
      case 'PEREZ Y CARDONA S.A.S':
       $costCenters = CostCenter::where('active','=',1)
                       ->orderby('name','asc')->get();

       $costCenters = DB::SELECT('SELECT
                                  id AS id, 
                                  code AS code,
                                  name AS name
                                  FROM cost_centers
                                  WHERE SUBSTRING(LTRIM(RTRIM(code)),1,1) = 1');
        break;

      case 'M.P GALAGRO S.A.S':
       $costCenters = DB::SELECT('SELECT
                                  id AS id, 
                                  code AS code,
                                  name AS name
                                  FROM cost_centers
                                  WHERE SUBSTRING(LTRIM(RTRIM(code)),1,1) = 2');
        break;
      default:
       $costCenters = DB::SELECT('SELECT
                                  id AS id, 
                                  code AS code,
                                  name AS name
                                  FROM cost_centers
                                  WHERE SUBSTRING(LTRIM(RTRIM(code)),1,1) = 3');
        break;
    }


   $directores=DB::SELECT("SELECT id AS id,
                                  name AS name,
                                  profile_name AS profile
                               FROM users
                               WHERE ((SUBSTRING(LTRIM(RTRIM(profile_name)),1,8)=? 
                               OR    SUBSTRING(LTRIM(RTRIM(profile_name)),1,9)=?) 
                               OR (id) = ?
                               OR (id) = ?
                               OR (id) = ?
                               OR (id) = ?)
                               AND active = ?",['DIRECTOR','DIRECTORA',6,239,226,275,1]);




    return view('anticipos/legalizar_final',['modules' => $modules,'user' => $user,'id_documento'=>$id_documento,'costCenters' => $costCenters,'cuentas'=>$cuentas,'directores'=>$directores]);
    

  }



  public function legalizacionsave(Request $request){

    $user = Auth::user();
    $application = new Application();
    $modules = $application->getModules($user->id,4);

    $input = $request->all();

      
      $cantidadadjuntos=intval($request->countfieldsadd);
      $cantidadcampos=intval($request->countfields);
      $function_name=DB::SELECT('SELECT name AS name FROM functions WHERE id=?',[27]);

      $leader_id=DB::SELECT('SELECT leader_id AS leader_id FROM users
                             WHERE id=?',[$user->id]);

      $file = $request->file('file1');

      if ($file != NULL) 
      {
         for ($i=1; $i <=$cantidadadjuntos; $i++) {
          $file = $request->file('file'.$i);
                $ext = $file->getClientOriginalExtension();
                $nombre = Str::random(6).".".$ext;
                \Storage::disk('facturas')->put($nombre,  \File::get($file));
            $guardado_datos=DB::INSERT("INSERT INTO attacheds(files,id_user,next_user_id,id_relation,id_function,name_module,created_at) VALUES (?,?,?,?,?,?,?)",[$nombre,$user->id,$request->id_director,$request->invoice_id,27,$function_name[0]->name,date('Y-m-d')]);
         }
          $guardado_datos_log=DB::INSERT("INSERT INTO anticipos_log(user_id,next_user_id,id_document,type_document, created_at) VALUES (?,?,?,?,?)",[$user->id,$request->id_director,$request->invoice_id,$function_name[0]->name,date('Y-m-d')]);
       }else{
            $guardado_datos=DB::INSERT("INSERT INTO attacheds(files,id_user,id_relation,id_function,name_module, created_at) VALUES (?,?,?,?,?,?,?)",['N/A',$user->id,$request->id_director,$request->invoice_id,27,$function_name[0]->name,date('Y-m-d')]);
          $guardado_datos_log=DB::INSERT("INSERT INTO anticipos_log(user_id,next_user_id,id_document,type_document, created_at) VALUES (?,?,?,?,?)",[$user->id,$request->id_director,$request->invoice_id,$function_name[0]->name,date('Y-m-d')]);
       }


        for ($j=1; $j <=$cantidadcampos; $j++) {
            $guardado_datos=DB::INSERT("INSERT INTO distributions_legalizacion(anticipo_id,cost_center_id,cuenta,value, created_at,updated_at,concept) VALUES (?,?,?,?,?,?,?)",[$request->invoice_id,$input['coce'.$j],$input['cuenta'.$j],$input['value'.$j],date('Y-m-d'),date('Y-m-d'),$input['concepto_anticipo']]);
         }

      $leader_name= DB::SELECT('SELECT first_name AS name, email AS email FROM users WHERE id=?',[$request->id_director]);


    $anticipos=DB::SELECT('SELECT
                                 a.id AS id,
                                 a.id_user AS id_user, 
                                 a.fecha_pago AS fecha_pago,
                                 a.valor_anticipo AS valor_anticipo,
                                 a.forma_pago AS forma_pago,
                                 a.concepto AS concepto,
                                us.name AS gestionando,
                                usn.name AS name,
                                usn.email AS email,
                            CASE
                            WHEN a.estado = 0 THEN "En proceso..."
                            WHEN a.estado = 1 THEN "Aprobado"
                            WHEN a.estado = 2 THEN "Pagado"
                            WHEN a.estado = 3 THEN "Rechazado" 
                            WHEN a.estado = 4 THEN "Proceso legalización"
                            WHEN a.estado = 5 THEN "Legalización aprobada"
                            WHEN a.estado = 6 THEN "Legalización cerrada"
                            WHEN a.estado = 7 THEN "Legalización finalizada"
                            WHEN a.estado = 8 THEN "Legalización rechazada"         
                            END AS estado,
                            ad.files AS adjunto
                            FROM anticipos a
                            INNER JOIN attacheds ad
                            ON ad.id_relation = a.id
                            INNER JOIN users us
                            ON ad.id_user=?
                            INNER JOIN users usn
                            ON a.id_user =usn.id
                            WHERE ad.name_module= ? AND 
                                  (a.estado = ? OR a.estado=?)
                            GROUP BY a.id',[$user->id,$function_name[0]->name,2,8]);
    $count= DB::SELECT('SELECT count(a.id) AS count 
                        FROM anticipos a
                               INNER JOIN users u
                               ON u.id=a.id_user
                        WHERE a.estado = ?',[2]);
    $array = json_decode(json_encode($anticipos), true);

    $update=DB::UPDATE('UPDATE anticipos
                        SET estado = 4
                        WHERE id=?',[$request->invoice_id]);






   $function_name2=DB::SELECT('SELECT name AS name FROM functions WHERE id=?',[24]);
    $anticipos=DB::SELECT('SELECT
                                 a.id AS id,
                                 a.id_user AS id_user, 
                                 a.fecha_pago AS fecha_pago,
                                 a.valor_anticipo AS valor_anticipo,
                                 a.forma_pago AS forma_pago,
                                 a.concepto AS concepto,
                                us.name AS gestionando,
                                usn.name AS name,
                            CASE
                            WHEN a.estado = 0 THEN "En proceso..."
                            WHEN a.estado = 1 THEN "Aprobado"
                            WHEN a.estado = 2 THEN "Pagado"
                            WHEN a.estado = 3 THEN "Rechazado" 
                            WHEN a.estado = 4 THEN "Proceso legalización"
                            WHEN a.estado = 5 THEN "Legalización aprobada"
                            WHEN a.estado = 6 THEN "Legalización cerrada"
                            WHEN a.estado = 7 THEN "Legalización finalizada"
                            WHEN a.estado = 8 THEN "Legalización rechazada"        
                            END AS estado,
                            ad.files AS adjunto
                            FROM anticipos a
                            INNER JOIN attacheds ad
                            ON ad.id_relation = a.id
                            INNER JOIN users us
                            ON ad.id_user=?
                            INNER JOIN users usn
                            ON a.id_user =usn.id
                            WHERE ad.name_module= ? AND 
                                  (a.estado = ? OR a.estado=?)
                            GROUP BY a.id',[$user->id,$function_name2[0]->name,2,8]);

   $data_anticipo=DB::SELECT('SELECT a.empresa AS empresa,
                                   a.fecha_pago AS fecha_pago,
                                   a.valor_anticipo AS valor_anticipo,
                                   a.forma_pago AS forma_pago,
                                   a.concepto AS concepto,
                                   u.name AS name
                                   FROM anticipos a
                                   INNER JOIN users u
                                   ON u.id=a.id_user
                                   WHERE a.id=?',[$request->invoice_id]);

  //$assignmentuser = $leader_name[0]->name;
  $Type = 'legalizacion';


  //var_dump($array[0]['email']);
  $MailSend = $leader_name[0]->email;
  
  //$CaseNumber =$Case[0]->id;
  //$MailSend= $array[0]['email'];
 // var_dump($array[0]['name']);

  $request->session()->put('assignmentuser', $leader_name[0]->name);
  
  // Envia info por correo: "Solicitud de gestón de legalización"
  $data = [
    $array[0]['name'],
    $Type,
    $request->invoice_id,
    $array[0]['name'],
    $data_anticipo[0]->empresa,
    $data_anticipo[0]->fecha_pago,
    $data_anticipo[0]->valor_anticipo,
    $data_anticipo[0]->forma_pago,
    $data_anticipo[0]->concepto,
    $data_anticipo[0]->name,
    $request->id_director, // $leader_id[0]->leader_id
  ];


        if ($MailSend != NULL) {
          Mail::to($MailSend)->send(new SendMail($data));
        }




    header("Location: https://flora.tierragro.com/anticipos/legalizacion",true,303);  
    exit();  

  //  return view('anticipos/legalizacion',['modules' => $modules,'user' => $user,'anticipos'=>$anticipos,'count'=>$count]);
    

  }



  public function legalizaciongestion(Request $request){

    $user = Auth::user();
    $application = new Application();
    $modules = $application->getModules($user->id,4);
    $function_name=DB::SELECT('SELECT name AS name FROM functions WHERE id=?',[27]);

    $anticipos=DB::SELECT('SELECT
                                 a.id AS id,
                                 a.id_user AS id_user,
                                 ad.next_user_id,
                                 usn.cedula AS cedula,
                                 usnu.profile_name AS cargo_aprobador, 
                                 a.fecha_pago AS fecha_pago,
                                 a.valor_anticipo AS valor_anticipo,
                                 a.forma_pago AS forma_pago,
                                 a.concepto AS concepto,
                                 l.concept AS conceptolegalizacion,
                                us.name AS gestionando,
                                usn.name AS name,
                            CASE
                            WHEN a.estado = 0 THEN "En proceso..."
                            WHEN a.estado = 1 THEN "Aprobado"
                            WHEN a.estado = 2 THEN "Pagado"
                            WHEN a.estado = 3 THEN "Rechazado" 
                            WHEN a.estado = 4 THEN "Proceso legalización"
                            WHEN a.estado = 5 THEN "Legalización aprobada"
                            WHEN a.estado = 6 THEN "Legalización cerrada"
                            WHEN a.estado = 7 THEN "Legalización finalizada"
                            WHEN a.estado = 8 THEN "Legalización rechazada"      
                            END AS estado,
                            ad.files AS adjunto
                            FROM anticipos a
                            INNER JOIN attacheds ad
                            ON ad.id_relation = a.id
                            INNER JOIN users us
                            ON ad.next_user_id=?
                            INNER JOIN users usn
                            ON a.id_user =usn.id
                            INNER JOIN distributions_legalizacion l
                            ON l.anticipo_id = a.id
                            LEFT JOIN anticipos_log al
                            ON al.id_document= a.id 
                            LEFT JOIN users usnu
                            ON usnu.id = al.next_user_id
                            WHERE ad.name_module= ? AND
                                  ad.id =(SELECT MAX(id) FROM attacheds ad WHERE ad.id_relation = a.id) AND
                                  al.id = (SELECT MAX(id) FROM anticipos_log al WHERE al.id_document = a.id) AND 
                                  (a.estado = ? OR a.estado= ?)
                            GROUP BY a.id',[$user->id,$function_name[0]->name,4,5]);


    $count= DB::SELECT('SELECT count(a.id) AS count 
                        FROM anticipos a
                               INNER JOIN users u
                               ON u.id=a.id_user
                        WHERE a.estado = ?',[ 4 ]);

    // Bandera si esta logado: IMPORTANTE!!!!
    $user_id_ = -1;
    
    return view('anticipos/legalizacionesgestion',[
      'modules' => $modules,
      'user' => $user,
      'anticipos' => $anticipos,
      'count' => $count,
      'user_id_' => $user_id_,
    ]);
  }



  public function legalizaciongestioncorreo(Request $request){

    $user = $request->id_user;
    $application = new Application();
    $modules = $application->getModules($user,4);
    $function_name=DB::SELECT('SELECT name AS name FROM functions WHERE id=?',[27]);

    $anticipos=DB::SELECT('SELECT
                                 a.id AS id,
                                 a.id_user AS id_user, 
                                 ad.next_user_id,
                                 a.fecha_pago AS fecha_pago,
                                 a.valor_anticipo AS valor_anticipo,
                                 a.forma_pago AS forma_pago,
                                 a.concepto AS concepto,
                                 l.concept AS conceptolegalizacion,
                                us.name AS gestionando,
                                usn.name AS name,
                            CASE
                            WHEN a.estado = 0 THEN "En proceso..."
                            WHEN a.estado = 1 THEN "Aprobado"
                            WHEN a.estado = 2 THEN "Pagado"
                            WHEN a.estado = 3 THEN "Rechazado" 
                            WHEN a.estado = 4 THEN "Proceso legalización"
                            WHEN a.estado = 5 THEN "Legalización aprobada"
                            WHEN a.estado = 6 THEN "Legalización cerrada"
                            WHEN a.estado = 7 THEN "Legalización finalizada"
                            WHEN a.estado = 8 THEN "Legalización rechazada"      
                            END AS estado,
                            ad.files AS adjunto
                            FROM anticipos a
                            INNER JOIN attacheds ad
                            ON ad.id_relation = a.id
                            INNER JOIN users us
                            ON ad.next_user_id=?
                            INNER JOIN users usn
                            ON a.id_user =usn.id
                            INNER JOIN distributions_legalizacion l
                            ON l.anticipo_id = a.id
                            WHERE ad.name_module= ? AND
                                  ad.id =(SELECT MAX(id) FROM attacheds ad WHERE ad.id_relation = a.id) AND 
                                  (a.estado = ? OR a.estado= ?)
                            GROUP BY a.id',[$user,$function_name[0]->name,4,5]);
    $count= DB::SELECT('SELECT count(a.id) AS count 
                        FROM anticipos a
                               INNER JOIN users u
                               ON u.id=a.id_user
                        WHERE a.estado = ?',[4]);


    // Bandera No esta logado: IMPORTANTE!!!
    $user_id_ = $request->id_user;
    return view('anticipos/legalizacionesgestion',[
      'modules' => $modules,
      'user' => $user,
      'anticipos' => $anticipos,
      'count' => $count,
      'user_id_' => $user_id_,
    ]);
  }


  public function gestionaraceptarlegalizacion(Request $request){

    $id_function_cierre_legalizacion = config('app.global')['id_function_cierre_legalizacion'];
    $rows_permissions = DB::SELECT('SELECT id_user FROM permission  
      WHERE function_id = ' . $id_function_cierre_legalizacion );

    $validador_contable = $rows_permissions[ 0 ]->id_user;


    $user = Auth::user();
    if ( $user ) {
      $application = new Application();
      $modules = $application->getModules($user->id,4);

      $function_name=DB::SELECT('SELECT name AS name FROM functions WHERE id=?',[
        config('app.global')['id_function_legalizacion_acticipos']
      ]);

      $leader_id=DB::SELECT('SELECT leader_id AS leader_id FROM users
          WHERE id=?',[
            $user->id
          ]);

      $ultimo_registro=DB::SELECT('SELECT max(id) AS id FROM attacheds
          WHERE id_relation=? AND
          name_module=?',[
            $request->id,
            $function_name[0]->name
          ]);

      $anticipos1 = DB::SELECT('SELECT
            a.id AS id,
            a.id_user AS id_user,
            usn.cedula AS cedula,
            usnu.name AS ultimo_aprobador,
            usnu.profile_name AS cargo_aprobador,  
            a.fecha_pago AS fecha_pago,
            a.valor_anticipo AS valor_anticipo,
            a.forma_pago AS forma_pago,
            a.concepto AS concepto,
            l.concept AS concept,
            us.name AS gestionando,
            usn.name AS name,
            usn.email AS email,
            CASE
              WHEN a.estado = 0 THEN "En proceso..."
              WHEN a.estado = 1 THEN "Aprobado"
              WHEN a.estado = 2 THEN "Pagado"
              WHEN a.estado = 3 THEN "Rechazado" 
              WHEN a.estado = 4 THEN "Proceso legalización"
              WHEN a.estado = 5 THEN "Legalización aprobada"
              WHEN a.estado = 6 THEN "Legalización cerrada"
              WHEN a.estado = 7 THEN "Legalización finalizada"
              WHEN a.estado = 8 THEN "Legalización rechazada"        
            END AS estado,
            ad.files AS adjunto
          FROM anticipos a
          INNER JOIN attacheds ad
          ON ad.id_relation = a.id
          INNER JOIN users us
          ON ad.next_user_id=?
          INNER JOIN users usn
          ON a.id_user =usn.id
          INNER JOIN distributions_legalizacion l
          ON l.anticipo_id=a.id
          LEFT JOIN anticipos_log lo
          ON lo.id_document= a.id 
          LEFT JOIN users usnu
          ON usnu.id = lo.next_user_id
          WHERE ad.name_module= ? AND
          (a.estado = ? OR a.estado = ?)
          GROUP BY a.id',[
            $user->id,
            $function_name[0]->name,
            4,
            5
          ]);



      $valor_anticipo_real=str_replace('.', '', $anticipos1[0]->valor_anticipo);

      $cargo_usuario=DB::SELECT("SELECT profile_name AS profile
          FROM users
          WHERE id=?",[
            $user->id
          ]);

      $pos = strpos($cargo_usuario[0]->profile," ");
      $cargo_final =substr($cargo_usuario[0]->profile,0,$pos);

      $next_user_legalizacion='';

      if ((intval($valor_anticipo_real > 5000000)) && (($cargo_final == 'DIRECTOR') || ($cargo_final == 'DIRECTORA'))) 
      {
        $update=DB::UPDATE('UPDATE attacheds
                          SET next_user_id = ?
                          WHERE id=?',[$leader_id[0]->leader_id,$ultimo_registro[0]->id]);

        $leader_name= DB::SELECT('SELECT first_name AS name, email AS email FROM users WHERE id=?',[$leader_id[0]->leader_id]);

        $guardado_datos_log=DB::INSERT("INSERT INTO anticipos_log(user_id,next_user_id,id_document,type_document, created_at) VALUES (?,?,?,?,?)",[
          $user->id,
          $leader_id[0]->leader_id,
          $request->id, // $ultimo_registro[0]->id
          $function_name[0]->name,
          date('Y-m-d')
        ]);

        $next_user_legalizacion = $leader_id[0]->leader_id;

      }
      elseif((intval($valor_anticipo_real >= 5000000)) && (($cargo_final == 'GERENTE')))
      {
        $update=DB::UPDATE('UPDATE attacheds
          SET next_user_id = ?
          WHERE id=?',[
            $validador_contable,
            $ultimo_registro[0]->id
          ]);

        $leader_name= DB::SELECT('SELECT first_name AS name, email AS email FROM users WHERE id=?',[
          $validador_contable
        ]);

        $guardado_datos_log=DB::INSERT("INSERT INTO anticipos_log(user_id,next_user_id,id_document,type_document, created_at) VALUES (?,?,?,?,?)",[
          $user->id,
          $validador_contable,
          $request->id, // $ultimo_registro[0]->id
          $function_name[0]->name,
          date('Y-m-d')
        ]);
        $next_user_legalizacion = $validador_contable;


      }
      elseif((intval($valor_anticipo_real < 5000000)) && (($cargo_final == 'GERENTE')))
      {
        $update=DB::UPDATE('UPDATE attacheds
            SET next_user_id = ?
            WHERE id=?',[
              $validador_contable,
              $ultimo_registro[0]->id
            ]);

        $leader_name= DB::SELECT('SELECT first_name AS name, email AS email FROM users WHERE id=?',[
          $validador_contable
        ]);

        $guardado_datos_log=DB::INSERT("INSERT INTO anticipos_log(user_id,next_user_id,id_document,type_document, created_at) VALUES (?,?,?,?,?)",[
          $user->id,
          $validador_contable,
          $request->id, // $ultimo_registro[0]->id,
          $function_name[0]->name,
          date('Y-m-d')
        ]);

        $next_user_legalizacion = $validador_contable;

      }
      elseif((intval($valor_anticipo_real < 5000000)) && (($cargo_final == 'DIRECTOR') || ($cargo_final == 'DIRECTORA')))
      {
        $update=DB::UPDATE('UPDATE attacheds
            SET next_user_id = ?
            WHERE id=?',[
              $validador_contable,
              $ultimo_registro[0]->id
            ]);

        $leader_name= DB::SELECT('SELECT first_name AS name, email AS email FROM users WHERE id=?',[
          $validador_contable
        ]);

        $guardado_datos_log=DB::INSERT("INSERT INTO anticipos_log(user_id,next_user_id,id_document,type_document, created_at) VALUES (?,?,?,?,?)", [
          $user->id,
          $validador_contable,
          $request->id, // $ultimo_registro[0]->id
          $function_name[0]->name,
          date('Y-m-d')
        ]);

        $next_user_legalizacion = $validador_contable;

      }
      else{

        $update = DB::UPDATE('UPDATE attacheds
            SET next_user_id = ?
            WHERE id=?',[
              $leader_id[0]->leader_id,
              $ultimo_registro[0]->id
            ]);

        $leader_name = DB::SELECT('SELECT first_name AS name, email AS email FROM users WHERE id=?',[
          $leader_id[0]->leader_id
        ]);

        $guardado_datos_log=DB::INSERT("INSERT INTO anticipos_log(user_id,next_user_id,id_document,type_document, created_at) VALUES (?,?,?,?,?)",[
          $user->id,
          $validador_contable,
          $request->id, // $ultimo_registro[0]->id
          $function_name[0]->name,
          date('Y-m-d'),
        ]);

        $next_user_legalizacion = $validador_contable;

      }


      $update = DB::UPDATE('UPDATE anticipos
        SET estado = ?
        WHERE id=?',[
          config('app.global')['estados_anticipos']['Legalización aprobada'],//5
          $request->id
        ]);


      $anticipos = DB::SELECT('SELECT
          a.id AS id,
          a.id_user AS id_user,
          ad.next_user_id,
          usn.cedula AS cedula,
          usnu.profile_name AS cargo_aprobador, 
          a.fecha_pago AS fecha_pago,
          a.valor_anticipo AS valor_anticipo,
          a.forma_pago AS forma_pago,
          a.concepto AS concepto,
          l.concept AS conceptolegalizacion,
          us.name AS gestionando,
          usn.name AS name,
          CASE
            WHEN a.estado = 0 THEN "En proceso..."
            WHEN a.estado = 1 THEN "Aprobado"
            WHEN a.estado = 2 THEN "Pagado"
            WHEN a.estado = 3 THEN "Rechazado" 
            WHEN a.estado = 4 THEN "Proceso legalización"
            WHEN a.estado = 5 THEN "Legalización aprobada"
            WHEN a.estado = 6 THEN "Legalización cerrada"
            WHEN a.estado = 7 THEN "Legalización finalizada"
            WHEN a.estado = 8 THEN "Legalización rechazada"      
          END AS estado,
          ad.files AS adjunto
        FROM anticipos a
        INNER JOIN attacheds ad
        ON ad.id_relation = a.id
        INNER JOIN users us
        ON ad.next_user_id=?
        INNER JOIN users usn
        ON a.id_user =usn.id
        INNER JOIN distributions_legalizacion l
        ON l.anticipo_id = a.id
        LEFT JOIN anticipos_log al
        ON al.id_document= a.id 
        LEFT JOIN users usnu
        ON usnu.id = al.next_user_id
        WHERE ad.name_module= ? AND
        ad.id =(SELECT MAX(id) FROM attacheds ad WHERE ad.id_relation = a.id) AND
        al.id = (SELECT MAX(id) FROM anticipos_log al WHERE al.id_document = a.id) AND 
        (a.estado = ? OR a.estado= ?)
        GROUP BY a.id',[
          $user->id,
          $function_name[0]->name,
          config('app.global')['estados_anticipos']['Proceso legalización'],//4
          config('app.global')['estados_anticipos']['Legalización aprobada'],//5
        ]);


      $count= DB::SELECT('SELECT count(a.id) AS count 
        FROM anticipos a
        INNER JOIN users u
        ON u.id=a.id_user
        WHERE a.estado = ?',[
          config('app.global')['estados_anticipos']['Proceso legalización'],//4
        ]);

      $data_anticipo=DB::SELECT('SELECT a.empresa AS empresa,
          a.fecha_pago AS fecha_pago,
          a.valor_anticipo AS valor_anticipo,
          a.forma_pago AS forma_pago,
          a.concepto AS concepto,
          u.name AS name
        FROM anticipos a
        INNER JOIN users u
        ON u.id=a.id_user
        WHERE a.id=?',[
          $request->id
        ]);

      //$assignmentuser = $leader_name[0]->name;
      $Type = 'legalizacion';

      //var_dump($array[0]['email']);
      $MailSend = $leader_name[0]->email;
    
      //$CaseNumber =$Case[0]->id;
      //$MailSend= $array[0]['email'];
      //var_dump($array[0]['name']);

      $request->session()->put('assignmentuser', $leader_name[0]->name);
  
      $data = [
        $leader_name[0]->name,
        $Type,
        $request->id,
        $leader_name[0]->name,
        $data_anticipo[0]->empresa,
        $data_anticipo[0]->fecha_pago,
        $data_anticipo[0]->valor_anticipo,
        $data_anticipo[0]->forma_pago,
        $data_anticipo[0]->concepto,
        $data_anticipo[0]->name,
        $next_user_legalizacion,
      ];

      if ($MailSend != NULL) {
        Mail::to($MailSend)->send(new SendMail($data));
      }


      // Bandera si esta logado: iMPORTANTE !!!!
      $user_id_ = -1;
      return view('anticipos/legalizacionesgestion',[
        'modules' => $modules,
        'user' => $user,
        'anticipos' => $anticipos,
        'count' => $count,
        'user_id_' => $user_id_,
      ]);
      
    }
    else{

      $user = $request->id_user;
      $application = new Application();
      $modules = $application->getModules($user,4);

      $function_name=DB::SELECT('SELECT name AS name FROM functions WHERE id=?',[
        config('app.global')['id_function_legalizacion_acticipos'], //27
      ]);

      $leader_id = DB::SELECT('SELECT leader_id AS leader_id FROM users
          WHERE id=?',[$user]);

      $ultimo_registro = DB::SELECT('SELECT max(id) AS id FROM attacheds
        WHERE id_relation=? AND
        name_module=?',[
          $request->id,
          $function_name[0]->name
        ]);



      $anticipos1=DB::SELECT('SELECT
          a.id AS id,
          a.id_user AS id_user, 
          a.fecha_pago AS fecha_pago,
          a.valor_anticipo AS valor_anticipo,
          a.forma_pago AS forma_pago,
          a.concepto AS concepto,
          l.concept AS conceptolegalizacion,
          us.name AS gestionando,
          usn.name AS name,
          usn.email AS email,
          CASE
            WHEN a.estado = 0 THEN "En proceso..."
            WHEN a.estado = 1 THEN "Aprobado"
            WHEN a.estado = 2 THEN "Pagado"
            WHEN a.estado = 3 THEN "Rechazado" 
            WHEN a.estado = 4 THEN "Proceso legalización"
            WHEN a.estado = 5 THEN "Legalización aprobada"
            WHEN a.estado = 6 THEN "Legalización cerrada"
            WHEN a.estado = 7 THEN "Legalización finalizada"
            WHEN a.estado = 8 THEN "Legalización rechazada"        
          END AS estado,
        ad.files AS adjunto
        FROM anticipos a
        INNER JOIN attacheds ad
        ON ad.id_relation = a.id
        INNER JOIN users us
        ON ad.next_user_id=?
        INNER JOIN users usn
        ON a.id_user =usn.id
        INNER JOIN distributions_legalizacion l
        ON l.anticipo_id=a.id
        WHERE ad.name_module= ? AND 
        (a.estado = ? OR a.estado = ?)
        GROUP BY a.id',[
          $user,
          $function_name[0]->name,
          config('app.global')['estados_anticipos']['Proceso legalización'],//4
          config('app.global')['estados_anticipos']['Legalización aprobada'],//5
        ]);

      $valor_anticipo_real=str_replace('.', '', $anticipos1[0]->valor_anticipo);

      $cargo_usuario=DB::SELECT("SELECT profile_name AS profile
        FROM users
        WHERE id=?",[
          $user 
        ]);

      $pos = strpos($cargo_usuario[0]->profile," ");
      $cargo_final =substr($cargo_usuario[0]->profile,0,$pos);

      
      $next_user_legalizacion=''; // ..1

      if ((intval($valor_anticipo_real > 5000000)) && (($cargo_final == 'DIRECTOR') || ($cargo_final == 'DIRECTORA'))) {
        $update=DB::UPDATE('UPDATE attacheds
        SET next_user_id = ?
        WHERE id=?',[
          $leader_id[0]->leader_id,
          $ultimo_registro[0]->id
        ]);
        $leader_name= DB::SELECT('SELECT first_name AS name, email AS email FROM users WHERE id=?',[
          $leader_id[0]->leader_id
        ]);

        $next_user_legalizacion = $leader_id[0]->leader_id; // ..2
      }
      elseif((intval($valor_anticipo_real >= 5000000)) && (($cargo_final == 'GERENTE'))){
          $update=DB::UPDATE('UPDATE attacheds
            SET next_user_id = ?
            WHERE id=?',[
              $validador_contable,
              $ultimo_registro[0]->id,
            ]);
          $leader_name= DB::SELECT('SELECT first_name AS name, email AS email FROM users WHERE id=?',[
            $validador_contable
          ]);

          $next_user_legalizacion = $validador_contable; // ..3
      }
      elseif((intval($valor_anticipo_real < 5000000)) && (($cargo_final == 'GERENTE'))){
        $update=DB::UPDATE('UPDATE attacheds
          SET next_user_id = ?
          WHERE id=?',[
            $validador_contable,
            $ultimo_registro[0]->id
          ]);
        $leader_name= DB::SELECT('SELECT first_name AS name, email AS email FROM users WHERE id=?',[
          $validador_contable
        ]);

        $next_user_legalizacion = $validador_contable; // ..4
      }
      elseif((intval($valor_anticipo_real < 5000000)) && (($cargo_final == 'DIRECTOR') || ($cargo_final == 'DIRECTORA'))){
        $update=DB::UPDATE('UPDATE attacheds
            SET next_user_id = ?
            WHERE id=?',[
              $validador_contable,
              $ultimo_registro[0]->id
            ]);
        $leader_name= DB::SELECT('SELECT first_name AS name, email AS email FROM users WHERE id=?',[
          $validador_contable
        ]);

        $next_user_legalizacion = $validador_contable; // ..5
      }
      else{
        $update = DB::UPDATE('UPDATE attacheds
            SET next_user_id = ?
            WHERE id=?',[
              $leader_id[0]->leader_id,
              $ultimo_registro[0]->id
            ]);
        $leader_name= DB::SELECT('SELECT first_name AS name, email AS email FROM users WHERE id=?',[
          $leader_id[0]->leader_id
        ]);

        $next_user_legalizacion = $validador_contable; // ..6
      }


      $update=DB::UPDATE('UPDATE anticipos
          SET estado = ?
          WHERE id=?',[
            config('app.global')['estados_anticipos']['Legalización aprobada'],//5,
            $request->id
          ]);


      $anticipos = DB::SELECT('SELECT
            a.id AS id,
            a.id_user AS id_user,
            ad.next_user_id,
            a.fecha_pago AS fecha_pago,
            a.valor_anticipo AS valor_anticipo,
            a.forma_pago AS forma_pago,
            a.concepto AS concepto,
            l.concept AS conceptolegalizacion,
            us.name AS gestionando,
            usn.name AS name,
            CASE
              WHEN a.estado = 0 THEN "En proceso..."
              WHEN a.estado = 1 THEN "Aprobado"
              WHEN a.estado = 2 THEN "Pagado"
              WHEN a.estado = 3 THEN "Rechazado" 
              WHEN a.estado = 4 THEN "Proceso legalización"
              WHEN a.estado = 5 THEN "Legalización aprobada"
              WHEN a.estado = 6 THEN "Legalización cerrada"
              WHEN a.estado = 7 THEN "Legalización finalizada"
              WHEN a.estado = 8 THEN "Legalización rechazada"       
            END AS estado,
            ad.files AS adjunto
          FROM anticipos a
          INNER JOIN attacheds ad
          ON ad.id_relation = a.id
          INNER JOIN users us
          ON ad.next_user_id=?
          INNER JOIN users usn
          ON a.id_user =usn.id
          INNER JOIN distributions_legalizacion l
          ON l.anticipo_id=a.id
          WHERE ad.name_module= ? AND 
          ad.id =(SELECT MAX(id) FROM attacheds ad WHERE ad.id_relation = a.id) AND
          (a.estado = ? OR a.estado= ?)
          GROUP BY a.id',[
            $user,
            $function_name[0]->name,
            config('app.global')['estados_anticipos']['Proceso legalización'],//4
            config('app.global')['estados_anticipos']['Legalización aprobada'],//5
          ]);

      $count= DB::SELECT('SELECT count(a.id) AS count 
          FROM anticipos a
          INNER JOIN users u
          ON u.id=a.id_user
          WHERE a.estado = ?',[
            config('app.global')['estados_anticipos']['Proceso legalización'],//4
          ]);

      $data_anticipo=DB::SELECT('SELECT a.empresa AS empresa,
          a.fecha_pago AS fecha_pago,
          a.valor_anticipo AS valor_anticipo,
          a.forma_pago AS forma_pago,
          a.concepto AS concepto,
          u.name AS name
          FROM anticipos a
          INNER JOIN users u
          ON u.id=a.id_user
          WHERE a.id=?',[
            $request->id
          ]);

      //$assignmentuser = $leader_name[0]->name;
      $Type = 'legalizacion';


      //var_dump($array[0]['email']);
      $MailSend = $leader_name[0]->email;

      //$CaseNumber =$Case[0]->id;
      //$MailSend= $array[0]['email'];
      // var_dump($array[0]['name']);

      $request->session()->put('assignmentuser', $leader_name[0]->name);
  
      $data = [
        $leader_name[0]->name,
        $Type,
        $request->id,
        $leader_name[0]->name,
        $data_anticipo[0]->empresa,
        $data_anticipo[0]->fecha_pago,
        $data_anticipo[0]->valor_anticipo,
        $data_anticipo[0]->forma_pago,
        $data_anticipo[0]->concepto,
        $data_anticipo[0]->name,
        $next_user_legalizacion, // ..6
      ];

      if ($MailSend != NULL) {
        Mail::to($MailSend)->send(new SendMail($data));
      }


      // Bandera No esta logado: IMPORTANTE!!!
      $user_id_ = $request->id_user;
      return view('anticipos/legalizacionesgestion',[
        'modules' => $modules,
        'user' => $user,
        'anticipos' => $anticipos,
        'count' => $count,
        'user_id_' => $user_id_,
      ]);
    }
  }



public function legalizacioncerrar(Request $request){

    $user = Auth::user();
    $application = new Application();
    $modules = $application->getModules($user->id,4);
    $function_name=DB::SELECT('SELECT name AS name FROM functions WHERE id=?',[27]);

    $leader_id=DB::SELECT('SELECT leader_id AS leader_id FROM users
                             WHERE id=?',[$user->id]);

    $ultimo_registro=DB::SELECT('SELECT max(id) AS id FROM attacheds
                                 WHERE id_relation=? AND
                                       name_module=?',[$request->id,$function_name[0]->name]);



    $anticipos1=DB::SELECT('SELECT
                                 a.id AS id,
                                 a.id_user AS id_user, 
                                 a.fecha_pago AS fecha_pago,
                                 a.valor_anticipo AS valor_anticipo,
                                 a.forma_pago AS forma_pago,
                                 a.concepto AS concepto,
                                 d.concept AS conceptolegalizacion,
                                us.name AS gestionando,
                                usn.name AS name,
                            CASE
                            WHEN a.estado = 0 THEN "En proceso..."
                            WHEN a.estado = 1 THEN "Aprobado"
                            WHEN a.estado = 2 THEN "Pagado"
                            WHEN a.estado = 3 THEN "Rechazado" 
                            WHEN a.estado = 4 THEN "Proceso legalización"
                            WHEN a.estado = 5 THEN "Legalización aprobada"
                            WHEN a.estado = 6 THEN "Legalización cerrada"
                            WHEN a.estado = 7 THEN "Legalización finalizada"
                            WHEN a.estado = 8 THEN "Legalización rechazada"       
                            END AS estado,
                            ad.files AS adjunto
                            FROM anticipos a
                            INNER JOIN attacheds ad
                            ON ad.id_relation = a.id
                            INNER JOIN users us
                            ON ad.next_user_id=?
                            INNER JOIN users usn
                            ON a.id_user =usn.id
                            INNER JOIN distributions_legalizacion d
                            ON d.anticipo_id=a.id
                            WHERE ad.name_module= ? AND 
                                  (a.estado = ? OR a.estado = ?)
                            GROUP BY a.id',[$user->id,$function_name[0]->name,4,5]);


   /* if (($anticipos1[0]->valor_anticipo > 5000000) && ($user->id == '26')) {
       $update=DB::UPDATE('UPDATE attacheds
                        SET next_user_id = ?
                        WHERE id=?',[$leader_id[0]->leader_id,$ultimo_registro[0]->id]);
    }elseif(($anticipos1[0]->valor_anticipo < 5000000) && ($user->id == '26')){
        $update=DB::UPDATE('UPDATE attacheds
                        SET next_user_id = ?
                        WHERE id=?',['215',$ultimo_registro[0]->id]);
    }else{
      $update=DB::UPDATE('UPDATE attacheds
                        SET next_user_id = ?
                        WHERE id=?',[$leader_id[0]->leader_id,$ultimo_registro[0]->id]);
    }*/


    /*$update=DB::UPDATE('UPDATE anticipos
                        SET estado = 6
                        WHERE id=?',[$ultimo_registro[0]->id]);*/


    $anticipos=DB::SELECT('SELECT
                                 a.id AS id,
                                 a.id_user AS id_user, 
                                 a.fecha_pago AS fecha_pago,
                                 a.valor_anticipo AS valor_anticipo,
                                 a.forma_pago AS forma_pago,
                                 a.concepto AS concepto,
                                 l.concept AS conceptolegalizacion,
                                us.name AS gestionando,
                                usn.name AS name,
                            CASE
                            WHEN a.estado = 0 THEN "En proceso..."
                            WHEN a.estado = 1 THEN "Aprobado"
                            WHEN a.estado = 2 THEN "Pagado"
                            WHEN a.estado = 3 THEN "Rechazado" 
                            WHEN a.estado = 4 THEN "Proceso legalización"
                            WHEN a.estado = 5 THEN "Legalización aprobada"
                            WHEN a.estado = 6 THEN "Legalización cerrada"
                            WHEN a.estado = 7 THEN "Legalización finalizada"
                            WHEN a.estado = 8 THEN "Legalización rechazada"       
                            END AS estado,
                            ad.files AS adjunto
                            FROM anticipos a
                            INNER JOIN attacheds ad
                            ON ad.id_relation = a.id
                            INNER JOIN users us
                            ON ad.next_user_id=?
                            INNER JOIN users usn
                            ON a.id_user =usn.id
                            INNER JOIN distributions_legalizacion l
                            ON l.anticipo_id = a.id
                            WHERE ad.name_module= ? AND 
                                  (a.estado = ? OR a.estado= ?) AND 
                                  ad.id =(SELECT MAX(id) FROM attacheds ad WHERE ad.id_relation = a.id)
                            GROUP BY a.id',[$user->id,$function_name[0]->name,4,5]);
    $count= DB::SELECT('SELECT count(a.id) AS count 
                        FROM anticipos a
                               INNER JOIN users u
                               ON u.id=a.id_user
                        WHERE (a.estado = ? OR a.estado=?)',[4,5]);
    return view('anticipos/legalizacion_cerrar',['modules' => $modules,'user' => $user,'anticipos'=>$anticipos,'count'=>$count]);
    

  }






  public function gestionarcerrarlegalizacion(Request $request){

    $user = Auth::user();
    $application = new Application();
    $modules = $application->getModules($user->id,4);
    $function_name=DB::SELECT('SELECT name AS name FROM functions WHERE id=?',[27]);

    $leader_id=DB::SELECT('SELECT leader_id AS leader_id FROM users
                             WHERE id=?',[$user->id]);

    $ultimo_registro=DB::SELECT('SELECT max(id) AS id FROM attacheds
                                 WHERE id_relation=? AND
                                       name_module=?',[$request->id,$function_name[0]->name]);



    $anticipos1=DB::SELECT('SELECT
                                 a.id AS id,
                                 a.id_user AS id_user, 
                                 a.fecha_pago AS fecha_pago,
                                 a.valor_anticipo AS valor_anticipo,
                                 a.forma_pago AS forma_pago,
                                 a.concepto AS concepto,
                                us.name AS gestionando,
                                usn.name AS name,
                            CASE
                            WHEN a.estado = 0 THEN "En proceso..."
                            WHEN a.estado = 1 THEN "Aprobado"
                            WHEN a.estado = 2 THEN "Pagado"
                            WHEN a.estado = 3 THEN "Rechazado" 
                            WHEN a.estado = 4 THEN "Proceso legalización"
                            WHEN a.estado = 5 THEN "Legalización aprobada"
                            WHEN a.estado = 6 THEN "Legalización cerrada"
                            WHEN a.estado = 7 THEN "Legalización finalizada"
                            WHEN a.estado = 8 THEN "Legalización rechazada"        
                            END AS estado,
                            ad.files AS adjunto
                            FROM anticipos a
                            INNER JOIN attacheds ad
                            ON ad.id_relation = a.id
                            INNER JOIN users us
                            ON ad.next_user_id=?
                            INNER JOIN users usn
                            ON a.id_user =usn.id
                            WHERE ad.name_module= ? AND 
                                  (a.estado = ? OR a.estado = ?)
                            GROUP BY a.id',[$user->id,$function_name[0]->name,4,5]);



   /* if (($anticipos1[0]->valor_anticipo > 5000000) && ($user->id == '26')) {
       $update=DB::UPDATE('UPDATE attacheds
                        SET next_user_id = ?
                        WHERE id=?',[$leader_id[0]->leader_id,$ultimo_registro[0]->id]);
    }elseif(($anticipos1[0]->valor_anticipo < 5000000) && ($user->id == '26')){
        $update=DB::UPDATE('UPDATE attacheds
                        SET next_user_id = ?
                        WHERE id=?',['215',$ultimo_registro[0]->id]);
    }else{
      $update=DB::UPDATE('UPDATE attacheds
                        SET next_user_id = ?
                        WHERE id=?',[$leader_id[0]->leader_id,$ultimo_registro[0]->id]);
    }*/

    $update=DB::UPDATE('UPDATE anticipos
                        SET estado = 6
                        WHERE id=?',[$request->id]);


    $update=DB::UPDATE('UPDATE attacheds
                        SET next_user_id = ?
                        WHERE id=?',[$user->id,$ultimo_registro[0]->id]);


   // $leader_name= DB::SELECT('SELECT first_name AS name, email AS email FROM users WHERE id=?',['189']);


    $anticipos=DB::SELECT('SELECT
                                 a.id AS id,
                                 a.id_user AS id_user, 
                                 a.fecha_pago AS fecha_pago,
                                 a.valor_anticipo AS valor_anticipo,
                                 a.forma_pago AS forma_pago,
                                 a.concepto AS concepto,
                                 l.concept AS conceptolegalizacion,
                                us.name AS gestionando,
                                usn.name AS name,
                            CASE
                            WHEN a.estado = 0 THEN "En proceso..."
                            WHEN a.estado = 1 THEN "Aprobado"
                            WHEN a.estado = 2 THEN "Pagado"
                            WHEN a.estado = 3 THEN "Rechazado" 
                            WHEN a.estado = 4 THEN "Proceso legalización"
                            WHEN a.estado = 5 THEN "Legalización aprobada"
                            WHEN a.estado = 6 THEN "Legalización cerrada"
                            WHEN a.estado = 7 THEN "Legalización finalizada"
                            WHEN a.estado = 8 THEN "Legalización rechazada"       
                            END AS estado,
                            ad.files AS adjunto
                            FROM anticipos a
                            INNER JOIN attacheds ad
                            ON ad.id_relation = a.id
                            INNER JOIN users us
                            ON ad.next_user_id=?
                            INNER JOIN users usn
                            ON a.id_user =usn.id
                            INNER JOIN distributions_legalizacion l
                            ON l.anticipo_id = a.id
                            WHERE ad.name_module= ? AND 
                                  (a.estado = ? OR a.estado= ?) AND 
                                  ad.id =(SELECT MAX(id) FROM attacheds ad WHERE ad.id_relation = a.id)
                            GROUP BY a.id',[$user->id,$function_name[0]->name,4,5]);
    $count= DB::SELECT('SELECT count(a.id) AS count 
                        FROM anticipos a
                               INNER JOIN users u
                               ON u.id=a.id_user
                        WHERE a.estado = ?',[4]);
  
    $data_anticipo=DB::SELECT('SELECT a.empresa AS empresa,
                                   a.fecha_pago AS fecha_pago,
                                   a.valor_anticipo AS valor_anticipo,
                                   a.forma_pago AS forma_pago,
                                   a.concepto AS concepto,
                                   u.name AS name
                                   FROM anticipos a
                                   INNER JOIN users u
                                   ON u.id=a.id_user
                                   WHERE a.id=?',[$request->id]);

  //$assignmentuser = $leader_name[0]->name;
  /*$Type = 'legalizacion';


  //var_dump($array[0]['email']);
  $MailSend = $leader_name[0]->email;
  
  //$CaseNumber =$Case[0]->id;
  //$MailSend= $array[0]['email'];
 // var_dump($array[0]['name']);

  $request->session()->put('assignmentuser', $leader_name[0]->name);
  
  $data=[$leader_name[0]->name,$Type,$request->id,$leader_name[0]->name,$data_anticipo[0]->empresa,$data_anticipo[0]->fecha_pago,$data_anticipo[0]->valor_anticipo,$data_anticipo[0]->forma_pago,$data_anticipo[0]->concepto,$data_anticipo[0]->name];



  Mail::to($MailSend)->send(new SendMail($data));*/




    return view('anticipos/legalizacion_cerrar',['modules' => $modules,'user' => $user,'anticipos'=>$anticipos,'count'=>$count]);
    

  }




  public function gestionarfinalizarlegalizacion(Request $request){

    $user = Auth::user();
    $application = new Application();
    $modules = $application->getModules($user->id,4);
    $function_name=DB::SELECT('SELECT name AS name FROM functions WHERE id=?',[27]);

    $leader_id=DB::SELECT('SELECT leader_id AS leader_id FROM users
                             WHERE id=?',[$user->id]);

    $ultimo_registro=DB::SELECT('SELECT max(id) AS id FROM attacheds
                                 WHERE id_relation=? AND
                                       name_module=?',[$request->id,$function_name[0]->name]);



    $anticipos1=DB::SELECT('SELECT
                                 a.id AS id,
                                 a.id_user AS id_user, 
                                 a.fecha_pago AS fecha_pago,
                                 a.valor_anticipo AS valor_anticipo,
                                 a.forma_pago AS forma_pago,
                                 a.concepto AS concepto,
                                 l.concept AS conceptolegalizacion,
                                us.name AS gestionando,
                                usn.name AS name,
                            CASE
                            WHEN a.estado = 0 THEN "En proceso..."
                            WHEN a.estado = 1 THEN "Aprobado"
                            WHEN a.estado = 2 THEN "Pagado"
                            WHEN a.estado = 3 THEN "Rechazado" 
                            WHEN a.estado = 4 THEN "Proceso legalización"
                            WHEN a.estado = 5 THEN "Legalización aprobada"
                            WHEN a.estado = 6 THEN "Legalización cerrada"
                            WHEN a.estado = 7 THEN "Legalización finalizada"
                            WHEN a.estado = 8 THEN "Legalización rechazada"      
                            END AS estado,
                            ad.files AS adjunto
                            FROM anticipos a
                            INNER JOIN attacheds ad
                            ON ad.id_relation = a.id
                            INNER JOIN users us
                            ON ad.next_user_id=?
                            INNER JOIN users usn
                            ON a.id_user =usn.id
                            INNER JOIN distributions_legalizacion l
                            ON l.anticipo_id=a.id
                            WHERE ad.name_module= ? AND 
                                  (a.estado = ?)
                            GROUP BY a.id',[$user->id,$function_name[0]->name,6]);
    $count= DB::SELECT('SELECT count(a.id) AS count 
                        FROM anticipos a
                               INNER JOIN users u
                               ON u.id=a.id_user
                        WHERE a.estado = ?',[4]);
   return view('anticipos/legalizacion_finalizar',['modules' => $modules,'user' => $user,'anticipos'=>$anticipos1,'count'=>$count]);
    

  }





public function gestionarfinalizacionlegalizacion(Request $request){

    $user = Auth::user();
    $application = new Application();
    $modules = $application->getModules($user->id,4);

    // Obtener el ID del User con la opción: Pagar anticipos
    $id_function_pagar_anticipos = config('app.global')['id_function_pagar_anticipos'];
    $rows_permissions2 = DB::SELECT('SELECT id_user FROM permission  
      WHERE function_id = ' . $id_function_pagar_anticipos );
      

    $validador_tesoreria = $rows_permissions2[ 0 ]->id_user;


    $function_name=DB::SELECT('SELECT name AS name FROM functions WHERE id=?',[
      config('app.global')['id_function_legalizacion_acticipos'], //27
    ]);


    $leader_id=DB::SELECT('SELECT leader_id AS leader_id FROM users
                             WHERE id=?',[$user->id]);

    $ultimo_registro=DB::SELECT('SELECT max(id) AS id FROM attacheds
                                 WHERE id_relation=? AND
                                       name_module=?',[$request->id,$function_name[0]->name]);

    $update=DB::UPDATE('UPDATE anticipos
                        SET estado = 7
                        WHERE id=?',[$request->id]);


    $update=DB::UPDATE('UPDATE attacheds
        SET next_user_id = ?
        WHERE id=?',[
          $validador_tesoreria,
          $ultimo_registro[0]->id
        ]);


    $anticipos1=DB::SELECT('SELECT
                                 a.id AS id,
                                 a.id_user AS id_user, 
                                 a.fecha_pago AS fecha_pago,
                                 a.valor_anticipo AS valor_anticipo,
                                 a.forma_pago AS forma_pago,
                                 a.concepto AS concepto,
                                 l.concept AS conceptolegalizacion,
                                us.name AS gestionando,
                                usn.name AS name,
                            CASE
                            WHEN a.estado = 0 THEN "En proceso..."
                            WHEN a.estado = 1 THEN "Aprobado"
                            WHEN a.estado = 2 THEN "Pagado"
                            WHEN a.estado = 3 THEN "Rechazado" 
                            WHEN a.estado = 4 THEN "Proceso legalización"
                            WHEN a.estado = 5 THEN "Legalización aprobada"
                            WHEN a.estado = 6 THEN "Legalización cerrada"
                            WHEN a.estado = 7 THEN "Legalización finalizada"
                            WHEN a.estado = 8 THEN "Legalización rechazada"        
                            END AS estado,
                            ad.files AS adjunto
                            FROM anticipos a
                            INNER JOIN attacheds ad
                            ON ad.id_relation = a.id
                            INNER JOIN users us
                            ON ad.next_user_id=?
                            INNER JOIN users usn
                            ON a.id_user =usn.id
                            INNER JOIN distributions_legalizacion l
                            ON l.anticipo_id=a.id
                            WHERE ad.name_module= ? AND 
                                  (a.estado = ?)
                            GROUP BY a.id',[$user->id,$function_name[0]->name,6]);
    $count= DB::SELECT('SELECT count(a.id) AS count 
                        FROM anticipos a
                               INNER JOIN users u
                               ON u.id=a.id_user
                        WHERE a.estado = ?',[4]);
   return view('anticipos/legalizacion_finalizar',['modules' => $modules,'user' => $user,'anticipos'=>$anticipos1,'count'=>$count]);
    

  }
  


  public function gastos(Request $request){

    $user = Auth::user();
    $application = new Application();
    $modules = $application->getModules($user->id,4);
    $costCenters = CostCenter::where('active','=',1)
                       ->orderby('name','asc')->get();
    $id_documento=$request->id;
    $cuentas = DB::SELECT('SELECT id AS id,
                           Cuenta AS cuenta
                           FROM cuentas_cecos');

   $directores=DB::SELECT("SELECT id AS id,
                                  name AS name,
                                  profile_name AS profile
                               FROM users
                               WHERE ((SUBSTRING(LTRIM(RTRIM(profile_name)),1,8)=? 
                               OR    SUBSTRING(LTRIM(RTRIM(profile_name)),1,9)=?) 
                               OR (id) = ?
                               OR (id) = ?
                               OR (id) = ?)
                               AND active = ?",['DIRECTOR','DIRECTORA',6,239,226,1]);

    $validacion=0;


    return view('/anticipos/gastos',['modules' => $modules,'user' => $user,'cuentas'=>$cuentas,'directores'=>$directores,'validacion'=>$validacion]);
  }



  public function gastos_save(Request $request){

  $user = Auth::user();
  $application = new Application();
  $modules = $application->getModules($user->id,4);
  $valor=0;


  $input=$request->all();
  
  $cantidad=($input['countfields']);
  $cantidadadjuntos=($input['countfieldsadd']);
  $leader_id=$request->id_director;

  for ($i=1; $i <=$cantidad ; $i++) { 
    $valor =$valor + intval(preg_replace('/[@\.\;\" "]+/', '', $input['value'.$i]));
  }

  $save=DB::INSERT('INSERT INTO gastos (id_user,empresa,fecha_pago,valor_reintregro,forma_pago,concepto,estado) VALUES (?,?,?,?,?,?,?)',[$user->id,$request->empresa,$request->fecha_anticipo,$valor,$request->forma_pago,$request->concepto_anticipo,0]);
  $maxId=DB::SELECT('SELECT max(id) AS id_gasto FROM gastos');
  $function_name=DB::SELECT('SELECT name AS name FROM functions WHERE id=?',[37]);
  if ($request->file1 != NULL) 
  {
     for ($i=1; $i <=$cantidadadjuntos; $i++) {
      $file = $request->file('file'.$i);
            $ext = $file->getClientOriginalExtension();
            $nombre = Str::random(6).".".$ext;
            \Storage::disk('facturas')->put($nombre,  \File::get($file));
            $guardado_datos=DB::INSERT("INSERT INTO attacheds(id_user,next_user_id,files,id_relation,id_function,name_module, created_at) VALUES (?,?,?,?,?,?,?)",[$user->id,$leader_id,$nombre,$maxId[0]->id_gasto,37,$function_name[0]->name,date('Y-m-d')]);
           // $save=DB::INSERT('INSERT INTO gastos (id_user,empresa,fecha_pago,valor_reintregro,forma_pago,concepto,proveedor,estado) VALUES (?,?,?,?,?,?,?,?)',[$user->id,$request->empresa,$request->fecha_anticipo,$request->valor_anticipo,$request->forma_pago,$request->concepto_anticipo,'',0]);
     }
   }

   $guardado_datos_log=DB::INSERT("INSERT INTO gastos_log(user_id,next_user_id,id_document,created_at) VALUES (?,?,?,?)",[$user->id,$request->id_director,$maxId[0]->id_gasto,date('Y-m-d')]);
   $leader_name= DB::SELECT('SELECT first_name AS name, email AS email FROM users WHERE id=?',[$leader_id]);
   
   for ($j=1; $j <=$cantidad ; $j++) { 
    $distributions_gastos = DB::INSERT('INSERT INTO distributions_gastos (gasto_id,cost_center_id,cuenta,value,created_at, updated_at, active) VALUES (?,?,?,?,?,?,?)',[$maxId[0]->id_gasto, $input['coce'.$j], $input['cuenta'.$j], $input['value'.$j],date('Y-m-d'),date('Y-m-d'),1]);
  }

        $assignmentuser = $leader_name[0]->name;
        $Type = 'gastos';
        $MailSend= $leader_name[0]->email;

        $request->session()->put('assignmentuser', $leader_name[0]->name);
        
        $data=[$assignmentuser,$Type,$maxId[0]->id_gasto,$leader_name[0]->name,$request->empresa,$request->fecha_anticipo,$valor,$request->forma_pago,$request->concepto_anticipo,$request->observacion_anticipo,$user->name,$user->name];

        if ($MailSend != NULL) {
          Mail::to($MailSend)->send(new SendMail($data));
        }

        $cuentas = DB::SELECT('SELECT id AS id,
        Cuenta AS cuenta
        FROM cuentas_cecos');

        $directores=DB::SELECT("SELECT id AS id,
                      name AS name,
                      profile_name AS profile
                    FROM users
                    WHERE ((SUBSTRING(LTRIM(RTRIM(profile_name)),1,8)=? 
                    OR    SUBSTRING(LTRIM(RTRIM(profile_name)),1,9)=?) 
                    OR (id) = ?
                    OR (id) = ?
                    OR (id) = ?)
                    AND active = ?",['DIRECTOR','DIRECTORA',6,239,226,1]);

        $validacion=1;


        return view('/anticipos/gastos',['modules' => $modules,'user' => $user,'cuentas'=>$cuentas,'directores'=>$directores,'validacion'=>$validacion]);

  }



  public function costcenterlegalizacion(Request $request){
     $empresa=$request->empresa;
     $costcenters='';


     switch ($empresa) {
        case 'PEREZ Y CARDONA S.A.S':
              $costcenters= DB::SELECT("SELECT code AS code,
                                                name AS name
                                        FROM  cost_centers
                                        WHERE SUBSTRING(code, 1, 1)=?",[1]);
          break;
        case 'M.P GALAGRO S.A.S':
              $costcenters= DB::SELECT("SELECT code AS code,
                                                name AS name
                                        FROM  cost_centers
                                        WHERE SUBSTRING(code, 1, 1)=?",[2]);
          break;
        case 'SUPER AGRO S.A.S':
              $costcenters= DB::SELECT("SELECT code AS code,
                                                name AS name
                                        FROM  cost_centers
                                        WHERE SUBSTRING(code, 1, 1)=?",[4]);
          break;
     }

      echo json_encode($costcenters);
  }



  public function gastosgestion(Request $request){

    $user = Auth::user();
    $application = new Application();
    $modules = $application->getModules($user->id,4);

    $anticipos=DB::SELECT('SELECT g.id AS id,
                                  g.empresa AS company,
                                  u.name AS solicitante,
                                  g.fecha_pago AS fecha_pago,
                                  g.valor_reintregro AS valor,
                                  g.concepto AS concepto,
                              CASE
                                  when g.estado = 0 then "Radicada"
                                  when g.estado = 1 then "Aprobada"
                                  when g.estado = 2 then "Pagada"
                              END
                                AS estado
                              FROM gastos g
                              INNER JOIN gastos_log l
                              ON l.id_document= g.id
                              INNER JOIN users u
                              ON u.id= g.id_user
                              WHERE l.id = (SELECT MAX(id) FROM gastos_log l WHERE l.id_document = g.id) AND (g.estado=0 OR g.estado=1) AND
                                l.next_user_id = ?
                        GROUP BY g.id',[$user->id]);

    return view('anticipos/gastosgestion',[
      'modules' => $modules,
      'user' => $user,
      'anticipos' => $anticipos
    ]);
  }



  public function flujogastos(Request $request){
    
    $flujosgastos= DB::SELECT("SELECT u.name AS nombre1,
                                    u2.name AS nombre2,
                                    l.created_at AS fecha
                                FROM users u
                                INNER JOIN gastos_log l
                                ON l.user_id=u.id
                                INNER JOIN users u2
                                ON u2.id=l.next_user_id
                                WHERE l.id_document=?",[$request->id]);

    echo json_encode($flujosgastos);



}


public function adjuntosfilesgastos(Request $request){
    
  $adjuntosgastos= DB::SELECT("SELECT a.created_at AS fecha,
                                a.files AS file,
                                u.name AS usuario
                              FROM attacheds a
                              INNER JOIN users u
                              ON u.id=a.id_user
                              WHERE id_relation =? AND a.id_function=?",[$request->id,37]);

  echo json_encode($adjuntosgastos);



}


public function adjuntosdistribuciongastos(Request $request){
    
  $adjuntosgastos= DB::SELECT("SELECT c.name as ceco,
                                      c1.Cuenta as cuenta,
                                      d.value as valor
                              FROM cost_centers c
                              INNER JOIN distributions_gastos d
                              ON d.cost_center_id = c.code
                              INNER JOIN cuentas_cecos c1
                              ON c1.id = d.cuenta
                              WHERE d.gasto_id=?",[$request->id]);

  echo json_encode($adjuntosgastos);



}


public function aceptargastos(Request $request){
  $user = Auth::user();
  $application = new Application();
  $modules = $application->getModules($user->id,4);


  $leader_id = DB::SELECT('SELECT leader_id AS leader_id FROM users
                           WHERE id=?',[$user->id]);

  $cargo_usuario=DB::SELECT("SELECT profile_name AS profile FROM users
                               WHERE id=?",[$user->id]);

    $pos = strpos($cargo_usuario[0]->profile," ");
    $cargo_final =substr($cargo_usuario[0]->profile,0,$pos);

    $valor_anticipo=DB::SELECT('SELECT g.valor_reintregro AS valor, g.concepto AS concepto,g.fecha_pago AS fecha_pago,u.name as nombre FROM gastos g INNER JOIN users u ON u.id=g.id_user WHERE g.id=?',[$request->id]);
    $valor_anticipo_real=$valor_anticipo[0]->valor;

    $next_user_legalizacion=''; // ..1



    // Obtener el ID del User con la opción: Cierre de legalización
    $id_function_cierre_legalizacion = config('app.global')['id_function_cierre_legalizacion'];
    $id_function_pago_legalizacion = config('app.global')['id_function_pagos_legalizacion'];
    $rows_permissions = DB::SELECT('SELECT id_user FROM permission  
      WHERE function_id = ' . $id_function_cierre_legalizacion );

    $rows_permissions_pagos = DB::SELECT('SELECT id_user FROM permission  
    WHERE function_id = ' . $id_function_pago_legalizacion );

    $validador_contable = $rows_permissions[ array_rand($rows_permissions) ]->id_user;

    $validador_cartera = $rows_permissions_pagos[ array_rand($rows_permissions_pagos) ]->id_user;

    // Obtener el ID del User con la opción: Pagar anticipos
    $id_function_pagar_anticipos = config('app.global')['id_function_pagar_anticipos'];
    $rows_permissions2 = DB::SELECT('SELECT id_user FROM permission  
      WHERE function_id = ' . $id_function_pagar_anticipos );

    $validador_tesoreria = $rows_permissions2[ 0 ]->id_user;

    if ((intval($valor_anticipo_real > 5000000)) && (($cargo_final == 'DIRECTOR') || ($cargo_final == 'DIRECTORA'))) {
    $leader_name= DB::SELECT('SELECT first_name AS name, email AS email FROM users WHERE id=?',[$leader_id[0]->leader_id
    ]);
    $next_user_legalizacion = $leader_id[0]->leader_id; // ..2
    }elseif((intval($valor_anticipo_real >= 5000000)) && (($cargo_final == 'GERENTE'))){
    $leader_name= DB::SELECT('SELECT first_name AS name, email AS email FROM users WHERE id=?',[
        $validador_contable
      ]);

      $next_user_legalizacion = $validador_contable; // ..3
    }elseif((intval($valor_anticipo_real < 5000000)) && (($cargo_final == 'GERENTE'))){
    $leader_name= DB::SELECT('SELECT first_name AS name, email AS email FROM users WHERE id=?',[
      $validador_contable
    ]);
    $next_user_legalizacion = $validador_contable; // ..4
    }elseif((intval($valor_anticipo_real < 5000000)) && (($cargo_final == 'DIRECTOR') || ($cargo_final == 'DIRECTORA'))){
    $leader_name= DB::SELECT('SELECT first_name AS name, email AS email FROM users WHERE id=?',[
      $validador_contable
    ]);
    $next_user_legalizacion = $validador_contable; // ..5
    }else{
      if ($user->id == $validador_contable) {
        $next_user_legalizacion = $validador_cartera;
        $leader_name= DB::SELECT('SELECT first_name AS name, email AS email FROM users WHERE id=?',[$validador_cartera]);
      }else{
        $leader_name= DB::SELECT('SELECT first_name AS name, email AS email FROM users WHERE id=?',[$leader_id[0]->leader_id]);
        $next_user_legalizacion = $validador_contable; // ..6        
      }
    }

    $update=DB::UPDATE('UPDATE gastos
      SET estado = ?
      WHERE id=?',[1,$request->id]);

    $guardado_datos_log=DB::INSERT("INSERT INTO gastos_log(user_id,next_user_id,id_document,created_at) VALUES (?,?,?,?)",[$user->id,$next_user_legalizacion,$request->id,date('Y-m-d')]);

    $assignmentuser = $leader_name[0]->name;
    $Type = 'gastos';
    $MailSend= $leader_name[0]->email;

    $request->session()->put('assignmentuser', $leader_name[0]->name);
    
    $data=[$valor_anticipo[0]->nombre,$Type,$request->id,$leader_name[0]->name,$request->empresa,$valor_anticipo[0]->fecha_pago,$valor_anticipo_real,$request->forma_pago,$valor_anticipo[0]->concepto,$request->observacion_anticipo,$valor_anticipo[0]->nombre,$user->name];

    if ($MailSend != NULL) {
      Mail::to($MailSend)->send(new SendMail($data));
    }

    $anticipos=DB::SELECT('SELECT g.id AS id,
                                  g.empresa AS company,
                                  u.name AS solicitante,
                                  g.fecha_pago AS fecha_pago,
                                  g.valor_reintregro AS valor,
                                  g.concepto AS concepto,
                              CASE
                                  when g.estado = 0 then "Radicada"
                                  when g.estado = 1 then "Aprobada"
                                  when g.estado = 2 then "Pagada"
                              END
                                AS estado
                              FROM gastos g
                              INNER JOIN gastos_log l
                              ON l.id_document= g.id
                              INNER JOIN users u
                              ON u.id= g.id_user
                              WHERE l.id = (SELECT MAX(id) FROM gastos_log l WHERE l.id_document = g.id) AND (g.estado=0 OR g.estado=1) AND
                                l.next_user_id = ?
                        GROUP BY g.id',[$user->id]);

    return view('anticipos/gastosgestion',['modules' => $modules,'user' => $user,'anticipos' => $anticipos]);
}



public function historialgastos(Request $request){

  $user = Auth::user();
  $application = new Application();
  $modules = $application->getModules($user->id,4);

  $anticipos=DB::SELECT('SELECT g.id AS id,
                                g.empresa AS company,
                                u.name AS solicitante,
                                g.fecha_pago AS fecha_pago,
                                g.valor_reintregro AS valor,
                                g.concepto AS concepto,
                            CASE
                                when g.estado = 0 then "Radicada"
                                when g.estado = 1 then "Aprobada"
                                when g.estado = 2 then "Pagada"
                                when g.estado = 3 then "Rechazado"
                            END
                              AS estado
                            FROM gastos g
                            INNER JOIN gastos_log l
                            ON l.id_document= g.id
                            INNER JOIN users u
                            ON u.id= g.id_user
                            WHERE g.id_user = ?
                      GROUP BY g.id',[$user->id]);
              

  return view('anticipos/historialgastos',[
    'modules' => $modules,
    'user' => $user,
    'anticipos' => $anticipos
  ]);
}


public function gastospagos(Request $request){

  $user = Auth::user();
  $application = new Application();
  $modules = $application->getModules($user->id,4);

  $anticipos=DB::SELECT('SELECT g.id AS id,
                                g.empresa AS company,
                                u.name AS solicitante,
                                g.fecha_pago AS fecha_pago,
                                g.valor_reintregro AS valor,
                                g.concepto AS concepto,
                            CASE
                                when g.estado = 0 then "Radicada"
                                when g.estado = 1 then "Aprobada"
                                when g.estado = 2 then "Pagada"
                                when g.estado = 3 then "Rechazado"
                            END
                              AS estado
                            FROM gastos g
                            INNER JOIN gastos_log l
                            ON l.id_document= g.id
                            INNER JOIN users u
                            ON u.id= g.id_user
                            WHERE l.id = (SELECT MAX(id) FROM gastos_log l WHERE l.id_document = g.id) AND
                                  l.next_user_id = ? AND 
                                  g.estado NOT IN (?,?)
                      GROUP BY g.id',[$user->id,2,3]);
              

  return view('anticipos/gastospagos',[
    'modules' => $modules,
    'user' => $user,
    'anticipos' => $anticipos
  ]);
}



public function pagogasto(Request $request){

  $user = Auth::user();
  $application = new Application();
  $modules = $application->getModules($user->id,4);


  $pagogasto=DB::UPDATE('UPDATE gastos
                        SET estado = ?
                        WHERE id=?',[2,$request->id]);


$valor_anticipo=DB::SELECT('SELECT g.valor_reintregro AS valor, g.concepto AS concepto,g.fecha_pago AS fecha_pago,u.name as nombre,u.email AS email,g.motivo_rechazo AS motivo_rechazo FROM gastos g INNER JOIN users u ON u.id=g.id_user WHERE g.id=?',[$request->id]);

//$assignmentuser = $leader_name[0]->name;
$Type = 'gastospago';
$MailSend= $valor_anticipo[0]->email;

$request->session()->put('assignmentuser', $valor_anticipo[0]->nombre);

$data=[$valor_anticipo[0]->nombre,$Type,$request->invoice_id,$valor_anticipo[0]->nombre,$request->empresa,$valor_anticipo[0]->fecha_pago,$valor_anticipo[0]->valor,$request->forma_pago,$valor_anticipo[0]->concepto,$valor_anticipo[0]->motivo_rechazo,$valor_anticipo[0]->nombre,$user->name];

if ($MailSend != NULL) {
  Mail::to($MailSend)->send(new SendMail($data));
}

  $anticipos=DB::SELECT('SELECT g.id AS id,
                                g.empresa AS company,
                                u.name AS solicitante,
                                g.fecha_pago AS fecha_pago,
                                g.valor_reintregro AS valor,
                                g.concepto AS concepto,
                            CASE
                                when g.estado = 0 then "Radicada"
                                when g.estado = 1 then "Aprobada"
                                when g.estado = 2 then "Pagada"
                                when g.estado = 3 then "Rechazado"
                            END
                              AS estado
                            FROM gastos g
                            INNER JOIN gastos_log l
                            ON l.id_document= g.id
                            INNER JOIN users u
                            ON u.id= g.id_user
                            WHERE l.id = (SELECT MAX(id) FROM gastos_log l WHERE l.id_document = g.id) AND
                                  l.next_user_id = ? AND 
                                  g.estado NOT IN (?,?)
                      GROUP BY g.id',[$user->id,2,3]);
              

  return view('anticipos/gastospagos',[
    'modules' => $modules,
    'user' => $user,
    'anticipos' => $anticipos
  ]);
}



public function rechazarlegalizaciongastos(Request $request){

  $user = Auth::user();
  $application = new Application();
  $modules = $application->getModules($user->id,4);

  $rechazar_legalizacion=DB::UPDATE('UPDATE gastos
                                     SET estado = ?,
                                         motivo_rechazo = ?,
                                         id_user_rechazo = ?
                                     WHERE id=?',[3,$request->motivo_rechazo,$user->id,$request->invoice_id]);

  
$valor_anticipo=DB::SELECT('SELECT g.valor_reintregro AS valor, g.concepto AS concepto,g.fecha_pago AS fecha_pago,u.name as nombre,u.email AS email,g.motivo_rechazo AS motivo_rechazo FROM gastos g INNER JOIN users u ON u.id=g.id_user WHERE g.id=?',[$request->invoice_id]);

//$assignmentuser = $leader_name[0]->name;
$Type = 'gastosrechazo';
$MailSend= $valor_anticipo[0]->email;

$request->session()->put('assignmentuser', $valor_anticipo[0]->nombre);

$data=[$valor_anticipo[0]->nombre,$Type,$request->invoice_id,$valor_anticipo[0]->nombre,$request->empresa,$valor_anticipo[0]->fecha_pago,$valor_anticipo[0]->valor,$request->forma_pago,$valor_anticipo[0]->concepto,$valor_anticipo[0]->motivo_rechazo,$valor_anticipo[0]->nombre,$user->name];

if ($MailSend != NULL) {
  Mail::to($MailSend)->send(new SendMail($data));
}



  $anticipos=DB::SELECT('SELECT g.id AS id,
                                g.empresa AS company,
                                u.name AS solicitante,
                                g.fecha_pago AS fecha_pago,
                                g.valor_reintregro AS valor,
                                g.concepto AS concepto,
                            CASE
                                when g.estado = 0 then "Radicada"
                                when g.estado = 1 then "Aprobada"
                                when g.estado = 2 then "Pagada"
                            END
                              AS estado
                            FROM gastos g
                            INNER JOIN gastos_log l
                            ON l.id_document= g.id
                            INNER JOIN users u
                            ON u.id= g.id_user
                            WHERE l.id = (SELECT MAX(id) FROM gastos_log l WHERE l.id_document = g.id) AND (g.estado=0 OR g.estado=1) AND
                              l.next_user_id = ?
                      GROUP BY g.id',[$user->id]);

  return view('anticipos/gastosgestion',[
    'modules' => $modules,
    'user' => $user,
    'anticipos' => $anticipos
  ]);
}




}
