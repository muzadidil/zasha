<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

expect()->extend('toBeOne', fn () => $this->toBe(1));

function actingAsCustomer(): \App\Models\User
{
    $user = \App\Models\User::factory()->customer()->create();
    test()->actingAs($user, 'sanctum');

    return $user;
}

function actingAsPartner(): \App\Models\User
{
    $user = \App\Models\User::factory()->partner()->create();
    test()->actingAs($user, 'sanctum');

    return $user;
}

function actingAsAdmin(): \App\Models\User
{
    $user = \App\Models\User::factory()->admin()->create();
    test()->actingAs($user, 'sanctum');

    return $user;
}
