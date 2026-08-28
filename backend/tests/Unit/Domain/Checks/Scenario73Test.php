<?php

/**
 * SPEC.md §7.3 / §9: адрес на странице изменяется после запуска проверки.
 *
 * - address_after_watch_window !== null → сравнить с displayed_address
 *   (значение, зафиксированное как опорное в момент запуска проверки).
 * - несовпадение → «Обнаружена подмена», с указанием, что адрес изменился
 *   уже после запуска проверки.
 * - совпадение (адрес не менялся за 5 секунд) → сценарий не срабатывает.
 * - address_after_watch_window === null (наблюдение технически не
 *   выполнено) → incomplete.
 *
 * Замер самих 5 секунд — обязанность фронтенда/наблюдателя, который передаёт
 * готовое значение; доменное правило только сравнивает два переданных
 * значения.
 */

use App\Domain\Checks\Rules\Scenario73Rule;
use Tests\Unit\Domain\Checks\Support\Fixtures;

it('triggers 7.3 when the address changes after the check starts', function () {
    $input = Fixtures::checkInput([
        'displayedAddress' => 'addr-at-start-aaaa',
        'addressAfterWatchWindow' => 'addr-after-5s-dddd',
    ]);

    $result = (new Scenario73Rule)->evaluate($input, Fixtures::referencePayment());

    expect($result->scenario)->toBe('7.3')
        ->and($result->triggered)->toBeTrue()
        ->and($result->incomplete)->toBeFalse();

    Fixtures::assertSamePair($result->expected, $result->actual, 'addr-at-start-aaaa', 'addr-after-5s-dddd');
});

it('does not trigger 7.3 when the address is unchanged after the watch window', function () {
    $input = Fixtures::checkInput([
        'displayedAddress' => 'addr-stable-value',
        'addressAfterWatchWindow' => 'addr-stable-value',
    ]);

    $result = (new Scenario73Rule)->evaluate($input, Fixtures::referencePayment());

    expect($result->triggered)->toBeFalse()
        ->and($result->incomplete)->toBeFalse();
});

it('marks 7.3 incomplete when the post-window address observation is unavailable', function () {
    $input = Fixtures::checkInput([
        'displayedAddress' => 'addr-at-start-aaaa',
        'addressAfterWatchWindow' => null,
    ]);

    $result = (new Scenario73Rule)->evaluate($input, Fixtures::referencePayment());

    expect($result->incomplete)->toBeTrue()
        ->and($result->triggered)->toBeFalse();
});
