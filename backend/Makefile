SHELL := /bin/sh
COMPOSE := docker compose

.PHONY: up down restart build migrate fresh test logs sh db-sh pint stan

## Build (if needed, quietly) and start backend + db, run migrations
## automatically. Build output is suppressed on purpose — `--build` alone
## still streams the full build trace even with `-d`; `build --quiet` first
## keeps this readable for a non-technical teammate, errors still surface.
up:
	$(COMPOSE) build --quiet
	$(COMPOSE) up -d

## Stop and remove containers (keeps the db volume/data).
down:
	$(COMPOSE) down

restart: down up

## Rebuild the backend image without starting containers.
build:
	$(COMPOSE) build backend

## Run pending migrations against the running (or a one-off) db.
migrate:
	$(COMPOSE) run --rm backend php artisan migrate --force

## Drop all tables and re-run every migration from scratch.
fresh:
	$(COMPOSE) run --rm backend php artisan migrate:fresh --force

## Run the Pest test suite inside a one-off backend container.
test:
	$(COMPOSE) run --rm backend php artisan test

## Follow logs from both services.
logs:
	$(COMPOSE) logs -f

## Shell into the running backend container.
sh:
	$(COMPOSE) exec backend sh

## Shell into the running db container (psql).
db-sh:
	$(COMPOSE) exec db psql -U scamtest -d scamtest

## Formatting / static analysis convenience targets (see backend-conventions skill).
pint:
	$(COMPOSE) run --rm backend vendor/bin/pint

stan:
	$(COMPOSE) run --rm backend vendor/bin/phpstan analyse
