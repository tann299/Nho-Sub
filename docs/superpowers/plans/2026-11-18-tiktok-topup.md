# TikTok Topup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development
> (recommended) or superpowers:executing-plans to implement plan task-by-task.
> Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Khách NhoSub liên kết tài khoản TikTok (QR/Cookie) và mua xu TikTok qua
napxu.vn, trả bằng ví NhoSub; admin quản lý gói giá; cron đồng bộ trạng thái.

**Architecture:** Subsystem riêng `app/Services/Napxu/` (NapxuClient HTTP Bearer +
TikTokAccountService + TopupOrderService) — không đụng ProviderInterface của mảng
SMM. 3 bảng mới: tiktok_accounts, topup_packages, topup_orders. Cron `topup:sync`.

**Tech Stack:** Laravel 12, Http client có sẵn, MySQL, PHPUnit. Không dependency mới.

**Spec:** docs/superpowers/specs/2026-11-18-tiktok-topup-design.md

## Global Constraints

- Mọi thay đổi số dư qua `WalletService::apply` (khoá dòng + idempotency UNIQUE).
- Key napxu không bao giờ vào log/exception message.
- Cookie TikTok không lưu DB.
- Khách chỉ thao tác trên tài khoản TikTok của chính mình (where user_id).
- PHP 8.2+, không thêm package composer mới.
- Tiền VND BIGINT; giá đóng băng vào đơn tại thời điểm đặt.

## Review Focus

1. Đặt đơn thành công nhưng napxu lỗi giữa chừng → ví phải được hoàn; test pin:
   napxu trả 400 sau khi đã trừ ví → đơn failed + refund áp dụng đúng key.
2. Bấm đặt đơn 2 lần nhanh → chỉ 1 đơn được tạo (unique token per request).
3. Khách A truy cập/đặt trên TK của khách B → 403/404 (lọc user_id ở controller).
4. Cron chạy khi napxu chết → không hoàn tiền nhầm, đơn giữ trạng thái, ghi log.
5. Admin sửa giá gói giữa chừng → đơn cũ giữ giá đóng băng, không đổi.

---

### Task 1: Migration 3 bảng mới

**Files:**
- Create: `database/migrations/2026_11_18_000001_create_tiktok_accounts_table.php`
- Create: `database/migrations/2026_11_18_000002_create_topup_packages_table.php`
- Create: `database/migrations/2026_11_18_000003_create_topup_orders_table.php`
- Test: `tests/Feature/SchemaTopupTest.php`

**Interfaces:** Produces bảng `tiktok_accounts`, `topup_packages`, `topup_orders`
(models Task 2).

- [ ] **Step 1: Viết test schema (failing)**

```php
<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaTopupTest extends TestCase
{
    public function test_bang_topup_co_du_cot(): void
    {
        $this->assertTrue(Schema::hasTable('tiktok_accounts'));
        $this->assertTrue(Schema::hasTable('topup_packages'));
        $this->assertTrue(Schema::hasTable('topup_orders'));

        foreach (['user_id', 'napxu_account_id', 'username', 'coins'] as $col) {
            $this->assertTrue(Schema::hasColumn('tiktok_accounts', $col), "tiktok_accounts.$col");
        }
        foreach (['name', 'coins', 'cost_price', 'sell_price', 'server', 'napxu_package_id', 'is_active'] as $col) {
            $this->assertTrue(Schema::hasColumn('topup_packages', $col), "topup_packages.$col");
        }
        foreach (['user_id', 'tiktok_account_id', 'topup_package_id', 'coins', 'sell_price', 'cost_price',
                  'napxu_order_ref', 'status', 'synced_at', 'synced_count'] as $col) {
            $this->assertTrue(Schema::hasColumn('topup_orders', $col), "topup_orders.$col");
        }
    }
}
```

- [ ] **Step 2: Chạy test — phải FAIL**

Run: `php artisan test tests/Feature/SchemaTopupTest.php`
Expected: FAIL (chưa có bảng).

- [ ] **Step 3: Viết 3 migration**

`create_tiktok_accounts_table`:

```php
Schema::create('tiktok_accounts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $table->unsignedBigInteger('napxu_account_id');
    $table->string('username', 120);
    $table->string('nickname', 120)->default('');
    $table->string('avatar', 500)->default('');
    $table->unsignedBigInteger('coins')->default(0);
    $table->string('account_status', 30)->default('active');
    $table->timestamp('synced_at')->nullable();
    $table->timestamps();
    $table->unique(['user_id', 'napxu_account_id']);
});
```

`create_topup_packages_table`:

