@extends('layouts.app')

@section('content')

<div class="container invoice-area">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    Documentos de soporte 
                </div>
                <div class="card-body">
                    <div>
                        <a 
                        href="{{url('document/create')}}/" 
                        class="btn btn-success">
                            Nuevo documento
                        </a>
                        <hr />
                    </div>
                    @if($count)
                        <div class="div-table-responsive">
                            <table class="table-responsive-md table-bordered table-striped table-sm" cellspacing="0" width="100%">
                                <thead>
                                    <tr>
                                    <th>ID</th>
                                    <th>Compañía</th>
                                    <th>NIT Compañía</th>
                                    <th>Proveedor</th>
                                    <th>NIT Proveedor</th>
                                    <th>Resolución</th>
                                    <th>Tipo</th>
                                    <th>Código</th>
                                    <th>Estado</th>
                                    <th>Documento</th>
                                    <th>Fecha de Pago</th>
                                    <th>Valor bruto</th>
                                    <th>Valor descuentos</th>
                                    <th>Valor total</th>
                                    <th>Valor en letras</th>
                                    <th>Líneas</th>
                                    <th>Orden</th> 
                                    <th>Anticipo</th>
                                    <th>Opciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach( $rows as $row )
                                    <td>{{$row->id}}</td>
                                    <td>{{$row->company}}</td>
                                    <td>{{$row->company_nit}}</td>
                                    <td>{{$row->supplier_reason}}</td>
                                    <td>{{$row->supplier_document}}</td>
                                    <td>{{$row->resolution}}</td>
                                    <td>{{$row->type}}</td>
                                    <td>{{$row->type_code}}</td>
                                    <td>{{$row->status}}</td>
                                    <td>{{$row->document_number}}</td>
                                    <td>{{$row->date_due_payment}}</td>
                                    <td>
                                        {{ number_format($row->gross_total_and_tribute, 0,',','.') }}
                                    </td>
                                    <td>
                                        {{ number_format($row->discounts_total_and_detail, 0,',','.') }}
                                    </td>
                                    <td>
                                        {{ number_format($row->pay_total, 0,',','.') }}
                                    </td>
                                    <td>{{$row->value_letters}}</td>
                                    <td>{{$row->total_lines}}</td>
                                    <td>{{$row->order_number}}</td>
                                    <td>{{$row->advance_number}}</td>
                                    <td>
                                        <a href="{{url('document/')}}/{{$row->id}}" 
                                        class="btn btn-info">
                                            Editar
                                        </a>
                                    </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p>No hay documentos </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@endsection