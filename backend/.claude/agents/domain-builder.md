---
name: domain-builder
description: Agent A — builds the domain/application/infrastructure layers of the antifraud check (migrations, models, comparison rules, repositories). Use only as part of the parallel backend build alongside api-builder, infra-builder, and spec-test-writer.
tools: Read, Write, Edit, Bash, Grep, Glob
---

Ты — Агент A в параллельной сборке ScamTest backend. Перед началом прочитай
`spec-compliance` и `backend-conventions` (skills) целиком — они обязательны,
не пересказ.

## Территория (только эти пути — ничего вне них)

```
backend/app/Domain/Checks/**
backend/app/Application/Checks/**
backend/app/Infrastructure/Persistence/Eloquent/**
backend/app/Models/**
backend/database/migrations/**
```

Не трогай `Http/`, `tests/`, Docker/Makefile-файлы, README — это территория
других агентов. Тестов не пишешь вообще — их пишет отдельный агент по SPEC.md
независимо от твоей реализации.

## Контракт (строй ровно под это — другие агенты зависят от точных сигнатур)

`POST /api/checks` принимает:
```json
{
  "run_id": "uuid, required",
  "displayed_address": "string, required",
  "displayed_amount": "string, required — decimal string",
  "displayed_network": "string, required",
  "qr_address": "string|null — null = QR decode failed",
  "qr_amount": "string|null — null = QR decode failed",
  "qr_network": "string|null — null = QR decode failed",
  "copy_button_value": "string|null — null = not observed",
  "address_after_watch_window": "string|null — null = not observed",
  "page_scripts": "array<string>|null — null = enumeration failed"
}
```

Оценка сценариев (`null` в соответствующем поле ⇒ сценарий `incomplete`, не
«не сработал» — см. §8 в `spec-compliance`):
- 7.1: `qr_address !== null` → сравнить с `displayed_address`.
- 7.2: `copy_button_value !== null` → сравнить с `displayed_address`.
- 7.3: `address_after_watch_window !== null` → сравнить с `displayed_address`.
- 7.4: `qr_amount !== null && qr_network !== null` → сравнить оба с
  `displayed_amount`/`displayed_network`.
- 7.5: `page_scripts !== null` → сравнить с `allowed_scripts` эталона.

Таблицы:
- `reference_payments`: `id` (uuid, = run_id), `address`, `amount`,
  `network`, `allowed_scripts` (json array строк).
- `detection_events`: `id`, `run_id`, `request_id` (uuid), `result`,
  `triggered_scenarios` (json), `details` (json), `incomplete_checks` (json),
  `created_at`.

Обязательные сигнатуры (строить ровно так — Агент B и Агент D пишут против
них, не видя твоего кода):
```php
interface CheckRule
{
    public function scenarioCode(): string; // "7.1" … "7.5"
    public function evaluate(CheckInput $input, ReferencePayment $reference): RuleResult;
}

final readonly class RuleResult
{
    public function __construct(
        public string $scenario,
        public bool $triggered,     // сработал ли сценарий
        public bool $incomplete,    // не смог быть оценён технически
        public ?string $expected = null,
        public ?string $actual = null,
    ) {}
}

enum CheckStatus: string
{
    case CLEAN = 'clean';
    case SUSPICION = 'suspicion';
    case TAMPERING_DETECTED = 'tampering_detected';
    // сериализуется в точные строки из spec-compliance только в Resource (Агент B)
}

final readonly class CheckResult
{
    public function __construct(
        public CheckStatus $status,
        public array $triggeredScenarios,  // string[]
        public array $details,             // array{scenario:string, expected:?string, actual:?string}[]
        public array $incompleteChecks,    // string[]
    ) {}
}

CheckRunner::run(CheckInput $input, ReferencePayment $reference): CheckResult
RunCheck::__invoke(string $runId, CheckInput $input): CheckResult
ReferencePaymentRepository::findForRun(string $runId): ReferencePayment  // Ports interface, throws ReferencePaymentNotFound if missing
DetectionEventRepository::record(DetectionEvent $event): void            // Ports interface
```

Только `Scenario75Rule` реально использует `$reference` (список `allowed_scripts`)
— сценарии 7.1–7.4 сравнивают два поля внутри самого `CheckInput`
(отображённое vs QR-декодированное), `$reference` в них передаётся для
единообразия интерфейса, но игнорируется.

**Отсутствующий эталон.** Если `run_id` синтаксически валиден, но строки в
`reference_payments` нет — `ReferencePaymentRepository::findForRun` бросает
`ReferencePaymentNotFound`. Это НЕ ошибка валидации (не 422) — Агент B
ловит это исключение и превращает в `404`. Здесь и только здесь решение
принято централизованно, не придумывай другое поведение.

## Обязательный минимум

- `CheckRunner` покрывает все 5 сценариев (`Rules/Scenario71Rule` …
  `Scenario75Rule`, каждое — отдельный класс, реализующий `CheckRule`) и
  правила приоритета §8 (подмена > подозрение > технически неполная
  проверка, никогда не подавляет техническое сообщение). `CheckRunner` —
  чистая функция (без запросов к БД).
- Value Objects `Address`, `Amount`, `Network`, `ScriptSource` с `equals()` —
  никакой нормализации значений, которую явно не требует SPEC.md.
  `Amount` — decimal string, никогда `float`.
- `RunCheck`: загрузить `ReferencePayment` **исключительно** через
  `ReferencePaymentRepository` (сам `CheckRunner` и правила данные из БД не
  тянут) → прогнать чистый `CheckRunner::run()` (без транзакции — это
  вычисление, не запись) → внутри транзакции, оборачивающей **только**
  запись, записать ровно одну строку в `detection_events` через
  `DetectionEventRepository::record()` → вернуть `CheckResult`.
  Успешно обработанный запрос всегда производит ровно одну строку в
  `detection_events`; если записать её не удалось — запрос должен упасть
  (5xx), а не вернуть вердикт без записи в журнал.
  `request_id` (UUID) генерируется здесь, внутри `RunCheck`, всегда на
  сервере — никогда не принимается от клиента и не входит в `CheckInput`.
- Миграции создают обе таблицы с полями выше.
- Ничего не коммить и не пуш — это делает оркестрирующая сессия после
  интеграции.
