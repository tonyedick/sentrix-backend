<?php

declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\SafetyContact;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Manages a user's 1–5 trusted safety contacts. Enforces the cap and the
 * single-primary invariant. User-scoped (ADR-0001).
 */
final readonly class SafetyContactService
{
    public const MAX_CONTACTS = 5;

    public function __construct(private DatabaseManager $db) {}

    /**
     * @return Collection<int, SafetyContact>
     */
    public function list(User $user): Collection
    {
        return SafetyContact::query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('is_primary')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @param  array{name:string,phone:string,email?:?string,relationship?:?string,is_primary?:bool}  $data
     */
    public function add(User $user, array $data): SafetyContact
    {
        return $this->db->transaction(function () use ($user, $data): SafetyContact {
            // Lock the user's rows and count in PHP — PostgreSQL disallows
            // FOR UPDATE with an aggregate (count()).
            $count = SafetyContact::query()->where('user_id', $user->getKey())->lockForUpdate()->get()->count();
            if ($count >= self::MAX_CONTACTS) {
                throw ValidationException::withMessages(['contacts' => ['You can have at most '.self::MAX_CONTACTS.' safety contacts.']]);
            }

            $makePrimary = ($data['is_primary'] ?? false) || $count === 0; // first contact is primary by default
            if ($makePrimary) {
                $this->clearPrimary($user);
            }

            return SafetyContact::create([
                'user_id' => $user->getKey(),
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'relationship' => $data['relationship'] ?? null,
                'is_primary' => $makePrimary,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(SafetyContact $contact, array $data): SafetyContact
    {
        return $this->db->transaction(function () use ($contact, $data): SafetyContact {
            if (($data['is_primary'] ?? false) === true) {
                $this->clearPrimary($contact->user);
            }
            $contact->fill($data)->save();

            return $contact->refresh();
        });
    }

    public function delete(SafetyContact $contact): void
    {
        $contact->delete();
    }

    private function clearPrimary(User $user): void
    {
        SafetyContact::query()->where('user_id', $user->getKey())->where('is_primary', true)->update(['is_primary' => false]);
    }
}