```php
Schema::create('topup_packages', function (Blueprint $table) {
    $table->id();
    $table->string('name', 120);
    $table->unsignedBigInteger('coins');
    $table->unsignedBigInteger('cost_price');   // VND napxu thu theo gói
    $table->unsignedBigInteger('sell_price');   // VND khách trả
    $table->string('server', 30)->default('server1');
    $table->unsignedBigInteger('napxu_package_id');
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

`create_topup_orders_table`:

```php
Schema::create('topup_orders', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $table->foreignId('tiktok_account_id')->constrained('tiktok_accounts')->cascadeOnDelete();
    $table->foreignId('topup_package_id')->constrained('topup_packages')->restrictOnDelete();
    $table->unsignedBigInteger('coins');
    $table->unsignedBigInteger('sell_price');
    $table->unsignedBigInteger('cost_price');
    $table->string('napxu_order_ref', 64)->nullable()->index();
    $table->string('status', 20)->default('pending');
    $table->timestamp('synced_at')->nullable();
    $table->unsignedInteger('synced_count')->default(0);
    $table->timestamps();
});
```

- [ ] **Step 4: Chạy migrate + test pass**

Run: `php artisan migrate && php artisan test tests/Feature/SchemaTopupTest.php`
Expected: PASS.

- [ ] **Step 5: Commit** `git commit -m "feat(topup): migration 3 bang tiktok_accounts, topup_packages, topup_orders"`

### Task 2: Models + napxu_package_id/coins mapping

**Files:**
- Create: `app/Models/TikTokAccount.php`, `app/Models/TopupPackage.php`, `app/Models/TopupOrder.php`
- Test: thêm vào `tests/Feature/SchemaTopupTest.php`

**Interfaces:** Produces:
- `TikTokAccount` fillable [user_id, napxu_account_id, username, nickname, avatar, coins, account_status, synced_at]
- `TopupPackage` fillable [name, coins, cost_price, sell_price, server, napxu_package_id, is_active]
- `TopupOrder` fillable như bảng; const STATUS_PENDING/PROCESSING/SUCCESS/FAILED/REFUNDED/CANCELED; helper `isFinal()`.

- [ ] **Step 1: Test tạo bản ghi qua model (thêm vào SchemaTopupTest)**

```php
public function test_models_tao_va_doc_duoc(): void
{
    $user = \App\Models\User::factory()->create();
    $acc = \App\Models\TikTokAccount::create([
        'user_id' => $user->id, 'napxu_account_id' => 777, 'username' => '@a',
    ]);
    $pkg = \App\Models\TopupPackage::create([
        'name' => 'Gói 100', 'coins' => 100, 'cost_price' => 27800,
        'sell_price' => 30000, 'server' => 'server1', 'napxu_package_id' => 5,
    ]);
    $order = \App\Models\TopupOrder::create([
        'user_id' => $user->id, 'tiktok_account_id' => $acc->id,
        'topup_package_id' => $pkg->id, 'coins' => 100,
        'sell_price' => 30000, 'cost_price' => 27800,
    ]);
    $this->assertSame('pending', $order->status);
    $this->assertTrue($order->isFinal() === false);
}
```

(Laravel `User::factory()` có sẵn trong project.)

- [ ] **Step 2: Chạy — FAIL (models chưa có)**

Run: `php artisan test tests/Feature/SchemaTopupTest.php`

- [ ] **Step 3: Viết 3 model chuẩn pattern project** (như `app/Models/Order.php`:
  `$fillable`, `casts()`, quan hệ `belongsTo`).

- [ ] **Step 4: Test pass → commit** `feat(topup): models TikTokAccount, TopupPackage, TopupOrder`

### Task 3: NapxuClient

**Files:**
- Create: `app/Services/Napxu/NapxuClient.php`
- Test: `tests/Unit/NapxuClientTest.php`

**Interfaces:** Produces:
- `__construct(string $apiKey, string $baseUrl = 'https://napxu.vn/api')`
- `get(string $path, array $query = []): array`
- `post(string $path, array $payload = []): array`
- ném `App\Exceptions\NapxuException` (tạo mới, extends RuntimeException) với
  message đã lọc — không chứa key.

- [ ] **Step 1: Test failing**

```php
<?php

namespace Tests\Unit;

