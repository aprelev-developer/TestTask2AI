---
name: backend-conventions
description: Architecture, layering, DTO/repository/DI rules, API contract, persistence, testing, quality gates and anti-patterns for the ScamTest backend service. Use when writing or reviewing any PHP file, route, migration, Docker/Make config, or API response in backend/ — anywhere a design or contract choice could drift between agents A/B/C/D.
---

# Backend conventions — ScamTest antifraud utility

Decisions already made for this project (not open per-agent choices — change
them here, once, if they need to change, not ad hoc in one agent's output).

## Decision priority

When something conflicts, resolve in this order — never by agent preference:

1. `ScamTest/SPEC.md` (product/domain requirements)
2. The `spec-compliance` skill (distilled SPEC.md)
3. The API contract below
4. This skill
5. Laravel framework conventions

If a change would require breaking a higher-priority rule, **do not resolve it
locally** — report the conflict to the orchestrating session instead of
guessing.

## Stack

- Laravel, PHP pinned in `composer.json` (not "latest" — pin the version so
  agents don't drift on upgrade).
- PostgreSQL — own container/service, never SQLite/MySQL.
- Docker Compose: services `backend` (php-fpm + nginx or `artisan serve`) and
  `db` (postgres), on a shared network `scamtest` so a future `frontend`
  service can join.
- Tests: Pest.
- API docs: OpenAPI via `l5-swagger`, **PHP Attributes** (not legacy
  DocBlock annotations). Pin the exact version in `composer.json`, not just
  the package name — attribute syntax support varies between versions.
- Static analysis: Larastan (PHPStan for Laravel) — `composer require --dev
  larastan/larastan`, `phpstan.neon` at max reasonable level for a small
  codebase.
- **All packages above are installed once, by the orchestrating session,
  before any build agent starts** — the Laravel skeleton and `composer.json`
  exist before A/B/C/D run in parallel. No build agent runs `composer
  require`/`composer create-project` itself; four agents touching
  `composer.json` concurrently is a guaranteed conflict.
- Formatting: Laravel Pint, PSR-12.
- Error monitoring: Sentry SDK via `SENTRY_LARAVEL_DSN` env var — no DSN
  committed; missing DSN must not break local/dev runs.
- **Deliberately not used this cycle**: `spatie/laravel-data` (a plain
  `readonly` DTO class gives the same value for the 1-2 DTOs this project
  needs, no dependency), full idempotency-key deduplication (no SPEC or
  grading requirement for it — see Persistence), Serena/semantic code index
  (its value is navigating a *large or foreign* codebase; this one is built
  from scratch — revisit post-submission if the codebase grows).

## Architecture — layers and dependency direction

```
Http → Application → Domain
Infrastructure → Domain
Domain → nothing Laravel-specific (no Eloquent, no Facades, no Request)
```

```
app/
├── Http/
│   ├── Controllers/        # thin: validate → call use case → map response
│   ├── Requests/            # validation rules ONLY, see below
│   └── Resources/
├── Application/
│   └── Checks/
│       └── RunCheck.php     # use case: orchestrates repo + rules + repo
├── Domain/
│   └── Checks/
│       ├── CheckInput.php   # DTO in
│       ├── CheckResult.php  # DTO out
│       ├── CheckRunner.php  # runs rules, applies §8 priority
│       ├── CheckRule.php    # interface
│       ├── Rules/
│       │   ├── Scenario71Rule.php
│       │   ├── Scenario72Rule.php
│       │   ├── Scenario73Rule.php
│       │   ├── Scenario74Rule.php
│       │   └── Scenario75Rule.php
│       ├── ValueObjects/
│       │   ├── Address.php
│       │   ├── Amount.php
│       │   ├── Network.php
│       │   └── ScriptSource.php
│       └── Ports/            # repository interfaces — ports, not domain objects
│           ├── ReferencePaymentRepository.php
│           └── DetectionEventRepository.php
├── Infrastructure/
│   └── Persistence/
│       └── Eloquent/
│           ├── EloquentReferencePaymentRepository.php
│           └── EloquentDetectionEventRepository.php
└── Models/
    ├── ReferencePayment.php
    └── DetectionEvent.php
```

Repository **interfaces** live in `Domain/Checks/Ports/` (they're ports the
domain depends on, not domain objects themselves), implementations in
`Infrastructure/Persistence/Eloquent/`. Only two repositories exist —
introduce a new one only for a genuine new persistence boundary, not
mechanically per model.

### Eloquent models

Models are persistence models: keep them to persistence concerns and simple
relationship/query-scope definitions. Business decisions that affect a
verdict belong to `Domain`/`Application`, never to a model method — no
`$payment->isSuspicious()`, `$payment->shouldTriggerScenario71()`,
`$payment->calculateVerdict()` and similar.

### Mappers

A mapper converts between layers/representations (request → DTO, result →
JSON). Mappers MUST NOT contain business decisions or branch on business
state — if a mapper needs an `if` on domain data, that decision belongs in
`Domain`/`Application`, not the mapper.

### Helpers

Prefer a named class, value object, or domain service over a generic helper
function. Global helpers are reserved for truly generic framework/application
utilities — not a dumping ground (`AddressHelper`, `CheckHelper`,
`PaymentHelper` and similar are anti-patterns, see below).

### Use cases (Application layer)

One use case = one externally meaningful action, named after that action
(`RunCheck`, not `CheckManager`/`BackendService`/`ApplicationService`). Use a
**Pipeline** only when an operation has multiple ordered, composable
processing stages — not merely to make a long method look shorter. Use a
separate use case when responsibilities represent distinct application
actions rather than stages of one action.

### Configuration

Application code reads configuration via Laravel `config()`, never `env()`
outside config files — `env()` belongs only inside `config/*.php`.

### Controllers

Thin: validate via FormRequest → build DTO → call the `Application` use case
→ map the result DTO to a Resource/JSON response. No business logic, no
direct Eloquent queries.

### FormRequest

`FormRequest` MAY contain request-specific validation rules and lightweight
input normalization required for validation. Business decisions, database
queries and domain logic MUST NOT live in `FormRequest`. If a rule needs
domain logic, extract it into a `Rule`/validator class and call it from
`rules()` — do not inline logic in the request class.

### Dependency injection

Dependencies MUST be injected through constructors. Do not resolve
application/domain dependencies via `app()`, `resolve()`, `Container::get()`
or Facades inside `Application`/`Domain` code — constructor injection only.
Laravel's container wires it automatically; no manual resolution needed.

### DTOs and models

- Pass the smallest meaningful data structure a dependency needs. Do not pass
  a whole Eloquent model into `Domain`/`Application` code when one or two
  values suffice — but don't destructure a value that's itself a meaningful
  domain concept (e.g. pass an `Address` value object whole, not its raw
  string).
- DTOs are plain `readonly` classes with typed properties. No package.

### Comments and types

- Type-hint everything; PHP native types are the contract.
- No PHPDoc that only repeats what the type declaration already says.
- Comments explain **why**, not what — a well-named method needs no comment
  describing what it does.

## Domain — invariants

- **Prefer readonly/immutable.** DTOs, value objects, and immutable domain
  data use readonly properties/classes. Domain objects don't expose mutable
  state unless mutation is an explicit business requirement — don't write
  `$input->address = $newAddress`.
- **Never normalize, trim, lowercase, decode, or otherwise transform an
  observed value unless SPEC.md explicitly requires it for that check.** The
  whole point is detecting subtle differences — silent normalization can hide
  exactly the substitution we're supposed to catch.
- **Preserve raw observations.** Comparison happens against the original
  submitted values; the values used for a verdict must be traceable in the
  journal, not only a derived/summarized form.
- **Money is never `float`.** Represent amounts as decimal strings or integer
  minor units; compare deterministically, never with `==`/`===` on floats.
- Value objects (`Address`, `Amount`, `Network`, `ScriptSource`) carry their
  own `equals()` — comparisons go through the value object, not raw `===` on
  strings scattered across the codebase.
- Internal result status is a PHP `enum`; it is serialized to the exact
  Russian strings from the `spec-compliance` skill only at the API boundary
  (Resource/response layer), never compared against those strings elsewhere
  in the code.

## API contract

- Single endpoint: `POST /api/checks` — frontend sends the raw observations
  (displayed address, QR-decoded address/amount/network, value captured at
  copy-button click, address value after the 5s window, list of `<script>`
  sources present). Backend does the comparison — never trust a
  verdict computed client-side.
- Validate with a `FormRequest`; reject with `422` and field-level errors on
  malformed input — never `500` on bad client input.
- Success response:
  ```json
  {
    "result": "Обнаружена подмена",
    "triggered_scenarios": ["7.1"],
    "details": [{"scenario": "7.1", "expected": "...", "actual": "..."}],
    "incomplete_checks": [],
    "incomplete_message": null
  }
  ```
  `result` is always one of the three exact strings from `spec-compliance`.
  `incomplete_checks` lists scenario codes that couldn't be evaluated (§8),
  present even when `result` already reports a finding. `incomplete_message`
  is SPEC.md §8's exact technical message
  (`"Проверка выполнена не полностью"`) when `incomplete_checks` is
  non-empty, `null` otherwise — the literal string lives once, in the
  Resource, never inferred by the frontend from array emptiness.
- Error response: `{"error": {"message": "...", "fields": {"field": ["..."]}}}`
  — `422` for validation errors (with `fields`); `404` when `run_id` is
  syntactically valid but no matching `reference_payments` row exists
  (without `fields` — see Domain contract below).

## Test-fixture endpoints (tooling, not part of SPEC.md)

SPEC.md never says where a run's ground-truth data comes from — it assumes
a reference payment already exists. These two endpoints exist purely so the
frontend can create and fetch that ground truth; they carry no fraud-scenario
logic and are not covered by `spec-compliance`.

- `POST /api/reference-payments` — creates a new test run. Body: all fields
  optional (`address`, `amount`, `network`, `allowed_scripts`) — any field
  left out is filled in by `ReferencePaymentGenerator` with a random,
  clearly-fictitious value (never something that could pass as a real
  address). Response `201`:
  ```json
  {
    "run_id": "uuid",
    "address": "...",
    "amount": "...",
    "network": "...",
    "allowed_scripts": ["..."]
  }
  ```
- `GET /api/reference-payments/{run_id}` — returns the same shape, `200`, or
  `404` (same error envelope as `POST /api/checks`) if the run doesn't exist.
- Validation for `POST`: same `422` envelope as `POST /api/checks`;
  `amount`, if present, uses the same decimal-string regex
  (`/^\d+(\.\d+)?$/`) as `StoreCheckRequest`.
- `run_id` here is always server-generated (UUID) — same rule as
  `request_id` in the checks journal: never accepted from the client.
- `ReferencePaymentRepository` gains `create(ReferencePayment $payment):
  void` alongside the existing `findForRun()`.
- `ReferencePaymentGenerator` (`Domain/Checks/`) is a pure class — no DB, no
  Laravel — that fills in whichever of `address`/`amount`/`network`/
  `allowedScripts` weren't supplied. Called only from the `Application`
  use case that creates a reference payment, never from `CheckRunner` or
  any `CheckRule` — generation has nothing to do with the comparison logic.

## Domain contract (canonical)

`.claude/agents/*.md` each quote a working copy of this for standalone use
(agents run in isolated contexts) — if a copy ever drifts, **this skill
wins**, per Decision priority above.

```php
interface CheckRule {
    public function scenarioCode(): string; // "7.1" … "7.5"
    public function evaluate(CheckInput $input, ReferencePayment $reference): RuleResult;
}

final readonly class RuleResult {
    public function __construct(
        public string $scenario,
        public bool $triggered,
        public bool $incomplete,
        public ?string $expected = null,
        public ?string $actual = null,
    ) {}
}

enum CheckStatus: string {
    case CLEAN = 'clean';
    case SUSPICION = 'suspicion';
    case TAMPERING_DETECTED = 'tampering_detected';
    // mapped to the exact spec-compliance strings only in the API Resource
}

final readonly class CheckResult {
    public function __construct(
        public CheckStatus $status,
        public array $triggeredScenarios,  // string[]
        public array $details,             // array{scenario, expected, actual}[]
        public array $incompleteChecks,    // string[]
    ) {}
}
```

Only `Scenario75Rule` actually reads `$reference` (the allowed-script list);
7.1–7.4 compare two fields already present in `CheckInput` and ignore
`$reference` — it's passed for interface uniformity, not because they need
it.

**Missing reference payment.** A syntactically valid `run_id` for which no
`reference_payments` row exists is a `404` application error (via a
`ReferencePaymentNotFound` exception the controller maps to `404`), never a
`422` validation error — the request body itself was valid.

**`request_id`** is always generated server-side (UUID) inside `RunCheck`,
purely for journal traceability — never accepted from the client, never part
of `CheckInput`.

## Persistence

- `reference_payments` — ground-truth payment data for a test run (address,
  amount, network, allowed script list).
- `detection_events` — the journal: run id, `request_id` (UUID, generated
  server-side if the client doesn't send one — for traceability only, **not**
  used for deduplication or idempotent replay), scenario(s) triggered,
  compared raw values, result, timestamp, reason. Every `POST /api/checks`
  call writes exactly one row.
- **Transaction invariant:** the transaction wraps **only** the persistence
  write (`DetectionEventRepository::record()`), not the domain computation —
  `CheckRunner` is a pure function and runs outside any transaction. Every
  *successfully processed* `POST /api/checks` produces exactly one
  `detection_events` row; a request that cannot persist its event MUST fail
  (5xx) rather than return a successful verdict with no journal entry.

## Testing

- **Unit** (`tests/Unit/Domain/Checks/`) — one file per scenario rule
  (`Scenario71Test.php` … `Scenario75Test.php`), plain Pest/PHPUnit, no
  Laravel bootstrapping needed since `Domain` has no framework dependency.
- **Priority** (`tests/Unit/Domain/Checks/PriorityTest.php`) — the §8
  priority rules: substitution-over-suspicion, incomplete-check accompanying
  a positive result, incomplete-check standalone.
- **Feature** (`tests/Feature/Api/ChecksTest.php`) — `POST /api/checks`
  end-to-end: validation errors, response contract, persistence, transaction
  behavior.

## Quality gates

Every backend change must pass before it's considered done:

- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse` (Larastan)
- `php artisan test` (Pest suite)
- OpenAPI spec generates without error

## Definition of done

A backend change is complete only when:

- implementation follows the layer/dependency rules above;
- relevant unit/feature tests exist or were updated;
- the API contract/OpenAPI is updated when the change affects it;
- migrations are included when persistence changes;
- Pint, Larastan and Pest all pass;
- no unrelated files were modified;
- no new package, tool, or architectural pattern was introduced without
  flagging it to the orchestrating session first.

## Security

- No secrets, `.env` files, or credentials committed — `.env.example` only.
- No raw SQL string interpolation — Eloquent/query builder with bindings.

## Agent discipline

- Agents A, B, C, D never edit files outside their own territory (see the
  agent definitions in `.claude/agents/`) — cross-cutting changes go through
  the orchestrating session, not through one agent reaching into another's
  files.
- Before modifying backend code, an agent identifies: which layer the change
  belongs to, which existing invariant/contract it touches, which tests prove
  the behavior, and whether it crosses into another agent's territory.
- If a proposed change contradicts this skill or `SPEC.md`, report the
  conflict to the orchestrating session — do not resolve it locally.

## Anti-patterns — do NOT

- Put business logic in controllers or `FormRequest` classes.
- Query Eloquent from `Domain` code, or load `ReferencePayment` from
  anywhere other than `RunCheck` calling `ReferencePaymentRepository` —
  `CheckRunner` and every `CheckRule` are pure, given data, never fetch it.
- Let a Resource/mapper decide status, priority, triggered scenarios, or
  incomplete checks — it serializes an already-computed `CheckResult`, full
  stop.
- Use Facades, static calls (`SomeService::run(...)`), or `app()`/`resolve()`
  for application/domain behavior when the dependency can be injected instead.
- Pass `Request` objects or whole Eloquent models into `Domain`/`Application`
  when a DTO/value object suffices.
- Create a repository mechanically for every model.
- Create generic `BaseService`/`BaseRepository` abstractions.
- Split a service by line count rather than by distinct responsibility.
- Duplicate a validation/business rule in more than one place.
- Introduce a new design pattern, package, or tool not listed in this skill
  without flagging it to the orchestrating session first.
- Add a comment that only restates the code.
