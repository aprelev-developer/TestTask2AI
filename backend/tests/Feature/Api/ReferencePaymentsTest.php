<?php

/**
 * POST/GET /api/reference-payments — end-to-end contract test.
 *
 * This is tooling, not part of SPEC.md (see backend-conventions →
 * "Test-fixture endpoints"): the assertions below come strictly from the
 * contract handed to Agent D, not from reading
 * ReferencePaymentGenerator/CreateReferencePayment/ReferencePaymentController,
 * which are being written in parallel by Agents A and B.
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------
// POST /api/reference-payments — generation
// ---------------------------------------------------------------------

it('generates all four fields with a valid run_id when the body is empty', function () {
    $response = $this->postJson('/api/reference-payments', []);

    $response->assertStatus(201)
        ->assertJsonStructure(['run_id', 'address', 'amount', 'network', 'allowed_scripts']);

    $body = $response->json();

    expect(Str::isUuid($body['run_id']))->toBeTrue()
        ->and($body['address'])->toBeString()->not->toBe('')
        ->and($body['amount'])->toBeString()->not->toBe('')
        ->and($body['network'])->toBeString()->not->toBe('')
        ->and($body['allowed_scripts'])->toBeArray()->not->toBeEmpty();
});

it('keeps the field the caller supplied and generates the rest when only network is given', function () {
    $response = $this->postJson('/api/reference-payments', [
        'network' => 'BTC',
    ]);

    $response->assertStatus(201);

    $body = $response->json();

    expect($body['network'])->toBe('BTC')
        ->and($body['address'])->toBeString()->not->toBe('')
        ->and($body['amount'])->toBeString()->not->toBe('')
        ->and($body['allowed_scripts'])->toBeArray()->not->toBeEmpty();
});

it('keeps the field the caller supplied and generates the rest when only address is given', function () {
    $response = $this->postJson('/api/reference-payments', [
        'address' => 'addr-caller-supplied',
    ]);

    $response->assertStatus(201);

    $body = $response->json();

    expect($body['address'])->toBe('addr-caller-supplied')
        ->and($body['amount'])->toBeString()->not->toBe('')
        ->and($body['network'])->toBeString()->not->toBe('')
        ->and($body['allowed_scripts'])->toBeArray()->not->toBeEmpty();
});

it('returns exactly what was sent, unchanged, when every field is fully specified', function () {
    $payload = [
        'address' => 'addr-fully-specified',
        'amount' => '1.23456789',
        'network' => 'ETH',
        'allowed_scripts' => ['https://payments.example/checkout.js', 'https://payments.example/vendor.js'],
    ];

    $response = $this->postJson('/api/reference-payments', $payload);

    $response->assertStatus(201)
        ->assertJson([
            'address' => $payload['address'],
            'amount' => $payload['amount'],
            'network' => $payload['network'],
            'allowed_scripts' => $payload['allowed_scripts'],
        ]);
});

it('rejects a non-decimal-string amount with 422 and a field-level error on amount', function () {
    $response = $this->postJson('/api/reference-payments', [
        'amount' => 'abc',
    ]);

    $response->assertStatus(422)
        ->assertJsonStructure(['error' => ['message', 'fields']]);

    expect(array_key_exists('amount', $response->json('error.fields')))->toBeTrue();
});

it('returns the error envelope shape {error: {message, fields}} on validation failure', function () {
    $response = $this->postJson('/api/reference-payments', [
        'amount' => 'not-a-decimal',
    ]);

    $response->assertStatus(422);

    $body = $response->json();

    expect($body)->toHaveKey('error')
        ->and($body['error'])->toHaveKey('message')
        ->and($body['error']['message'])->toBeString()
        ->and($body['error'])->toHaveKey('fields')
        ->and($body['error']['fields'])->toBeArray();
});

it('generates a different run_id on each of two consecutive empty POST calls', function () {
    $first = $this->postJson('/api/reference-payments', [])->assertStatus(201)->json('run_id');
    $second = $this->postJson('/api/reference-payments', [])->assertStatus(201)->json('run_id');

    expect($first)->not->toBe($second);
});

// ---------------------------------------------------------------------
// GET /api/reference-payments/{run_id}
// ---------------------------------------------------------------------

it('returns the same body from GET that POST returned for that run_id', function () {
    $created = $this->postJson('/api/reference-payments', [
        'address' => 'addr-round-trip',
        'amount' => '2.5',
        'network' => 'TRX',
        'allowed_scripts' => ['https://payments.example/checkout.js'],
    ])->assertStatus(201)->json();

    $response = $this->getJson('/api/reference-payments/'.$created['run_id']);

    $response->assertStatus(200)
        ->assertJson($created);
});

it('returns 404 without a fields key for a syntactically valid but non-existent run_id', function () {
    $unknownRunId = (string) Str::uuid();

    $response = $this->getJson('/api/reference-payments/'.$unknownRunId);

    $response->assertStatus(404);

    $body = $response->json();

    expect($body)->toHaveKey('error')
        ->and($body['error'])->toHaveKey('message')
        ->and($body['error']['message'])->toBeString()
        ->and(array_key_exists('fields', $body['error']))->toBeFalse();
});
