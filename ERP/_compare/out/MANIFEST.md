# UltimatePOS Customization Manifest
Stock v6.9 vs LIVE site — exact file comparison.
| | Count |
|---|---|
| Files you ADDED (custom, upgrade-safe) | 21 |
| Core files you MODIFIED (overwritten by upgrade) | 28 |
| Files MISSING vs stock | 0 |

---

## MODIFIED core files (most changed first)

| Changed lines | File |
|---|---|
| 242 | `resources/views/sale_pos/receipts/classic.blade.php` |
| 149 | `app/Http/Controllers/SellPosController.php` |
| 141 | `resources/views/contact/edit.blade.php` |
| 113 | `resources/views/contact/create.blade.php` |
| 65 | `app/Utils/NotificationUtil.php` |
| 62 | `app/Http/Controllers/TransactionPaymentController.php` |
| 59 | `resources/views/sell/index.blade.php` |
| 41 | `app/Http/Controllers/SellController.php` |
| 36 | `app/Utils/Util.php` |
| 27 | `app/Utils/TransactionUtil.php` |
| 11 | `Modules/Repair/Resources/views/job_sheet/print_pdf.blade.php` |
| 10 | `resources/views/sell/create.blade.php` |
| 8 | `routes/api.php` |
| 7 | `.htaccess` |
| 7 | `Modules/Repair/Resources/views/job_sheet/show.blade.php` |
| 7 | `routes/web.php` |
| 6 | `Modules/Repair/Utils/RepairUtil.php` |
| 6 | `resources/views/expense/index.blade.php` |
| 6 | `resources/views/purchase/partials/purchase_table.blade.php` |
| 5 | `public/js/purchase.js` |
| 4 | `resources/views/report/product_sell_report.blade.php` |
| 3 | `public/js/pos.js` |
| 2 | `Modules/Repair/Routes/web.php` |
| 2 | `app/Http/Controllers/ExpenseController.php` |
| 2 | `config/constants.php` |
| 1 | `public/js/app.js` |
| 0 | `public/favicon.ico` |
| 0 | `public/img/logo-small.png` |

---

## ADDED files (yours — safe across upgrades)

- `.well-known/pki-validation/10101CF9ED7F7A3ED358BEC3F21626E4.txt`
- `Modules/Repair-Module-V3.2-UltimatePOS/Getting-Started-Repair-Module-For-UltimatePOS.pdf`
- `Modules/Repair-Module-V3.2-UltimatePOS/Repair.zip`
- `app/Console/Commands/CleanOldSms.php`
- `app/Http/Controllers/AppLookupController.php`
- `app/Http/Controllers/RepairStatusApiController.php`
- `app/Http/Controllers/SalesLookupApiController.php`
- `app/Http/Controllers/WarrantyApiController.php`
- `app/Jobs/ProcessMaintenanceSms.php`
- `app/Jobs/ProcessSms.php`
- `app/Services/MaintenanceSmsService.php`
- `bimi/smartliving-bimi.svg`
- `database/migrations/2026_02_15_000001_create_maintenance_sms_schedules_table.php`
- `public/error_log`
- `public/favicon.ico.bak`
- `public/img/logo-small.png.bak`
- `public/maintenance-sms-cron.php`
- `public/modules/repair/.gitkeep`
- `public/modules/repair/js/app.js`
- `public/modules/repair/sass/app.scss`
- `public/sms_test.php`
