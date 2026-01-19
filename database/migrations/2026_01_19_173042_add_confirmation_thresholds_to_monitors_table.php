<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->unsignedSmallInteger('failure_confirmation_threshold')->default(3);
            $table->unsignedSmallInteger('recovery_confirmation_threshold')->default(3);
        });
    }

    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropColumn('failure_confirmation_threshold');
            $table->dropColumn('recovery_confirmation_threshold');
        });
    }
};
