<?php

/**
 * SPEC.md §7.2 / §9: при нажатии кнопки копирования подставляется другой
 * адрес.
 *
 * - copy_button_value !== null → сравнить с displayed_address.
 * - несовпадение → «Обнаружена подмена», оба значения адреса присутствуют.
 * - совпадение → сценарий не срабатывает.
 * - copy_button_value === null (кнопку не нажимали / значение не
 *   перехвачено) → проверка не выполнена технически (incomplete).
 */

use App\Domain\Checks\Rules\Scenario72Rule;
use Tests\Unit\Domain\Checks\Support\Fixtures;

it('triggers 7.2 when the copy-button value differs from the displayed address', function () {
    $input = Fixtures::checkInput([
        'displayedAddress' => 'addr-displayed-aaaa',
        'copyButtonValue' => 'addr-clipboard-cccc',
    ]);

    $result = (new Scenario72Rule)->evaluate($input, Fixtures::referencePayment());

    expect($result->scenario)->toBe('7.2')
        ->and($result->triggered)->toBeTrue()
        ->and($result->incomplete)->toBeFalse();

    Fixtures::assertSamePair($result->expected, $result->actual, 'addr-displayed-aaaa', 'addr-clipboard-cccc');
});

it('does not trigger 7.2 when the copy-button value matches the displayed address', function () {
    $input = Fixtures::checkInput([
        'displayedAddress' => 'addr-same-value',
        'copyButtonValue' => 'addr-same-value',
    ]);

    $result = (new Scenario72Rule)->evaluate($input, Fixtures::referencePayment());

    expect($result->triggered)->toBeFalse()
        ->and($result->incomplete)->toBeFalse();
});

it('marks 7.2 incomplete when no copy-button value was captured', function () {
    $input = Fixtures::checkInput([
        'displayedAddress' => 'addr-displayed-aaaa',
        'copyButtonValue' => null,
    ]);

    $result = (new Scenario72Rule)->evaluate($input, Fixtures::referencePayment());

    expect($result->incomplete)->toBeTrue()
        ->and($result->triggered)->toBeFalse();
});
