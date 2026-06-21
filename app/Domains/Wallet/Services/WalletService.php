<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Services;

use App\Domains\Wallet\Events\ReferralClaimed;
use App\Domains\Wallet\Events\WalletCharged;
use App\Domains\Wallet\Events\WalletToppedUp;
use App\Domains\Wallet\Models\PaymentMethod;
use App\Domains\Wallet\Models\ReferralClaim;
use App\Domains\Wallet\Models\Wallet;
use App\Domains\Wallet\Models\WalletTransaction;
use App\Domains\Wallet\Support\Enums\PaymentMethodKind;
use App\Domains\Wallet\Support\Enums\TopupMethod;
use App\Domains\Wallet\Support\Enums\TransactionDirection;
use App\Domains\Wallet\Support\Enums\TransactionStatus;
use App\Domains\Wallet\Support\Enums\TransactionType;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Wallet & payments economy. EVERY balance mutation runs inside a DB transaction
 * with lockForUpdate on the wallet row, re-reads the locked row, computes
 * balance_after_cents from the fresh value, and never trusts a stale read — this
 * is money, so correctness over speed. ALL MONEY IS INTEGER CENTS.
 *
 * Top-up confirmation is idempotent: the unique `reference` on a transaction is
 * the guard. In production a PSP webhook (e.g. Paystack) calls confirm(); here
 * confirm() is invoked directly for the demo rail.
 *
 * User-scoped (ADR-0001): no organization, no permission catalogue.
 */
