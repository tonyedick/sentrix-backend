<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks when an active trip's device went dark. Set once by the staleness sweep,
 * cleared when a fix arrives again — so detection is exactly-once per episode and
 * re-arms if contact is lost a second time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table): void {
            $table->timestamp('lost_contact_at')->nullable()->after('last_lng');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table): void {
            $table->dropColumn('lost_contact_at');
        });
    }
};
