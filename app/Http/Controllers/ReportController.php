<?php

namespace App\Http\Controllers;

use App\User;
use App\Application;
use App\Ubication;
use App\Performance_pdi_history;
use App\Invoice;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Pagination\Paginator;



class ReportController extends Controller
{
    //
    public function find(Request $request){
		$user = Auth::user();
        $application = new Application();
        $modules = [];
       switch ($request->report) {
       	case 'reports.invoices':
		       		 $datas=DB::SELECT("SELECT i.id AS id, 
									       i.number AS number,
										   s.name AS supplier,
										   f.name AS flow,
										   i.due_date AS due,
										   i.total AS total,
										   MAX(l.next_user_id) AS user,
										   CASE
										   WHEN MAX(l.state_id) = 1 THEN 'Radicada'
										   WHEN MAX(l.state_id) = 2 THEN 'Cancelada'
										   WHEN MAX(l.state_id) = 3 THEN 'Validada'
										   WHEN MAX(l.state_id) = 4 THEN 'Aprobada'
										   WHEN MAX(l.state_id) = 5 THEN 'Rechazada'
										   WHEN MAX(l.state_id) = 6 THEN 'Finalizada'
										   END AS state
									FROM invoices i
									LEFT JOIN suppliers s
									ON s.id= i.supplier_id
									LEFT JOIN invoice_flows f
									ON f.id= i.flow_id
									LEFT JOIN invoice_logg l 
									ON l.invoice_id=i.id
									LEFT JOIN invoice_states st 
									ON st.name=l.state_id
									LEFT JOIN users u
									ON u.id=l.next_user_id
									GROUP BY i.number,s.name,f.name,i.due_date,i.total,i.id
									ORDER BY i.number DESC,i.created_at DESC");
		        $references=DB::SELECT('SELECT id AS id,
		        	                          number AS number
		        	                          FROM invoices
		        	                          ORDER BY number ASC');
		        $suppliers=DB::SELECT('SELECT id AS id,
				                          name AS name
				                          FROM suppliers
				                          ORDER BY name ASC');
		        $states=DB::SELECT('SELECT id AS id,
				                          name AS name
				                          FROM invoice_states
				                          ORDER BY id ASC');
		        return view($request->report,['modules' => $modules,'user' => $user,'datas'=>$datas,'references'=>$references,'suppliers'=>$suppliers,'states'=>$states]);
		       		break;

       	case 'reports.tickets':
			       		 /*$datas=DB::SELECT("SELECT t.id AS number,
												   t.created_at AS date,
											       h.name AS tool,
											       u.name AS user,
											       t.text AS detail,
											       us.name AS agent,
											       ts.name AS state
											  FROM tickets t
											  INNER JOIN tools h
											  ON h.id= t.tool_id
											  INNER JOIN users u
											  ON u.id= t.user_id
											  INNER JOIN users us
											  ON us.id= t.agent_id
											  INNER JOIN ticket_states ts
											  ON ts.id=t.status_id
											ORDER BY t.created_at DESC;");*/
			            //$datas= $datas->simplePaginate(15); 
			$datas = DB::table('tickets')
			        ->join('users', 'tickets.user_id', '=', 'users.id')
			        ->join('tools', 'tickets.tool_id', '=', 'tools.id')
			        ->join('users as us', 'tickets.agent_id', '=', 'us.id')
			        ->join('ticket_states', 'tickets.status_id', '=', 'ticket_states.id')
			        ->select('tickets.id AS number', 'tickets.created_at AS date', 'tools.name AS tool', 'users.name AS user', 'tickets.text AS detail', 'us.name AS agent', 'ticket_states.name AS state')
			        ->orderBy('tickets.id', 'DESC')
			        ->paginate(25);
			       	$tools=DB::SELECT('SELECT id AS id,
			       		                      name AS name
			       		               FROM tools
			       		               WHERE active=?',[1]);
			        $agents=DB::SELECT('SELECT id AS id,
			       		                      name AS name
			       		               FROM users
			       		               WHERE ubication_id=? AND
			       		                     active=?',[40,1]);
			        $states=DB::SELECT('SELECT id AS id,
			       		                      name AS name
			       		               FROM ticket_states
			       		               WHERE active=?',[1]);
			        $pagination_state=1;
			            return view($request->report,['modules' => $modules,'user' => $user,'datas'=>$datas,'tools'=>$tools,'agents'=>$agents,'states'=>$states,'pagination_state'=>$pagination_state]);
			       		break;

       	case 'performance.report':
				$user = Auth::user();
		        $application = new Application();
		        $modules = $application->getModules($user->id,1);
		        $names=array();

		        $users = User::where('active','=',1)->get();
		        $ubications = Ubication::where('active','=',1)->get();

		        $leaders=DB::SELECT('SELECT users.name AS name,
									       users.id AS id
									       FROM users
									INNER JOIN profile_role
									ON profile_role.profile_id=users.profile_id
									WHERE profile_role.role_id=?
									OR profile_role.role_id=?',[11,12]);
		       $data=DB::SELECT('SELECT YEAR(p.created_at) AS year,
		       	                        u.name AS name,
								        u.id AS id,
									    us.name AS jefe,
									    ub.name AS area
								FROM users u
								INNER JOIN users us
								ON us.id = u.leader_id
								INNER JOIN performance_pdi_histories p
								ON p.evaluated_id= u.id
								INNER JOIN ubications ub
								ON ub.id= u.ubication_id
						GROUP BY p.created_at,u.id,u.name,us.name,ub.name
						ORDER BY u.id ASC,YEAR(p.created_at) DESC');

		       $datapdiser=DB::SELECT('SELECT evaluated_id AS id,
								       average AS average,
								       objective AS objective,
								       action AS action,
								       YEAR(created_at) AS year,
								       followupdate AS followupdate
								FROM performance_pdi_histories
								WHERE dimension_id = ?
								ORDER BY evaluated_id ASC,YEAR(created_at) DESC',[1]);
		       $datapdisaber=DB::SELECT('SELECT evaluated_id AS id,
								       average AS average,
								       objective AS objective,
								       action AS action,
								       YEAR(created_at) AS year,
								       followupdate AS followupdate
								FROM performance_pdi_histories
								WHERE dimension_id = ?
								ORDER BY evaluated_id ASC,YEAR(created_at) DESC',[2]);
		       $datapdihacer=DB::SELECT('SELECT evaluated_id AS id,
								       average AS average,
								       objective AS objective,
								       action AS action,
								       YEAR(created_at) AS year,
								       followupdate AS followupdate
								FROM performance_pdi_histories
								WHERE dimension_id = ?
								ORDER BY evaluated_id ASC,YEAR(created_at) DESC',[3]);
		       $datapditotal=DB::SELECT('SELECT evaluated_id AS id,
								       average AS average,
								       objective AS objective,
								       action AS action,
								       YEAR(created_at) AS year,
								       followupdate AS followupdate
								FROM performance_pdi_histories
								WHERE dimension_id = ?
								ORDER BY evaluated_id ASC,YEAR(created_at) DESC',[4]);

			return view('performance.report',['modules' => $modules,'user' => $user,'data'=>$data,'datapdiser'=>$datapdiser,'datapdisaber'=>$datapdisaber,'datapdihacer'=>$datapdihacer,'datapditotal'=>$datapditotal,'users'=>$users,'ubications'=>$ubications,'leaders'=>$leaders]);
		       		break;

       	case 'performance.missing':
		$user = Auth::user();
        $application = new Application();
        $modules = $application->getModules($user->id,1);
        $names=array();

        $users = User::where('active','=',1)->get();
        $ubications = Ubication::where('active','=',1)->get();

        $leaders=DB::SELECT('SELECT users.name AS name,
							       users.id AS id
							       FROM users
							INNER JOIN profile_role
							ON profile_role.profile_id=users.profile_id
							WHERE profile_role.role_id=?
							OR profile_role.role_id=?',[11,12]);

        $data=DB::SELECT('SELECT t1.name AS name,
						       t1.cedula AS cedula,
						       p.name AS profile,
						       us.name AS lider,
						       u.name AS ubication
						  FROM users t1
						  LEFT JOIN ubications u
						  ON u.id=t1.ubication_id
						  LEFT JOIN profiles p
						  ON p.id= t1.profile_id
						  LEFT JOIN users us
						  ON us.id=t1.leader_id
						  LEFT JOIN performance_pdis t2
						 ON t2.evaluated_id = t1.id
						 WHERE t2.evaluated_id IS NULL');

				   	$request->session()->put('data', $data);

					return view('performance.reportmissing',['modules' => $modules,'user' => $user,'data'=>$data,'users'=>$users,'leaders'=>$leaders,'ubications'=>$ubications]);
				       		break;


       	default:
			$datas = DB::table('tickets')
			        ->join('users', 'tickets.user_id', '=', 'users.id')
			        ->join('tools', 'tickets.tool_id', '=', 'tools.id')
			        ->join('users as us', 'tickets.agent_id', '=', 'us.id')
			        ->join('ticket_states', 'tickets.status_id', '=', 'ticket_states.id')
			        ->select('tickets.id AS number', 'tickets.created_at AS date', 'tools.name AS tool', 'users.name AS user', 'tickets.text AS detail', 'us.name AS agent', 'ticket_states.name AS state')
			        ->orderBy('tickets.id', 'DESC')
			        ->paginate(25);
			       	$tools=DB::SELECT('SELECT id AS id,
			       		                      name AS name
			       		               FROM tools
			       		               WHERE active=?',[1]);
			        $agents=DB::SELECT('SELECT id AS id,
			       		                      name AS name
			       		               FROM users
			       		               WHERE ubication_id=? AND
			       		                     active=?',[40,1]);
			        $states=DB::SELECT('SELECT id AS id,
			       		                      name AS name
			       		               FROM ticket_states
			       		               WHERE active=?',[1]);
			        $pagination_state=1;
			            return view('reports.tickets',['modules' => $modules,'user' => $user,'datas'=>$datas,'tools'=>$tools,'agents'=>$agents,'states'=>$states,'pagination_state'=>$pagination_state]);
       }







	
		
	}

	


	public function reportsInvoices(Request $request){
		$user = Auth::user();
        $application = new Application();
        $modules = $application->getModules($user->id,6);
        $part=0;
        $datas=DB::SELECT("SELECT i.id AS id, 
									       i.number AS number,
										   s.name AS supplier,
										   f.name AS flow,
										   i.due_date AS due,
										   i.total AS total,
										   MAX(l.next_user_id) AS user,
										   CASE
										   WHEN MAX(l.state_id) = 1 THEN 'Radicada'
										   WHEN MAX(l.state_id) = 2 THEN 'Cancelada'
										   WHEN MAX(l.state_id) = 3 THEN 'Validada'
										   WHEN MAX(l.state_id) = 4 THEN 'Aprobada'
										   WHEN MAX(l.state_id) = 5 THEN 'Rechazada'
										   WHEN MAX(l.state_id) = 6 THEN 'Finalizada'
										   END AS state
									FROM invoices i
									LEFT JOIN suppliers s
									ON s.id= i.supplier_id
									LEFT JOIN invoice_flows f
									ON f.id= i.flow_id
									LEFT JOIN invoice_logg l 
									ON l.invoice_id=i.id
									LEFT JOIN invoice_states st 
									ON st.name=l.state_id
									LEFT JOIN users u
									ON u.id=l.next_user_id
									GROUP BY i.number,s.name,f.name,i.due_date,i.total,i.id
									ORDER BY i.number DESC,i.created_at DESC");

		        $references=DB::SELECT('SELECT id AS id,
		        	                          number AS number
		        	                          FROM invoices
		        	                          ORDER BY number ASC');
		        $suppliers=DB::SELECT('SELECT id AS id,
				                          name AS name
				                          FROM suppliers
				                          ORDER BY name ASC');
		        $states=DB::SELECT('SELECT id AS id,
				                          name AS name
				                          FROM invoice_states
				                          ORDER BY id ASC');
		        return view('reports.invoices',['modules' => $modules,'user' => $user,'references'=>$references,'suppliers'=>$suppliers,'states'=>$states,'part'=>$part]);
	}

	public function reportsInvoicesUsers(Request $request){
		$user = Auth::user();
        $application = new Application();
        $modules = $application->getModules($user->id,6);

        $datas=DB::SELECT("SELECT L.id AS log_id,I.id AS id ,I.number AS number,
							       CASE 
							       WHEN L.state_id=1 THEN 'Radicada'
							       WHEN L.state_id=2 THEN 'Cancelada'
							       WHEN L.state_id=3 THEN 'Validada'
							       WHEN L.state_id=4 THEN 'Aprobada'
							       WHEN L.state_id=5 THEN 'Rechazada'
							       WHEN L.state_id=6 THEN 'Finalizada'
							       END AS state,
							       DATE_FORMAT(I.created_at,'%Y-%m-%d') AS created_at,
							       I.currency AS currency,
							       I.subtotal,I.iva,FORMAT(I.total,2) AS total,
							       I.priority,L.next_user_id,
							       S.name supplier,I.due_date AS due,
								   (SELECT MAX(id) FROM invoice_logg L WHERE L.invoice_id = I.id) LOG
							FROM invoices I
							INNER JOIN invoice_logg L ON L.invoice_id = I.id AND L.id = (SELECT MAX(id) FROM invoice_logg L WHERE L.invoice_id = I.id) 
							AND user_id = ? AND L.state_id NOT IN (6,4,2)
							INNER JOIN suppliers S ON S.id = I.supplier_id
							ORDER BY I.due_date asc",[$user->id]);
		        $references=DB::SELECT("SELECT L.id AS log_id,I.id AS id ,I.number AS number,
							       CASE 
							       WHEN L.state_id=1 THEN 'Radicada'
							       WHEN L.state_id=2 THEN 'Cancelada'
							       WHEN L.state_id=3 THEN 'Validada'
							       WHEN L.state_id=4 THEN 'Aprobada'
							       WHEN L.state_id=5 THEN 'Rechazada'
							       WHEN L.state_id=6 THEN 'Finalizada'
							       END AS state,
							       DATE_FORMAT(I.created_at,'%Y-%m-%d') AS created_at,
							       I.currency AS currency,
							       I.subtotal,I.iva,I.total AS total,
							       I.priority,L.next_user_id,
							       S.name supplier,I.due_date AS due,
								   (SELECT MAX(id) FROM invoice_logg L WHERE L.invoice_id = I.id) LOG
							FROM invoices I
							INNER JOIN invoice_logg L ON L.invoice_id = I.id AND L.id = (SELECT MAX(id) FROM invoice_logg L WHERE L.invoice_id = I.id) 
							AND user_id = ? AND L.state_id NOT IN (6,4,2)
							INNER JOIN suppliers S ON S.id = I.supplier_id
							ORDER BY I.due_date asc",[$user->id]);
		        $suppliers=DB::SELECT('SELECT id AS id,
				                          name AS name
				                          FROM suppliers
				                          ORDER BY name ASC');
		        $states=DB::SELECT('SELECT id AS id,
				                          name AS name
				                          FROM invoice_states
				                          ORDER BY id ASC');
		        return view('reports.invoicesself',['modules' => $modules,'user' => $user,'datas'=>$datas,'references'=>$references,'suppliers'=>$suppliers,'states'=>$states]);
	}

	public function reportsTickets(Request $request){
		$user = Auth::user();
        $application = new Application();
        $modules = $application->getModules($user->id,6);
			$datas = DB::table('tickets')
			        ->join('users', 'tickets.user_id', '=', 'users.id')
			        ->join('tools', 'tickets.tool_id', '=', 'tools.id')
			        ->join('users as us', 'tickets.agent_id', '=', 'us.id')
			        ->join('ticket_states', 'tickets.status_id', '=', 'ticket_states.id')
			        ->join('ticket_log','tickets.id','=','ticket_log.ticket_id')
			        ->select('tickets.id AS number', 'tickets.created_at AS date', 'tools.name AS tool', 'users.name AS user', 'tickets.text AS detail', 'us.name AS agent', 'ticket_states.name AS state','ticket_log.created_at AS log_date',
			           'tickets.updated_at AS updated')
			        ->groupBy('tickets.id')
			        ->orderBy('tickets.id', 'DESC')
			        ->paginate(25);
			       	$tools=DB::SELECT('SELECT id AS id,
			       		                      name AS name
			       		               FROM tools
			       		               WHERE active=?',[1]);
			        $agents=DB::SELECT('SELECT id AS id,
			       		                      name AS name
			       		               FROM users
			       		               WHERE ubication_name = \'SISTEMAS\' AND
			       		                    active = 1 ');
			        $states=DB::SELECT('SELECT id AS id,
			       		                      name AS name
			       		               FROM ticket_states
			       		               WHERE active=?',[1]);
			        $pagination_state=1;
			            return view('reports.tickets',['modules' => $modules,'user' => $user,'datas'=>$datas,'tools'=>$tools,'agents'=>$agents,'states'=>$states,'pagination_state'=>$pagination_state]);
	}


	public function editinvoices(Request $request){
		$user = Auth::user();
        $application = new Application();
        $modules = [];
		$datas=DB::SELECT("SELECT i.id AS id, 
							       i.number AS number,
								   s.name AS supplier,
								   f.name AS flow,
								   i.due_date AS due,
								   i.total AS total,
								   CASE
								   WHEN MAX(l.state_id) = 1 THEN 'Radicada'
								   WHEN MAX(l.state_id) = 2 THEN 'Cancelada'
								   WHEN MAX(l.state_id) = 3 THEN 'Validada'
								   WHEN MAX(l.state_id) = 4 THEN 'Aprobada'
								   WHEN MAX(l.state_id) = 5 THEN 'Rechazada'
								   WHEN MAX(l.state_id) = 6 THEN 'Finalizada'
								   END AS state
							FROM invoices i
							LEFT JOIN suppliers s
							ON s.id= i.supplier_id
							LEFT JOIN invoice_flows f
							ON f.id= i.flow_id
							LEFT JOIN invoice_logg l 
							ON l.invoice_id=i.id
							LEFT JOIN invoice_states st 
							ON st.name=l.state_id
							LEFT JOIN users u
							ON u.id=l.next_user_id
							WHERE i.id=?
							GROUP BY i.number,s.name,f.name,i.due_date,i.total,i.id
							ORDER BY i.number DESC,i.created_at DESC",[$request->id]);
        $references=DB::SELECT('SELECT id AS id,
        	                          number AS number
        	                          FROM invoices
        	                          ORDER BY number ASC');
        $suppliers=DB::SELECT('SELECT id AS id,
		                          name AS name
		                          FROM suppliers
		                          ORDER BY name ASC');
        $states=DB::SELECT('SELECT id AS id,
		                          name AS name
		                          FROM invoice_states
		                          ORDER BY id ASC');
		return view('/reports/editioninvoices',['modules' => $modules,'user' => $user,'datas'=>$datas,'references'=>$references,'suppliers'=>$suppliers,'states'=>$states]);
	}


	public function editinvoicesself(Request $request){
		$user = Auth::user();
        $application = new Application();
        $modules = [];
		$datas=DB::SELECT("SELECT i.id AS id, 
							       i.number AS number,
								   s.name AS supplier,
								   s.id AS idsupplier,
								   f.name AS flow,
								   i.due_date AS due,
								   i.total AS total,
								   CASE
								   WHEN MAX(l.state_id) = 1 THEN 'Radicada'
								   WHEN MAX(l.state_id) = 2 THEN 'Cancelada'
								   WHEN MAX(l.state_id) = 3 THEN 'Validada'
								   WHEN MAX(l.state_id) = 4 THEN 'Aprobada'
								   WHEN MAX(l.state_id) = 5 THEN 'Rechazada'
								   WHEN MAX(l.state_id) = 6 THEN 'Finalizada'
								   END AS state
							FROM invoices i
							LEFT JOIN suppliers s
							ON s.id= i.supplier_id
							LEFT JOIN invoice_flows f
							ON f.id= i.flow_id
							LEFT JOIN invoice_logg l 
							ON l.invoice_id=i.id
							LEFT JOIN invoice_states st 
							ON st.name=l.state_id
							LEFT JOIN users u
							ON u.id=l.next_user_id
							WHERE i.id=?
							GROUP BY i.number,s.name,f.name,i.due_date,i.total,i.id,s.id
							ORDER BY i.number DESC,i.created_at DESC",[$request->invoice_id]);
        $references=DB::SELECT('SELECT id AS id,
        	                          number AS number
        	                          FROM invoices
        	                          ORDER BY number ASC');
        $suppliers=DB::SELECT('SELECT id AS id,
		                          name AS name
		                          FROM suppliers
		                          ORDER BY name ASC');
        $states=DB::SELECT('SELECT id AS id,
		                          name AS name
		                          FROM invoice_states
		                          ORDER BY id ASC');
		return view('/reports/editioninvoicesself',['modules' => $modules,'user' => $user,'datas'=>$datas,'references'=>$references,'suppliers'=>$suppliers,'states'=>$states]);
	}


	public function deleteinvoices(Request $request){
		$user = Auth::user();
        $application = new Application();
        $modules = [];
        $part=0;
        $deletelog=DB::DELETE("DELETE FROM distributions
        	                WHERE invoice_id=?",[$request->id]);
        $deletelog=DB::DELETE("DELETE FROM invoice_logg
        	                WHERE invoice_id=?",[$request->id]);
        $delete=DB::DELETE("DELETE FROM invoices
        	                WHERE id=?",[$request->id]);
       		 $datas=DB::SELECT("SELECT i.id AS id, 
							       i.number AS number,
								   s.name AS supplier,
								   f.name AS flow,
								   i.due_date AS due,
								   i.total AS total,
								   CASE
								   WHEN MAX(l.state_id) = 1 THEN 'Radicada'
								   WHEN MAX(l.state_id) = 2 THEN 'Cancelada'
								   WHEN MAX(l.state_id) = 3 THEN 'Validada'
								   WHEN MAX(l.state_id) = 4 THEN 'Aprobada'
								   WHEN MAX(l.state_id) = 5 THEN 'Rechazada'
								   WHEN MAX(l.state_id) = 6 THEN 'Finalizada'
								   END AS state
							FROM invoices i
							LEFT JOIN suppliers s
							ON s.id= i.supplier_id
							LEFT JOIN invoice_flows f
							ON f.id= i.flow_id
							LEFT JOIN invoice_logg l 
							ON l.invoice_id=i.id
							LEFT JOIN invoice_states st 
							ON st.name=l.state_id
							LEFT JOIN users u
							ON u.id=l.next_user_id
							GROUP BY i.number,s.name,f.name,i.due_date,i.total,i.id
							ORDER BY i.number DESC,i.created_at DESC");
        $references=DB::SELECT('SELECT id AS id,
        	                          number AS number
        	                          FROM invoices
        	                          ORDER BY number ASC');
        $suppliers=DB::SELECT('SELECT id AS id,
		                          name AS name
		                          FROM suppliers
		                          ORDER BY name ASC');
        $states=DB::SELECT('SELECT id AS id,
		                          name AS name
		                          FROM invoice_states
		                          ORDER BY id ASC');
     return view('reports.invoices',['modules' => $modules,'user' => $user,'datas'=>$datas,'references'=>$references,'suppliers'=>$suppliers,'states'=>$states,'part'=>$part]);
			
	}



	public function deleteinvoicesself(Request $request){
		$user = Auth::user();
        $application = new Application();
        $modules = [];
        $deletelog=DB::DELETE("DELETE FROM distributions
        	                WHERE invoice_id=?",[$request->invoice_id]);
        $deletelog=DB::DELETE("DELETE FROM invoice_logg
        	                WHERE invoice_id=?",[$request->invoice_id]);
        $delete=DB::DELETE("DELETE FROM invoices
        	                WHERE id=?",[$request->invoice_id]);
       		 $datas=DB::SELECT("SELECT i.id AS id, 
							       i.number AS number,
								   s.name AS supplier,
								   f.name AS flow,
								   i.due_date AS due,
								   i.total AS total,
								   CASE
								   WHEN MAX(l.state_id) = 1 THEN 'Radicada'
								   WHEN MAX(l.state_id) = 2 THEN 'Cancelada'
								   WHEN MAX(l.state_id) = 3 THEN 'Validada'
								   WHEN MAX(l.state_id) = 4 THEN 'Aprobada'
								   WHEN MAX(l.state_id) = 5 THEN 'Rechazada'
								   WHEN MAX(l.state_id) = 6 THEN 'Finalizada'
								   END AS state,
								   DATE_FORMAT(i.created_at,'%Y-%m-%d') AS created_at
							FROM invoices i
							LEFT JOIN suppliers s
							ON s.id= i.supplier_id
							LEFT JOIN invoice_flows f
							ON f.id= i.flow_id
							LEFT JOIN invoice_logg l 
							ON l.invoice_id=i.id
							LEFT JOIN invoice_states st 
							ON st.name=l.state_id
							LEFT JOIN users u
							ON u.id=l.next_user_id
							WHERE l.state_id = ? AND
							      l.user_id = ?
							GROUP BY i.number,s.name,f.name,i.due_date,i.total,i.id
							ORDER BY i.number DESC,i.created_at DESC",[1,$user->id]);
        $references=DB::SELECT('SELECT id AS id,
        	                          number AS number
        	                          FROM invoices
        	                          ORDER BY number ASC');
        $suppliers=DB::SELECT('SELECT id AS id,
		                          name AS name
		                          FROM suppliers
		                          ORDER BY name ASC');
        $states=DB::SELECT('SELECT id AS id,
		                          name AS name
		                          FROM invoice_states
		                          ORDER BY id ASC');
     return view('reports.invoicesself',['modules' => $modules,'user' => $user,'datas'=>$datas,'references'=>$references,'suppliers'=>$suppliers,'states'=>$states]);
			
	}


	public function finaleditinvoices(Request $request){
		$user = Auth::user();
        $application = new Application();
        $modules = [];
        $part=0;

        $update=DB::UPDATE("UPDATE invoices
        	                SET number = ?
        	                WHERE id=?",[$request->reference,$request->invoice_id]);
       		 $datas=DB::SELECT("SELECT i.id AS id, 
							       i.number AS number,
								   s.name AS supplier,
								   f.name AS flow,
								   i.due_date AS due,
								   i.total AS total,
								   CASE
								   WHEN MAX(l.state_id) = 1 THEN 'Radicada'
								   WHEN MAX(l.state_id) = 2 THEN 'Cancelada'
								   WHEN MAX(l.state_id) = 3 THEN 'Validada'
								   WHEN MAX(l.state_id) = 4 THEN 'Aprobada'
								   WHEN MAX(l.state_id) = 5 THEN 'Rechazada'
								   WHEN MAX(l.state_id) = 6 THEN 'Finalizada'
								   END AS state
							FROM invoices i
							LEFT JOIN suppliers s
							ON s.id= i.supplier_id
							LEFT JOIN invoice_flows f
							ON f.id= i.flow_id
							LEFT JOIN invoice_logg l 
							ON l.invoice_id=i.id
							LEFT JOIN invoice_states st 
							ON st.name=l.state_id
							LEFT JOIN users u
							ON u.id=l.next_user_id
							GROUP BY i.number,s.name,f.name,i.due_date,i.total,i.id
							ORDER BY i.number DESC,i.created_at DESC");
        $references=DB::SELECT('SELECT id AS id,
        	                          number AS number
        	                          FROM invoices
        	                          ORDER BY number ASC');
        $suppliers=DB::SELECT('SELECT id AS id,
		                          name AS name
		                          FROM suppliers
		                          ORDER BY name ASC');
        $states=DB::SELECT('SELECT id AS id,
		                          name AS name
		                          FROM invoice_states
		                          ORDER BY id ASC');
     return view('reports.invoices',['modules' => $modules,'user' => $user,'datas'=>$datas,'references'=>$references,'suppliers'=>$suppliers,'states'=>$states,'part'=>$part]);
	}

	public function finaleditinvoicesself(Request $request){
		$user = Auth::user();
        $application = new Application();
        $modules = [];

        $id=$request->invoice_id;
        $reference=$request->reference;
        $supplier=$request->supplier;
        $total=$request->total;

        if ($reference != '') {
        $update=DB::UPDATE("UPDATE invoices
        	                SET number = ?
        	                WHERE id=?",[$request->reference,$request->invoice_id]);        	
        }

        if ($supplier != '') {
        $update=DB::UPDATE("UPDATE invoices
        	                SET supplier_id = ?
        	                WHERE id=?",[$request->supplier,$request->invoice_id]);        	
        }

        if ($total != '') {
        $update=DB::UPDATE("UPDATE invoices
        	                SET total = ?
        	                WHERE id=?",[$request->total,$request->invoice_id]);        	
        }


       		 $datas=DB::SELECT("SELECT L.id AS log_id,I.id AS id ,I.number AS number,
							       CASE 
							       WHEN L.state_id=1 THEN 'Radicada'
							       WHEN L.state_id=2 THEN 'Cancelada'
							       WHEN L.state_id=3 THEN 'Validada'
							       WHEN L.state_id=4 THEN 'Aprobada'
							       WHEN L.state_id=5 THEN 'Rechazada'
							       WHEN L.state_id=6 THEN 'Finalizada'
							       END AS state,
							       DATE_FORMAT(I.created_at,'%Y-%m-%d') AS created_at,
							       I.currency AS currency,
							       I.subtotal,I.iva,I.total AS total,
							       I.priority,L.next_user_id,
							       S.name supplier,I.due_date AS due,
								   (SELECT MAX(id) FROM invoice_logg L WHERE L.invoice_id = I.id) LOG
							FROM invoices I
							INNER JOIN invoice_logg L ON L.invoice_id = I.id AND L.id = (SELECT MAX(id) FROM invoice_logg L WHERE L.invoice_id = I.id) 
							AND user_id = ? AND L.state_id NOT IN (6,4,2)
							INNER JOIN suppliers S ON S.id = I.supplier_id
							ORDER BY I.due_date asc",[$user->id]);
        $references=DB::SELECT("SELECT L.id AS log_id,I.id AS id ,I.number AS number,
							       CASE 
							       WHEN L.state_id=1 THEN 'Radicada'
							       WHEN L.state_id=2 THEN 'Cancelada'
							       WHEN L.state_id=3 THEN 'Validada'
							       WHEN L.state_id=4 THEN 'Aprobada'
							       WHEN L.state_id=5 THEN 'Rechazada'
							       WHEN L.state_id=6 THEN 'Finalizada'
							       END AS state,
							       DATE_FORMAT(I.created_at,'%Y-%m-%d') AS created_at,
							       I.currency AS currency,
							       I.subtotal,I.iva,I.total AS total,
							       I.priority,L.next_user_id,
							       S.name supplier,I.due_date AS due,
								   (SELECT MAX(id) FROM invoice_logg L WHERE L.invoice_id = I.id) LOG
							FROM invoices I
							INNER JOIN invoice_logg L ON L.invoice_id = I.id AND L.id = (SELECT MAX(id) FROM invoice_logg L WHERE L.invoice_id = I.id) 
							AND user_id = ? AND L.state_id NOT IN (6,4,2)
							INNER JOIN suppliers S ON S.id = I.supplier_id
							ORDER BY I.due_date asc",[$user->id]);
        $suppliers=DB::SELECT('SELECT id AS id,
		                          name AS name
		                          FROM suppliers
		                          ORDER BY name ASC');
        $states=DB::SELECT('SELECT id AS id,
		                          name AS name
		                          FROM invoice_states
		                          ORDER BY id ASC');
     return view('reports.invoicesself',['modules' => $modules,'user' => $user,'datas'=>$datas,'references'=>$references,'suppliers'=>$suppliers,'states'=>$states]);
	}


	public function lookinginvoices(Request $request){
	$user = Auth::user();
    $application = new Application();
    $modules = $application->getModules($user->id,6);
     $reference=$request->reference;
     $supplier=$request->supplier;
     $state=$request->state;
     $looking='';
    if ($reference != 0) {
    	$looking=1;
    	
    }
    if ($supplier != 0) {
    	$looking=2;
    	
    }
    if ($state != 0) {
    	$looking=3;
    	
    }


    switch ($looking) {
    	case 1:
       		 $datas=DB::SELECT("SELECT i.id AS id, 
							       i.number AS number,
								   s.name AS supplier,
								   f.name AS flow,
								   i.due_date AS due,
								   i.total AS total,
								   CASE
								   WHEN MAX(l.state_id) = 1 THEN 'Radicada'
								   WHEN MAX(l.state_id) = 2 THEN 'Cancelada'
								   WHEN MAX(l.state_id) = 3 THEN 'Validada'
								   WHEN MAX(l.state_id) = 4 THEN 'Aprobada'
								   WHEN MAX(l.state_id) = 5 THEN 'Rechazada'
								   WHEN MAX(l.state_id) = 6 THEN 'Finalizada'
								   END AS state
							FROM invoices i
							LEFT JOIN suppliers s
							ON s.id= i.supplier_id
							LEFT JOIN invoice_flows f
							ON f.id= i.flow_id
							LEFT JOIN invoice_logg l 
							ON l.invoice_id=i.id
							LEFT JOIN invoice_states st 
							ON st.name=l.state_id
							LEFT JOIN users u
							ON u.id=l.next_user_id
							WHERE i.id=?
							GROUP BY i.number,s.name,f.name,i.due_date,i.total,i.id
							ORDER BY i.number DESC,i.created_at DESC",[$reference]);
    		break;
    	case 2:
       		 $datas=DB::SELECT("SELECT i.id AS id, 
							       i.number AS number,
								   s.name AS supplier,
								   f.name AS flow,
								   i.due_date AS due,
								   i.total AS total,
								   CASE
								   WHEN MAX(l.state_id) = 1 THEN 'Radicada'
								   WHEN MAX(l.state_id) = 2 THEN 'Cancelada'
								   WHEN MAX(l.state_id) = 3 THEN 'Validada'
								   WHEN MAX(l.state_id) = 4 THEN 'Aprobada'
								   WHEN MAX(l.state_id) = 5 THEN 'Rechazada'
								   WHEN MAX(l.state_id) = 6 THEN 'Finalizada'
								   END AS state
							FROM invoices i
							LEFT JOIN suppliers s
							ON s.id= i.supplier_id
							LEFT JOIN invoice_flows f
							ON f.id= i.flow_id
							LEFT JOIN invoice_logg l 
							ON l.invoice_id=i.id
							LEFT JOIN invoice_states st 
							ON st.name=l.state_id
							LEFT JOIN users u
							ON u.id=l.next_user_id
							WHERE i.supplier_id=?
							GROUP BY i.number,s.name,f.name,i.due_date,i.total,i.id
							ORDER BY i.number DESC,i.created_at DESC",[$supplier]);
    		break;
    	case 3:
       		 $datas=DB::SELECT("SELECT i.id AS id, 
							       i.number AS number,
								   s.name AS supplier,
								   f.name AS flow,
								   i.due_date AS due,
								   i.total AS total,
								   CASE
								   WHEN MAX(l.state_id) = 1 THEN 'Radicada'
								   WHEN MAX(l.state_id) = 2 THEN 'Cancelada'
								   WHEN MAX(l.state_id) = 3 THEN 'Validada'
								   WHEN MAX(l.state_id) = 4 THEN 'Aprobada'
								   WHEN MAX(l.state_id) = 5 THEN 'Rechazada'
								   WHEN MAX(l.state_id) = 6 THEN 'Finalizada'
								   END AS state
							FROM invoices i
							LEFT JOIN suppliers s
							ON s.id= i.supplier_id
							LEFT JOIN invoice_flows f
							ON f.id= i.flow_id
							LEFT JOIN invoice_logg l 
							ON l.invoice_id=i.id
							LEFT JOIN invoice_states st 
							ON st.name=l.state_id
							LEFT JOIN users u
							ON u.id=l.next_user_id
							WHERE l.state_id=?
							GROUP BY i.number,s.name,f.name,i.due_date,i.total,i.id
							ORDER BY i.number DESC,i.created_at DESC",[$state]);
    		break;
    	default:
       		 $datas=DB::SELECT("SELECT i.id AS id, 
							       i.number AS number,
								   s.name AS supplier,
								   f.name AS flow,
								   i.due_date AS due,
								   i.total AS total,
								   CASE
								   WHEN MAX(l.state_id) = 1 THEN 'Radicada'
								   WHEN MAX(l.state_id) = 2 THEN 'Cancelada'
								   WHEN MAX(l.state_id) = 3 THEN 'Validada'
								   WHEN MAX(l.state_id) = 4 THEN 'Aprobada'
								   WHEN MAX(l.state_id) = 5 THEN 'Rechazada'
								   WHEN MAX(l.state_id) = 6 THEN 'Finalizada'
								   END AS state
							FROM invoices i
							LEFT JOIN suppliers s
							ON s.id= i.supplier_id
							LEFT JOIN invoice_flows f
							ON f.id= i.flow_id
							LEFT JOIN invoice_logg l 
							ON l.invoice_id=i.id
							LEFT JOIN invoice_states st 
							ON st.name=l.state_id
							LEFT JOIN users u
							ON u.id=l.next_user_id
							GROUP BY i.number,s.name,f.name,i.due_date,i.total,i.id
							ORDER BY i.number DESC,i.created_at DESC");
    		break;
    }


        $references=DB::SELECT('SELECT id AS id,
        	                          number AS number
        	                          FROM invoices
        	                          ORDER BY number ASC');
        $suppliers=DB::SELECT('SELECT id AS id,
		                          name AS name
		                          FROM suppliers
		                          ORDER BY name ASC');
        $states=DB::SELECT('SELECT id AS id,
		                          name AS name
		                          FROM invoice_states
		                          ORDER BY id ASC');

     return view('reports.invoices',['modules' => $modules,'user' => $user,'datas'=>$datas,'references'=>$references,'suppliers'=>$suppliers,'states'=>$states]);
	}



public function lookinginvoicesself(Request $request){
	$user = Auth::user();
    $application = new Application();
    $modules = $application->getModules($user->id,6);
     $reference=$request->reference;
     $supplier=$request->supplier;
     $state=$request->state;
     $looking='';
    if ($reference != 0) {
    	$looking=1;
    	
    }
    if ($supplier != 0) {
    	$looking=2;
    	
    }
    if ($state != 0) {
    	$looking=3;
    	
    }


    switch ($looking) {
    	case 1:
       		 $datas=DB::SELECT("SELECT i.id AS id, 
							       i.number AS number,
								   s.name AS supplier,
								   f.name AS flow,
								   i.due_date AS due,
								   i.total AS total,
								   i.currency AS currency,
								   CASE
								   WHEN MAX(l.state_id) = 1 THEN 'Radicada'
								   WHEN MAX(l.state_id) = 2 THEN 'Cancelada'
								   WHEN MAX(l.state_id) = 3 THEN 'Validada'
								   WHEN MAX(l.state_id) = 4 THEN 'Aprobada'
								   WHEN MAX(l.state_id) = 5 THEN 'Rechazada'
								   WHEN MAX(l.state_id) = 6 THEN 'Finalizada'
								   END AS state,
								   DATE_FORMAT(i.created_at,'%Y-%m-%d') AS created_at
							FROM invoices i
							LEFT JOIN suppliers s
							ON s.id= i.supplier_id
							LEFT JOIN invoice_flows f
							ON f.id= i.flow_id
							LEFT JOIN invoice_logg l 
							ON l.invoice_id=i.id
							LEFT JOIN invoice_states st 
							ON st.name=l.state_id
							LEFT JOIN users u
							ON u.id=l.next_user_id
							WHERE i.id=?
							GROUP BY i.number,s.name,f.name,i.due_date,i.total,i.id,i.currency
							ORDER BY i.number DESC,i.created_at DESC",[$reference]);
    		break;
    	case 2:
       		 $datas=DB::SELECT("SELECT L.id AS log_id,I.id AS id ,I.number AS number,
							       CASE 
							       WHEN L.state_id=1 THEN 'Radicada'
							       WHEN L.state_id=2 THEN 'Cancelada'
							       WHEN L.state_id=3 THEN 'Validada'
							       WHEN L.state_id=4 THEN 'Aprobada'
							       WHEN L.state_id=5 THEN 'Rechazada'
							       WHEN L.state_id=6 THEN 'Finalizada'
							       END AS state,
							       DATE_FORMAT(I.created_at,'%Y-%m-%d') AS created_at,
							       I.currency AS currency,
							       I.subtotal,I.iva,I.total AS total,
							       I.priority,L.next_user_id,
							       S.name supplier,I.due_date AS due,
								   (SELECT MAX(id) FROM invoice_logg L WHERE L.invoice_id = I.id) LOG
							FROM invoices I
							INNER JOIN invoice_logg L ON L.invoice_id = I.id AND L.id = (SELECT MAX(id) FROM invoice_logg L WHERE L.invoice_id = I.id) 
							AND user_id = ? AND L.state_id NOT IN (6,4,2)
							INNER JOIN suppliers S ON S.id = I.supplier_id
							WHERE I.supplier_id=?
							ORDER BY I.due_date asc",[$user->id,$supplier]);
    		break;
    	case 3:
       		 $datas=DB::SELECT("SELECT L.id AS log_id,
       		 	                       I.id AS id,
       		 	                       I.number AS number,
							       CASE 
							       WHEN L.state_id=1 THEN 'Radicada'
							       WHEN L.state_id=2 THEN 'Cancelada'
							       WHEN L.state_id=3 THEN 'Validada'
							       WHEN L.state_id=4 THEN 'Aprobada'
							       WHEN L.state_id=5 THEN 'Rechazada'
							       WHEN L.state_id=6 THEN 'Finalizada'
							       END AS state,
							       DATE_FORMAT(I.created_at,'%Y-%m-%d') AS created_at,
							       I.currency AS currency,
							       I.subtotal,I.iva,I.total AS total,
							       I.priority,L.next_user_id,
							       S.name supplier,I.due_date AS due,
								   (SELECT MAX(id) FROM invoice_logg L WHERE L.invoice_id = I.id) LOG
							FROM invoices I
							INNER JOIN invoice_logg L ON L.invoice_id = I.id AND L.id = (SELECT MAX(id) FROM invoice_logg L WHERE L.invoice_id = I.id) 
							AND user_id = ? AND L.state_id NOT IN (6,4,2)
							INNER JOIN suppliers S ON S.id = I.supplier_id
							WHERE L.state_id=?
							ORDER BY I.due_date asc",[$user->id,$state]);
    		break;
    	default:

       		 $datas=DB::SELECT("SELECT L.id AS log_id,I.id AS id ,I.number AS number,
							       CASE 
							       WHEN L.state_id=1 THEN 'Radicada'
							       WHEN L.state_id=2 THEN 'Cancelada'
							       WHEN L.state_id=3 THEN 'Validada'
							       WHEN L.state_id=4 THEN 'Aprobada'
							       WHEN L.state_id=5 THEN 'Rechazada'
							       WHEN L.state_id=6 THEN 'Finalizada'
							       END AS state,
							       DATE_FORMAT(I.created_at,'%Y-%m-%d') AS created_at,
							       I.currency AS currency,
							       I.subtotal,I.iva,I.total AS total,
							       I.priority,L.next_user_id,
							       S.name supplier,I.due_date AS due,
								   (SELECT MAX(id) FROM invoice_logg L WHERE L.invoice_id = I.id) LOG
							FROM invoices I
							INNER JOIN invoice_logg L ON L.invoice_id = I.id AND L.id = (SELECT MAX(id) FROM invoice_logg L WHERE L.invoice_id = I.id) 
							AND user_id = ? AND L.state_id NOT IN (6,4,2)
							INNER JOIN suppliers S ON S.id = I.supplier_id
							ORDER BY I.due_date asc",[$user->id]);
    		break;
    }

        $references=DB::SELECT("SELECT L.id AS log_id,I.id AS id ,I.number AS number,
							       CASE 
							       WHEN L.state_id=1 THEN 'Radicada'
							       WHEN L.state_id=2 THEN 'Cancelada'
							       WHEN L.state_id=3 THEN 'Validada'
							       WHEN L.state_id=4 THEN 'Aprobada'
							       WHEN L.state_id=5 THEN 'Rechazada'
							       WHEN L.state_id=6 THEN 'Finalizada'
							       END AS state,
							       DATE_FORMAT(I.created_at,'%Y-%m-%d') AS created_at,
							       I.currency AS currency,
							       I.subtotal,I.iva,I.total AS total,
							       I.priority,L.next_user_id,
							       S.name supplier,I.due_date AS due,
								   (SELECT MAX(id) FROM invoice_logg L WHERE L.invoice_id = I.id) LOG
							FROM invoices I
							INNER JOIN invoice_logg L ON L.invoice_id = I.id AND L.id = (SELECT MAX(id) FROM invoice_logg L WHERE L.invoice_id = I.id) 
							AND user_id = ? AND L.state_id NOT IN (6,4,2)
							INNER JOIN suppliers S ON S.id = I.supplier_id
							ORDER BY I.due_date asc",[$user->id]);
        $suppliers=DB::SELECT('SELECT id AS id,
		                          name AS name
		                          FROM suppliers
		                          ORDER BY name ASC');
        $states=DB::SELECT('SELECT id AS id,
		                          name AS name
		                          FROM invoice_states
		                          ORDER BY id ASC');

     return view('reports.invoicesself',['modules' => $modules,'user' => $user,'datas'=>$datas,'references'=>$references,'suppliers'=>$suppliers,'states'=>$states]);
	}


   public function lookingtickets(Request $request){
		$user = Auth::user();
		$application = new Application();
		$modules = $application->getModules($user->id,6);
		$tool=$request->tool;
		$agent=$request->agent;
		$state=$request->state;
		$looking='';
		$datas=[];

	 if (($tool == 0) && ($agent == 0) && ($state == 0)) {
	 	$looking = ' ';
	 	$pagination_state=1;
	 }
    if (($tool == 0) && ($agent ==0) && ($state != 0)) {
    	$looking=' WHERE t.status_id=? ';
    	$datas=[$state];
    	$pagination_state=0;
    }
    if (($tool == 0) && ($agent !=0) && ($state == 0)) {
    	$looking=' WHERE t.agent_id=? ';
    	$datas=[$agent];
    	$pagination_state=0;
    	
    }
    if (($tool == 0) && ($agent !=0) && ($state != 0)) {
    	$looking=' WHERE t.agent_id=? AND t.status_id=? ';
    	$datas=[$agent,$state];
    	$pagination_state=0;
    	
    }
    if (($tool != 0) && ($agent ==0) && ($state == 0)) {
    	$looking=' WHERE t.tool_id=? ';
    	$datas=[$tool];
    	$pagination_state=0;
    	
    }
    if (($tool != 0) && ($agent ==0) && ($state != 0)) {
    	$looking=' WHERE t.tool_id=? AND t.status_id=? ';
    	$datas=[$tool,$state];
    	$pagination_state=0;
    	
    }
    if (($tool != 0) && ($agent !=0) && ($state == 0)) {
    	$looking=' WHERE t.tool_id=? AND t.agent_id=? ';
    	$datas=[$tool,$agent];
    	$pagination_state=0;
    	
    }
    if (($tool != 0) && ($agent !=0) && ($state != 0)) {
    	$looking=' WHERE t.tool_id=? AND t.agent_id=? AND t.status_id=? ';
    	$datas=[$tool,$agent,$state];
    	$pagination_state=0;
    	
    }
          if ($pagination_state == 1) {
			$datas = DB::table('tickets')
			        ->join('users', 'tickets.user_id', '=', 'users.id')
			        ->join('tools', 'tickets.tool_id', '=', 'tools.id')
			        ->join('users as us', 'tickets.agent_id', '=', 'us.id')
			        ->join('ticket_states', 'tickets.status_id', '=', 'ticket_states.id')
			        ->select('tickets.id AS number', 'tickets.created_at AS date', 'tools.name AS tool', 'users.name AS user', 'tickets.text AS detail', 'us.name AS agent', 'ticket_states.name AS state','tickets.updated_at AS updated')
			        ->orderBy('tickets.id', 'DESC')
			        ->paginate(25);
			       	$tools=DB::SELECT('SELECT id AS id,
			       		                      name AS name
			       		               FROM tools
			       		               WHERE active=?',[1]);
			        $agents=DB::SELECT('SELECT id AS id,
			       		                      name AS name
			       		               FROM users
			       		               WHERE ubication_id=? AND
			       		                     active=?',[40,1]);
			        $states=DB::SELECT('SELECT id AS id,
			       		                      name AS name
			       		               FROM ticket_states
			       		               WHERE active=?',[1]);

			       return view('reports.tickets',['modules' => $modules,'user' => $user,'datas'=>$datas,'tools'=>$tools,'agents'=>$agents,'states'=>$states,'pagination_state'=>$pagination_state]);
          }else{
       		 $datas=DB::SELECT("SELECT t.id AS number,
									   t.created_at AS date,
									   t.updated_at AS updated,
								       h.name AS tool,
								       u.name AS user,
								       t.text AS detail,
								       us.name AS agent,
								       ts.name AS state
								  FROM tickets t
								  INNER JOIN tools h
								  ON h.id= t.tool_id
								  INNER JOIN users u
								  ON u.id= t.user_id
								  INNER JOIN users us
								  ON us.id= t.agent_id
								  INNER JOIN ticket_states ts
								  ON ts.id=t.status_id".
								  $looking.
								"ORDER BY t.created_at DESC;",$datas);
       	$tools=DB::SELECT('SELECT id AS id,
       		                      name AS name
       		               FROM tools
       		               WHERE active=?',[1]);
        $agents=DB::SELECT('SELECT id AS id,
       		                      name AS name
       		               FROM users
       		               WHERE ubication_id=? AND
       		                     active=?',[40,1]);
        $states=DB::SELECT('SELECT id AS id,
       		                      name AS name
       		               FROM ticket_states
       		               WHERE active=?',[1]);
        return view('reports.tickets',['modules' => $modules,'user' => $user,'datas'=>$datas,'tools'=>$tools,'agents'=>$agents,'states'=>$states,'pagination_state'=>$pagination_state]);
          }
    }




	public function permission(Request $request){
		$user = Auth::user();
        $application = new Application();
        $modules = $application->getModules($user->id,7);
		$users=DB::SELECT("SELECT  id,name FROM users 
                            WHERE active=?;",[1]);

		$profiles=DB::SELECT('SELECT profile_name from users 
		where active=1 group by profile_name');

		$functions=DB::SELECT("SELECT  id AS id,
			                          name AS name
							FROM functions 
                            WHERE active=?;",[1]);
		$applications=DB::SELECT("SELECT  id AS id,
			                          name AS name
							FROM applications 
                            WHERE active=?;",[1]);
		$modulesfinal=DB::SELECT("SELECT  id AS id,
			                          name AS name
							FROM modules 
                            WHERE active=?;",[1]);
		$datas=DB::SELECT("SELECT  p.id AS id,
			                       u.name AS name,
							       c.name AS cargo,
							       p.aplication_name AS aplication,
							       p.module_name AS module,
							       p.function_name AS functions,
							       p.route AS route,
							       CASE 
							       WHEN p.active = 1 THEN 'Activo'
							       WHEN p.active = 0 THEN 'Inactivo'
							       END AS Estado
							FROM users u
							LEFT JOIN profiles c
							ON c.id= u.profile_id
							INNER JOIN permission p
							ON p.id_user=u.id
							ORDER BY u.name ASC;");
		return view('/reports/permissions',['modules' => $modules,'user' => $user,'datas'=>$datas,'users'=>$users,'profiles'=>$profiles,'functions'=>$functions,'applications'=>$applications,'modulesfinal'=>$modulesfinal]);
	}


	public function permissionedit(Request $request){
		$user = Auth::user();
        $application = new Application();
        $modules = $application->getModules($user->id,7);

        $idpermission=array();
        $result=array();
        $idchecked=array();

		$input = $request->except('_token');


		$ids=DB::SELECT("SELECT id AS id
						 FROM permission 
						 ORDER BY id ASC;");
        foreach ($ids as $id ) {
        	array_push($idpermission, $id->id);
        	
        }

        foreach ($input as $data) {
           array_push($idchecked, $data);
        }

        $result=array_diff($idpermission, $idchecked);


		foreach ($input as  $value) 
		{
			$update=DB::UPDATE("UPDATE permission
                                SET active=?
                                WHERE id=?",[1,$value]);
		}


        foreach($result as $dato){
			$update=DB::UPDATE("UPDATE permission
                                SET active=?
                                WHERE id=?",[0,$dato]);        	
        }

		$datas=DB::SELECT("SELECT  p.id AS id,
			                       u.name AS name,
							       c.name AS cargo,
							       p.aplication_name AS aplication,
							       p.module_name AS module,
							       p.function_name AS functions,
							       p.route AS route,
							       CASE 
							       WHEN p.active = 1 THEN 'Activo'
							       WHEN p.active = 0 THEN 'Inactivo'
							       END AS Estado
							FROM users u
							LEFT JOIN profiles c
							ON c.id= u.profile_id
							INNER JOIN permission p
							ON p.id_user=u.id
							ORDER BY u.id ASC;");
		$users=DB::SELECT("SELECT  id AS id,
			                       name AS name
							FROM users 
                            WHERE active=?;",[1]);
		$profiles=DB::SELECT("SELECT  id AS id,
			                          name AS name
							FROM profiles 
                            WHERE active=?;",[1]);
		$functions=DB::SELECT("SELECT  id AS id,
			                          name AS name
							FROM functions 
                            WHERE active=?;",[1]);
		$applications=DB::SELECT("SELECT  id AS id,
			                          name AS name
							FROM applications 
                            WHERE active=?;",[1]);
		$modulesfinal=DB::SELECT("SELECT  id AS id,
			                          name AS name
							FROM modules 
                            WHERE active=?;",[1]);
		return view('/reports/permissions',['modules' => $modules,'user' => $user,'datas'=>$datas,'users'=>$users,'profiles'=>$profiles,'functions'=>$functions,'applications'=>$applications,'modulesfinal'=>$modulesfinal]);
	}


	public function permissionfinder(Request $request){
		$user = Auth::user();
        $application = new Application();
        $modules = $application->getModules($user->id,7);

        $input = $request->except('_token');

        $identificador_data=array();

        $informacion="SELECT  p.id AS id,
				                       u.name AS name,
								       u.profile_name AS cargo,
								       p.aplication_name AS aplication,
								       p.module_name AS module,
								       p.function_name AS functions,
								       p.route AS route,
								       CASE 
								       WHEN p.active = 1 THEN 'Activo'
								       WHEN p.active = 0 THEN 'Inactivo'
								       END AS Estado
								FROM users u
								INNER JOIN profiles c
								ON c.id= u.profile_id
								INNER JOIN permission p
								ON p.id_user=u.id
								WHERE u.active = 1 ";

		if ($request->user != 0) {
			$informacion .= "AND p.id_user=? ";
			array_push($identificador_data,$request->user);
		}
		if( $request->profile != '0' && $request->profile != '' ) {
			$informacion .= "AND u.profile_name=? ";
			array_push($identificador_data, $request->profile);
		}
		if ($request->aplication != 0) {
			$informacion .= "AND p.aplication_id=? ";
			array_push($identificador_data,$request->aplication);
		}
		if ($request->module != 0) {
			$informacion .= "AND p.module_id=? ";
			array_push($identificador_data,$request->module);
		}
		if ($request->function != 0) {
			$informacion .= "AND p.function_id=? ";
			array_push($identificador_data,$request->function);
		}
		if ($request->estado != 'Vacio') {
			$informacion .= "AND p.active=? ";
			array_push($identificador_data,$request->estado);
		}

		$informacion .=		" ORDER BY u.id ASC";


		$datas=DB::SELECT($informacion,$identificador_data);	

		$users=DB::SELECT("SELECT  id AS id,
			                       name AS name
							FROM users 
                            WHERE active=?;",[1]);

		$profiles=DB::SELECT('SELECT profile_name from users 
			where active=1 group by profile_name');

		$functions=DB::SELECT("SELECT  id AS id, name AS name FROM functions 
                            WHERE active=?;",[1]);

		$applications=DB::SELECT("SELECT  id AS id, name AS name FROM applications 
                            WHERE active=?;",[1]);

		$modulesfinal=DB::SELECT("SELECT  id AS id, name AS name FROM modules 
                            WHERE active=?;",[1]);

		return view('/reports/permissions',['modules' => $modules,'user' => $user,'datas'=>$datas,'users'=>$users,'profiles'=>$profiles,'functions'=>$functions,'applications'=>$applications,'modulesfinal'=>$modulesfinal]);
	}


	// El del error
	public function permissioneditfinder(Request $request){
		$user = Auth::user();
        $application = new Application();
        $modules = $application->getModules($user->id,7);

        $idpermission=array();
        $result=array();
        $idchecked=array();

		$input = $request->except('_token');
        
        if ($request->state ==1) {
        	$update=DB::UPDATE('UPDATE permission
        		                SET active =?
        		                WHERE id=?',[1,$request->idpermission]);
        }else{
        	$update=DB::UPDATE('UPDATE permission
        		                SET active =?
        		                WHERE id=?',[0,$request->idpermission]);
        }
		$datas=DB::SELECT("SELECT  p.id AS id,
			                       u.name AS name,
							       c.name AS cargo,
							       p.aplication_name AS aplication,
							       p.module_name AS module,
							       p.function_name AS functions,
							       p.route AS route,
							       CASE 
							       WHEN p.active = 1 THEN 'Activo'
							       WHEN p.active = 0 THEN 'Inactivo'
							       END AS Estado
							FROM users u
							LEFT JOIN profiles c
							ON c.id= u.profile_id
							INNER JOIN permission p
							ON p.id_user=u.id
							ORDER BY u.id ASC;");
		$users=DB::SELECT("SELECT  id AS id,
			                       name AS name
							FROM users 
                            WHERE active=?;",[1]);

		$profiles=DB::SELECT('SELECT profile_name from users where active=1 group by profile_name');

		$functions=DB::SELECT("SELECT  id AS id, name AS name
							FROM functions 
                            WHERE active=?;",[1]);

		$applications=DB::SELECT("SELECT  id AS id, name AS name
							FROM applications 
                            WHERE active=?;",[1]);

		$modulesfinal=DB::SELECT("SELECT  id AS id, name AS name
							FROM modules 
                            WHERE active=?;",[1]);

		return view('/reports/permissions',['modules' => $modules,'user' => $user,'datas'=>$datas,'users'=>$users,'profiles'=>$profiles,'functions'=>$functions,'applications'=>$applications,'modulesfinal'=>$modulesfinal]);
	}


	public function permissionUser(Request $request){
		$user = Auth::user();
        $application = new Application();
        $modules = $application->getModules($user->id,7);
        $applications=DB::SELECT("SELECT id AS id,
        	                              name AS name
        	                      FROM applications
        	                      WHERE active=?",[1]);


        $ubications= Ubication::where('active','=',1)
                                ->orderBy('id','ASC')
                                ->get();
        $users= User::where('active','=',1)
                    ->orderBy('id','ASC')
                    ->get();
        $reports=DB::SELECT('SELECT id AS id,
                                    report_name AS report,
                                    report_route AS route
                            FROM reports
                            WHERE ubication_id=? AND
                                  active=?',[$user->ubication_id,1]);

        return view('admin',['modules' => $modules,'user' => $user,'ubications'=>$ubications,'reports'=>$reports,'users'=>$users,'applications'=>$applications]);
 
	}



		public function directorio(Request $request){
		$user = Auth::user();
        $application = new Application();
        $modules = $application->getModules($user->id,6);
        $datas=DB::SELECT("SELECT u.name AS name,
								  u.profile_name AS profile,
								  us.name AS lider,
								  u.ubication_name AS ubication,
								 
								       CASE
								       WHEN u.email IS NOT NULL THEN u.email
								       WHEN u.email IS NULL THEN ''
								       END AS email,
								       u.extension AS extension,
								       u.phone AS phone,
								       DATE_FORMAT(u.start_date, '%Y-%m-%d') AS fecha_ingresa
								FROM users u
								LEFT JOIN users us
								ON us.id=u.leader_id
								WHERE u.active=?							
								ORDER BY u.name ASC",[1]);
        
        $ubications= DB::SELECT('SELECT ubication_name as ubication_name from users where active=1 group by ubication_name');

        $users= User::where('active','=',1)
                    ->orderBy('id','ASC')
                    ->get();

		$profiles=DB::SELECT('SELECT profile_name as profile_name from users where active=1 group by profile_name');

        $leaders= DB::SELECT("SELECT  l.id AS id,
								        l.name AS name
								FROM users u
								INNER JOIN users l
								ON l.id=u.leader_id
								WHERE u.active=?
								GROUP BY l.id,l.name",[1]);

        return view('reports/directorio',['modules' => $modules,'user' => $user,'datas'=>$datas,'ubications'=>$ubications,'leaders'=>$leaders,'profiles'=>$profiles,'users'=>$users]);
 
	}


	public function invoiceslist(Request $request){
		$user = Auth::user();
        $application = new Application();
        $modules = $application->getModules($user->id,6);
        $id_invoices=array();

        $egresos=DB::SELECT("SELECT  id AS id,
        	                        egress as egreso
        	                FROM  invoices
        	                WHERE egress IS NOT NULL");

        $suppliers=DB::SELECT("SELECT  id AS id,
        	                           nit as nit,
        	                           name AS name
        	                     FROM  suppliers");

        $invoices=DB::SELECT("SELECT  i.id AS id,
        	                          i.number AS number,
                                      c.name AS company
        	                     FROM  invoices i
        	                     LEFT JOIN companies c
        	                     ON c.id=i.company");

        $radication_time=DB::SELECT("SELECT  created_at AS radication_time,
        	                                 id AS id
        	                     FROM  invoices");

        $users=DB::SELECT("SELECT u.id AS id,
                                  u.name AS name
        	              FROM  users u
        	              INNER JOIN invoice_logg l
        	              ON l.user_id = u.id
        	              GROUP BY u.id");


        $dato_tabla= Invoice::orderBy('id','ASC')
                    ->get();


     

        return view('reports/invoiceslist',['modules' => $modules,'user' => $user,'suppliers'=>$suppliers,'invoices'=>$invoices,'egresos'=>$egresos,'radication_time'=>$radication_time,'users'=>$users,'dato_tabla'=>$dato_tabla]);
 
	}

public function load_data_invoices(Request $request){
	$record_per_page=100;
	$page='';
	$output='';

	if (isset($request->page)) {
		$page=intval($request->page);
	}else{
		$page=1;
	}

	$start_from = ($page - 1)*$record_per_page;

    $total_data=DB::SELECT("SELECT   i.id AS id,
                                            i.file AS file,
                                            i.number AS number,
                                            s.name AS supplier,
                                            s.nit AS supplier_nit,
                                            DATE_FORMAT(i.created_at,'%Y-%m-%d') AS created_at,
                                            FORMAT(i.total,2) AS total,
                                            st.name AS state,
                                            i.currency AS currency,
                                            i.concept AS concept,
                                            i.egress AS egress,
                                            u.name AS name,
                                            c.name AS company
                                            FROM invoices i
                                            INNER JOIN suppliers s
                                            ON s.id=i.supplier_id
                                            INNER JOIN invoice_logg l 
                                            ON l.invoice_id = i.id
                                            INNER JOIN users u
                                            ON u.id= l.next_user_id AND l.id = (SELECT MAX(id) FROM invoice_logg l WHERE l.invoice_id = i.id)
                                            INNER JOIN invoice_states st
                                            ON st.id=l.state_id
                                            LEFT JOIN companies c
                                            ON c.id=i.company
                                            ORDER BY i.created_at DESC");
    $total_records=count($total_data);
    $total_pages=ceil($total_records/$record_per_page);

    $data_information=DB::SELECT("SELECT   i.id AS id,
                                            i.file AS file,
                                            i.number AS number,
                                            s.name AS supplier,
                                            s.nit AS supplier_nit,
                                            DATE_FORMAT(i.created_at,'%Y-%m-%d') AS created_at,
                                            FORMAT(i.total,2) AS total,
                                            st.name AS state,
                                            CASE 
                                            WHEN i.currency IS NULL THEN ''
                                            ELSE i.currency
                                            END 
                                            AS currency,
                                            i.concept AS concept,
                                            CASE 
                                            WHEN i.egress IS NULL THEN ''
                                            ELSE i.egress
                                            END 
                                            AS egress,
                                            u.name AS name,
                                            CASE WHEN c.name IS NOT NULL THEN
                                            c.name
                                            ELSE '' 
                                            END AS company
                                            FROM invoices i
                                            INNER JOIN suppliers s
                                            ON s.id=i.supplier_id
                                            INNER JOIN invoice_logg l 
                                            ON l.invoice_id = i.id
                                            INNER JOIN users u
                                            ON u.id= l.next_user_id AND l.id = (SELECT MAX(id) FROM invoice_logg l WHERE l.invoice_id = i.id)
                                            INNER JOIN invoice_states st
                                            ON st.id=l.state_id
                                            LEFT JOIN companies c
                                            ON c.id=i.company
                                            ORDER BY i.created_at DESC LIMIT ?,?",[$start_from,$record_per_page]);

    $jsonArrayObject=(array('data_information'=>$data_information,'total_pages'=>$total_pages,'page'=>$page));  
    echo json_encode($jsonArrayObject);
    
}


public function load_data_anticipos(Request $request)
					{
					$record_per_page = 100;
					$page = '';
					$output = '';



					if (isset($request->page)) {
					$page = intval($request->page);
					} else {
					$page = 1;
					}

					$start_from = ($page - 1) * $record_per_page;

					$function_name1 = DB::SELECT('SELECT name AS name FROM functions WHERE id=?', [24]);
					$function_name2 = DB::SELECT('SELECT name AS name FROM functions WHERE id=?', [27]);

					$total_data = DB::SELECT("SELECT
												count(a.id) AS cantidad,
												a.id AS id,
												a.id_user AS id_user, 
												a.fecha_pago AS fecha_pago,
												a.valor_anticipo AS valor_anticipo,
												a.empresa AS empresa,
												a.forma_pago AS forma_pago,
												a.concepto AS concepto,
												p.name AS proveedor,
												us.name AS gestionando,
												usn.name AS name,
											CASE
											WHEN a.estado = 0 THEN 'En proceso...'
											WHEN a.estado = 1 THEN 'Aprobado'
											WHEN a.estado = 2 THEN 'Pagado'
											WHEN a.estado = 3 THEN 'Rechazado' 
											WHEN a.estado = 4 THEN 'Proceso legalización'
											WHEN a.estado = 5 THEN 'Legalización aprobada'
											WHEN a.estado = 6 THEN 'Legalización cerrada'
											WHEN a.estado = 7 THEN 'Legalización finalizada'
											WHEN a.estado = 8 THEN 'Legalización rechazada'       
											END AS estado,
											ad.files AS adjunto
											FROM anticipos a
											LEFT JOIN attacheds ad
											ON ad.id_relation = a.id
											INNER JOIN users us
											ON ad.next_user_id=us.id
											INNER JOIN users usn
											ON a.id_user =usn.id
											LEFT JOIN suppliers p
											ON p.id= a.proveedor
											WHERE (ad.name_module= ? OR ad.name_module= ?) AND 
												ad.id =(SELECT MAX(id) FROM attacheds ad WHERE ad.id_relation = a.id)
											GROUP BY a.id ORDER BY a.id DESC LIMIT ?,?;", [$function_name1[0]->name, $function_name2[0]->name,$start_from,$record_per_page]);

					$total_records = count($total_data);
					$total_pages = ceil($total_records / $record_per_page);

					$data_information = DB::SELECT("SELECT
												count(a.id) AS cantidad,
												a.id AS id,
												a.id_user AS id_user, 
												a.fecha_pago AS fecha_pago,
												a.valor_anticipo AS valor_anticipo,
												a.empresa AS empresa,
												a.forma_pago AS forma_pago,
												a.concepto AS concepto,
												p.name AS proveedor,
												us.name AS gestionando,
												usn.name AS name,
											CASE
											WHEN a.estado = 0 THEN 'En proceso...'
											WHEN a.estado = 1 THEN 'Aprobado'
											WHEN a.estado = 2 THEN 'Pagado'
											WHEN a.estado = 3 THEN 'Rechazado' 
											WHEN a.estado = 4 THEN 'Proceso legalización'
											WHEN a.estado = 5 THEN 'Legalización aprobada'
											WHEN a.estado = 6 THEN 'Legalización cerrada'
											WHEN a.estado = 7 THEN 'Legalización finalizada'
											WHEN a.estado = 8 THEN 'Legalización rechazada'       
											END AS estado,
											ad.files AS adjunto
											FROM anticipos a
											LEFT JOIN attacheds ad
											ON ad.id_relation = a.id
											INNER JOIN users us
											ON ad.next_user_id=us.id
											INNER JOIN users usn
											ON a.id_user =usn.id
											LEFT JOIN suppliers p
											ON p.id= a.proveedor
											WHERE (ad.name_module= ? OR ad.name_module= ?) AND 
												ad.id =(SELECT MAX(id) FROM attacheds ad WHERE ad.id_relation = a.id)
											GROUP BY a.id ORDER BY a.id DESC LIMIT ?,?;", [$function_name1[0]->name, $function_name2[0]->name,$start_from,$record_per_page]);

					$jsonArrayObject = (array('data_information' => $data_information, 'total_pages' => $total_pages, 'page' => $page));
					echo json_encode($jsonArrayObject);
					}



public function load_data_invoices_report(Request $request){
	$record_per_page=100;
	$page='';
	$output='';
	$part=1;

	if (isset($request->page)) {
		$page=intval($request->page);
	}else{
		$page=1;
	}

	$start_from = ($page - 1)*$record_per_page;

    $total_data=DB::SELECT("SELECT i.id AS id, 
									       i.number AS number,
										   s.name AS supplier,
										   f.name AS flow,
										   i.due_date AS due,
										   i.total AS total,
										   MAX(l.next_user_id) AS user,
										   CASE
										   WHEN MAX(l.state_id) = 1 THEN 'Radicada'
										   WHEN MAX(l.state_id) = 2 THEN 'Cancelada'
										   WHEN MAX(l.state_id) = 3 THEN 'Validada'
										   WHEN MAX(l.state_id) = 4 THEN 'Aprobada'
										   WHEN MAX(l.state_id) = 5 THEN 'Rechazada'
										   WHEN MAX(l.state_id) = 6 THEN 'Finalizada'
										   END AS state
									FROM invoices i
									LEFT JOIN suppliers s
									ON s.id= i.supplier_id
									LEFT JOIN invoice_flows f
									ON f.id= i.flow_id
									LEFT JOIN invoice_logg l 
									ON l.invoice_id=i.id
									LEFT JOIN invoice_states st 
									ON st.name=l.state_id
									LEFT JOIN users u
									ON u.id=l.next_user_id
									GROUP BY i.number,s.name,f.name,i.due_date,i.total,i.id
									ORDER BY i.number DESC,i.created_at DESC");
    $total_records=count($total_data);
    $total_pages=ceil($total_records/$record_per_page);

     $reference=$request->reference;
     $supplier=$request->supplier;
     $state=$request->state;
     $looking='';
    if ($reference != 0) {
    	$looking=1;
    	
    }
    if ($supplier != 0) {
    	$looking=2;
    	
    }
    if ($state != 0) {
    	$looking=3;
    	
    }
    
   if ($looking == 1) {
       		 $data_information=DB::SELECT("SELECT i.id AS id, 
							       i.number AS number,
								   s.name AS supplier,
								   f.name AS flow,
								   i.due_date AS due,
								   i.total AS total,
								   CASE
								   WHEN MAX(l.state_id) = 1 THEN 'Radicada'
								   WHEN MAX(l.state_id) = 2 THEN 'Cancelada'
								   WHEN MAX(l.state_id) = 3 THEN 'Validada'
								   WHEN MAX(l.state_id) = 4 THEN 'Aprobada'
								   WHEN MAX(l.state_id) = 5 THEN 'Rechazada'
								   WHEN MAX(l.state_id) = 6 THEN 'Finalizada'
								   END AS state
							FROM invoices i
							LEFT JOIN suppliers s
							ON s.id= i.supplier_id
							LEFT JOIN invoice_flows f
							ON f.id= i.flow_id
							LEFT JOIN invoice_logg l 
							ON l.invoice_id=i.id
							LEFT JOIN invoice_states st 
							ON st.name=l.state_id
							LEFT JOIN users u
							ON u.id=l.next_user_id
							WHERE i.id=?
							GROUP BY i.number,s.name,f.name,i.due_date,i.total,i.id
							ORDER BY i.number DESC",[$reference]);
   }else if($looking == 2){
       		 $data_information=DB::SELECT("SELECT i.id AS id, 
							       i.number AS number,
								   s.name AS supplier,
								   f.name AS flow,
								   i.due_date AS due,
								   i.total AS total,
								   CASE
								   WHEN MAX(l.state_id) = 1 THEN 'Radicada'
								   WHEN MAX(l.state_id) = 2 THEN 'Cancelada'
								   WHEN MAX(l.state_id) = 3 THEN 'Validada'
								   WHEN MAX(l.state_id) = 4 THEN 'Aprobada'
								   WHEN MAX(l.state_id) = 5 THEN 'Rechazada'
								   WHEN MAX(l.state_id) = 6 THEN 'Finalizada'
								   END AS state
							FROM invoices i
							LEFT JOIN suppliers s
							ON s.id= i.supplier_id
							LEFT JOIN invoice_flows f
							ON f.id= i.flow_id
							LEFT JOIN invoice_logg l 
							ON l.invoice_id=i.id
							LEFT JOIN invoice_states st 
							ON st.name=l.state_id
							LEFT JOIN users u
							ON u.id=l.next_user_id
							WHERE i.supplier_id=?
							GROUP BY i.number,s.name,f.name,i.due_date,i.total,i.id
							ORDER BY i.number LIMIT ?,?",[$supplier,$start_from,$record_per_page]);
   }else if($looking == 3){
       		 $data_information=DB::SELECT("SELECT i.id AS id, 
							       i.number AS number,
								   s.name AS supplier,
								   f.name AS flow,
								   i.due_date AS due,
								   i.total AS total,
								   CASE
								   WHEN MAX(l.state_id) = 1 THEN 'Radicada'
								   WHEN MAX(l.state_id) = 2 THEN 'Cancelada'
								   WHEN MAX(l.state_id) = 3 THEN 'Validada'
								   WHEN MAX(l.state_id) = 4 THEN 'Aprobada'
								   WHEN MAX(l.state_id) = 5 THEN 'Rechazada'
								   WHEN MAX(l.state_id) = 6 THEN 'Finalizada'
								   END AS state
							FROM invoices i
							LEFT JOIN suppliers s
							ON s.id= i.supplier_id
							LEFT JOIN invoice_flows f
							ON f.id= i.flow_id
							LEFT JOIN invoice_logg l 
							ON l.invoice_id=i.id
							LEFT JOIN invoice_states st 
							ON st.name=l.state_id
							LEFT JOIN users u
							ON u.id=l.next_user_id
							WHERE l.state_id=?
							GROUP BY i.number,s.name,f.name,i.due_date,i.total,i.id
							ORDER BY i.number DESC LIMIT ?,?",[$state,$start_from,$record_per_page]);
   }else{
       		 $data_information=DB::SELECT("SELECT i.id AS id, 
							       i.number AS number,
								   s.name AS supplier,
								   f.name AS flow,
								   i.due_date AS due,
								   i.total AS total,
								   CASE
								   WHEN MAX(l.state_id) = 1 THEN 'Radicada'
								   WHEN MAX(l.state_id) = 2 THEN 'Cancelada'
								   WHEN MAX(l.state_id) = 3 THEN 'Validada'
								   WHEN MAX(l.state_id) = 4 THEN 'Aprobada'
								   WHEN MAX(l.state_id) = 5 THEN 'Rechazada'
								   WHEN MAX(l.state_id) = 6 THEN 'Finalizada'
								   END AS state
							FROM invoices i
							LEFT JOIN suppliers s
							ON s.id= i.supplier_id
							LEFT JOIN invoice_flows f
							ON f.id= i.flow_id
							LEFT JOIN invoice_logg l 
							ON l.invoice_id=i.id
							LEFT JOIN invoice_states st 
							ON st.name=l.state_id
							LEFT JOIN users u
							ON u.id=l.next_user_id
							GROUP BY i.number,s.name,f.name,i.due_date,i.total,i.id
							ORDER BY i.number DESC LIMIT ?,?",[$start_from,$record_per_page]);   	
   }


    $jsonArrayObject=(array('data_information'=>$data_information,'total_pages'=>$total_pages,'page'=>$page,'part'=>$part));  
    echo json_encode($jsonArrayObject);
    
}


	public function invoicesfinder(Request $request){
		$user = Auth::user();
        $application = new Application();
        $modules = $application->getModules($user->id,6);
        $id_invoices=array();

        $number=array();
        $supplier=array();
        $creation_date=array();
        $total=array();
        $states=array();
        $currency=array();
        $concept=array();
        $name=array();
        $mainarray=array();

        $values=array();

        $dato_tabla= Invoice::orderBy('id','ASC')
                    ->get();

        $information_datas="SELECT i.id AS id,
        	                                    i.file AS file,
        	                                    i.number AS number,
												s.name AS supplier,
												s.nit AS supplier_nit,
												DATE_FORMAT(i.created_at,'%Y-%m-%d') AS created_at,
												FORMAT(i.total,2) AS total,
												st.name AS state,
												i.currency AS currency,
												i.concept AS concept,
												i.egress AS egress,
												u.name AS name,
	                                            CASE WHEN c.name IS NOT NULL THEN
	                                            c.name
	                                            ELSE '' 
	                                            END AS company
												FROM invoices i
												INNER JOIN suppliers s
												ON s.id=i.supplier_id
												INNER JOIN invoice_logg l 
												ON l.invoice_id = i.id
												INNER JOIN users u
												ON u.id= l.next_user_id AND l.id = (SELECT MAX(id) FROM invoice_logg l WHERE l.invoice_id = i.id)
												INNER JOIN invoice_states st
												ON st.id=l.state_id
	                                            LEFT JOIN companies c
	                                            ON c.id=i.company
												WHERE i.created_at IS NOT NULL ";
            if ($request->supplier != 0) {
            $information_datas .= 'AND i.supplier_id=? ';
            }

            if ($request->invoice != 0) {
            $information_datas .= 'AND i.id=? ';
            }

            if ($request->supplier_nit != 0) {
            $information_datas .= 'AND s.nit=? ';
            }

            if ($request->egress != 0) {
            $information_datas .= 'AND i.egress=? ';
            }

            
            $information_datas .= 'ORDER BY i.create_date DESC';

       if ((($request->fecha_inicial != NULL) && ($request->fecha_final != NULL)) || ($request->user != 0)) {
            if (($request->fecha_inicial != NULL) && ($request->fecha_final != NULL)) {
             $information_data=DB::SELECT("SELECT i.id AS id,
        	                                    i.file AS file,
        	                                    i.number AS number,
												s.name AS supplier,
												s.nit AS supplier_nit,
												DATE_FORMAT(i.created_at,'%Y-%m-%d') AS created_at,
												FORMAT(i.total,2) AS total,
												st.name AS state,
												i.currency AS currency,
												i.concept AS concept,
												i.egress AS egress,
												u.name AS name,
	                                            CASE WHEN c.name IS NOT NULL THEN
	                                            c.name
	                                            ELSE '' 
	                                            END AS company
												FROM invoices i
												INNER JOIN suppliers s
												ON s.id=i.supplier_id
												INNER JOIN invoice_logg l 
												ON l.invoice_id = i.id
												INNER JOIN users u
												ON u.id= l.next_user_id AND l.id = (SELECT MAX(id) FROM invoice_logg l WHERE l.invoice_id = i.id)
												INNER JOIN invoice_states st
												ON st.id=l.state_id
	                                            LEFT JOIN companies c
	                                            ON c.id=i.company												
												WHERE DATE_FORMAT(i.created_at,'%Y-%m-%d') BETWEEN ? AND ? ORDER BY i.created_at DESC",[$request->fecha_inicial,$request->fecha_final]);

            }

            if ($request->user != 0) {
            $ids_invoices=array();
            $ids_string="(";
            $ids_string_final=")";

            $id_invoices =DB::SELECT("SELECT invoice_id
                                      FROM invoice_logg
                                      WHERE user_id = ? GROUP BY invoice_id",[$request->user]);

            foreach ($id_invoices as $key ) {
            	array_push($ids_invoices, $key);
            
            }
            $array = json_decode(json_encode($ids_invoices), true);
            $primer_valor = $array[0]['invoice_id'];
            $cantidad=count($array);
            $ids_string = $ids_string .$array[0]['invoice_id'];
            for ($i=1; $i <$cantidad ; $i++) { 
            	$ids_string = $ids_string .",".$array[$i]['invoice_id'];
            }
            $ids_string = $ids_string .$ids_string_final;

            $information_data=DB::SELECT("SELECT i.id AS id,
        	                                    i.file AS file,
        	                                    i.number AS number,
												s.name AS supplier,
												s.nit AS supplier_nit,
												DATE_FORMAT(i.created_at,'%Y-%m-%d') AS created_at,
												FORMAT(i.total,2) AS total,
												st.name AS state,
												i.currency AS currency,
												i.concept AS concept,
												i.egress AS egress,
												u.name AS name,
	                                            CASE WHEN c.name IS NOT NULL THEN
	                                            c.name
	                                            ELSE '' 
	                                            END AS company
												FROM invoices i
												INNER JOIN suppliers s
												ON s.id=i.supplier_id
												INNER JOIN invoice_logg l 
												ON l.invoice_id = i.id
												INNER JOIN users u
												ON u.id= l.next_user_id AND l.id = (SELECT MAX(id) FROM invoice_logg l WHERE l.invoice_id = i.id)
												INNER JOIN invoice_states st
												ON st.id=l.state_id
	                                            LEFT JOIN companies c
	                                            ON c.id=i.company												
												WHERE i.id IN ".$ids_string." 
												ORDER BY i.created_at DESC");
            }



       }else{

            if ($request->supplier != 0) {
            array_push($values, $request->supplier);
            }

            if ($request->invoice != 0) {
            array_push($values, $request->invoice);
            }

            if ($request->supplier_nit != 0) {
            array_push($values, $request->supplier_nit);
            }

            if ($request->egress != 0) {
            array_push($values, $request->egress);
            }
            $information_data =DB::SELECT($information_datas,$values);

        }



        $egresos=DB::SELECT("SELECT  id AS id,
        	                        egress as egreso
        	                FROM  invoices
        	                WHERE egress IS NOT NULL");


        $suppliers=DB::SELECT("SELECT  id AS id,
        	                           nit as nit,
        	                           name AS name
        	                     FROM  suppliers");

        $invoices=DB::SELECT("SELECT  id AS id,
        	                           number AS number
        	                     FROM  invoices");

        $radication_time=DB::SELECT("SELECT  created_at AS radication_time,
        	                                 id AS id
        	                     FROM  invoices");

        $users=DB::SELECT("SELECT u.id AS id,
                                  u.name AS name
        	              FROM  users u
        	              INNER JOIN invoice_logg l
        	              ON l.user_id = u.id
        	              GROUP BY u.id");
    
       if (($request->supplier == 0) && ($request->invoice == 0) && ($request->supplier_nit == 0) && ($request->egress == 0) && ($request->user == 0) && ($request->fecha_inicial == NULL) && ($request->fecha_final == NULL)) {
           return view('reports/invoiceslist',['modules' => $modules,'user' => $user,'information_data'=>$information_data,'suppliers'=>$suppliers,'invoices'=>$invoices,'egresos'=>$egresos,'radication_time'=>$radication_time,'users'=>$users,'dato_tabla'=>$dato_tabla]);
        }else{
        return view('reports/invoiceslistfinder',['modules' => $modules,'user' => $user,'information_data'=>$information_data,'suppliers'=>$suppliers,'invoices'=>$invoices,'egresos'=>$egresos,'radication_time'=>$radication_time,'users'=>$users,'dato_tabla'=>$dato_tabla]);
        }
 
	}		


	public function directoryfinder(Request $request){
		$user = Auth::user();
        $application = new Application();
        $modules = $application->getModules($user->id,6);

        $input = $request->except('_token');

        $identificador_data=array();

      

        if (($request->profile == '0') && ($request->user != '0') && ($request->leader == '0') && ($request->ubication == '0')) {
			$datas=DB::SELECT("SELECT u.name AS name,
								      u.profile_name AS profile,
								       us.name AS lider,
								       u.ubication_name AS ubication,
								       CASE
								       WHEN u.email IS NOT NULL THEN u.email
								       WHEN u.email IS NULL THEN ''
								       END AS email,
								       u.extension AS extension,
								       u.phone AS phone,
								       DATE_FORMAT(u.start_date, '%Y-%m-%d') AS fecha_ingresa
								FROM users u
								LEFT JOIN users us
								ON us.id=u.leader_id
							    WHERE u.id=? AND 
								      u.active=?
								ORDER BY u.name ASC;",[$request->user,1]);	
        }

        if (($request->profile != '0') && ($request->user == '0') && ($request->leader == '0') && ($request->ubication == '0')) {

			$datas=DB::SELECT("SELECT u.name AS name,
								      u.profile_name AS profile,
								       us.name AS lider,
								       u.ubication_name AS ubication,
								       CASE
								       WHEN u.email IS NOT NULL THEN u.email
								       WHEN u.email IS NULL THEN ''
								       END AS email,
								       u.extension AS extension,
								       u.phone AS phone,
								       DATE_FORMAT(u.start_date, '%Y-%m-%d') AS fecha_ingresa
								FROM users u
								LEFT JOIN users us
								ON us.id=u.leader_id
							    WHERE u.profile_name=? AND 
								      u.active=?
								ORDER BY u.name ASC;",[$request->profile,1]);	
        }

        if (($request->profile == '0') && ($request->user == '0') && ($request->leader != '0') && ($request->ubication == '0')) {
			$datas=DB::SELECT("SELECT u.name AS name,
								      u.profile_name AS profile,
								       us.name AS lider,
								      u.ubication_name AS ubication,
								       CASE
								       WHEN u.email IS NOT NULL THEN u.email
								       WHEN u.email IS NULL THEN ''
								       END AS email,
								       u.extension AS extension,
								       u.phone AS phone,
								       DATE_FORMAT(u.start_date, '%Y-%m-%d') AS fecha_ingresa
								FROM users u
								LEFT JOIN users us
								ON us.id=u.leader_id
								WHERE u.leader_id=? AND
								      u.active = ?
								ORDER BY u.name ASC;",[$request->leader,1]);	
        }

        if (($request->profile == '0') && ($request->user == 0) && ($request->leader == 0) && ($request->ubication != '0')) {
			$datas=DB::SELECT("SELECT u.name AS name,
								      u.profile_name AS profile,
								       us.name AS lider,
								      u.ubication_name AS ubication,
								       CASE
								       WHEN u.email IS NOT NULL THEN u.email
								       WHEN u.email IS NULL THEN ''
								       END AS email,
								       u.extension AS extension,
								       u.phone AS phone,
								       DATE_FORMAT(u.start_date, '%Y-%m-%d') AS fecha_ingresa
								FROM users u
								LEFT JOIN users us
								ON us.id=u.leader_id
								WHERE u.ubication_name=? AND
								      u.active =?
								ORDER BY u.name ASC;",[$request->ubication,1]);	
        }

        if (($request->profile != '0') && ($request->user == '0') && ($request->leader == '0') && ($request->ubication != '0')) {
			$datas=DB::SELECT("SELECT u.name AS name,
								      u.profile_name AS profile,
								       us.name AS lider,
								      u.ubication_name AS ubication,
								       CASE
								       WHEN u.email IS NOT NULL THEN u.email
								       WHEN u.email IS NULL THEN ''
								       END AS email,
								       u.extension AS extension,
								       u.phone AS phone,
								       DATE_FORMAT(u.start_date, '%Y-%m-%d') AS fecha_ingresa
								FROM users u
								LEFT JOIN users us
								ON us.id=u.leader_id
								WHERE u.ubication_name=? AND
								      u.profile_name=? AND
								      u.active =?
								ORDER BY u.name ASC;",[$request->ubication,$request->profile,1]);	
        }

        if (($request->profile != '0') && ($request->user != '0') && ($request->leader == '0') && ($request->ubication == '0')) {

			$datas=DB::SELECT("SELECT u.name AS name,
								      u.profile_name AS profile,
								       us.name AS lider,
								       u.ubication_name AS ubication,
								       CASE
								       WHEN u.email IS NOT NULL THEN u.email
								       WHEN u.email IS NULL THEN ''
								       END AS email,
								       u.extension AS extension,
								       u.phone AS phone,
								       DATE_FORMAT(u.start_date, '%Y-%m-%d') AS fecha_ingresa
								FROM users u
								LEFT JOIN users us
								ON us.id=u.leader_id
								WHERE u.id=? AND
								      u.profile_name=? AND
								      u.active =?
								ORDER BY u.name ASC;",[$request->user,$request->profile,1]);	
        }

        if (($request->profile != '0') && ($request->user == '0') && ($request->leader != '0') && ($request->ubication == '0')) {
			$datas=DB::SELECT("SELECT u.name AS name,
								      u.profile_name AS profile,
								       us.name AS lider,
								      u.ubication_name AS ubication,
								       CASE
								       WHEN u.email IS NOT NULL THEN u.email
								       WHEN u.email IS NULL THEN ''
								       END AS email,
								       u.extension AS extension,
								       u.phone AS phone,
								       DATE_FORMAT(u.start_date, '%Y-%m-%d') AS fecha_ingresa
								FROM users u
								LEFT JOIN users us
								ON us.id=u.leader_id
								WHERE u.leader_id=? AND
								      u.profile_name=? AND
								      u.active =?
								ORDER BY u.name ASC;",[$request->leader,$request->profile,1]);	
        }

        if (($request->profile == '0') && ($request->user != '0') && ($request->leader != '0') && ($request->ubication == '0')) {
   
			$datas=DB::SELECT("SELECT u.name AS name,
								      u.profile_name AS profile,
								       us.name AS lider,
								      u.ubication_name AS ubication,
								       CASE
								       WHEN u.email IS NOT NULL THEN u.email
								       WHEN u.email IS NULL THEN ''
								       END AS email,
								       u.extension AS extension,
								       u.phone AS phone,
								       DATE_FORMAT(u.start_date, '%Y-%m-%d') AS fecha_ingresa
								FROM users u
								LEFT JOIN users us
								ON us.id=u.leader_id
								WHERE u.id=? AND
								      u.leader_id=? AND
								      u.active =?
								ORDER BY u.name ASC;",[$request->user,$request->leader,1]);	
        }

        if (($request->profile == '0') && ($request->user != '0') && ($request->leader == '0') && ($request->ubication != '0')) {
			$datas=DB::SELECT("SELECT u.name AS name,
								      u.profile_name AS profile,
								       us.name AS lider,
								      u.ubication_name AS ubication,
								       CASE
								       WHEN u.email IS NOT NULL THEN u.email
								       WHEN u.email IS NULL THEN ''
								       END AS email,
								       u.extension AS extension,
								       u.phone AS phone,
								       DATE_FORMAT(u.start_date, '%Y-%m-%d') AS fecha_ingresa
								FROM users u
								LEFT JOIN users us
								ON us.id=u.leader_id
								WHERE u.id=? AND
								      u.ubication_name=? AND
								      u.active =?
								ORDER BY u.name ASC;",[$request->user,$request->ubication,1]);	
        }

        if (($request->profile == '0') && ($request->user == '0') && ($request->leader != '0') && ($request->ubication != '0')) {
			$datas=DB::SELECT("SELECT u.name AS name,
								       u.profile_name AS profile,
								       us.name AS lider,
								       u.ubication_name AS ubication,
								       CASE
								       WHEN u.email IS NOT NULL THEN u.email
								       WHEN u.email IS NULL THEN ''
								       END AS email,
								       u.extension AS extension,
								       u.phone AS phone,
								       DATE_FORMAT(u.start_date, '%Y-%m-%d') AS fecha_ingresa
								FROM users u
								LEFT JOIN users us
								ON us.id=u.leader_id
								WHERE u.ubication_name=? AND
								      u.leader_id=? AND
								      u.active =?
								ORDER BY u.name ASC;",[$request->ubication,$request->leader,1]);	
        }

        if (($request->profile != '0') && ($request->user != '0') && ($request->leader != '0') && ($request->ubication != '0')) {
			$datas=DB::SELECT("SELECT u.name AS name,
								       u.profile_name AS profile,
								       us.name AS lider,
								       u.ubication_name AS ubication,
								       CASE
								       WHEN u.email IS NOT NULL THEN u.email
								       WHEN u.email IS NULL THEN ''
								       END AS email,
								       u.extension AS extension,
								       u.phone AS phone,
								       DATE_FORMAT(u.start_date, '%Y-%m-%d') AS fecha_ingresa
								FROM users u
								LEFT JOIN users us
								ON us.id=u.leader_id
								WHERE u.ubication_name=? AND
								      u.leader_id=? AND
								      u.id=? AND
								      u.profile_name=? AND
								      u.active =?
								ORDER BY u.name ASC;",[$request->ubication,$request->leader,$request->user,$request->profile,1]);	
        }

        if (($request->profile != '0') && ($request->user != '0') && ($request->leader != '0') && ($request->ubication == '0')) {
			$datas=DB::SELECT("SELECT u.name AS name,
								       u.profile_name AS profile,
								       us.name AS lider,
								       u.ubication_name AS ubication,
								       CASE
								       WHEN u.email IS NOT NULL THEN u.email
								       WHEN u.email IS NULL THEN ''
								       END AS email,
								       u.extension AS extension,
								       u.phone AS phone,
								       DATE_FORMAT(u.start_date, '%Y-%m-%d') AS fecha_ingresa
								FROM users u
								LEFT JOIN users us
								ON us.id=u.leader_id
								WHERE 
								      u.leader_id=? AND
								      u.id=? AND
								      u.profile_name=? AND
								      u.active =?
								ORDER BY u.name ASC;",[$request->leader,$request->user,$request->profile,1]);	
        }


        if (($request->profile != '0') && ($request->user == '0') && ($request->leader != '0') && ($request->ubication != '0')) {
			$datas=DB::SELECT("SELECT u.name AS name,
								      u.profile_name AS profile,
								       us.name AS lider,
								      u.ubication_name AS ubication,
								       CASE
								       WHEN u.email IS NOT NULL THEN u.email
								       WHEN u.email IS NULL THEN ''
								       END AS email,
								       u.extension AS extension,
								       u.phone AS phone,
								       DATE_FORMAT(u.start_date, '%Y-%m-%d') AS fecha_ingresa
								FROM users u
								LEFT JOIN users us
								ON us.id=u.leader_id
								WHERE 
								      u.leader_id=? AND
								      u.profile_name=? AND
								      u.ubication_name=? AND
								      u.active =?
								ORDER BY u.name ASC;",[$request->leader,$request->profile,$request->ubication,1]);	
        }



      if (($request->ubication == '0') && ($request->leader == '0') && ($request->profile == '0') && ($request->user == '0')) {
        $datas=DB::SELECT("SELECT u.name AS name,
								       u.profile_name AS profile,
								       us.name AS lider,
								       u.ubication_name AS ubication,
								       CASE
								       WHEN u.email IS NOT NULL THEN u.email
								       WHEN u.email IS NULL THEN ''
								       END AS email,
								       u.extension AS extension,
								       u.phone AS phone,
								       DATE_FORMAT(u.start_date, '%Y-%m-%d') AS fecha_ingresa
								FROM users u
								LEFT JOIN users us
								ON us.id=u.leader_id
								WHERE u.active=?
								ORDER BY u.name ASC",[1]);
      }

        if (($request->profile != '0') && ($request->user != '0') && ($request->leader == '0') && ($request->ubication != '0')) {
			$datas=DB::SELECT("SELECT u.name AS name,
								       u.profile_name AS profile,
								       us.name AS lider,
								       u.ubication_name AS ubication,
								       CASE
								       WHEN u.email IS NOT NULL THEN u.email
								       WHEN u.email IS NULL THEN ''
								       END AS email,
								       u.extension AS extension,
								       u.phone AS phone,
								       DATE_FORMAT(u.start_date, '%Y-%m-%d') AS fecha_ingresa
								FROM users u
								LEFT JOIN users us
								ON us.id=u.leader_id
								WHERE 
								      u.profile_name=? AND
								      u.id=? AND
								      u.ubication_id=? AND
								      u.active =?
								ORDER BY u.name ASC;",[$request->profile,$request->user,$request->ubication,1]);	
        }



        if (($request->profile == '0') && ($request->user != '0') && ($request->leader != '0') && ($request->ubication != '0')) {
			$datas=DB::SELECT("SELECT u.name AS name,
								       u.profile_name AS profile,
								       us.name AS lider,
								       u.ubication_name AS ubication,
								       CASE
								       WHEN u.email IS NOT NULL THEN u.email
								       WHEN u.email IS NULL THEN ''
								       END AS email,
								       u.extension AS extension,
								       u.phone AS phone,
								       DATE_FORMAT(u.start_date, '%Y-%m-%d') AS fecha_ingresa
								FROM users u
								LEFT JOIN users us
								ON us.id=u.leader_id
								WHERE 
								      u.leader_id=? AND
								      u.id=? AND
								      u.ubication_name=? AND
								      u.active =?
								ORDER BY u.name ASC;",[$request->leader,$request->user,$request->ubication,1]);	
        }




        $ubications=DB::SELECT('SELECT ubication_name as ubication_name from users where active=1 group by ubication_name');

        $users= User::where('active','=',1)
                    ->orderBy('id','ASC')
                    ->get();

		$profiles=DB::SELECT('SELECT profile_name as profile_name from users where active=1 group by profile_name');

        $leaders= DB::SELECT("SELECT  l.id AS id,
								        l.name AS name
								FROM users u
								INNER JOIN users l
								ON l.id=u.leader_id
								WHERE u.active=?
								GROUP BY l.id,l.name",[1]);


    

        return view('reports/directorio',['modules'=>$modules, 'user'=>$user, 'datas'=>$datas, 'profiles'=>$profiles, 'ubications'=>$ubications,'leaders'=>$leaders,'users'=>$users]);
	}


	public function costcenter(Request $request){
        $costcenters= DB::SELECT("SELECT  c.code AS code,
								          c.name AS name,
								          d.percentage AS percentage,
								          d.value AS value
								FROM cost_centers c
								INNER JOIN distributions d
								ON d.cost_center_id=c.id
								WHERE d.invoice_id = ?",[$request->id]);

        echo json_encode($costcenters);

    }


	public function adjuntosfiles(Request $request){
        $adjuntosfiles= DB::SELECT("SELECT DATE_FORMAT(i.created_at, '%Y-%m-%d') AS date,
								           u.name AS name,
								           CASE
								           WHEN i.file IS NOT NULL THEN i.file
								           ELSE ''  
								           END AS file
								FROM invoice_logg i
								INNER JOIN users u
								ON u.id=i.user_id
								WHERE i.invoice_id = ? AND
								      i.file IS NOT NULL",[$request->id]);

        echo json_encode($adjuntosfiles);



    }



	public function equivalente(Request $request){
		$user = Auth::user();
        $application = new Application();
        $modules = $application->getModules($user->id,6);
        $id_invoices=array();

        $datos=DB::SELECT("SELECT l.id AS id, 
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
							WHERE 
							  ef.id =(SELECT MAX(id) FROM equivalent_users_flow ef WHERE ef.id_equivalent = l.id) AND
							  ef.state <> 6
							GROUP BY ef.id_equivalent
							ORDER BY l.id DESC");

       $countDocuments= DB::SELECT('SELECT count(id) AS cantidad
        	                         FROM equivalent_users_flow
        	                         WHERE state = ?',[1]);


        return view('reports/equivalente',['modules' => $modules,'user' => $user,'datos'=>$datos,'countDocuments'=>$countDocuments[0]->cantidad]);
 
	}



	public function equivalentedit(Request $request){
		$user = Auth::user();
        $application = new Application();
        $modules = $application->getModules($user->id,6);
        

        $id_document=$request->document_id;


        $information = DB::SELECT('SELECT l.id AS id, 
							  CONCAT(r.prefijo,l.id_consecutive) AS 
							   numero_documento,
							   l.supplier AS proveedor,
							   l.id_supplier AS id_proveedor,
							   l.address AS address,
							   l.phone AS phone,
							   l.city AS city,
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
							WHERE 
							  l.id = ? AND
							  ef.id =(SELECT MAX(id) FROM equivalent_users_flow ef WHERE ef.id_equivalent = l.id) AND
							  ef.state <> 6
							GROUP BY ef.id_equivalent
							ORDER BY l.id DESC',[$id_document]);
        return view('reports/equivalentedit',['modules' => $modules,'user' => $user,'information'=>$information]);
 
	}     



	public function equivalentupdate(Request $request){
		$user = Auth::user();
        $application = new Application();
        $modules = $application->getModules($user->id,6);
        

        $id_document=$request->document_id;

        $document_state=0;

        if ($request->estado == 'Radicada') {
        	$document_state = 1;
        }else{
        	$document_state = 2;
        }



        $update = DB::UPDATE('UPDATE equivalent_log
        	                  SET city =?,
        	                      supplier = ?,
        	                      address = ?,
        	                      created_date = ?,
        	                      id_supplier = ?
        	                   WHERE id= ?',[$request->ciudad,$request->supplier_name,$request->address,$request->date_data,$request->supplier_id,$id_document]);

        $updatestate = DB::UPDATE('UPDATE equivalent_users_flow
        	                       SET state = ?
        	                       WHERE id_equivalent = ?',[$document_state,$id_document]);


        $datos=DB::SELECT("SELECT l.id AS id, 
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
							WHERE 
							  ef.id =(SELECT MAX(id) FROM equivalent_users_flow ef WHERE ef.id_equivalent = l.id) AND
							  ef.state <> 6
							GROUP BY ef.id_equivalent
							ORDER BY l.id DESC");

        $countDocuments= DB::SELECT('SELECT count(id) AS cantidad
        	                         FROM equivalent_users_flow
        	                         WHERE state = ?',[1]);



        return view('reports/equivalente',['modules' => $modules,'user' => $user,'datos'=>$datos,'countDocuments'=>$countDocuments[0]->cantidad]);
 
	}


	public function newclients(){
	    $user = Auth::user();
        $application = new Application();
        $modules = $application->getModules($user->id,6);

        $clientes= DB::SELECT('SELECT name AS nombre');

        return view('reports/clientes',['modules' => $modules,'user' => $user,'clientes'=>$clientes]);
	}


	public function users(){
	    $user = Auth::user();
        $application = new Application();
        $modules = $application->getModules($user->id,6);

        //$clientes= DB::SELECT('SELECT name AS nombre');

        //return view('reports/clientes',['modules' => $modules,'user' => $user,'clientes'=>$clientes]);
        $users = DB::SELECT("SELECT u.id AS id,
        	                        u.name AS name,
        	                        u.cedula AS cedula,
        	                        u.profile_name AS cargo,
        	                        u.ubication_name AS ubicacion,        	                        

        	                        CASE
                                    WHEN u.email IS NULL THEN ''
                                    ELSE u.email
                                    END AS email,
        	                        CASE
        	                        WHEN u.vehiculo_marca IS NULL THEN ''
        	                        ELSE u.vehiculo_marca
        	                        END AS vehiculo,
        	                        CASE
        	                        WHEN u.vehiculo_modelo IS NULL THEN ''
        	                        ELSE u.vehiculo_modelo
        	                        END AS modelo,
        	                        CASE 
        	                        WHEN u.cantidad_mascotas IS NULL THEN 0
        	                        ELSE u.cantidad_mascotas
        	                        END AS cantidad_mascotas,
        	                        CASE 
        	                        WHEN u.especie_mascotas IS NULL THEN ''
        	                        ELSE u.especie_mascotas
        	                        END AS especie_mascotas,
        	                        CASE 
        	                        WHEN u.nombre_mascotas IS NULL THEN ''
        	                        ELSE u.nombre_mascotas
        	                        END AS nombre_mascotas,
        	                        CASE 
        	                        WHEN u.tipo_vivienda IS NULL THEN ''
        	                        ELSE u.tipo_vivienda
        	                        END AS tipo_vivienda,
        	                        CASE 
        	                        WHEN u.gender IS NULL THEN ''
        	                        ELSE u.gender
        	                        END AS gender,

        	                        CASE 
        	                        WHEN u.celular_personal IS NULL THEN ''
        	                        ELSE u.celular_personal
        	                        END AS celular_personal,

        	                        CASE 
        	                        WHEN u.telefono_fijo IS NULL THEN ''
        	                        ELSE u.telefono_fijo
        	                        END AS telefono_fijo,

        	                        CASE 
        	                        WHEN u.direccion_residencia IS NULL THEN ''
        	                        ELSE u.direccion_residencia
        	                        END AS direccion_residencia,

        	                        CASE 
        	                        WHEN u.barrio IS NULL THEN ''
        	                        ELSE u.barrio
        	                        END AS barrio,

        	                        CASE 
        	                        WHEN u.nombre_unidad IS NULL THEN ''
        	                        ELSE u.nombre_unidad
        	                        END AS nombre_unidad,

        	                        CASE 
        	                        WHEN u.apto IS NULL THEN ''
        	                        ELSE u.apto
        	                        END AS apto,

        	                        CASE 
        	                        WHEN u.ciudad IS NULL THEN ''
        	                        ELSE u.ciudad
        	                        END AS ciudad,

        	                        CASE 
        	                        WHEN u.estado_civil IS NULL THEN ''
        	                        ELSE u.estado_civil
        	                        END AS estado_civil,

        	                        CASE 
        	                        WHEN u.conyuge IS NULL THEN ''
        	                        ELSE u.conyuge
        	                        END AS conyuge,

        	                        CASE 
        	                        WHEN u.grupo_sanguineo IS NULL THEN ''
        	                        ELSE u.grupo_sanguineo
        	                        END AS grupo_sanguineo,

        	                        CASE 
        	                        WHEN u.posee_hijos IS NULL THEN ''
        	                        ELSE u.posee_hijos
        	                        END AS posee_hijos,

        	                        CASE 
        	                        WHEN u.cantidad_hijos IS NULL THEN ''
        	                        ELSE u.cantidad_hijos
        	                        END AS cantidad_hijos,

        	                        CASE 
        	                        WHEN u.contacto_emergencia IS NULL THEN ''
        	                        ELSE u.contacto_emergencia
        	                        END AS contacto_emergencia,

        	                        CASE 
        	                        WHEN u.parentesco_contacto_emergencia IS NULL THEN ''
        	                        ELSE u.parentesco_contacto_emergencia
        	                        END AS parentesco_contacto_emergencia,

        	                        CASE 
        	                        WHEN u.celular_contacto_emergencia IS NULL THEN ''
        	                        ELSE u.celular_contacto_emergencia
        	                        END AS celular_contacto_emergencia,

                                    CASE
                                    WHEN u.nivel_estudios IS NULL THEN ''
                                    ELSE u.nivel_estudios
                                    END AS nivel_estudios,                                  

                                    CASE
                                    WHEN u.formacion_actual IS NULL THEN ''
                                    ELSE u.formacion_actual
                                    END AS formacion_actual,

                                    CASE
                                    WHEN u.estudia_actualmente IS NULL THEN ''
                                    ELSE u.estudia_actualmente
                                    END AS estudia_actualmente,

                                    CASE
                                    WHEN u.estudio_actual IS NULL THEN ''
                                    ELSE u.estudio_actual
                                    END AS estudio_actual,

                                    CASE
                                    WHEN u.eps IS NULL THEN ''
                                    ELSE u.eps
                                    END AS eps,

                                    CASE
                                    WHEN u.fondo_pensiones IS NULL THEN ''
                                    ELSE u.fondo_pensiones
                                    END AS fondo_pensiones,

                                    CASE
                                    WHEN u.fondo_cesantias IS NULL THEN ''
                                    ELSE u.fondo_cesantias
                                    END AS fondo_cesantias,

                                    CASE
                                    WHEN u.grupo_trabajo IS NULL THEN ''
                                    ELSE u.grupo_trabajo
                                    END AS grupo_trabajo,

                                    CASE
                                    WHEN u.sede_labora IS NULL THEN ''
                                    ELSE u.sede_labora
                                    END AS sede_labora,

                                    CASE
                                    WHEN u.tipo_vehiculo IS NULL THEN ''
                                    ELSE u.tipo_vehiculo
                                    END AS tipo_vehiculo,

                                    CASE
                                    WHEN u.placa_vehiculo IS NULL THEN ''
                                    ELSE u.placa_vehiculo
                                    END AS placa_vehiculo,

                                    CASE
                                    WHEN u.experiencia_conduccion IS NULL THEN ''
                                    ELSE u.experiencia_conduccion
                                    END AS experiencia_conduccion,

                                    CASE
                                    WHEN u.categoria_licencia IS NULL THEN ''
                                    ELSE u.categoria_licencia
                                    END AS categoria_licencia,

                                    CASE
                                    WHEN u.vencimiento_licencia IS NULL THEN ''
                                    ELSE u.vencimiento_licencia
                                    END AS vencimiento_licencia

        	                        FROM users u
        	                        INNER JOIN profiles c 
        	                        ON c.id=u.profile_id
        	                        INNER JOIN ubications a
        	                        ON a.id=u.ubication_id
        	                        WHERE u.active=1
        	                        ORDER BY u.name ASC;");

                    $hijos = DB::table('hijos_usuarios')->get();
        


       return view('reports/users',['modules' => $modules,'user' => $user,'users'=>$users,'hijos'=>$hijos]);
	}   

  


	public function usersfinder(Request $request){
	    $user = Auth::user();
        $application = new Application();
        $modules = $application->getModules($user->id,6);

        if ($request->user != '0') {
          $users =DB::SELECT("SELECT u.name AS name,
        	                        u.cedula AS cedula,
        	                        u.profile_name AS cargo,
        	                        u.ubication_name AS ubicacion,
        	                        CASE
                                    WHEN u.id IS NULL THEN ''
                                    ELSE u.id
                                    END AS id,
        	                       	CASE
                                    WHEN u.email IS NULL THEN ''
                                    ELSE u.email
                                    END AS email,
        	                        CASE
        	                        WHEN u.vehiculo_marca IS NULL THEN ''
        	                        ELSE u.vehiculo_marca
        	                        END AS vehiculo,
        	                        CASE
        	                        WHEN u.vehiculo_modelo IS NULL THEN ''
        	                        ELSE u.vehiculo_modelo
        	                        END AS modelo,
        	                        CASE 
        	                        WHEN u.cantidad_mascotas IS NULL THEN 0
        	                        ELSE u.cantidad_mascotas
        	                        END AS cantidad_mascotas,
        	                        CASE 
        	                        WHEN u.especie_mascotas IS NULL THEN ''
        	                        ELSE u.especie_mascotas
        	                        END AS especie_mascotas,
        	                        CASE 
        	                        WHEN u.nombre_mascotas IS NULL THEN ''
        	                        ELSE u.nombre_mascotas
        	                        END AS nombre_mascotas,
        	                        CASE 
        	                        WHEN u.tipo_vivienda IS NULL THEN ''
        	                        ELSE u.tipo_vivienda
        	                        END AS tipo_vivienda,
        	                        CASE 
        	                        WHEN u.gender IS NULL THEN ''
        	                        ELSE u.gender
        	                        END AS gender,

        	                        CASE 
        	                        WHEN u.celular_personal IS NULL THEN ''
        	                        ELSE u.celular_personal
        	                        END AS celular_personal,

        	                        CASE 
        	                        WHEN u.telefono_fijo IS NULL THEN ''
        	                        ELSE u.telefono_fijo
        	                        END AS telefono_fijo,

        	                        CASE 
        	                        WHEN u.direccion_residencia IS NULL THEN ''
        	                        ELSE u.direccion_residencia
        	                        END AS direccion_residencia,

        	                        CASE 
        	                        WHEN u.barrio IS NULL THEN ''
        	                        ELSE u.barrio
        	                        END AS barrio,

        	                        CASE 
        	                        WHEN u.nombre_unidad IS NULL THEN ''
        	                        ELSE u.nombre_unidad
        	                        END AS nombre_unidad,

        	                        CASE 
        	                        WHEN u.apto IS NULL THEN ''
        	                        ELSE u.apto
        	                        END AS apto,

        	                        CASE 
        	                        WHEN u.ciudad IS NULL THEN ''
        	                        ELSE u.ciudad
        	                        END AS ciudad,

        	                        CASE 
        	                        WHEN u.estado_civil IS NULL THEN ''
        	                        ELSE u.estado_civil
        	                        END AS estado_civil,

        	                        CASE 
        	                        WHEN u.conyuge IS NULL THEN ''
        	                        ELSE u.conyuge
        	                        END AS conyuge,

        	                        CASE 
        	                        WHEN u.grupo_sanguineo IS NULL THEN ''
        	                        ELSE u.grupo_sanguineo
        	                        END AS grupo_sanguineo,

        	                        CASE 
        	                        WHEN u.posee_hijos IS NULL THEN ''
        	                        ELSE u.posee_hijos
        	                        END AS posee_hijos,

        	                        CASE 
        	                        WHEN u.cantidad_hijos IS NULL THEN ''
        	                        ELSE u.cantidad_hijos
        	                        END AS cantidad_hijos,

        	                        CASE 
        	                        WHEN u.contacto_emergencia IS NULL THEN ''
        	                        ELSE u.contacto_emergencia
        	                        END AS contacto_emergencia,

        	                        CASE 
        	                        WHEN u.parentesco_contacto_emergencia IS NULL THEN ''
        	                        ELSE u.parentesco_contacto_emergencia
        	                        END AS parentesco_contacto_emergencia,

        	                        CASE 
        	                        WHEN u.celular_contacto_emergencia IS NULL THEN ''
        	                        ELSE u.celular_contacto_emergencia
        	                        END AS celular_contacto_emergencia,

                                    CASE
                                    WHEN u.nivel_estudios IS NULL THEN ''
                                    ELSE u.nivel_estudios
                                    END AS nivel_estudios,                                  

                                    CASE
                                    WHEN u.formacion_actual IS NULL THEN ''
                                    ELSE u.formacion_actual
                                    END AS formacion_actual,

                                    CASE
                                    WHEN u.estudia_actualmente IS NULL THEN ''
                                    ELSE u.estudia_actualmente
                                    END AS estudia_actualmente,

                                    CASE
                                    WHEN u.estudio_actual IS NULL THEN ''
                                    ELSE u.estudio_actual
                                    END AS estudio_actual,

                                    CASE
                                    WHEN u.eps IS NULL THEN ''
                                    ELSE u.eps
                                    END AS eps,

                                    CASE
                                    WHEN u.fondo_pensiones IS NULL THEN ''
                                    ELSE u.fondo_pensiones
                                    END AS fondo_pensiones,

                                    CASE
                                    WHEN u.fondo_cesantias IS NULL THEN ''
                                    ELSE u.fondo_cesantias
                                    END AS fondo_cesantias,

                                    CASE
                                    WHEN u.grupo_trabajo IS NULL THEN ''
                                    ELSE u.grupo_trabajo
                                    END AS grupo_trabajo,

                                    CASE
                                    WHEN u.sede_labora IS NULL THEN ''
                                    ELSE u.sede_labora
                                    END AS sede_labora,

                                    CASE
                                    WHEN u.tipo_vehiculo IS NULL THEN ''
                                    ELSE u.tipo_vehiculo
                                    END AS tipo_vehiculo,

                                    CASE
                                    WHEN u.placa_vehiculo IS NULL THEN ''
                                    ELSE u.placa_vehiculo
                                    END AS placa_vehiculo,

                                    CASE
                                    WHEN u.experiencia_conduccion IS NULL THEN ''
                                    ELSE u.experiencia_conduccion
                                    END AS experiencia_conduccion,

                                    CASE
                                    WHEN u.categoria_licencia IS NULL THEN ''
                                    ELSE u.categoria_licencia
                                    END AS categoria_licencia,

                                    CASE
                                    WHEN u.vencimiento_licencia IS NULL THEN ''
                                    ELSE u.vencimiento_licencia
                                    END AS vencimiento_licencia

        	                        FROM users u
        	                        INNER JOIN profiles c 
        	                        ON c.id=u.profile_id
        	                        INNER JOIN ubications a
        	                        ON a.id=u.ubication_id
        	                        WHERE u.active=1 AND 
        	                        u.cedula = ?",[$request->user]);

  					
        }else{

          $users =DB::SELECT("SELECT u.name AS name,
        	                        u.cedula AS cedula,
        	                        u.profile_name AS cargo,
        	                        u.ubication_name AS ubicacion,
        	                        CASE
                                    WHEN u.id IS NULL THEN ''
                                    ELSE u.id
                                    END AS id,
        	                       	CASE
                                    WHEN u.email IS NULL THEN ''
                                    ELSE u.email
                                    END AS email,
        	                        CASE
        	                        WHEN u.vehiculo_marca IS NULL THEN ''
        	                        ELSE u.vehiculo_marca
        	                        END AS vehiculo,
        	                        CASE
        	                        WHEN u.vehiculo_modelo IS NULL THEN ''
        	                        ELSE u.vehiculo_modelo
        	                        END AS modelo,
        	                        CASE 
        	                        WHEN u.cantidad_mascotas IS NULL THEN 0
        	                        ELSE u.cantidad_mascotas
        	                        END AS cantidad_mascotas,
        	                        CASE 
        	                        WHEN u.especie_mascotas IS NULL THEN ''
        	                        ELSE u.especie_mascotas
        	                        END AS especie_mascotas,
        	                        CASE 
        	                        WHEN u.nombre_mascotas IS NULL THEN ''
        	                        ELSE u.nombre_mascotas
        	                        END AS nombre_mascotas,
        	                        CASE 
        	                        WHEN u.tipo_vivienda IS NULL THEN ''
        	                        ELSE u.tipo_vivienda
        	                        END AS tipo_vivienda,
        	                        CASE 
        	                        WHEN u.gender IS NULL THEN ''
        	                        ELSE u.gender
        	                        END AS gender,

        	                        CASE 
        	                        WHEN u.celular_personal IS NULL THEN ''
        	                        ELSE u.celular_personal
        	                        END AS celular_personal,

        	                        CASE 
        	                        WHEN u.telefono_fijo IS NULL THEN ''
        	                        ELSE u.telefono_fijo
        	                        END AS telefono_fijo,

        	                        CASE 
        	                        WHEN u.direccion_residencia IS NULL THEN ''
        	                        ELSE u.direccion_residencia
        	                        END AS direccion_residencia,

        	                        CASE 
        	                        WHEN u.barrio IS NULL THEN ''
        	                        ELSE u.barrio
        	                        END AS barrio,

        	                        CASE 
        	                        WHEN u.nombre_unidad IS NULL THEN ''
        	                        ELSE u.nombre_unidad
        	                        END AS nombre_unidad,

        	                        CASE 
        	                        WHEN u.apto IS NULL THEN ''
        	                        ELSE u.apto
        	                        END AS apto,

        	                        CASE 
        	                        WHEN u.ciudad IS NULL THEN ''
        	                        ELSE u.ciudad
        	                        END AS ciudad,

        	                        CASE 
        	                        WHEN u.estado_civil IS NULL THEN ''
        	                        ELSE u.estado_civil
        	                        END AS estado_civil,

        	                        CASE 
        	                        WHEN u.conyuge IS NULL THEN ''
        	                        ELSE u.conyuge
        	                        END AS conyuge,

        	                        CASE 
        	                        WHEN u.grupo_sanguineo IS NULL THEN ''
        	                        ELSE u.grupo_sanguineo
        	                        END AS grupo_sanguineo,

        	                        CASE 
        	                        WHEN u.posee_hijos IS NULL THEN ''
        	                        ELSE u.posee_hijos
        	                        END AS posee_hijos,

        	                        CASE 
        	                        WHEN u.cantidad_hijos IS NULL THEN ''
        	                        ELSE u.cantidad_hijos
        	                        END AS cantidad_hijos,

        	                        CASE 
        	                        WHEN u.contacto_emergencia IS NULL THEN ''
        	                        ELSE u.contacto_emergencia
        	                        END AS contacto_emergencia,

        	                        CASE 
        	                        WHEN u.parentesco_contacto_emergencia IS NULL THEN ''
        	                        ELSE u.parentesco_contacto_emergencia
        	                        END AS parentesco_contacto_emergencia,

        	                        CASE 
        	                        WHEN u.celular_contacto_emergencia IS NULL THEN ''
        	                        ELSE u.celular_contacto_emergencia
        	                        END AS celular_contacto_emergencia,

                                    CASE
                                    WHEN u.nivel_estudios IS NULL THEN ''
                                    ELSE u.nivel_estudios
                                    END AS nivel_estudios,                                  

                                    CASE
                                    WHEN u.formacion_actual IS NULL THEN ''
                                    ELSE u.formacion_actual
                                    END AS formacion_actual,

                                    CASE
                                    WHEN u.estudia_actualmente IS NULL THEN ''
                                    ELSE u.estudia_actualmente
                                    END AS estudia_actualmente,

                                    CASE
                                    WHEN u.estudio_actual IS NULL THEN ''
                                    ELSE u.estudio_actual
                                    END AS estudio_actual,

                                    CASE
                                    WHEN u.eps IS NULL THEN ''
                                    ELSE u.eps
                                    END AS eps,

                                    CASE
                                    WHEN u.fondo_pensiones IS NULL THEN ''
                                    ELSE u.fondo_pensiones
                                    END AS fondo_pensiones,

                                    CASE
                                    WHEN u.fondo_cesantias IS NULL THEN ''
                                    ELSE u.fondo_cesantias
                                    END AS fondo_cesantias,

                                    CASE
                                    WHEN u.grupo_trabajo IS NULL THEN ''
                                    ELSE u.grupo_trabajo
                                    END AS grupo_trabajo,

                                    CASE
                                    WHEN u.sede_labora IS NULL THEN ''
                                    ELSE u.sede_labora
                                    END AS sede_labora,

                                    CASE
                                    WHEN u.tipo_vehiculo IS NULL THEN ''
                                    ELSE u.tipo_vehiculo
                                    END AS tipo_vehiculo,

                                    CASE
                                    WHEN u.placa_vehiculo IS NULL THEN ''
                                    ELSE u.placa_vehiculo
                                    END AS placa_vehiculo,

                                    CASE
                                    WHEN u.experiencia_conduccion IS NULL THEN ''
                                    ELSE u.experiencia_conduccion
                                    END AS experiencia_conduccion,

                                    CASE
                                    WHEN u.categoria_licencia IS NULL THEN ''
                                    ELSE u.categoria_licencia
                                    END AS categoria_licencia,

                                    CASE
                                    WHEN u.vencimiento_licencia IS NULL THEN ''
                                    ELSE u.vencimiento_licencia
                                    END AS vencimiento_licencia

        	                        FROM users u
        	                        INNER JOIN profiles c 
        	                        ON c.id=u.profile_id
        	                        INNER JOIN ubications a
        	                        ON a.id=u.ubication_id
        	                        WHERE u.active=?",[1]);

        }
        $hijos = DB::table('hijos_usuarios')->get();

 
         
        return view('reports/users',['modules' => $modules,'user' => $user,'users'=>$users,'hijos'=>$hijos]);

	} 

	public function newrappi()
	{
		$user = Auth::user();
        $application = new Application();
        $modules = $application->getModules($user->id,6);

			

		return view('reports/informerappi',['modules' => $modules,'user' => $user]);
	}


	public function anticipo(Request $request){
		$user = Auth::user();
        $application = new Application();
        $modules = $application->getModules($user->id,6);
        $id_invoices=array();

        $function_name1=DB::SELECT('SELECT name AS name FROM functions WHERE id=?',[24]);
        $function_name2=DB::SELECT('SELECT name AS name FROM functions WHERE id=?',[27]);

        $datos=DB::SELECT("SELECT
        	                     count(a.id) AS cantidad,
								 a.id AS id,
								 a.id_user AS id_user, 
								 a.fecha_pago AS fecha_pago,
								 a.valor_anticipo AS valor_anticipo,
								 a.empresa AS empresa,
								 a.forma_pago AS forma_pago,
								 a.concepto AS concepto,
								 p.name AS proveedor,
								 us.name AS gestionando,
								 usn.name AS name,
								CASE
								WHEN a.estado = 0 THEN 'En proceso...'
								WHEN a.estado = 1 THEN 'Aprobado'
								WHEN a.estado = 2 THEN 'Pagado'
								WHEN a.estado = 3 THEN 'Rechazado' 
								WHEN a.estado = 4 THEN 'Proceso legalización'
								WHEN a.estado = 5 THEN 'Legalización aprobada'
	                            WHEN a.estado = 6 THEN 'Legalización cerrada'
	                            WHEN a.estado = 7 THEN 'Legalización finalizada'
	                            WHEN a.estado = 8 THEN 'Legalización rechazada'       
								END AS estado,
								ad.files AS adjunto
								FROM anticipos a
								LEFT JOIN attacheds ad
								ON ad.id_relation = a.id
								INNER JOIN users us
								ON ad.next_user_id=us.id
								INNER JOIN users usn
								ON a.id_user =usn.id
								LEFT JOIN suppliers p
								ON p.id= a.proveedor
								WHERE (ad.name_module= ? OR ad.name_module= ?) AND 
								  ad.id =(SELECT MAX(id) FROM attacheds ad WHERE ad.id_relation = a.id)
								GROUP BY a.id ORDER BY a.id DESC;",[$function_name1[0]->name,$function_name2[0]->name]);

        $users=DB::SELECT('SELECT id AS id,
        	               name AS name FROM users');

       
       $countDocuments= count($datos);


       return view('reports/anticipo',['modules' => $modules,'user' => $user,'datos'=>$datos,'countDocuments'=>$countDocuments,'users'=>$users]);
 
	}


	public function anticiposfinder(Request $request){
		$user = Auth::user();
        $application = new Application();
        $modules = $application->getModules($user->id,6);
        $id_invoices=array();

		$fecha_inicial = $request->fecha_inicial;
		$fecha_final = $request->fecha_final;
		$user_form = $request->user;

        $function_name1=DB::SELECT('SELECT name AS name FROM functions WHERE id=?',[24]);
        $function_name2=DB::SELECT('SELECT name AS name FROM functions WHERE id=?',[27]);

		$where = '';
		if( $fecha_inicial != '' && $fecha_final != '' && $user_form != 0 ){
			$where .= ' AND a.fecha_pago BETWEEN CAST(\''.$fecha_inicial.'\' AS DATE) AND CAST(\''.$fecha_final.'\' AS DATE) AND a.id_user = '.$user_form.' ';
		}
		if( $fecha_inicial != '' && $fecha_final != '' && $user_form == 0 ){
			$where .= ' AND a.fecha_pago BETWEEN CAST(\''.$fecha_inicial.'\' AS DATE) AND CAST(\''.$fecha_final.'\' AS DATE) ';
		}
		if( $fecha_inicial != '' && $fecha_final == '' && $user_form != 0 ){
			$where .= ' AND a.fecha_pago >= CAST(\''.$fecha_inicial.'\' AS DATE) AND a.id_user = '.$user_form.' ';
		}
		if( $fecha_inicial != '' && $fecha_final == '' && $user_form == 0 ){
			$where .= ' AND a.fecha_pago >= CAST(\''.$fecha_inicial.'\' AS DATE) ';
		}
		if( $fecha_inicial == '' && $fecha_final != '' && $user_form != 0 ){
			$where .= ' AND a.fecha_pago <= CAST(\''.$fecha_final.'\' AS DATE) AND a.id_user = '.$user_form.' ';
		}
		if( $fecha_inicial == '' && $fecha_final != '' && $user_form == 0 ){
			$where .= ' AND a.fecha_pago <= CAST(\''.$fecha_final.'\' AS DATE) ';
		}
		if( $fecha_inicial == '' && $fecha_final == '' && $user_form != 0 ){
			$where .= ' AND a.id_user = '.$user_form.' ';
		}
		//echo '<script>console.log("Where:", "'.$where.'")</script>';

        $datos=DB::SELECT("SELECT
        	                     count(a.id) AS cantidad,
								 a.id AS id,
								 a.id_user AS id_user, 
								 a.fecha_pago AS fecha_pago,
								 a.valor_anticipo AS valor_anticipo,
								 a.empresa AS empresa,
								 a.forma_pago AS forma_pago,
								 a.concepto AS concepto,
								 p.name AS proveedor,
								 us.name AS gestionando,
								 usn.name AS name,
								CASE
								WHEN a.estado = 0 THEN 'En proceso...'
								WHEN a.estado = 1 THEN 'Aprobado'
								WHEN a.estado = 2 THEN 'Pagado'
								WHEN a.estado = 3 THEN 'Rechazado' 
								WHEN a.estado = 4 THEN 'Proceso legalización'
								WHEN a.estado = 5 THEN 'Legalización aprobada'
	                            WHEN a.estado = 6 THEN 'Legalización cerrada'
	                            WHEN a.estado = 7 THEN 'Legalización finalizada'
	                            WHEN a.estado = 8 THEN 'Legalización rechazada'       
								END AS estado,
								ad.files AS adjunto
								FROM anticipos a
								LEFT JOIN attacheds ad
								ON ad.id_relation = a.id
								INNER JOIN users us
								ON ad.next_user_id=us.id
								INNER JOIN users usn
								ON a.id_user =usn.id
								LEFT JOIN suppliers p
								ON p.id= a.proveedor
								WHERE (ad.name_module= ? OR ad.name_module= ?) AND 
								  ad.id =(SELECT MAX(id) FROM attacheds ad WHERE ad.id_relation = a.id) 
								".$where."
								GROUP BY a.id ORDER BY a.id DESC;",[$function_name1[0]->name,$function_name2[0]->name]);

        $users=DB::SELECT('SELECT id AS id,
        	               name AS name FROM users');

       
       $countDocuments= count($datos);


       return view('reports/anticipo',['modules' => $modules,'user' => $user,'datos'=>$datos,'countDocuments'=>$countDocuments,'users'=>$users]);
 
	}


	public function costcenteranticipos(Request $request){


        $costcenters= DB::SELECT("SELECT  d.value AS monto,
        	                              c.name AS centcost,
        	                              cu.Cuenta AS cuenta
        	                      FROM distributions_legalizacion d
        	                      INNER JOIN cost_centers c
        	                      ON c.id=d.cost_center_id
        	                      INNER JOIN cuentas_cecos cu
        	                      ON cu.id=d.cuenta
        	                      WHERE d.anticipo_id=?",[$request->id]);

       /* $costcenters= DB::SELECT("SELECT  num_factura AS factura,
								          monto AS monto,
								          centcost AS centcost
								FROM anticipos 
								WHERE id = ?",[$request->id]);*/

        echo json_encode($costcenters);

    }
	
	

	public function flujoanticipos(Request $request){


		$flujo_anticipos= DB::SELECT("SELECT u.name AS nombre,
												us.name AS nombres,
												a.type_document AS tipo,
												a.created_at AS fecha
										FROM anticipos_log a
										INNER JOIN users u ON 
										u.id=a.user_id
										INNER JOIN users us ON
										us.id=a.next_user_id
										WHERE a.id_document=?
										ORDER BY a.created_at ASC",[$request->id]);
	
		   /* $costcenters= DB::SELECT("SELECT  num_factura AS factura,
											  monto AS monto,
											  centcost AS centcost
									FROM anticipos 
									WHERE id = ?",[$request->id]);*/
	
			echo json_encode($flujo_anticipos);
	
		}  
   





	}

