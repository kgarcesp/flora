@extends('layouts.app')

@section('content')
<div class="container invoice-area">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">Facturas por Gestionar</div>
                <div class="card-body"><h5>Facturas pendientes:{{$countInvoices}}</h5></div>
                @if($user->profile_name == 'COORDINADORA DE TESORERIA')
                <a href="{{url('invoice/aprobacion_masiva')}}"><div class="card-body"><button type="button" class="btn btn-danger">Autorización masiva</button></div></a>
                @endif                
                <div class="card-body">
                  @if($countInvoices>0)
                    <table class="table-responsive-md table-bordered table-striped table-sm" cellspacing="0" width="100%">
                        <thead>
                            <tr>
                              <th></th>
                              <th>Proveedor</th>
                              <th>Factura</th>
                              <th>Subtotal</th>
                              <th>IVA</th>
                              <th>Total</th>
                              <th>Moneda</th>
                              <th>Vencimiento</th>
                              <th>Gestionar</th>
                            </tr>
                        </thead>
                        
                        <tbody>
                        @foreach($invoices as $invoice)

                            @if($invoice->priority == 1)
                              <td class="text-center">
                                <svg width="30" height="30" viewBox="0 0 16 16" class="bi bi-exclamation" fill="red" xmlns="http://www.w3.org/2000/svg">
                                  <path d="M7.002 11a1 1 0 1 1 2 0 1 1 0 0 1-2 0zM7.1 4.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 4.995z"/>
                                </svg>
                              </td>
                            @else
                              <td></td>
                            @endif
                              <td>{{$invoice->supplier}}</td>
                              <td>{{$invoice->number}}</td>
                              <td>${{number_format($invoice->subtotal,2)}}</td>
                              <td>${{number_format($invoice->iva,2)}}</td>
                              <td>${{number_format($invoice->total,2)}}</td>
                              <td>{{$invoice->currency}}</td>
                              <td>{{$invoice->due_date}}</td>
                              <td><a href="{{url('invoice/')}}/{{$invoice->invoice_id}}/{{$invoice->next_user_id}}" class="btn btn-outline-success">Gestionar</a>
                              </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    @else
                        <p>¡no tienes facturas pendientes por gestionar!</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection