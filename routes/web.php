<?php

use App\Http\Controllers\UtilityController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\MobilePosController;
use App\Http\Controllers\ImportExportController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    app(InstallController::class)->install();
    return redirect( url('admin/') );
});


Route::get('/install',  [InstallController::class, 'install']);
Route::get('/print/{id}', [UtilityController::class, 'print'])->name('utility.print-invoice');
Route::get('/print-credit-note/{id}', [UtilityController::class, 'printCreditNote'])->name('utility.print-credit-note');
Route::get('/login',  function(){
    return redirect( url('/admin') );
})->name('login');

Route::get('/clear', function(){
    Artisan::call('optimize:clear');
    Artisan::call('cache:clear');
    Artisan::call('config:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
});

// Protected routes examples - REMOVED: These were conflicting with Filament resources
// Route::get('/admin/users', ...);
// Route::get('/admin/stock-adjustment', ...);
// Route::get('/admin/reports', ...);

// Store switching route
Route::post('/admin/stores/switch', [StoreController::class, 'switch'])->name('filament.admin.stores.switch')->middleware('auth');

// Mobile POS Routes
Route::prefix('mobile-pos')->group(function () {
    Route::get('/login', [MobilePosController::class, 'login'])->name('mobile-pos.login');
    Route::get('/', [MobilePosController::class, 'index'])->name('mobile-pos.index')->middleware('auth');
});

// Import/Export Routes
Route::prefix('import-export')->group(function () {
    Route::get('/template/{resource}/{format?}', [ImportExportController::class, 'downloadTemplate'])->name('import-export.template');
    Route::get('/download/{job}', [ImportExportController::class, 'download'])->name('import-export.download');
});
