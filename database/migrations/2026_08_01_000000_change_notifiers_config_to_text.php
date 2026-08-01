<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * notifiers.config holds ciphertext from the encrypted:array cast, which is
 * not valid JSON. PostgreSQL enforces json column syntax, so every notifier
 * insert failed there; text matches what the column actually stores.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifiers', function (Blueprint $table) {
            $table->text('config')->change();
        });
    }

    public function down(): void
    {
        Schema::table('notifiers', function (Blueprint $table) {
            $table->json('config')->change();
        });
    }
};
