<?php

declare(strict_types=1);

use App\Domains\Responder\Support\Enums\CertificationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A responder credential with an issuing authority and an optional expiry. The
 * expiry sweep transitions verified certs to expired and notifies; the
 * (responder_id, expires_at) index keeps that sweep cheap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('responder_certifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('responder_id')->constrained('responders')->cascadeOnDelete();
            // Denormalised for organization-scoped queries/indexing.
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();

            $table->string('name');
            $table->string('authority')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('status')->default(CertificationStatus::Pending->value);

            $table->jsonb('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['responder_id', 'expires_at']);
            $table->index('organization_id');
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $allowed = collect(CertificationStatus::values())
            ->map(static fn (string $value): string => "'".$value."'")
            ->implode(', ');

        DB::statement("ALTER TABLE responder_certifications ADD CONSTRAINT responder_certifications_status_check CHECK (status IN ({$allowed}))");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE responder_certifications DROP CONSTRAINT IF EXISTS responder_certifications_status_check');
        }

        Schema::dropIfExists('responder_certifications');
    }
};
