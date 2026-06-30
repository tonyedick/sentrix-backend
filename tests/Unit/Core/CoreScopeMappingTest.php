<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Domains\Core\Services\CoreClient;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit test of the backend permission → Core tool-scope translation.
 *
 * Core enforces least-privilege with its own `domain:verb` scope vocabulary;
 * the backend holds Spatie `domain.verb` permissions. CoreClient::coreScopesFor
 * bridges the two so the forwarded X-Sentrix-Scopes header is understood by Core.
 */
final class CoreScopeMappingTest extends TestCase
{
    public function test_super_admin_gets_the_wildcard(): void
    {
        $this->assertSame(['*'], CoreClient::coreScopesFor(['incidents.view'], true));
        // Wildcard even with no explicit permissions.
        $this->assertSame(['*'], CoreClient::coreScopesFor([], true));
    }

    public function test_permissions_map_to_core_tool_scopes(): void
    {
        $scopes = CoreClient::coreScopesFor(['incidents.create', 'assignments.dispatch'], false);

        // The exact scope strings Core's tools require (open_incident, dispatch_responder).
        $this->assertContains('omni:incident', $scopes);
        $this->assertContains('emergency:dispatch', $scopes);
    }

    public function test_unheld_permissions_do_not_grant_their_scopes(): void
    {
        $scopes = CoreClient::coreScopesFor(['incidents.view'], false);

        // A read-only operator can read but cannot dispatch or open incidents.
        $this->assertContains('omni:read', $scopes);
        $this->assertNotContains('emergency:dispatch', $scopes);
        $this->assertNotContains('omni:incident', $scopes);
    }

    public function test_scopes_are_deduplicated_across_permissions(): void
    {
        // Both incidents.view and intel.view grant alert:read — it must appear once.
        $scopes = CoreClient::coreScopesFor(['incidents.view', 'intel.view'], false);

        $this->assertSame(1, count(array_keys($scopes, 'alert:read', true)));
    }

    public function test_no_permissions_yields_no_scopes(): void
    {
        $this->assertSame([], CoreClient::coreScopesFor([], false));
    }
}
