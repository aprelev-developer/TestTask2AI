<?php

/**
 * POST /api/checks — end-to-end contract test, per backend-conventions
 * skill → "API contract" and "Persistence" sections.
 *
 * Deliberately written against the contract only:
 * - request/response field names and shapes from the API contract;
 * - the three exact result strings and the "not a 4th status" rule from
 *   spec-compliance;
 * - "exactly one detection_events row per successful call" from Persistence.
 *
 * `reference_payments.id` doubles as the run_id a check run is validated
 * against (no separate `run_id` column) — confirmed against the actual
 * migration after this test failed to run against a stub `run_id` column
 * that does not exist; not guessed from reading Domain/Application code.
 */

use App\Models\ReferencePayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function validCheckPayload(string $runId, array $overrides = []): array
{
    return array_merge([
        'run_id' => $runId,
        'displayed_address' => 'addr-displayed-aaaa',
        'displayed_amount' => '0.50000000',
        'displayed_network' => 'BTC',
        'qr_address' => 'addr-displayed-aaaa',
        'qr_amount' => '0.50000000',
        'qr_network' => 'BTC',
        'copy_button_value' => 'addr-displayed-aaaa',
        'address_after_watch_window' => 'addr-displayed-aaaa',
        'page_scripts' => ['https://payments.example/checkout.js'],
    ], $overrides);
}

function seedReferencePayment(array $overrides = []): ReferencePayment
{
    $payment = new ReferencePayment;
    $payment->forceFill(array_merge([
        'id' => (string) Str::uuid(),
        'address' => 'addr-displayed-aaaa',
        'amount' => '0.50000000',
        'network' => 'BTC',
        'allowed_scripts' => ['https://payments.example/checkout.js'],
    ], $overrides));
    $payment->save();

    return $payment;
}

// ---------------------------------------------------------------------
// Validation (422)
// ---------------------------------------------------------------------

it('rejects a request missing run_id with 422 and a field-level error', function () {
    $payload = validCheckPayload((string) Str::uuid());
    unset($payload['run_id']);

    $response = $this->postJson('/api/checks', $payload);

    $response->assertStatus(422)
        ->assertJsonStructure(['error' => ['message', 'fields']])
        ->assertJsonPath('error.fields.run_id.0', fn ($value) => is_string($value));
});

it('rejects a syntactically invalid run_id with 422, not 404', function () {
    $payload = validCheckPayload('not-a-uuid');

    $response = $this->postJson('/api/checks', $payload);

    $response->assertStatus(422)
        ->assertJsonStructure(['error' => ['message', 'fields']]);

    expect(array_key_exists('run_id', $response->json('error.fields')))->toBeTrue();
});

it('rejects a request missing displayed_address with 422', function () {
    $payload = validCheckPayload((string) Str::uuid());
    unset($payload['displayed_address']);

    $response = $this->postJson('/api/checks', $payload);

    $response->assertStatus(422);
    expect(array_key_exists('displayed_address', $response->json('error.fields')))->toBeTrue();
});

it('rejects a request missing displayed_amount with 422', function () {
    $payload = validCheckPayload((string) Str::uuid());
    unset($payload['displayed_amount']);

    $response = $this->postJson('/api/checks', $payload);

    $response->assertStatus(422);
    expect(array_key_exists('displayed_amount', $response->json('error.fields')))->toBeTrue();
});

it('rejects a request missing displayed_network with 422', function () {
    $payload = validCheckPayload((string) Str::uuid());
    unset($payload['displayed_network']);

    $response = $this->postJson('/api/checks', $payload);

    $response->assertStatus(422);
    expect(array_key_exists('displayed_network', $response->json('error.fields')))->toBeTrue();
});

it('rejects page_scripts that is not an array with 422', function () {
    $payload = validCheckPayload((string) Str::uuid(), [
        'page_scripts' => 'not-an-array',
    ]);

    $response = $this->postJson('/api/checks', $payload);

    $response->assertStatus(422);
    expect(array_key_exists('page_scripts', $response->json('error.fields')))->toBeTrue();
});

it('returns the error envelope shape {error: {message, fields}} on validation failure', function () {
    $response = $this->postJson('/api/checks', []);

    $response->assertStatus(422);

    $body = $response->json();

    expect($body)->toHaveKey('error')
        ->and($body['error'])->toHaveKey('message')
        ->and($body['error']['message'])->toBeString()
        ->and($body['error'])->toHaveKey('fields')
        ->and($body['error']['fields'])->toBeArray();
});

// ---------------------------------------------------------------------
// Missing reference payment (404, not 422)
// ---------------------------------------------------------------------

it('returns 404 without a fields key when run_id is a syntactically valid UUID but no reference payment exists', function () {
    $unknownRunId = (string) Str::uuid();
    $payload = validCheckPayload($unknownRunId);

    $response = $this->postJson('/api/checks', $payload);

    $response->assertStatus(404);

    $body = $response->json();

    expect($body)->toHaveKey('error')
        ->and($body['error'])->toHaveKey('message')
        ->and(array_key_exists('fields', $body['error']))->toBeFalse();
});