use App\Exceptions\NapxuException;
use App\Services\Napxu\NapxuClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NapxuClientTest extends TestCase
{
    public function test_get_gui_bearer_va_tra_mang(): void
    {
        Http::fake(['https://napxu.vn/api/x' => Http::response(['ok' => 1])]);
        $c = new NapxuClient('KEY-SECRET-1');
        $this->assertSame(['ok' => 1], $c->get('/x'));
        Http::assertSent(fn ($r) => $r->header('Authorization')[0] === 'Bearer KEY-SECRET-1');
    }

    public function test_401_nem_exception_khong_lo_key(): void
    {
        Http::fake(['https://napxu.vn/api/x' => Http::response(['error' => 'API key sai định dạng.'], 401)]);
        $c = new NapxuClient('KEY-SECRET-1');
        try { $c->get('/x'); $this->fail('phải ném'); }
        catch (NapxuException $e) {
            $this->assertStringNotContainsString('KEY-SECRET-1', $e->getMessage());
        }
    }

    public function test_loi_nghiep_vu_lay_truong_error(): void
    {
        Http::fake(['https://napxu.vn/api/y' => Http::response(['error' => 'Gói không tồn tại.'], 400)]);
        $c = new NapxuClient('k');
        try { $c->post('/y'); $this->fail('phải ném'); }
        catch (NapxuException $e) { $this->assertSame('Gói không tồn tại.', $e->getMessage()); }
    }
}
```

- [ ] **Step 2: FAIL** → **Step 3: Implement** (pattern copy từ NapxuProvider::call,
  tách riêng class; 401 → message cố định, các mã khác đọc `error`).
- [ ] **Step 4: PASS** → **Step 5: Commit** `feat(topup): NapxuClient Bearer auth`

### Task 4: TikTokAccountService (QR + Cookie)

**Files:**
- Create: `app/Services/Napxu/TikTokAccountService.php`
- Test: `tests/Unit/TikTokAccountServiceTest.php`

**Interfaces:** Consumes NapxuClient. Produces:
- `createQrSession(): array{sessionId:string, qrImage:string, expiresAt:int}`
- `pollQrSession(string $sessionId): array{status:string, account?:array}` —
  khi napxu `done` thì upsert vào `tiktok_accounts` (theo user hiện hành) và trả
  account DB dạng mảng.
- `addByCookie(int $userId, string $cookie): TikTokAccount`
- `syncCoins(TikTokAccount $acc): void` — GET accounts search theo napxu_account_id.

- [ ] **Step 1: Test failing** — fake Http; poll `done` phải tạo dòng DB với
  napxu_account_id/username/coins đúng; `addByCookie` không lưu cookie xuống DB
  (assert `Database::getQueryLog()` không chứa chuỗi cookie, hoặc đơn giản: model
  không có cột cookie và response account phải khớp).
- [ ] **Step 2: FAIL** → **Step 3: Implement** → **Step 4: PASS**
- [ ] **Step 5: Commit** `feat(topup): TikTokAccountService QR + cookie flow`

### Task 5: TopupOrderService — đặt đơn + hoàn tiền

**Files:**
- Create: `app/Services/Napxu/TopupOrderService.php`
- Test: `tests/Unit/TopupOrderServiceTest.php`

**Interfaces:** Consumes WalletService, NapxuClient. Produces:
- `placeOrder(User $user, TikTokAccount $acc, TopupPackage $pkg): TopupOrder`
  — trừ ví (`type topup_payment`, key `topup-pay:{uniqid(24)}`) → tạo local order
  pending → gọi napxu → lưu ref + `processing`; NapxuException → ví hoàn
  (`topup_refund`, key `topup-refund:{order->id}`) + đơn `failed` (ném lại exception
  cho controller hiện lỗi).
- `syncOne(TopupOrder $order): void` — tìm napxu_order_ref trong
  `GET /topup/orders?limit=100`; `success` → success; `failed` → hoàn ví + failed;
  `refund` → hoàn ví + refunded; giữ nguyên nếu không thấy.
- `cancelPending(TopupOrder $order): bool` — chỉ khi chưa có napxu ref: hoàn ví
  + canceled. Có ref → gọi napxu cancel, thành công thì hoàn ví + canceled.

- [ ] **Step 1: Test failing** — các case:
  1) Happy path: ví trừ đúng sell_price, order có ref `TU-...`, status processing.
  2) Napxu 400 → ví được hoàn nguyên (balance về số cũ), order failed.
  3) syncOne với napxu `failed` → order failed + ví hoàn.
  4) cancelPending chưa có ref → canceled + hoàn ví.
- [ ] **Step 2: FAIL** → **Step 3: Implement** (WalletService::apply cho cả trừ
  và hoàn; hoàn khi napxu lỗi chạy trong catch, idempotency từ order id).
- [ ] **Step 4: PASS** → **Step 5: Commit** `feat(topup): dat don nap xu + hoan tien napxu loi`

### Task 6: Cron topup:sync

**Files:**
- Create: `app/Console/Commands/SyncTopupOrdersCommand.php`
- Modify: `routes/console.php` (thêm lịch 5 phút như orders:sync)
- Test: `tests/Feature/TopupSyncCommandTest.php`

**Interfaces:** Consumes TopupOrderService::syncOne. Produces command
`topup:sync {--limit=100}`.

- [ ] **Step 1: Test failing** — tạo 2 order processing (1 napxu success, 1 failed),
  fake Http; chạy artisan command; assert trạng thái + refund đúng; napxu chết
  (Http::fake trả ConnectionException) → không order nào đổi trạng thái.
- [ ] **Step 2: FAIL** → **Step 3: Implement** (pattern copy SyncOrdersCommand)
- [ ] **Step 4: PASS** → **Step 5: Commit** `feat(topup): cron topup:sync 5 phut`

### Task 7: Routes + controller khách

**Files:**
- Create: `app/Http/Controllers/TikTokAccountController.php`,
  `app/Http/Controllers/TopupController.php`
- Create requests: `app/Http/Requests/StoreTikTokCookieRequest.php`,
  `app/Http/Requests/StoreTopupOrderRequest.php`
- Modify: `routes/web.php`
- Views: `resources/views/panel/tiktok-accounts.blade.php`,
  `resources/views/panel/topup.blade.php`, `resources/views/panel/topup-orders.blade.php`
- Test: `tests/Feature/TikTokTopupPagesTest.php`

**Interfaces:** Routes (middleware auth):
- `GET /tiktok-accounts` danh sách; `POST /tiktok-accounts/qr` (returns JSON QR
  session); `GET /tiktok-accounts/qr/{sessionId}` (JSON poll); `POST /tiktok-accounts/cookie`;
  `POST /tiktok-accounts/{acc}/refresh`; `POST /tiktok-accounts/{acc}/delete`
  (chỉ bỏ khỏi DB local, không gọi napxu).
- `GET /topup` form đặt đơn (TK + gói active); `POST /topup` store → redirect với
  thông báo; `GET /topup/orders` lịch sử.

- [ ] **Step 1: Test failing** — guest redirect login; auth user thấy trang; đặt
  đơn hợp lệ trừ ví + tạo order; đặt trên TK người khác → 403; validate thiếu
  package → lỗi.
- [ ] **Step 2: FAIL** → **Step 3: Implement** (FormRequest validate mọi input;
  JS QR modal trong view — fetch QR → hiển thị base64 → poll 3s/lần → done thì
  reload danh sách).
- [ ] **Step 4: PASS** → **Step 5: Commit** `feat(topup): trang khach tiktok-accounts + topup`

### Task 8: Admin — packages CRUD + trang đơn nạp xu

**Files:**
- Create: `app/Http/Controllers/Admin/AdminTopupPackageController.php`,
  `app/Http/Controllers/Admin/AdminTopupOrderController.php`
- Modify: `routes/web.php` (group admin), menu layout admin
- Views: `resources/views/admin/topup-packages.blade.php`, `resources/views/admin/topup-orders.blade.php`
- Test: `tests/Feature/AdminTopupTest.php`

**Interfaces:** Routes admin:
- `GET /admin/topup-packages`, `POST /admin/topup-packages` (create),
  `POST /admin/topup-packages/{pkg}` (update), `POST /admin/topup-packages/{pkg}/toggle`
- `GET /admin/topup-orders` (filter status), `POST /admin/topup-orders/{order}/refund`
  (hoàn tay khi stuck — WalletService cap = sell_price - đã hoàn).

- [ ] **Step 1: Test failing** — admin CRUD package; customer 403; refund tay trừ
  refunded_amount logic: chỉ hoàn phần chưa hoàn (dùng synced_count không cần —
  giữ đơn giản: hoàn 1 lần duy nhất bằng idempotency `topup-refund-manual:{id}`,
  đơn → refunded).
- [ ] **Step 2: FAIL** → **Step 3: Implement** (pattern AdminServiceController)
- [ ] **Step 4: PASS** → **Step 5: Commit** `feat(topup): admin packages + orders`

### Task 9: Menu + dashboard + xác minh cuối

**Files:**
- Modify: `resources/views/layouts/panel.blade.php` (menu khách: TikTok của tôi, Nạp xu TikTok)
- Modify: `resources/views/layouts/admin.blade.php` hoặc tương đương (menu admin)
- Modify: `app/Http/Controllers/Admin/AdminDashboardController.php` (đếm topup stuck)
- Test: chạy full suite.

- [ ] **Step 1: Thêm menu + đếm stuck** (pattern notifications menu).
- [ ] **Step 2: `php artisan test` full — 0 fail**
- [ ] **Step 3: Chạy thử tay** `php artisan serve` → đặt đơn demo (cần napxu key
  có số dư; nếu napxu lỗi thì đơn failed + hoàn ví — đúng thiết kế).
- [ ] **Step 4: Commit** `feat(topup): menu + dashboard stuck count`
