<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive daily-streak + granted-premium tracking on reward accounts (sorts
 * after the 2026_06_16 base tables). Backward compatible: existing rows default
 * to a zero streak, a NULL last_activity_on, and zero granted premium days.
 *
 *  - streak_days: current consecutive-day activity streak (whole days).
 *  - last_activity_on: the date the streak last advanced (server-local date).
 *  - premium_days_granted: lifetime Premium days converted from points (the
 *    Billing handoff record until a real subscription hook lands).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reward_accounts', function (Blueprint $table): void {
            $table->unsignedInteger('streak_days')->default(0)->after('boost_expires_at');
            $table->date('last_activity_on')->nullable()->after('streak_days');
            $table->unsignedInteger('premium_days_granted')->default(0)->after('last_activity_on');
        });
    }

    public function down(): void
    {
        Schema::table('reward_accounts', function (Blueprint $table): void {
            $table->dropColumn(['streak_days', 'last_activity_on', 'premium_days_granted']);
        });
    }
};
