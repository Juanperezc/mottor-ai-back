# mottor-ai-back

Laravel REST API for **Mottor AI**, the *Mottor.ai Senior Full-Stack Challenge* ("Quote calculator with commercial rules").

> Working on this repo? Read [AGENTS.md](AGENTS.md) first. Following the specs is mandatory.

## Specs

This repo does **not** contain specs. The spec, the OpenAPI contract, the plan, the tasks and the fixtures live in **[mottor-ai-specs](https://github.com/Juanperezc/mottor-ai-specs)** (spec-anchored, GitHub Spec Kit):

- Constitution: [`.specify/memory/constitution.md`](https://github.com/Juanperezc/mottor-ai-specs/blob/main/.specify/memory/constitution.md)
- Feature specs: [`specs/`](https://github.com/Juanperezc/mottor-ai-specs/tree/main/specs) (`spec.md`, `plan.md`, `tasks.md`, `contracts/openapi.yaml`, `fixtures/`)

If a change alters behavior, update the spec in `mottor-ai-specs` **first**, then the code.

## Tech stack

| Area | Technology |
|---|---|
| Language | **PHP 8.5** (latest stable branch; 8.5.12 released 21 Oct 2026, [php.net](https://www.php.net/supported-versions.php)) |
| Framework | **Laravel 13** (latest major; `laravel/framework` v13.39.0 released 27 Oct 2026, [Packagist](https://packagist.org/packages/laravel/framework)) |
| Database | MySQL (`utf8mb4_0900_ai_ci` collation) |
| Local environment | [Laravel Sail](https://laravel.com/docs/sail) (Docker) |
| Queues | [Laravel Horizon](https://laravel.com/docs/horizon) + Redis |
| WebSockets | [Laravel Reverb](https://laravel.com/docs/reverb) (import progress and errors pushed to the frontend) |
| Persistence | Eloquent, **only** in persistence adapters. The domain (rules engine, money, ticket) is plain PHP with no framework dependencies |
| Money | [`brick/math`](https://github.com/brick/math) exact decimals, rounding `HALF_UP` by default (configurable) |
| Tests | PHPUnit 12 (Laravel 13 default) via `php artisan test` |
| Coverage | PCOV or Xdebug, both shipped in the Sail PHP 8.5 image |
| Code style | Laravel Pint |

## Quick start

```bash
git clone https://github.com/Juanperezc/mottor-ai-specs ../mottor-ai-specs   # default SPECS_PATH
cp .env.example .env
composer install                       # or the Sail one-off container, see Sail docs
./vendor/bin/sail up -d                # app, MySQL, Redis
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail artisan horizon      # queue workers (async import)
./vendor/bin/sail artisan reverb:start # websocket server
```

Tip: `alias sail='sh $([ -f sail ] && echo sail || echo vendor/bin/sail)'`.

## Test coverage

Run the tests with coverage inside Sail:

```bash
# PCOV (fast, coverage only)
./vendor/bin/sail artisan test --coverage

# Fail if total coverage drops below a threshold
./vendor/bin/sail artisan test --coverage --min=80

# Xdebug instead of PCOV
SAIL_XDEBUG_MODE=coverage ./vendor/bin/sail artisan test --coverage

# HTML and Clover reports
./vendor/bin/sail php vendor/bin/phpunit --coverage-html coverage/html --coverage-clover coverage/clover.xml
```

Where to look:

- Terminal: the per-file summary printed by `artisan test --coverage`.
- HTML report: open `coverage/html/index.html`.
- CI: `coverage/clover.xml` (uploaded as a build artifact).

`coverage/` is a build output and must not be committed.

### Current coverage

There is no application code yet, so there is no coverage to report. This table is updated when tests exist.

| Scope | Lines | Target |
|---|---|---|
| Domain (rules engine, money, ticket) | TBD | TBD |
| HTTP API | TBD | TBD |
| Total | TBD | TBD |

## Git flow

```text
staging ──► feat/<scope>-<description> ──PR──► staging ──PR (release)──► main
```

- Branch from `staging`: `feat/`, `fix/`, `refactor/`, `test/`, `docs/`, `chore/`.
- PR to `staging` with green CI and review. Release with a PR from `staging` to `main`. No direct commits to `staging` or `main`.

## Conventional Commits

`<type>(<optional scope>): <imperative description>`. Types: `feat`, `fix`, `refactor`, `test`, `docs`, `style`, `perf`, `build`, `ci`, `chore`, `revert`.

```text
feat(rules): add percentage discount strategy

Spec: 001-quotes-mvp
Tasks: T021, T023
```

## Referencing the spec in PRs

Every PR body includes `Spec: <feature>` and `Tasks: T0xx, ...` (task IDs from `tasks.md` in mottor-ai-specs). After merging, tick the tasks `[x]` in mottor-ai-specs.
