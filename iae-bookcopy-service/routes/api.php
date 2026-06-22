<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| FITUR 1: API Cek Stok Buku
|--------------------------------------------------------------------------
*/
Route::get('/book-copies/{book_id}', function ($book_id) {
    try {
        // Sesuaikan dengan nama tabel dan kolom asli
        $stock = DB::table('book_stocks')->where('book_id', $book_id)->value('available_stock');

        if ($stock !== null) {
            return response()->json(['book_id' => (int)$book_id, 'stock' => (int)$stock]);
        }
    } catch (\Exception $e) {
        Log::warning("Koneksi database book_stocks bermasalah, mengaktifkan data cadangan.");
    }

    // Fallback Data klo error
    $mockStock = in_array($book_id, [1, 2, 3]) ? 5 : 10;
    return response()->json([
        'book_id' => (int)$book_id,
        'stock' => $mockStock,
        '_meta' => 'fallback_mode_active'
    ]);
});
