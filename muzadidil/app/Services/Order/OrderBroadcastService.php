<?php

namespace App\Services\Order;

use App\Events\OrderAvailableForPartner;
use App\Events\OrderRadiusExpanded;
use App\Jobs\ExpandSearchRadiusJob;
use App\Models\Order;
use App\Models\OrderRadiusExpansion;
use App\Models\User;
use App\Services\Partner\PartnerLocationService;
use Illuminate\Support\Facades\DB;

class OrderBroadcastService
{
    public function __construct(
        private readonly PartnerLocationService $locationService,
    ) {
    }

    /**
     * Entry point for V2 staged broadcasting. Called by OrderService after an
     * order transitions to `searching`.
     */
    public function startStagedBroadcast(Order $order): void
    {
        $category = $order->serviceCategory;

        if (! $category->requires_geolocation) {
            $this->broadcastNational($order);

            return;
        }

        $this->expandToStep($order, stepIndex: 0);
    }

    /**
     * Expand the active search radius to the step at $stepIndex, notify newly
     * reachable partners, log the expansion, and schedule the next step.
     */
    public function expandToStep(Order $order, int $stepIndex): void
    {
        $steps = $order->serviceCategory->radius_steps ?? [];

        if (! isset($steps[$stepIndex])) {
            return;
        }

        $radiusKm = (int) $steps[$stepIndex];
        $previousRadius = $order->active_radius_km;

        $partners = $this->locationService->findPartnersInRadius(
            order: $order,
            radiusKm: $radiusKm,
            excludePreviousRadius: $previousRadius,
        );

        DB::transaction(function () use ($order, $radiusKm, $stepIndex, $previousRadius, $partners) {
            $order->active_radius_km = $radiusKm;
            $order->current_step_index = $stepIndex;
            $order->save();

            OrderRadiusExpansion::create([
                'order_id' => $order->id,
                'from_radius_km' => $previousRadius,
                'to_radius_km' => $radiusKm,
                'step_index' => $stepIndex,
                'partners_notified' => $partners->count(),
                'expanded_at' => now(),
            ]);
        });

        broadcast(new OrderRadiusExpanded($order->fresh()));

        foreach ($partners as $partner) {
            broadcast(new OrderAvailableForPartner($order, $partner));
        }

        $nextStep = $stepIndex + 1;
        if (isset($steps[$nextStep])) {
            $delay = (int) ($order->serviceCategory->step_duration_seconds ?? 15);
            ExpandSearchRadiusJob::dispatch($order->id, $nextStep)
                ->delay(now()->addSeconds($delay));
        }
    }

    /**
     * Broadcast WFH orders to every verified, non-blocked WFH partner without
     * geo filtering.
     */
    public function broadcastNational(Order $order): void
    {
        $partners = User::query()
            ->where('role', User::ROLE_PARTNER)
            ->whereNull('blocked_at')
            ->whereHas('partnerProfile', fn ($q) => $q
                ->where('is_verified', true)
                ->whereJsonContains('service_categories', $order->serviceCategory->slug)
            )
            ->get();

        foreach ($partners as $partner) {
            broadcast(new OrderAvailableForPartner($order, $partner));
        }
    }
}
