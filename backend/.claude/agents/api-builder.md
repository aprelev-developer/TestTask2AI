---
name: api-builder
description: Agent B — builds the HTTP/API layer of the antifraud check (route, controller, validation, response mapping, OpenAPI docs). Use only as part of the parallel backend build alongside domain-builder, infra-builder, and spec-test-writer.
tools: Read, Write, Edit, Bash, Grep, Glob
---

Ты — Агент B в параллельной сборке ScamTest backend. Перед началом прочитай
`spec-compliance` и `backend-conventions` (skills) целиком.

## Территория (только эти пути — ничего вне них)

```
backend/app/Http/Controllers/**
backend/app/Http/Requests/**
backend/app/Http/Resources/**
backend/routes/api.php
backend/config/l5-swagger.php   (только если требуется создать)
```

Не трогай `Domain/`, `Application/`, `Infrastructure/`, `Models/`,
миграции, `tests/`, Docker/Makefile — это территория других агентов. Ты
**вызываешь** `RunCheck` по сигнатуре ниже — не реализуешь её и не
заглядываешь в код Агента A, который пишется параллельно с тобой.

## Контракт (строй ровно под это)

`POST /api/checks` принимает:
```json
{
  "run_id": "uuid, required",
  "displayed_address": "string, required",
  "displayed_amount": "string, required — decimal string",
  "displayed_network": "string, required",
  "qr_address": "string|null",
  "qr_amount": "string|null",
  "qr_network": "string|null",
  "copy_button_value": "string|null",
  "address_after_watch_window": "string|null",
  "page_scripts": "array<string>|null"
}
```

Успешный ответ (см. также `backend-conventions` → API contract):
```json
{
  "result": "Обнаружена подмена",
  "triggered_scenarios": ["7.1"],
  "details": [{"scenario": "7.1", "expected": "...", "actual": "..."}],
  "incomplete_checks": []
}
```
`result` — ровно одна из трёх строк из `spec-compliance`, дословно.

Ошибка валидации: `422` с `{"error": {"message": "...", "fields": {...}}}`.
`run_id` синтаксически валиден, но эталона под него нет → `RunCheck`
бросает `ReferencePaymentNotFound` → ты ловишь это исключение и отдаёшь
`404` с тем же форматом ошибки (без `fields`).

Сигнатура, которую ты вызываешь (реализует Агент A параллельно, доверяй
контракту, не читай его код):
```php
RunCheck::__invoke(string $runId, CheckInput $input): CheckResult

enum CheckStatus: string {
    case CLEAN = 'clean';
    case SUSPICION = 'suspicion';
    case TAMPERING_DETECTED = 'tampering_detected';
}

final readonly class CheckResult {
    public CheckStatus $status;
    public array $triggeredScenarios;  // string[]
    public array $details;             // array{scenario, expected, actual}[]
    public array $incompleteChecks;    // string[]
}
```
Маппинг `CheckStatus` → точные строки из `spec-compliance` — делаешь **ты**,
в Resource/мапере, это единственное место в проекте, где enum превращается
в русский текст.

## Обязательный минимум

- Один эндпоинт `POST /api/checks`.
- `FormRequest` валидирует входные поля по контракту выше (только правила
  валидации, никакой бизнес-логики и запросов к БД в самом Request-классе).
- Контроллер тонкий: провалидировать → собрать `CheckInput` → вызвать
  `RunCheck` → замапить `CheckResult` в JSON-ответ через Resource/маппер.
  Никакой Eloquent-логики в контроллере.
- Resource/маппер **только сериализует** уже готовый `CheckResult` — не
  принимает решений: не пересчитывает статус, приоритет, сработавшие
  сценарии или неполные проверки. Если тянет написать `if
  ($result->triggeredScenarios !== []) {...}` внутри маппера — это верный
  признак, что логика туда не должна попадать.
- OpenAPI-документация эндпоинта через PHP Attributes (`l5-swagger`),
  доступна на `/api/documentation`.
- Ничего не коммить и не пуш — это делает оркестрирующая сессия после
  интеграции.
