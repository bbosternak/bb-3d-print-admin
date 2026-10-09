# BB 3DPrint Admin

A private administration application for a small 3D-printing business, built with
Laravel 13, Filament 5, Eloquent, and MySQL/MariaDB. All administration pages require
authentication. There is no public registration, storefront, customer/order
management, inventory, or production queue.

## Requirements

- PHP 8.3+ with BCMath, PDO MySQL, Intl, Mbstring, DOM, Fileinfo, and Laravel's
  standard extensions.
- Composer 2, MySQL 8+ or MariaDB 10.6+.
- Node.js 22.12+ and npm to build the Filament panel theme. Base panel assets
  are published by Composer's post-install/update scripts.

## Installation

From the project root:

```sh
composer install
cp .env.example .env
php artisan key:generate
```

Create a database and a dedicated database user. Set the following in `.env`
(never commit this file):

```dotenv
APP_NAME="BB 3DPrint Admin"
APP_URL=http://localhost:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bb_3dprint_admin
DB_USERNAME=bb_3dprint_admin
DB_PASSWORD=
```

Set `DB_PASSWORD` to your database user's password. MariaDB can use the same
`mysql` connection. An optional `DB_SOCKET` supports Unix-socket connections.

```sh
php artisan migrate
php artisan make:filament-user
npm ci
npm run build
php artisan serve
```

Visit `/admin` and sign in with the administrator you explicitly created.
Every provisioned user has administration access; create accounts only for
trusted staff. The seeder does **not** create a default account or password.
Product images are private uploads on the local filesystem and are served with
temporary signed URLs; no public storage symlink is needed.

For production, point the web server at `public/`, use HTTPS, set `APP_ENV=production`,
`APP_DEBUG=false`, the correct `APP_URL`, and `SESSION_SECURE_COOKIE=true`.
Keep the panel behind your private network/VPN where possible. Give the PHP process
write access to `storage/` and `bootstrap/cache/`, run `php artisan migrate --force`,
build the assets with `npm ci && npm run build`, then `php artisan optimize`.
Back up the database and private uploads together.
No payment gateway, external accounting service, or background worker is needed
for these synchronous business modules.

## Features

- **Products:** standard batch quantity, weight and printing seconds, multiple
  filament usages, images, active status, SKU, per-unit cost/profit figures,
  material filters, and independent duplication.
- **Filaments:** brand/material/color search and filters, purchase price, spool
  weight and derived EUR/kg price. Price edits immediately affect estimates;
  they never create an expense automatically.
- **Settings:** EUR, electricity €0.17/kWh, printer 150 W, depreciation
  €0.25/printing hour, processing 15 minutes/unit, labor €10/hour, packaging €0,
  and platform fee 0%. Blank product overrides inherit the current global value;
  an explicit zero remains an override.
- **Cost calculator:** saved versus simulated breakdowns of filament, energy,
  depreciation, labor, additional materials, packaging, fees, profit and hourly
  profitability. Simulations remain unsaved unless explicitly saved.
- **Pricing calculator:** saved products or temporary production parameters;
  target unit profit, margin, printing-hour profit, or total-production-hour
  profit. Shows the exact calculation, upward rounding to a configurable increment
  (default €0.10), and the resulting actual profit and margin. Impossible targets
  produce validation errors rather than division by zero.
- **Comparison:** side-by-side current estimates with unsaved selling-price
  simulations and separate winners by profit/unit and profit/printing hour.
- **Expenses:** actual recorded expenses, date/category filters, period/category
  totals and all-time totals.
- **Sales:** quantities and historical unit-price snapshots, editable prefills,
  period/product filters, revenue totals, and current printing/electricity estimates.
- **Dashboard:** selected-period and all-time recorded revenue, expenses and net
  cash flow; category and monthly summaries, revenue/expenses chart, units sold,
  weighted average selling price, most frequently sold products, and current
  estimated printing effort and electricity.

Dashboard product filters apply to sales/estimates only: expenses remain
business-wide. Estimated manufacturing or electricity costs are **not** actual
expenses and are never deducted from cash flow. Net cash flow is not accounting
profit. Sales estimates use today's standard parameters/settings, not measured
historical print times. Changing a product's selling price does not change its
past recorded revenue.

## Calculation and data rules

Shared `CostCalculator` and `PricingCalculator` services keep formulas out of
resource classes. BCMath decimal strings retain internal precision; EUR displays
round to two decimal places. Fractional per-unit duration is calculated from
integer batch seconds, and durations are presented in readable hours/minutes.
The price-rounding operation always rounds upward. Fees are percentages (`5` means
5%), and selling-price-dependent fees are included in pricing targets.

`ProductService` validates the exact sum of filament usages against batch weight,
rejects duplicate filament entries, and saves the product and usages in one
transaction. Products referenced by sales and filaments referenced by products
cannot be deleted: deactivate products instead to preserve history. Calculated
manufacturing costs and revenue totals are derived, not redundantly stored.

## Tests and checks

The PHPUnit suite uses isolated in-memory SQLite by default; no development data
is changed. It covers the reference costs, multiple filaments,
batch conversions, inherited/zero overrides, current price changes, impossible
targets, upward increments, financial snapshots/totals, simulations, CRUD validation,
and authentication.

The supplied reference example has an arithmetic discrepancy: its components
including €0.20 additional materials total **€6.3338**, giving €8.5662 profit.
The stated €6.1338 cost and €8.7662 profit apply with **zero** additional materials.
The implementation follows the formulas, and tests cover both cases.

```sh
php artisan test --filter=CalculationTest
php artisan test --filter=FinancialSummaryTest
composer test
vendor/bin/pint --test
composer audit
npm audit
npm run build
```

To test against a **dedicated disposable MySQL database**, override the PHPUnit
connection environment (never use a business database with `RefreshDatabase`):

```sh
DB_CONNECTION=mysql DB_DATABASE=bb_3dprint_admin_test \
DB_USERNAME=bb_3dprint_admin DB_PASSWORD='your-test-database-password' composer test
```

Official references: [Laravel 13](https://laravel.com/docs/13.x) and
[Filament 5](https://filamentphp.com/docs/5.x).
