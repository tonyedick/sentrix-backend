<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Denormalised pointer to a responder's current active assignment, for fast
 * reads. Nulled if the assignment row is deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('responders', function (Blueprint $table): void {
            $table->foreignUuid('current_assignment_id')
                ->nullable()
                ->after('on_duty')
                ->constrained('responder_assignments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('responders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('current_assignment_id');
        });
    }
};
