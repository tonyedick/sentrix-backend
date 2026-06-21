<?php

declare(strict_types=1);

use App\Domains\Responder\Support\Enums\SkillProficiency;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Grants a responder a skill at a proficiency level.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('responder_skill', function (Blueprint $table): void {
            // Conventional pivot: composite primary key (no surrogate UUID), so
            // belongsToMany attach() inserts cleanly. Matches organization_user.
            $table->foreignUuid('responder_id')->constrained('responders')->cascadeOnDelete();
            $table->foreignUuid('skill_id')->constrained('skills')->cascadeOnDelete();

            $table->string('proficiency')->default(SkillProficiency::Trained->value);

            $table->timestamps();

            $table->primary(['responder_id', 'skill_id']);
            $table->index('skill_id');
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $allowed = collect(SkillProficiency::values())
            ->map(static fn (string $value): string => "'".$value."'")
            ->implode(', ');

        DB::statement("ALTER TABLE responder_skill ADD CONSTRAINT responder_skill_proficiency_check CHECK (proficiency IN ({$allowed}))");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE responder_skill DROP CONSTRAINT IF EXISTS responder_skill_proficiency_check');
        }

        Schema::dropIfExists('responder_skill');
    }
};
