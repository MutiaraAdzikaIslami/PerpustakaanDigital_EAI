<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RedisSubscriber extends Command
{
    protected $signature = 'redis:subscribe';
    protected $description = 'Mendengarkan event broadcast dari Redis Broker untuk memperbarui stok buku';

    public function handle()
    {
        $this->info('Worker Subscriber sedang berjalan... Menunggu event di channel [book-events]');

        Redis::subscribe(['book-events'], function ($message) {
            $this->info("Menerima pesan mentah: " . $message);

            $data = json_decode($message, true);

            if ($data && isset($data['event']) && $data['event'] === 'BookBorrowed') {
                $bookId = $data['book_id'] ?? null;

                if (!$bookId) {
                    $this->error("Payload data tidak valid: 'book_id' tidak ditemukan.");
                    return;
                }

                $this->info("Memproses event BookBorrowed untuk Book ID: " . $bookId);

                try {
                    // AMBIL DATA DARI DATABASE
                    $stock = DB::table('book_stocks')->where('book_id', $bookId)->first();

                    if ($stock) {
                        if ($stock->available_stock > 0) {
                            DB::table('book_stocks')
                                ->where('book_id', $bookId)
                                ->decrement('available_stock', 1);

                            $this->info("SUKSES: Stok Buku ID {$bookId} berhasil dikurangi 1.");
                        } else {
                            $this->error("GAGAL: Stok Buku ID {$bookId} sudah habis di rak!");
                        }
                    } else {
                        // Jika buku belum ada di db, inisialisasi default otomatis
                        DB::table('book_stocks')->insert([
                            'book_id' => $bookId,
                            'total_stock' => 5,
                            'available_stock' => 4,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                        $this->info("SUKSES: Data stok baru diinisialisasi untuk Buku ID {$bookId}.");
                    }
                } catch (\Exception $e) {
                    // --- FALLBACK JIKA DATABASE CRASH / ERROR ---
                    Log::warning("Database bermasalah saat memproses Redis Event: " . $e->getMessage());
                    $this->warn("MODE CADANGAN: Database error, memproses simulasi potong stok sukses untuk Buku ID {$bookId}.");
                }
            }
        });
    }
}
