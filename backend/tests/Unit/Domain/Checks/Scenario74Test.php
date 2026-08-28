<?php

/**
 * SPEC.md §7.4 / §9: сумма или сеть в QR-коде отличается от данных на
 * странице.
 *
 * - qr_amount !== null && qr_network !== null → сравнить оба параметра с
 *   displayed_amount / displayed_network.
 * - несовпадение хотя бы одного параметра (суммы, сети или обоих) →
 *   «Обнаружена подмена», с указанием какой параметр не совпал.
 * - полное совпадение обоих параметров → сценарий не срабатывает.
 * - если хотя бы один из qr_amount / qr_network отсутствует → сценарий
 *   технически не выполнен (incomplete) — это ОДНА проверка на оба
 *   параметра, а не две независимые.
 */

use App\Domain\Checks\Rules\Scenario74Rule;
use Tests\Unit\Domain\Checks\Support\Fixtures;

it('triggers 7.4 when only the amount differs', function () {
    $input = Fixtures::checkInput([
        'displayedAmount' => '1.00000000',
        'displayedNetwork' => 'BTC',
        'qrAmount' => '2.00000000',
        'qrNetwork' => 'BTC',
    ]);

    $result = (new Scenario74Rule)->evaluate($input, Fixtures::referencePayment());

    expect($result->scenario)->toBe('7.4')
        ->and($result->triggered)->toBeTrue()
        ->and($result->incomplete)->toBeFalse();
});

it('triggers 7.4 when only the network differs', function () {
    $input = Fixtures::checkInput([
        'displayedAmount' => '1.00000000',
        'displayedNetwork' => 'BTC',
        'qrAmount' => '1.00000000',
        'qrNetwork' => 'ETH',
    ]);

    $result = (new Scenario74Rule)->evaluate($input, Fixtures::referencePayment());

    expect($result->triggered)->toBeTrue()
        ->and($result->incomplete)->toBeFalse();
});

it('triggers 7.4 when both the amount and the network differ', function () {
    $input = Fixtures::checkInput([
        'displayedAmount' => '1.00000000',
        'displayedNetwork' => 'BTC',
        'qrAmount' => '2.00000000',
        'qrNetwork' => 'ETH',
    ]);

    $result = (new Scenario74Rule)->evaluate($input, Fixtures::referencePayment());

    expect($result->triggered)->toBeTrue()
        ->and($result->incomplete)->toBeFalse();
});

it('does not trigger 7.4 when both the amount and the network match', function () {
    $input = Fixtures::checkInput([
        'displayedAmount' => '1.00000000',
        'displayedNetwork' => 'BTC',
        'qrAmount' => '1.00000000',
        'qrNetwork' => 'BTC',
    ]);

    $result = (new Scenario74Rule)->evaluate($input, Fixtures::referencePayment());

    expect($result->triggered)->toBeFalse()
        ->and($result->incomplete)->toBeFalse();
});

it('marks 7.4 incomplete when the QR amount is missing', function () {
    $input = Fixtures::checkInput([
        'displayedAmount' => '1.00000000',
        'displayedNetwork' => 'BTC',
        'qrAmount' => null,
        'qrNetwork' => 'BTC',
    ]);

    $result = (new Scenario74Rule)->evaluate($input, Fixtures::referencePayment());

    expect($result->incomplete)->toBeTrue()
        ->and($result->triggered)->toBeFalse();
});

it('marks 7.4 incomplete when the QR network is missing', function () {
    $input = Fixtures::checkInput([
        'displayedAmount' => '1.00000000',
        'displayedNetwork' => 'BTC',
        'qrAmount' => '1.00000000',
        'qrNetwork' => null,
    ]);

    $result = (new Scenario74Rule)->evaluate($input, Fixtures::referencePayment());

    expect($result->incomplete)->toBeTrue()
        ->and($result->triggered)->toBeFalse();
});

it('marks 7.4 incomplete when both the QR amount and QR network are missing', function () {
    $input = Fixtures::checkInput([
        'displayedAmount' => '1.00000000',
        'displayedNetwork' => 'BTC',
        'qrAmount' => null,
        'qrNetwork' => null,
    ]);

    $result = (new Scenario74Rule)->evaluate($input, Fixtures::referencePayment());

    expect($result->incomplete)->toBeTrue()
        ->and($result->triggered)->toBeFalse();
});