final readonly class WalletService
{
    public function __construct(private DatabaseManager $db) {}

    /**
     * The fixed referral reward credited to BOTH sides, in integer cents.
     */
    public function referralRewardCents(): int
    {
        return (int) config('sentrix.rides.referral_reward_cents', 100000);
    }

    /**
     * Fetch (lazily create) the caller's wallet. A read responds 200 even when
     * the wallet is created on the fly.
     */
    public function walletFor(User $user): Wallet
    {
        $wallet = Wallet::query()->firstOrCreate(['user_id' => $user->getKey()]);
        $wallet->wasRecentlyCreated = false;

        return $wallet;
    }

    /**
     * @return Collection<int, WalletTransaction>
     */
    public function recentTransactions(Wallet $wallet, int $limit = 20): Collection
    {
        return WalletTransaction::query()
            ->where('wallet_id', $wallet->getKey())
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    // ---- Payment methods --------------------------------------------------

    /**
     * List the caller's payment methods, seeding the non-removable system
     * methods (cash + wallet) on first read.
     *
     * @return Collection<int, PaymentMethod>
     */
    public function paymentMethodsFor(User $user): Collection
    {
        $this->ensureSystemPaymentMethods($user);

        return PaymentMethod::query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('is_default')
            ->orderBy('created_at')
            ->get();
    }

    public function addCard(User $user, string $last4, ?string $brand = null): PaymentMethod
    {
        $this->ensureSystemPaymentMethods($user);

        $label = trim(($brand ?? 'Card').' ****'.$last4);

        return PaymentMethod::create([
            'user_id' => $user->getKey(),
            'kind' => PaymentMethodKind::Card,
            'label' => $label,
            'brand' => $brand,
            'last4' => $last4,
            'is_default' => false,
            'removable' => true,
        ]);
    }

    public function removePaymentMethod(PaymentMethod $method): void
    {
        if (! $method->removable || $method->kind !== PaymentMethodKind::Card) {
            throw ValidationException::withMessages([
                'payment_method' => ['Cash and wallet are system methods and cannot be removed.'],
            ]);
        }

        $method->delete();
    }

    private function ensureSystemPaymentMethods(User $user): void
    {
        PaymentMethod::query()->firstOrCreate(
            ['user_id' => $user->getKey(), 'kind' => PaymentMethodKind::Cash->value],
            ['label' => 'Cash', 'is_default' => true, 'removable' => false],
        );

        PaymentMethod::query()->firstOrCreate(
            ['user_id' => $user->getKey(), 'kind' => PaymentMethodKind::Wallet->value],
            ['label' => 'Sentrix Wallet', 'is_default' => false, 'removable' => false],
        );
    }

    // ---- Top-up (local rails) --------------------------------------------

    /**
     * Begin a top-up: persist a PENDING transaction with a unique reference and
     * return method-specific instructions. NO real PSP — in production a PSP
     * webhook confirms the transfer/USSD/card/bank payment and calls confirm().
     *
     * @return array{transaction: WalletTransaction, instructions: array<string, mixed>}
     */
    public function initiateTopup(User $user, int $amountCents, TopupMethod $method): array
    {
        return $this->db->transaction(function () use ($user, $amountCents, $method): array {
            $wallet = Wallet::query()->where('user_id', $user->getKey())->lockForUpdate()->firstOrCreate(['user_id' => $user->getKey()]);

            $reference = $this->generateReference();

            $transaction = WalletTransaction::create([
                'wallet_id' => $wallet->getKey(),
                'type' => TransactionType::Topup,
                'direction' => TransactionDirection::Credit,
                'amount_cents' => $amountCents,
                // Balance is unchanged until confirmation; snapshot the current value.
                'balance_after_cents' => $wallet->balance_cents,
                'method' => $method->value,
                'reference' => $reference,
                'status' => TransactionStatus::Pending,
                'description' => 'Wallet top-up ('.$method->value.')',
            ]);

            return [
                'transaction' => $transaction,
                'instructions' => $this->topupInstructions($method, $amountCents, $reference),
            ];
        });
    }

    /**
     * Confirm a top-up by reference. IDEMPOTENT: confirming the same reference
     * twice does NOT double-credit. The status re-check happens INSIDE the
     * locked transaction so concurrent confirmations cannot both credit.
     */
    public function confirmTopup(User $user, string $reference): WalletTransaction
    {
        return $this->db->transaction(function () use ($user, $reference): WalletTransaction {
            $wallet = Wallet::query()->where('user_id', $user->getKey())->lockForUpdate()->firstOrFail();

            /** @var WalletTransaction $transaction */
            $transaction = WalletTransaction::query()
                ->where('wallet_id', $wallet->getKey())
                ->where('reference', $reference)
                ->where('type', TransactionType::Topup->value)
                ->lockForUpdate()
                ->firstOrFail();

            // Idempotency guard: already completed -> return unchanged, no credit.
            if ($transaction->status === TransactionStatus::Completed) {
                return $transaction;
            }

            $newBalance = $wallet->balance_cents + $transaction->amount_cents;

            $wallet->balance_cents = $newBalance;
            $wallet->lifetime_topup_cents += $transaction->amount_cents;
            $wallet->save();

            $transaction->status = TransactionStatus::Completed;
            $transaction->balance_after_cents = $newBalance;
            $transaction->save();

            event(new WalletToppedUp($transaction));

            return $transaction;
        });
    }

    // ---- Charge -----------------------------------------------------------

    /**
     * Debit the wallet atomically. If the balance is insufficient, throws an
     * InsufficientBalanceException carrying the shortfall (controller maps to
     * HTTP 402 Payment Required so the app can prompt an inline top-up).
     */
    public function charge(User $user, int $amountCents, ?string $description = null): WalletTransaction
    {
        return $this->db->transaction(function () use ($user, $amountCents, $description): WalletTransaction {
            $wallet = Wallet::query()->where('user_id', $user->getKey())->lockForUpdate()->firstOrCreate(['user_id' => $user->getKey()]);

            if ($wallet->balance_cents < $amountCents) {
                throw new InsufficientBalanceException($amountCents - $wallet->balance_cents);
            }

            $newBalance = $wallet->balance_cents - $amountCents;
            $wallet->balance_cents = $newBalance;
            $wallet->save();

            $transaction = WalletTransaction::create([
                'wallet_id' => $wallet->getKey(),
                'type' => TransactionType::Charge,
                'direction' => TransactionDirection::Debit,
                'amount_cents' => $amountCents,
                'balance_after_cents' => $newBalance,
                'method' => PaymentMethodKind::Wallet->value,
                'status' => TransactionStatus::Completed,
                'description' => $description ?? 'Wallet charge',
            ]);

            event(new WalletCharged($transaction));

            return $transaction;
        });
    }

    // ---- Payout (driver) --------------------------------------------------

    /**
     * Cash out the full balance to the linked bank (demo: instant). Driver
     * earnings accrual integrates with the deferred dispatch loop — once the
     * Driver domain owns canonical earnings, completed-ride payouts will credit
     * this wallet before payout.
     */
    public function payout(User $user): WalletTransaction
    {
        return $this->db->transaction(function () use ($user): WalletTransaction {
            $wallet = Wallet::query()->where('user_id', $user->getKey())->lockForUpdate()->firstOrCreate(['user_id' => $user->getKey()]);

            if ($wallet->balance_cents <= 0) {
                throw ValidationException::withMessages([
                    'amount' => ['Nothing to pay out yet.'],
                ]);
            }

            $amount = $wallet->balance_cents;
            $wallet->balance_cents = 0;
            $wallet->save();

            return WalletTransaction::create([
                'wallet_id' => $wallet->getKey(),
                'type' => TransactionType::Payout,
                'direction' => TransactionDirection::Debit,
                'amount_cents' => $amount,
                'balance_after_cents' => 0,
                'method' => PaymentMethodKind::Wallet->value,
                'status' => TransactionStatus::Completed,
                'description' => 'Payout to linked bank',
            ]);
        });
    }

    // ---- Referrals --------------------------------------------------------

    /**
     * Derive a stable, human-shareable referral code from the user's id. The
     * code is NOT stored on the user; it is recomputed deterministically so it
     * survives without a schema change and round-trips via referralUserId().
     */
    public function referralCodeFor(User $user): string
    {
        $hash = strtoupper(substr(hash('sha256', (string) $user->getKey()), 0, 6));

        return 'SR-'.$hash;
    }

    /**
     * Resolve a referral code back to its referrer. Returns null for an unknown
     * code. Guards uuid comparisons via the derived match (no raw uuid compare).
     */
    public function referrerForCode(string $code): ?User
    {
        $normalized = strtoupper(trim($code));

        // The code embeds a hash of the user id; scan users to find the match.
        // Bounded by the user table; in production this would be an indexed
        // stored code, but the derivation keeps it schema-free for the slice.
        return User::query()->get()->first(
            fn (User $candidate): bool => $this->referralCodeFor($candidate) === $normalized,
        );
    }

    /**
     * @return array{code: string, share_link: string, invited_count: int, total_earned_cents: int, has_claimed: bool}
     */
    public function referralSummary(User $user): array
    {
        $code = $this->referralCodeFor($user);

        $invitedCount = ReferralClaim::query()->where('referrer_id', $user->getKey())->count();

        $totalEarnedCents = (int) WalletTransaction::query()
            ->where('wallet_id', $this->walletFor($user)->getKey())
            ->where('type', TransactionType::ReferralCredit->value)
            ->sum('amount_cents');

        $hasClaimed = ReferralClaim::query()->where('claimer_id', $user->getKey())->exists();

        return [
            'code' => $code,
            'share_link' => 'https://saferides.sentrix.app/i/'.$code,
            'invited_count' => $invitedCount,
            'total_earned_cents' => $totalEarnedCents,
            'has_claimed' => $hasClaimed,
        ];
    }

    /**
     * Claim a referral code, crediting BOTH the referrer and the claimer the
     * fixed reward. Enforces: one claim per user, no self-claim, known code.
     */
    public function claimReferral(User $claimer, string $code): ReferralClaim
    {
        $normalized = strtoupper(trim($code));

        if ($normalized === $this->referralCodeFor($claimer)) {
            throw ValidationException::withMessages([
                'code' => ['You cannot claim your own referral code.'],
            ]);
        }

        $referrer = $this->referrerForCode($normalized);

        if ($referrer === null) {
            throw new UnknownReferralCodeException();
        }

        return $this->db->transaction(function () use ($claimer, $referrer, $normalized): ReferralClaim {
            if (ReferralClaim::query()->where('claimer_id', $claimer->getKey())->lockForUpdate()->exists()) {
                throw ValidationException::withMessages([
                    'code' => ['You have already claimed a referral code.'],
                ]);
            }

            $reward = $this->referralRewardCents();

            $claim = ReferralClaim::create([
                'code' => $normalized,
                'referrer_id' => $referrer->getKey(),
                'claimer_id' => $claimer->getKey(),
                'amount_cents' => $reward,
                'claimed_at' => Carbon::now(),
            ]);

            $this->creditReferral($referrer, $reward, 'Referral reward — a friend joined with your code');
            $this->creditReferral($claimer, $reward, 'Referral reward — welcome bonus');

            event(new ReferralClaimed($claim));

            return $claim;
        });
    }

    /**
     * Credit a fixed referral reward to a user's wallet inside the current
     * transaction. Locks + re-reads the wallet, snapshots balance_after.
     */
    private function creditReferral(User $user, int $amountCents, string $description): WalletTransaction
    {
        $wallet = Wallet::query()->where('user_id', $user->getKey())->lockForUpdate()->firstOrCreate(['user_id' => $user->getKey()]);

        $newBalance = $wallet->balance_cents + $amountCents;
        $wallet->balance_cents = $newBalance;
        $wallet->save();

        return WalletTransaction::create([
            'wallet_id' => $wallet->getKey(),
            'type' => TransactionType::ReferralCredit,
            'direction' => TransactionDirection::Credit,
            'amount_cents' => $amountCents,
            'balance_after_cents' => $newBalance,
            'method' => 'system',
            'status' => TransactionStatus::Completed,
            'description' => $description,
        ]);
    }

    // ---- Helpers ----------------------------------------------------------

    private function generateReference(): string
    {
        return 'TOPUP-'.strtoupper(Str::random(12));
    }

    /**
     * @return array<string, mixed>
     */
    private function topupInstructions(TopupMethod $method, int $amountCents, string $reference): array
    {
        return match ($method) {
            TopupMethod::Transfer => [
                'method' => $method->value,
                'reference' => $reference,
                'virtual_account_number' => '90'.substr(preg_replace('/\D/', '', $reference).'00000000', 0, 8),
                'bank_name' => 'Sentrix MFB',
                'account_name' => 'Sentrix Wallet Funding',
                'note' => 'Transfer the exact amount; the wallet is credited when the bank confirms (PSP webhook in production).',
            ],
            TopupMethod::Ussd => [
                'method' => $method->value,
                'reference' => $reference,
                'ussd_string' => '*737*000*'.intdiv($amountCents, 100).'#',
                'note' => 'Dial the USSD string on the phone linked to your bank; confirmation arrives via the PSP webhook.',
            ],
            TopupMethod::Card => [
                'method' => $method->value,
                'reference' => $reference,
                'checkout_url' => 'https://checkout.sentrix.app/topup/'.$reference,
                'note' => 'Complete payment on the hosted checkout; the PSP webhook confirms the top-up.',
            ],
            TopupMethod::Bank => [
                'method' => $method->value,
                'reference' => $reference,
                'bank_name' => 'Sentrix MFB',
                'account_number' => '0123456789',
                'account_name' => 'Sentrix Wallet Funding',
                'note' => 'Use the reference as the transfer narration; confirmation arrives via the PSP webhook.',
            ],
        };
    }
}
