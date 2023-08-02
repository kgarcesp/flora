<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::POST('/flujoanticipos', 'ReportController@flujoanticipos');
Route::POST('/costcenteranticipos', 'ReportController@costcenteranticipos');
Route::POST('/costcenter', 'ReportController@costcenter');
Route::POST('/adjuntosfiles', 'ReportController@adjuntosfiles');
Route::POST('/adjuntosfilesequivalentes', 'InvoiceController@adjuntosfilesequivalentes');
Route::POST('/adjuntosfilesanticipos', 'InvoiceController@adjuntosfilesanticipos');
Route::POST('/adjuntosfileslegalizaciones', 'InvoiceController@adjuntosfileslegalizaciones');
Route::POST('/anticipos/anticipos-log', 'InvoiceController@anticiposLog');
Route::POST('/load_data_invoices', 'ReportController@load_data_invoices');
Route::POST('/load_data_invoices_report', 'ReportController@load_data_invoices_report');
Route::POST('/actualizacionjefes', 'Homecontroller@actualizacionjefes');

Route::POST('/load_data_anticipos', 'ReportController@load_data_anticipos');

// Departamentos
Route::GET('/document/search-depto', 'DocumentController@search_deptos');
// Ciudades
Route::GET('/document/search-city', 'DocumentController@search_cities');

// Proveedores
Route::GET('/document/search-supplier', 'DocumentController@search_supplier');
// Unidades de medida
Route::GET('/document/search-quantity-unit', 'DocumentController@search_quantity_unit');

// Resoluciones DIAN
Route::POST('/dianresolution/store', 'DianResolutionController@store');

// Documents Soporte/Notas
Route::POST('/document/store', 'DocumentController@store');
Route::POST('/document/edit', 'DocumentController@edit');
Route::POST('/document/get-item', 'DocumentController@get_item');
Route::POST('/document/del-item', 'DocumentController@destroy');
Route::POST('/document/update', 'DocumentController@update');
Route::POST('/document/send-data', 'DocumentController@send_data');

// Legalizacion gastos

Route::POST('/costcenterlegalizacion', 'InvoiceController@costcenterlegalizacion');
Route::POST('/adjuntosfilesgastos', 'InvoiceController@adjuntosfilesgastos');
Route::POST('/flujogastos', 'InvoiceController@flujogastos');
Route::POST('/adjuntosdistribuciongastos', 'InvoiceController@adjuntosdistribuciongastos');

