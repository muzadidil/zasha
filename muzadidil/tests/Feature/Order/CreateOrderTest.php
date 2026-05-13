<?php

use App\Models\Order;
use App\Models\User;
use Database\Seeders\ServiceCategorySeeder;

beforeEach(function () {
    $this->seed(ServiceCategorySeeder::class);
});

it('creates a Titip order with full details and location', function () {
    $customer = User::factory()->customer()->create();

    $payload = [
        'service_category_slug' => 'titip',
        'initial_price' => 15_000,
        'pickup_lat' => -6.2088,
        'pickup_lng' => 106.8456,
        'destination_lat' => -6.2200,
        'destination_lng' => 106.8500,
        'details' => [
            'pickup_address' => 'Jl. Sudirman No. 1',
            'dropoff_address' => 'Jl. Thamrin No. 5',
            'estimated_weight' => 2.5,
            'items' => [
                ['name' => 'Sayur', 'qty' => 2, 'estimated_price' => 20_000],
            ],
            'notes' => 'Tolong jangan lewat tol',
        ],
    ];

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/customer/orders', $payload)
        ->assertCreated()
        ->assertJsonPath('data.status', Order::STATUS_SEARCHING)
        ->assertJsonPath('data.current_price', 15_000);

    expect(Order::count())->toBe(1);
    expect(Order::first()->statusLogs()->count())->toBe(1);
});

it('rejects WFH order without required details', function () {
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/customer/orders', [
            'service_category_slug' => 'wfh',
            'initial_price' => 50_000,
            'details' => ['task_title' => 'too short'],
        ])
        ->assertStatus(422);
});

it('returns per-field WFH validation errors that the frontend can render', function () {
    $customer = User::factory()->customer()->create();

    $response = $this->actingAs($customer, 'sanctum')
        ->postJson('/api/customer/orders', [
            'service_category_slug' => 'wfh',
            'initial_price' => 50_000,
            'details' => [
                'task_title' => 'adfadf',
                'task_description' => 'adfadf',
                'deadline' => '2027-10-05T11:11',
                'skills_required' => [],
                'attachment_urls' => [],
            ],
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'invalid_input');

    $errors = $response->json('error.data.errors');
    expect($errors)->toHaveKey('details.task_title');
    expect($errors)->toHaveKey('details.task_description');
    expect($errors)->toHaveKey('details.skills_required');
});

it('accepts a WFH order with all required details', function () {
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/customer/orders', [
            'service_category_slug' => 'wfh',
            'initial_price' => 50_000,
            'details' => [
                'task_title' => 'Landing page Vue minimalis',
                'task_description' => str_repeat('Saya butuh landing page Vue yang minimalis dan responsif. ', 3),
                'deadline' => now()->addDays(3)->toIso8601String(),
                'skills_required' => ['Vue 3', 'Tailwind'],
                'attachment_urls' => [],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'searching');
});

it('rejects order below category minimum price', function () {
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/customer/orders', [
            'service_category_slug' => 'titip',
            'initial_price' => 1_000,
            'pickup_lat' => -6.2,
            'pickup_lng' => 106.8,
            'details' => [
                'pickup_address' => 'a',
                'dropoff_address' => 'b',
                'estimated_weight' => 1,
                'items' => [['name' => 'x', 'qty' => 1]],
            ],
        ])
        ->assertStatus(422);
});

it('rejects geolocation-required category without pickup coords', function () {
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/customer/orders', [
            'service_category_slug' => 'titip',
            'initial_price' => 15_000,
            'details' => [
                'pickup_address' => 'a',
                'dropoff_address' => 'b',
                'estimated_weight' => 1,
                'items' => [['name' => 'x', 'qty' => 1]],
            ],
        ])
        ->assertStatus(422);
});

it('increases price by category step', function () {
    $customer = User::factory()->customer()->create();
    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/customer/orders', [
            'service_category_slug' => 'titip',
            'initial_price' => 15_000,
            'pickup_lat' => -6.2,
            'pickup_lng' => 106.8,
            'details' => [
                'pickup_address' => 'a',
                'dropoff_address' => 'b',
                'estimated_weight' => 1,
                'items' => [['name' => 'x', 'qty' => 1]],
            ],
        ]);

    $order = Order::first();

    $this->actingAs($customer, 'sanctum')
        ->patchJson("/api/customer/orders/{$order->id}/increase-price")
        ->assertOk()
        ->assertJsonPath('data.current_price', 20_000); // 15k + step 5k for titip
});

it('lets customer cancel a searching order', function () {
    $customer = User::factory()->customer()->create();
    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/customer/orders', [
            'service_category_slug' => 'titip',
            'initial_price' => 15_000,
            'pickup_lat' => -6.2,
            'pickup_lng' => 106.8,
            'details' => [
                'pickup_address' => 'a',
                'dropoff_address' => 'b',
                'estimated_weight' => 1,
                'items' => [['name' => 'x', 'qty' => 1]],
            ],
        ]);

    $order = Order::first();

    $this->actingAs($customer, 'sanctum')
        ->deleteJson("/api/customer/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.status', Order::STATUS_CANCELLED);
});

it('forbids non-customer from creating orders', function () {
    $partner = User::factory()->partner()->create();

    $this->actingAs($partner, 'sanctum')
        ->postJson('/api/customer/orders', ['service_category_slug' => 'titip'])
        ->assertStatus(403);
});
