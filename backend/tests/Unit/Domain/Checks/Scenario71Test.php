<?php

/**
 * SPEC.md §7.1 / §9: адрес на странице отличается от адреса в QR-коде.
 *
 * - qr_address !== null → сравнить с displayed_address.
 * - несовпадение хотя бы одного символа → «Обнаружена подмена», оба значения
 *   присутствуют в RuleResult (expected/actual, порядок не фиксирован
 *   контрактом — сверяется как неупорядоченная пара).
 * - полное совпадение → сценарий не срабатывает.
 * - qr_address === null → проверка технически не выполнена (incomplete),
 *   не «сработал»/«не сработал».
 */

use App\Domain\Checks\Rules\Scenario71Rule;
use Tests\Unit\Domain\Checks\Support\Fixtures;

it('triggers 7.1 when the QR-decoded address differs from the displayed address', function () {
    $input = Fixtures::checkInput([
        'displayedAddress' => 'addr-displayed-aaaa',
        'qrAddress' => 'addr-qr-bbbb',
    ]);

    $result = (new Scenario71Rule)->evaluate($input, Fixtures::referencePayment());

    expect($result->scenario)->toBe('7.1')
        ->and($result->triggered)->toBeTrue()
        ->and($result->incomplete)->toBeFalse();

    Fixtures::assertSamePair($result->expected, $result->actual, 'addr-displayed-aaaa', 'addr-qr-bbbb');
});

it('triggers 7.1 on a single differing character', function () {
    $input = Fixtures::checkInput([
        'displayedAddress' => 'bc1qsameaddressxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        'qrAddress' => 'bc1qsameaddressxxxxxxxxxxxxxxxxxxxxxxxxxxxxY',
    ]);

    $result = (new Scenario71Rule)->evaluate($input, Fixtures::referencePayment());

    expect($result->triggered)->toBeTrue();
});

it('does not trigger 7.1 when the QR-decoded address matches the displayed address exactly', function () {
    $input = Fixtures::checkInput([
        'displayedAddress' => 'addr-same-value',
        'qrAddress' => 'addr-same-value',
    ]);

    $result = (new Scenario71Rule)->evaluate($input, Fixtures::referencePayment());

    expect($result->triggered)->toBeFalse()
        ->and($result->incomplete)->toBeFalse();
});

it('marks 7.1 incomplete when the QR-decoded address is unavailable', function () {
    $input = Fixtures::checkInput([
        'displayedAddress' => 'addr-displayed-aaaa',
        'qrAddress' => null,
    ]);

    $result = (new Scenario71Rule)->evaluate($input, Fixtures::referencePayment());

    expect($result->incomplete)->toBeTrue()
        ->and($result->triggered)->toBeFalse();
});