// ---------------------------------------------------------------------
// Happy path — one test per possible result
// ---------------------------------------------------------------------

it('returns "Подмена не обнаружена" and no triggered/incomplete scenarios when everything matches', function () {
    $runId = (string) Str::uuid();
    seedReferencePayment([
        'id' => $runId,
        'address' => 'addr-displayed-aaaa',
        'amount' => '0.50000000',
        'network' => 'BTC',
        'allowed_scripts' => ['https://payments.example/checkout.js'],
    ]);

    $response = $this->postJson('/api/checks', validCheckPayload($runId));

    $response->assertStatus(200)
        ->assertJson([
            'result' => 'Подмена не обнаружена',
            'triggered_scenarios' => [],
            'incomplete_checks' => [],
        ]);
});

it('returns "Есть подозрение" when only the disallowed-script scenario (7.5) fires', function () {
    $runId = (string) Str::uuid();
    seedReferencePayment([
        'id' => $runId,
        'address' => 'addr-displayed-aaaa',
        'amount' => '0.50000000',
        'network' => 'BTC',
        'allowed_scripts' => ['https://payments.example/checkout.js'],
    ]);

    $payload = validCheckPayload($runId, [
        'page_scripts' => ['https://payments.example/checkout.js', 'https://evil.example/tracker.js'],
    ]);

    $response = $this->postJson('/api/checks', $payload);

    $response->assertStatus(200)
        ->assertJson([
            'result' => 'Есть подозрение',
            'triggered_scenarios' => ['7.5'],
            'incomplete_checks' => [],
        ]);
});

it('returns "Обнаружена подмена" when the QR-decoded address differs from the displayed address (7.1)', function () {
    $runId = (string) Str::uuid();
    seedReferencePayment([
        'id' => $runId,
        'address' => 'addr-displayed-aaaa',
        'amount' => '0.50000000',
        'network' => 'BTC',
        'allowed_scripts' => ['https://payments.example/checkout.js'],
    ]);

    $payload = validCheckPayload($runId, [
        'qr_address' => 'addr-qr-substituted-bbbb',
    ]);

    $response = $this->postJson('/api/checks', $payload);

    $response->assertStatus(200)
        ->assertJson([
            'result' => 'Обнаружена подмена',
            'incomplete_checks' => [],
        ]);

    expect($response->json('triggered_scenarios'))->toContain('7.1');
});

// ---------------------------------------------------------------------
// Response contract shape
// ---------------------------------------------------------------------

it('always responds with the result/triggered_scenarios/details/incomplete_checks shape', function () {
    $runId = (string) Str::uuid();
    seedReferencePayment(['id' => $runId]);

    $response = $this->postJson('/api/checks', validCheckPayload($runId));

    $response->assertStatus(200)
        ->assertJsonStructure([
            'result',
            'triggered_scenarios',
            'details',
            'incomplete_checks',
        ]);

    expect($response->json('result'))->toBeIn([
        'Подмена не обнаружена',
        'Есть подозрение',
        'Обнаружена подмена',
    ]);
});

it('includes a scenario/expected/actual entry in details for a triggered scenario', function () {
    $runId = (string) Str::uuid();
    seedReferencePayment([
        'id' => $runId,
        'address' => 'addr-displayed-aaaa',
    ]);

    $payload = validCheckPayload($runId, [
        'qr_address' => 'addr-qr-substituted-bbbb',
    ]);

    $response = $this->postJson('/api/checks', $payload);

    $response->assertStatus(200);

    $details = collect($response->json('details'));
    $entry = $details->firstWhere('scenario', '7.1');

    expect($entry)->not->toBeNull()
        ->and($entry)->toHaveKeys(['scenario', 'expected', 'actual']);
});

// ---------------------------------------------------------------------
// Persistence — exactly one detection_events row per successful call
// ---------------------------------------------------------------------

it('writes exactly one detection_events row per successful POST /api/checks call', function () {
    $runId = (string) Str::uuid();
    seedReferencePayment(['id' => $runId]);

    $before = DB::table('detection_events')->count();

    $response = $this->postJson('/api/checks', validCheckPayload($runId));
    $response->assertStatus(200);

    $after = DB::table('detection_events')->count();

    expect($after - $before)->toBe(1);
});

it('writes one additional detection_events row for each subsequent successful call', function () {
    $runId = (string) Str::uuid();
    seedReferencePayment(['id' => $runId]);

    $before = DB::table('detection_events')->count();

    $this->postJson('/api/checks', validCheckPayload($runId))->assertStatus(200);
    $this->postJson('/api/checks', validCheckPayload($runId))->assertStatus(200);
    $this->postJson('/api/checks', validCheckPayload($runId))->assertStatus(200);

    $after = DB::table('detection_events')->count();

    expect($after - $before)->toBe(3);
});

it('does not write a detection_events row for a request that fails validation', function () {
    $before = DB::table('detection_events')->count();

    $this->postJson('/api/checks', [])->assertStatus(422);

    $after = DB::table('detection_events')->count();

    expect($after)->toBe($before);
});
