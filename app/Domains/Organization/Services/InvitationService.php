<?php

declare(strict_types=1);

namespace App\Domains\Organization\Services;

use App\Domains\Organization\DTOs\InviteMemberData;
use App\Domains\Organization\Events\MemberInvited;
use App\Domains\Organization\Models\Organization;
use App\Domains\Organization\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class InvitationService
{
    /**
     * Create (or refresh) a pending invitation and emit MemberInvited so the
     * notification is dispatched on the queue by a listener.
     */
    public function invite(Organization $organization, InviteMemberData $data, User $inviter): OrganizationInvitation
    {
        $alreadyMember = $organization->members()
            ->where('users.email', $data->email)
            ->exists();

        if ($alreadyMember) {
            throw ValidationException::withMessages([
                'email' => ['This person is already a member of the organization.'],
            ]);
        }

        return DB::transaction(function () use ($organization, $data, $inviter): OrganizationInvitation {
            /** @var OrganizationInvitation $invitation */
            $invitation = OrganizationInvitation::updateOrCreate(
                ['organization_id' => $organization->getKey(), 'email' => $data->email],
                [
                    'role' => $data->role,
                    'token' => bin2hex(random_bytes(32)),
                    'invited_by' => $inviter->getKey(),
                    'expires_at' => now()->addDays(7),
                    'accepted_at' => null,
                ],
            );

            event(new MemberInvited($invitation));

            return $invitation;
        });
    }

    /**
     * Accept an invitation as the authenticated user.
     */
    public function accept(OrganizationInvitation $invitation, User $user, MembershipService $memberships): Organization
    {
        if (! $invitation->isPending()) {
            throw ValidationException::withMessages([
                'token' => ['This invitation is no longer valid.'],
            ]);
        }

        if (! hash_equals(mb_strtolower($invitation->email), mb_strtolower($user->email))) {
            throw ValidationException::withMessages([
                'token' => ['This invitation was issued to a different email address.'],
            ]);
        }

        $organization = $invitation->organization;

        return DB::transaction(function () use ($invitation, $organization, $user, $memberships): Organization {
            $memberships->addMember($organization, $user, $invitation->role);

            $invitation->forceFill(['accepted_at' => now()])->save();

            return $organization;
        });
    }

    public function revoke(OrganizationInvitation $invitation): void
    {
        $invitation->delete();
    }
}
