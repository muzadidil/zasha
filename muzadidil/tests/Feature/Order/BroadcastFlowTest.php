<?php

use App\Events\OrderAvailableForPartner;
use App\Events\OrderRadiusExpanded;
use App\Jobs\ExpandSearchRadiusJob;
use App\Models\User;
use Database\Seeders\ServiceCategorySeeder;
use Database\Seeders\TestPartnerSetupSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(ServiceCategorySeeder::class);
    $this->seed(TestPartnerSetupSeeder::class);
});

it('broadcasts OrderAvailableForPartner + OrderRadiusExpanded when a Titip order is created in Jember', function () {
    Event::fake([OrderAvailableForPartner::class, OrderRadiusExpanded::class]);
    Queue::fake();

    $customer = User::factory()->customer()->create();
    $testPartner = User::where('phone', '081200000001')->firstOrFail();

    $response = $this->actingAs($customer, 'sanctum')
        ->postJson('/api/customer/orders', [
            'service_category_slug' => 'titip',
            'initial_price' => 15_000,
            'pickup_lat' => -8.1727,
            'pickup_lng' => 113.7000,
            'details' => [
                'pickup_address' => 'Indomaret Jl. Jawa Jember',
                'dropoff_address' => 'Kost Jl. Mastrip Jember',
                'estimated_weight' => 2,
                'items' => [['name' => 'Galon air', 'qty' => 1]],
                'notes' => 'tolong yang dingin',
            ],
        ])
        ->assertCreated();

    $orderId = $response->json('data.id');

    // The test partner is at the pickup point, so step 0 (1km radius) must reach them.
    Event::assertDispatched(
        OrderAvailableForPartner::class,
        fn ($e) => $e->order->id === $orderId && $e->partner->id === $testPartner->id,
    );

    Event::assertDispatched(OrderRadiusExpanded::class, fn ($e) => $e->order->id === $orderId);

    // Step 1 (2km) is queued with 15s delay.
    Queue::assertPushed(ExpandSearchRadiusJob::class, fn ($j) => $j->orderId === $orderId && $j->stepIndex === 1);
});

it('broadcasts OrderAvailableForPartner nationally for a WFH order', function () {
    Event::fake([OrderAvailableForPartner::class, OrderRadiusExpanded::class]);
    Queue::fake();

    $customer = User::factory()->customer()->create();
    $testPartner = User::where('phone', '081200000001')->firstOrFail();

    $response = $this->actingAs($customer, 'sanctum')
        ->postJson('/api/customer/orders', [
            'service_category_slug' => 'wfh',
            'initial_price' => 100_000,
            'details' => [
                'task_title' => 'Landing page Vue minimalis',
                'task_description' => str_repeat('Saya butuh landing page Vue yang minimalis dan responsif. ', 3),
                'deadline' => now()->addDays(3)->toIso8601String(),
                'skills_required' => ['Vue 3'],
                'attachment_urls' => [],
            ],
        ])
        ->assertCreated();

    $orderId = $response->json('data.id');

    Event::assertDispatched(
        OrderAvailableForPartner::class,
        fn ($e) => $e->order->id === $orderId && $e->partner->id === $testPartner->id,
    );

    // WFH bypasses staged broadcasting entirely.
    Event::assertNotDispatched(OrderRadiusExpanded::class);
    Queue::assertNotPushed(ExpandSearchRadiusJob::class);
});
