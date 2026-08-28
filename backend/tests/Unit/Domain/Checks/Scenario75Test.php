<?php

/**
 * SPEC.md §7.5 / §9: подключён скрипт, которого нет в разрешённом списке.
 *
 * - page_scripts !== null → сравнить с allowed_scripts эталона
 *   (ReferencePayment).
 * - присутствие хотя бы одного неразрешённого скрипта → сработал сценарий
 *   (результат «Есть подозрение» формируется на уровне CheckRunner/§8, не
 *   здесь — правило только сообщает triggered=true и какой скрипт лишний).
 * - список скриптов страницы — точное совпадение или подмножество
 *   разрешённого списка → сценарий не срабатывает (отсутствие разрешённого
 *   скрипта само по себе не является лишним/неразрешённым скриптом).
 * - page_scripts === null (список скриптов не удалось прочитать) →
 *   incomplete.
 */

use App\Domain\Checks\Rules\Scenario75Rule;
use Tests\Unit\Domain\Checks\Support\Fixtures;

it('triggers 7.5 when a disallowed script is present on the page', function () {
    $input = Fixtures::checkInput([
        'pageScripts' => ['https://payments.example/checkout.js', 'https://evil.example/tracker.js'],
    ]);
    $reference = Fixtures::referencePayment([
        'allowedScripts' => ['https://payments.example/checkout.js'],
    ]);

    $result = (new Scenario75Rule)->evaluate($input, $reference);

    expect($result->scenario)->toBe('7.5')
        ->and($result->triggered)->toBeTrue()
        ->and($result->incomplete)->toBeFalse();
});

it('does not trigger 7.5 when the page scripts exactly match the allowed list', function () {
    $input = Fixtures::checkInput([
        'pageScripts' => ['https://payments.example/checkout.js'],
    ]);
    $reference = Fixtures::referencePayment([
        'allowedScripts' => ['https://payments.example/checkout.js'],
    ]);

    $result = (new Scenario75Rule)->evaluate($input, $reference);

    expect($result->triggered)->toBeFalse()
        ->and($result->incomplete)->toBeFalse();
});

it('does not trigger 7.5 when the page loads only a subset of the allowed scripts', function () {
    $input = Fixtures::checkInput([
        'pageScripts' => [],
    ]);
    $reference = Fixtures::referencePayment([
        'allowedScripts' => ['https://payments.example/checkout.js'],
    ]);

    $result = (new Scenario75Rule)->evaluate($input, $reference);

    expect($result->triggered)->toBeFalse()
        ->and($result->incomplete)->toBeFalse();
});

it('marks 7.5 incomplete when the page script list could not be read', function () {
    $input = Fixtures::checkInput([
        'pageScripts' => null,
    ]);
    $reference = Fixtures::referencePayment();

    $result = (new Scenario75Rule)->evaluate($input, $reference);

    expect($result->incomplete)->toBeTrue()
        ->and($result->triggered)->toBeFalse();
});
