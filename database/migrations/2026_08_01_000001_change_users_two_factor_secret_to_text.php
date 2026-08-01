<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * users.two_factor_secret holds Fortify's encrypted secret. The default
 * ciphertext fits varchar(255), but a longer configured secret length
 * overflows it, so the create migration was widened to text; installs
 * created before that need the same change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable()->change();
        });
    }

    public function down(): void
    {
        // No-op: narrowing back to varchar(255) would truncate or reject any
        // secret that needed the extra width. Leaving the column widened is safe.
    }
};
