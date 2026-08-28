<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Как запустить

Требуется только Docker Desktop / Docker Engine с плагином Compose (`docker
compose version`). Локально устанавливать PHP, Composer или Postgres не
нужно — всё собирается и запускается в контейнерах.

### Быстрый старт

Команды — из **корня репозитория** (там есть тонкий `Makefile`,
перенаправляющий сюда через `make -C backend`) или прямо из `backend/` —
одинаково работают из обеих директорий:

```bash
cp .env.example .env   # если .env ещё нет; значения уже настроены под Docker
make up
```

`make up` — это `docker compose build --quiet && docker compose up -d`:
собирает образ backend (PHP 8.2 + расширения для Postgres) без вывода
трейса сборки, поднимает Postgres (`db`), дожидается его healthcheck'а и
**автоматически прогоняет миграции** перед стартом сервера — никаких
ручных шагов после `make up` не требуется.

Backend слушает `http://localhost:8000` (порт настраивается через
`APP_PORT` в `.env`, по умолчанию `8000`).

Проверить, что всё поднялось:

```bash
curl -i http://localhost:8000/up        # health-check Laravel, ожидаем 200
docker compose logs backend --tail 50   # в логе видно "Running migrations" и "Server running on"
```

### Makefile

| Команда        | Что делает |
|----------------|------------|
| `make up`      | Собрать образ и поднять `backend` + `db` (с автоматическими миграциями) |
| `make down`    | Остановить и удалить контейнеры (данные БД в volume сохраняются) |
| `make restart` | `down` + `up` |
| `make build`   | Пересобрать образ backend без запуска |
| `make migrate` | Прогнать неприменённые миграции (`migrate --force`) |
| `make fresh`   | Пересоздать схему с нуля (`migrate:fresh --force`) |
| `make test`    | Прогнать тесты Pest (`php artisan test`) в одноразовом контейнере |
| `make logs`    | Логи обоих сервисов (`docker compose logs -f`) |
| `make sh`      | Шелл внутри контейнера backend |
| `make db-sh`   | `psql` внутри контейнера db |
| `make pint`    | Проверка/автофикс стиля кода (Laravel Pint) |
| `make stan`    | Статический анализ (Larastan/PHPStan) |

### Переменные окружения

`.env.example` — шаблон без секретов. `DB_*` уже настроены под сервис `db`
из `docker-compose.yml` (`DB_HOST=db`, база/юзер/пароль — `scamtest`).
`SENTRY_LARAVEL_DSN` по умолчанию пустой: без DSN Sentry SDK ничего никуда
не отправляет и приложение работает как обычно — DSN нужно указать только
в реальном окружении с мониторингом.

### Сеть

Сервисы `backend` и `db` находятся в общей Docker-сети `scamtest`
(external name `scamtest`) — к ней сможет подключиться будущий сервис
`frontend`, если его добавят отдельным `docker-compose.yml`/проектом.

### Если что-то пошло не так

- `make down` затем `make up` — пересоздать контейнеры.
- `docker compose down -v` — снести и volume с данными Postgres (полный
  сброс БД), затем `make up` заново.
- `docker compose logs db` — если backend не может подключиться к базе,
  сначала проверить, что `db` прошёл healthcheck (`docker compose ps`
  должен показывать `db` как `healthy`).

### API — примеры curl

`make up` сеет одну демо-запись `reference_payments`
(`run_id = 11111111-1111-1111-1111-111111111111`, адрес `addr-real`, сумма
`1.00000000`, сеть `BTC`, разрешённый скрипт
`https://payments.example/checkout.js`) — ниже примеры бьют по ней напрямую,
без ручной подготовки.

**Новый тестовый запуск (генерация эталонных данных):**
```bash
curl -s -X POST http://localhost:8000/api/reference-payments -H "Content-Type: application/json" -d '{}'
# → {"run_id":"...", "address":"test-addr-...", "amount":"...", "network":"BTC", "allowed_scripts":["https://payments.example/checkout.js"]}

curl -s -X POST http://localhost:8000/api/reference-payments -H "Content-Type: application/json" -d '{"network": "ETH"}'
# → network остаётся "ETH", остальное сгенерировано (например address вида "0x" + 40 hex-символов)
```

**Получить эталон повторно:**
```bash
curl -s http://localhost:8000/api/reference-payments/<run_id из POST выше>
# → тот же JSON, что вернул POST; 404, если run_id не существует
```

**Подмены нет:**
```bash
curl -s -X POST http://localhost:8000/api/checks -H "Content-Type: application/json" -d '{
  "run_id": "11111111-1111-1111-1111-111111111111",
  "displayed_address": "addr-real", "displayed_amount": "1.00000000", "displayed_network": "BTC",
  "qr_address": "addr-real", "qr_amount": "1.00000000", "qr_network": "BTC",
  "copy_button_value": "addr-real", "address_after_watch_window": "addr-real",
  "page_scripts": ["https://payments.example/checkout.js"]
}'
# → {"result":"Подмена не обнаружена","triggered_scenarios":[],"details":[],"incomplete_checks":[],"incomplete_message":null}
```

**Обнаружена подмена (сценарий 7.1 — адрес в QR ≠ адрес на странице):**
```bash
curl -s -X POST http://localhost:8000/api/checks -H "Content-Type: application/json" -d '{
  "run_id": "11111111-1111-1111-1111-111111111111",
  "displayed_address": "addr-real", "displayed_amount": "1.00000000", "displayed_network": "BTC",
  "qr_address": "addr-EVIL", "qr_amount": "1.00000000", "qr_network": "BTC",
  "copy_button_value": "addr-real", "address_after_watch_window": "addr-real",
  "page_scripts": ["https://payments.example/checkout.js"]
}'
# → {"result":"Обнаружена подмена","triggered_scenarios":["7.1"],"details":[{"scenario":"7.1","expected":"addr-real","actual":"addr-EVIL"}],"incomplete_checks":[],"incomplete_message":null}
```

**Неизвестный `run_id` (404, не 422 — тело запроса синтаксически валидно):**
```bash
curl -s -o /dev/null -w "%{http_code}\n" -X POST http://localhost:8000/api/checks -H "Content-Type: application/json" -d '{
  "run_id": "99999999-9999-9999-9999-999999999999",
  "displayed_address": "a", "displayed_amount": "1.00", "displayed_network": "BTC",
  "qr_address": null, "qr_amount": null, "qr_network": null,
  "copy_button_value": null, "address_after_watch_window": null, "page_scripts": null
}'
# → 404
```

Полная документация эндпоинта (OpenAPI/Swagger UI):
`http://localhost:8000/api/documentation`.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[WebReinvent](https://webreinvent.com/)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Jump24](https://jump24.co.uk)**
- **[Redberry](https://redberry.international/laravel/)**
- **[Active Logic](https://activelogic.com)**
- **[byte5](https://byte5.de)**
- **[OP.GG](https://op.gg)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
