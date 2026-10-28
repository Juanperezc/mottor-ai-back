# Quickstart: Quotes MVP (backend)

> Guía de validación end-to-end una vez implementado. Los comandos son orientativos hasta tener `plan.md` aprobado.

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed          # currencies, exchange_rates, clients, commercial_rules, quotes demo
php artisan queue:work &            # procesa imports
php artisan serve                   # http://localhost:8000/api
```

## Escenarios de verificación (mapean a la spec)
1. **US1**: `curl 'localhost:8000/api/quotes?perPage=25&currency=EUR'` → `data[]` + `meta`; montos como string; `displayCurrency = "EUR"`.
2. **US1 filtro**: `curl 'localhost:8000/api/clients?search=acm'` y después `.../quotes?clientId={id}`.
3. **US2**: `curl -F file=@tests/fixtures/dataset-valid-with-errors.json localhost:8000/api/imports` → `202`; repetir `GET /api/imports/{id}` hasta `completed_with_errors`; `GET /api/imports/{id}/errors`.
4. **US3**: `curl localhost:8000/api/quotes/1` → `appliedRules` ordenadas; el último `runningTotal` es igual a `total`.
5. **Ticket**: `curl -X PATCH -H 'Content-Type: application/json' -d '{"header":"Gracias","footer":"Vuelva pronto"}' localhost:8000/api/quotes/1/ticket` → `rendered` a 40 columnas.
6. **Tests**: `./vendor/bin/pest` (unit + feature + contract) · `./vendor/bin/phpstan` · `./vendor/bin/pint --test`.
