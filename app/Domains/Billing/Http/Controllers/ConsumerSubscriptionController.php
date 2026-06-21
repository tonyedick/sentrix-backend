<?php

declare(strict_types=1);

namespace App\Domains\Billing\Http\Controllers;

use App\Domains\Billing\Http\Requests\SetAutoRenewRequest;
use App\Domains\Billing\Http\Requests\SubscribeRequest;
use App\Domains\Billing\Http\Resources\InvoiceResource;
use App\Domains\Billing\Http\Resources\SubscriptionResource;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Services\SubscriptionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Consumer subscription management (Manage Subscription / Billing History).
 * User-scoped (ADR-0001).
 */
final class ConsumerSubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function show(Request $request): SubscriptionResource
    {
        return SubscriptionResource::make($this->subscriptions->current($request->user()));
    }

    public function subscribe(SubscribeRequest $request): SubscriptionResource
    {
        return SubscriptionResource::make(
            $this->subscriptions->subscribe($request->user(), $request->string('plan')->value()),
        );
    }

    public function cancel(Request $request): SubscriptionResource
    {
        return SubscriptionResource::make($this->subscriptions->cancel($request->user()));
    }

    public function autoRenew(SetAutoRenewRequest $request): SubscriptionResource
    {
        return SubscriptionResource::make(
            $this->subscriptions->setAutoRenew($request->user(), $request->boolean('auto_renew')),
        );
    }

    public function invoices(Request $request): AnonymousResourceCollection
    {
        $invoices = Invoice::query()
            ->where('user_id', $request->user()->getKey())
            ->latest('issued_at')
            ->paginate($this->perPage($request));

        return InvoiceResource::collection($invoices);
    }
}
