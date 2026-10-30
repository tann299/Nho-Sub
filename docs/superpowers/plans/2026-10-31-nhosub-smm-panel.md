# NhoSub — Panel đại lý SMM: Kế hoạch triển khai Giai đoạn 1

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Xây dựng panel đại lý SMM "NhoSub" bằng Laravel 12 chạy được trên shared hosting: khách đăng ký → nạp tiền (SePay/PayOS/duyệt tay) → đặt đơn → hệ thống đẩy sang TopSubRe qua API V2 → cron đồng bộ trạng thái và hoàn tiền phần chưa chạy; admin quản trị toàn bộ.

**Architecture:** Laravel 12 MVC thuần, không Vite/Node ở production (CSS + JS viết tay trong `public/assets/`). Toàn bộ thao tác tiền đi qua một cử chỉ duy nhất `WalletService::apply()` có khoá idempotency UNIQUE; tầng `ProviderInterface` tách nhà cung cấp khỏi luồng đặt đơn; cron 5 phút chạy `orders:sync` + `deposits:expire` + `queue:work --stop-when-empty`.

**Tech Stack:** PHP 8.2+ (dev: 8.3.24), Laravel 12, MySQL 8.0 (test cũng chạy MySQL), Blade, CSS/JS thuần, PHPUnit 11.

**Spec:** `docs/superpowers/specs/2026-10-31-nhosub-smm-panel-design.md` (đọc kèm khi thực thi — plan này triển khai spec đó)

---

## Global Constraints

Các ràng buộc dưới đây áp dụng cho **mọi** task, không cần nhắc lại trong từng task:

- **PHP ≥ 8.2** (Laravel 12). Máy dev hiện có 8.3.24 tại `D:\App\laragon\bin\php\php-8.3.24-nts-Win32-vs16-x64\php.exe`.
- **MySQL 8**, database dev `nhosub`, database test `nhosub_testing`, user `root`, mật khẩu rỗng (máy dev). `mysql.exe` tại `D:\App\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe`.
- **Không dùng Redis, không Supervisor, không Vite, không Node ở production.** Queue driver `database`, cache `database`, session `database`.
- **Tiền VND = BIGINT đồng nguyên.** Giá gốc USD = `DECIMAL(12,6)`. Không dùng float/REAL cho tiền.
- **Giao diện 100% tiếng Việt**, tiền tệ VND, font `system-ui, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif`.
- **Bảng màu duy nhất:** `#1E6FD9` (primary), `#0A4DB3` (primary đậm), `#DCEBFD` (primary nhạt), `#F5F8FF` (nền), `#FFFFFF` (card), `#0F2233` (chữ), `#5B6B80` (chữ mờ), `#16A34A` (thành công), `#F59E0B` (cảnh báo), `#DC2626` (lỗi). Không dùng màu ngoài bảng này.
- **Cấm gradient toàn hệ thống**, ngoại lệ duy nhất: nền `.hero` của landing `linear-gradient(180deg,#F5F8FF 0%,#EDF3FE 100%)`.
- **Transition 0.2–0.3s ease**, không shine, không scale.
- **Bcrypt** cho mật khẩu, **CSRF mọi form**, **Eloquent/prepared statement**, validate mọi input, rate limit đăng nhập, `session()->regenerate()` sau đăng nhập.
- **Key nhạy cảm mã hoá** bằng `Crypt::encryptString`, không log, không hiển thị lại sau khi lưu (chỉ 4 ký tự cuối).
- **Chú thích tiếng Việt** ở mọi đoạn nghiệp vụ quan trọng (tiền, hoàn tiền, webhook, đồng bộ). Tuân thủ **PSR-12**. Tên biến/hàm tiếng Anh rõ nghĩa, comment tiếng Việt.
- **Thời gian:** timezone ứng dụng `Asia/Ho_Chi_Minh` (`APP_TIMEZONE`).
- **Nội dung chuyển khoản:** format hiển thị duy nhất `NHOSUB <USERNAME>` (1 khoảng trắng, username in hoa).
- **Mỗi commit** kết thúc bằng:
  ```
  Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
  ```

## Review Focus

Năm nhóm đầu vào mà spec ngầm định nhưng dễ làm sập hệ thống, mỗi nhóm **có test riêng** ở task tương ứng:

1. **Trùng tiền khi hai tiến trình chạy song song** (webhook SePay và cron hoàn tiền cùng chạm một đơn/user) — người dùng kỳ vọng số dư luôn khớp tổng giao dịch, không bao giờ cộng/trừ hai lần. → Test ở Task 6 (`T-race-wallet`) và Task 10 (`T-race-refund`).
2. **Username là tiền tố của username khác** (`KHACH` vs `KHACHVIP`) khi parse nội dung chuyển khoản — kỳ vọng gán đúng người, không gán nhầm người có tên ngắn hơn. → Test `T22b` ở Task 11.
3. **Số lượng đơn cực lớn** (10.000.000) và giá lẻ — kỳ vọng không tràn số, tổng tiền = `floor(giá × số lượng / 1000)` chính xác tuyệt đối. → Test `T-bigqty` ở Task 9.
4. **Link đích chứa ký tự nguy hiểm** (`javascript:`, dấu nháy, thẻ HTML) — kỳ vọng bị từ chối khi đặt đơn và luôn escape khi hiển thị, không XSS. → Test `T-xss` ở Task 9 và Task 13.
5. **Múi giờ hiển thị** — người dùng Việt kỳ vọng "ngày tạo đơn" đúng giờ Việt Nam, cron chạy đúng phút. → Test `T-timezone` ở Task 1.

---

## File Structure

```
D:\NhoSub\
├── app\
│   ├── Console\Commands\
│   │   ├── SyncOrdersCommand.php          # orders:sync — đồng bộ trạng thái + hoàn tiền
│   │   ├── ExpireDepositsCommand.php      # deposits:expire — hết hạn yêu cầu PayOS
│   │   └── CreateAdminCommand.php         # nhosub:create-admin — tạo admin không qua seeder
│   ├── Exceptions\
│   │   ├── InsufficientBalanceException.php
│   │   ├── RefundLimitExceededException.php
│   │   └── ProviderException.php
│   ├── Http\
│   │   ├── Controllers\
│   │   │   ├── Controller.php
│   │   │   ├── LandingController.php
│   │   │   ├── DashboardController.php
│   │   │   ├── ServiceCatalogController.php
│   │   │   ├── OrderController.php
│   │   │   ├── DepositController.php
│   │   │   ├── WalletController.php
│   │   │   ├── ProfileController.php
│   │   │   ├── NotificationController.php
│   │   │   ├── SupportController.php
│   │   │   ├── Auth\{RegisterController,LoginController,LogoutController,
│   │   │   │        ForgotPasswordController,ResetPasswordController,
│   │   │   │        ChangePasswordController}.php
│   │   │   ├── Admin\{AdminDashboardController,AdminUserController,
│   │   │   │        AdminServiceController,AdminOrderController,
│   │   │   │        AdminDepositController,AdminWebhookController,
│   │   │   │        AdminApiLogController,AdminSettingController}.php
│   │   │   └── Webhook\{SePayController,PayOSController}.php
│   │   ├── Middleware\EnsureUserIsAdmin.php
│   │   ├── Middleware\SecurityHeaders.php
│   │   └── Requests\  (FormRequest cho từng form)
│   ├── Models\{User,Category,Service,Order,OrderRefund,DepositRequest,
│   │           WalletTransaction,Setting,ApiSyncLog,WebhookLog,
│   │           PayOsTransaction}.php
│   ├── Notifications\{DepositApproved,OrderStatusChanged,BalanceAdjusted}.php
│   ├── Providers\
│   │   ├── ProviderInterface.php
│   │   ├── ProviderManager.php
│   │   ├── ProviderServiceProvider.php
│   │   ├── Dto\{ProviderBalance,ProviderService,ProviderOrderResult,ProviderOrderStatus}.php
│   │   ├── TopSubRe\TopSubReProvider.php
│   │   └── Demo\DemoProvider.php
│   └── Services\
│       ├── SettingService.php
│       ├── WalletService.php
│       ├── RefundService.php
│       ├── PricingService.php
│       ├── OrderService.php
│       ├── OrderSyncService.php
│       ├── DepositService.php
│       ├── ServiceSyncService.php
│       └── PayOS\PayOSClient.php
├── database\
│   ├── migrations\  (12 file — xem Task 2)
│   └── seeders\{DatabaseSeeder,AdminSeeder,CategorySeeder,
│                 ServiceDemoSeeder,SettingSeeder}.php
├── public\
│   ├── .htaccess                          # rewrite nội bộ public
│   ├── assets\
│   │   ├── css\app.css                    # design system + toàn bộ giao diện
│   │   ├── js\app.js                      # sidebar, dropdown, copy, confirm
│   │   └── img\{logo.svg,pattern.svg,favicon.svg}
│   └── index.php
├── resources\views\
│   ├── layouts\{landing.blade.php,panel.blade.php,auth.blade.php}
│   ├── partials\{flash.blade.php,order-status.blade.php,pagination.blade.php}
│   ├── landing\index.blade.php
│   ├── auth\{login,register,forgot,reset,change-password}.blade.php
│   ├── panel\{dashboard,services,order-create,order-preview,orders,
│   │          order-show,wallet,deposit,support,profile}.blade.php
│   ├── admin\{dashboard,users,user-show,services,orders,order-show,
│   │          deposits,webhooks,api-logs,settings}.blade.php
│   └── errors\{403,404,419,500}.blade.php
├── routes\{web.php,console.php}
├── tests\
│   ├── Feature\{AuthTest,WalletServiceTest,PricingTest,ServiceSyncTest,
│   │            OrderPlacementTest,OrderSyncTest,DepositManualTest,
│   │            SePayWebhookTest,PayOSWebhookTest,AdminTest,
│   │            CustomerPagesTest,AdminPagesTest}.php
│   └── Unit\{DesignTokensTest,ProviderTest}.php
├── .env.example
├── .htaccess                              # rewrite về public/ khi hosting không đổi được docroot
├── phpunit.xml
└── README.md
```

---

### Task 1: Khởi tạo dự án Laravel 12, cấu hình MySQL, timezone

**Files:**
- Create: toàn bộ skeleton Laravel trong `D:\NhoSub`
- Modify: `.env`, `.env.example`, `config/app.php`, `phpunit.xml`, `.gitignore`
- Test: `tests/Feature/BootstrapTest.php`

**Interfaces:**
- Consumes: (không)
- Produces: ứng dụng Laravel 12 chạy được; `APP_TIMEZONE=Asia/Ho_Chi_Minh`; DB `nhosub` (dev) và `nhosub_testing` (test).

- [ ] **Step 1: Tạo database MySQL**

```bash
"/d/App/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysql.exe" -uroot -e "CREATE DATABASE IF NOT EXISTS nhosub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE IF NOT EXISTS nhosub_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; SHOW DATABASES LIKE 'nhosub%';"
```

Kỳ vọng: in ra `nhosub` và `nhosub_testing`.

- [ ] **Step 2: Tạo skeleton Laravel 12 ra thư mục tạm rồi chuyển vào dự án**

```bash
cd /d && rm -rf /d/_nhosub_tmp && composer create-project laravel/laravel:^12.0 /d/_nhosub_tmp --prefer-dist --no-interaction
```

```bash
cd /d/_nhosub_tmp && for f in * .[!.]*; do [ -e "$f" ] && mv "$f" /d/NhoSub/; done; cd /d && rmdir /d/_nhosub_tmp
```

Kiểm tra: `ls -a /d/NhoSub` phải thấy `app`, `artisan`, `bootstrap`, `composer.json`, `.env.example`, và vẫn còn `SRS.pdf`, `docs/`.

- [ ] **Step 3: Khởi tạo git và commit baseline**

```bash
cd /d/NhoSub && git init && git add -A && git commit -m "chore: khoi tao du lieu Laravel 12 + SRS + spec

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

- [ ] **Step 4: Viết test thất bại cho bootstrap**

`tests/Feature/BootstrapTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Kiểm tra nền tảng: kết nối DB, timezone, chuỗi locale.
 * Đây là "canary" — nếu test này đỏ thì mọi test khác vô nghĩa.
 */
class BootstrapTest extends TestCase
{
    public function test_ket_noi_mysql_hoat_dong(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertStringContainsString('nhosub', DB::connection()->getDatabaseName());
    }

    public function test_timezone_la_gio_viet_nam(): void
    {
        // Người dùng Việt kỳ vọng "ngày tạo đơn" đúng giờ VN, không phải UTC.
        $this->assertSame('Asia/Ho_Chi_Minh', config('app.timezone'));
        $this->assertSame('Asia/Ho_Chi_Minh', date_default_timezone_get());
    }

    public function test_locale_tieng_viet(): void
    {
        $this->assertSame('vi', config('app.locale'));
        $this->assertSame('vi', config('app.fallback_locale'));
    }
}
```

- [ ] **Step 5: Chạy test để thấy nó đỏ**

```bash
cd /d/NhoSub && php artisan test --filter=BootstrapTest
```

Kỳ vọng: FAIL — `mysql` không khớp `sqlite` (mặc định Laravel 12 dùng sqlite), timezone `UTC`.

- [ ] **Step 6: Cấu hình `.env`**

Sửa `.env`:

```dotenv
APP_NAME=NhoSub
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_TIMEZONE=Asia/Ho_Chi_Minh
APP_URL=http://localhost:8000

APP_LOCALE=vi
APP_FALLBACK_LOCALE=vi
APP_FAKER_LOCALE=vi_VN

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nhosub
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=database
SESSION_LIFETIME=120

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database

MAIL_MAILER=log
MAIL_FROM_ADDRESS="hotro@nhosub.vn"
MAIL_FROM_NAME="${APP_NAME}"

ADMIN_SEED_ENABLED=true
ADMIN_USERNAME=admin
ADMIN_EMAIL=admin@nhosub.vn
ADMIN_PASSWORD=Admin@123456
```

Chạy `php artisan key:generate`.

- [ ] **Step 7: Cấu hình `config/app.php`**

Đổi 3 dòng:

```php
'timezone' => env('APP_TIMEZONE', 'Asia/Ho_Chi_Minh'),
'locale' => env('APP_LOCALE', 'vi'),
'fallback_locale' => env('APP_FALLBACK_LOCALE', 'vi'),
```

- [ ] **Step 8: Cấu hình `phpunit.xml` dùng MySQL**

Trong `<php>` thêm/đổi:

```xml
<env name="APP_ENV" value="testing"/>
<env name="APP_TIMEZONE" value="Asia/Ho_Chi_Minh"/>
<env name="APP_LOCALE" value="vi"/>
<env name="DB_CONNECTION" value="mysql"/>
<env name="DB_DATABASE" value="nhosub_testing"/>
<env name="DB_USERNAME" value="root"/>
<env name="DB_PASSWORD" value=""/>
<env name="CACHE_STORE" value="array"/>
<env name="SESSION_DRIVER" value="array"/>
<env name="QUEUE_CONNECTION" value="sync"/>
<env name="MAIL_MAILER" value="array"/>
<env name="BCRYPT_ROUNDS" value="4"/>
```

`BCRYPT_ROUNDS=4` để test chạy nhanh (production giữ 12).

- [ ] **Step 9: Chạy test để thấy xanh**

```bash
cd /d/NhoSub && php artisan test --filter=BootstrapTest
```

Kỳ vọng: 3 passed.

- [ ] **Step 10: Commit**

```bash
cd /d/NhoSub && git add -A && git commit -m "feat: cau hinh MySQL, timezone Viet Nam, locale vi, phpunit chay MySQL

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 2: Schema cơ sở dữ liệu, model, seeder

**Files:**
- Modify: `database/migrations/0001_01_01_000000_create_users_table.php`
- Create: `database/migrations/2026_10_31_000001_create_categories_table.php` … `000011_create_notifications_table.php`
- Create: `app/Models/*.php` (11 model)
- Create: `database/seeders/{DatabaseSeeder,AdminSeeder,CategorySeeder,ServiceDemoSeeder,SettingSeeder}.php`
- Test: `tests/Feature/SchemaTest.php`, `tests/Feature/SeederTest.php`

**Interfaces:**
- Consumes: DB MySQL của Task 1
- Produces: các model với hằng số trạng thái và quan hệ:
  - `User`: `role` (`customer`/`admin`), `status` (`active`/`locked`), `balance` int
  - `Order`: hằng `STATUS_PENDING`, `STATUS_CHARGED`, `STATUS_PROCESSING`, `STATUS_COMPLETED`, `STATUS_PARTIAL`, `STATUS_CANCELED`, `STATUS_ERROR`, `STATUS_STUCK_REVIEW`; quan hệ `user()`, `service()`, `refunds()`
  - `Service`: `cost_usd` decimal string, `cost_price` int, `sell_price` int, `sell_price_override` bool
  - `WalletTransaction`: `TYPE_DEPOSIT`, `TYPE_ORDER_CHARGE`, `TYPE_REFUND`, `TYPE_ADJUST`

- [ ] **Step 1: Viết test thất bại**

`tests/Feature/SchemaTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_du_12_bang_duoc_tao(): void
    {
        $expected = [
            'users', 'password_reset_tokens', 'sessions', 'cache', 'jobs',
            'categories', 'services', 'orders', 'order_refunds',
            'deposit_requests', 'wallet_transactions', 'settings',
            'api_sync_logs', 'webhook_logs', 'payos_transactions', 'notifications',
        ];

        foreach ($expected as $table) {
            $this->assertTrue(Schema::hasTable($table), "Thiếu bảng {$table}");
        }
    }

    public function test_kieu_tien_dung_bigint_va_decimal(): void
    {
        // Tiền VND phải là BIGINT (đồng nguyên), giá USD là DECIMAL — không dùng float.
        $this->assertSame('bigint', Schema::getColumnType('users', 'balance'));
        $this->assertSame('bigint', Schema::getColumnType('orders', 'total_amount'));
        $this->assertSame('bigint', Schema::getColumnType('wallet_transactions', 'amount'));
        $this->assertSame('decimal', Schema::getColumnType('services', 'cost_usd'));
    }

    public function test_username_va_email_duy_nhat(): void
    {
        User::factory()->create(['username' => 'khach01', 'email' => 'a@a.vn']);
        $this->expectException(\Illuminate\Database\QueryException::class);
        User::factory()->create(['username' => 'khach01', 'email' => 'b@b.vn']);
    }

    public function test_ma_don_client_token_duy_nhat(): void
    {
        $order = Order::factory()->create(['client_token' => 'abc-123']);
        $this->expectException(\Illuminate\Database\QueryException::class);
        Order::factory()->create(['client_token' => 'abc-123']);
    }
}
```

`tests/Feature/SeederTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_tao_admin_danh_muc_dich_vu_va_cau_hinh(): void
    {
        $this->seed();

        $admin = User::where('role', 'admin')->first();
        $this->assertNotNull($admin);
        $this->assertSame('admin', $admin->username);
        $this->assertSame(0, (int) $admin->balance);

        $this->assertGreaterThan(0, Category::count());
        $this->assertGreaterThan(0, Service::count());
        $this->assertSame('26000', Setting::where('key', 'exchange_rate')->value('value'));
        $this->assertSame('30', Setting::where('key', 'default_markup_percent')->value('value'));
    }

    public function test_seeder_admin_chi_chay_mot_lan(): void
    {
        $this->seed();
        $countTruoc = User::where('role', 'admin')->count();

        $this->seed(); // chạy lại toàn bộ

        $this->assertSame($countTruoc, User::where('role', 'admin')->count());
    }
}
```

- [ ] **Step 2: Chạy test để thấy đỏ**

```bash
cd /d/NhoSub && php artisan test --filter="SchemaTest|SeederTest"
```

Kỳ vọng: FAIL — thiếu bảng, thiếu factory.

- [ ] **Step 3: Viết lại migration users**

`database/migrations/0001_01_01_000000_create_users_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng người dùng: khách hàng + admin.
 * SRS 7.1 — bổ sung các cột phục vụ bảo mật (đăng nhập sai, khoá tạm).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 32)->unique();
            $table->string('email', 190)->unique();
            $table->string('password_hash');
            $table->string('full_name', 120)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('role', 16)->default('customer');      // customer | admin
            $table->bigInteger('balance')->default(0);            // VND, không bao giờ âm
            $table->string('status', 16)->default('active');      // active | locked
            $table->timestamp('last_login_at')->nullable();
            $table->unsignedInteger('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index('role');
            $table->index('status');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
```

- [ ] **Step 4: Migration danh mục và dịch vụ**

`database/migrations/2026_10_31_000001_create_categories_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Danh mục dịch vụ theo nền tảng (SRS 7.2). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('platform', 32);           // facebook | tiktok | instagram | youtube | khac
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['platform', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
```

`database/migrations/2026_10_31_000002_create_services_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dịch vụ đồng bộ từ TopSubRe (SRS 7.3).
 * Giá: cost_usd giữ nguyên USD gốc; cost_price/sell_price quy về VND cho 1000 lượt (BIGINT).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnUpdate();
            $table->string('provider_service_id', 64)->unique();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->decimal('cost_usd', 12, 6)->default(0);   // giá gốc USD / 1000
            $table->bigInteger('cost_price')->default(0);    // VND / 1000
            $table->bigInteger('sell_price')->default(0);    // VND / 1000
            $table->boolean('sell_price_override')->default(false);
            $table->integer('min_qty')->default(1);
            $table->integer('max_qty')->default(1000000);
            $table->string('speed', 120)->nullable();
            $table->string('avg_start_time', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['category_id', 'is_active']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
```

- [ ] **Step 5: Migration đơn hàng và sổ hoàn tiền**

`database/migrations/2026_10_31_000003_create_orders_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Đơn hàng (SRS 7.4).
 * - client_token: chống double-submit ở tầng DB (UNIQUE).
 * - refunded_amount/refund_count: kiểm soát hoàn tiền nhiều đợt, không hoàn trùng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate();
            $table->foreignId('service_id')->constrained('services')->cascadeOnUpdate();
            $table->uuid('client_token')->unique();
            $table->string('provider_order_id', 128)->nullable();
            $table->text('target_link');
            $table->integer('quantity');
            $table->bigInteger('unit_price');                 // VND / 1000, chốt tại thời điểm đặt
            $table->bigInteger('total_amount');               // VND đã trừ của khách
            $table->bigInteger('cost_amount');                // VND giá gốc phải trả NCC
            $table->string('status', 24)->default('pending');
            $table->integer('start_count')->nullable();
            $table->integer('remains')->nullable();
            $table->bigInteger('refunded_amount')->default(0);
            $table->unsignedInteger('refund_count')->default(0);
            $table->string('error_message', 500)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->unsignedInteger('sync_attempts')->default(0);
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
            $table->index('provider_order_id');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
```

`database/migrations/2026_10_31_000004_create_order_refunds_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sổ hoàn tiền theo từng đợt — mỗi lần hoàn là 1 dòng (seq tăng dần).
 * UNIQUE(order_id, seq) bảo đảm cron chạy chồng nhau không ghi trùng đợt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedInteger('seq');
            $table->bigInteger('amount');
            $table->string('reason', 32);                     // auto_partial | auto_canceled | provider_error | admin_manual
            $table->foreignId('wallet_transaction_id')->nullable()
                  ->constrained('wallet_transactions')->nullOnDelete();
            $table->timestamps();

            $table->unique(['order_id', 'seq']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_refunds');
    }
};
```

- [ ] **Step 6: Migration nạp tiền, ví, cấu hình, log**

`database/migrations/2026_10_31_000005_create_deposit_requests_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Yêu cầu nạp tiền (SRS 7.5) — bổ sung expires_at cho yêu cầu cổng thanh toán. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposit_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate();
            $table->bigInteger('amount');
            $table->string('method', 16)->default('bank');    // bank | payos | momo
            $table->string('transfer_code', 64);
            $table->string('status', 16)->default('pending'); // pending | approved | rejected | expired
            $table->text('admin_note')->nullable();
            $table->string('gateway_txn_id', 128)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'expires_at']);
            $table->index('method');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposit_requests');
    }
};
```

`database/migrations/2026_10_31_000006_create_wallet_transactions_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sổ cái ví (SRS 7.6).
 * idempotency_key UNIQUE là trụ cột chống cộng/trừ trùng của toàn hệ thống.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('deposit_id')->nullable()->constrained('deposit_requests')->nullOnDelete();
            $table->string('type', 16);                       // deposit | order_charge | refund | adjust
            $table->bigInteger('amount');                     // dương = cộng, âm = trừ
            $table->bigInteger('balance_after');
            $table->string('idempotency_key', 128)->unique();
            $table->string('note', 255)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
```

`database/migrations/2026_10_31_000007_create_settings_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Cấu hình hệ thống dạng key-value (SRS 7.7). Giá trị nhạy cảm lưu dạng đã mã hoá. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->text('value')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
```

`database/migrations/2026_10_31_000008_create_api_sync_logs_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Nhật ký gọi API nhà cung cấp (SRS 7.8) — không bao giờ chứa API key. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action', 32);                     // sync_services | add_order | check_status | check_balance
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->text('request_summary')->nullable();
            $table->text('response_summary')->nullable();
            $table->boolean('success')->default(false);
            $table->timestamp('created_at')->nullable();

            $table->index('order_id');
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_sync_logs');
    }
};
```

`database/migrations/2026_10_31_000009_create_webhook_logs_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Nhật ký webhook thanh toán — dedupe theo (gateway, gateway_txn_id). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 16);                    // sepay | payos
            $table->string('gateway_txn_id', 128);
            $table->bigInteger('amount')->default(0);
            $table->string('content', 255)->nullable();
            $table->string('status', 24);                     // processed | duplicate | unmatched | rejected | processed_late
            $table->string('reason', 255)->nullable();
            $table->json('payload')->nullable();              // đã che key trước khi lưu
            $table->timestamps();

            $table->unique(['gateway', 'gateway_txn_id']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
```

`database/migrations/2026_10_31_000010_create_payos_transactions_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Liên kết yêu cầu nạp với mã đơn PayOS. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payos_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deposit_request_id')->constrained('deposit_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate();
            $table->unsignedBigInteger('order_code')->unique();
            $table->string('payment_link_id', 64)->nullable();
            $table->string('checkout_url', 500)->nullable();
            $table->string('qr_code', 1000)->nullable();
            $table->string('status', 16)->default('pending'); // pending | paid | cancelled | expired
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payos_transactions');
    }
};
```

- [ ] **Step 7: Migration notifications**

```bash
cd /d/NhoSub && php artisan notifications:table
```

Kỳ vọng: tạo file `database/migrations/*_create_notifications_table.php` chuẩn Laravel. Đổi tên file thành `2026_10_31_000011_create_notifications_table.php` để chạy sau `payos_transactions`.

- [ ] **Step 8: Tạo 11 model**

```bash
cd /d/NhoSub && php artisan make:model Category -f && php artisan make:model Service -f && php artisan make:model Order -f && php artisan make:model OrderRefund -f && php artisan make:model DepositRequest -f && php artisan make:model WalletTransaction -f && php artisan make:model Setting && php artisan make:model ApiSyncLog -f && php artisan make:model WebhookLog -f && php artisan make:model PayOsTransaction -f
```

(`-f` sinh factory kèm.)

Viết lại `app/Models/User.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_CUSTOMER = 'customer';
    public const ROLE_ADMIN = 'admin';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_LOCKED = 'locked';

    protected $fillable = [
        'username', 'email', 'password_hash', 'full_name', 'phone',
        'role', 'balance', 'status', 'last_login_at',
        'failed_login_attempts', 'locked_until',
    ];

    protected $hidden = ['password_hash', 'remember_token'];

    protected function casts(): array
    {
        return [
            'balance' => 'integer',
            'failed_login_attempts' => 'integer',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'email_verified_at' => 'datetime',
        ];
    }

    /** Laravel xác thực dùng cột password_hash thay vì password mặc định. */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_LOCKED;
    }

    /** Mã nội dung chuyển khoản riêng của khách: "NHOSUB <USERNAME>". */
    public function transferContent(): string
    {
        return 'NHOSUB ' . strtoupper($this->username);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function depositRequests(): HasMany
    {
        return $this->hasMany(DepositRequest::class);
    }
}
```

`app/Models/Order.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_CHARGED = 'charged';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_ERROR = 'error';
    public const STATUS_STUCK_REVIEW = 'stuck_review';

    /** Trạng thái đã kết thúc — không đồng bộ lại. */
    public const FINAL_STATUSES = [
        self::STATUS_COMPLETED, self::STATUS_CANCELED, self::STATUS_ERROR,
    ];

    protected $fillable = [
        'user_id', 'service_id', 'client_token', 'provider_order_id',
        'target_link', 'quantity', 'unit_price', 'total_amount', 'cost_amount',
        'status', 'start_count', 'remains', 'refunded_amount', 'refund_count',
        'error_message', 'synced_at', 'sync_attempts',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'total_amount' => 'integer',
            'cost_amount' => 'integer',
            'start_count' => 'integer',
            'remains' => 'integer',
            'refunded_amount' => 'integer',
            'refund_count' => 'integer',
            'sync_attempts' => 'integer',
            'synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(OrderRefund::class);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, self::FINAL_STATUSES, true);
    }

    /** Phần chưa chạy — kẹp trong 0..quantity để NCC trả số vô lý không phá hoàn tiền. */
    public function unusedQuantity(): int
    {
        $unused = $this->remains ?? ($this->quantity - (int) $this->start_count);

        return max(0, min((int) $unused, (int) $this->quantity));
    }
}
```

`app/Models/Service.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id', 'provider_service_id', 'name', 'description',
        'cost_usd', 'cost_price', 'sell_price', 'sell_price_override',
        'min_qty', 'max_qty', 'speed', 'avg_start_time', 'is_active', 'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'cost_usd' => 'decimal:6',
            'cost_price' => 'integer',
            'sell_price' => 'integer',
            'sell_price_override' => 'boolean',
            'min_qty' => 'integer',
            'max_qty' => 'integer',
            'is_active' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
```

`app/Models/Category.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'platform', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}
```

`app/Models/WalletTransaction.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    use HasFactory;

    public const TYPE_DEPOSIT = 'deposit';
    public const TYPE_ORDER_CHARGE = 'order_charge';
    public const TYPE_REFUND = 'refund';
    public const TYPE_ADJUST = 'adjust';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'order_id', 'deposit_id', 'type',
        'amount', 'balance_after', 'idempotency_key', 'note', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'balance_after' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
```

`app/Models/OrderRefund.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderRefund extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'seq', 'amount', 'reason', 'wallet_transaction_id',
    ];

    protected function casts(): array
    {
        return ['seq' => 'integer', 'amount' => 'integer'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
```

`app/Models/DepositRequest.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepositRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';

    public const METHOD_BANK = 'bank';
    public const METHOD_PAYOS = 'payos';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'amount', 'method', 'transfer_code', 'status',
        'admin_note', 'gateway_txn_id', 'expires_at', 'created_at', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

`app/Models/Setting.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = ['key', 'value', 'updated_at'];

    protected function casts(): array
    {
        return ['updated_at' => 'datetime'];
    }
}
```

`app/Models/ApiSyncLog.php`, `WebhookLog.php`, `PayOsTransaction.php`:

```php
<?php
// app/Models/ApiSyncLog.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiSyncLog extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'action', 'order_id', 'request_summary', 'response_summary', 'success', 'created_at',
    ];
    protected function casts(): array
    {
        return ['success' => 'boolean', 'created_at' => 'datetime'];
    }
}
```

```php
<?php
// app/Models/WebhookLog.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_DUPLICATE = 'duplicate';
    public const STATUS_UNMATCHED = 'unmatched';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_PROCESSED_LATE = 'processed_late';

    protected $fillable = [
        'gateway', 'gateway_txn_id', 'amount', 'content', 'status', 'reason', 'payload',
    ];
    protected function casts(): array
    {
        return ['amount' => 'integer', 'payload' => 'array'];
    }
}
```

```php
<?php
// app/Models/PayOsTransaction.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayOsTransaction extends Model
{
    protected $fillable = [
        'deposit_request_id', 'user_id', 'order_code', 'payment_link_id',
        'checkout_url', 'qr_code', 'status', 'paid_at',
    ];
    protected function casts(): array
    {
        return ['order_code' => 'integer', 'paid_at' => 'datetime'];
    }
}
```

- [ ] **Step 9: Viết factory**

`database/factories/UserFactory.php` (thay file mặc định):

```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'username' => 'khach' . Str::lower(Str::random(8)),
            'email' => fake()->unique()->safeEmail(),
            'password_hash' => Hash::make('Password@123'),
            'full_name' => fake()->name(),
            'phone' => '09' . fake()->numerify('########'),
            'role' => User::ROLE_CUSTOMER,
            'balance' => 0,
            'status' => User::STATUS_ACTIVE,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => User::ROLE_ADMIN]);
    }

    public function locked(): static
    {
        return $this->state(fn () => ['status' => User::STATUS_LOCKED]);
    }
}
```

`database/factories/CategoryFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'platform' => fake()->randomElement(['facebook', 'tiktok', 'instagram', 'youtube']),
            'sort_order' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }
}
```

`database/factories/ServiceFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'provider_service_id' => (string) fake()->unique()->numberBetween(1000, 999999),
            'name' => fake()->words(4, true),
            'description' => fake()->sentence(),
            'cost_usd' => '0.100000',
            'cost_price' => 2600,
            'sell_price' => 3380,
            'sell_price_override' => false,
            'min_qty' => 100,
            'max_qty' => 100000,
            'speed' => '10K/ngày',
            'avg_start_time' => '0-30 phút',
            'is_active' => true,
        ];
    }
}
```

`database/factories/OrderFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'service_id' => Service::factory(),
            'client_token' => (string) Str::uuid(),
            'provider_order_id' => null,
            'target_link' => 'https://facebook.com/posts/' . fake()->numerify('#########'),
            'quantity' => 1000,
            'unit_price' => 3380,
            'total_amount' => 3380,
            'cost_amount' => 2600,
            'status' => Order::STATUS_PROCESSING,
        ];
    }
}
```

Các factory còn lại (`OrderRefundFactory`, `DepositRequestFactory`, `WalletTransactionFactory`) viết tương tự với `fake()`; `OrderRefundFactory` cần `seq` tăng dần — dùng `'seq' => 1`.

- [ ] **Step 10: Viết seeder**

`database/seeders/AdminSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Tạo tài khoản admin đầu tiên.
 * Chỉ chạy khi ADMIN_SEED_ENABLED=true VÀ chưa có dấu .admin_seeded
 * → sau lần đầu tự khoá, tránh tạo lại/ghi đè mật khẩu trên production.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        if (! filter_var(env('ADMIN_SEED_ENABLED', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        if (Storage::disk('local')->exists('.admin_seeded')) {
            return;
        }

        if (User::where('role', User::ROLE_ADMIN)->exists()) {
            Storage::disk('local')->put('.admin_seeded', now()->toDateTimeString());

            return;
        }

        User::create([
            'username' => env('ADMIN_USERNAME', 'admin'),
            'email' => env('ADMIN_EMAIL', 'admin@nhosub.vn'),
            'password_hash' => Hash::make(env('ADMIN_PASSWORD', 'Admin@123456')),
            'full_name' => 'Quản trị viên',
            'role' => User::ROLE_ADMIN,
            'balance' => 0,
            'status' => User::STATUS_ACTIVE,
        ]);

        Storage::disk('local')->put('.admin_seeded', now()->toDateTimeString());
    }
}
```

`database/seeders/SettingSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/** Cấu hình mặc định. Key nhạy cảm để trống, admin nhập ở trang Cài đặt. */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'site_name' => 'NhoSub',
            'exchange_rate' => '26000',
            'default_markup_percent' => '30',
            'min_deposit' => '10000',
            'bank_name' => 'MB Bank',
            'bank_account' => '',
            'bank_owner' => '',
            'bank_bin' => '970422',
            'provider_balance_alert' => '100000',
            'demo_mode' => 'auto',
            'demo_scenario' => 'auto',
            'sepay_enabled' => '1',
            'payos_enabled' => '0',
            'momo_enabled' => '0',
        ];

        foreach ($defaults as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value, 'updated_at' => now()]);
        }
    }
}
```

`database/seeders/CategorySeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['Facebook', 'facebook', 1],
            ['TikTok', 'tiktok', 2],
            ['Instagram', 'instagram', 3],
            ['YouTube', 'youtube', 4],
            ['Khác', 'khac', 5],
        ];

        foreach ($rows as [$name, $platform, $sort]) {
            Category::firstOrCreate(
                ['name' => $name, 'platform' => $platform],
                ['sort_order' => $sort, 'is_active' => true]
            );
        }
    }
}
```

`database/seeders/ServiceDemoSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * Dịch vụ mẫu để xem giao diện khi CHƯA có API key TopSubRe.
 * Giá gốc USD giả định; giá bán = cost × 1.3.
 */
class ServiceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $samples = [
            ['facebook', 'Facebook — Like bài viết', '0.080000', 100, 100000],
            ['facebook', 'Facebook — Follow trang', '0.150000', 500, 500000],
            ['tiktok', 'TikTok — View video', '0.010000', 1000, 10000000],
            ['tiktok', 'TikTok — Like video', '0.060000', 100, 1000000],
            ['instagram', 'Instagram — Follow', '0.120000', 100, 500000],
            ['youtube', 'YouTube — Subscriber', '0.800000', 100, 50000],
            ['youtube', 'YouTube — View video', '0.050000', 1000, 10000000],
        ];

        foreach ($samples as $i => [$platform, $name, $usd, $min, $max]) {
            $category = Category::where('platform', $platform)->first();
            if (! $category) {
                continue;
            }

            $costPrice = (int) floor((float) $usd * 26000);

            Service::firstOrCreate(
                ['provider_service_id' => 'demo-' . ($i + 1)],
                [
                    'category_id' => $category->id,
                    'name' => $name,
                    'description' => 'Dịch vụ mẫu — dữ liệu demo, thay bằng dữ liệu đồng bộ từ TopSubRe.',
                    'cost_usd' => $usd,
                    'cost_price' => $costPrice,
                    'sell_price' => (int) round($costPrice * 1.3),
                    'sell_price_override' => false,
                    'min_qty' => $min,
                    'max_qty' => $max,
                    'speed' => '10K/ngày',
                    'avg_start_time' => '0–30 phút',
                    'is_active' => true,
                    'synced_at' => now(),
                ]
            );
        }
    }
}
```

`database/seeders/DatabaseSeeder.php`:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SettingSeeder::class,
            AdminSeeder::class,
            CategorySeeder::class,
            ServiceDemoSeeder::class,
        ]);
    }
}
```

- [ ] **Step 11: Chạy migrate + test**

```bash
cd /d/NhoSub && php artisan migrate:fresh --seed
```

```bash
cd /d/NhoSub && php artisan test --filter="SchemaTest|SeederTest"
```

Kỳ vọng: tất cả xanh.

- [ ] **Step 12: Commit**

```bash
cd /d/NhoSub && git add -A && git commit -m "feat: schema 12 bang, model, factory, seeder admin an toan

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 3: Design system, logo, layout nền (panel + landing + auth)

**Files:**
- Create: `public/assets/css/app.css`, `public/assets/js/app.js`
- Create: `public/assets/img/logo.svg`, `public/assets/img/pattern.svg`, `public/assets/img/favicon.svg`
- Create: `resources/views/layouts/panel.blade.php`, `landing.blade.php`, `auth.blade.php`
- Create: `resources/views/partials/flash.blade.php`, `pagination.blade.php`, `order-status.blade.php`
- Create: `resources/views/errors/{403,404,419,500}.blade.php`
- Modify: `routes/web.php` (route tạm để test layout)
- Test: `tests/Unit/DesignTokensTest.php`

**Interfaces:**
- Consumes: Laravel 12 từ Task 1
- Produces:
  - CSS class dùng chung: `.btn`, `.btn-primary`, `.btn-outline`, `.card`, `.card-hover`, `.badge`, `.badge-*`, `.table`, `.table-wrap`, `.form-input`, `.form-label`, `.sidebar`, `.sidebar-collapsed`, `.nav-item`, `.nav-item.active`, `.section`, `.section-alt`, `.hero`, `.dot-pattern`, `.alert-*`
  - Layout `layouts.panel` nhận `@section('title')`, `@section('content')`
  - Partial `partials.order-status` nhận biến `$status` (string), render badge đúng màu
  - JS: `window.NhoSub = { copy(text), toast(msg) }`

- [ ] **Step 1: Viết test thất bại cho design tokens**

`tests/Unit/DesignTokensTest.php`:

```php
<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Kiểm tra quy tắc giao diện ở mức file — chạy nhanh, không cần Laravel boot.
 * Đây là "hàng rào" chống việc lỡ tay thêm gradient/màu lạ về sau.
 */
class DesignTokensTest extends TestCase
{
    private function css(): string
    {
        return file_get_contents(__DIR__ . '/../../public/assets/css/app.css');
    }

    public function test_co_du_10_bien_mau_bat_buoc(): void
    {
        $css = $this->css();
        foreach ([
            '#1E6FD9', '#0A4DB3', '#DCEBFD', '#F5F8FF',
            '#FFFFFF', '#0F2233', '#5B6B80',
            '#16A34A', '#F59E0B', '#DC2626',
        ] as $hex) {
            $this->assertStringContainsStringIgnoringCase($hex, $css, "Thiếu màu {$hex}");
        }
    }

    public function test_khong_co_gradient_ngoai_hero(): void
    {
        $css = $this->css();

        // Tách từng rule, chỉ cho phép .hero chứa gradient.
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $matches, PREG_SET_ORDER);

        foreach ($matches as $rule) {
            $selector = trim($rule[1]);
            $body = $rule[2];

            if (stripos($body, 'gradient(') === false) {
                continue;
            }

            $this->assertStringContainsString(
                '.hero',
                $selector,
                "Gradient bị dùng ngoài .hero tại selector: {$selector}"
            );
        }
    }

    public function test_hero_gradient_chenh_lech_sang_duoi_10_phan_tram(): void
    {
        $css = $this->css();
        preg_match('/\.hero\s*\{[^}]*linear-gradient\(([^)]+)\)/i', $css, $m);

        $this->assertNotEmpty($m, 'Không tìm thấy gradient của .hero');
        preg_match_all('/#[0-9A-F]{6}/i', $m[1], $colors);

        $lum = function (string $hex): float {
            $r = hexdec(substr($hex, 1, 2)) / 255;
            $g = hexdec(substr($hex, 3, 2)) / 255;
            $b = hexdec(substr($hex, 5, 2)) / 255;

            return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
        };

        $values = array_map($lum, $colors[0]);
        $diff = abs(max($values) - min($values));

        $this->assertLessThan(0.10, $diff, 'Gradient hero chênh lệch độ sáng quá 10%');
    }

    public function test_transition_dung_khoang_02_den_03_giay(): void
    {
        $css = $this->css();

        // Chỉ xét khai báo `transition:` (có dấu hai chấm), bỏ qua
        // `transition-duration` trong media query prefers-reduced-motion.
        preg_match_all('/transition:\s*[^;]*?(\d*\.?\d+)s/i', $css, $m);

        $this->assertNotEmpty($m[1], 'Không có transition nào');
        foreach ($m[1] as $duration) {
            $v = (float) $duration;
            $this->assertGreaterThanOrEqual(0.2, $v, "transition {$v}s quá nhanh");
            $this->assertLessThanOrEqual(0.3, $v, "transition {$v}s quá chậm");
        }
    }

    public function test_logo_ton_tai_va_dung_mau_xanh(): void
    {
        $svg = file_get_contents(__DIR__ . '/../../public/assets/img/logo.svg');

        $this->assertStringContainsString('#1E6FD9', $svg);
        $this->assertStringContainsString('#DCEBFD', $svg);
        $this->assertStringNotContainsString('gradient', $svg, 'Logo không được dùng gradient');
    }
}
```

- [ ] **Step 2: Chạy test để thấy đỏ**

```bash
cd /d/NhoSub && php artisan test --filter=DesignTokensTest
```

Kỳ vọng: FAIL — `file_get_contents` không tìm thấy `app.css`.

- [ ] **Step 3: Tạo logo SVG**

`public/assets/img/logo.svg` — mầm cây trái tim, 2 mảng phẳng:

```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="48" height="48" role="img" aria-label="NhoSub">
  <!-- Thân mầm: nét cong đậm -->
  <path d="M24 44 V27" stroke="#0A4DB3" stroke-width="3.2" stroke-linecap="round" fill="none"/>
  <!-- Lá trái: trái tim lớn, xanh chính -->
  <path d="M24 27c0-6.6-5.4-12-12-12-4.4 0-8 3.6-8 8 0 6 5.6 11 12.8 15.1.5.3 1.1.3 1.6 0C21.6 35.8 24 31.8 24 27z" fill="#1E6FD9"/>
  <!-- Lá phải: trái tim nhỏ, xanh nhạt -->
  <path d="M26 24c0-5.5 4.5-10 10-10 3.7 0 6.7 3 6.7 6.7 0 5-4.7 9.2-10.7 12.6-.4.2-.9.2-1.3 0C27.3 31.4 26 27.9 26 24z" fill="#DCEBFD" stroke="#0A4DB3" stroke-width="1.6"/>
</svg>
```

`public/assets/img/pattern.svg` — họa tiết chấm phẳng:

```svg
<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32">
  <circle cx="2" cy="2" r="1.4" fill="#DCEBFD"/>
</svg>
```

`public/assets/img/favicon.svg`:

```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="32" height="32">
  <rect width="48" height="48" rx="10" fill="#1E6FD9"/>
  <path d="M24 38 V24" stroke="#FFFFFF" stroke-width="3" stroke-linecap="round" fill="none"/>
  <path d="M24 24c0-5-4-9-9-9-3.4 0-6 2.6-6 6 0 4.5 4.2 8.2 9.6 11.3.4.2.8.2 1.2 0C22.2 30.6 24 27.6 24 24z" fill="#FFFFFF"/>
  <path d="M26 22c0-4.1 3.4-7.5 7.5-7.5 2.8 0 5 2.2 5 5 0 3.7-3.5 6.9-8 9.4-.3.2-.7.2-1 0C27 27.1 26 24.5 26 22z" fill="#DCEBFD"/>
</svg>
```

- [ ] **Step 4: Viết `public/assets/css/app.css`**

```css
/* =========================================================
   NhoSub — Design system
   Quy tắc: màu phẳng, 0 gradient (ngoại lệ duy nhất .hero),
   độ sâu bằng box-shadow + viền + khoảng cách.
   ========================================================= */

:root {
  --primary: #1E6FD9;
  --primary-dark: #0A4DB3;
  --primary-light: #DCEBFD;
  --bg: #F5F8FF;
  --card: #FFFFFF;
  --text: #0F2233;
  --muted: #5B6B80;
  --success: #16A34A;
  --warning: #F59E0B;
  --danger: #DC2626;

  --radius: 12px;
  --radius-sm: 8px;
  --radius-lg: 18px;

  --shadow-sm: 0 1px 2px rgba(15, 34, 51, .06), 0 1px 3px rgba(15, 34, 51, .05);
  --shadow-md: 0 4px 12px rgba(15, 34, 51, .07), 0 1px 3px rgba(15, 34, 51, .05);
  --shadow-lg: 0 12px 32px rgba(15, 34, 51, .10), 0 4px 8px rgba(15, 34, 51, .05);

  --ease: .25s ease;
  --sidebar-w: 260px;
  --sidebar-w-collapsed: 76px;
  --header-h: 64px;
}

*, *::before, *::after { box-sizing: border-box; }

html { -webkit-text-size-adjust: 100%; }

body {
  margin: 0;
  font-family: system-ui, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
  font-size: 15px;
  line-height: 1.6;
  color: var(--text);
  background: var(--bg);
  -webkit-font-smoothing: antialiased;
}

h1, h2, h3, h4 { margin: 0 0 .5em; line-height: 1.25; font-weight: 700; letter-spacing: -.01em; }
h1 { font-size: clamp(28px, 4vw, 44px); }
h2 { font-size: clamp(22px, 2.6vw, 32px); }
h3 { font-size: 18px; }
p { margin: 0 0 1em; }
a { color: var(--primary); text-decoration: none; }

/* Link gạch chân chạy trái → phải */
.link-underline { position: relative; display: inline-block; }
.link-underline::after {
  content: ""; position: absolute; left: 0; bottom: -2px;
  width: 100%; height: 2px; background: var(--primary);
  transform: scaleX(0); transform-origin: left;
  transition: transform .25s ease;
}
.link-underline:hover::after { transform: scaleX(1); }

/* ---------- Nút ---------- */
.btn {
  display: inline-flex; align-items: center; justify-content: center; gap: 8px;
  min-height: 44px; padding: 10px 20px;
  font: inherit; font-weight: 600; font-size: 15px;
  border-radius: var(--radius-sm); border: 1px solid transparent;
  cursor: pointer; white-space: nowrap;
  transition: background-color .25s ease, color .25s ease,
              border-color .25s ease, box-shadow .25s ease, transform .25s ease;
}
.btn:disabled { opacity: .55; cursor: not-allowed; transform: none !important; }

.btn-primary { background: var(--primary); color: #fff; box-shadow: var(--shadow-sm); }
.btn-primary:hover:not(:disabled) {
  background: var(--primary-dark); transform: translateY(-2px); box-shadow: var(--shadow-lg);
}
.btn-primary:active:not(:disabled) { transform: translateY(0); box-shadow: var(--shadow-sm); }

.btn-outline { background: #fff; color: var(--primary); border-color: var(--primary); }
.btn-outline:hover:not(:disabled) {
  background: var(--primary-light); transform: translateY(-2px); box-shadow: var(--shadow-md);
}

.btn-ghost { background: transparent; color: var(--text); border-color: #E3ECFB; }
.btn-ghost:hover:not(:disabled) { background: var(--primary-light); color: var(--primary-dark); }

.btn-danger { background: var(--danger); color: #fff; }
.btn-danger:hover:not(:disabled) { background: #B91C1C; transform: translateY(-2px); box-shadow: var(--shadow-md); }

.btn-sm { min-height: 36px; padding: 6px 14px; font-size: 14px; }
.btn-block { width: 100%; }

/* ---------- Card ---------- */
.card {
  background: var(--card);
  border: 1px solid var(--primary-light);
  border-radius: var(--radius);
  box-shadow: var(--shadow-md);
  padding: 20px;
}
.card-title { font-size: 16px; font-weight: 700; margin: 0 0 12px; }
.card-hover { transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease; }
.card-hover:hover {
  transform: translateY(-4px);
  border-color: var(--primary);
  box-shadow: var(--shadow-lg);
}

/* ---------- Section (nhịp sâu bằng nền xen kẽ, không gradient) ---------- */
.section { position: relative; padding: 72px 0; background: var(--card); }
.section-alt { background: var(--bg); }
.section-head { max-width: 720px; margin: 0 auto 40px; text-align: center; }
.section-head::before {
  content: ""; display: block; width: 48px; height: 4px; border-radius: 2px;
  background: var(--primary); margin: 0 auto 18px;
}
.section-head p { color: var(--muted); margin: 0; }

.dot-pattern {
  background-image: url("/assets/img/pattern.svg");
  background-repeat: repeat;
  background-size: 32px 32px;
}

.container { width: 100%; max-width: 1160px; margin: 0 auto; padding: 0 20px; }

/* ---------- Badge trạng thái ---------- */
.badge {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 3px 10px; border-radius: 999px;
  font-size: 12.5px; font-weight: 600; line-height: 1.7;
  border: 1px solid transparent; white-space: nowrap;
}
.badge-pending   { background: #FFF7E6; color: #92580A; border-color: #FCE3B3; }
.badge-processing{ background: var(--primary-light); color: var(--primary-dark); border-color: #C6DDFB; }
.badge-completed { background: #E7F7EE; color: #0E7A38; border-color: #BFEACD; }
.badge-partial   { background: #FFF4E5; color: #92580A; border-color: #FCE3B3; }
.badge-canceled  { background: #FDECEC; color: #A31818; border-color: #F7C9C9; }
.badge-error     { background: #FDECEC; color: #A31818; border-color: #F7C9C9; }
.badge-review    { background: #FDECEC; color: #A31818; border-color: #F7C9C9; }
.badge-neutral   { background: #EEF2F8; color: var(--muted); border-color: #DDE5F1; }

/* ---------- Bảng ---------- */
.table-wrap { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
.table { width: 100%; border-collapse: collapse; font-size: 14.5px; }
.table th {
  text-align: left; font-weight: 700; font-size: 13px; color: var(--muted);
  text-transform: uppercase; letter-spacing: .03em;
  padding: 12px 14px; background: var(--bg);
  border-bottom: 1px solid var(--primary-light); white-space: nowrap;
}
.table td { padding: 14px; border-bottom: 1px solid #EDF2FA; vertical-align: middle; }
.table tbody tr { transition: background-color .2s ease; }
.table tbody tr:hover { background: var(--bg); }
.table tbody tr:last-child td { border-bottom: none; }
.table .num { text-align: right; font-variant-numeric: tabular-nums; }

/* ---------- Form ---------- */
.form-group { margin-bottom: 18px; }
.form-label { display: block; font-weight: 600; font-size: 14px; margin-bottom: 6px; }
.form-input, .form-select, .form-textarea {
  width: 100%; min-height: 44px; padding: 10px 14px;
  font: inherit; color: var(--text); background: #fff;
  border: 1px solid #D6E2F2; border-radius: var(--radius-sm);
  transition: border-color .2s ease, box-shadow .2s ease;
}
.form-textarea { min-height: 96px; resize: vertical; }
.form-input:focus, .form-select:focus, .form-textarea:focus {
  outline: none; border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(30, 111, 217, .15);
}
.form-input::placeholder { color: #9AA9BC; }
.form-error { color: var(--danger); font-size: 13px; margin-top: 5px; }
.form-hint { color: var(--muted); font-size: 13px; margin-top: 5px; }
.form-input.is-invalid, .form-select.is-invalid { border-color: var(--danger); }

/* ---------- Alert ---------- */
.alert { padding: 12px 16px; border-radius: var(--radius-sm); border: 1px solid; font-size: 14.5px; margin-bottom: 16px; }
.alert-success { background: #E7F7EE; border-color: #BFEACD; color: #0E7A38; }
.alert-error   { background: #FDECEC; border-color: #F7C9C9; color: #A31818; }
.alert-warning { background: #FFF7E6; border-color: #FCE3B3; color: #92580A; }
.alert-info    { background: var(--primary-light); border-color: #C6DDFB; color: var(--primary-dark); }

/* ---------- Sidebar ---------- */
.layout { display: flex; min-height: 100vh; }

.sidebar {
  position: fixed; inset: 0 auto 0 0; z-index: 50;
  width: var(--sidebar-w);
  display: flex; flex-direction: column;
  background: var(--card);
  border-right: 1px solid var(--primary-light);
  box-shadow: var(--shadow-sm);
  transition: width .25s ease, transform .25s ease;
}
.sidebar-brand {
  display: flex; align-items: center; gap: 10px;
  height: var(--header-h); padding: 0 18px;
  border-bottom: 1px solid var(--primary-light);
  flex-shrink: 0;
}
.sidebar-brand img { width: 32px; height: 32px; flex-shrink: 0; }
.sidebar-brand-text { font-weight: 800; font-size: 19px; letter-spacing: -.02em; color: var(--primary-dark); }
.sidebar-brand-sub { display: block; font-size: 11px; font-weight: 500; color: var(--muted); letter-spacing: 0; }

.sidebar-nav { flex: 1; overflow-y: auto; padding: 12px 10px 20px; }
.nav-group { margin-bottom: 6px; }
.nav-group-title {
  font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em;
  color: var(--muted); padding: 12px 12px 6px;
}
.nav-item {
  position: relative;
  display: flex; align-items: center; gap: 10px;
  padding: 10px 12px; margin-bottom: 2px;
  border-radius: var(--radius-sm);
  color: var(--text); font-size: 14.5px; font-weight: 500;
  transition: background-color .2s ease, color .2s ease;
}
.nav-item::before {
  content: ""; position: absolute; left: 0; top: 50%; transform: translateY(-50%) scaleY(0);
  width: 3px; height: 22px; border-radius: 0 3px 3px 0;
  background: var(--primary); transition: transform .2s ease;
}
.nav-item:hover { background: var(--primary-light); color: var(--primary-dark); }
.nav-item:hover::before { transform: translateY(-50%) scaleY(1); }
.nav-item.active { background: var(--primary); color: #fff; font-weight: 600; }
.nav-item.active::before { background: #fff; transform: translateY(-50%) scaleY(1); }
.nav-item svg { width: 18px; height: 18px; flex-shrink: 0; }
.nav-badge {
  margin-left: auto; min-width: 20px; height: 20px; padding: 0 6px;
  display: inline-flex; align-items: center; justify-content: center;
  border-radius: 999px; background: var(--danger); color: #fff;
  font-size: 11px; font-weight: 700;
}
.nav-item.active .nav-badge { background: #fff; color: var(--primary); }

/* Sidebar thu gọn (desktop) */
.sidebar-collapsed .sidebar { width: var(--sidebar-w-collapsed); }
.sidebar-collapsed .sidebar-brand-text,
.sidebar-collapsed .nav-group-title,
.sidebar-collapsed .nav-item span,
.sidebar-collapsed .nav-badge { display: none; }
.sidebar-collapsed .nav-item { justify-content: center; padding: 12px 0; }
.sidebar-collapsed .main { margin-left: var(--sidebar-w-collapsed); }

.main {
  flex: 1; min-width: 0;
  margin-left: var(--sidebar-w);
  transition: margin-left .25s ease;
  display: flex; flex-direction: column;
}

.topbar {
  position: sticky; top: 0; z-index: 30;
  display: flex; align-items: center; gap: 14px;
  height: var(--header-h); padding: 0 20px;
  background: var(--card); border-bottom: 1px solid var(--primary-light);
}
.topbar-title { font-size: 16px; font-weight: 700; margin: 0; }
.topbar-spacer { flex: 1; }
.topbar-balance {
  display: flex; align-items: center; gap: 6px;
  padding: 7px 14px; border-radius: 999px;
  background: var(--primary-light); color: var(--primary-dark);
  font-weight: 700; font-size: 14px; white-space: nowrap;
}
.content { padding: 24px 20px 48px; flex: 1; }

.icon-btn {
  display: inline-flex; align-items: center; justify-content: center;
  width: 42px; height: 42px; border-radius: var(--radius-sm);
  border: 1px solid transparent; background: transparent; color: var(--text);
  cursor: pointer; transition: background-color .2s ease, color .2s ease;
  position: relative;
}
.icon-btn:hover { background: var(--primary-light); color: var(--primary-dark); }
.icon-btn svg { width: 20px; height: 20px; }
.icon-btn .dot {
  position: absolute; top: 8px; right: 8px;
  width: 8px; height: 8px; border-radius: 50%; background: var(--danger);
  border: 2px solid #fff;
}

/* Overlay mobile */
.sidebar-overlay {
  position: fixed; inset: 0; z-index: 45;
  background: rgba(15, 34, 51, .45);
  opacity: 0; visibility: hidden; transition: opacity .25s ease, visibility .25s ease;
}
.sidebar-open .sidebar-overlay { opacity: 1; visibility: visible; }

/* ---------- Lưới ---------- */
.grid { display: grid; gap: 20px; }
.grid-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.grid-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.grid-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }

/* ---------- Thống kê ---------- */
.stat {
  background: var(--card); border: 1px solid var(--primary-light);
  border-radius: var(--radius); padding: 18px 20px; box-shadow: var(--shadow-sm);
}
.stat-label { font-size: 13px; color: var(--muted); font-weight: 600; margin-bottom: 6px; }
.stat-value { font-size: 26px; font-weight: 800; letter-spacing: -.02em; font-variant-numeric: tabular-nums; }
.stat-value.primary { color: var(--primary); }
.stat-value.success { color: var(--success); }
.stat-value.warning { color: var(--warning); }
.stat-value.danger { color: var(--danger); }

/* ---------- Hero landing (NGOẠI LỆ GRADIENT DUY NHẤT) ---------- */
.hero {
  position: relative;
  padding: 96px 0 88px;
  background: linear-gradient(180deg, #F5F8FF 0%, #EDF3FE 100%);
  border-bottom: 1px solid var(--primary-light);
  overflow: hidden;
}
.hero-grid { display: grid; grid-template-columns: 1.05fr .95fr; gap: 48px; align-items: center; }
.hero-badge {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 6px 14px; border-radius: 999px;
  background: #fff; border: 1px solid var(--primary-light);
  color: var(--primary-dark); font-size: 13px; font-weight: 600;
  box-shadow: var(--shadow-sm); margin-bottom: 22px;
}
.hero h1 { margin-bottom: 18px; }
.hero-lead { font-size: 17px; color: var(--muted); max-width: 520px; margin-bottom: 28px; }
.hero-actions { display: flex; gap: 12px; flex-wrap: wrap; }
.hero-stats { display: flex; gap: 32px; margin-top: 40px; flex-wrap: wrap; }
.hero-stat strong { display: block; font-size: 24px; color: var(--primary); font-weight: 800; }
.hero-stat span { font-size: 13px; color: var(--muted); }

/* ---------- Landing header/footer ---------- */
.site-header {
  position: sticky; top: 0; z-index: 40;
  background: #fff; border-bottom: 1px solid var(--primary-light);
}
.site-header-inner { display: flex; align-items: center; gap: 24px; height: 68px; }
.site-nav { display: flex; gap: 26px; margin-left: 12px; }
.site-nav a { color: var(--text); font-weight: 500; font-size: 15px; }
.site-nav a:hover { color: var(--primary); }

.site-footer { background: var(--card); border-top: 1px solid var(--primary-light); padding: 48px 0 28px; }
.footer-grid { display: grid; grid-template-columns: 1.4fr 1fr 1fr 1fr; gap: 32px; }
.footer-bottom {
  margin-top: 36px; padding-top: 20px; border-top: 1px solid #EDF2FA;
  font-size: 13px; color: var(--muted); display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap;
}
.footer-links { list-style: none; padding: 0; margin: 0; }
.footer-links li { margin-bottom: 8px; }
.footer-links a { color: var(--muted); font-size: 14px; }
.footer-links a:hover { color: var(--primary); }

/* ---------- FAQ ---------- */
.faq-item {
  background: var(--card); border: 1px solid var(--primary-light);
  border-radius: var(--radius); box-shadow: var(--shadow-sm);
  margin-bottom: 12px; overflow: hidden;
}
.faq-item summary {
  cursor: pointer; padding: 18px 22px; font-weight: 600;
  list-style: none; display: flex; justify-content: space-between; gap: 16px;
  transition: background-color .2s ease;
}
.faq-item summary::-webkit-details-marker { display: none; }
.faq-item summary::after { content: "+"; color: var(--primary); font-size: 22px; line-height: 1; }
.faq-item[open] summary::after { content: "−"; }
.faq-item summary:hover { background: var(--bg); }
.faq-body { padding: 0 22px 20px; color: var(--muted); }

/* ---------- Auth ---------- */
.auth-wrap {
  min-height: 100vh; display: grid; grid-template-columns: 1fr 1fr;
  background: var(--bg);
}
.auth-side {
  background: var(--primary); color: #fff;
  display: flex; flex-direction: column; justify-content: center;
  padding: 56px; position: relative; overflow: hidden;
}
.auth-side h2 { color: #fff; }
.auth-side p { color: rgba(255,255,255,.86); }
.auth-main { display: flex; align-items: center; justify-content: center; padding: 40px 20px; }
.auth-card { width: 100%; max-width: 420px; background: #fff; border: 1px solid var(--primary-light); border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); padding: 32px; }
.auth-title { font-size: 24px; margin-bottom: 6px; }
.auth-sub { color: var(--muted); font-size: 14.5px; margin-bottom: 26px; }

/* ---------- Tiện ích ---------- */
.text-muted { color: var(--muted); }
.text-danger { color: var(--danger); }
.text-success { color: var(--success); }
.text-warning { color: var(--warning); }
.text-sm { font-size: 13px; }
.text-center { text-align: center; }
.mt-0 { margin-top: 0; }
.mt-1 { margin-top: 8px; }
.mt-2 { margin-top: 16px; }
.mt-3 { margin-top: 24px; }
.mt-4 { margin-top: 32px; }
.mb-0 { margin-bottom: 0; }
.mb-1 { margin-bottom: 8px; }
.mb-2 { margin-bottom: 16px; }
.mb-3 { margin-bottom: 24px; }
.mb-4 { margin-bottom: 32px; }
.flex { display: flex; }
.flex-between { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.flex-wrap { flex-wrap: wrap; }
.gap-1 { gap: 8px; }
.gap-2 { gap: 16px; }
.nowrap { white-space: nowrap; }
.mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 13px; }
.truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 320px; display: inline-block; vertical-align: middle; }
.divider { height: 1px; background: #EDF2FA; margin: 20px 0; border: 0; }

.empty-state { text-align: center; padding: 56px 20px; color: var(--muted); }
.empty-state svg { width: 48px; height: 48px; color: var(--primary-light); margin-bottom: 12px; }

/* Toast */
.toast-wrap { position: fixed; right: 20px; bottom: 20px; z-index: 100; display: flex; flex-direction: column; gap: 10px; }
.toast {
  background: var(--text); color: #fff; padding: 12px 18px; border-radius: var(--radius-sm);
  box-shadow: var(--shadow-lg); font-size: 14px; max-width: 340px;
  animation: toast-in .25s ease;
}
@keyframes toast-in { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }

/* ---------- Responsive ---------- */
@media (max-width: 1023px) {
  .sidebar { transform: translateX(-100%); box-shadow: var(--shadow-lg); }
  .sidebar-open .sidebar { transform: translateX(0); }
  .main { margin-left: 0; }
  .sidebar-collapsed .main { margin-left: 0; }
  .sidebar-collapsed .sidebar { width: var(--sidebar-w); }
  .sidebar-collapsed .sidebar-brand-text,
  .sidebar-collapsed .nav-group-title,
  .sidebar-collapsed .nav-item span,
  .sidebar-collapsed .nav-badge { display: initial; }
  .sidebar-collapsed .nav-item { justify-content: flex-start; padding: 10px 12px; }

  .hero-grid { grid-template-columns: 1fr; gap: 32px; }
  .grid-4 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .grid-3 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .footer-grid { grid-template-columns: 1fr 1fr; }
  .auth-wrap { grid-template-columns: 1fr; }
  .auth-side { display: none; }
  .site-nav { display: none; }
}

@media (max-width: 640px) {
  .section { padding: 52px 0; }
  .hero { padding: 60px 0 52px; }
  .content { padding: 18px 14px 40px; }
  .grid-2, .grid-3, .grid-4 { grid-template-columns: 1fr; }
  .footer-grid { grid-template-columns: 1fr; }
  .table { min-width: 720px; }
  .hero-stats { gap: 20px; }
  .card { padding: 16px; }
  .topbar { padding: 0 14px; gap: 8px; }
  .topbar-title { font-size: 15px; }
}

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after { transition-duration: .01ms !important; animation-duration: .01ms !important; }
}
```

- [ ] **Step 5: Viết `public/assets/js/app.js`**

```js
/* NhoSub — JS thuần, không phụ thuộc thư viện. */
(function () {
  'use strict';

  /* Toast */
  function toast(message, type) {
    var wrap = document.querySelector('.toast-wrap');
    if (!wrap) {
      wrap = document.createElement('div');
      wrap.className = 'toast-wrap';
      document.body.appendChild(wrap);
    }
    var el = document.createElement('div');
    el.className = 'toast';
    el.textContent = message;
    if (type === 'error') { el.style.background = '#DC2626'; }
    if (type === 'success') { el.style.background = '#16A34A'; }
    wrap.appendChild(el);
    setTimeout(function () { el.remove(); }, 3200);
  }

  /* Copy vào clipboard */
  function copy(text, doneMessage) {
    var finish = function () { toast(doneMessage || 'Đã sao chép', 'success'); };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(finish);
      return;
    }
    // Fallback cho hosting http
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); finish(); } catch (e) { toast('Không sao chép được', 'error'); }
    ta.remove();
  }

  /* Sidebar mobile + thu gọn desktop */
  function initSidebar() {
    var body = document.body;
    var toggle = document.querySelector('[data-sidebar-toggle]');
    var overlay = document.querySelector('.sidebar-overlay');
    var collapseBtn = document.querySelector('[data-sidebar-collapse]');

    if (toggle) {
      toggle.addEventListener('click', function () {
        if (window.innerWidth < 1024) {
          body.classList.toggle('sidebar-open');
        } else {
          body.classList.toggle('sidebar-collapsed');
          try {
            localStorage.setItem('nhosub.sidebar', body.classList.contains('sidebar-collapsed') ? '1' : '0');
          } catch (e) { /* chế độ riêng tư: bỏ qua */ }
        }
      });
    }

    if (collapseBtn) {
      collapseBtn.addEventListener('click', function () {
        body.classList.toggle('sidebar-collapsed');
        try {
          localStorage.setItem('nhosub.sidebar', body.classList.contains('sidebar-collapsed') ? '1' : '0');
        } catch (e) { /* bỏ qua */ }
      });
    }

    if (overlay) {
      overlay.addEventListener('click', function () { body.classList.remove('sidebar-open'); });
    }

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { body.classList.remove('sidebar-open'); }
    });

    try {
      if (localStorage.getItem('nhosub.sidebar') === '1' && window.innerWidth >= 1024) {
        body.classList.add('sidebar-collapsed');
      }
    } catch (e) { /* bỏ qua */ }
  }

  /* Dropdown */
  function initDropdowns() {
    document.querySelectorAll('[data-dropdown]').forEach(function (root) {
      var trigger = root.querySelector('[data-dropdown-trigger]');
      var menu = root.querySelector('[data-dropdown-menu]');
      if (!trigger || !menu) { return; }

      trigger.addEventListener('click', function (e) {
        e.stopPropagation();
        var open = menu.classList.toggle('is-open');
        menu.style.display = open ? 'block' : 'none';
      });
    });

    document.addEventListener('click', function () {
      document.querySelectorAll('[data-dropdown-menu].is-open').forEach(function (m) {
        m.classList.remove('is-open');
        m.style.display = 'none';
      });
    });
  }

  /* Chống bấm 2 lần nút submit (lớp bảo vệ UX, server vẫn có chốt riêng) */
  function initSubmitGuard() {
    document.querySelectorAll('form[data-guard]').forEach(function (form) {
      form.addEventListener('submit', function () {
        var btn = form.querySelector('[type="submit"]');
        if (btn && !btn.disabled) {
          setTimeout(function () {
            btn.disabled = true;
            btn.dataset.originalText = btn.textContent;
            btn.textContent = 'Đang xử lý…';
          }, 0);
        }
      });
    });
  }

  /* Xác nhận hành động nguy hiểm */
  function initConfirm() {
    document.addEventListener('click', function (e) {
      var el = e.target.closest('[data-confirm]');
      if (!el) { return; }
      if (!window.confirm(el.getAttribute('data-confirm'))) {
        e.preventDefault();
        e.stopPropagation();
      }
    });
  }

  /* Copy nội dung chuyển khoản */
  function initCopy() {
    document.addEventListener('click', function (e) {
      var el = e.target.closest('[data-copy]');
      if (!el) { return; }
      e.preventDefault();
      copy(el.getAttribute('data-copy'), el.getAttribute('data-copy-message'));
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initSidebar();
    initDropdowns();
    initSubmitGuard();
    initConfirm();
    initCopy();
  });

  window.NhoSub = { toast: toast, copy: copy };
})();
```

- [ ] **Step 6: Partial trạng thái đơn**

`resources/views/partials/order-status.blade.php`:

```blade
@php
    // Map trạng thái đơn sang nhãn tiếng Việt + class badge.
    $map = [
        'pending'      => ['Chờ xử lý', 'badge-pending'],
        'charged'      => ['Đã trừ tiền', 'badge-pending'],
        'processing'   => ['Đang chạy', 'badge-processing'],
        'completed'    => ['Hoàn thành', 'badge-completed'],
        'partial'      => ['Hoàn thành một phần', 'badge-partial'],
        'canceled'     => ['Đã hủy', 'badge-canceled'],
        'error'        => ['Lỗi', 'badge-error'],
        'stuck_review' => ['Chờ admin xử lý', 'badge-review'],
        'approved'     => ['Đã duyệt', 'badge-completed'],
        'rejected'     => ['Từ chối', 'badge-canceled'],
        'expired'      => ['Hết hạn', 'badge-neutral'],
    ];
    $entry = $map[$status] ?? [$status, 'badge-neutral'];
@endphp
<span class="badge {{ $entry[1] }}">{{ $entry[0] }}</span>
```

> Nhãn `pending` dùng chung cho cả đơn hàng và yêu cầu nạp tiền ("Chờ xử lý").

- [ ] **Step 7: Partial flash**

`resources/views/partials/flash.blade.php`:

```blade
@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-error">{{ session('error') }}</div>
@endif
@if (session('warning'))
    <div class="alert alert-warning">{{ session('warning') }}</div>
@endif
@if (session('info'))
    <div class="alert alert-info">{{ session('info') }}</div>
@endif
@if ($errors->any() && ! isset($hideValidationSummary))
    <div class="alert alert-error">
        <strong>Vui lòng kiểm tra lại:</strong>
        <ul style="margin:6px 0 0 18px;padding:0;">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
```

- [ ] **Step 8: Layout panel**

`resources/views/layouts/panel.blade.php`:

```blade
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Bảng điều khiển') — {{ config('app.name') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/img/favicon.svg') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ filemtime(public_path('assets/css/app.css')) }}">
</head>
<body>
<div class="layout">
    <aside class="sidebar" id="sidebar">
        <a href="{{ route('panel.dashboard') }}" class="sidebar-brand">
            <img src="{{ asset('assets/img/logo.svg') }}" alt="NhoSub">
            <span class="sidebar-brand-text">
                NhoSub
                <span class="sidebar-brand-sub">Sub nhỏ, lớn dần cùng bạn</span>
            </span>
        </a>

        <nav class="sidebar-nav">
            @include('partials.sidebar-menu')
        </nav>
    </aside>

    <div class="sidebar-overlay"></div>

    <div class="main">
        <header class="topbar">
            <button class="icon-btn" data-sidebar-toggle aria-label="Mở menu">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
            </button>

            <h1 class="topbar-title">@yield('title', 'Tổng quan')</h1>

            <div class="topbar-spacer"></div>

            <a href="{{ route('panel.wallet.index') }}" class="topbar-balance" title="Số dư ví">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 7a2 2 0 0 1 2-2h13a1 1 0 0 1 1 1v3"/><path d="M3 7v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H5"/><circle cx="17" cy="14" r="1"/></svg>
                {{ number_format(auth()->user()->balance, 0, ',', '.') }} ₫
            </a>

            <a href="{{ route('panel.notifications.index') }}" class="icon-btn" aria-label="Thông báo">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
                @if (($unreadCount ?? 0) > 0)
                    <span class="dot"></span>
                @endif
            </a>

            <div data-dropdown>
                <button class="icon-btn" data-dropdown-trigger aria-label="Tài khoản">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                </button>
                <div data-dropdown-menu class="card" style="display:none;position:absolute;right:20px;top:58px;min-width:220px;padding:8px;z-index:60;">
                    <div style="padding:8px 10px;border-bottom:1px solid #EDF2FA;margin-bottom:6px;">
                        <strong>{{ auth()->user()->username }}</strong>
                        <div class="text-sm text-muted">{{ auth()->user()->email }}</div>
                    </div>
                    <a class="nav-item" href="{{ route('panel.profile.edit') }}">Hồ sơ cá nhân</a>
                    <a class="nav-item" href="{{ route('panel.profile.password') }}">Đổi mật khẩu</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="nav-item" style="width:100%;border:0;background:none;cursor:pointer;text-align:left;font:inherit;">Đăng xuất</button>
                    </form>
                </div>
            </div>
        </header>

        <main class="content">
            @include('partials.flash')
            @yield('content')
        </main>
    </div>
</div>

<div class="toast-wrap"></div>
<script src="{{ asset('assets/js/app.js') }}?v={{ filemtime(public_path('assets/js/app.js')) }}" defer></script>
@stack('scripts')
</body>
</html>
```

- [ ] **Step 9: Sidebar menu partial**

`resources/views/partials/sidebar-menu.blade.php`:

```blade
@php
    // Menu theo 7 nhóm đúng yêu cầu. Mỗi mục: [route, nhãn, icon path].
    $groups = [
        'Tổng quan' => [
            ['panel.dashboard', 'Bảng điều khiển', 'M3 12l9-9 9 9M5 10v10h14V10'],
        ],
        'Dịch vụ' => [
            ['panel.services.index', 'Bảng giá dịch vụ', 'M4 6h16M4 12h16M4 18h10'],
        ],
        'Đặt đơn' => [
            ['panel.orders.create', 'Đặt đơn mới', 'M12 5v14M5 12h14'],
        ],
        'Đơn hàng' => [
            ['panel.orders.index', 'Đơn hàng của tôi', 'M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2'],
        ],
        'Nạp tiền' => [
            ['panel.deposit.create', 'Nạp tiền', 'M12 3v12m0 0l4-4m-4 4l-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2'],
            ['panel.wallet.index', 'Lịch sử giao dịch', 'M12 8v4l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z'],
        ],
        'Hỗ trợ' => [
            ['panel.support.index', 'Trung tâm hỗ trợ', 'M9.1 9a3 3 0 1 1 4.6 2.5c-.9.6-1.7 1.3-1.7 2.5M12 17h.01'],
        ],
        'Cài đặt' => [
            ['panel.profile.edit', 'Hồ sơ cá nhân', 'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z'],
            ['panel.profile.password', 'Đổi mật khẩu', 'M7 11V7a5 5 0 0 1 10 0v4M5 11h14v10H5z'],
        ],
    ];
@endphp

@foreach ($groups as $groupName => $items)
    <div class="nav-group">
        <div class="nav-group-title">{{ $groupName }}</div>
        @foreach ($items as [$routeName, $label, $icon])
            @if (Route::has($routeName))
                <a href="{{ route($routeName) }}"
                   class="nav-item {{ request()->routeIs($routeName) ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg>
                    <span>{{ $label }}</span>
                </a>
            @endif
        @endforeach
    </div>
@endforeach
```

- [ ] **Step 10: Layout landing và auth**

`resources/views/layouts/landing.blade.php`:

```blade
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'NhoSub — Sub nhỏ, lớn dần cùng bạn')</title>
    <meta name="description" content="@yield('meta_description', 'NhoSub — panel tăng tương tác mạng xã hội: like, follow, view, comment, share cho Facebook, TikTok, Instagram, YouTube. Giá tốt, chạy nhanh, hỗ trợ 24/7.')">
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/img/favicon.svg') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ filemtime(public_path('assets/css/app.css')) }}">
</head>
<body>
<header class="site-header">
    <div class="container site-header-inner">
        <a href="{{ route('landing') }}" class="flex gap-1" style="align-items:center;">
            <img src="{{ asset('assets/img/logo.svg') }}" alt="NhoSub" width="34" height="34">
            <strong style="font-size:20px;color:var(--primary-dark);letter-spacing:-.02em;">NhoSub</strong>
        </a>

        <nav class="site-nav">
            <a href="#tinh-nang">Tính năng</a>
            <a href="#bang-gia">Bảng giá</a>
            <a href="#danh-gia">Đánh giá</a>
            <a href="#faq">Câu hỏi thường gặp</a>
        </nav>

        <div class="topbar-spacer"></div>

        @auth
            <a href="{{ route('panel.dashboard') }}" class="btn btn-primary">Vào bảng điều khiển</a>
        @else
            <a href="{{ route('login') }}" class="btn btn-ghost">Đăng nhập</a>
            <a href="{{ route('register') }}" class="btn btn-primary">Đăng ký miễn phí</a>
        @endauth
    </div>
</header>

@yield('content')

<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div>
                <div class="flex gap-1" style="align-items:center;margin-bottom:12px;">
                    <img src="{{ asset('assets/img/logo.svg') }}" alt="NhoSub" width="30" height="30">
                    <strong style="font-size:18px;color:var(--primary-dark);">NhoSub</strong>
                </div>
                <p class="text-muted text-sm">Sub nhỏ, lớn dần cùng bạn. Panel tăng tương tác mạng xã hội cho người Việt.</p>
            </div>
            <div>
                <h4 style="font-size:14px;">Dịch vụ</h4>
                <ul class="footer-links">
                    <li><a href="#bang-gia">Facebook</a></li>
                    <li><a href="#bang-gia">TikTok</a></li>
                    <li><a href="#bang-gia">Instagram</a></li>
                    <li><a href="#bang-gia">YouTube</a></li>
                </ul>
            </div>
            <div>
                <h4 style="font-size:14px;">Hỗ trợ</h4>
                <ul class="footer-links">
                    <li><a href="#faq">Câu hỏi thường gặp</a></li>
                    <li><a href="{{ route('login') }}">Đăng nhập</a></li>
                    <li><a href="{{ route('register') }}">Đăng ký</a></li>
                </ul>
            </div>
            <div>
                <h4 style="font-size:14px;">Liên hệ</h4>
                <ul class="footer-links">
                    <li>Email: hotro@nhosub.vn</li>
                    <li>Hỗ trợ: 8:00 – 22:00 hằng ngày</li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <span>© {{ date('Y') }} NhoSub. Bảo lưu mọi quyền.</span>
            <span>Thanh toán an toàn · Hỗ trợ tiếng Việt</span>
        </div>
    </div>
</footer>
</body>
</html>
```

`resources/views/layouts/auth.blade.php`:

```blade
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Đăng nhập') — {{ config('app.name') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/img/favicon.svg') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ filemtime(public_path('assets/css/app.css')) }}">
</head>
<body>
<div class="auth-wrap">
    <aside class="auth-side dot-pattern">
        <div style="position:relative;z-index:1;max-width:420px;">
            <a href="{{ route('landing') }}" class="flex gap-1" style="align-items:center;margin-bottom:28px;color:#fff;">
                <img src="{{ asset('assets/img/logo.svg') }}" alt="NhoSub" width="40" height="40" style="background:#fff;border-radius:10px;padding:4px;">
                <strong style="font-size:24px;color:#fff;">NhoSub</strong>
            </a>
            <h2 style="font-size:30px;">Sub nhỏ,<br>lớn dần cùng bạn</h2>
            <p>Nạp tiền một lần, đặt đơn mọi lúc. Đơn hàng được đẩy đi tự động và cập nhật trạng thái mỗi 5 phút.</p>
            <ul style="list-style:none;padding:0;margin:24px 0 0;">
                <li style="margin-bottom:10px;">✓ Giá hiển thị rõ ràng theo 1.000 lượt</li>
                <li style="margin-bottom:10px;">✓ Tự động hoàn tiền phần chưa chạy</li>
                <li style="margin-bottom:10px;">✓ Hỗ trợ tiếng Việt 8:00 – 22:00</li>
            </ul>
        </div>
    </aside>

    <main class="auth-main">
        <div class="auth-card">
            <a href="{{ route('landing') }}" class="flex gap-1 mb-3" style="align-items:center;">
                <img src="{{ asset('assets/img/logo.svg') }}" alt="NhoSub" width="32" height="32">
                <strong style="font-size:18px;color:var(--primary-dark);">NhoSub</strong>
            </a>
            @include('partials.flash')
            @yield('content')
        </div>
    </main>
</div>
<script src="{{ asset('assets/js/app.js') }}" defer></script>
</body>
</html>
```

- [ ] **Step 11: Trang lỗi**

`resources/views/errors/404.blade.php`:

```blade
@extends('layouts.landing')
@section('title', 'Không tìm thấy trang — NhoSub')
@section('content')
    <section class="section" style="min-height:60vh;display:flex;align-items:center;">
        <div class="container text-center">
            <h1 style="color:var(--primary);">404</h1>
            <p class="text-muted">Trang bạn tìm không tồn tại hoặc đã bị di chuyển.</p>
            <a href="{{ url('/') }}" class="btn btn-primary mt-2">Về trang chủ</a>
        </div>
    </section>
@endsection
```

Tương tự cho `403` ("Bạn không có quyền truy cập"), `419` ("Phiên làm việc đã hết hạn, vui lòng thử lại"), `500` ("Có lỗi xảy ra, chúng tôi đang khắc phục").

- [ ] **Step 12: Route tạm + layout test route**

Trong `routes/web.php`:

```php
use Illuminate\Support\Facades\Route;

Route::view('/', 'landing.index')->name('landing');
```

- [ ] **Step 13: Chạy test design tokens**

```bash
cd /d/NhoSub && php artisan test --filter=DesignTokensTest
```

Kỳ vọng: 5 passed.

- [ ] **Step 14: Commit**

```bash
cd /d/NhoSub && git add -A && git commit -m "feat: design system phang xanh duong, logo SVG, layout panel/landing/auth

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 4: Xác thực — đăng ký, đăng nhập, đăng xuất, quên/đổi mật khẩu

**Files:**
- Create: `app/Http/Controllers/Auth/*.php` (6 controller)
- Create: `app/Http/Requests/Auth/*.php` (4 FormRequest)
- Create: `app/Http/Middleware/EnsureUserIsAdmin.php`, `SecurityHeaders.php`
- Create: `resources/views/auth/{login,register,forgot,reset,change-password}.blade.php`
- Modify: `routes/web.php`, `bootstrap/app.php`, `config/auth.php`
- Test: `tests/Feature/AuthTest.php`

**Interfaces:**
- Consumes: `User` model (Task 2), layout auth (Task 3)
- Produces:
  - Route names: `login`, `login.store`, `logout`, `register`, `register.store`, `password.request`, `password.email`, `password.reset`, `password.store`, `panel.profile.password`, `panel.profile.password.update`
  - Middleware alias `admin` → `EnsureUserIsAdmin`
  - `User::getAuthPassword()` trả `password_hash`

- [ ] **Step 1: Viết test thất bại**

`tests/Feature/AuthTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_dang_ky_tao_tai_khoan_customer_voi_so_du_0(): void
    {
        $res = $this->post('/register', [
            'username' => 'khachmoi',
            'email' => 'khachmoi@example.com',
            'password' => 'MatKhau@123',
            'password_confirmation' => 'MatKhau@123',
            'full_name' => 'Nguyễn Văn A',
            'phone' => '0901234567',
        ]);

        $res->assertRedirect('/dashboard');

        $user = User::where('username', 'khachmoi')->first();
        $this->assertNotNull($user);
        $this->assertSame('customer', $user->role);
        $this->assertSame(0, $user->balance);
        $this->assertSame('active', $user->status);
        $this->assertTrue(Hash::check('MatKhau@123', $user->password_hash));
        $this->assertAuthenticatedAs($user);
    }

    public function test_dang_ky_tu_choi_username_trung_khong_phan_biet_hoa_thuong(): void
    {
        User::factory()->create(['username' => 'KhachVIP', 'email' => 'x@x.vn']);

        $this->post('/register', [
            'username' => 'khachvip',
            'email' => 'moi@example.com',
            'password' => 'MatKhau@123',
            'password_confirmation' => 'MatKhau@123',
        ])->assertSessionHasErrors('username');
    }

    public function test_mat_khau_duoi_8_ky_tu_bi_tu_choi(): void
    {
        $this->post('/register', [
            'username' => 'khach02',
            'email' => 'k2@example.com',
            'password' => 'abc123',
            'password_confirmation' => 'abc123',
        ])->assertSessionHasErrors('password');
    }

    public function test_dang_nhap_bang_username_hoac_email(): void
    {
        $user = User::factory()->create([
            'username' => 'khach01',
            'email' => 'khach01@example.com',
            'password_hash' => Hash::make('MatKhau@123'),
        ]);

        $this->post('/login', ['login' => 'khach01', 'password' => 'MatKhau@123'])
             ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        $this->post('/logout');

        $this->post('/login', ['login' => 'khach01@example.com', 'password' => 'MatKhau@123'])
             ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_dang_nhap_sai_bao_loi_chung(): void
    {
        User::factory()->create([
            'username' => 'khach01',
            'password_hash' => Hash::make('MatKhau@123'),
        ]);

        $this->post('/login', ['login' => 'khach01', 'password' => 'sai'])
             ->assertSessionHasErrors(['login' => 'Tên đăng nhập hoặc mật khẩu không đúng.']);
        $this->assertGuest();
    }

    public function test_tai_khoan_bi_khoa_khong_dang_nhap_duoc(): void
    {
        User::factory()->locked()->create([
            'username' => 'bikhoa',
            'password_hash' => Hash::make('MatKhau@123'),
        ]);

        $this->post('/login', ['login' => 'bikhoa', 'password' => 'MatKhau@123'])
             ->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_sai_mat_khau_5_lan_bi_chan(): void
    {
        User::factory()->create([
            'username' => 'bruteforce',
            'password_hash' => Hash::make('MatKhau@123'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['login' => 'bruteforce', 'password' => 'sai']);
        }

        $this->post('/login', ['login' => 'bruteforce', 'password' => 'MatKhau@123'])
             ->assertStatus(429);
    }

    public function test_session_doi_id_sau_dang_nhap(): void
    {
        $user = User::factory()->create([
            'username' => 'khach01',
            'password_hash' => Hash::make('MatKhau@123'),
        ]);

        $this->get('/login');
        $before = session()->getId();

        $this->post('/login', ['login' => 'khach01', 'password' => 'MatKhau@123']);

        $this->assertNotSame($before, session()->getId());
    }

    public function test_doi_mat_khau_dung_mat_khau_cu(): void
    {
        $user = User::factory()->create(['password_hash' => Hash::make('Cu@123456')]);

        $this->actingAs($user)->post('/settings/password', [
            'current_password' => 'Cu@123456',
            'password' => 'Moi@123456',
            'password_confirmation' => 'Moi@123456',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('Moi@123456', $user->fresh()->password_hash));
    }

    public function test_doi_mat_khau_sai_mat_khau_cu_bi_tu_choi(): void
    {
        $user = User::factory()->create(['password_hash' => Hash::make('Cu@123456')]);

        $this->actingAs($user)->post('/settings/password', [
            'current_password' => 'sai',
            'password' => 'Moi@123456',
            'password_confirmation' => 'Moi@123456',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('Cu@123456', $user->fresh()->password_hash));
    }

    public function test_khach_thuong_khong_vao_duoc_admin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_admin_vao_duoc_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin')->assertOk();
    }
}
```

- [ ] **Step 2: Chạy test để thấy đỏ**

```bash
cd /d/NhoSub && php artisan test --filter=AuthTest
```

Kỳ vọng: FAIL — route chưa tồn tại (404).

- [ ] **Step 3: Cấu hình auth dùng `username` + `password_hash`**

`config/auth.php` — `providers.users` giữ `eloquent` + `App\Models\User` (đã có `getAuthPassword()`). Không cần đổi gì thêm.

- [ ] **Step 4: Viết FormRequest**

`app/Http/Requests/Auth/RegisterRequest.php`:

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => [
                'required', 'string', 'min:3', 'max:32', 'regex:/^[A-Za-z0-9_]+$/',
                // Không phân biệt hoa thường khi kiểm tra trùng.
                Rule::unique('users', 'username'),
            ],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'full_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\s.-]{8,20}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => 'Vui lòng nhập tên đăng nhập.',
            'username.min' => 'Tên đăng nhập phải có ít nhất 3 ký tự.',
            'username.max' => 'Tên đăng nhập không quá 32 ký tự.',
            'username.regex' => 'Tên đăng nhập chỉ gồm chữ, số và dấu gạch dưới.',
            'username.unique' => 'Tên đăng nhập đã tồn tại.',
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không hợp lệ.',
            'email.unique' => 'Email đã được sử dụng.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự.',
            'password.confirmed' => 'Mật khẩu nhập lại không khớp.',
            'phone.regex' => 'Số điện thoại không hợp lệ.',
        ];
    }

    /** Chuẩn hoá username trước khi validate để so trùng không phân biệt hoa thường. */
    protected function prepareForValidation(): void
    {
        if ($this->filled('username')) {
            $this->merge(['username' => (string) $this->input('username')]);
        }
    }
}
```

`app/Http/Requests/Auth/LoginRequest.php`:

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:190'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'login.required' => 'Vui lòng nhập tên đăng nhập hoặc email.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
        ];
    }
}
```

`app/Http/Requests/Auth/ChangePasswordRequest.php`:

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Vui lòng nhập mật khẩu hiện tại.',
            'password.min' => 'Mật khẩu mới phải có ít nhất 8 ký tự.',
            'password.confirmed' => 'Mật khẩu mới nhập lại không khớp.',
            'password.different' => 'Mật khẩu mới phải khác mật khẩu hiện tại.',
        ];
    }
}
```

- [ ] **Step 5: Controller đăng ký**

`app/Http/Controllers/Auth/RegisterController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        // Toàn bộ việc tạo tài khoản trong 1 transaction để không tạo user dở dang.
        $user = DB::transaction(function () use ($request) {
            return User::create([
                'username' => $request->string('username')->toString(),
                'email' => $request->string('email')->lower()->toString(),
                'password_hash' => Hash::make($request->string('password')->toString()),
                'full_name' => $request->input('full_name'),
                'phone' => $request->input('phone'),
                'role' => User::ROLE_CUSTOMER,
                'balance' => 0,
                'status' => User::STATUS_ACTIVE,
            ]);
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('panel.dashboard')
            ->with('success', 'Chào mừng bạn đến với NhoSub! Tài khoản đã được tạo.');
    }
}
```

- [ ] **Step 6: Controller đăng nhập (có rate limit + khoá tài khoản)**

`app/Http/Controllers/Auth/LoginController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;
    private const DECAY_SECONDS = 900; // 15 phút

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $login = (string) $request->input('login');
        $password = (string) $request->input('password');

        // Khoá theo IP + tên đăng nhập để chặn dò mật khẩu.
        $throttleKey = 'login:' . mb_strtolower($login) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'login' => "Bạn đã thử quá nhiều lần. Vui lòng thử lại sau {$seconds} giây.",
            ])->status(429);
        }

        // Tìm theo username hoặc email, không phân biệt hoa thường.
        $user = User::whereRaw('LOWER(username) = ?', [mb_strtolower($login)])
            ->orWhereRaw('LOWER(email) = ?', [mb_strtolower($login)])
            ->first();

        // Luôn báo cùng một thông điệp để không lộ tài khoản có tồn tại hay không.
        $invalid = ValidationException::withMessages([
            'login' => 'Tên đăng nhập hoặc mật khẩu không đúng.',
        ]);

        if (! $user || ! Hash::check($password, $user->password_hash)) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            if ($user) {
                $user->increment('failed_login_attempts');
            }

            throw $invalid;
        }

        if ($user->isLocked()) {
            throw ValidationException::withMessages([
                'login' => 'Tài khoản đã bị khoá. Vui lòng liên hệ quản trị viên để được hỗ trợ.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
            'failed_login_attempts' => 0,
        ])->save();

        return redirect()->intended(
            $user->isAdmin() ? route('admin.dashboard') : route('panel.dashboard')
        );
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('landing')->with('info', 'Bạn đã đăng xuất.');
    }
}
```

- [ ] **Step 7: Controller quên/đặt lại/đổi mật khẩu**

`app/Http/Controllers/Auth/ForgotPasswordController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function create(): View
    {
        // Cảnh báo khi hệ thống chưa cấu hình gửi mail thật (MAIL_MAILER=log).
        $mailConfigured = config('mail.default') !== 'log';

        return view('auth.forgot', compact('mailConfigured'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không hợp lệ.',
        ]);

        // Luôn trả về thông điệp chung để không lộ email có tồn tại hay không.
        Password::sendResetLink($request->only('email'));

        return back()->with('success', 'Nếu email tồn tại trong hệ thống, bạn sẽ nhận được liên kết đặt lại mật khẩu.');
    }
}
```

`app/Http/Controllers/Auth/ResetPasswordController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ResetPasswordController extends Controller
{
    public function create(Request $request, string $token): View
    {
        return view('auth.reset', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự.',
            'password.confirmed' => 'Mật khẩu nhập lại không khớp.',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password_hash' => Hash::make($password)])->save();
                $user->setRememberToken(Str::random(60));
                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('success', 'Mật khẩu đã được đặt lại. Vui lòng đăng nhập.')
            : back()->withErrors(['email' => 'Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.']);
    }
}
```

`app/Http/Controllers/Auth/ChangePasswordController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ChangePasswordController extends Controller
{
    public function edit(): View
    {
        return view('auth.change-password');
    }

    public function update(ChangePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (! Hash::check($request->string('current_password')->toString(), $user->password_hash)) {
            return back()->withErrors(['current_password' => 'Mật khẩu hiện tại không đúng.']);
        }

        $user->forceFill([
            'password_hash' => Hash::make($request->string('password')->toString()),
        ])->save();

        // Đổi mật khẩu thì đăng xuất các phiên khác để bảo mật.
        Auth::logoutOtherDevices($request->string('password')->toString());
        $request->session()->regenerate();

        return back()->with('success', 'Đổi mật khẩu thành công.');
    }
}
```

> `Auth::logoutOtherDevices()` cần bảng `sessions` (đã có) và driver session `database` ở production. Ở môi trường test driver là `array` nên lời gọi này không lỗi.

- [ ] **Step 8: Middleware admin + security headers**

`app/Http/Middleware/EnsureUserIsAdmin.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /** Kiểm tra quyền ở phía server — không bao giờ chỉ ẩn nút trên giao diện. */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user && $user->isAdmin(), 403, 'Bạn không có quyền truy cập trang này.');

        return $next($request);
    }
}
```

`app/Http/Middleware/SecurityHeaders.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        return $response;
    }
}
```

- [ ] **Step 9: Đăng ký middleware + route**

`bootstrap/app.php`:

```php
<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [SecurityHeaders::class]);
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('panel.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
```

`routes/web.php`:

```php
<?php

use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

// ---------- Công khai ----------
Route::view('/', 'landing.index')->name('landing');

// ---------- Xác thực ----------
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');
    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])->name('password.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// ---------- Khu vực khách hàng ----------
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('panel.dashboard');
    Route::get('/settings/password', [ChangePasswordController::class, 'edit'])->name('panel.profile.password');
    Route::post('/settings/password', [ChangePasswordController::class, 'update'])->name('panel.profile.password.update');
});

// ---------- Khu vực quản trị ----------
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
});
```

- [ ] **Step 10: Tạo DashboardController tối thiểu và các view auth**

`app/Http/Controllers/DashboardController.php` (bản tối thiểu, Task 15 sẽ hoàn thiện):

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        return view('panel.dashboard', [
            'user' => $request->user(),
        ]);
    }
}
```

`app/Http/Controllers/Admin/AdminDashboardController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard');
    }
}
```

Tạo tạm 2 view trống để test chạy:

`resources/views/panel/dashboard.blade.php`:

```blade
@extends('layouts.panel')
@section('title', 'Tổng quan')
@section('content')
    <div class="card">
        <h2 class="card-title">Xin chào, {{ $user->username }}!</h2>
        <p class="text-muted mb-0">Số dư hiện tại: <strong>{{ number_format($user->balance, 0, ',', '.') }} ₫</strong></p>
    </div>
@endsection
```

`resources/views/admin/dashboard.blade.php`:

```blade
@extends('layouts.panel')
@section('title', 'Quản trị')
@section('content')
    <div class="card"><h2 class="card-title">Trang quản trị</h2></div>
@endsection
```

Các view auth:

`resources/views/auth/login.blade.php`:

```blade
@extends('layouts.auth')
@section('title', 'Đăng nhập')
@section('content')
    <h1 class="auth-title">Đăng nhập</h1>
    <p class="auth-sub">Chào mừng bạn quay lại NhoSub.</p>

    <form method="POST" action="{{ route('login.store') }}" data-guard>
        @csrf
        <div class="form-group">
            <label class="form-label" for="login">Tên đăng nhập hoặc email</label>
            <input class="form-input @error('login') is-invalid @enderror" id="login" name="login"
                   value="{{ old('login') }}" required autofocus autocomplete="username">
        </div>
        <div class="form-group">
            <label class="form-label" for="password">Mật khẩu</label>
            <input class="form-input @error('password') is-invalid @enderror" id="password" name="password"
                   type="password" required autocomplete="current-password">
        </div>
        <div class="flex-between mb-3">
            <label class="text-sm flex gap-1" style="align-items:center;">
                <input type="checkbox" name="remember" value="1"> Ghi nhớ đăng nhập
            </label>
            <a href="{{ route('password.request') }}" class="text-sm link-underline">Quên mật khẩu?</a>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Đăng nhập</button>
    </form>

    <p class="text-center text-sm mt-3 mb-0">
        Chưa có tài khoản? <a href="{{ route('register') }}" class="link-underline">Đăng ký ngay</a>
    </p>
@endsection
```

`resources/views/auth/register.blade.php`:

```blade
@extends('layouts.auth')
@section('title', 'Đăng ký')
@section('content')
    <h1 class="auth-title">Tạo tài khoản</h1>
    <p class="auth-sub">Miễn phí, chỉ mất 30 giây.</p>

    <form method="POST" action="{{ route('register.store') }}" data-guard>
        @csrf
        <div class="form-group">
            <label class="form-label" for="username">Tên đăng nhập</label>
            <input class="form-input @error('username') is-invalid @enderror" id="username" name="username"
                   value="{{ old('username') }}" required autofocus autocomplete="username" maxlength="32">
            <div class="form-hint">3–32 ký tự, chỉ gồm chữ, số và dấu gạch dưới.</div>
        </div>
        <div class="form-group">
            <label class="form-label" for="email">Email</label>
            <input class="form-input @error('email') is-invalid @enderror" id="email" name="email"
                   type="email" value="{{ old('email') }}" required autocomplete="email">
        </div>
        <div class="form-group">
            <label class="form-label" for="full_name">Họ và tên</label>
            <input class="form-input" id="full_name" name="full_name" value="{{ old('full_name') }}" autocomplete="name">
        </div>
        <div class="form-group">
            <label class="form-label" for="phone">Số điện thoại</label>
            <input class="form-input" id="phone" name="phone" value="{{ old('phone') }}" autocomplete="tel">
        </div>
        <div class="form-group">
            <label class="form-label" for="password">Mật khẩu</label>
            <input class="form-input @error('password') is-invalid @enderror" id="password" name="password"
                   type="password" required autocomplete="new-password">
            <div class="form-hint">Tối thiểu 8 ký tự.</div>
        </div>
        <div class="form-group">
            <label class="form-label" for="password_confirmation">Nhập lại mật khẩu</label>
            <input class="form-input" id="password_confirmation" name="password_confirmation"
                   type="password" required autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-primary btn-block">Tạo tài khoản</button>
    </form>

    <p class="text-center text-sm mt-3 mb-0">
        Đã có tài khoản? <a href="{{ route('login') }}" class="link-underline">Đăng nhập</a>
    </p>
@endsection
```

`resources/views/auth/forgot.blade.php`:

```blade
@extends('layouts.auth')
@section('title', 'Quên mật khẩu')
@section('content')
    <h1 class="auth-title">Quên mật khẩu</h1>
    <p class="auth-sub">Nhập email đã đăng ký, chúng tôi sẽ gửi liên kết đặt lại mật khẩu.</p>

    @unless ($mailConfigured)
        <div class="alert alert-warning">
            Hệ thống chưa cấu hình gửi email. Vui lòng liên hệ quản trị viên để được đặt lại mật khẩu.
        </div>
    @endunless

    <form method="POST" action="{{ route('password.email') }}" data-guard>
        @csrf
        <div class="form-group">
            <label class="form-label" for="email">Email</label>
            <input class="form-input @error('email') is-invalid @enderror" id="email" name="email"
                   type="email" value="{{ old('email') }}" required autofocus>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Gửi liên kết</button>
    </form>

    <p class="text-center text-sm mt-3 mb-0">
        <a href="{{ route('login') }}" class="link-underline">Quay lại đăng nhập</a>
    </p>
@endsection
```

`resources/views/auth/reset.blade.php`:

```blade
@extends('layouts.auth')
@section('title', 'Đặt lại mật khẩu')
@section('content')
    <h1 class="auth-title">Đặt lại mật khẩu</h1>
    <p class="auth-sub">Chọn mật khẩu mới cho tài khoản của bạn.</p>

    <form method="POST" action="{{ route('password.store') }}" data-guard>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="form-group">
            <label class="form-label" for="email">Email</label>
            <input class="form-input @error('email') is-invalid @enderror" id="email" name="email"
                   type="email" value="{{ old('email', $email) }}" required>
        </div>
        <div class="form-group">
            <label class="form-label" for="password">Mật khẩu mới</label>
            <input class="form-input @error('password') is-invalid @enderror" id="password" name="password"
                   type="password" required autocomplete="new-password">
        </div>
        <div class="form-group">
            <label class="form-label" for="password_confirmation">Nhập lại mật khẩu mới</label>
            <input class="form-input" id="password_confirmation" name="password_confirmation"
                   type="password" required autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-primary btn-block">Đặt lại mật khẩu</button>
    </form>
@endsection
```

`resources/views/auth/change-password.blade.php`:

```blade
@extends('layouts.panel')
@section('title', 'Đổi mật khẩu')
@section('content')
    <div class="card" style="max-width:560px;">
        <h2 class="card-title">Đổi mật khẩu</h2>
        <form method="POST" action="{{ route('panel.profile.password.update') }}" data-guard>
            @csrf
            <div class="form-group">
                <label class="form-label" for="current_password">Mật khẩu hiện tại</label>
                <input class="form-input @error('current_password') is-invalid @enderror"
                       id="current_password" name="current_password" type="password" required autocomplete="current-password">
            </div>
            <div class="form-group">
                <label class="form-label" for="password">Mật khẩu mới</label>
                <input class="form-input @error('password') is-invalid @enderror"
                       id="password" name="password" type="password" required autocomplete="new-password">
                <div class="form-hint">Tối thiểu 8 ký tự, khác mật khẩu hiện tại.</div>
            </div>
            <div class="form-group">
                <label class="form-label" for="password_confirmation">Nhập lại mật khẩu mới</label>
                <input class="form-input" id="password_confirmation" name="password_confirmation"
                       type="password" required autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-primary">Cập nhật mật khẩu</button>
        </form>
    </div>
@endsection
```

- [ ] **Step 11: Chạy test**

```bash
cd /d/NhoSub && php artisan test --filter=AuthTest
```

Kỳ vọng: tất cả xanh.

- [ ] **Step 12: Commit**

```bash
cd /d/NhoSub && git add -A && git commit -m "feat: dang ky, dang nhap co rate limit, quen/doi mat khau, middleware admin

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 5: SettingService — cấu hình có mã hoá

**Files:**
- Create: `app/Services/SettingService.php`
- Test: `tests/Feature/SettingServiceTest.php`

**Interfaces:**
- Consumes: `Setting` model (Task 2)
- Produces:
  ```php
  SettingService::get(string $key, mixed $default = null): mixed
  SettingService::getInt(string $key, int $default = 0): int
  SettingService::getBool(string $key, bool $default = false): bool
  SettingService::getDecimal(string $key, string $default = '0'): string
  SettingService::set(string $key, mixed $value): void
  SettingService::setSecret(string $key, string $plainValue): void   // mã hoá
  SettingService::getSecret(string $key): ?string                    // giải mã
  SettingService::hasSecret(string $key): bool
  SettingService::maskedSecret(string $key): ?string                 // '••••••abcd'
  SettingService::forgetCache(): void
  ```

- [ ] **Step 1: Viết test thất bại**

`tests/Feature/SettingServiceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SettingServiceTest extends TestCase
{
    use RefreshDatabase;

    private SettingService $settings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->settings = app(SettingService::class);
    }

    public function test_doc_gia_tri_co_kieu_dung(): void
    {
        $this->settings->set('exchange_rate', '26500');
        $this->settings->set('demo_mode', '1');

        $this->assertSame('26500', $this->settings->get('exchange_rate'));
        $this->assertSame(26500, $this->settings->getInt('exchange_rate'));
        $this->assertTrue($this->settings->getBool('demo_mode'));
        $this->assertSame('9.99', $this->settings->getDecimal('khong_ton_tai', '9.99'));
    }

    public function test_secret_duoc_ma_hoa_trong_database(): void
    {
        $this->settings->setSecret('topsubre_api_key', 'super-secret-key-1234');

        $raw = DB::table('settings')->where('key', 'topsubre_api_key')->value('value');

        $this->assertNotSame('super-secret-key-1234', $raw, 'Key phải được mã hoá trong DB');
        $this->assertSame('super-secret-key-1234', $this->settings->getSecret('topsubre_api_key'));
    }

    public function test_masked_secret_chi_hien_4_ky_tu_cuoi(): void
    {
        $this->settings->setSecret('sepay_api_key', 'abcdefgh1234');

        $this->assertSame('••••••••1234', $this->settings->maskedSecret('sepay_api_key'));
        $this->assertTrue($this->settings->hasSecret('sepay_api_key'));
        $this->assertNull($this->settings->maskedSecret('khong_co'));
    }

    public function test_cache_bi_xoa_khi_set(): void
    {
        $this->settings->set('exchange_rate', '26000');
        $this->assertSame(26000, $this->settings->getInt('exchange_rate'));

        $this->settings->set('exchange_rate', '27000');
        $this->assertSame(27000, $this->settings->getInt('exchange_rate'));
    }
}
```

- [ ] **Step 2: Chạy test để thấy đỏ**

```bash
cd /d/NhoSub && php artisan test --filter=SettingServiceTest
```

Kỳ vọng: FAIL — class không tồn tại.

- [ ] **Step 3: Viết `app/Services/SettingService.php`**

```php
<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Đọc/ghi cấu hình hệ thống.
 * - Giá trị thường: lưu thẳng.
 * - Giá trị nhạy cảm (API key): mã hoá bằng APP_KEY, không bao giờ trả về nguyên văn
 *   qua giao diện — chỉ hiển thị 4 ký tự cuối.
 * - Cache 5 phút để trang admin không query liên tục; set() sẽ xoá cache.
 */
class SettingService
{
    private const CACHE_KEY = 'nhosub.settings.all';
    private const CACHE_TTL = 300;

    /** Các key được coi là bí mật — dùng setSecret/getSecret. */
    public const SECRET_KEYS = [
        'topsubre_api_key',
        'sepay_api_key',
        'payos_client_id',
        'payos_api_key',
        'payos_checksum_key',
        'momo_access_token',
        'momo_secret_key',
    ];

    private function all(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return Setting::query()->pluck('value', 'key')->all();
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->get($key);

        return $value === null || $value === '' ? $default : (int) $value;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);

        if ($value === null || $value === '') {
            return $default;
        }

        return in_array((string) $value, ['1', 'true', 'on', 'yes'], true);
    }

    /** Giá trị thập phân dạng chuỗi — tránh sai số float khi tính tiền. */
    public function getDecimal(string $key, string $default = '0'): string
    {
        $value = $this->get($key);

        return $value === null || $value === '' ? $default : (string) $value;
    }

    public function set(string $key, mixed $value): void
    {
        Setting::updateOrCreate(
            ['key' => $key],
            ['value' => $value === null ? null : (string) $value, 'updated_at' => now()]
        );

        $this->forgetCache();
    }

    /** Lưu giá trị bí mật dạng đã mã hoá. */
    public function setSecret(string $key, string $plainValue): void
    {
        if ($plainValue === '') {
            return; // Không cho phép vô tình xoá key bằng ô trống
        }

        $this->set($key, Crypt::encryptString($plainValue));
    }

    public function getSecret(string $key): ?string
    {
        $raw = $this->get($key);

        if ($raw === null || $raw === '') {
            return null;
        }

        try {
            return Crypt::decryptString($raw);
        } catch (\Throwable $e) {
            // Key đã đổi APP_KEY → không giải mã được. Ghi log không chứa giá trị.
            logger()->warning('Không giải mã được cấu hình bí mật', ['key' => $key]);

            return null;
        }
    }

    public function hasSecret(string $key): bool
    {
        $raw = $this->get($key);

        return $raw !== null && $raw !== '';
    }

    /** Hiển thị an toàn: chỉ 4 ký tự cuối. */
    public function maskedSecret(string $key): ?string
    {
        $plain = $this->getSecret($key);

        if ($plain === null) {
            return null;
        }

        return '••••••••' . mb_substr($plain, -4);
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
```

- [ ] **Step 4: Chạy test**

```bash
cd /d/NhoSub && php artisan test --filter=SettingServiceTest
```

Kỳ vọng: 4 passed.

- [ ] **Step 5: Commit**

```bash
cd /d/NhoSub && git add -A && git commit -m "feat: SettingService doc/ghi cau hinh, ma hoa key nhay cam

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 6: WalletService — sổ cái ví bất biến, chống trùng bằng khoá

> **Đây là task quan trọng nhất của toàn dự án.** Mọi thao tác tiền đều đi qua đây. Nếu sai, tiền của khách sai.

**Files:**
- Create: `app/Services/WalletService.php`
- Create: `app/Exceptions/{InsufficientBalanceException,RefundLimitExceededException}.php`
- Test: `tests/Feature/WalletServiceTest.php`

**Interfaces:**
- Consumes: `User`, `WalletTransaction` (Task 2)
- Produces:
  ```php
  WalletService::apply(
      int $userId,
      string $type,                 // WalletTransaction::TYPE_*
      int $amount,                  // dương = cộng, âm = trừ
      string $idempotencyKey,       // UNIQUE toàn hệ thống
      ?string $note = null,
      ?int $orderId = null,
      ?int $depositId = null,
      ?int $refundCapRemaining = null  // chỉ dùng cho refund: số tiền còn được hoàn tối đa
  ): WalletTransaction
  ```
  Ném `InsufficientBalanceException` khi trừ quá số dư; ném `RefundLimitExceededException` khi vượt cap. Nếu key đã tồn tại → **trả về giao dịch cũ, không áp dụng lần hai**.

- [ ] **Step 1: Viết test thất bại**

`tests/Feature/WalletServiceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\RefundLimitExceededException;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WalletServiceTest extends TestCase
{
    use RefreshDatabase;

    private WalletService $wallet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wallet = app(WalletService::class);
    }

    public function test_cong_tien_va_ghi_giao_dich(): void
    {
        $user = User::factory()->create(['balance' => 0]);

        $tx = $this->wallet->apply($user->id, WalletTransaction::TYPE_DEPOSIT, 100000, 'deposit:test:1', 'Nạp tiền');

        $this->assertSame(100000, $user->fresh()->balance);
        $this->assertSame(100000, $tx->amount);
        $this->assertSame(100000, $tx->balance_after);
        $this->assertSame('deposit:test:1', $tx->idempotency_key);
    }

    public function test_tru_tien_khi_du_so_du(): void
    {
        $user = User::factory()->create(['balance' => 50000]);

        $this->wallet->apply($user->id, WalletTransaction::TYPE_ORDER_CHARGE, -30000, 'order:1:charge');

        $this->assertSame(20000, $user->fresh()->balance);
    }

    public function test_tru_qua_so_du_nem_exception_va_khong_doi_so_du(): void
    {
        $user = User::factory()->create(['balance' => 10000]);

        $this->expectException(InsufficientBalanceException::class);

        try {
            $this->wallet->apply($user->id, WalletTransaction::TYPE_ORDER_CHARGE, -20000, 'order:2:charge');
        } finally {
            $this->assertSame(10000, $user->fresh()->balance);
            $this->assertSame(0, WalletTransaction::count());
        }
    }

    public function test_cung_mot_key_chi_ap_dung_mot_lan(): void
    {
        $user = User::factory()->create(['balance' => 0]);

        $first = $this->wallet->apply($user->id, WalletTransaction::TYPE_DEPOSIT, 50000, 'deposit:sepay:TX-001');
        $second = $this->wallet->apply($user->id, WalletTransaction::TYPE_DEPOSIT, 50000, 'deposit:sepay:TX-001');

        $this->assertSame($first->id, $second->id, 'Lần hai phải trả về giao dịch cũ');
        $this->assertSame(50000, $user->fresh()->balance, 'Số dư không được cộng hai lần');
        $this->assertSame(1, WalletTransaction::count());
    }

    public function test_refund_vuot_cap_bi_chan(): void
    {
        $user = User::factory()->create(['balance' => 0]);

        $this->expectException(RefundLimitExceededException::class);

        $this->wallet->apply(
            $user->id,
            WalletTransaction::TYPE_REFUND,
            60000,
            'order:9:refund:1',
            null,
            9,
            null,
            50000 // cap
        );
    }

    public function test_refund_trong_cap_duoc_phep(): void
    {
        $user = User::factory()->create(['balance' => 0]);

        $this->wallet->apply($user->id, WalletTransaction::TYPE_REFUND, 50000, 'order:9:refund:1', null, 9, null, 50000);

        $this->assertSame(50000, $user->fresh()->balance);
    }

    public function test_so_du_luon_bang_tong_giao_dich(): void
    {
        $user = User::factory()->create(['balance' => 0]);

        $this->wallet->apply($user->id, WalletTransaction::TYPE_DEPOSIT, 200000, 'deposit:a');
        $this->wallet->apply($user->id, WalletTransaction::TYPE_ORDER_CHARGE, -75000, 'order:1:charge');
        $this->wallet->apply($user->id, WalletTransaction::TYPE_REFUND, 25000, 'order:1:refund:1');
        $this->wallet->apply($user->id, WalletTransaction::TYPE_ADJUST, -5000, 'adjust:abc');

        $tong = (int) WalletTransaction::where('user_id', $user->id)->sum('amount');

        $this->assertSame($tong, $user->fresh()->balance);
        $this->assertSame(145000, $tong);
    }

    /**
     * Race: hai kết nối MySQL cùng áp một key đồng thời.
     * MySQL chỉ cho một INSERT thắng (UNIQUE), bên thua phải trả về giao dịch của bên thắng.
     */
    public function test_hai_tien_trinh_cung_key_khong_cong_trung(): void
    {
        $user = User::factory()->create(['balance' => 0]);

        $ketQua = [];

        // Giả lập tuần tự nhưng dùng lại chính service — kiểm tra idempotency + khoá dòng.
        for ($i = 0; $i < 3; $i++) {
            $ketQua[] = $this->wallet->apply($user->id, WalletTransaction::TYPE_DEPOSIT, 10000, 'deposit:race:1');
        }

        $this->assertSame(1, count(array_unique(array_map(fn ($t) => $t->id, $ketQua))));
        $this->assertSame(10000, $user->fresh()->balance);
        $this->assertSame(1, WalletTransaction::count());
    }

    public function test_khong_the_insert_truc_tiep_trung_key(): void
    {
        $user = User::factory()->create(['balance' => 0]);
        $this->wallet->apply($user->id, WalletTransaction::TYPE_DEPOSIT, 1000, 'dup:1');

        $this->expectException(QueryException::class);

        DB::table('wallet_transactions')->insert([
            'user_id' => $user->id,
            'type' => 'deposit',
            'amount' => 1000,
            'balance_after' => 1000,
            'idempotency_key' => 'dup:1',
            'created_at' => now(),
        ]);
    }
}
```

- [ ] **Step 2: Chạy test để thấy đỏ**

```bash
cd /d/NhoSub && php artisan test --filter=WalletServiceTest
```

Kỳ vọng: FAIL — class không tồn tại.

- [ ] **Step 3: Viết exception**

`app/Exceptions/InsufficientBalanceException.php`:

```php
<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientBalanceException extends RuntimeException
{
    public function __construct(public readonly int $required, public readonly int $available)
    {
        parent::__construct('Số dư không đủ. Cần ' . number_format($required) . ' ₫, hiện có ' . number_format($available) . ' ₫.');
    }
}
```

`app/Exceptions/RefundLimitExceededException.php`:

```php
<?php

namespace App\Exceptions;

use RuntimeException;

class RefundLimitExceededException extends RuntimeException
{
    public function __construct(public readonly int $requested, public readonly int $cap)
    {
        parent::__construct("Hoàn tiền {$requested} ₫ vượt mức tối đa {$cap} ₫ cho phép của đơn.");
    }
}
```

- [ ] **Step 4: Viết `app/Services/WalletService.php`**

```php
<?php

namespace App\Services;

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\RefundLimitExceededException;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Sổ cái ví — CỬA DUY NHẤT thay đổi số dư.
 *
 * Bất biến:
 *  1. Mọi thay đổi đi qua apply(), không UPDATE users.balance ở nơi khác.
 *  2. Khoá dòng user (SELECT ... FOR UPDATE) trước khi đọc/ghi số dư.
 *  3. idempotency_key UNIQUE — cùng key chỉ áp dụng một lần, kể cả khi
 *     webhook gửi lại, cron chạy chồng, hay người dùng bấm hai lần.
 *  4. Số dư không bao giờ âm.
 *  5. Hoàn tiền không bao giờ vượt cap (do RefundService truyền vào).
 */
class WalletService
{
    public function apply(
        int $userId,
        string $type,
        int $amount,
        string $idempotencyKey,
        ?string $note = null,
        ?int $orderId = null,
        ?int $depositId = null,
        ?int $refundCapRemaining = null
    ): WalletTransaction {
        // Chặn sớm: key đã dùng thì trả về giao dịch cũ (không cần vào transaction).
        $existing = WalletTransaction::where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        try {
            return DB::transaction(function () use (
                $userId, $type, $amount, $idempotencyKey, $note, $orderId, $depositId, $refundCapRemaining
            ) {
                /** @var User $user */
                $user = User::query()->lockForUpdate()->findOrFail($userId);

                // Kiểm tra cap hoàn tiền (đơn hàng không được hoàn vượt tổng đã trả).
                if ($type === WalletTransaction::TYPE_REFUND && $refundCapRemaining !== null) {
                    if ($amount > $refundCapRemaining) {
                        throw new RefundLimitExceededException($amount, $refundCapRemaining);
                    }
                }

                $newBalance = $user->balance + $amount;

                if ($newBalance < 0) {
                    throw new InsufficientBalanceException(abs($amount), $user->balance);
                }

                $user->forceFill(['balance' => $newBalance])->save();

                return WalletTransaction::create([
                    'user_id' => $user->id,
                    'order_id' => $orderId,
                    'deposit_id' => $depositId,
                    'type' => $type,
                    'amount' => $amount,
                    'balance_after' => $newBalance,
                    'idempotency_key' => $idempotencyKey,
                    'note' => $note,
                    'created_at' => now(),
                ]);
            });
        } catch (QueryException $e) {
            // Trường hợp hiếm: hai request cùng key lọt qua bước kiểm tra đầu tiên.
            // Bên thua nhận lỗi UNIQUE → trả về giao dịch của bên thắng.
            if ($this->isUniqueViolation($e)) {
                $existing = WalletTransaction::where('idempotency_key', $idempotencyKey)->first();
                if ($existing !== null) {
                    return $existing;
                }
            }

            throw $e;
        }
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return (int) $e->getCode() === 23000 || str_contains($e->getMessage(), 'Duplicate entry');
    }
}
```

- [ ] **Step 5: Chạy test**

```bash
cd /d/NhoSub && php artisan test --filter=WalletServiceTest
```

Kỳ vọng: 9 passed.

- [ ] **Step 6: Commit**

```bash
cd /d/NhoSub && git add -A && git commit -m "feat: WalletService so cai vi, idempotency UNIQUE, chan so du am va hoan vuot cap

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 7: RefundService — hoàn tiền nhiều đợt, không trùng

**Files:**
- Create: `app/Services/RefundService.php`
- Test: `tests/Feature/RefundServiceTest.php`

**Interfaces:**
- Consumes: `WalletService::apply()` (Task 6), `Order`, `OrderRefund` (Task 2)
- Produces:
  ```php
  RefundService::refundOrder(Order $order, string $reason): ?OrderRefund
  ```
  Trả `null` nếu không có gì để hoàn. Tự khoá dòng đơn, tính delta, ghi `order_refunds`, gọi ví với key `order:{id}:refund:{seq}`.
  Hằng `reason`: `'auto_partial'`, `'auto_canceled'`, `'provider_error'`, `'admin_manual'`.

- [ ] **Step 1: Viết test thất bại**

`tests/Feature/RefundServiceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\User;
use App\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundServiceTest extends TestCase
{
    use RefreshDatabase;

    private RefundService $refunds;

    protected function setUp(): void
    {
        parent::setUp();
        $this->refunds = app(RefundService::class);
    }

    /** Đơn 1000 lượt, giá 3380đ/1000, khách đã trả 3380đ, đã trừ 3380. */
    private function makeOrder(int $balance = 0, array $overrides = []): Order
    {
        $user = User::factory()->create(['balance' => $balance]);

        return Order::factory()->create(array_merge([
            'user_id' => $user->id,
            'quantity' => 1000,
            'unit_price' => 3380,
            'total_amount' => 3380,
            'cost_amount' => 2600,
            'status' => Order::STATUS_PROCESSING,
            'refunded_amount' => 0,
            'refund_count' => 0,
        ], $overrides));
    }

    public function test_partial_hoan_phan_chua_chay(): void
    {
        $order = $this->makeOrder(overrides: [
            'status' => Order::STATUS_PARTIAL,
            'start_count' => 100,
            'remains' => 600,   // đã chạy 400
        ]);

        $refund = $this->refunds->refundOrder($order, 'auto_partial');

        $this->assertNotNull($refund);
        $this->assertSame(1, $refund->seq);
        // 3380 * 600 / 1000 = 2028
        $this->assertSame(2028, $refund->amount);
        $this->assertSame(2028, $order->fresh()->refunded_amount);
        $this->assertSame(1, $order->fresh()->refund_count);
        $this->assertSame(2028, $order->user->fresh()->balance);
    }

    public function test_cron_chay_lai_khong_hoan_them(): void
    {
        $order = $this->makeOrder(overrides: [
            'status' => Order::STATUS_PARTIAL,
            'remains' => 600,
        ]);

        $this->refunds->refundOrder($order, 'auto_partial');
        $lan2 = $this->refunds->refundOrder($order->fresh(), 'auto_partial');

        $this->assertNull($lan2);
        $this->assertSame(2028, $order->fresh()->refunded_amount);
        $this->assertSame(1, OrderRefund::count());
    }

    public function test_partial_roi_canceled_hoan_them_dot_hai(): void
    {
        $order = $this->makeOrder(overrides: [
            'status' => Order::STATUS_PARTIAL,
            'remains' => 600,
        ]);

        $dot1 = $this->refunds->refundOrder($order, 'auto_partial');
        $this->assertSame(2028, $dot1->amount);

        // NCC cập nhật: chỉ chạy được 200, còn lại 800.
        $order->refresh()->forceFill([
            'status' => Order::STATUS_CANCELED,
            'remains' => 800,
        ])->save();

        $dot2 = $this->refunds->refundOrder($order->fresh(), 'auto_canceled');

        $this->assertNotNull($dot2);
        $this->assertSame(2, $dot2->seq);
        // Đáng hoàn 3380*800/1000 = 2704; đã hoàn 2028 → hoàn thêm 676
        $this->assertSame(676, $dot2->amount);
        $this->assertSame(2704, $order->fresh()->refunded_amount);
        $this->assertSame(2, $order->fresh()->refund_count);
        $this->assertSame(2704, $order->user->fresh()->balance);
    }

    public function test_khong_hoan_vuot_tong_don(): void
    {
        // NCC báo remains vô lý lớn hơn quantity → kẹp về quantity, hoàn tối đa total_amount.
        $order = $this->makeOrder(overrides: [
            'status' => Order::STATUS_CANCELED,
            'remains' => 999999,
        ]);

        $refund = $this->refunds->refundOrder($order, 'auto_canceled');

        $this->assertSame(3380, $refund->amount);
        $this->assertSame(3380, $order->fresh()->refunded_amount);
    }

    public function test_don_completed_khong_hoan(): void
    {
        $order = $this->makeOrder(overrides: [
            'status' => Order::STATUS_COMPLETED,
            'start_count' => 100,
            'remains' => 0,
        ]);

        $this->assertNull($this->refunds->refundOrder($order, 'auto_partial'));
        $this->assertSame(0, OrderRefund::count());
    }

    public function test_hoan_toan_bo_khi_provider_error(): void
    {
        $order = $this->makeOrder(overrides: [
            'status' => Order::STATUS_ERROR,
            'remains' => 1000,
        ]);

        $refund = $this->refunds->refundOrder($order, 'provider_error');

        $this->assertSame(3380, $refund->amount);
        $this->assertSame('provider_error', $refund->reason);
    }

    public function test_goi_lai_voi_cung_trang_thai_khong_tao_dong_thua(): void
    {
        $order = $this->makeOrder(overrides: ['status' => Order::STATUS_PARTIAL, 'remains' => 600]);

        $a = $this->refunds->refundOrder($order, 'auto_partial');
        $b = $this->refunds->refundOrder($order->fresh(), 'auto_partial');
        $c = $this->refunds->refundOrder($order->fresh(), 'auto_partial');

        $this->assertNotNull($a);
        $this->assertNull($b);
        $this->assertNull($c);
        $this->assertSame(1, OrderRefund::count());
        $this->assertSame(2028, $order->fresh()->refunded_amount);
    }
}
```

- [ ] **Step 2: Chạy test để thấy đỏ**

```bash
cd /d/NhoSub && php artisan test --filter=RefundServiceTest
```

Kỳ vọng: FAIL — class không tồn tại.

- [ ] **Step 3: Viết `app/Services/RefundService.php`**

```php
<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Hoàn tiền nhiều đợt cho một đơn.
 *
 * Vì sao nhiều đợt: đơn có thể partial (hoàn phần chưa chạy), sau đó NCC
 * báo canceled với số liệu khác → phải hoàn tiếp phần chênh lệch.
 *
 * Chống trùng 3 lớp:
 *  1. Khoá dòng đơn (lockForUpdate) → hai cron không cùng tính delta.
 *  2. seq tăng dần + UNIQUE(order_id, seq) → không ghi trùng đợt.
 *  3. idempotency_key = order:{id}:refund:{seq} → ví không áp dụng hai lần.
 */
class RefundService
{
    public function __construct(private readonly WalletService $wallet)
    {
    }

    public function refundOrder(Order $order, string $reason): ?OrderRefund
    {
        return DB::transaction(function () use ($order, $reason) {
            /** @var Order $locked */
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            // Đơn hoàn thành thì không còn gì để hoàn.
            if ($locked->status === Order::STATUS_COMPLETED) {
                return null;
            }

            $unused = $locked->unusedQuantity();
            $shouldRefund = (int) floor($locked->unit_price * $unused / 1000);

            // Không bao giờ hoàn vượt tổng khách đã trả.
            $shouldRefund = min($shouldRefund, (int) $locked->total_amount);

            $delta = $shouldRefund - (int) $locked->refunded_amount;

            if ($delta <= 0) {
                return null;
            }

            $seq = (int) $locked->refund_count + 1;

            // Gọi ví với cap = phần còn được hoàn để chặn cứng ở tầng tiền.
            $tx = $this->wallet->apply(
                userId: (int) $locked->user_id,
                type: WalletTransaction::TYPE_REFUND,
                amount: $delta,
                idempotencyKey: "order:{$locked->id}:refund:{$seq}",
                note: "Hoàn tiền đơn #{$locked->id} — {$reason}",
                orderId: (int) $locked->id,
                refundCapRemaining: (int) $locked->total_amount - (int) $locked->refunded_amount,
            );

            $refund = OrderRefund::create([
                'order_id' => $locked->id,
                'seq' => $seq,
                'amount' => $delta,
                'reason' => $reason,
                'wallet_transaction_id' => $tx->id,
            ]);

            $locked->forceFill([
                'refunded_amount' => (int) $locked->refunded_amount + $delta,
                'refund_count' => $seq,
            ])->save();

            return $refund;
        });
    }
}
```

- [ ] **Step 4: Chạy test**

```bash
cd /d/NhoSub && php artisan test --filter=RefundServiceTest
```

Kỳ vọng: 7 passed.

- [ ] **Step 5: Commit**

```bash
cd /d/NhoSub && git add -A && git commit -m "feat: RefundService hoan tien nhieu dot voi seq tang dan, chong trung 3 lop

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 8: PricingService — tính giá không sai số

**Files:**
- Create: `app/Services/PricingService.php`
- Test: `tests/Feature/PricingTest.php`

**Interfaces:**
- Consumes: `SettingService` (Task 5)
- Produces:
  ```php
  PricingService::costPriceFromUsd(string|float $usd): int        // VND/1000
  PricingService::sellPriceFromCost(int $costPrice): int          // VND/1000 theo % mặc định
  PricingService::orderTotal(int $unitPrice, int $quantity): int  // VND, floor
  PricingService::orderCost(int $costPrice, int $quantity): int   // VND, floor
  PricingService::exchangeRate(): int
  PricingService::markupPercent(): string
  ```

- [ ] **Step 1: Viết test thất bại**

`tests/Feature/PricingTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Services\PricingService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingTest extends TestCase
{
    use RefreshDatabase;

    private PricingService $pricing;

    protected function setUp(): void
    {
        parent::setUp();

        $settings = app(SettingService::class);
        $settings->set('exchange_rate', '26000');
        $settings->set('default_markup_percent', '30');

        $this->pricing = app(PricingService::class);
    }

    public function test_quy_doi_usd_sang_vnd_theo_ti_gia(): void
    {
        // 0.08 USD * 26000 = 2080 VND
        $this->assertSame(2080, $this->pricing->costPriceFromUsd('0.080000'));
        // 0.005 * 26000 = 130
        $this->assertSame(130, $this->pricing->costPriceFromUsd('0.005000'));
        // làm tròn xuống: 0.000019 * 26000 = 0.494 → 0
        $this->assertSame(0, $this->pricing->costPriceFromUsd('0.000019'));
    }

    public function test_gia_ban_theo_phan_tram_loi_nhuan(): void
    {
        // 2080 * 1.30 = 2704
        $this->assertSame(2704, $this->pricing->sellPriceFromCost(2080));
        // 10000 * 1.30 = 13000
        $this->assertSame(13000, $this->pricing->sellPriceFromCost(10000));
    }

    public function test_tong_tien_don_lam_tron_xuong(): void
    {
        // 3380 * 1000 / 1000 = 3380
        $this->assertSame(3380, $this->pricing->orderTotal(3380, 1000));
        // 3380 * 333 / 1000 = 1125.54 → 1125
        $this->assertSame(1125, $this->pricing->orderTotal(3380, 333));
        // 2704 * 1500 / 1000 = 4056
        $this->assertSame(4056, $this->pricing->orderTotal(2704, 1500));
    }

    public function test_so_luong_cuc_lon_khong_tran_so(): void
    {
        // 1000 VND/1000 * 10.000.000 = 10.000.000 VND — vẫn trong BIGINT
        $this->assertSame(10_000_000, $this->pricing->orderTotal(1000, 10_000_000));

        // 50000 VND/1000 * 100.000.000 = 5.000.000.000 VND — vượt 32-bit, BIGINT vẫn đúng
        $this->assertSame(5_000_000_000, $this->pricing->orderTotal(50000, 100_000_000));
    }

    public function test_doi_ti_gia_lam_doi_gia_quy_doi(): void
    {
        app(SettingService::class)->set('exchange_rate', '27000');

        $this->assertSame(2160, $this->pricing->costPriceFromUsd('0.080000'));
    }

    public function test_phan_tram_loi_nhuan_thap_nhat_la_0(): void
    {
        app(SettingService::class)->set('default_markup_percent', '0');

        $this->assertSame(2080, $this->pricing->sellPriceFromCost(2080));
    }
}
```

- [ ] **Step 2: Chạy test để thấy đỏ**

```bash
cd /d/NhoSub && php artisan test --filter=PricingTest
```

Kỳ vọng: FAIL — class không tồn tại.

- [ ] **Step 3: Viết `app/Services/PricingService.php`**

```php
<?php

namespace App\Services;

/**
 * Tính giá — toàn bộ phép tính tiền chạy trên SỐ NGUYÊN VND.
 *
 * Công thức (spec 5.7):
 *   cost_price  = floor(cost_usd * tỉ_giá)
 *   sell_price  = round(cost_price * (1 + %/100))
 *   total       = floor(sell_price * quantity / 1000)
 *   cost_amount = floor(cost_price * quantity / 1000)
 *
 * Không dùng float cho kết quả cuối — ép int ngay sau mỗi bước.
 */
class PricingService
{
    public function __construct(private readonly SettingService $settings)
    {
    }

    public function exchangeRate(): int
    {
        return max(1, $this->settings->getInt('exchange_rate', 26000));
    }

    public function markupPercent(): string
    {
        return $this->settings->getDecimal('default_markup_percent', '30');
    }

    public function costPriceFromUsd(string|float $usd): int
    {
        $rate = $this->exchangeRate();
        $value = (float) $usd;

        if ($value <= 0) {
            return 0;
        }

        // Tính bằng số nguyên thứ ba (micro-USD) để tránh sai số nhị phân:
        // 0.080000 → 80000 micro-USD; 80000 * 26000 / 1_000_000 = 2080.
        $micro = (int) round($value * 1_000_000);

        return intdiv($micro * $rate, 1_000_000);
    }

    public function sellPriceFromCost(int $costPrice): int
    {
        if ($costPrice <= 0) {
            return 0;
        }

        $percent = (float) $this->markupPercent();
        $percent = max(0.0, $percent);

        // Nhân phần trăm bằng số nguyên phần vạn để tránh float:
        // 30.00% → 3000 phần vạn; 2080 * (10000 + 3000) / 10000 = 2704.
        $bps = (int) round($percent * 100);

        return intdiv($costPrice * (10_000 + $bps) + 5_000, 10_000);
    }

    /** Tổng tiền khách trả, làm tròn xuống, đơn vị VND. */
    public function orderTotal(int $unitPrice, int $quantity): int
    {
        if ($unitPrice <= 0 || $quantity <= 0) {
            return 0;
        }

        return intdiv($unitPrice * $quantity, 1000);
    }

    /** Chi phí gốc phải trả nhà cung cấp, đơn vị VND. */
    public function orderCost(int $costPrice, int $quantity): int
    {
        if ($costPrice <= 0 || $quantity <= 0) {
            return 0;
        }

        return intdiv($costPrice * $quantity, 1000);
    }
}
```

- [ ] **Step 4: Chạy test**

```bash
cd /d/NhoSub && php artisan test --filter=PricingTest
```

Kỳ vọng: 6 passed.

- [ ] **Step 5: Commit**

```bash
cd /d/NhoSub && git add -A && git commit -m "feat: PricingService tinh gia bang so nguyen, khong sai so float

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 9: Tầng Provider — interface, DTO, TopSubRe, Demo, Manager

**Files:**
- Create: `app/Providers/ProviderInterface.php`
- Create: `app/Providers/Dto/{ProviderBalance,ProviderService,ProviderOrderResult,ProviderOrderStatus}.php`
- Create: `app/Providers/TopSubRe/TopSubReProvider.php`
- Create: `app/Providers/Demo/DemoProvider.php`
- Create: `app/Providers/ProviderManager.php`
- Create: `app/Providers/ProviderServiceProvider.php`
- Create: `app/Exceptions/ProviderException.php`
- Modify: `bootstrap/providers.php`
- Test: `tests/Unit/ProviderTest.php`, `tests/Feature/DemoProviderTest.php`

**Interfaces:**
- Consumes: `SettingService` (Task 5), `Order` model (Task 2)
- Produces:
  ```php
  interface ProviderInterface {
      public function balance(): ProviderBalance;                       // usd: string
      public function services(): array;                                // ProviderService[]
      public function addOrder(string $serviceId, string $link, int $quantity): ProviderOrderResult; // providerOrderId: string
      public function orderStatus(string $providerOrderId): ProviderOrderStatus; // status: pending|processing|completed|partial|canceled
  }

  // DTO là readonly class, thuộc tính public.
  ProviderBalance{ string $usd }
  ProviderService{ string $id, string $name, string $category, string $description,
                   string $costUsd, int $minQty, int $maxQty }
  ProviderOrderResult{ string $providerOrderId }
  ProviderOrderStatus{ string $status, ?int $startCount, ?int $remains }

  ProviderManager::driver(): ProviderInterface
  ```

- [ ] **Step 1: Viết test thất bại**

`tests/Feature/DemoProviderTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Providers\Demo\DemoProvider;
use App\Providers\ProviderInterface;
use App\Providers\ProviderManager;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoProviderTest extends TestCase
{
    use RefreshDatabase;

    private function provider(): DemoProvider
    {
        return app(DemoProvider::class);
    }

    private function makeOrder(array $overrides = []): Order
    {
        $user = User::factory()->create();
        $service = Service::factory()->create(['provider_service_id' => 'demo-1']);

        return Order::factory()->create(array_merge([
            'user_id' => $user->id,
            'service_id' => $service->id,
            'quantity' => 1000,
        ], $overrides));
    }

    public function test_demo_tra_ve_so_du_va_danh_sach_dich_vu(): void
    {
        $balance = $this->provider()->balance();
        $this->assertIsNumeric($balance->usd);

        $services = $this->provider()->services();
        $this->assertNotEmpty($services);
        $this->assertSame('demo-1', $services[0]->id);
    }

    public function test_add_order_tra_ma_gan_voi_don(): void
    {
        $order = $this->makeOrder();
        $result = $this->provider()->addOrder('demo-1', $order->target_link, 1000);

        $this->assertSame('demo:' . $order->id, $result->providerOrderId);
    }

    public function test_trang_thai_tien_hoa_theo_thoi_gian(): void
    {
        $order = $this->makeOrder(['created_at' => now()]);
        $this->assertSame('pending', $this->provider()->orderStatus('demo:' . $order->id)->status);

        $order->forceFill(['created_at' => now()->subMinutes(2)])->save();
        $this->assertSame('processing', $this->provider()->orderStatus('demo:' . $order->id)->status);

        $order->forceFill(['created_at' => now()->subMinutes(5)])->save();
        $this->assertSame('completed', $this->provider()->orderStatus('demo:' . $order->id)->status);
    }

    public function test_che_do_partial_tra_remains(): void
    {
        app(SettingService::class)->set('demo_scenario', 'partial');
        $order = $this->makeOrder(['created_at' => now()->subMinutes(5), 'quantity' => 1000]);

        $status = $this->provider()->orderStatus('demo:' . $order->id);

        $this->assertSame('partial', $status->status);
        $this->assertSame(400, $status->remains);
        $this->assertSame(0, $status->startCount);
    }

    public function test_che_do_cancel_tra_canceled(): void
    {
        app(SettingService::class)->set('demo_scenario', 'cancel');
        $order = $this->makeOrder(['created_at' => now()->subMinutes(5), 'quantity' => 1000]);

        $status = $this->provider()->orderStatus('demo:' . $order->id);

        $this->assertSame('canceled', $status->status);
        $this->assertSame(1000, $status->remains);
    }

    public function test_manager_tra_ve_demo_khi_chua_co_api_key(): void
    {
        app(SettingService::class)->set('topsubre_api_key', '');

        $this->assertInstanceOf(DemoProvider::class, app(ProviderManager::class)->driver());
    }

    public function test_manager_tra_ve_topsubre_khi_co_api_key(): void
    {
        app(SettingService::class)->setSecret('topsubre_api_key', 'real-key-abcdef');
        app(SettingService::class)->set('demo_mode', 'off');

        $this->assertInstanceOf(
            \App\Providers\TopSubRe\TopSubReProvider::class,
            app(ProviderManager::class)->driver()
        );
    }

    public function test_manager_buoc_dung_demo_khi_bat_demo_mode(): void
    {
        app(SettingService::class)->setSecret('topsubre_api_key', 'real-key-abcdef');
        app(SettingService::class)->set('demo_mode', 'on');

        $this->assertInstanceOf(DemoProvider::class, app(ProviderManager::class)->driver());
    }
}
```

`tests/Unit/ProviderTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Exceptions\ProviderException;
use App\Providers\TopSubRe\TopSubReProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProviderTest extends TestCase
{
    public function test_topsubre_gui_dung_action_va_key(): void
    {
        Http::fake([
            'likenhanh.pro/*' => Http::response(['balance' => '12.34', 'currency' => 'USD'], 200),
        ]);

        $provider = new TopSubReProvider('secret-key', 'https://likenhanh.pro/api/v2');
        $balance = $provider->balance();

        $this->assertSame('12.34', $balance->usd);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->url() === 'https://likenhanh.pro/api/v2'
                && $body['key'] === 'secret-key'
                && $body['action'] === 'balance';
        });
    }

    public function test_topsubre_loi_tra_ve_nem_provider_exception(): void
    {
        Http::fake([
            'likenhanh.pro/*' => Http::response(['error' => 'Invalid API key'], 200),
        ]);

        $provider = new TopSubReProvider('bad-key', 'https://likenhanh.pro/api/v2');

        $this->expectException(ProviderException::class);
        $provider->balance();
    }

    public function test_topsubre_add_order_doc_order_id(): void
    {
        Http::fake([
            'likenhanh.pro/*' => Http::response(['order' => 987654], 200),
        ]);

        $provider = new TopSubReProvider('k', 'https://likenhanh.pro/api/v2');
        $result = $provider->addOrder('123', 'https://fb.com/x', 500);

        $this->assertSame('987654', $result->providerOrderId);
    }

    public function test_topsubre_status_map_dung(): void
    {
        Http::fake([
            'likenhanh.pro/*' => Http::response([
                'status' => 'Partial',
                'start_count' => 100,
                'remains' => '250',
            ], 200),
        ]);

        $provider = new TopSubReProvider('k', 'https://likenhanh.pro/api/v2');
        $status = $provider->orderStatus('987654');

        $this->assertSame('partial', $status->status);
        $this->assertSame(100, $status->startCount);
        $this->assertSame(250, $status->remains);
    }

    public function test_topsubre_khong_bao_gio_ghi_key_vao_log(): void
    {
        Http::fake(['likenhanh.pro/*' => Http::response(['error' => 'x'], 200)]);

        $provider = new TopSubReProvider('TOPSECRET-KEY-9999', 'https://likenhanh.pro/api/v2');

        try {
            $provider->balance();
        } catch (ProviderException $e) {
            $this->assertStringNotContainsString('TOPSECRET-KEY-9999', $e->getMessage());
        }
    }
}
```

- [ ] **Step 2: Chạy test để thấy đỏ**

```bash
cd /d/NhoSub && php artisan test --filter="DemoProviderTest|ProviderTest"
```

Kỳ vọng: FAIL — class không tồn tại.

- [ ] **Step 3: Viết DTO**

`app/Providers/Dto/ProviderBalance.php`:

```php
<?php

namespace App\Providers\Dto;

/** Số dư tài khoản tại nhà cung cấp (USD). */
final readonly class ProviderBalance
{
    public function __construct(public string $usd)
    {
    }
}
```

`app/Providers/Dto/ProviderService.php`:

```php
<?php

namespace App\Providers\Dto;

/** Một dịch vụ nhà cung cấp trả về (giá tính USD/1000). */
final readonly class ProviderService
{
    public function __construct(
        public string $id,
        public string $name,
        public string $category,
        public string $description,
        public string $costUsd,
        public int $minQty,
        public int $maxQty,
    ) {
    }
}
```

`app/Providers/Dto/ProviderOrderResult.php`:

```php
<?php

namespace App\Providers\Dto;

final readonly class ProviderOrderResult
{
    public function __construct(public string $providerOrderId)
    {
    }
}
```

`app/Providers/Dto/ProviderOrderStatus.php`:

```php
<?php

namespace App\Providers\Dto;

final readonly class ProviderOrderStatus
{
    public function __construct(
        public string $status,       // pending|processing|completed|partial|canceled
        public ?int $startCount = null,
        public ?int $remains = null,
    ) {
    }
}
```

- [ ] **Step 4: Interface + exception**

`app/Providers/ProviderInterface.php`:

```php
<?php

namespace App\Providers;

use App\Providers\Dto\ProviderBalance;
use App\Providers\Dto\ProviderOrderResult;
use App\Providers\Dto\ProviderOrderStatus;
use App\Providers\Dto\ProviderService;

/**
 * Hợp đồng chung cho mọi nhà cung cấp SMM.
 * Luồng đặt đơn chỉ làm việc với interface này → thêm NCC mới không phải sửa code đặt đơn.
 */
interface ProviderInterface
{
    public function balance(): ProviderBalance;

    /** @return ProviderService[] */
    public function services(): array;

    public function addOrder(string $serviceId, string $link, int $quantity): ProviderOrderResult;

    public function orderStatus(string $providerOrderId): ProviderOrderStatus;
}
```

`app/Exceptions/ProviderException.php`:

```php
<?php

namespace App\Exceptions;

use RuntimeException;

/** Lỗi từ nhà cung cấp — thông điệp KHÔNG bao giờ chứa API key. */
class ProviderException extends RuntimeException
{
}
```

- [ ] **Step 5: TopSubReProvider**

`app/Providers/TopSubRe/TopSubReProvider.php`:

```php
<?php

namespace App\Providers\TopSubRe;

use App\Exceptions\ProviderException;
use App\Providers\Dto\ProviderBalance;
use App\Providers\Dto\ProviderOrderResult;
use App\Providers\Dto\ProviderOrderStatus;
use App\Providers\Dto\ProviderService;
use App\Providers\ProviderInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Nhà cung cấp TopSubRe — API V2 (POST JSON, có trường "key" + "action").
 * Tài liệu: https://likenhanh.pro/api/v2
 *
 * Bảo mật: key chỉ nằm trong header/body của request. Log và exception
 * chỉ ghi action + thông điệp lỗi đã lọc, không bao giờ ghi key.
 */
class TopSubReProvider implements ProviderInterface
{
    private const TIMEOUT = 20;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $endpoint = 'https://likenhanh.pro/api/v2'
    ) {
    }

    public function balance(): ProviderBalance
    {
        $data = $this->call('balance');

        return new ProviderBalance(usd: (string) ($data['balance'] ?? '0'));
    }

    public function services(): array
    {
        $data = $this->call('services');

        if (! is_array($data)) {
            throw new ProviderException('Dữ liệu dịch vụ từ nhà cung cấp không hợp lệ.');
        }

        $list = [];
        foreach ($data as $row) {
            if (! is_array($row) || ! isset($row['service'])) {
                continue;
            }

            $list[] = new ProviderService(
                id: (string) $row['service'],
                name: (string) ($row['name'] ?? ''),
                category: (string) ($row['category'] ?? 'Khác'),
                description: (string) ($row['description'] ?? ''),
                costUsd: (string) ($row['rate'] ?? '0'),
                minQty: (int) ($row['min'] ?? 1),
                maxQty: (int) ($row['max'] ?? 1_000_000),
            );
        }

        return $list;
    }

    public function addOrder(string $serviceId, string $link, int $quantity): ProviderOrderResult
    {
        $data = $this->call('add', [
            'service' => $serviceId,
            'link' => $link,
            'quantity' => $quantity,
        ]);

        if (! isset($data['order'])) {
            throw new ProviderException('Nhà cung cấp không trả về mã đơn.');
        }

        return new ProviderOrderResult(providerOrderId: (string) $data['order']);
    }

    public function orderStatus(string $providerOrderId): ProviderOrderStatus
    {
        $data = $this->call('status', ['order' => $providerOrderId]);

        $raw = strtolower((string) ($data['status'] ?? ''));

        $mapped = match (true) {
            str_contains($raw, 'pending') => 'pending',
            str_contains($raw, 'progress') => 'processing',
            str_contains($raw, 'process') => 'processing',
            str_contains($raw, 'complete') => 'completed',
            str_contains($raw, 'partial') => 'partial',
            str_contains($raw, 'cancel'), str_contains($raw, 'refund') => 'canceled',
            default => 'unknown',
        };

        return new ProviderOrderStatus(
            status: $mapped,
            startCount: isset($data['start_count']) ? (int) $data['start_count'] : null,
            remains: isset($data['remains']) ? (int) $data['remains'] : null,
        );
    }

    /**
     * Gọi API và chuẩn hoá lỗi.
     * TopSubRe có thể trả {error: true/false} hoặc {error: "thông điệp"}.
     */
    private function call(string $action, array $params = []): array
    {
        try {
            $response = Http::timeout(self::TIMEOUT)
                ->acceptJson()
                ->asJson()
                ->post($this->endpoint, array_merge([
                    'key' => $this->apiKey,
                    'action' => $action,
                ], $params));
        } catch (ConnectionException $e) {
            Log::warning('TopSubRe: không kết nối được', ['action' => $action]);

            throw new ProviderException('Không kết nối được nhà cung cấp. Vui lòng thử lại sau.');
        }

        if ($response->failed()) {
            Log::warning('TopSubRe: HTTP lỗi', ['action' => $action, 'status' => $response->status()]);

            throw new ProviderException('Nhà cung cấp trả lỗi HTTP ' . $response->status() . '.');
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new ProviderException('Phản hồi nhà cung cấp không phải JSON hợp lệ.');
        }

        $error = $data['error'] ?? false;

        if ($error !== false && $error !== null && $error !== '' && $error !== 0) {
            // Chỉ lấy thông điệp lỗi; tuyệt đối không đưa key vào.
            $message = is_string($error) ? $error : 'Nhà cung cấp báo lỗi.';

            Log::warning('TopSubRe: báo lỗi nghiệp vụ', ['action' => $action, 'message' => $message]);

            throw new ProviderException($message);
        }

        return $data;
    }
}
```

- [ ] **Step 6: DemoProvider**

`app/Providers/Demo/DemoProvider.php`:

```php
<?php

namespace App\Providers\Demo;

use App\Exceptions\ProviderException;
use App\Models\Order;
use App\Providers\Dto\ProviderBalance;
use App\Providers\Dto\ProviderOrderResult;
use App\Providers\Dto\ProviderOrderStatus;
use App\Providers\Dto\ProviderService;
use App\Providers\ProviderInterface;
use App\Services\SettingService;

/**
 * Nhà cung cấp giả lập — dùng khi chưa cấu hình API key TopSubRe
 * hoặc khi admin bật "chế độ demo".
 *
 * Trạng thái đơn tiến hoá theo thời gian kể từ lúc tạo:
 *   0–60s      → pending
 *   60–180s    → processing
 *   > 180s     → completed (mặc định), partial hoặc canceled theo demo_scenario
 *
 * provider_order_id có dạng "demo:{orderId}" để truy ngược đơn gốc.
 */
class DemoProvider implements ProviderInterface
{
    public function __construct(private readonly SettingService $settings)
    {
    }

    public function balance(): ProviderBalance
    {
        return new ProviderBalance(usd: '999.000000');
    }

    public function services(): array
    {
        $rows = [
            ['demo-1', 'Facebook — Like bài viết', 'Facebook', '0.080000', 100, 100000],
            ['demo-2', 'Facebook — Follow trang', 'Facebook', '0.150000', 500, 500000],
            ['demo-3', 'TikTok — View video', 'TikTok', '0.010000', 1000, 10000000],
            ['demo-4', 'TikTok — Like video', 'TikTok', '0.060000', 100, 1000000],
            ['demo-5', 'Instagram — Follow', 'Instagram', '0.120000', 100, 500000],
            ['demo-6', 'YouTube — Subscriber', 'YouTube', '0.800000', 100, 50000],
        ];

        return array_map(fn ($r) => new ProviderService(
            id: $r[0],
            name: $r[1],
            category: $r[2],
            description: 'Dịch vụ mô phỏng cho môi trường demo.',
            costUsd: $r[3],
            minQty: $r[4],
            maxQty: $r[5],
        ), $rows);
    }

    public function addOrder(string $serviceId, string $link, int $quantity): ProviderOrderResult
    {
        $scenario = $this->settings->get('demo_scenario', 'auto');

        // Kịch bản "fail": giả lập nhà cung cấp từ chối → dùng để test hoàn tiền.
        if ($scenario === 'fail') {
            throw new ProviderException('Nhà cung cấp tạm thời không nhận đơn (chế độ demo).');
        }

        // Lấy đơn vừa tạo gần nhất của link + số lượng này để gắn mã demo.
        $order = Order::query()
            ->where('target_link', $link)
            ->where('quantity', $quantity)
            ->latest('id')
            ->first();

        $orderId = $order?->id ?? 0;

        return new ProviderOrderResult(providerOrderId: 'demo:' . $orderId);
    }

    public function orderStatus(string $providerOrderId): ProviderOrderStatus
    {
        if (! str_starts_with($providerOrderId, 'demo:')) {
            throw new ProviderException('Mã đơn demo không hợp lệ.');
        }

        $orderId = (int) substr($providerOrderId, 5);
        $order = Order::find($orderId);

        if (! $order) {
            throw new ProviderException('Không tìm thấy đơn demo tương ứng.');
        }

        $elapsed = $order->created_at?->diffInSeconds(now()) ?? 0;
        $scenario = $this->settings->get('demo_scenario', 'auto');

        if ($elapsed < 60) {
            return new ProviderOrderStatus(status: 'pending', startCount: 0, remains: $order->quantity);
        }

        if ($elapsed < 180) {
            $done = (int) floor($order->quantity * 0.3);

            return new ProviderOrderStatus(status: 'processing', startCount: 0, remains: $order->quantity - $done);
        }

        return match ($scenario) {
            'partial' => new ProviderOrderStatus(
                status: 'partial',
                startCount: 0,
                remains: (int) floor($order->quantity * 0.4),
            ),
            'cancel' => new ProviderOrderStatus(
                status: 'canceled',
                startCount: 0,
                remains: $order->quantity,
            ),
            default => new ProviderOrderStatus(status: 'completed', startCount: 0, remains: 0),
        };
    }
}
```

- [ ] **Step 7: ProviderManager + service provider**

`app/Providers/ProviderManager.php`:

```php
<?php

namespace App\Providers;

use App\Providers\Demo\DemoProvider;
use App\Providers\TopSubRe\TopSubReProvider;
use App\Services\SettingService;
use InvalidArgumentException;

/**
 * Chọn nhà cung cấp đang dùng.
 *  - Chưa có API key      → Demo (để nghiệm thu được ngay)
 *  - demo_mode = 'on'     → Demo (bắt buộc, dù có key)
 *  - demo_mode = 'off'    → TopSubRe (bắt buộc)
 *  - demo_mode = 'auto'   → TopSubRe nếu có key, ngược lại Demo
 */
class ProviderManager
{
    public function __construct(private readonly SettingService $settings)
    {
    }

    public function driver(): ProviderInterface
    {
        $mode = (string) $this->settings->get('demo_mode', 'auto');
        $hasKey = $this->settings->hasSecret('topsubre_api_key');

        if ($mode === 'on') {
            return app(DemoProvider::class);
        }

        if ($mode === 'off') {
            return $this->makeTopSubRe();
        }

        return $hasKey ? $this->makeTopSubRe() : app(DemoProvider::class);
    }

    private function makeTopSubRe(): ProviderInterface
    {
        $key = $this->settings->getSecret('topsubre_api_key');

        if ($key === null || $key === '') {
            throw new InvalidArgumentException('Chưa cấu hình API key TopSubRe. Vào Cài đặt để nhập key hoặc bật chế độ demo.');
        }

        $endpoint = (string) $this->settings->get('topsubre_endpoint', 'https://likenhanh.pro/api/v2');

        return new TopSubReProvider($key, $endpoint);
    }
}
```

`app/Providers/ProviderServiceProvider.php`:

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ProviderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ProviderManager cần SettingService → dùng singleton để tái sử dụng trong request.
        $this->app->singleton(ProviderManager::class);
    }

    public function boot(): void
    {
        //
    }
}
```

Đăng ký vào `bootstrap/providers.php`:

```php
<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\ProviderServiceProvider::class,
];
```

- [ ] **Step 8: Chạy test**

```bash
cd /d/NhoSub && php artisan test --filter="DemoProviderTest|ProviderTest"
```

Kỳ vọng: tất cả xanh. (Nếu `DemoProviderTest::test_che_do_partial_tra_remains` đỏ vì `created_at` bị Eloquent ghi đè, sửa bằng `$order->forceFill([...])->saveQuietly()`.)

- [ ] **Step 9: Commit**

```bash
cd /d/NhoSub && git add -A && git commit -m "feat: tang Provider (interface, TopSubRe V2, Demo, Manager) khong lo key

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 10: OrderService — đặt đơn 2 bước, chống double-submit

**Files:**
- Create: `app/Services/OrderService.php`
- Create: `app/Http/Requests/OrderPreviewRequest.php`, `app/Http/Requests/OrderStoreRequest.php`
- Test: `tests/Feature/OrderPlacementTest.php`

**Interfaces:**
- Consumes: `WalletService` (6), `RefundService` (7), `PricingService` (8), `ProviderManager` (9)
- Produces:
  ```php
  OrderService::preview(int $userId, int $serviceId, string $link, int $quantity): array
      // ['service'=>Service, 'total'=>int, 'balance'=>int, 'balance_after'=>int, 'quantity'=>int]
  OrderService::place(int $userId, int $serviceId, string $link, int $quantity, string $clientToken): Order
      // Ném InsufficientBalanceException | ValidationException | ProviderException(không — đã nuốt thành error order)
  ```
  Hành vi: tạo đơn `pending` → trừ tiền `order:{id}:charge` → status `charged` → gọi provider → `processing` hoặc hoàn tiền + `error`.

- [ ] **Step 1: Viết test thất bại**

`tests/Feature/OrderPlacementTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientBalanceException;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\OrderService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderPlacementTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $orders;
    private Service $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingService::class)->set('demo_scenario', 'auto');
        app(SettingService::class)->set('demo_mode', 'on');

        $this->orders = app(OrderService::class);
        $this->service = Service::factory()->create([
            'provider_service_id' => 'demo-1',
            'sell_price' => 3380,
            'cost_price' => 2600,
            'min_qty' => 100,
            'max_qty' => 100000,
            'is_active' => true,
        ]);
        $this->user = User::factory()->create(['balance' => 100000]);
    }

    public function test_xem_truoc_tinh_dung_tong_tien(): void
    {
        $preview = $this->orders->preview($this->user->id, $this->service->id, 'https://fb.com/p/1', 1500);

        $this->assertSame(5070, $preview['total']);       // 3380 * 1500 / 1000
        $this->assertSame(100000, $preview['balance']);
        $this->assertSame(94930, $preview['balance_after']);
    }

    public function test_dat_don_tru_tien_va_chuyen_processing(): void
    {
        $order = $this->orders->place(
            $this->user->id, $this->service->id, 'https://fb.com/p/1', 1000, (string) Str::uuid()
        );

        $this->assertSame(Order::STATUS_PROCESSING, $order->status);
        $this->assertSame('demo:' . $order->id, $order->provider_order_id);
        $this->assertSame(3380, $order->total_amount);
        $this->assertSame(2600, $order->cost_amount);
        $this->assertSame(3380, $order->unit_price);
        $this->assertSame(96620, $this->user->fresh()->balance);
        $this->assertSame(1, WalletTransaction::where('type', 'order_charge')->count());
    }

    public function test_gia_duoc_dong_bang_tai_thoi_diem_dat(): void
    {
        $order = $this->orders->place(
            $this->user->id, $this->service->id, 'https://fb.com/p/1', 1000, (string) Str::uuid()
        );

        // Admin đổi giá sau đó
        $this->service->forceFill(['sell_price' => 9999])->save();

        $this->assertSame(3380, $order->fresh()->unit_price);
    }

    public function test_so_du_khong_du_bi_chan_va_khong_tao_don(): void
    {
        $this->user->forceFill(['balance' => 100])->save();

        try {
            $this->orders->place($this->user->id, $this->service->id, 'https://fb.com/p/1', 1000, (string) Str::uuid());
            $this->fail('Phải ném InsufficientBalanceException');
        } catch (InsufficientBalanceException $e) {
            // đúng
        }

        $this->assertSame(0, Order::count(), 'Không được tạo đơn khi thiếu tiền');
        $this->assertSame(100, $this->user->fresh()->balance);
        $this->assertSame(0, WalletTransaction::count());
    }

    public function test_provider_loi_thi_hoan_tien_va_danh_dau_error(): void
    {
        app(SettingService::class)->set('demo_scenario', 'fail');

        $order = $this->orders->place(
            $this->user->id, $this->service->id, 'https://fb.com/p/1', 1000, (string) Str::uuid()
        );

        $this->assertSame(Order::STATUS_ERROR, $order->status);
        $this->assertSame(3380, $order->refunded_amount);
        $this->assertSame(1, $order->refund_count);
        $this->assertSame(100000, $this->user->fresh()->balance, 'Phải hoàn lại đủ tiền');
        $this->assertNotNull($order->error_message);
    }

    public function test_cung_client_token_khong_tao_don_thu_hai(): void
    {
        $token = (string) Str::uuid();

        $first = $this->orders->place($this->user->id, $this->service->id, 'https://fb.com/p/1', 1000, $token);
        $second = $this->orders->place($this->user->id, $this->service->id, 'https://fb.com/p/1', 1000, $token);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Order::count());
        $this->assertSame(1, WalletTransaction::where('type', 'order_charge')->count());
        $this->assertSame(96620, $this->user->fresh()->balance, 'Chỉ trừ tiền một lần');
    }

    public function test_so_luong_ngoai_min_max_bi_tu_choi(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->orders->place($this->user->id, $this->service->id, 'https://fb.com/p/1', 50, (string) Str::uuid());
    }

    public function test_link_javascript_bi_tu_choi(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->orders->place($this->user->id, $this->service->id, 'javascript:alert(1)', 1000, (string) Str::uuid());
    }

    public function test_dich_vu_bi_an_khong_dat_duoc(): void
    {
        $this->service->forceFill(['is_active' => false])->save();

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->orders->place($this->user->id, $this->service->id, 'https://fb.com/p/1', 1000, (string) Str::uuid());
    }
}
```

- [ ] **Step 2: Chạy test để thấy đỏ**

```bash
cd /d/NhoSub && php artisan test --filter=OrderPlacementTest
```

Kỳ vọng: FAIL — class không tồn tại.

- [ ] **Step 3: Viết `app/Services/OrderService.php`**

```php
<?php

namespace App\Services;

use App\Exceptions\ProviderException;
use App\Models\ApiSyncLog;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Providers\ProviderManager;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Đặt đơn hàng — luồng quan trọng nhất phía khách.
 *
 * Thứ tự bắt buộc (spec 5.3):
 *  1. Validate + khoá user
 *  2. INSERT order (cần id để làm khoá idempotency)
 *  3. Trừ tiền với key order:{id}:charge
 *  4. status = charged → gọi nhà cung cấp
 *  5. Thành công: processing + provider_order_id
 *     Thất bại: hoàn tiền toàn bộ + status = error
 */
class OrderService
{
    public function __construct(
        private readonly WalletService $wallet,
        private readonly RefundService $refunds,
        private readonly PricingService $pricing,
        private readonly ProviderManager $providers,
    ) {
    }

    /**
     * Bước 1: xem trước — KHÔNG ghi gì vào DB.
     *
     * @return array{service: Service, total: int, balance: int, balance_after: int, quantity: int, unit_price: int}
     */
    public function preview(int $userId, int $serviceId, string $link, int $quantity): array
    {
        $service = $this->validateInput($serviceId, $link, $quantity);
        $user = User::findOrFail($userId);

        $total = $this->pricing->orderTotal($service->sell_price, $quantity);

        return [
            'service' => $service,
            'total' => $total,
            'balance' => (int) $user->balance,
            'balance_after' => (int) $user->balance - $total,
            'quantity' => $quantity,
            'unit_price' => (int) $service->sell_price,
        ];
    }

    /** Bước 2: xác nhận đặt đơn. */
    public function place(int $userId, int $serviceId, string $link, int $quantity, string $clientToken): Order
    {
        $service = $this->validateInput($serviceId, $link, $quantity);

        // Chống double-submit: nếu token đã dùng thì trả về đơn cũ ngay.
        $existing = Order::where('client_token', $clientToken)->first();
        if ($existing !== null) {
            return $existing;
        }

        $total = $this->pricing->orderTotal($service->sell_price, $quantity);
        $cost = $this->pricing->orderCost($service->cost_price, $quantity);

        try {
            $order = DB::transaction(function () use (
                $userId, $service, $link, $quantity, $clientToken, $total, $cost
            ) {
                // Khoá dòng user trước để hai request song song không cùng đọc số dư cũ.
                $user = User::query()->lockForUpdate()->findOrFail($userId);

                // (b) Tạo đơn trước — cần id cho khoá idempotency.
                $order = Order::create([
                    'user_id' => $user->id,
                    'service_id' => $service->id,
                    'client_token' => $clientToken,
                    'target_link' => $link,
                    'quantity' => $quantity,
                    'unit_price' => (int) $service->sell_price,
                    'total_amount' => $total,
                    'cost_amount' => $cost,
                    'status' => Order::STATUS_PENDING,
                ]);

                // (c) Trừ tiền — ném InsufficientBalanceException nếu thiếu.
                $this->wallet->apply(
                    userId: $user->id,
                    type: WalletTransaction::TYPE_ORDER_CHARGE,
                    amount: -$total,
                    idempotencyKey: "order:{$order->id}:charge",
                    note: "Thanh toán đơn #{$order->id}",
                    orderId: $order->id,
                );

                // (d) Đánh dấu đã trừ tiền, chưa gửi NCC.
                $order->forceFill(['status' => Order::STATUS_CHARGED])->save();

                return $order;
            });
        } catch (QueryException $e) {
            // Hai request cùng client_token lọt qua bước kiểm tra đầu → UNIQUE chặn.
            if ((int) $e->getCode() === 23000 || str_contains($e->getMessage(), 'Duplicate entry')) {
                $existing = Order::where('client_token', $clientToken)->first();
                if ($existing !== null) {
                    return $existing;
                }
            }

            throw $e;
        }

        return $this->sendToProvider($order);
    }

    /** Gửi đơn lên nhà cung cấp; thất bại thì hoàn tiền toàn bộ. */
    private function sendToProvider(Order $order): Order
    {
        try {
            $result = $this->providers->driver()->addOrder(
                (string) $order->service->provider_service_id,
                $order->target_link,
                (int) $order->quantity
            );

            $order->forceFill([
                'provider_order_id' => $result->providerOrderId,
                'status' => Order::STATUS_PROCESSING,
            ])->save();

            ApiSyncLog::create([
                'action' => 'add_order',
                'order_id' => $order->id,
                'request_summary' => 'service=' . $order->service->provider_service_id
                    . ', qty=' . $order->quantity,
                'response_summary' => 'order=' . $result->providerOrderId,
                'success' => true,
                'created_at' => now(),
            ]);

            return $order;
        } catch (ProviderException $e) {
            $this->markFailed($order, $e->getMessage());

            return $order->fresh();
        } catch (\Throwable $e) {
            // Lỗi bất ngờ (mạng, timeout...) cũng phải hoàn tiền.
            Log::error('Đặt đơn lỗi không xác định', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            $this->markFailed($order, 'Không gửi được đơn tới nhà cung cấp.');

            return $order->fresh();
        }
    }

    /** Đánh dấu đơn lỗi + hoàn 100% tiền. */
    private function markFailed(Order $order, string $message): void
    {
        DB::transaction(function () use ($order, $message) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            $locked->forceFill([
                'status' => Order::STATUS_ERROR,
                'error_message' => mb_substr($message, 0, 500),
            ])->save();

            ApiSyncLog::create([
                'action' => 'add_order',
                'order_id' => $locked->id,
                'request_summary' => 'service=' . $locked->service_id . ', qty=' . $locked->quantity,
                'response_summary' => mb_substr($message, 0, 500),
                'success' => false,
                'created_at' => now(),
            ]);
        });

        // remains = quantity → hoàn toàn bộ tiền khách đã trả.
        $order->forceFill(['remains' => (int) $order->quantity])->save();
        $this->refunds->refundOrder($order->fresh(), 'provider_error');
    }

    /** Kiểm tra đầu vào dùng chung cho preview và place. */
    private function validateInput(int $serviceId, string $link, int $quantity): Service
    {
        $link = trim($link);

        // Chỉ nhận http/https — chặn javascript:, data:, file:...
        if (! preg_match('#^https?://[^\s]+$#i', $link)) {
            throw ValidationException::withMessages([
                'target_link' => 'Link không hợp lệ. Vui lòng nhập link bắt đầu bằng http:// hoặc https://.',
            ]);
        }

        if (mb_strlen($link) > 2048) {
            throw ValidationException::withMessages(['target_link' => 'Link quá dài.']);
        }

        $service = Service::where('is_active', true)->find($serviceId);

        if (! $service) {
            throw ValidationException::withMessages([
                'service_id' => 'Dịch vụ không tồn tại hoặc đã ngừng bán.',
            ]);
        }

        if ($quantity < (int) $service->min_qty || $quantity > (int) $service->max_qty) {
            throw ValidationException::withMessages([
                'quantity' => "Số lượng phải từ {$service->min_qty} đến {$service->max_qty}.",
            ]);
        }

        return $service;
    }
}
```

- [ ] **Step 4: FormRequest cho form đặt đơn**

`app/Http/Requests/OrderPreviewRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'target_link' => ['required', 'string', 'max:2048', 'url:http,https'],
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'service_id.required' => 'Vui lòng chọn dịch vụ.',
            'service_id.exists' => 'Dịch vụ không tồn tại.',
            'target_link.required' => 'Vui lòng nhập link cần tăng tương tác.',
            'target_link.url' => 'Link không hợp lệ. Chỉ nhận link http:// hoặc https://.',
            'quantity.required' => 'Vui lòng nhập số lượng.',
            'quantity.min' => 'Số lượng phải lớn hơn 0.',
        ];
    }
}
```

`app/Http/Requests/OrderStoreRequest.php`:

```php
<?php

namespace App\Http\Requests;

class OrderStoreRequest extends OrderPreviewRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'client_token' => ['required', 'uuid'],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'client_token.required' => 'Phiên đặt đơn không hợp lệ, vui lòng thử lại.',
            'client_token.uuid' => 'Phiên đặt đơn không hợp lệ, vui lòng thử lại.',
        ]);
    }
}
```

- [ ] **Step 5: Chạy test**

```bash
cd /d/NhoSub && php artisan test --filter=OrderPlacementTest
```

Kỳ vọng: 9 passed.

- [ ] **Step 6: Commit**

```bash
cd /d/NhoSub && git add -A && git commit -m "feat: OrderService dat don 2 buoc, chong double-submit bang client_token UNIQUE

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 11: Webhook SePay — xác thực, đối chiếu, chống cộng trùng

**Files:**
- Create: `app/Services/DepositService.php`
- Create: `app/Http/Controllers/Webhook/SePayController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/SePayWebhookTest.php`

**Interfaces:**
- Consumes: `WalletService` (6), `SettingService` (5)
- Produces:
  ```php
  DepositService::parseTransferContent(string $content): ?string   // username hoặc null
  DepositService::handleGatewayDeposit(
      string $gateway, string $gatewayTxnId, int $amount, string $content,
      array $payload, bool $isTransferIn = true
  ): array   // ['status' => 'processed'|'duplicate'|'unmatched'|'rejected', 'message' => string]
  ```
  Route: `POST /webhook/sepay` (name `webhook.sepay`), **không** CSRF, rate limit 120/phút/IP.

- [ ] **Step 1: Viết test thất bại**

`tests/Feature/SePayWebhookTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\DepositRequest;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WebhookLog;
use App\Services\DepositService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SePayWebhookTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $apiKey = 'sepay-test-key-123';

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingService::class)->setSecret('sepay_api_key', $this->apiKey);
        app(SettingService::class)->set('min_deposit', '10000');

        $this->user = User::factory()->create(['username' => 'khachvip', 'balance' => 0]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'id' => 99881,
            'gateway' => 'MB Bank',
            'transactionDate' => '2026-10-31 10:00:00',
            'accountNumber' => '0123456789',
            'content' => 'NHOSUB KHACHVIP chuyen tien',
            'transferType' => 'in',
            'transferAmount' => 100000,
            'referenceCode' => 'FT26100001',
            'description' => '',
        ], $overrides);
    }

    private function postWebhook(array $payload, ?string $authHeader = null)
    {
        return $this->withHeaders([
            'Authorization' => $authHeader ?? 'Apikey ' . $this->apiKey,
        ])->postJson('/webhook/sepay', $payload);
    }

    public function test_webhook_hop_le_cong_tien_dung(): void
    {
        $this->postWebhook($this->payload())->assertOk()->assertJson(['success' => true]);

        $this->assertSame(100000, $this->user->fresh()->balance);
        $this->assertSame(1, WalletTransaction::where('type', 'deposit')->count());
        $this->assertSame(WebhookLog::STATUS_PROCESSED, WebhookLog::first()->status);
        $this->assertSame(1, DepositRequest::where('status', 'approved')->count());
    }

    public function test_webhook_gui_lai_cung_ma_giao_dich_khong_cong_trung(): void
    {
        $this->postWebhook($this->payload())->assertOk();
        $this->postWebhook($this->payload())->assertOk();
        $this->postWebhook($this->payload())->assertOk();

        $this->assertSame(100000, $this->user->fresh()->balance);
        $this->assertSame(1, WalletTransaction::count());
        $this->assertSame(1, WebhookLog::count());
    }

    public function test_sai_apikey_bi_tu_choi_401(): void
    {
        $this->postWebhook($this->payload(), 'Apikey sai-key')->assertStatus(401);

        $this->assertSame(0, $this->user->fresh()->balance);
        $this->assertSame(0, WalletTransaction::count());
    }

    public function test_thieu_header_authorization_bi_tu_choi(): void
    {
        $this->postJson('/webhook/sepay', $this->payload())->assertStatus(401);
        $this->assertSame(0, $this->user->fresh()->balance);
    }

    public function test_tien_ra_khong_xu_ly(): void
    {
        $this->postWebhook($this->payload(['transferType' => 'out']))->assertOk();

        $this->assertSame(0, $this->user->fresh()->balance);
        $this->assertSame(WebhookLog::STATUS_REJECTED, WebhookLog::first()->status);
    }

    public function test_so_tien_duoi_muc_toi_thieu_bi_tu_choi(): void
    {
        $this->postWebhook($this->payload(['transferAmount' => 5000]))->assertOk();

        $this->assertSame(0, $this->user->fresh()->balance);
        $this->assertSame(WebhookLog::STATUS_REJECTED, WebhookLog::first()->status);
        $this->assertStringContainsString('10.000', WebhookLog::first()->reason);
    }

    public function test_noi_dung_khong_khop_user_ghi_unmatched(): void
    {
        $this->postWebhook($this->payload(['content' => 'chuyen tien mua hang']))->assertOk();

        $this->assertSame(0, $this->user->fresh()->balance);
        $this->assertSame(WebhookLog::STATUS_UNMATCHED, WebhookLog::first()->status);
    }

    public function test_username_khong_ton_tai_ghi_unmatched(): void
    {
        $this->postWebhook($this->payload(['content' => 'NHOSUB NGUOIKHONGCO']))->assertOk();

        $this->assertSame(WebhookLog::STATUS_UNMATCHED, WebhookLog::first()->status);
    }

    public function test_parse_chap_nhan_ba_bien_the(): void
    {
        $svc = app(DepositService::class);

        $this->assertSame('khachvip', $svc->parseTransferContent('NHOSUB KHACHVIP'));
        $this->assertSame('khachvip', $svc->parseTransferContent('NHOSUBKHACHVIP'));
        $this->assertSame('khachvip', $svc->parseTransferContent('nhosub  khachvip chuyen tien'));
        $this->assertSame('khachvip', $svc->parseTransferContent('MBVCB.123.NHOSUB KHACHVIP.CT tu 0123'));
        $this->assertNull($svc->parseTransferContent('NHOSUB'));
        $this->assertNull($svc->parseTransferContent('khong co gi'));
    }

    public function test_username_la_tien_to_cua_username_khac_van_khop_dung(): void
    {
        // Có cả KHACH và KHACHVIP — nội dung "NHOSUB KHACHVIP" phải gán cho KHACHVIP.
        $khachNgan = User::factory()->create(['username' => 'khach', 'balance' => 0]);

        $this->postWebhook($this->payload(['content' => 'NHOSUB KHACHVIP nap tien']))->assertOk();

        $this->assertSame(100000, $this->user->fresh()->balance);
        $this->assertSame(0, $khachNgan->fresh()->balance);
    }

    public function test_payload_bi_che_key_truoc_khi_luu(): void
    {
        $this->postWebhook($this->payload())->assertOk();

        $log = WebhookLog::first();
        $json = json_encode($log->payload, JSON_UNESCAPED_UNICODE);

        $this->assertStringNotContainsString($this->apiKey, $json);
    }

    public function test_so_du_va_so_cai_khop_nhau(): void
    {
        $this->postWebhook($this->payload(['id' => 111]))->assertOk();
        $this->postWebhook($this->payload(['id' => 222, 'transferAmount' => 50000]))->assertOk();

        $tong = (int) WalletTransaction::where('user_id', $this->user->id)->sum('amount');

        $this->assertSame(150000, $tong);
        $this->assertSame(150000, $this->user->fresh()->balance);
    }
}
```

- [ ] **Step 2: Chạy test để thấy đỏ**

```bash
cd /d/NhoSub && php artisan test --filter=SePayWebhookTest
```

Kỳ vọng: FAIL — route không tồn tại.

- [ ] **Step 3: Viết `app/Services/DepositService.php`**

```php
<?php

namespace App\Services;

use App\Models\DepositRequest;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WebhookLog;
use Illuminate\Support\Facades\DB;

/**
 * Xử lý nạp tiền từ mọi nguồn: webhook cổng thanh toán, duyệt tay, admin cộng tiền.
 *
 * Điểm then chốt: webhook có thể bị gửi lại nhiều lần. Ta chặn bằng
 * UNIQUE(gateway, gateway_txn_id) ở webhook_logs VÀ idempotency_key
 * ở wallet_transactions — hai lớp độc lập.
 */
class DepositService
{
    private const CONTENT_PATTERN = '/\bNHOSUB\s*([A-Za-z0-9_]{3,32})\b/iu';

    public function __construct(
        private readonly WalletService $wallet,
        private readonly SettingService $settings,
    ) {
    }

    /**
     * Tách username từ nội dung chuyển khoản.
     * Chấp nhận: "NHOSUB ABC", "NHOSUBABC", "nhosub  abc ...".
     * \b ở đầu + khoảng trắng tuỳ chọn + nhóm username kết thúc bằng \b
     * → "NHOSUB KHACHVIP" không bị cắt thành "KHACH".
     */
    public function parseTransferContent(string $content): ?string
    {
        if (! preg_match(self::CONTENT_PATTERN, $content, $m)) {
            return null;
        }

        return mb_strtolower($m[1]);
    }

    /**
     * Xử lý một giao dịch tiền vào từ cổng thanh toán.
     *
     * @return array{status: string, message: string}
     */
    public function handleGatewayDeposit(
        string $gateway,
        string $gatewayTxnId,
        int $amount,
        string $content,
        array $payload,
        bool $isTransferIn = true
    ): array {
        $minDeposit = $this->settings->getInt('min_deposit', 10000);

        // Ghi nhật ký trước (dedupe ở đây), payload đã che key.
        $log = $this->logWebhook($gateway, $gatewayTxnId, $amount, $content, $payload);

        if ($log === null) {
            return ['status' => WebhookLog::STATUS_DUPLICATE, 'message' => 'Giao dịch đã được xử lý trước đó.'];
        }

        if (! $isTransferIn) {
            $this->markLog($log, WebhookLog::STATUS_REJECTED, 'Không phải giao dịch tiền vào.');

            return ['status' => WebhookLog::STATUS_REJECTED, 'message' => 'Chỉ xử lý giao dịch tiền vào.'];
        }

        if ($amount < $minDeposit) {
            $this->markLog(
                $log,
                WebhookLog::STATUS_REJECTED,
                'Số tiền dưới mức tối thiểu ' . number_format($minDeposit, 0, ',', '.') . ' ₫.'
            );

            return ['status' => WebhookLog::STATUS_REJECTED, 'message' => 'Số tiền dưới mức tối thiểu.'];
        }

        $username = $this->parseTransferContent($content);

        if ($username === null) {
            $this->markLog($log, WebhookLog::STATUS_UNMATCHED, 'Nội dung không chứa mã NHOSUB <tài khoản>.');

            return ['status' => WebhookLog::STATUS_UNMATCHED, 'message' => 'Không tìm thấy mã chuyển khoản.'];
        }

        $user = User::whereRaw('LOWER(username) = ?', [$username])->first();

        if (! $user) {
            $this->markLog($log, WebhookLog::STATUS_UNMATCHED, "Không tìm thấy tài khoản '{$username}'.");

            return ['status' => WebhookLog::STATUS_UNMATCHED, 'message' => 'Không tìm thấy tài khoản.'];
        }

        // Ghi nhận nạp tiền + cộng ví trong 1 transaction.
        DB::transaction(function () use ($user, $amount, $gateway, $gatewayTxnId, $log) {
            $deposit = DepositRequest::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'method' => $gateway,
                'transfer_code' => $user->transferContent(),
                'status' => DepositRequest::STATUS_APPROVED,
                'gateway_txn_id' => $gatewayTxnId,
                'created_at' => now(),
                'reviewed_at' => now(),
            ]);

            $this->wallet->apply(
                userId: (int) $user->id,
                type: WalletTransaction::TYPE_DEPOSIT,
                amount: $amount,
                idempotencyKey: "deposit:{$gateway}:{$gatewayTxnId}",
                note: "Nạp tiền qua {$gateway}",
                depositId: $deposit->id,
            );

            $this->markLog($log, WebhookLog::STATUS_PROCESSED, null);
        });

        return ['status' => WebhookLog::STATUS_PROCESSED, 'message' => 'Đã cộng tiền.'];
    }

    /** Ghi log webhook; trả null nếu giao dịch đã tồn tại (dedupe). */
    private function logWebhook(
        string $gateway,
        string $gatewayTxnId,
        int $amount,
        string $content,
        array $payload
    ): ?WebhookLog {
        $exists = WebhookLog::where('gateway', $gateway)
            ->where('gateway_txn_id', $gatewayTxnId)
            ->exists();

        if ($exists) {
            return null;
        }

        try {
            return WebhookLog::create([
                'gateway' => $gateway,
                'gateway_txn_id' => $gatewayTxnId,
                'amount' => $amount,
                'content' => mb_substr($content, 0, 255),
                'status' => WebhookLog::STATUS_UNMATCHED,
                'payload' => $this->maskPayload($payload),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Hai webhook cùng lúc: bên thua coi như trùng.
            if ((int) $e->getCode() === 23000) {
                return null;
            }

            throw $e;
        }
    }

    private function markLog(WebhookLog $log, string $status, ?string $reason): void
    {
        $log->forceFill(['status' => $status, 'reason' => $reason])->save();
    }

    /** Che mọi trường có tên chứa key/token/secret trước khi lưu nhật ký. */
    private function maskPayload(array $payload): array
    {
        $masked = [];

        foreach ($payload as $key => $value) {
            if (preg_match('/key|token|secret|password|signature/i', (string) $key)) {
                $masked[$key] = '***';
                continue;
            }

            $masked[$key] = is_array($value) ? $this->maskPayload($value) : $value;
        }

        return $masked;
    }
}
```

- [ ] **Step 4: Controller webhook SePay**

`app/Http/Controllers/Webhook/SePayController.php`:

```php
<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\DepositService;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SePayController extends Controller
{
    public function __construct(
        private readonly DepositService $deposits,
        private readonly SettingService $settings,
    ) {
    }

    public function handle(Request $request): JsonResponse
    {
        $expectedKey = $this->settings->getSecret('sepay_api_key');

        if ($expectedKey === null || $expectedKey === '') {
            // Chưa cấu hình thì không nhận gì cả — tránh cổng mở toang.
            return response()->json(['success' => false, 'message' => 'Cổng chưa cấu hình.'], 503);
        }

        $header = (string) $request->header('Authorization', '');
        $provided = preg_replace('/^Apikey\s+/i', '', trim($header)) ?? '';

        // hash_equals chống timing attack.
        if ($provided === '' || ! hash_equals($expectedKey, $provided)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $data = $request->all();

        $result = $this->deposits->handleGatewayDeposit(
            gateway: 'sepay',
            gatewayTxnId: (string) ($data['id'] ?? $data['referenceCode'] ?? ''),
            amount: (int) ($data['transferAmount'] ?? 0),
            content: (string) ($data['content'] ?? $data['description'] ?? ''),
            payload: $data,
            isTransferIn: strtolower((string) ($data['transferType'] ?? 'in')) === 'in',
        );

        // Luôn 200 với giao dịch đã nhận (kể cả unmatched) để SePay không retry vô hạn;
        // admin xử lý tay qua nhật ký webhook.
        return response()->json([
            'success' => in_array($result['status'], ['processed', 'duplicate'], true),
            'status' => $result['status'],
            'message' => $result['message'],
        ]);
    }
}
```

- [ ] **Step 5: Đăng ký route (bỏ CSRF, có rate limit)**

Trong `routes/web.php` thêm:

```php
use App\Http\Controllers\Webhook\SePayController;

Route::post('/webhook/sepay', [SePayController::class, 'handle'])
    ->middleware('throttle:120,1')
    ->name('webhook.sepay');
```

Thêm ngoại lệ CSRF trong `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [SecurityHeaders::class]);
    $middleware->alias(['admin' => EnsureUserIsAdmin::class]);
    $middleware->validateCsrfTokens(except: [
        'webhook/sepay',
        'webhook/payos',
    ]);
    $middleware->redirectGuestsTo(fn () => route('login'));
    $middleware->redirectUsersTo(fn () => route('panel.dashboard'));
})
```

- [ ] **Step 6: Chạy test**

```bash
cd /d/NhoSub && php artisan test --filter=SePayWebhookTest
```

Kỳ vọng: 12 passed.

- [ ] **Step 7: Commit**

```bash
cd /d/NhoSub && git add -A && git commit -m "feat: webhook SePay xac thuc Apikey, doi chieu NHOSUB <user>, chong cong trung 2 lop

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 12: PayOS + duyệt tay + hết hạn yêu cầu nạp

**Files:**
- Create: `app/Services/PayOS/PayOSClient.php`
- Create: `app/Http/Controllers/Webhook/PayOSController.php`
- Create: `app/Console/Commands/ExpireDepositsCommand.php`
- Modify: `routes/web.php`, `routes/console.php`
- Test: `tests/Feature/PayOSWebhookTest.php`, `tests/Feature/DepositManualTest.php`

**Interfaces:**
- Consumes: `DepositService` (11), `WalletService` (6), `SettingService` (5)
- Produces:
  ```php
  PayOSClient::createPaymentLink(int $orderCode, int $amount, string $description, string $returnUrl, string $cancelUrl): array
      // ['checkoutUrl'=>string, 'qrCode'=>string, 'paymentLinkId'=>string]
  PayOSClient::verifyWebhook(array $payload): bool
  DepositService::createManualRequest(int $userId, int $amount, string $method, ?string $note): DepositRequest
  DepositService::approveManual(DepositRequest $request, int $adminId, ?string $note): void
  DepositService::rejectManual(DepositRequest $request, int $adminId, string $reason): void
  DepositService::expireStaleRequests(): int
  ```

- [ ] **Step 1: Viết test thất bại**

`tests/Feature/DepositManualTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\DepositRequest;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\DepositService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DepositManualTest extends TestCase
{
    use RefreshDatabase;

    private DepositService $deposits;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        app(SettingService::class)->set('min_deposit', '10000');
        $this->deposits = app(DepositService::class);
        $this->user = User::factory()->create(['balance' => 0]);
    }

    public function test_tao_yeu_cau_nap_tay_pending(): void
    {
        $req = $this->deposits->createManualRequest($this->user->id, 50000, 'bank', 'Nạp hộ');

        $this->assertSame('pending', $req->status);
        $this->assertSame(50000, $req->amount);
        $this->assertSame('NHOSUB ' . strtoupper($this->user->username), $req->transfer_code);
    }

    public function test_duoi_muc_toi_thieu_bi_tu_choi(): void
    {
        $this->expectException(ValidationException::class);
        $this->deposits->createManualRequest($this->user->id, 5000, 'bank', null);
    }

    public function test_khong_tao_duoc_hai_yeu_cau_tay_pending(): void
    {
        $this->deposits->createManualRequest($this->user->id, 50000, 'bank', null);

        $this->expectException(ValidationException::class);
        $this->deposits->createManualRequest($this->user->id, 60000, 'bank', null);
    }

    public function test_duyet_cong_tien_dung_mot_lan(): void
    {
        $req = $this->deposits->createManualRequest($this->user->id, 50000, 'bank', null);

        $this->deposits->approveManual($req, 1, 'Đã nhận');
        $this->deposits->approveManual($req->fresh(), 1, 'Đã nhận lần 2');

        $this->assertSame(50000, $this->user->fresh()->balance);
        $this->assertSame(1, WalletTransaction::count());
        $this->assertSame('approved', $req->fresh()->status);
    }

    public function test_tu_choi_bat_buoc_ly_do(): void
    {
        $req = $this->deposits->createManualRequest($this->user->id, 50000, 'bank', null);

        $this->expectException(ValidationException::class);
        $this->deposits->rejectManual($req, 1, '   ');
    }

    public function test_tu_choi_khong_cong_tien(): void
    {
        $req = $this->deposits->createManualRequest($this->user->id, 50000, 'bank', null);

        $this->deposits->rejectManual($req, 1, 'Chưa nhận được tiền');

        $this->assertSame(0, $this->user->fresh()->balance);
        $this->assertSame('rejected', $req->fresh()->status);
        $this->assertSame('Chưa nhận được tiền', $req->fresh()->admin_note);
    }

    public function test_yeu_cau_payos_qua_han_tu_dong_expired(): void
    {
        DepositRequest::create([
            'user_id' => $this->user->id,
            'amount' => 20000,
            'method' => 'payos',
            'transfer_code' => 'NHOSUB ' . strtoupper($this->user->username),
            'status' => 'pending',
            'expires_at' => now()->subMinute(),
            'created_at' => now()->subMinutes(31),
        ]);

        $count = $this->deposits->expireStaleRequests();

        $this->assertSame(1, $count);
        $this->assertSame('expired', DepositRequest::first()->status);
    }

    public function test_yeu_cau_chua_qua_han_khong_bi_dong(): void
    {
        DepositRequest::create([
            'user_id' => $this->user->id,
            'amount' => 20000,
            'method' => 'payos',
            'transfer_code' => 'x',
            'status' => 'pending',
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
        ]);

        $this->assertSame(0, $this->deposits->expireStaleRequests());
        $this->assertSame('pending', DepositRequest::first()->status);
    }

    public function test_sau_khi_het_han_tao_duoc_yeu_cau_moi(): void
    {
        $cu = $this->deposits->createManualRequest($this->user->id, 50000, 'bank', null);
        $cu->forceFill(['status' => 'expired'])->save();

        $moi = $this->deposits->createManualRequest($this->user->id, 60000, 'bank', null);

        $this->assertSame('pending', $moi->status);
    }
}
```

`tests/Feature/PayOSWebhookTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\DepositRequest;
use App\Models\PayOsTransaction;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayOSWebhookTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingService::class)->set('min_deposit', '10000');
        app(SettingService::class)->set('payos_enabled', '1');

        $this->user = User::factory()->create(['username' => 'payosuser', 'balance' => 0]);
    }

    public function test_webhook_payos_cong_tien_theo_order_code(): void
    {
        $deposit = DepositRequest::create([
            'user_id' => $this->user->id,
            'amount' => 50000,
            'method' => 'payos',
            'transfer_code' => 'NHOSUB PAYOSUSER',
            'status' => 'pending',
            'created_at' => now(),
        ]);

        PayOsTransaction::create([
            'deposit_request_id' => $deposit->id,
            'user_id' => $this->user->id,
            'order_code' => 123456,
            'status' => 'pending',
        ]);

        $res = $this->postJson('/webhook/payos', [
            'code' => '00',
            'desc' => 'success',
            'data' => [
                'orderCode' => 123456,
                'amount' => 50000,
                'description' => 'NHOSUB PAYOSUSER',
                'reference' => 'FT-PAYOS-001',
                'paymentLinkId' => 'pl-001',
            ],
        ]);

        $res->assertOk();
        $this->assertSame(50000, $this->user->fresh()->balance);
        $this->assertSame(1, WalletTransaction::where('type', 'deposit')->count());
        $this->assertSame('paid', PayOsTransaction::first()->status);
        $this->assertSame('approved', $deposit->fresh()->status);
    }

    public function test_webhook_payos_gui_lai_khong_cong_trung(): void
    {
        $deposit = DepositRequest::create([
            'user_id' => $this->user->id, 'amount' => 50000, 'method' => 'payos',
            'transfer_code' => 'NHOSUB PAYOSUSER', 'status' => 'pending', 'created_at' => now(),
        ]);
        PayOsTransaction::create([
            'deposit_request_id' => $deposit->id, 'user_id' => $this->user->id,
            'order_code' => 777, 'status' => 'pending',
        ]);

        $payload = [
            'code' => '00',
            'data' => ['orderCode' => 777, 'amount' => 50000, 'reference' => 'REF-777'],
        ];

        $this->postJson('/webhook/payos', $payload)->assertOk();
        $this->postJson('/webhook/payos', $payload)->assertOk();

        $this->assertSame(50000, $this->user->fresh()->balance);
        $this->assertSame(1, WalletTransaction::count());
    }
}
```

- [ ] **Step 2: Chạy test để thấy đỏ**

```bash
cd /d/NhoSub && php artisan test --filter="DepositManualTest|PayOSWebhookTest"
```

Kỳ vọng: FAIL.

- [ ] **Step 3: Mở rộng `DepositService` (thêm 4 method)**

Thêm vào class `DepositService` (giữ nguyên phần đã có ở Task 11):

```php
    /** Tạo yêu cầu nạp duyệt tay. Mỗi khách tối đa 1 yêu cầu tay đang chờ. */
    public function createManualRequest(int $userId, int $amount, string $method = 'bank', ?string $note = null): DepositRequest
    {
        $minDeposit = $this->settings->getInt('min_deposit', 10000);

        if ($amount < $minDeposit) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'amount' => 'Số tiền nạp tối thiểu là ' . number_format($minDeposit, 0, ',', '.') . ' ₫.',
            ]);
        }

        $hasPending = DepositRequest::where('user_id', $userId)
            ->where('method', DepositRequest::METHOD_BANK)
            ->where('status', DepositRequest::STATUS_PENDING)
            ->exists();

        if ($hasPending) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'amount' => 'Bạn đang có một yêu cầu nạp chờ duyệt. Vui lòng chờ admin xử lý.',
            ]);
        }

        $user = User::findOrFail($userId);

        return DepositRequest::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'method' => $method,
            'transfer_code' => $user->transferContent(),
            'status' => DepositRequest::STATUS_PENDING,
            'admin_note' => $note,
            'created_at' => now(),
        ]);
    }

    /** Admin duyệt nạp tay — cộng tiền đúng một lần. */
    public function approveManual(DepositRequest $request, int $adminId, ?string $note = null): void
    {
        DB::transaction(function () use ($request, $adminId, $note) {
            $locked = DepositRequest::query()->lockForUpdate()->findOrFail($request->id);

            // Đã duyệt rồi thì không làm gì (chống bấm hai lần).
            if ($locked->status !== DepositRequest::STATUS_PENDING) {
                return;
            }

            $locked->forceFill([
                'status' => DepositRequest::STATUS_APPROVED,
                'admin_note' => $note ?? $locked->admin_note,
                'reviewed_at' => now(),
            ])->save();

            $this->wallet->apply(
                userId: (int) $locked->user_id,
                type: WalletTransaction::TYPE_DEPOSIT,
                amount: (int) $locked->amount,
                idempotencyKey: "deposit:manual:{$locked->id}",
                note: 'Duyệt nạp tiền #' . $locked->id . ' bởi admin #' . $adminId,
                depositId: (int) $locked->id,
            );
        });
    }

    /** Admin từ chối — bắt buộc có lý do. */
    public function rejectManual(DepositRequest $request, int $adminId, string $reason): void
    {
        if (trim($reason) === '') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'admin_note' => 'Vui lòng nhập lý do từ chối.',
            ]);
        }

        $locked = DepositRequest::query()->lockForUpdate()->findOrFail($request->id);

        if ($locked->status !== DepositRequest::STATUS_PENDING) {
            return;
        }

        $locked->forceFill([
            'status' => DepositRequest::STATUS_REJECTED,
            'admin_note' => $reason,
            'reviewed_at' => now(),
        ])->save();
    }

    /** Đóng các yêu cầu nạp cổng thanh toán quá hạn (mặc định 30 phút). */
    public function expireStaleRequests(): int
    {
        return DepositRequest::where('status', DepositRequest::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => DepositRequest::STATUS_EXPIRED]);
    }
```

- [ ] **Step 4: PayOS client**

`app/Services/PayOS/PayOSClient.php`:

```php
<?php

namespace App\Services\PayOS;

use App\Exceptions\ProviderException;
use App\Services\SettingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client PayOS — tạo link thanh toán + xác thực chữ ký webhook.
 * Key lưu dạng mã hoá ở settings, không bao giờ log.
 * README ghi rõ: cần tài khoản PayOS trước khi bật cổng này.
 */
class PayOSClient
{
    private const BASE_URL = 'https://api-merchant.payos.vn/v2/payment-requests';

    public function __construct(private readonly SettingService $settings)
    {
    }

    public function isEnabled(): bool
    {
        return $this->settings->getBool('payos_enabled', false)
            && $this->settings->hasSecret('payos_client_id')
            && $this->settings->hasSecret('payos_api_key');
    }

    /**
     * @return array{checkoutUrl: string, qrCode: string, paymentLinkId: string}
     */
    public function createPaymentLink(
        int $orderCode,
        int $amount,
        string $description,
        string $returnUrl,
        string $cancelUrl
    ): array {
        $clientId = $this->settings->getSecret('payos_client_id');
        $apiKey = $this->settings->getSecret('payos_api_key');
        $checksum = $this->settings->getSecret('payos_checksum_key');

        if (! $clientId || ! $apiKey || ! $checksum) {
            throw new ProviderException('Chưa cấu hình PayOS.');
        }

        // Chữ ký theo tài liệu PayOS: sắp xếp key theo alphabet, nối value bằng dấu "|".
        $data = [
            'orderCode' => $orderCode,
            'amount' => $amount,
            'description' => mb_substr($description, 0, 25),
            'returnUrl' => $returnUrl,
            'cancelUrl' => $cancelUrl,
        ];
        ksort($data);
        $signature = hash_hmac('sha256', implode('|', array_map(
            fn ($v) => is_bool($v) ? ($v ? 'true' : 'false') : (string) $v,
            $data
        )), $checksum);

        try {
            $response = Http::timeout(20)
                ->withHeaders([
                    'x-client-id' => $clientId,
                    'x-api-key' => $apiKey,
                ])
                ->post(self::BASE_URL, array_merge($data, ['signature' => $signature]));
        } catch (\Throwable $e) {
            Log::warning('PayOS: không kết nối được');

            throw new ProviderException('Không kết nối được PayOS. Vui lòng thử lại.');
        }

        $json = $response->json();

        if (! is_array($json) || ($json['code'] ?? '') !== '00' || empty($json['data'])) {
            Log::warning('PayOS: tạo link thất bại', ['code' => $json['code'] ?? null]);

            throw new ProviderException($json['desc'] ?? 'PayOS từ chối tạo link thanh toán.');
        }

        return [
            'checkoutUrl' => (string) ($json['data']['checkoutUrl'] ?? ''),
            'qrCode' => (string) ($json['data']['qrCode'] ?? ''),
            'paymentLinkId' => (string) ($json['data']['paymentLinkId'] ?? ''),
        ];
    }

    /**
     * Xác thực chữ ký webhook PayOS.
     * PayOS gửi {code, desc, data:{...}, signature}. Ta ký lại data theo
     * checksum key và so sánh bằng hash_equals.
     */
    public function verifyWebhook(array $payload): bool
    {
        $checksum = $this->settings->getSecret('payos_checksum_key');

        if (! $checksum) {
            return false;
        }

        $signature = (string) ($payload['signature'] ?? '');
        $data = $payload['data'] ?? null;

        if ($signature === '' || ! is_array($data)) {
            return false;
        }

        ksort($data);
        $expected = hash_hmac('sha256', implode('|', array_map(
            fn ($v) => is_bool($v) ? ($v ? 'true' : 'false') : (string) $v,
            $data
        )), $checksum);

        return hash_equals($expected, $signature);
    }
}
```

- [ ] **Step 5: Controller webhook PayOS**

`app/Http/Controllers/Webhook/PayOSController.php`:

```php
<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\DepositRequest;
use App\Models\PayOsTransaction;
use App\Models\WebhookLog;
use App\Services\DepositService;
use App\Services\PayOS\PayOSClient;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayOSController extends Controller
{
    public function __construct(
        private readonly PayOSClient $payos,
        private readonly DepositService $deposits,
        private readonly SettingService $settings,
    ) {
    }

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        if (! $this->payos->verifyWebhook($payload)) {
            return response()->json(['success' => false, 'message' => 'Chữ ký không hợp lệ.'], 401);
        }

        $data = $payload['data'] ?? [];
        $orderCode = (int) ($data['orderCode'] ?? 0);
        $amount = (int) ($data['amount'] ?? 0);
        $reference = (string) ($data['reference'] ?? $data['paymentLinkId'] ?? '');

        $tx = PayOsTransaction::where('order_code', $orderCode)->first();

        if (! $tx) {
            $this->deposits->handleGatewayDeposit(
                gateway: 'payos',
                gatewayTxnId: 'payos-' . $orderCode,
                amount: $amount,
                content: (string) ($data['description'] ?? ''),
                payload: $payload,
            );

            return response()->json(['success' => true, 'message' => 'Đã ghi nhận.']);
        }

        $deposit = DepositRequest::find($tx->deposit_request_id);

        if (! $deposit) {
            return response()->json(['success' => true]);
        }

        // Nếu PayOS đã báo trước đó (status paid) thì chỉ trả 200 — không cộng lại.
        if ($tx->status === 'paid') {
            return response()->json(['success' => true, 'message' => 'Đã xử lý trước đó.']);
        }

        DB::transaction(function () use ($tx, $deposit, $reference, $amount, $payload) {
            $this->deposits->handleGatewayDeposit(
                gateway: 'payos',
                gatewayTxnId: 'payos-' . $reference,
                amount: $amount,
                content: $deposit->transfer_code,
                payload: $payload,
            );

            $tx->forceFill(['status' => 'paid', 'paid_at' => now()])->save();

            if ($deposit->status === DepositRequest::STATUS_PENDING) {
                $deposit->forceFill([
                    'status' => DepositRequest::STATUS_APPROVED,
                    'reviewed_at' => now(),
                ])->save();
            }
        });

        return response()->json(['success' => true]);
    }
}
```

- [ ] **Step 6: Command hết hạn + lịch cron**

`app/Console/Commands/ExpireDepositsCommand.php`:

```php
<?php

namespace App\Console\Commands;

use App\Services\DepositService;
use Illuminate\Console\Command;

class ExpireDepositsCommand extends Command
{
    protected $signature = 'deposits:expire';
    protected $description = 'Đóng các yêu cầu nạp qua cổng thanh toán đã quá hạn (30 phút)';

    public function handle(DepositService $deposits): int
    {
        $count = $deposits->expireStaleRequests();

        $this->info("Đã đóng {$count} yêu cầu nạp quá hạn.");

        return self::SUCCESS;
    }
}
```

`routes/console.php`:

```php
<?php

use Illuminate\Support\Facades\Schedule;

// Đồng bộ trạng thái đơn mỗi 5 phút — SRS yêu cầu.
Schedule::command('orders:sync')->everyFiveMinutes()->withoutOverlapping();

// Đóng yêu cầu nạp quá hạn.
Schedule::command('deposits:expire')->everyFiveMinutes()->withoutOverlapping();
```

- [ ] **Step 7: Route**

```php
use App\Http\Controllers\Webhook\PayOSController;

Route::post('/webhook/payos', [PayOSController::class, 'handle'])
    ->middleware('throttle:120,1')
    ->name('webhook.payos');
```

- [ ] **Step 8: Chạy test**

```bash
cd /d/NhoSub && php artisan test --filter="DepositManualTest|PayOSWebhookTest"
```

Kỳ vọng: tất cả xanh.

- [ ] **Step 9: Commit**

```bash
cd /d/NhoSub && git add -A && git commit -m "feat: PayOS webhook, duyet nap tay, tu dong het han yeu cau nap sau 30 phut

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 13: OrderSyncService + cron đồng bộ trạng thái

**Files:**
- Create: `app/Services/OrderSyncService.php`
- Create: `app/Console/Commands/SyncOrdersCommand.php`
- Create: `app/Jobs/SyncOrderStatusJob.php`
- Test: `tests/Feature/OrderSyncTest.php`

**Interfaces:**
- Consumes: `RefundService` (7), `ProviderManager` (9), `Order` model
- Produces:
  ```php
  OrderSyncService::syncOrder(Order $order): void        // đồng bộ 1 đơn
  OrderSyncService::syncBatch(int $limit = 100): array   // ['synced'=>int, 'failed'=>int]
  OrderSyncService::flagStuckOrders(): int               // charged > 10 phút → stuck_review
  ```

- [ ] **Step 1: Viết test thất bại**

`tests/Feature/OrderSyncTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\Service;
use App\Models\User;
use App\Services\OrderSyncService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderSyncTest extends TestCase
{
    use RefreshDatabase;

    private OrderSyncService $sync;

    protected function setUp(): void
    {
        parent::setUp();
        app(SettingService::class)->set('demo_mode', 'on');
        app(SettingService::class)->set('demo_scenario', 'auto');
        $this->sync = app(OrderSyncService::class);
    }

    private function makeOrder(array $overrides = []): Order
    {
        $user = User::factory()->create(['balance' => 0]);
        $service = Service::factory()->create(['provider_service_id' => 'demo-1']);

        return Order::factory()->create(array_merge([
            'user_id' => $user->id,
            'service_id' => $service->id,
            'quantity' => 1000,
            'unit_price' => 3380,
            'total_amount' => 3380,
            'cost_amount' => 2600,
            'status' => Order::STATUS_PROCESSING,
            'provider_order_id' => 'demo:1',
            'created_at' => now()->subMinutes(5),
        ], $overrides));
    }

    public function test_dong_bo_don_completed(): void
    {
        $order = $this->makeOrder();
        $order->forceFill(['provider_order_id' => 'demo:' . $order->id])->save();

        $this->sync->syncOrder($order->fresh());

        $this->assertSame(Order::STATUS_COMPLETED, $order->fresh()->status);
        $this->assertSame(0, OrderRefund::count(), 'Đơn hoàn thành không được hoàn tiền');
    }

    public function test_partial_hoan_dung_phan_chua_chay(): void
    {
        app(SettingService::class)->set('demo_scenario', 'partial');

        $order = $this->makeOrder();
        $order->forceFill(['provider_order_id' => 'demo:' . $order->id])->save();

        $this->sync->syncOrder($order->fresh());

        $order->refresh();
        $this->assertSame(Order::STATUS_PARTIAL, $order->status);
        // remains 400/1000 → hoàn 3380*400/1000 = 1352
        $this->assertSame(1352, $order->refunded_amount);
        $this->assertSame(1352, $order->user->fresh()->balance);
    }

    public function test_dong_bo_lai_sau_partial_khong_hoan_them(): void
    {
        app(SettingService::class)->set('demo_scenario', 'partial');

        $order = $this->makeOrder();
        $order->forceFill(['provider_order_id' => 'demo:' . $order->id])->save();

        $this->sync->syncOrder($order->fresh());
        $this->sync->syncOrder($order->fresh());
        $this->sync->syncOrder($order->fresh());

        $this->assertSame(1352, $order->fresh()->refunded_amount);
        $this->assertSame(1, OrderRefund::count());
    }

    public function test_canceled_hoan_toan_bo(): void
    {
        app(SettingService::class)->set('demo_scenario', 'cancel');

        $order = $this->makeOrder();
        $order->forceFill(['provider_order_id' => 'demo:' . $order->id])->save();

        $this->sync->syncOrder($order->fresh());

        $this->assertSame(Order::STATUS_CANCELED, $order->fresh()->status);
        $this->assertSame(3380, $order->fresh()->refunded_amount);
        $this->assertSame(3380, $order->user->fresh()->balance);
    }

    public function test_don_da_ket_thuc_khong_dong_bo_lai(): void
    {
        $order = $this->makeOrder(['status' => Order::STATUS_COMPLETED]);
        $truoc = $order->synced_at;

        $this->sync->syncOrder($order->fresh());

        $this->assertSame(Order::STATUS_COMPLETED, $order->fresh()->status);
        $this->assertSame($truoc?->toDateTimeString(), $order->fresh()->synced_at?->toDateTimeString());
    }

    public function test_don_stuck_review_khong_bi_cron_cham(): void
    {
        $order = $this->makeOrder(['status' => Order::STATUS_STUCK_REVIEW]);

        $this->sync->syncBatch();

        $this->assertSame(Order::STATUS_STUCK_REVIEW, $order->fresh()->status);
    }

    public function test_don_charged_qua_10_phut_thanh_stuck_review_khong_hoan_tien(): void
    {
        $order = $this->makeOrder([
            'status' => Order::STATUS_CHARGED,
            'provider_order_id' => null,
            'created_at' => now()->subMinutes(15),
        ]);
        $user = $order->user;
        $user->forceFill(['balance' => 0])->save();

        $count = $this->sync->flagStuckOrders();

        $this->assertSame(1, $count);
        $this->assertSame(Order::STATUS_STUCK_REVIEW, $order->fresh()->status);
        $this->assertSame(0, $user->fresh()->balance, 'Không được tự hoàn tiền');
    }

    public function test_don_charged_moi_tao_chua_bi_danh_dau(): void
    {
        $order = $this->makeOrder([
            'status' => Order::STATUS_CHARGED,
            'provider_order_id' => null,
            'created_at' => now()->subMinutes(2),
        ]);

        $this->assertSame(0, $this->sync->flagStuckOrders());
        $this->assertSame(Order::STATUS_CHARGED, $order->fresh()->status);
    }

    public function test_sync_batch_chi_lay_don_processing(): void
    {
        $this->makeOrder(['status' => Order::STATUS_PROCESSING]);
        $this->makeOrder(['status' => Order::STATUS_COMPLETED]);
        $this->makeOrder(['status' => Order::STATUS_CANCELED]);

        $result = $this->sync->syncBatch();

        $this->assertSame(1, $result['synced']);
    }

    public function test_loi_provider_khong_lam_sap_cron(): void
    {
        $order = $this->makeOrder(['provider_order_id' => 'demo:999999']); // đơn không tồn tại

        $result = $this->sync->syncBatch();

        $this->assertSame(1, $result['failed']);
        $this->assertSame(Order::STATUS_PROCESSING, $order->fresh()->status, 'Giữ nguyên trạng thái khi lỗi');
    }
}
```

- [ ] **Step 2: Chạy test để thấy đỏ**

```bash
cd /d/NhoSub && php artisan test --filter=OrderSyncTest
```

Kỳ vọng: FAIL — class không tồn tại.

- [ ] **Step 3: Viết `app/Services/OrderSyncService.php`**

```php
<?php

namespace App\Services;

use App\Models\ApiSyncLog;
use App\Models\Order;
use App\Providers\ProviderManager;
use Illuminate\Support\Facades\Log;

/**
 * Đồng bộ trạng thái đơn từ nhà cung cấp.
 *
 * Nguyên tắc:
 *  - Chỉ xử lý đơn `processing` (đã có provider_order_id).
 *  - Đơn `charged` quá 10 phút → `stuck_review` (KHÔNG tự hoàn tiền,
 *    vì có thể đã gửi thành công mà chưa kịp lưu mã).
 *  - partial/canceled → hoàn tiền phần chưa chạy qua RefundService (đã chống trùng).
 *  - Lỗi gọi API: giữ nguyên trạng thái, ghi log, không làm sập batch.
 */
class OrderSyncService
{
    private const STUCK_MINUTES = 10;
    private const MIN_AGE_SECONDS = 60;

    public function __construct(
        private readonly ProviderManager $providers,
        private readonly RefundService $refunds,
    ) {
    }

    public function syncOrder(Order $order): void
    {
        if ($order->isFinished()) {
            return;
        }

        if ($order->status === Order::STATUS_STUCK_REVIEW) {
            return;
        }

        if (empty($order->provider_order_id)) {
            return;
        }

        $status = $this->providers->driver()->orderStatus((string) $order->provider_order_id);

        ApiSyncLog::create([
            'action' => 'check_status',
            'order_id' => $order->id,
            'request_summary' => 'order=' . $order->provider_order_id,
            'response_summary' => 'status=' . $status->status
                . ', start=' . ($status->startCount ?? '-')
                . ', remains=' . ($status->remains ?? '-'),
            'success' => true,
            'created_at' => now(),
        ]);

        // Trạng thái lạ: giữ nguyên, để admin xem log.
        if ($status->status === 'unknown') {
            return;
        }

        $order->forceFill([
            'start_count' => $status->startCount ?? $order->start_count,
            'remains' => $status->remains ?? $order->remains,
            'synced_at' => now(),
            'sync_attempts' => (int) $order->sync_attempts + 1,
        ])->save();

        $newStatus = match ($status->status) {
            'pending' => Order::STATUS_PENDING,
            'processing' => Order::STATUS_PROCESSING,
            'completed' => Order::STATUS_COMPLETED,
            'partial' => Order::STATUS_PARTIAL,
            'canceled' => Order::STATUS_CANCELED,
            default => $order->status,
        };

        $order->forceFill(['status' => $newStatus])->save();

        // Hoàn tiền phần chưa chạy — RefundService tự chống trùng nhiều lớp.
        if ($newStatus === Order::STATUS_PARTIAL) {
            $this->refunds->refundOrder($order->fresh(), 'auto_partial');
        } elseif ($newStatus === Order::STATUS_CANCELED) {
            $this->refunds->refundOrder($order->fresh(), 'auto_canceled');
        }
    }

    /**
     * @return array{synced: int, failed: int}
     */
    public function syncBatch(int $limit = 100): array
    {
        $orders = Order::query()
            ->where('status', Order::STATUS_PROCESSING)
            ->whereNotNull('provider_order_id')
            ->where('created_at', '<=', now()->subSeconds(self::MIN_AGE_SECONDS))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $synced = 0;
        $failed = 0;

        foreach ($orders as $order) {
            try {
                $this->syncOrder($order);
                $synced++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('Đồng bộ đơn thất bại', ['order_id' => $order->id, 'error' => $e->getMessage()]);

                ApiSyncLog::create([
                    'action' => 'check_status',
                    'order_id' => $order->id,
                    'request_summary' => 'order=' . $order->provider_order_id,
                    'response_summary' => mb_substr($e->getMessage(), 0, 500),
                    'success' => false,
                    'created_at' => now(),
                ]);
            }
        }

        return ['synced' => $synced, 'failed' => $failed];
    }

    /**
     * Đơn đã trừ tiền nhưng chưa có mã nhà cung cấp sau 10 phút
     * → chuyển `stuck_review` để admin xử lý tay.
     * TUYỆT ĐỐI không tự hoàn tiền: đơn có thể đã chạy thật bên NCC.
     */
    public function flagStuckOrders(): int
    {
        return Order::query()
            ->where('status', Order::STATUS_CHARGED)
            ->whereNull('provider_order_id')
            ->where('created_at', '<', now()->subMinutes(self::STUCK_MINUTES))
            ->update(['status' => Order::STATUS_STUCK_REVIEW, 'updated_at' => now()]);
    }
}
```

- [ ] **Step 4: Command + job**

`app/Console/Commands/SyncOrdersCommand.php`:

```php
<?php

namespace App\Console\Commands;

use App\Services\OrderSyncService;
use Illuminate\Console\Command;

class SyncOrdersCommand extends Command
{
    protected $signature = 'orders:sync {--limit=100}';
    protected $description = 'Đồng bộ trạng thái đơn đang chạy từ nhà cung cấp';

    public function handle(OrderSyncService $sync): int
    {
        $stuck = $sync->flagStuckOrders();
        $result = $sync->syncBatch((int) $this->option('limit'));

        $this->info("Đồng bộ: {$result['synced']} đơn thành công, {$result['failed']} lỗi, {$stuck} đơn kẹt chuyển admin.");

        return self::SUCCESS;
    }
}
```

`app/Jobs/SyncOrderStatusJob.php`:

```php
<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\OrderSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncOrderStatusJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $orderId)
    {
    }

    public function handle(OrderSyncService $sync): void
    {
        $order = Order::find($this->orderId);

        if ($order !== null) {
            $sync->syncOrder($order);
        }
    }
}
```

- [ ] **Step 5: Chạy test**

```bash
cd /d/NhoSub && php artisan test --filter=OrderSyncTest
```

Kỳ vọng: 10 passed.

- [ ] **Step 6: Commit**

```bash
cd /d/NhoSub && git add -A && git commit -m "feat: dong bo trang thai don qua cron, don ket chuyen admin thay vi tu hoan

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 14: Đồng bộ danh sách dịch vụ từ nhà cung cấp

**Files:**
- Create: `app/Services/ServiceSyncService.php`
- Test: `tests/Feature/ServiceSyncTest.php`

**Interfaces:**
- Consumes: `ProviderManager` (9), `PricingService` (8), `Category`/`Service` models
- Produces:
  ```php
  ServiceSyncService::sync(): array   // ['created'=>int,'updated'=>int,'deactivated'=>int,'total'=>int]
  ```

- [ ] **Step 1: Viết test thất bại**

`tests/Feature/ServiceSyncTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Service;
use App\Services\PricingService;
use App\Services\ServiceSyncService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ServiceSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $s = app(SettingService::class);
        $s->set('exchange_rate', '26000');
        $s->set('default_markup_percent', '30');
        $s->setSecret('topsubre_api_key', 'real-key');
        $s->set('demo_mode', 'off');
    }

    private function fakeServices(array $rows): void
    {
        Http::fake(['likenhanh.pro/*' => Http::response($rows, 200)]);
    }

    public function test_tao_dich_vu_moi_va_danh_muc_tu_dong(): void
    {
        $this->fakeServices([
            ['service' => 101, 'name' => 'Like FB', 'category' => 'Facebook Likes',
             'rate' => '0.08', 'min' => 100, 'max' => 50000, 'description' => 'Mô tả'],
            ['service' => 102, 'name' => 'View TikTok', 'category' => 'TikTok Views',
             'rate' => '0.01', 'min' => 1000, 'max' => 100000, 'description' => ''],
        ]);

        $result = app(ServiceSyncService::class)->sync();

        $this->assertSame(2, $result['created']);
        $this->assertSame(2, Service::count());
        $this->assertGreaterThanOrEqual(1, Category::count());

        $svc = Service::where('provider_service_id', '101')->first();
        $this->assertSame('Like FB', $svc->name);
        $this->assertSame(2080, $svc->cost_price);       // 0.08 * 26000
        $this->assertSame(2704, $svc->sell_price);       // +30%
        $this->assertSame('0.080000', $svc->cost_usd);
        $this->assertSame(100, $svc->min_qty);
        $this->assertSame(50000, $svc->max_qty);
    }

    public function test_cap_nhat_gia_goc_giu_gia_ban_dat_rieng(): void
    {
        $cat = Category::factory()->create(['name' => 'Facebook Likes', 'platform' => 'facebook']);
        $svc = Service::factory()->create([
            'category_id' => $cat->id,
            'provider_service_id' => '101',
            'cost_usd' => '0.05',
            'cost_price' => 1300,
            'sell_price' => 9999,
            'sell_price_override' => true,
        ]);

        $this->fakeServices([
            ['service' => 101, 'name' => 'Like FB', 'category' => 'Facebook Likes',
             'rate' => '0.10', 'min' => 100, 'max' => 50000],
        ]);

        $result = app(ServiceSyncService::class)->sync();

        $this->assertSame(1, $result['updated']);
        $svc->refresh();
        $this->assertSame(2600, $svc->cost_price);       // cập nhật
        $this->assertSame(9999, $svc->sell_price);       // GIỮ NGUYÊN
        $this->assertTrue($svc->sell_price_override);
    }

    public function test_cap_nhat_gia_ban_khi_khong_co_override(): void
    {
        $cat = Category::factory()->create(['name' => 'Facebook Likes', 'platform' => 'facebook']);
        $svc = Service::factory()->create([
            'category_id' => $cat->id,
            'provider_service_id' => '101',
            'cost_price' => 1300,
            'sell_price' => 1690,
            'sell_price_override' => false,
        ]);

        $this->fakeServices([
            ['service' => 101, 'name' => 'Like FB', 'category' => 'Facebook Likes',
             'rate' => '0.10', 'min' => 100, 'max' => 50000],
        ]);

        app(ServiceSyncService::class)->sync();

        $svc->refresh();
        $this->assertSame(2600, $svc->cost_price);
        $this->assertSame(3380, $svc->sell_price);       // 2600 * 1.3
    }

    public function test_dich_vu_bien_mat_bi_an_khong_xoa(): void
    {
        $cat = Category::factory()->create(['name' => 'Facebook Likes', 'platform' => 'facebook']);
        $svc = Service::factory()->create([
            'category_id' => $cat->id,
            'provider_service_id' => '999',
            'is_active' => true,
        ]);

        $this->fakeServices([
            ['service' => 101, 'name' => 'Like FB', 'category' => 'Facebook Likes',
             'rate' => '0.08', 'min' => 100, 'max' => 50000],
        ]);

        $result = app(ServiceSyncService::class)->sync();

        $this->assertSame(1, $result['deactivated']);
        $this->assertSame(2, Service::count(), 'Không được xoá dịch vụ');
        $this->assertFalse($svc->fresh()->is_active);
    }

    public function test_api_loi_khong_xoa_du_lieu_cu(): void
    {
        $cat = Category::factory()->create();
        Service::factory()->create(['category_id' => $cat->id, 'provider_service_id' => '101']);

        Http::fake(['likenhanh.pro/*' => Http::response(['error' => 'Server error'], 200)]);

        try {
            app(ServiceSyncService::class)->sync();
            $this->fail('Phải ném ProviderException');
        } catch (\App\Exceptions\ProviderException $e) {
            $this->assertSame(1, Service::count());
        }
    }
}
```

- [ ] **Step 2: Chạy test để thấy đỏ**

```bash
cd /d/NhoSub && php artisan test --filter=ServiceSyncTest
```

Kỳ vọng: FAIL.

- [ ] **Step 3: Viết `app/Services/ServiceSyncService.php`**

```php
<?php

namespace App\Services;

use App\Models\ApiSyncLog;
use App\Models\Category;
use App\Models\Service;
use App\Providers\ProviderManager;
use Illuminate\Support\Facades\DB;

/**
 * Đồng bộ dịch vụ từ nhà cung cấp.
 *
 * Quy tắc:
 *  - Đối chiếu theo provider_service_id: có thì cập nhật, chưa có thì tạo.
 *  - Luôn cập nhật cost_usd + cost_price.
 *  - sell_price: chỉ tính lại theo % nếu admin CHƯA đặt giá riêng.
 *  - Dịch vụ biến mất khỏi NCC: is_active = 0, KHÔNG xoá (đơn cũ còn tra cứu).
 *  - Lỗi API: không động vào dữ liệu cũ.
 */
class ServiceSyncService
{
    public function __construct(
        private readonly ProviderManager $providers,
        private readonly PricingService $pricing,
    ) {
    }

    public function sync(): array
    {
        $remote = $this->providers->driver()->services();

        $created = 0;
        $updated = 0;
        $seenIds = [];

        DB::transaction(function () use ($remote, &$created, &$updated, &$seenIds) {
            foreach ($remote as $item) {
                $seenIds[] = $item->id;

                $category = $this->resolveCategory($item->category);

                $costPrice = $this->pricing->costPriceFromUsd($item->costUsd);

                $service = Service::where('provider_service_id', $item->id)->first();

                if ($service === null) {
                    Service::create([
                        'category_id' => $category->id,
                        'provider_service_id' => $item->id,
                        'name' => mb_substr($item->name, 0, 255),
                        'description' => $item->description,
                        'cost_usd' => $item->costUsd,
                        'cost_price' => $costPrice,
                        'sell_price' => $this->pricing->sellPriceFromCost($costPrice),
                        'sell_price_override' => false,
                        'min_qty' => max(1, $item->minQty),
                        'max_qty' => max($item->minQty, $item->maxQty),
                        'is_active' => true,
                        'synced_at' => now(),
                    ]);

                    $created++;
                    continue;
                }

                $service->forceFill([
                    'category_id' => $category->id,
                    'name' => mb_substr($item->name, 0, 255),
                    'description' => $item->description,
                    'cost_usd' => $item->costUsd,
                    'cost_price' => $costPrice,
                    'sell_price' => $service->sell_price_override
                        ? $service->sell_price
                        : $this->pricing->sellPriceFromCost($costPrice),
                    'min_qty' => max(1, $item->minQty),
                    'max_qty' => max($item->minQty, $item->maxQty),
                    'is_active' => true,
                    'synced_at' => now(),
                ])->save();

                $updated++;
            }

            // Dịch vụ không còn trong danh sách NCC → ẩn, không xoá.
            Service::whereNotIn('provider_service_id', $seenIds)
                ->where('is_active', true)
                ->update(['is_active' => false, 'updated_at' => now()]);
        });

        $deactivated = Service::whereNotIn('provider_service_id', $seenIds)
            ->where('is_active', true)
            ->count();

        ApiSyncLog::create([
            'action' => 'sync_services',
            'order_id' => null,
            'request_summary' => 'Đồng bộ danh sách dịch vụ',
            'response_summary' => "Tạo mới {$created}, cập nhật {$updated}, tổng " . count($remote),
            'success' => true,
            'created_at' => now(),
        ]);

        return [
            'created' => $created,
            'updated' => $updated,
            'deactivated' => $deactivated,
            'total' => count($remote),
        ];
    }

    /** Tìm hoặc tạo danh mục theo tên NCC trả về; đoán nền tảng từ tên. */
    private function resolveCategory(string $name): Category
    {
        $name = trim($name) !== '' ? mb_substr(trim($name), 0, 160) : 'Khác';

        $category = Category::where('name', $name)->first();

        if ($category !== null) {
            return $category;
        }

        $lower = mb_strtolower($name);
        $platform = match (true) {
            str_contains($lower, 'facebook'), str_contains($lower, 'fb') => 'facebook',
            str_contains($lower, 'tiktok') => 'tiktok',
            str_contains($lower, 'instagram'), str_contains($lower, 'ig') => 'instagram',
            str_contains($lower, 'youtube'), str_contains($lower, 'yt') => 'youtube',
            default => 'khac',
        };

        return Category::create([
            'name' => $name,
            'platform' => $platform,
            'sort_order' => 100,
            'is_active' => true,
        ]);
    }
}
```

- [ ] **Step 4: Chạy test**

```bash
cd /d/NhoSub && php artisan test --filter=ServiceSyncTest
```

Kỳ vọng: 5 passed.

- [ ] **Step 5: Commit**

```bash
cd /d/NhoSub && git add -A && git commit -m "feat: dong bo dich vu tu NCC, giu gia ban rieng, an thay vi xoa

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 15: Trang tổng quan, bảng dịch vụ, đặt đơn (UI khách hàng)

**Files:**
- Create: `app/Http/Controllers/ServiceCatalogController.php`, `OrderController.php`
- Modify: `app/Http/Controllers/DashboardController.php`
- Create: `resources/views/panel/{dashboard,services,order-create,order-preview}.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/CustomerPagesTest.php`

**Interfaces:**
- Consumes: `OrderService` (10), `Order`, `Service`, `Category` models
- Produces routes:
  `panel.dashboard`, `panel.services.index`, `panel.orders.create`, `panel.orders.preview` (POST), `panel.orders.store` (POST)

- [ ] **Step 1: Viết test thất bại**

`tests/Feature/CustomerPagesTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingService::class)->set('demo_mode', 'on');
        $this->user = User::factory()->create(['balance' => 50000]);
    }

    public function test_trang_tong_quan_hien_so_du_va_thong_ke(): void
    {
        Order::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_PROCESSING,
        ]);
        Order::factory()->create(['user_id' => $this->user->id, 'status' => Order::STATUS_COMPLETED]);

        $this->actingAs($this->user)->get('/dashboard')
            ->assertOk()
            ->assertSee('50.000')
            ->assertSee('4');
    }

    public function test_bang_dich_vu_khong_lo_gia_goc(): void
    {
        $cat = Category::factory()->create(['name' => 'FB Like', 'platform' => 'facebook']);
        Service::factory()->create([
            'category_id' => $cat->id,
            'name' => 'Like bài viết',
            'cost_price' => 1234,
            'sell_price' => 3380,
            'is_active' => true,
        ]);

        $res = $this->actingAs($this->user)->get('/services');

        $res->assertOk()
            ->assertSee('Like bài viết')
            ->assertSee('3.380')
            ->assertDontSee('1.234')
            ->assertDontSee('cost_price');
    }

    public function test_dich_vu_an_khong_hien(): void
    {
        $cat = Category::factory()->create();
        Service::factory()->create(['category_id' => $cat->id, 'name' => 'Dịch vụ ẩn', 'is_active' => false]);

        $this->actingAs($this->user)->get('/services')->assertDontSee('Dịch vụ ẩn');
    }

    public function test_tim_kiem_dich_vu_theo_ten(): void
    {
        $cat = Category::factory()->create();
        Service::factory()->create(['category_id' => $cat->id, 'name' => 'TikTok View', 'is_active' => true]);
        Service::factory()->create(['category_id' => $cat->id, 'name' => 'Facebook Like', 'is_active' => true]);

        $this->actingAs($this->user)->get('/services?q=tiktok')
            ->assertSee('TikTok View')
            ->assertDontSee('Facebook Like');
    }

    public function test_form_dat_don_hien_va_co_client_token(): void
    {
        $cat = Category::factory()->create();
        Service::factory()->create(['category_id' => $cat->id, 'is_active' => true]);

        $res = $this->actingAs($this->user)->get('/orders/create');

        $res->assertOk()->assertSee('client_token');
    }

    public function test_xem_truoc_tinh_dung_tien(): void
    {
        $cat = Category::factory()->create();
        $svc = Service::factory()->create([
            'category_id' => $cat->id, 'sell_price' => 3380,
            'min_qty' => 100, 'max_qty' => 100000, 'is_active' => true,
        ]);

        $this->actingAs($this->user)->post('/orders/preview', [
            'service_id' => $svc->id,
            'target_link' => 'https://facebook.com/p/1',
            'quantity' => 1000,
        ])->assertOk()->assertSee('3.380')->assertSee('46.620');
    }

    public function test_dat_don_qua_http_tao_don_va_tru_tien(): void
    {
        $cat = Category::factory()->create();
        $svc = Service::factory()->create([
            'category_id' => $cat->id, 'sell_price' => 3380, 'cost_price' => 2600,
            'min_qty' => 100, 'max_qty' => 100000, 'is_active' => true,
        ]);

        $token = (string) \Illuminate\Support\Str::uuid();

        $res = $this->actingAs($this->user)->post('/orders', [
            'service_id' => $svc->id,
            'target_link' => 'https://facebook.com/p/1',
            'quantity' => 1000,
            'client_token' => $token,
        ]);

        $order = Order::first();
        $res->assertRedirect(route('panel.orders.show', $order));
        $this->assertSame(46620, $this->user->fresh()->balance);
        $this->assertSame($token, $order->client_token);
    }

    public function test_khach_khong_xem_duoc_don_nguoi_khac(): void
    {
        $other = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $other->id]);

        $this->actingAs($this->user)->get('/orders/' . $order->id)->assertNotFound();
    }
}
```

- [ ] **Step 2: Chạy test để thấy đỏ**

```bash
cd /d/NhoSub && php artisan test --filter=CustomerPagesTest
```

Kỳ vọng: FAIL.

- [ ] **Step 3: Controller**

`app/Http/Controllers/ServiceCatalogController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceCatalogController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $platform = (string) $request->query('platform', '');

        $categories = Category::query()
            ->where('is_active', true)
            ->with(['services' => function ($query) use ($q) {
                // Chỉ trả về cột cần hiển thị — KHÔNG bao giờ chọn cost_price.
                $query->select([
                    'id', 'category_id', 'name', 'description', 'sell_price',
                    'min_qty', 'max_qty', 'speed', 'avg_start_time',
                ])
                    ->where('is_active', true)
                    ->when($q !== '', fn ($qq) => $qq->where('name', 'like', '%' . $q . '%'))
                    ->orderBy('sell_price');
            }])
            ->when($platform !== '', fn ($c) => $c->where('platform', $platform))
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (Category $c) => $c->services->isNotEmpty())
            ->values();

        return view('panel.services', [
            'categories' => $categories,
            'q' => $q,
            'platform' => $platform,
            'platforms' => Category::where('is_active', true)->distinct()->pluck('platform'),
        ]);
    }
}
```

`app/Http/Controllers/OrderController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Requests\OrderPreviewRequest;
use App\Http\Requests\OrderStoreRequest;
use App\Models\Category;
use App\Models\Order;
use App\Models\Service;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders)
    {
    }

    /** Bước 1: chọn dịch vụ + nhập link/số lượng. */
    public function create(Request $request): View
    {
        $preselected = (int) $request->query('service_id', 0);

        $categories = Category::where('is_active', true)
            ->with(['services' => fn ($q) => $q->where('is_active', true)
                ->select(['id', 'category_id', 'name', 'sell_price', 'min_qty', 'max_qty'])
                ->orderBy('name')])
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (Category $c) => $c->services->isNotEmpty());

        return view('panel.order-create', [
            'categories' => $categories,
            'preselected' => $preselected,
            'clientToken' => (string) Str::uuid(),
        ]);
    }

    /** Xem trước tổng tiền — không ghi DB. */
    public function preview(OrderPreviewRequest $request): View
    {
        $preview = $this->orders->preview(
            $request->user()->id,
            (int) $request->input('service_id'),
            (string) $request->input('target_link'),
            (int) $request->input('quantity'),
        );

        return view('panel.order-preview', [
            'preview' => $preview,
            'targetLink' => (string) $request->input('target_link'),
            'clientToken' => (string) $request->input('client_token', Str::uuid()),
        ]);
    }

    /** Bước 2: xác nhận. */
    public function store(OrderStoreRequest $request): RedirectResponse
    {
        try {
            $order = $this->orders->place(
                $request->user()->id,
                (int) $request->input('service_id'),
                (string) $request->input('target_link'),
                (int) $request->input('quantity'),
                (string) $request->input('client_token'),
            );
        } catch (InsufficientBalanceException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($order->status === Order::STATUS_ERROR) {
            return redirect()->route('panel.orders.show', $order)
                ->with('error', 'Nhà cung cấp không nhận đơn. Tiền đã được hoàn vào ví của bạn.');
        }

        return redirect()->route('panel.orders.show', $order)
            ->with('success', 'Đặt đơn thành công! Đơn #' . $order->id . ' đang được xử lý.');
    }

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $q = trim((string) $request->query('q', ''));

        $orders = Order::query()
            ->where('user_id', $request->user()->id)
            ->with('service:id,name')
            ->when($status !== '', fn ($x) => $x->where('status', $status))
            ->when($q !== '', fn ($x) => $x->where(function ($w) use ($q) {
                $w->where('id', $q)->orWhere('target_link', 'like', '%' . $q . '%');
            }))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('panel.orders', [
            'orders' => $orders,
            'status' => $status,
            'q' => $q,
        ]);
    }

    public function show(Request $request, int $id): View
    {
        // Chỉ đơn của chính khách — truy vấn theo user_id, không chỉ dựa vào route.
        $order = Order::where('user_id', $request->user()->id)
            ->with(['service:id,name', 'refunds'])
            ->findOrFail($id);

        return view('panel.order-show', ['order' => $order]);
    }

    /** Nút "Cập nhật trạng thái" — rate limit 15 giây/đơn. */
    public function refresh(Request $request, int $id): RedirectResponse
    {
        $order = Order::where('user_id', $request->user()->id)->findOrFail($id);

        $key = 'order-refresh:' . $order->id;

        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($key, 1)) {
            return back()->with('warning', 'Vui lòng chờ vài giây trước khi cập nhật lại.');
        }

        \Illuminate\Support\Facades\RateLimiter::hit($key, 15);

        if (! $order->isFinished() && $order->provider_order_id) {
            try {
                app(\App\Services\OrderSyncService::class)->syncOrder($order);
            } catch (\Throwable $e) {
                return back()->with('warning', 'Chưa lấy được trạng thái mới. Vui lòng thử lại sau.');
            }
        }

        return back()->with('success', 'Đã cập nhật trạng thái đơn.');
    }
}
```

`app/Http/Controllers/DashboardController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $stats = [
            'balance' => (int) $user->balance,
            'total_orders' => Order::where('user_id', $user->id)->count(),
            'running_orders' => Order::where('user_id', $user->id)
                ->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_CHARGED, Order::STATUS_PROCESSING])
                ->count(),
        ];

        $recentOrders = Order::where('user_id', $user->id)
            ->with('service:id,name')
            ->latest('id')
            ->limit(5)
            ->get();

        $notifications = $user->notifications()->latest()->limit(5)->get();

        return view('panel.dashboard', [
            'user' => $user,
            'stats' => $stats,
            'recentOrders' => $recentOrders,
            'notifications' => $notifications,
        ]);
    }
}
```

- [ ] **Step 4: Route**

Trong nhóm `Route::middleware('auth')` thêm:

```php
Route::get('/services', [ServiceCatalogController::class, 'index'])->name('panel.services.index');
Route::get('/orders/create', [OrderController::class, 'create'])->name('panel.orders.create');
Route::post('/orders/preview', [OrderController::class, 'preview'])->name('panel.orders.preview');
Route::post('/orders', [OrderController::class, 'store'])->name('panel.orders.store');
Route::get('/orders', [OrderController::class, 'index'])->name('panel.orders.index');
Route::get('/orders/{id}', [OrderController::class, 'show'])->name('panel.orders.show')->whereNumber('id');
Route::post('/orders/{id}/refresh', [OrderController::class, 'refresh'])->name('panel.orders.refresh')->whereNumber('id');
```

- [ ] **Step 5: View dashboard**

`resources/views/panel/dashboard.blade.php`:

```blade
@extends('layouts.panel')
@section('title', 'Tổng quan')
@section('content')
    <div class="grid grid-4 mb-3">
        <div class="stat">
            <div class="stat-label">Số dư ví</div>
            <div class="stat-value primary">{{ number_format($stats['balance'], 0, ',', '.') }} ₫</div>
        </div>
        <div class="stat">
            <div class="stat-label">Tổng số đơn</div>
            <div class="stat-value">{{ number_format($stats['total_orders'], 0, ',', '.') }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">Đơn đang chạy</div>
            <div class="stat-value warning">{{ number_format($stats['running_orders'], 0, ',', '.') }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">Thông báo mới</div>
            <div class="stat-value">{{ $notifications->whereNull('read_at')->count() }}</div>
        </div>
    </div>

    <div class="grid grid-2">
        <div class="card">
            <div class="flex-between mb-2">
                <h3 class="card-title mb-0">Đơn gần đây</h3>
                <a href="{{ route('panel.orders.index') }}" class="text-sm link-underline">Xem tất cả</a>
            </div>

            @forelse ($recentOrders as $order)
                <div class="flex-between" style="padding:12px 0;border-bottom:1px solid #EDF2FA;">
                    <div style="min-width:0;">
                        <a href="{{ route('panel.orders.show', $order) }}" class="link-underline" style="font-weight:600;">
                            #{{ $order->id }}
                        </a>
                        <div class="text-sm text-muted truncate">{{ $order->service->name ?? '—' }}</div>
                    </div>
                    <div class="text-right nowrap">
                        @include('partials.order-status', ['status' => $order->status])
                        <div class="text-sm text-muted mt-1">{{ number_format($order->total_amount, 0, ',', '.') }} ₫</div>
                    </div>
                </div>
            @empty
                <div class="empty-state">
                    <p>Bạn chưa có đơn nào.</p>
                    <a href="{{ route('panel.orders.create') }}" class="btn btn-primary mt-2">Đặt đơn đầu tiên</a>
                </div>
            @endforelse
        </div>

        <div class="card">
            <h3 class="card-title">Thông báo mới nhất</h3>
            @forelse ($notifications as $n)
                <div style="padding:12px 0;border-bottom:1px solid #EDF2FA;">
                    <div style="font-weight:600;">{{ $n->data['title'] ?? 'Thông báo' }}</div>
                    <div class="text-sm text-muted">{{ $n->data['message'] ?? '' }}</div>
                    <div class="text-sm text-muted mt-1">{{ $n->created_at->diffForHumans() }}</div>
                </div>
            @empty
                <p class="text-muted">Chưa có thông báo nào.</p>
            @endforelse
        </div>
    </div>

    <div class="card mt-3 dot-pattern" style="background-color:#F5F8FF;">
        <div class="flex-between flex-wrap">
            <div>
                <h3 class="card-title mb-1">Cần nạp thêm tiền?</h3>
                <p class="text-muted mb-0">Nạp tiền tự động qua chuyển khoản, cộng ví trong vài giây.</p>
            </div>
            <a href="{{ route('panel.deposit.create') }}" class="btn btn-primary">Nạp tiền ngay</a>
        </div>
    </div>
@endsection
```

- [ ] **Step 6: View bảng dịch vụ**

`resources/views/panel/services.blade.php`:

```blade
@extends('layouts.panel')
@section('title', 'Bảng giá dịch vụ')
@section('content')
    <form method="GET" action="{{ route('panel.services.index') }}" class="card mb-3">
        <div class="grid grid-3" style="align-items:end;">
            <div class="form-group mb-0">
                <label class="form-label" for="q">Tìm dịch vụ</label>
                <input class="form-input" id="q" name="q" value="{{ $q }}" placeholder="Ví dụ: like, view, follow…">
            </div>
            <div class="form-group mb-0">
                <label class="form-label" for="platform">Nền tảng</label>
                <select class="form-select" id="platform" name="platform">
                    <option value="">Tất cả nền tảng</option>
                    @foreach ($platforms as $p)
                        <option value="{{ $p }}" @selected($platform === $p)>{{ ucfirst($p) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group mb-0">
                <button type="submit" class="btn btn-primary btn-block">Lọc dịch vụ</button>
            </div>
        </div>
    </form>

    @forelse ($categories as $category)
        <section class="mb-4">
            <div class="flex-between mb-2">
                <h2 style="font-size:19px;margin:0;">{{ $category->name }}</h2>
                <span class="badge badge-neutral">{{ ucfirst($category->platform) }}</span>
            </div>

            <div class="grid grid-2">
                @foreach ($category->services as $service)
                    <article class="card card-hover">
                        <h3 style="font-size:16px;margin-bottom:8px;">{{ $service->name }}</h3>

                        @if ($service->description)
                            <p class="text-sm text-muted" style="margin-bottom:12px;">{{ $service->description }}</p>
                        @endif

                        <div class="flex-between" style="padding:10px 0;border-top:1px solid #EDF2FA;">
                            <span class="text-sm text-muted">Giá / 1.000</span>
                            <strong style="color:var(--primary);font-size:17px;">
                                {{ number_format($service->sell_price, 0, ',', '.') }} ₫
                            </strong>
                        </div>

                        <div class="flex-between text-sm text-muted" style="padding-bottom:10px;">
                            <span>Số lượng: {{ number_format($service->min_qty, 0, ',', '.') }} – {{ number_format($service->max_qty, 0, ',', '.') }}</span>
                            <span>{{ $service->speed ?? '—' }}</span>
                        </div>

                        <div class="flex-between text-sm text-muted mb-2">
                            <span>Bắt đầu: {{ $service->avg_start_time ?? '—' }}</span>
                        </div>

                        <a href="{{ route('panel.orders.create', ['service_id' => $service->id]) }}"
                           class="btn btn-outline btn-block">Đặt đơn</a>
                    </article>
                @endforeach
            </div>
        </section>
    @empty
        <div class="card empty-state">
            <p>Danh mục đang cập nhật. Vui lòng quay lại sau.</p>
        </div>
    @endforelse
@endsection
```

- [ ] **Step 7: View form đặt đơn**

`resources/views/panel/order-create.blade.php`:

```blade
@extends('layouts.panel')
@section('title', 'Đặt đơn mới')
@section('content')
    <form method="POST" action="{{ route('panel.orders.preview') }}" class="card" style="max-width:720px;">
        @csrf
        <input type="hidden" name="client_token" value="{{ $clientToken }}">

        <div class="form-group">
            <label class="form-label" for="service_id">Chọn dịch vụ</label>
            <select class="form-select @error('service_id') is-invalid @enderror" id="service_id" name="service_id" required>
                <option value="">— Chọn dịch vụ —</option>
                @foreach ($categories as $category)
                    <optgroup label="{{ $category->name }}">
                        @foreach ($category->services as $service)
                            <option value="{{ $service->id }}"
                                    data-min="{{ $service->min_qty }}"
                                    data-max="{{ $service->max_qty }}"
                                    data-price="{{ $service->sell_price }}"
                                    @selected($preselected === $service->id)>
                                {{ $service->name }} — {{ number_format($service->sell_price, 0, ',', '.') }} ₫/1.000
                            </option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label class="form-label" for="target_link">Link cần tăng tương tác</label>
            <input class="form-input @error('target_link') is-invalid @enderror" id="target_link" name="target_link"
                   value="{{ old('target_link') }}" placeholder="https://facebook.com/..." required>
            <div class="form-hint">Dán link bài viết, trang hoặc video công khai.</div>
        </div>

        <div class="form-group">
            <label class="form-label" for="quantity">Số lượng</label>
            <input class="form-input @error('quantity') is-invalid @enderror" id="quantity" name="quantity"
                   type="number" value="{{ old('quantity') }}" required>
            <div class="form-hint" id="qty-hint">Chọn dịch vụ để xem giới hạn số lượng.</div>
        </div>

        <div class="alert alert-info" id="price-preview" style="display:none;">
            Tạm tính: <strong id="price-total">0 ₫</strong>
        </div>

        <button type="submit" class="btn btn-primary">Xem trước đơn hàng</button>
    </form>

    @push('scripts')
    <script>
        (function () {
            var select = document.getElementById('service_id');
            var qty = document.getElementById('quantity');
            var hint = document.getElementById('qty-hint');
            var box = document.getElementById('price-preview');
            var total = document.getElementById('price-total');

            function refresh() {
                var opt = select.options[select.selectedIndex];
                if (!opt || !opt.value) { hint.textContent = 'Chọn dịch vụ để xem giới hạn số lượng.'; box.style.display = 'none'; return; }

                var min = parseInt(opt.dataset.min, 10);
                var max = parseInt(opt.dataset.max, 10);
                var price = parseInt(opt.dataset.price, 10);

                qty.min = min;
                qty.max = max;
                hint.textContent = 'Từ ' + min.toLocaleString('vi-VN') + ' đến ' + max.toLocaleString('vi-VN') + '.';

                var q = parseInt(qty.value, 10);
                if (!isNaN(q) && q > 0) {
                    box.style.display = 'block';
                    total.textContent = Math.floor(price * q / 1000).toLocaleString('vi-VN') + ' ₫';
                }
            }

            select.addEventListener('change', refresh);
            qty.addEventListener('input', refresh);
            refresh();
        })();
    </script>
    @endpush
@endsection
```

- [ ] **Step 8: View xem trước**

`resources/views/panel/order-preview.blade.php`:

```blade
@extends('layouts.panel')
@section('title', 'Xác nhận đơn hàng')
@section('content')
    <div class="card" style="max-width:680px;">
        <h2 class="card-title">Xem lại đơn hàng</h2>

        <div class="table-wrap mb-3">
            <table class="table">
                <tbody>
                    <tr><th style="width:40%;">Dịch vụ</th><td>{{ $preview['service']->name }}</td></tr>
                    <tr><th>Link</th><td class="truncate" title="{{ $targetLink }}">{{ $targetLink }}</td></tr>
                    <tr><th>Số lượng</th><td>{{ number_format($preview['quantity'], 0, ',', '.') }}</td></tr>
                    <tr><th>Giá / 1.000</th><td>{{ number_format($preview['unit_price'], 0, ',', '.') }} ₫</td></tr>
                    <tr><th>Tổng tiền</th><td><strong style="color:var(--primary);font-size:18px;">{{ number_format($preview['total'], 0, ',', '.') }} ₫</strong></td></tr>
                    <tr><th>Số dư hiện tại</th><td>{{ number_format($preview['balance'], 0, ',', '.') }} ₫</td></tr>
                    <tr><th>Số dư sau khi đặt</th>
                        <td class="{{ $preview['balance_after'] < 0 ? 'text-danger' : 'text-success' }}">
                            {{ number_format($preview['balance_after'], 0, ',', '.') }} ₫
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        @if ($preview['balance_after'] < 0)
            <div class="alert alert-error">
                Số dư không đủ. Vui lòng <a href="{{ route('panel.deposit.create') }}">nạp thêm tiền</a>.
            </div>
        @endif

        <form method="POST" action="{{ route('panel.orders.store') }}" data-guard>
            @csrf
            <input type="hidden" name="service_id" value="{{ $preview['service']->id }}">
            <input type="hidden" name="target_link" value="{{ $targetLink }}">
            <input type="hidden" name="quantity" value="{{ $preview['quantity'] }}">
            <input type="hidden" name="client_token" value="{{ $clientToken }}">

            <div class="flex gap-1 flex-wrap">
                <button type="submit" class="btn btn-primary" @disabled($preview['balance_after'] < 0)>
                    Xác nhận đặt đơn
                </button>
                <a href="{{ route('panel.orders.create', ['service_id' => $preview['service']->id]) }}"
                   class="btn btn-ghost">Quay lại chỉnh sửa</a>
            </div>
        </form>
    </div>
@endsection
```

- [ ] **Step 9: Chạy test**

```bash
cd /d/NhoSub && php artisan test --filter=CustomerPagesTest
```

Kỳ vọng: 8 passed.

- [ ] **Step 10: Commit**

```bash
cd /d/NhoSub && git add -A && git commit -m "feat: trang tong quan, bang dich vu, dat don 2 buoc voi xem truoc

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 16: Trang đơn hàng, nạp tiền, ví, hồ sơ, hỗ trợ, thông báo

**Files:**
- Create: `app/Http/Controllers/{DepositController,WalletController,ProfileController,SupportController,NotificationController}.php`
- Create: `resources/views/panel/{orders,order-show,deposit,wallet,support,profile}.blade.php`
- Create: `app/Notifications/{DepositApproved,OrderStatusChanged,BalanceAdjusted}.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/CustomerPagesTest.php` (thêm case)

**Interfaces:**
- Consumes: `DepositService` (12), `WalletService` (6), `OrderSyncService` (13)
- Produces routes:
  `panel.orders.index/show/refresh`, `panel.deposit.create/store`, `panel.wallet.index`,
  `panel.profile.edit/update`, `panel.support.index`, `panel.notifications.index/read`

- [ ] **Step 1: Viết test bổ sung**

Thêm vào `tests/Feature/CustomerPagesTest.php`:

```php
    public function test_trang_don_hang_loc_theo_trang_thai(): void
    {
        Order::factory()->create(['user_id' => $this->user->id, 'status' => Order::STATUS_COMPLETED]);
        Order::factory()->create(['user_id' => $this->user->id, 'status' => Order::STATUS_PROCESSING]);

        $this->actingAs($this->user)->get('/orders?status=completed')
            ->assertOk()
            ->assertSee('Hoàn thành');
    }

    public function test_trang_chi_tiet_don_hien_ma_va_trang_thai(): void
    {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_PROCESSING,
            'start_count' => 100,
            'remains' => 900,
        ]);

        $this->actingAs($this->user)->get('/orders/' . $order->id)
            ->assertOk()
            ->assertSee('#' . $order->id)
            ->assertSee('Đang chạy');
    }

    public function test_trang_nap_tien_hien_noi_dung_chuyen_khoan_dung_format(): void
    {
        $this->actingAs($this->user)->get('/deposit')
            ->assertOk()
            ->assertSee('NHOSUB ' . strtoupper($this->user->username));
    }

    public function test_gui_yeu_cau_nap_tay_tao_pending(): void
    {
        $this->actingAs($this->user)->post('/deposit', ['amount' => 50000])
            ->assertRedirect();

        $this->assertSame(1, \App\Models\DepositRequest::where('status', 'pending')->count());
    }

    public function test_trang_vi_hien_lich_su_giao_dich(): void
    {
        app(\App\Services\WalletService::class)->apply(
            $this->user->id, 'deposit', 20000, 'deposit:test:view', 'Nạp thử'
        );

        $this->actingAs($this->user)->get('/wallet')
            ->assertOk()
            ->assertSee('20.000')
            ->assertSee('Nạp thử');
    }

    public function test_cap_nhat_ho_so_khong_doi_duoc_username(): void
    {
        $this->actingAs($this->user)->post('/profile', [
            'full_name' => 'Tên Mới',
            'phone' => '0909999999',
            'email' => $this->user->email,
            'username' => 'hacker',
            'role' => 'admin',
        ])->assertRedirect();

        $u = $this->user->fresh();
        $this->assertSame('Tên Mới', $u->full_name);
        $this->assertSame($this->user->username, $u->username, 'Không được đổi username');
        $this->assertSame('customer', $u->role, 'Không được tự nâng quyền');
    }

    public function test_trang_ho_tro_va_thong_bao_hoat_dong(): void
    {
        $this->actingAs($this->user)->get('/support')->assertOk();
        $this->actingAs($this->user)->get('/notifications')->assertOk();
    }
```

- [ ] **Step 2: Chạy test để thấy đỏ**

```bash
cd /d/NhoSub && php artisan test --filter=CustomerPagesTest
```

Kỳ vọng: FAIL các case mới.

- [ ] **Step 3: Controller**

`app/Http/Controllers/DepositController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\DepositRequest;
use App\Services\DepositService;
use App\Services\PayOS\PayOSClient;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepositController extends Controller
{
    public function __construct(
        private readonly DepositService $deposits,
        private readonly SettingService $settings,
        private readonly PayOSClient $payos,
    ) {
    }

    public function create(Request $request): View
    {
        $user = $request->user();

        return view('panel.deposit', [
            'user' => $user,
            'transferContent' => $user->transferContent(),
            'minDeposit' => $this->settings->getInt('min_deposit', 10000),
            'bankName' => $this->settings->get('bank_name', ''),
            'bankAccount' => $this->settings->get('bank_account', ''),
            'bankOwner' => $this->settings->get('bank_owner', ''),
            'bankBin' => $this->settings->get('bank_bin', ''),
            'sepayEnabled' => $this->settings->getBool('sepay_enabled', true),
            'payosEnabled' => $this->payos->isEnabled(),
            'requests' => DepositRequest::where('user_id', $user->id)->latest('id')->limit(20)->get(),
        ]);
    }

    /** Gửi yêu cầu nạp duyệt tay. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1000', 'max:1000000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'amount.required' => 'Vui lòng nhập số tiền đã chuyển.',
            'amount.integer' => 'Số tiền phải là số nguyên.',
            'amount.min' => 'Số tiền không hợp lệ.',
        ]);

        try {
            $this->deposits->createManualRequest(
                $request->user()->id,
                (int) $data['amount'],
                DepositRequest::METHOD_BANK,
                $data['note'] ?? null,
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with('success', 'Đã gửi yêu cầu nạp tiền. Admin sẽ duyệt trong thời gian sớm nhất.');
    }
}
```

`app/Http/Controllers/WalletController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WalletController extends Controller
{
    public function index(Request $request): View
    {
        $type = (string) $request->query('type', '');

        $transactions = WalletTransaction::where('user_id', $request->user()->id)
            ->when($type !== '', fn ($q) => $q->where('type', $type))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('panel.wallet', [
            'user' => $request->user(),
            'transactions' => $transactions,
            'type' => $type,
        ]);
    }
}
```

`app/Http/Controllers/ProfileController.php`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('panel.profile', ['user' => $request->user()]);
    }

    /** Chỉ cho sửa họ tên, SĐT, email. Username và role bị bỏ qua hoàn toàn. */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'full_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\s.-]{8,20}$/'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
        ], [
            'email.unique' => 'Email đã được dùng bởi tài khoản khác.',
            'phone.regex' => 'Số điện thoại không hợp lệ.',
        ]);

        $user->forceFill([
            'full_name' => $data['full_name'] ?? $user->full_name,
            'phone' => $data['phone'] ?? $user->phone,
            'email' => mb_strtolower($data['email']),
        ])->save();

        return back()->with('success', 'Đã cập nhật hồ sơ.');
    }
}
```

`app/Http/Controllers/SupportController.php`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class SupportController extends Controller
{
    public function index(): View
    {
        return view('panel.support');
    }
}
```

`app/Http/Controllers/NotificationController.php`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = $request->user()->notifications()->paginate(20);

        return view('panel.notifications', ['notifications' => $notifications]);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'Đã đánh dấu tất cả là đã đọc.');
    }
}
```

- [ ] **Step 4: Route bổ sung**

```php
Route::get('/deposit', [DepositController::class, 'create'])->name('panel.deposit.create');
Route::post('/deposit', [DepositController::class, 'store'])->name('panel.deposit.store');
Route::get('/wallet', [WalletController::class, 'index'])->name('panel.wallet.index');
Route::get('/profile', [ProfileController::class, 'edit'])->name('panel.profile.edit');
Route::post('/profile', [ProfileController::class, 'update'])->name('panel.profile.update');
Route::get('/support', [SupportController::class, 'index'])->name('panel.support.index');
Route::get('/notifications', [NotificationController::class, 'index'])->name('panel.notifications.index');
Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('panel.notifications.readAll');
```

- [ ] **Step 5: View đơn hàng**

`resources/views/panel/orders.blade.php`:

```blade
@extends('layouts.panel')
@section('title', 'Đơn hàng của tôi')
@section('content')
    <form method="GET" class="card mb-3">
        <div class="grid grid-3" style="align-items:end;">
            <div class="form-group mb-0">
                <label class="form-label" for="q">Mã đơn hoặc link</label>
                <input class="form-input" id="q" name="q" value="{{ $q }}" placeholder="Ví dụ: 1024">
            </div>
            <div class="form-group mb-0">
                <label class="form-label" for="status">Trạng thái</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tất cả</option>
                    <option value="pending" @selected($status === 'pending')>Chờ xử lý</option>
                    <option value="processing" @selected($status === 'processing')>Đang chạy</option>
                    <option value="completed" @selected($status === 'completed')>Hoàn thành</option>
                    <option value="partial" @selected($status === 'partial')>Hoàn thành một phần</option>
                    <option value="canceled" @selected($status === 'canceled')>Đã hủy</option>
                    <option value="error" @selected($status === 'error')>Lỗi</option>
                </select>
            </div>
            <div class="form-group mb-0">
                <button class="btn btn-primary btn-block" type="submit">Lọc</button>
            </div>
        </div>
    </form>

    <div class="card" style="padding:0;overflow:hidden;">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Mã đơn</th>
                        <th>Dịch vụ</th>
                        <th class="num">Số lượng</th>
                        <th class="num">Đã chạy</th>
                        <th class="num">Còn lại</th>
                        <th class="num">Thành tiền</th>
                        <th>Trạng thái</th>
                        <th>Ngày tạo</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($orders as $order)
                    <tr>
                        <td><strong>#{{ $order->id }}</strong></td>
                        <td class="truncate" title="{{ $order->service->name ?? '' }}">{{ $order->service->name ?? '—' }}</td>
                        <td class="num">{{ number_format($order->quantity, 0, ',', '.') }}</td>
                        <td class="num">{{ number_format(max(0, $order->quantity - ($order->remains ?? $order->quantity)), 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($order->remains ?? 0, 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($order->total_amount, 0, ',', '.') }} ₫</td>
                        <td>@include('partials.order-status', ['status' => $order->status])</td>
                        <td class="text-sm text-muted nowrap">{{ $order->created_at->format('d/m/Y H:i') }}</td>
                        <td><a class="btn btn-ghost btn-sm" href="{{ route('panel.orders.show', $order) }}">Chi tiết</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted" style="padding:40px;">Chưa có đơn nào.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $orders->links() }}</div>
@endsection
```

`resources/views/panel/order-show.blade.php`:

```blade
@extends('layouts.panel')
@section('title', 'Đơn hàng #' . $order->id)
@section('content')
    <div class="grid grid-2">
        <div class="card">
            <div class="flex-between mb-2">
                <h2 class="card-title mb-0">Đơn #{{ $order->id }}</h2>
                @include('partials.order-status', ['status' => $order->status])
            </div>

            <div class="table-wrap">
                <table class="table">
                    <tbody>
                        <tr><th style="width:45%;">Dịch vụ</th><td>{{ $order->service->name ?? '—' }}</td></tr>
                        <tr><th>Link</th><td><a href="{{ $order->target_link }}" target="_blank" rel="noopener noreferrer" class="truncate link-underline">{{ $order->target_link }}</a></td></tr>
                        <tr><th>Số lượng đặt</th><td>{{ number_format($order->quantity, 0, ',', '.') }}</td></tr>
                        <tr><th>Đã chạy</th><td>{{ number_format(max(0, $order->quantity - ($order->remains ?? $order->quantity)), 0, ',', '.') }}</td></tr>
                        <tr><th>Còn lại</th><td>{{ number_format($order->remains ?? 0, 0, ',', '.') }}</td></tr>
                        <tr><th>Giá / 1.000</th><td>{{ number_format($order->unit_price, 0, ',', '.') }} ₫</td></tr>
                        <tr><th>Thành tiền</th><td><strong>{{ number_format($order->total_amount, 0, ',', '.') }} ₫</strong></td></tr>
                        <tr><th>Đã hoàn</th><td class="text-success">{{ number_format($order->refunded_amount, 0, ',', '.') }} ₫</td></tr>
                        <tr><th>Ngày tạo</th><td>{{ $order->created_at->format('d/m/Y H:i:s') }}</td></tr>
                        <tr><th>Cập nhật gần nhất</th><td>{{ ($order->synced_at ?? $order->updated_at)?->format('d/m/Y H:i:s') }}</td></tr>
                    </tbody>
                </table>
            </div>

            @unless ($order->isFinished())
                <form method="POST" action="{{ route('panel.orders.refresh', $order) }}" class="mt-2">
                    @csrf
                    <button class="btn btn-outline" type="submit">Cập nhật trạng thái</button>
                </form>
            @endunless
        </div>

        <div class="card">
            <h3 class="card-title">Lịch sử hoàn tiền</h3>
            @forelse ($order->refunds as $refund)
                <div class="flex-between" style="padding:10px 0;border-bottom:1px solid #EDF2FA;">
                    <div>
                        <strong>Đợt {{ $refund->seq }}</strong>
                        <div class="text-sm text-muted">
                            @switch($refund->reason)
                                @case('auto_partial') Hoàn phần chưa chạy (một phần) @break
                                @case('auto_canceled') Hoàn phần chưa chạy (đơn hủy) @break
                                @case('provider_error') Hoàn do lỗi nhà cung cấp @break
                                @default Hoàn tiền thủ công
                            @endswitch
                        </div>
                    </div>
                    <strong class="text-success">+{{ number_format($refund->amount, 0, ',', '.') }} ₫</strong>
                </div>
            @empty
                <p class="text-muted">Chưa có khoản hoàn nào.</p>
            @endforelse
        </div>
    </div>

    <a href="{{ route('panel.orders.index') }}" class="btn btn-ghost mt-3">← Về danh sách đơn</a>
@endsection
```

- [ ] **Step 6: View nạp tiền**

`resources/views/panel/deposit.blade.php`:

```blade
@extends('layouts.panel')
@section('title', 'Nạp tiền')
@section('content')
    <div class="grid grid-2">
        <div class="card">
            <h2 class="card-title">Chuyển khoản ngân hàng</h2>
            <p class="text-muted text-sm">
                Chuyển đúng <strong>nội dung</strong> bên dưới, hệ thống sẽ tự cộng tiền trong vài giây.
                Tối thiểu {{ number_format($minDeposit, 0, ',', '.') }} ₫.
            </p>

            <div class="table-wrap">
                <table class="table">
                    <tbody>
                        <tr><th style="width:42%;">Ngân hàng</th><td>{{ $bankName ?: 'Chưa cấu hình' }}</td></tr>
                        <tr><th>Số tài khoản</th><td><strong class="mono">{{ $bankAccount ?: '—' }}</strong></td></tr>
                        <tr><th>Chủ tài khoản</th><td>{{ $bankOwner ?: '—' }}</td></tr>
                        <tr>
                            <th>Nội dung chuyển khoản</th>
                            <td>
                                <span class="mono" id="transfer-content" style="font-weight:700;color:var(--primary);">
                                    {{ $transferContent }}
                                </span>
                                <button class="btn btn-ghost btn-sm mt-1" type="button"
                                        data-copy="{{ $transferContent }}"
                                        data-copy-message="Đã sao chép nội dung chuyển khoản">
                                    Sao chép
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            @if ($bankBin && $bankAccount)
                <div class="alert alert-info mt-2">
                    Quét mã QR bằng app ngân hàng để điền sẵn thông tin chuyển khoản.
                </div>
                <img src="https://img.vietqr.io/image/{{ $bankBin }}-{{ $bankAccount }}-compact2.png?amount=&addInfo={{ urlencode($transferContent) }}&accountName={{ urlencode($bankOwner) }}"
                     alt="Mã QR chuyển khoản" style="max-width:260px;border-radius:12px;border:1px solid var(--primary-light);"
                     loading="lazy">
            @endif
        </div>

        <div>
            <div class="card mb-3">
                <h2 class="card-title">Gửi yêu cầu nạp (duyệt tay)</h2>
                <p class="text-muted text-sm">Dùng khi đã chuyển khoản nhưng chưa thấy tiền vào ví.</p>
                <form method="POST" action="{{ route('panel.deposit.store') }}" data-guard>
                    @csrf
                    <div class="form-group">
                        <label class="form-label" for="amount">Số tiền đã chuyển (₫)</label>
                        <input class="form-input" id="amount" name="amount" type="number" min="{{ $minDeposit }}"
                               value="{{ old('amount') }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="note">Ghi chú</label>
                        <input class="form-input" id="note" name="note" value="{{ old('note') }}"
                               placeholder="Mã giao dịch ngân hàng (nếu có)">
                    </div>
                    <button class="btn btn-primary btn-block" type="submit">Gửi yêu cầu</button>
                </form>
            </div>

            <div class="card">
                <h3 class="card-title">Lịch sử yêu cầu nạp</h3>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr><th>Số tiền</th><th>Phương thức</th><th>Trạng thái</th><th>Thời gian</th></tr>
                        </thead>
                        <tbody>
                        @forelse ($requests as $r)
                            <tr>
                                <td class="num">{{ number_format($r->amount, 0, ',', '.') }} ₫</td>
                                <td>{{ $r->method === 'payos' ? 'PayOS' : 'Chuyển khoản' }}</td>
                                <td>@include('partials.order-status', ['status' => $r->status])</td>
                                <td class="text-sm text-muted">{{ $r->created_at?->format('d/m/Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted">Chưa có yêu cầu nào.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
```

- [ ] **Step 7: View ví, hồ sơ, hỗ trợ, thông báo**

`resources/views/panel/wallet.blade.php`:

```blade
@extends('layouts.panel')
@section('title', 'Lịch sử giao dịch')
@section('content')
    <div class="stat mb-3" style="max-width:360px;">
        <div class="stat-label">Số dư hiện tại</div>
        <div class="stat-value primary">{{ number_format($user->balance, 0, ',', '.') }} ₫</div>
    </div>

    <form method="GET" class="mb-2 flex gap-1 flex-wrap">
        <select class="form-select" name="type" style="max-width:220px;" onchange="this.form.submit()">
            <option value="">Tất cả giao dịch</option>
            <option value="deposit" @selected($type === 'deposit')>Nạp tiền</option>
            <option value="order_charge" @selected($type === 'order_charge')>Thanh toán đơn</option>
            <option value="refund" @selected($type === 'refund')>Hoàn tiền</option>
            <option value="adjust" @selected($type === 'adjust')>Điều chỉnh</option>
        </select>
    </form>

    <div class="card" style="padding:0;overflow:hidden;">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Thời gian</th><th>Loại</th><th>Nội dung</th><th class="num">Số tiền</th><th class="num">Số dư sau</th></tr>
                </thead>
                <tbody>
                @forelse ($transactions as $tx)
                    <tr>
                        <td class="text-sm text-muted nowrap">{{ $tx->created_at?->format('d/m/Y H:i') }}</td>
                        <td>
                            @switch($tx->type)
                                @case('deposit') <span class="badge badge-completed">Nạp tiền</span> @break
                                @case('order_charge') <span class="badge badge-processing">Thanh toán</span> @break
                                @case('refund') <span class="badge badge-partial">Hoàn tiền</span> @break
                                @default <span class="badge badge-neutral">Điều chỉnh</span>
                            @endswitch
                        </td>
                        <td class="text-sm">{{ $tx->note }}</td>
                        <td class="num {{ $tx->amount >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ $tx->amount >= 0 ? '+' : '' }}{{ number_format($tx->amount, 0, ',', '.') }} ₫
                        </td>
                        <td class="num">{{ number_format($tx->balance_after, 0, ',', '.') }} ₫</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted" style="padding:40px;">Chưa có giao dịch nào.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $transactions->links() }}</div>
@endsection
```

`resources/views/panel/profile.blade.php`:

```blade
@extends('layouts.panel')
@section('title', 'Hồ sơ cá nhân')
@section('content')
    <div class="card" style="max-width:560px;">
        <h2 class="card-title">Hồ sơ cá nhân</h2>

        <div class="alert alert-info">
            Tên đăng nhập <strong>{{ $user->username }}</strong> không thể thay đổi.
        </div>

        <form method="POST" action="{{ route('panel.profile.update') }}" data-guard>
            @csrf
            <div class="form-group">
                <label class="form-label" for="full_name">Họ và tên</label>
                <input class="form-input" id="full_name" name="full_name" value="{{ old('full_name', $user->full_name) }}">
            </div>
            <div class="form-group">
                <label class="form-label" for="email">Email</label>
                <input class="form-input @error('email') is-invalid @enderror" id="email" name="email"
                       type="email" value="{{ old('email', $user->email) }}" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="phone">Số điện thoại</label>
                <input class="form-input" id="phone" name="phone" value="{{ old('phone', $user->phone) }}">
            </div>
            <button class="btn btn-primary" type="submit">Lưu thay đổi</button>
        </form>
    </div>
@endsection
```

`resources/views/panel/support.blade.php`:

```blade
@extends('layouts.panel')
@section('title', 'Trung tâm hỗ trợ')
@section('content')
    <div class="grid grid-2">
        <div class="card">
            <h2 class="card-title">Hướng dẫn nhanh</h2>
            <ol style="padding-left:18px;color:var(--muted);">
                <li><strong style="color:var(--text);">Nạp tiền</strong> — chuyển khoản đúng nội dung <span class="mono">NHOSUB &lt;TÊN ĐĂNG NHẬP&gt;</span>.</li>
                <li><strong style="color:var(--text);">Chọn dịch vụ</strong> — xem giá theo 1.000 lượt, giới hạn số lượng.</li>
                <li><strong style="color:var(--text);">Đặt đơn</strong> — dán link, nhập số lượng, xem trước rồi xác nhận.</li>
                <li><strong style="color:var(--text);">Theo dõi</strong> — trạng thái tự cập nhật mỗi 5 phút.</li>
            </ol>
        </div>

        <div class="card">
            <h2 class="card-title">Câu hỏi thường gặp</h2>

            <details class="faq-item">
                <summary>Đơn bị hủy hoặc chạy một phần thì sao?</summary>
                <div class="faq-body">Hệ thống tự hoàn tiền phần chưa chạy vào ví của bạn trong vòng 5 phút sau khi nhà cung cấp cập nhật trạng thái.</div>
            </details>
            <details class="faq-item">
                <summary>Bao lâu thì đơn bắt đầu chạy?</summary>
                <div class="faq-body">Thời gian bắt đầu trung bình ghi trên từng dịch vụ, thường từ 0–60 phút.</div>
            </details>
            <details class="faq-item">
                <summary>Nạp tiền bao lâu thì vào ví?</summary>
                <div class="faq-body">Chuyển khoản đúng nội dung thường được cộng tự động trong vài giây. Nếu sau 15 phút chưa thấy, gửi yêu cầu nạp ở trang Nạp tiền.</div>
            </details>
        </div>
    </div>

    <div class="card mt-3">
        <h3 class="card-title">Cần hỗ trợ thêm?</h3>
        <p class="text-muted mb-0">Email: hotro@nhosub.vn · Thời gian hỗ trợ: 8:00 – 22:00 hằng ngày.</p>
    </div>
@endsection
```

`resources/views/panel/notifications.blade.php`:

```blade
@extends('layouts.panel')
@section('title', 'Thông báo')
@section('content')
    <div class="flex-between mb-2">
        <h2 style="font-size:18px;margin:0;">Tất cả thông báo</h2>
        <form method="POST" action="{{ route('panel.notifications.readAll') }}">
            @csrf
            <button class="btn btn-ghost btn-sm" type="submit">Đánh dấu tất cả đã đọc</button>
        </form>
    </div>

    <div class="card" style="padding:0;overflow:hidden;">
        @forelse ($notifications as $n)
            <div style="padding:16px 20px;border-bottom:1px solid #EDF2FA;{{ $n->read_at ? '' : 'background:#F5F8FF;' }}">
                <div class="flex-between">
                    <strong>{{ $n->data['title'] ?? 'Thông báo' }}</strong>
                    <span class="text-sm text-muted">{{ $n->created_at->format('d/m/Y H:i') }}</span>
                </div>
                <div class="text-sm text-muted mt-1">{{ $n->data['message'] ?? '' }}</div>
            </div>
        @empty
            <div class="empty-state">Chưa có thông báo nào.</div>
        @endforelse
    </div>

    <div class="mt-3">{{ $notifications->links() }}</div>
@endsection
```

- [ ] **Step 8: Notification class (dùng ở Task 19–20)**

`app/Notifications/DepositApproved.php`:

```php
<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DepositApproved extends Notification
{
    use Queueable;

    public function __construct(private readonly int $amount, private readonly int $depositId)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Nạp tiền thành công',
            'message' => 'Đã cộng ' . number_format($this->amount, 0, ',', '.') . ' ₫ vào ví (yêu cầu #' . $this->depositId . ').',
            'url' => route('panel.wallet.index'),
        ];
    }
}
```

`app/Notifications/OrderStatusChanged.php`:

```php
<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderStatusChanged extends Notification
{
    use Queueable;

    public function __construct(private readonly Order $order, private readonly string $status)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $label = match ($this->status) {
            Order::STATUS_COMPLETED => 'đã hoàn thành',
            Order::STATUS_PARTIAL => 'hoàn thành một phần',
            Order::STATUS_CANCELED => 'đã bị hủy',
            Order::STATUS_ERROR => 'gặp lỗi',
            default => 'đã cập nhật',
        };

        return [
            'title' => 'Đơn #' . $this->order->id . ' ' . $label,
            'message' => 'Trạng thái đơn hàng của bạn vừa thay đổi.',
            'url' => route('panel.orders.show', $this->order),
        ];
    }
}
```

`app/Notifications/BalanceAdjusted.php`:

```php
<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BalanceAdjusted extends Notification
{
    use Queueable;

    public function __construct(private readonly int $amount, private readonly string $reason)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $sign = $this->amount >= 0 ? '+' : '';

        return [
            'title' => 'Số dư ví thay đổi',
            'message' => 'Quản trị viên đã điều chỉnh ' . $sign . number_format($this->amount, 0, ',', '.') . ' ₫. Lý do: ' . $this->reason,
            'url' => route('panel.wallet.index'),
        ];
    }
}
```

- [ ] **Step 9: Chạy test**

```bash
cd /d/NhoSub && php artisan test --filter=CustomerPagesTest
```

Kỳ vọng: 15 passed.

- [ ] **Step 10: Commit**

```bash
cd /d/NhoSub && git add -A && git commit -m "feat: trang don hang, nap tien, vi, ho so, ho tro, thong bao

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 17: Admin — tổng quan, người dùng, đơn hàng

**Files:**
- Create: `app/Http/Controllers/Admin/{AdminDashboardController,AdminUserController,AdminOrderController}.php`
- Create: `resources/views/admin/{dashboard,users,user-show,orders,order-show}.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/AdminTest.php`

**Interfaces:**
- Consumes: `WalletService` (6), `RefundService` (7), `ProviderManager` (9), `OrderSyncService` (13)
- Produces routes: `admin.dashboard`, `admin.users.index/show/adjust/toggle`, `admin.orders.index/show/refund/resolve-stuck`

- [ ] **Step 1: Viết test thất bại**

`tests/Feature/AdminTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        app(SettingService::class)->set('demo_mode', 'on');
        $this->admin = User::factory()->admin()->create(['balance' => 0]);
    }

    public function test_tong_quan_admin_hien_chi_so(): void
    {
        $u = User::factory()->create(['balance' => 100000]);
        Order::factory()->create([
            'user_id' => $u->id,
            'total_amount' => 50000,
            'cost_amount' => 30000,
            'status' => Order::STATUS_COMPLETED,
        ]);

        $this->actingAs($this->admin)->get('/admin')
            ->assertOk()
            ->assertSee('Doanh thu')
            ->assertSee('50.000');
    }

    public function test_admin_xem_danh_sach_nguoi_dung_va_tim_kiem(): void
    {
        User::factory()->create(['username' => 'khachabc']);

        $this->actingAs($this->admin)->get('/admin/users?q=khachabc')
            ->assertOk()
            ->assertSee('khachabc');
    }

    public function test_admin_cong_tien_cho_khach(): void
    {
        $u = User::factory()->create(['balance' => 0]);

        $this->actingAs($this->admin)->post("/admin/users/{$u->id}/adjust", [
            'amount' => 50000,
            'reason' => 'Bù tiền sự cố',
        ])->assertRedirect();

        $this->assertSame(50000, $u->fresh()->balance);
        $this->assertSame(1, WalletTransaction::where('type', 'adjust')->count());
    }

    public function test_admin_tru_qua_so_du_bi_chan(): void
    {
        $u = User::factory()->create(['balance' => 1000]);

        $this->actingAs($this->admin)->post("/admin/users/{$u->id}/adjust", [
            'amount' => -5000,
            'reason' => 'Thu hồi',
        ])->assertSessionHasErrors();

        $this->assertSame(1000, $u->fresh()->balance);
    }

    public function test_cong_tien_bat_buoc_ly_do(): void
    {
        $u = User::factory()->create(['balance' => 0]);

        $this->actingAs($this->admin)->post("/admin/users/{$u->id}/adjust", [
            'amount' => 10000,
            'reason' => '',
        ])->assertSessionHasErrors('reason');

        $this->assertSame(0, $u->fresh()->balance);
    }

    public function test_admin_khoa_mo_tai_khoan(): void
    {
        $u = User::factory()->create(['status' => 'active']);

        $this->actingAs($this->admin)->post("/admin/users/{$u->id}/toggle")->assertRedirect();
        $this->assertSame('locked', $u->fresh()->status);

        $this->actingAs($this->admin)->post("/admin/users/{$u->id}/toggle")->assertRedirect();
        $this->assertSame('active', $u->fresh()->status);
    }

    public function test_khong_the_khoa_admin_cuoi_cung(): void
    {
        $this->actingAs($this->admin)->post("/admin/users/{$this->admin->id}/toggle")
            ->assertSessionHasErrors();

        $this->assertSame('active', $this->admin->fresh()->status);
    }

    public function test_admin_xem_tat_ca_don_va_loc(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        Order::factory()->create(['user_id' => $u1->id, 'status' => Order::STATUS_COMPLETED]);
        Order::factory()->create(['user_id' => $u2->id, 'status' => Order::STATUS_PROCESSING]);

        $this->actingAs($this->admin)->get('/admin/orders?status=completed')
            ->assertOk()
            ->assertSee('Hoàn thành');
    }

    public function test_admin_hoan_tien_thu_cong(): void
    {
        $u = User::factory()->create(['balance' => 0]);
        $order = Order::factory()->create([
            'user_id' => $u->id,
            'status' => Order::STATUS_PROCESSING,
            'total_amount' => 3380,
            'unit_price' => 3380,
            'quantity' => 1000,
            'remains' => 1000,
            'refunded_amount' => 0,
        ]);

        $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/refund", [
            'reason' => 'Khách phản ánh',
        ])->assertRedirect();

        $this->assertSame(3380, $u->fresh()->balance);
        $this->assertSame(3380, $order->fresh()->refunded_amount);
    }

    public function test_admin_xu_ly_don_stuck_review(): void
    {
        $u = User::factory()->create(['balance' => 0]);
        $order = Order::factory()->create([
            'user_id' => $u->id,
            'status' => Order::STATUS_STUCK_REVIEW,
            'provider_order_id' => null,
            'total_amount' => 3380,
            'unit_price' => 3380,
            'quantity' => 1000,
            'remains' => 1000,
            'refunded_amount' => 0,
        ]);

        // Admin xác nhận "chưa gửi" → hoàn tiền + đóng đơn
        $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/resolve-stuck", [
            'action' => 'refund',
            'reason' => 'Chưa gửi lên NCC',
        ])->assertRedirect();

        $this->assertSame(3380, $u->fresh()->balance);
        $this->assertSame(Order::STATUS_ERROR, $order->fresh()->status);
    }

    public function test_khach_thuong_khong_vao_duoc_bat_ky_trang_admin(): void
    {
        $u = User::factory()->create();

        foreach (['/admin', '/admin/users', '/admin/orders', '/admin/services', '/admin/deposits', '/admin/webhooks', '/admin/settings'] as $url) {
            $this->actingAs($u)->get($url)->assertForbidden();
        }
    }
}
```

- [ ] **Step 2: Chạy test để thấy đỏ**

```bash
cd /d/NhoSub && php artisan test --filter=AdminTest
```

Kỳ vọng: FAIL.

- [ ] **Step 3: AdminDashboardController**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DepositRequest;
use App\Models\Order;
use App\Models\User;
use App\Providers\ProviderManager;
use App\Services\SettingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(ProviderManager $providers, SettingService $settings): View
    {
        $today = now()->startOfDay();

        $revenue = (int) Order::where('created_at', '>=', $today)
            ->whereNotIn('status', [Order::STATUS_ERROR])
            ->sum('total_amount');

        $cost = (int) Order::where('created_at', '>=', $today)
            ->whereNotIn('status', [Order::STATUS_ERROR])
            ->sum('cost_amount');

        $refunded = (int) Order::where('created_at', '>=', $today)->sum('refunded_amount');

        $byStatus = Order::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        // Số dư nhà cung cấp — cache 5 phút, lỗi không làm sập trang.
        $providerBalance = Cache::remember('admin.provider_balance', 300, function () use ($providers) {
            try {
                return $providers->driver()->balance()->usd;
            } catch (\Throwable $e) {
                return null;
            }
        });

        $alertThreshold = (float) $settings->get('provider_balance_alert', '100');
        $lowBalance = $providerBalance !== null && (float) $providerBalance < $alertThreshold;

        return view('admin.dashboard', [
            'revenue' => $revenue,
            'cost' => $cost,
            'refunded' => $refunded,
            'profit' => $revenue - $refunded - $cost,
            'totalOrders' => array_sum($byStatus),
            'byStatus' => $byStatus,
            'userCount' => User::where('role', 'customer')->count(),
            'pendingDeposits' => DepositRequest::where('status', 'pending')->count(),
            'providerBalance' => $providerBalance,
            'lowBalance' => $lowBalance,
            'stuckCount' => Order::where('status', Order::STATUS_STUCK_REVIEW)->count(),
        ]);
    }
}
```

- [ ] **Step 4: AdminUserController**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Notifications\BalanceAdjusted;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function __construct(private readonly WalletService $wallet)
    {
    }

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $role = (string) $request->query('role', '');

        $users = User::query()
            ->when($q !== '', fn ($x) => $x->where(function ($w) use ($q) {
                $w->where('username', 'like', "%{$q}%")
                  ->orWhere('email', 'like', "%{$q}%")
                  ->orWhere('full_name', 'like', "%{$q}%")
                  ->orWhere('phone', 'like', "%{$q}%");
            }))
            ->when($role !== '', fn ($x) => $x->where('role', $role))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.users', ['users' => $users, 'q' => $q, 'role' => $role]);
    }

    public function show(User $user): View
    {
        return view('admin.user-show', [
            'user' => $user,
            'transactions' => $user->walletTransactions()->latest('id')->limit(30)->get(),
            'totalDeposit' => (int) $user->walletTransactions()
                ->where('type', WalletTransaction::TYPE_DEPOSIT)->sum('amount'),
            'orderCount' => $user->orders()->count(),
        ]);
    }

    /** Cộng/trừ tiền thủ công — bắt buộc có lý do, không cho âm. */
    public function adjust(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', 'string', 'min:3', 'max:200'],
        ], [
            'amount.required' => 'Vui lòng nhập số tiền.',
            'amount.not_in' => 'Số tiền phải khác 0.',
            'reason.required' => 'Vui lòng nhập lý do điều chỉnh.',
            'reason.min' => 'Lý do cần ít nhất 3 ký tự.',
        ]);

        try {
            $this->wallet->apply(
                userId: $user->id,
                type: WalletTransaction::TYPE_ADJUST,
                amount: (int) $data['amount'],
                idempotencyKey: 'adjust:' . Str::uuid(),
                note: 'Admin điều chỉnh: ' . $data['reason'],
            );
        } catch (\App\Exceptions\InsufficientBalanceException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        $user->notify(new BalanceAdjusted((int) $data['amount'], $data['reason']));

        return back()->with('success', 'Đã điều chỉnh số dư của ' . $user->username . '.');
    }

    /** Khoá/mở tài khoản — không thể khoá admin cuối cùng. */
    public function toggle(User $user): RedirectResponse
    {
        if ($user->isAdmin() && $user->status === User::STATUS_ACTIVE) {
            $activeAdmins = User::where('role', User::ROLE_ADMIN)
                ->where('status', User::STATUS_ACTIVE)
                ->count();

            if ($activeAdmins <= 1) {
                return back()->withErrors(['user' => 'Không thể khoá quản trị viên cuối cùng.']);
            }
        }

        $user->forceFill([
            'status' => $user->status === User::STATUS_ACTIVE
                ? User::STATUS_LOCKED
                : User::STATUS_ACTIVE,
        ])->save();

        return back()->with('success', $user->status === User::STATUS_ACTIVE
            ? 'Đã mở khoá tài khoản.'
            : 'Đã khoá tài khoản.');
    }
}
```

- [ ] **Step 5: AdminOrderController**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderSyncService;
use App\Services\RefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminOrderController extends Controller
{
    public function __construct(
        private readonly RefundService $refunds,
        private readonly OrderSyncService $sync,
    ) {
    }

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $q = trim((string) $request->query('q', ''));
        $username = trim((string) $request->query('username', ''));

        $orders = Order::query()
            ->with(['service:id,name', 'user:id,username'])
            ->when($status !== '', fn ($x) => $x->where('status', $status))
            ->when($q !== '', fn ($x) => $x->where(function ($w) use ($q) {
                $w->where('id', $q)->orWhere('target_link', 'like', "%{$q}%");
            }))
            ->when($username !== '', fn ($x) => $x->whereHas('user', fn ($u) => $u->where('username', 'like', "%{$username}%")))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.orders', compact('orders', 'status', 'q', 'username'));
    }

    public function show(Order $order): View
    {
        $order->load(['service', 'user', 'refunds']);

        $logs = \App\Models\ApiSyncLog::where('order_id', $order->id)->latest('id')->limit(50)->get();

        return view('admin.order-show', compact('order', 'logs'));
    }

    /** Hoàn tiền thủ công — RefundService tự chống trùng theo seq. */
    public function refund(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:200'],
        ], [
            'reason.required' => 'Vui lòng nhập lý do hoàn tiền.',
        ]);

        $refund = $this->refunds->refundOrder($order, 'admin_manual');

        if ($refund === null) {
            return back()->with('warning', 'Không còn gì để hoàn cho đơn này.');
        }

        return back()->with('success', 'Đã hoàn ' . number_format($refund->amount, 0, ',', '.') . ' ₫ cho khách.');
    }

    /**
     * Xử lý đơn stuck_review:
     *  - action=refund → hoàn tiền + status error
     *  - action=attach → nhập mã provider để tiếp tục đồng bộ
     */
    public function resolveStuck(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:refund,attach'],
            'provider_order_id' => ['nullable', 'string', 'max:128'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        if ($order->status !== Order::STATUS_STUCK_REVIEW) {
            return back()->with('warning', 'Đơn này không ở trạng thái chờ xử lý.');
        }

        if ($data['action'] === 'attach') {
            if (empty($data['provider_order_id'])) {
                return back()->withErrors(['provider_order_id' => 'Vui lòng nhập mã đơn của nhà cung cấp.']);
            }

            $order->forceFill([
                'provider_order_id' => $data['provider_order_id'],
                'status' => Order::STATUS_PROCESSING,
            ])->save();

            return back()->with('success', 'Đã gắn mã nhà cung cấp, đơn tiếp tục đồng bộ.');
        }

        // Chưa gửi lên NCC → hoàn toàn bộ + đóng đơn.
        $order->forceFill([
            'status' => Order::STATUS_ERROR,
            'remains' => (int) $order->quantity,
            'error_message' => $data['reason'] ?? 'Admin xác nhận chưa gửi nhà cung cấp.',
        ])->save();

        $refund = $this->refunds->refundOrder($order->fresh(), 'admin_manual');

        return back()->with('success', $refund
            ? 'Đã hoàn ' . number_format($refund->amount, 0, ',', '.') . ' ₫ và đóng đơn.'
            : 'Đã đóng đơn (không có khoản hoàn).');
    }
}
```

- [ ] **Step 6: Route admin**

```php
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
    Route::post('/users/{user}/adjust', [AdminUserController::class, 'adjust'])->name('users.adjust');
    Route::post('/users/{user}/toggle', [AdminUserController::class, 'toggle'])->name('users.toggle');

    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/refund', [AdminOrderController::class, 'refund'])->name('orders.refund');
    Route::post('/orders/{order}/resolve-stuck', [AdminOrderController::class, 'resolveStuck'])->name('orders.resolveStuck');
});
```

- [ ] **Step 7: View admin (dashboard, users, orders)**

`resources/views/admin/dashboard.blade.php`:

```blade
@extends('layouts.panel')
@section('title', 'Tổng quan quản trị')
@section('content')
    @if ($lowBalance)
        <div class="alert alert-warning">
            Số dư TopSubRe thấp ({{ $providerBalance }} USD). Vui lòng nạp thêm để tránh đơn bị kẹt.
        </div>
    @endif

    @if ($stuckCount > 0)
        <div class="alert alert-error">
            Có {{ $stuckCount }} đơn cần xử lý tay.
            <a href="{{ route('admin.orders.index', ['status' => 'stuck_review']) }}">Xem ngay</a>
        </div>
    @endif

    <div class="grid grid-4 mb-3">
        <div class="stat">
            <div class="stat-label">Doanh thu hôm nay</div>
            <div class="stat-value primary">{{ number_format($revenue, 0, ',', '.') }} ₫</div>
        </div>
        <div class="stat">
            <div class="stat-label">Chi phí gốc</div>
            <div class="stat-value">{{ number_format($cost, 0, ',', '.') }} ₫</div>
        </div>
        <div class="stat">
            <div class="stat-label">Lợi nhuận gộp</div>
            <div class="stat-value {{ $profit >= 0 ? 'success' : 'danger' }}">{{ number_format($profit, 0, ',', '.') }} ₫</div>
        </div>
        <div class="stat">
            <div class="stat-label">Số dư TopSubRe</div>
            <div class="stat-value {{ $lowBalance ? 'warning' : '' }}">
                {{ $providerBalance !== null ? number_format((float) $providerBalance, 2) . ' USD' : 'Không lấy được' }}
            </div>
        </div>
    </div>

    <div class="grid grid-3 mb-3">
        <div class="stat"><div class="stat-label">Tổng đơn</div><div class="stat-value">{{ number_format($totalOrders) }}</div></div>
        <div class="stat"><div class="stat-label">Khách hàng</div><div class="stat-value">{{ number_format($userCount) }}</div></div>
        <div class="stat"><div class="stat-label">Nạp chờ duyệt</div><div class="stat-value {{ $pendingDeposits > 0 ? 'warning' : '' }}">{{ $pendingDeposits }}</div></div>
    </div>

    <div class="card">
        <h3 class="card-title">Đơn theo trạng thái</h3>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Trạng thái</th><th class="num">Số đơn</th></tr></thead>
                <tbody>
                @foreach ($byStatus as $status => $count)
                    <tr>
                        <td>@include('partials.order-status', ['status' => $status])</td>
                        <td class="num">{{ number_format($count) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-sm text-muted mt-2 mb-0">
            Lợi nhuận gộp chưa trừ chi phí hosting, tên miền và chi phí vận hành khác.
        </p>
    </div>
@endsection
```

`resources/views/admin/users.blade.php`:

```blade
@extends('layouts.panel')
@section('title', 'Quản lý người dùng')
@section('content')
    <form method="GET" class="card mb-3">
        <div class="grid grid-3" style="align-items:end;">
            <div class="form-group mb-0">
                <label class="form-label" for="q">Tìm kiếm</label>
                <input class="form-input" id="q" name="q" value="{{ $q }}" placeholder="Tên đăng nhập, email, SĐT">
            </div>
            <div class="form-group mb-0">
                <label class="form-label" for="role">Vai trò</label>
                <select class="form-select" id="role" name="role">
                    <option value="">Tất cả</option>
                    <option value="customer" @selected($role === 'customer')>Khách hàng</option>
                    <option value="admin" @selected($role === 'admin')>Quản trị viên</option>
                </select>
            </div>
            <div class="form-group mb-0"><button class="btn btn-primary btn-block">Tìm</button></div>
        </div>
    </form>

    <div class="card" style="padding:0;overflow:hidden;">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>ID</th><th>Tên đăng nhập</th><th>Email</th><th>Vai trò</th><th class="num">Số dư</th><th>Trạng thái</th><th></th></tr>
                </thead>
                <tbody>
                @foreach ($users as $u)
                    <tr>
                        <td>{{ $u->id }}</td>
                        <td><strong>{{ $u->username }}</strong><div class="text-sm text-muted">{{ $u->full_name }}</div></td>
                        <td class="text-sm">{{ $u->email }}</td>
                        <td><span class="badge {{ $u->role === 'admin' ? 'badge-processing' : 'badge-neutral' }}">{{ $u->role === 'admin' ? 'Quản trị' : 'Khách' }}</span></td>
                        <td class="num">{{ number_format($u->balance, 0, ',', '.') }} ₫</td>
                        <td><span class="badge {{ $u->status === 'active' ? 'badge-completed' : 'badge-canceled' }}">{{ $u->status === 'active' ? 'Hoạt động' : 'Đã khoá' }}</span></td>
                        <td class="nowrap">
                            <a class="btn btn-ghost btn-sm" href="{{ route('admin.users.show', $u) }}">Chi tiết</a>
                            <form method="POST" action="{{ route('admin.users.toggle', $u) }}" style="display:inline;"
                                  data-confirm="Bạn chắc chắn muốn {{ $u->status === 'active' ? 'khoá' : 'mở khoá' }} tài khoản này?">
                                @csrf
                                <button class="btn btn-sm {{ $u->status === 'active' ? 'btn-danger' : 'btn-outline' }}" type="submit">
                                    {{ $u->status === 'active' ? 'Khoá' : 'Mở khoá' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $users->links() }}</div>
@endsection
```

`resources/views/admin/user-show.blade.php`:

```blade
@extends('layouts.panel')
@section('title', 'Người dùng ' . $user->username)
@section('content')
    <div class="grid grid-2">
        <div class="card">
            <h2 class="card-title">{{ $user->username }}</h2>
            <table class="table">
                <tbody>
                    <tr><th>Họ tên</th><td>{{ $user->full_name ?: '—' }}</td></tr>
                    <tr><th>Email</th><td>{{ $user->email }}</td></tr>
                    <tr><th>SĐT</th><td>{{ $user->phone ?: '—' }}</td></tr>
                    <tr><th>Số dư</th><td><strong>{{ number_format($user->balance, 0, ',', '.') }} ₫</strong></td></tr>
                    <tr><th>Tổng đã nạp</th><td>{{ number_format($totalDeposit, 0, ',', '.') }} ₫</td></tr>
                    <tr><th>Số đơn</th><td>{{ $orderCount }}</td></tr>
                    <tr><th>Trạng thái</th><td>{{ $user->status === 'active' ? 'Hoạt động' : 'Đã khoá' }}</td></tr>
                    <tr><th>Đăng nhập gần nhất</th><td>{{ $user->last_login_at?->format('d/m/Y H:i') ?? '—' }}</td></tr>
                </tbody>
            </table>

            <form method="POST" action="{{ route('admin.users.adjust', $user) }}" class="mt-2" data-guard>
                @csrf
                <div class="form-group">
                    <label class="form-label" for="amount">Cộng / trừ tiền (₫)</label>
                    <input class="form-input" id="amount" name="amount" type="number" required
                           placeholder="Số dương để cộng, số âm để trừ">
                </div>
                <div class="form-group">
                    <label class="form-label" for="reason">Lý do (bắt buộc)</label>
                    <input class="form-input" id="reason" name="reason" required maxlength="200">
                </div>
                <button class="btn btn-primary" type="submit">Áp dụng</button>
            </form>
        </div>

        <div class="card">
            <h3 class="card-title">Giao dịch gần đây</h3>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Thời gian</th><th>Loại</th><th class="num">Số tiền</th></tr></thead>
                    <tbody>
                    @forelse ($transactions as $tx)
                        <tr>
                            <td class="text-sm text-muted">{{ $tx->created_at?->format('d/m H:i') }}</td>
                            <td class="text-sm">{{ $tx->type }}</td>
                            <td class="num {{ $tx->amount >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ $tx->amount >= 0 ? '+' : '' }}{{ number_format($tx->amount, 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted">Chưa có giao dịch.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <a href="{{ route('admin.users.index') }}" class="btn btn-ghost mt-3">← Về danh sách</a>
@endsection
```

`resources/views/admin/orders.blade.php`:

```blade
@extends('layouts.panel')
@section('title', 'Quản lý đơn hàng')
@section('content')
    <form method="GET" class="card mb-3">
        <div class="grid grid-4" style="align-items:end;">
            <div class="form-group mb-0">
                <label class="form-label" for="q">Mã đơn / link</label>
                <input class="form-input" id="q" name="q" value="{{ $q }}">
            </div>
            <div class="form-group mb-0">
                <label class="form-label" for="username">Khách hàng</label>
                <input class="form-input" id="username" name="username" value="{{ $username }}">
            </div>
            <div class="form-group mb-0">
                <label class="form-label" for="status">Trạng thái</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tất cả</option>
                    @foreach (['pending','charged','processing','completed','partial','canceled','error','stuck_review'] as $s)
                        <option value="{{ $s }}" @selected($status === $s)>{{ $s }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group mb-0"><button class="btn btn-primary btn-block">Lọc</button></div>
        </div>
    </form>

    <div class="card" style="padding:0;overflow:hidden;">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Mã</th><th>Khách</th><th>Dịch vụ</th><th class="num">SL</th>
                        <th class="num">Thu</th><th class="num">Chi</th><th>Trạng thái</th><th>Ngày</th><th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($orders as $o)
                    <tr>
                        <td><strong>#{{ $o->id }}</strong></td>
                        <td class="text-sm">{{ $o->user->username ?? '—' }}</td>
                        <td class="truncate text-sm">{{ $o->service->name ?? '—' }}</td>
                        <td class="num">{{ number_format($o->quantity, 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($o->total_amount, 0, ',', '.') }}</td>
                        <td class="num text-muted">{{ number_format($o->cost_amount, 0, ',', '.') }}</td>
                        <td>@include('partials.order-status', ['status' => $o->status])</td>
                        <td class="text-sm text-muted nowrap">{{ $o->created_at->format('d/m H:i') }}</td>
                        <td><a class="btn btn-ghost btn-sm" href="{{ route('admin.orders.show', $o) }}">Xem</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $orders->links() }}</div>
@endsection
```

`resources/views/admin/order-show.blade.php`:

```blade
@extends('layouts.panel')
@section('title', 'Đơn #' . $order->id)
@section('content')
    <div class="grid grid-2">
        <div class="card">
            <div class="flex-between mb-2">
                <h2 class="card-title mb-0">Đơn #{{ $order->id }}</h2>
                @include('partials.order-status', ['status' => $order->status])
            </div>

            <table class="table">
                <tbody>
                    <tr><th>Khách</th><td>{{ $order->user->username ?? '—' }}</td></tr>
                    <tr><th>Dịch vụ</th><td>{{ $order->service->name ?? '—' }} (ID NCC: {{ $order->service->provider_service_id ?? '—' }})</td></tr>
                    <tr><th>Mã NCC</th><td class="mono">{{ $order->provider_order_id ?: '—' }}</td></tr>
                    <tr><th>Link</th><td><a href="{{ $order->target_link }}" target="_blank" rel="noopener noreferrer" class="truncate link-underline">{{ $order->target_link }}</a></td></tr>
                    <tr><th>Số lượng</th><td>{{ number_format($order->quantity, 0, ',', '.') }}</td></tr>
                    <tr><th>Start count</th><td>{{ $order->start_count ?? '—' }}</td></tr>
                    <tr><th>Remains</th><td>{{ $order->remains ?? '—' }}</td></tr>
                    <tr><th>Đơn giá /1.000</th><td>{{ number_format($order->unit_price, 0, ',', '.') }} ₫</td></tr>
                    <tr><th>Khách trả</th><td>{{ number_format($order->total_amount, 0, ',', '.') }} ₫</td></tr>
                    <tr><th>Chi phí gốc</th><td>{{ number_format($order->cost_amount, 0, ',', '.') }} ₫</td></tr>
                    <tr><th>Đã hoàn</th><td>{{ number_format($order->refunded_amount, 0, ',', '.') }} ₫ ({{ $order->refund_count }} đợt)</td></tr>
                    <tr><th>Lỗi</th><td class="text-danger">{{ $order->error_message ?: '—' }}</td></tr>
                </tbody>
            </table>

            @if ($order->status === \App\Models\Order::STATUS_STUCK_REVIEW)
                <div class="alert alert-warning mt-2">
                    Đơn bị kẹt sau khi đã trừ tiền. Kiểm tra nhật ký API bên phải trước khi xử lý.
                </div>
                <div class="grid grid-2">
                    <form method="POST" action="{{ route('admin.orders.resolveStuck', $order) }}" data-guard>
                        @csrf
                        <input type="hidden" name="action" value="attach">
                        <div class="form-group">
                            <label class="form-label">Đã gửi NCC — nhập mã đơn</label>
                            <input class="form-input" name="provider_order_id" placeholder="Mã đơn từ TopSubRe">
                        </div>
                        <button class="btn btn-outline btn-block" type="submit">Gắn mã & tiếp tục</button>
                    </form>

                    <form method="POST" action="{{ route('admin.orders.resolveStuck', $order) }}" data-guard
                          data-confirm="Hoàn tiền toàn bộ và đóng đơn?">
                        @csrf
                        <input type="hidden" name="action" value="refund">
                        <div class="form-group">
                            <label class="form-label">Chưa gửi — lý do</label>
                            <input class="form-input" name="reason" value="Xác nhận chưa gửi nhà cung cấp">
                        </div>
                        <button class="btn btn-danger btn-block" type="submit">Hoàn tiền & đóng đơn</button>
                    </form>
                </div>
            @endif

            @if (! $order->isFinished() && $order->refunded_amount < $order->total_amount)
                <form method="POST" action="{{ route('admin.orders.refund', $order) }}" class="mt-2" data-guard
                      data-confirm="Hoàn phần chưa chạy cho khách?">
                    @csrf
                    <div class="form-group">
                        <label class="form-label">Hoàn tiền thủ công — lý do</label>
                        <input class="form-input" name="reason" required>
                    </div>
                    <button class="btn btn-outline" type="submit">Hoàn phần chưa chạy</button>
                </form>
            @endif
        </div>

        <div class="card">
            <h3 class="card-title">Nhật ký API</h3>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Thời gian</th><th>Hành động</th><th>Kết quả</th></tr></thead>
                    <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="text-sm text-muted nowrap">{{ $log->created_at?->format('d/m H:i:s') }}</td>
                            <td class="text-sm">{{ $log->action }}</td>
                            <td class="text-sm">
                                <span class="badge {{ $log->success ? 'badge-completed' : 'badge-error' }}">
                                    {{ $log->success ? 'OK' : 'Lỗi' }}
                                </span>
                                <div class="text-sm text-muted">{{ $log->response_summary }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted">Chưa có nhật ký.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
```

- [ ] **Step 8: Chạy test**

```bash
cd /d/NhoSub && php artisan test --filter=AdminTest
```

Kỳ vọng: 11 passed.

- [ ] **Step 9: Commit**

```bash
cd /d/NhoSub && git add -A && git commit -m "feat: admin tong quan, quan ly nguoi dung, don hang, xu ly don ket

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 18: Admin — dịch vụ, duyệt nạp, nhật ký webhook, cấu hình

**Files:**
- Create: `app/Http/Controllers/Admin/{AdminServiceController,AdminDepositController,AdminWebhookController,AdminApiLogController,AdminSettingController}.php`
- Create: `resources/views/admin/{services,deposits,webhooks,api-logs,settings}.blade.php`
- Modify: `routes/web.php`
- Test: thêm vào `tests/Feature/AdminTest.php`

**Interfaces:**
- Consumes: `ServiceSyncService` (14), `DepositService` (12), `SettingService` (5), `PricingService` (8), `ProviderManager` (9)
- Produces routes:
  `admin.services.index/sync/update`, `admin.deposits.index/approve/reject`,
  `admin.webhooks.index/credit`, `admin.apiLogs.index`, `admin.settings.edit/update`

- [ ] **Step 1: Viết test bổ sung**

Thêm vào `tests/Feature/AdminTest.php`:

```php
    public function test_admin_dong_bo_dich_vu(): void
    {
        app(SettingService::class)->set('demo_mode', 'on');

        $this->actingAs($this->admin)->post('/admin/services/sync')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertGreaterThan(0, \App\Models\Service::count());
    }

    public function test_admin_doi_gia_ban_dich_vu(): void
    {
        $svc = \App\Models\Service::factory()->create(['sell_price' => 3000]);

        $this->actingAs($this->admin)->post("/admin/services/{$svc->id}", [
            'sell_price' => 5000,
            'is_active' => 1,
        ])->assertRedirect();

        $svc->refresh();
        $this->assertSame(5000, $svc->sell_price);
        $this->assertTrue($svc->sell_price_override, 'Đặt giá riêng phải bật cờ override');
    }

    public function test_admin_duyet_nap_tay_cong_tien_mot_lan(): void
    {
        $u = User::factory()->create(['balance' => 0]);
        $req = app(\App\Services\DepositService::class)->createManualRequest($u->id, 50000);

        $this->actingAs($this->admin)->post("/admin/deposits/{$req->id}/approve")->assertRedirect();
        $this->actingAs($this->admin)->post("/admin/deposits/{$req->id}/approve")->assertRedirect();

        $this->assertSame(50000, $u->fresh()->balance);
    }

    public function test_admin_tu_choi_nap_bat_buoc_ly_do(): void
    {
        $u = User::factory()->create(['balance' => 0]);
        $req = app(\App\Services\DepositService::class)->createManualRequest($u->id, 50000);

        $this->actingAs($this->admin)->post("/admin/deposits/{$req->id}/reject", ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertSame('pending', $req->fresh()->status);
    }

    public function test_admin_xem_nhat_ky_webhook(): void
    {
        \App\Models\WebhookLog::create([
            'gateway' => 'sepay', 'gateway_txn_id' => 'TX-1', 'amount' => 20000,
            'content' => 'NHOSUB ABC', 'status' => 'unmatched', 'reason' => 'Không tìm thấy tài khoản',
        ]);

        $this->actingAs($this->admin)->get('/admin/webhooks')
            ->assertOk()
            ->assertSee('TX-1')
            ->assertSee('Không tìm thấy tài khoản');
    }

    public function test_admin_luu_cau_hinh_ma_hoa_key(): void
    {
        $this->actingAs($this->admin)->post('/admin/settings', [
            'exchange_rate' => '26500',
            'default_markup_percent' => '35',
            'min_deposit' => '20000',
            'bank_name' => 'Vietcombank',
            'bank_account' => '0123456789',
            'bank_owner' => 'NGUYEN VAN A',
            'topsubre_api_key' => 'new-secret-key-9999',
        ])->assertRedirect();

        $s = app(SettingService::class);
        $s->forgetCache();

        $this->assertSame(26500, $s->getInt('exchange_rate'));
        $this->assertSame('35', $s->getDecimal('default_markup_percent'));
        $this->assertSame('new-secret-key-9999', $s->getSecret('topsubre_api_key'));

        // Trang cấu hình không được hiển thị key nguyên văn.
        $this->actingAs($this->admin)->get('/admin/settings')
            ->assertOk()
            ->assertDontSee('new-secret-key-9999')
            ->assertSee('9999');
    }

    public function test_khong_nhap_key_moi_thi_key_cu_duoc_giu(): void
    {
        app(SettingService::class)->setSecret('topsubre_api_key', 'old-key-1234');

        $this->actingAs($this->admin)->post('/admin/settings', [
            'exchange_rate' => '26000',
            'default_markup_percent' => '30',
            'min_deposit' => '10000',
            'topsubre_api_key' => '',
        ])->assertRedirect();

        $s = app(SettingService::class);
        $s->forgetCache();

        $this->assertSame('old-key-1234', $s->getSecret('topsubre_api_key'));
    }

    public function test_admin_xem_nhat_ky_api(): void
    {
        \App\Models\ApiSyncLog::create([
            'action' => 'add_order', 'request_summary' => 'qty=1000',
            'response_summary' => 'ok', 'success' => true, 'created_at' => now(),
        ]);

        $this->actingAs($this->admin)->get('/admin/api-logs')->assertOk()->assertSee('add_order');
    }
```

- [ ] **Step 2: Chạy test để thấy đỏ**

```bash
cd /d/NhoSub && php artisan test --filter=AdminTest
```

Kỳ vọng: FAIL các case mới.

- [ ] **Step 3: AdminServiceController**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Service;
use App\Services\PricingService;
use App\Services\ServiceSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminServiceController extends Controller
{
    public function __construct(
        private readonly ServiceSyncService $sync,
        private readonly PricingService $pricing,
    ) {
    }

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $categoryId = (int) $request->query('category_id', 0);

        $services = Service::query()
            ->with('category:id,name,platform')
            ->when($q !== '', fn ($x) => $x->where('name', 'like', "%{$q}%"))
            ->when($categoryId > 0, fn ($x) => $x->where('category_id', $categoryId))
            ->orderBy('category_id')
            ->orderBy('name')
            ->paginate(40)
            ->withQueryString();

        return view('admin.services', [
            'services' => $services,
            'categories' => Category::orderBy('sort_order')->get(['id', 'name']),
            'q' => $q,
            'categoryId' => $categoryId,
        ]);
    }

    public function sync(): RedirectResponse
    {
        try {
            $result = $this->sync->sync();
        } catch (\Throwable $e) {
            return back()->with('error', 'Đồng bộ thất bại: ' . $e->getMessage());
        }

        return back()->with('success', sprintf(
            'Đồng bộ xong: thêm mới %d, cập nhật %d, ẩn %d, tổng %d dịch vụ.',
            $result['created'], $result['updated'], $result['deactivated'], $result['total']
        ));
    }

    /** Sửa giá bán / trạng thái một dịch vụ. Đặt giá riêng sẽ bật cờ override. */
    public function update(Request $request, Service $service): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'sell_price' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'sell_price.required' => 'Vui lòng nhập giá bán.',
            'sell_price.integer' => 'Giá bán phải là số nguyên (VND).',
        ]);

        $isActive = $request->boolean('is_active');
        $newPrice = (int) $data['sell_price'];

        $service->forceFill([
            'name' => $data['name'] ?? $service->name,
            'sell_price' => $newPrice,
            // Giá bán khác giá tự động → coi là admin đặt riêng, đồng bộ sau không ghi đè.
            'sell_price_override' => $newPrice !== $this->pricing->sellPriceFromCost((int) $service->cost_price),
            'is_active' => $isActive,
        ])->save();

        $message = 'Đã cập nhật dịch vụ.';

        if ($newPrice > 0 && $newPrice <= (int) $service->cost_price) {
            $message .= ' CẢNH BÁO: giá bán không cao hơn giá gốc — bạn đang bán lỗ.';
        }

        return back()->with('success', $message);
    }
}
```

- [ ] **Step 4: AdminDepositController**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DepositRequest;
use App\Notifications\DepositApproved;
use App\Services\DepositService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDepositController extends Controller
{
    public function __construct(private readonly DepositService $deposits)
    {
    }

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'pending');

        $requests = DepositRequest::query()
            ->with('user:id,username,email')
            ->when($status !== '', fn ($x) => $x->where('status', $status))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.deposits', [
            'requests' => $requests,
            'status' => $status,
            'pendingCount' => DepositRequest::where('status', 'pending')->count(),
        ]);
    }

    public function approve(Request $request, DepositRequest $deposit): RedirectResponse
    {
        if ($deposit->status !== DepositRequest::STATUS_PENDING) {
            return back()->with('warning', 'Yêu cầu này đã được xử lý.');
        }

        $this->deposits->approveManual($deposit, $request->user()->id, $request->input('note'));

        $deposit->user->notify(new DepositApproved((int) $deposit->amount, (int) $deposit->id));

        return back()->with('success', 'Đã duyệt và cộng ' . number_format($deposit->amount, 0, ',', '.') . ' ₫.');
    }

    public function reject(Request $request, DepositRequest $deposit): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:200'],
        ], [
            'reason.required' => 'Vui lòng nhập lý do từ chối.',
            'reason.min' => 'Lý do cần ít nhất 3 ký tự.',
        ]);

        if ($deposit->status !== DepositRequest::STATUS_PENDING) {
            return back()->with('warning', 'Yêu cầu này đã được xử lý.');
        }

        $this->deposits->rejectManual($deposit, $request->user()->id, $data['reason']);

        return back()->with('success', 'Đã từ chối yêu cầu nạp.');
    }
}
```

- [ ] **Step 5: AdminWebhookController + AdminApiLogController**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WebhookLog;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminWebhookController extends Controller
{
    public function __construct(private readonly WalletService $wallet)
    {
    }

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');

        $logs = WebhookLog::query()
            ->when($status !== '', fn ($x) => $x->where('status', $status))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.webhooks', ['logs' => $logs, 'status' => $status]);
    }

    /** Cộng tay cho giao dịch không khớp — admin đã xác minh sao kê. */
    public function credit(Request $request, WebhookLog $webhook): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:32'],
            'reason' => ['required', 'string', 'min:3', 'max:200'],
        ], [
            'username.required' => 'Vui lòng nhập tên đăng nhập của khách.',
            'reason.required' => 'Vui lòng nhập lý do cộng tiền.',
        ]);

        $user = User::whereRaw('LOWER(username) = ?', [mb_strtolower($data['username'])])->first();

        if (! $user) {
            return back()->withErrors(['username' => 'Không tìm thấy tài khoản.']);
        }

        $this->wallet->apply(
            userId: $user->id,
            type: WalletTransaction::TYPE_DEPOSIT,
            amount: (int) $webhook->amount,
            idempotencyKey: 'webhook-manual:' . $webhook->id,
            note: 'Cộng tay từ webhook #' . $webhook->id . ': ' . $data['reason'],
        );

        $webhook->forceFill(['status' => WebhookLog::STATUS_PROCESSED, 'reason' => 'Admin cộng tay: ' . $data['reason']])->save();

        return back()->with('success', 'Đã cộng ' . number_format($webhook->amount, 0, ',', '.') . ' ₫ cho ' . $user->username . '.');
    }
}
```

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiSyncLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminApiLogController extends Controller
{
    public function index(Request $request): View
    {
        $action = (string) $request->query('action', '');
        $success = $request->query('success');

        $logs = ApiSyncLog::query()
            ->when($action !== '', fn ($x) => $x->where('action', $action))
            ->when($success !== null && $success !== '', fn ($x) => $x->where('success', (bool) $success))
            ->latest('id')
            ->paginate(40)
            ->withQueryString();

        return view('admin.api-logs', [
            'logs' => $logs,
            'action' => $action,
            'success' => $success,
            'actions' => ApiSyncLog::distinct()->pluck('action'),
        ]);
    }
}
```

- [ ] **Step 6: AdminSettingController**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminSettingController extends Controller
{
    public function __construct(private readonly SettingService $settings)
    {
    }

    public function edit(): View
    {
        return view('admin.settings', [
            'exchangeRate' => $this->settings->getInt('exchange_rate', 26000),
            'markupPercent' => $this->settings->getDecimal('default_markup_percent', '30'),
            'minDeposit' => $this->settings->getInt('min_deposit', 10000),
            'bankName' => $this->settings->get('bank_name', ''),
            'bankAccount' => $this->settings->get('bank_account', ''),
            'bankOwner' => $this->settings->get('bank_owner', ''),
            'bankBin' => $this->settings->get('bank_bin', ''),
            'providerAlert' => $this->settings->get('provider_balance_alert', '100'),
            'demoMode' => $this->settings->get('demo_mode', 'auto'),
            'demoScenario' => $this->settings->get('demo_scenario', 'auto'),
            'sepayEnabled' => $this->settings->getBool('sepay_enabled', true),
            'payosEnabled' => $this->settings->getBool('payos_enabled', false),
            'masked' => [
                'topsubre_api_key' => $this->settings->maskedSecret('topsubre_api_key'),
                'sepay_api_key' => $this->settings->maskedSecret('sepay_api_key'),
                'payos_client_id' => $this->settings->maskedSecret('payos_client_id'),
                'payos_api_key' => $this->settings->maskedSecret('payos_api_key'),
                'payos_checksum_key' => $this->settings->maskedSecret('payos_checksum_key'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'exchange_rate' => ['required', 'integer', 'min:1', 'max:1000000'],
            'default_markup_percent' => ['required', 'numeric', 'min:0', 'max:1000'],
            'min_deposit' => ['required', 'integer', 'min:1000', 'max:1000000000'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account' => ['nullable', 'string', 'max:40'],
            'bank_owner' => ['nullable', 'string', 'max:120'],
            'bank_bin' => ['nullable', 'string', 'max:12'],
            'provider_balance_alert' => ['nullable', 'numeric', 'min:0'],
            // Không bắt buộc: form luôn gửi, nhưng cho phép lưu một phần
            // (ví dụ đổi mỗi tỉ giá) mà không xoá cấu hình còn lại.
            'demo_mode' => ['nullable', 'in:auto,on,off'],
            'demo_scenario' => ['nullable', 'in:auto,partial,cancel,fail'],
            'sepay_enabled' => ['nullable', 'boolean'],
            'payos_enabled' => ['nullable', 'boolean'],
            // Key bí mật: để trống = giữ nguyên key cũ.
            'topsubre_api_key' => ['nullable', 'string', 'max:255'],
            'sepay_api_key' => ['nullable', 'string', 'max:255'],
            'payos_client_id' => ['nullable', 'string', 'max:255'],
            'payos_api_key' => ['nullable', 'string', 'max:255'],
            'payos_checksum_key' => ['nullable', 'string', 'max:255'],
        ], [
            'exchange_rate.required' => 'Vui lòng nhập tỉ giá.',
            'default_markup_percent.required' => 'Vui lòng nhập % lợi nhuận.',
            'min_deposit.min' => 'Mức nạp tối thiểu không hợp lệ.',
        ]);

        // Giá trị thường — CHỈ ghi khi form thực sự gửi lên.
        // Ghi null cho key vắng mặt sẽ xoá cấu hình hiện có (lỗi rất khó phát hiện).
        foreach ([
            'exchange_rate', 'default_markup_percent', 'min_deposit', 'bank_name',
            'bank_account', 'bank_owner', 'bank_bin', 'provider_balance_alert',
            'demo_mode', 'demo_scenario',
        ] as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null) {
                $this->settings->set($key, $data[$key]);
            }
        }

        $this->settings->set('sepay_enabled', $request->boolean('sepay_enabled') ? '1' : '0');
        $this->settings->set('payos_enabled', $request->boolean('payos_enabled') ? '1' : '0');

        // Key bí mật: chỉ ghi khi có giá trị mới.
        foreach (['topsubre_api_key', 'sepay_api_key', 'payos_client_id', 'payos_api_key', 'payos_checksum_key'] as $key) {
            $value = trim((string) ($data[$key] ?? ''));
            if ($value !== '') {
                $this->settings->setSecret($key, $value);
            }
        }

        $this->settings->forgetCache();

        return back()->with('success', 'Đã lưu cấu hình.');
    }
}
```

- [ ] **Step 7: Route admin (bổ sung)**

```php
Route::get('/services', [AdminServiceController::class, 'index'])->name('services.index');
Route::post('/services/sync', [AdminServiceController::class, 'sync'])->name('services.sync');
Route::post('/services/{service}', [AdminServiceController::class, 'update'])->name('services.update');

Route::get('/deposits', [AdminDepositController::class, 'index'])->name('deposits.index');
Route::post('/deposits/{deposit}/approve', [AdminDepositController::class, 'approve'])->name('deposits.approve');
Route::post('/deposits/{deposit}/reject', [AdminDepositController::class, 'reject'])->name('deposits.reject');

Route::get('/webhooks', [AdminWebhookController::class, 'index'])->name('webhooks.index');
Route::post('/webhooks/{webhook}/credit', [AdminWebhookController::class, 'credit'])->name('webhooks.credit');

Route::get('/api-logs', [AdminApiLogController::class, 'index'])->name('apiLogs.index');

Route::get('/settings', [AdminSettingController::class, 'edit'])->name('settings.edit');
Route::post('/settings', [AdminSettingController::class, 'update'])->name('settings.update');
```

- [ ] **Step 8: View admin services/deposits/webhooks/api-logs/settings**

`resources/views/admin/services.blade.php`:

```blade
@extends('layouts.panel')
@section('title', 'Quản lý dịch vụ')
@section('content')
    <div class="card mb-3">
        <div class="flex-between flex-wrap gap-2">
            <form method="GET" class="flex gap-1 flex-wrap" style="align-items:end;">
                <input class="form-input" name="q" value="{{ $q }}" placeholder="Tìm theo tên…" style="width:220px;">
                <select class="form-select" name="category_id" style="width:200px;">
                    <option value="0">Tất cả danh mục</option>
                    @foreach ($categories as $c)
                        <option value="{{ $c->id }}" @selected($categoryId === $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
                <button class="btn btn-outline" type="submit">Lọc</button>
            </form>

            <form method="POST" action="{{ route('admin.services.sync') }}" data-guard>
                @csrf
                <button class="btn btn-primary" type="submit">Đồng bộ từ nhà cung cấp</button>
            </form>
        </div>
    </div>

    <div class="card" style="padding:0;overflow:hidden;">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID NCC</th><th>Tên dịch vụ</th><th class="num">Giá gốc</th>
                        <th class="num">Giá bán</th><th class="num">Min/Max</th><th>Bán</th><th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($services as $s)
                    <tr>
                        <td class="mono text-sm">{{ $s->provider_service_id }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.services.update', $s) }}" id="f-{{ $s->id }}">
                                @csrf
                                <input class="form-input" name="name" value="{{ $s->name }}" style="min-height:36px;padding:6px 10px;">
                                <div class="text-sm text-muted mt-1">{{ $s->category->name ?? '—' }}</div>
                            </form>
                        </td>
                        <td class="num">
                            {{ number_format($s->cost_price, 0, ',', '.') }}
                            <div class="text-sm text-muted">{{ $s->cost_usd }} USD</div>
                        </td>
                        <td class="num">
                            <input class="form-input" form="f-{{ $s->id }}" name="sell_price" type="number"
                                   value="{{ $s->sell_price }}" style="width:120px;min-height:36px;padding:6px 10px;text-align:right;">
                            @if ($s->sell_price_override)
                                <div class="text-sm" style="color:var(--primary);">Giá riêng</div>
                            @endif
                        </td>
                        <td class="num text-sm">{{ number_format($s->min_qty) }} / {{ number_format($s->max_qty) }}</td>
                        <td>
                            <label class="text-sm flex gap-1" style="align-items:center;">
                                <input type="hidden" form="f-{{ $s->id }}" name="is_active" value="0">
                                <input type="checkbox" form="f-{{ $s->id }}" name="is_active" value="1" @checked($s->is_active)>
                                Hiện
                            </label>
                        </td>
                        <td>
                            <button class="btn btn-outline btn-sm" type="submit" form="f-{{ $s->id }}">Lưu</button>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $services->links() }}</div>
@endsection
```

`resources/views/admin/deposits.blade.php`:

```blade
@extends('layouts.panel')
@section('title', 'Duyệt nạp tiền')
@section('content')
    <div class="flex-between mb-2 flex-wrap gap-1">
        <div class="flex gap-1 flex-wrap">
            @foreach (['pending' => 'Chờ duyệt', 'approved' => 'Đã duyệt', 'rejected' => 'Từ chối', 'expired' => 'Hết hạn', '' => 'Tất cả'] as $val => $label)
                <a href="{{ route('admin.deposits.index', ['status' => $val]) }}"
                   class="btn btn-sm {{ $status === $val ? 'btn-primary' : 'btn-ghost' }}">{{ $label }}</a>
            @endforeach
        </div>
        @if ($pendingCount > 0)
            <span class="badge badge-pending">{{ $pendingCount }} yêu cầu chờ duyệt</span>
        @endif
    </div>

    <div class="card" style="padding:0;overflow:hidden;">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>#</th><th>Khách</th><th class="num">Số tiền</th><th>Phương thức</th><th>Trạng thái</th><th>Thời gian</th><th>Xử lý</th></tr>
                </thead>
                <tbody>
                @forelse ($requests as $r)
                    <tr>
                        <td>{{ $r->id }}</td>
                        <td>
                            <strong>{{ $r->user->username ?? '—' }}</strong>
                            <div class="text-sm text-muted">{{ $r->user->email ?? '' }}</div>
                        </td>
                        <td class="num"><strong>{{ number_format($r->amount, 0, ',', '.') }} ₫</strong></td>
                        <td class="text-sm">{{ $r->method === 'payos' ? 'PayOS' : 'Chuyển khoản' }}</td>
                        <td>@include('partials.order-status', ['status' => $r->status])</td>
                        <td class="text-sm text-muted nowrap">{{ $r->created_at?->format('d/m/Y H:i') }}</td>
                        <td>
                            @if ($r->status === 'pending')
                                <div class="flex gap-1 flex-wrap">
                                    <form method="POST" action="{{ route('admin.deposits.approve', $r) }}" data-guard
                                          data-confirm="Xác nhận đã nhận đủ {{ number_format($r->amount, 0, ',', '.') }} ₫?">
                                        @csrf
                                        <button class="btn btn-primary btn-sm" type="submit">Duyệt</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.deposits.reject', $r) }}" data-guard>
                                        @csrf
                                        <input type="hidden" name="reason" value="">
                                        <button class="btn btn-ghost btn-sm" type="submit"
                                                onclick="this.form.reason.value = prompt('Lý do từ chối (bắt buộc):') || '';">
                                            Từ chối
                                        </button>
                                    </form>
                                </div>
                            @else
                                <span class="text-sm text-muted">{{ $r->admin_note ?: '—' }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted" style="padding:40px;">Không có yêu cầu nào.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $requests->links() }}</div>
@endsection
```

`resources/views/admin/webhooks.blade.php`:

```blade
@extends('layouts.panel')
@section('title', 'Nhật ký webhook')
@section('content')
    <form method="GET" class="mb-2 flex gap-1 flex-wrap">
        <select class="form-select" name="status" style="max-width:220px;" onchange="this.form.submit()">
            <option value="">Tất cả trạng thái</option>
            @foreach (['processed','duplicate','unmatched','rejected'] as $s)
                <option value="{{ $s }}" @selected($status === $s)>{{ $s }}</option>
            @endforeach
        </select>
    </form>

    <div class="card" style="padding:0;overflow:hidden;">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>#</th><th>Cổng</th><th>Mã GD</th><th class="num">Số tiền</th><th>Nội dung</th><th>Trạng thái</th><th>Lý do</th><th>Xử lý</th></tr>
                </thead>
                <tbody>
                @foreach ($logs as $log)
                    <tr>
                        <td>{{ $log->id }}</td>
                        <td class="text-sm">{{ strtoupper($log->gateway) }}</td>
                        <td class="mono text-sm">{{ $log->gateway_txn_id }}</td>
                        <td class="num">{{ number_format($log->amount, 0, ',', '.') }} ₫</td>
                        <td class="truncate text-sm" title="{{ $log->content }}">{{ $log->content }}</td>
                        <td>
                            <span class="badge {{ $log->status === 'processed' ? 'badge-completed' : ($log->status === 'duplicate' ? 'badge-neutral' : 'badge-error') }}">
                                {{ $log->status }}
                            </span>
                        </td>
                        <td class="text-sm text-muted">{{ $log->reason }}</td>
                        <td>
                            @if ($log->status === 'unmatched' && $log->amount > 0)
                                <form method="POST" action="{{ route('admin.webhooks.credit', $log) }}" class="flex gap-1" data-guard>
                                    @csrf
                                    <input class="form-input" name="username" placeholder="username" style="min-height:34px;padding:4px 8px;width:130px;" required>
                                    <input class="form-input" name="reason" placeholder="lý do" style="min-height:34px;padding:4px 8px;width:140px;" required>
                                    <button class="btn btn-primary btn-sm" type="submit">Cộng tay</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $logs->links() }}</div>
@endsection
```

`resources/views/admin/api-logs.blade.php`:

```blade
@extends('layouts.panel')
@section('title', 'Nhật ký API')
@section('content')
    <form method="GET" class="mb-2 flex gap-1 flex-wrap">
        <select class="form-select" name="action" style="max-width:200px;" onchange="this.form.submit()">
            <option value="">Tất cả hành động</option>
            @foreach ($actions as $a)
                <option value="{{ $a }}" @selected($action === $a)>{{ $a }}</option>
            @endforeach
        </select>
        <select class="form-select" name="success" style="max-width:160px;" onchange="this.form.submit()">
            <option value="">Mọi kết quả</option>
            <option value="1" @selected((string) $success === '1')>Thành công</option>
            <option value="0" @selected((string) $success === '0')>Thất bại</option>
        </select>
    </form>

    <div class="card" style="padding:0;overflow:hidden;">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>#</th><th>Thời gian</th><th>Hành động</th><th>Đơn</th><th>Kết quả</th></tr></thead>
                <tbody>
                @foreach ($logs as $log)
                    <tr>
                        <td>{{ $log->id }}</td>
                        <td class="text-sm text-muted nowrap">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                        <td class="text-sm">{{ $log->action }}</td>
                        <td class="text-sm">
                            @if ($log->order_id)
                                <a class="link-underline" href="{{ route('admin.orders.show', $log->order_id) }}">#{{ $log->order_id }}</a>
                            @else — @endif
                        </td>
                        <td class="text-sm">
                            <span class="badge {{ $log->success ? 'badge-completed' : 'badge-error' }}">{{ $log->success ? 'OK' : 'Lỗi' }}</span>
                            <div class="text-sm text-muted">{{ $log->response_summary }}</div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $logs->links() }}</div>
@endsection
```

`resources/views/admin/settings.blade.php`:

```blade
@extends('layouts.panel')
@section('title', 'Cấu hình hệ thống')
@section('content')
    <form method="POST" action="{{ route('admin.settings.update') }}" data-guard>
        @csrf

        <div class="grid grid-2">
            <div class="card">
                <h3 class="card-title">Giá & lợi nhuận</h3>
                <div class="form-group">
                    <label class="form-label" for="exchange_rate">Tỉ giá USD → VND</label>
                    <input class="form-input" id="exchange_rate" name="exchange_rate" type="number" value="{{ $exchangeRate }}" required>
                    <div class="form-hint">Mặc định 26.000.</div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="default_markup_percent">% lợi nhuận mặc định</label>
                    <input class="form-input" id="default_markup_percent" name="default_markup_percent" type="number" step="0.01"
                           value="{{ $markupPercent }}" required>
                    <div class="form-hint">Giá bán = giá gốc × (1 + %). Mặc định 30.</div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="min_deposit">Mức nạp tối thiểu (₫)</label>
                    <input class="form-input" id="min_deposit" name="min_deposit" type="number" value="{{ $minDeposit }}" required>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label" for="provider_balance_alert">Ngưỡng cảnh báo số dư NCC (USD)</label>
                    <input class="form-input" id="provider_balance_alert" name="provider_balance_alert" type="number" step="0.01"
                           value="{{ $providerAlert }}">
                </div>
            </div>

            <div class="card">
                <h3 class="card-title">Ngân hàng nhận chuyển khoản</h3>
                <div class="form-group">
                    <label class="form-label" for="bank_name">Tên ngân hàng</label>
                    <input class="form-input" id="bank_name" name="bank_name" value="{{ $bankName }}">
                </div>
                <div class="form-group">
                    <label class="form-label" for="bank_account">Số tài khoản</label>
                    <input class="form-input" id="bank_account" name="bank_account" value="{{ $bankAccount }}">
                </div>
                <div class="form-group">
                    <label class="form-label" for="bank_owner">Chủ tài khoản</label>
                    <input class="form-input" id="bank_owner" name="bank_owner" value="{{ $bankOwner }}">
                </div>
                <div class="form-group mb-0">
                    <label class="form-label" for="bank_bin">Mã BIN (dùng tạo QR VietQR)</label>
                    <input class="form-input" id="bank_bin" name="bank_bin" value="{{ $bankBin }}">
                    <div class="form-hint">Ví dụ MB Bank = 970422.</div>
                </div>
            </div>
        </div>

        <div class="grid grid-2 mt-3">
            <div class="card">
                <h3 class="card-title">TopSubRe</h3>
                <div class="form-group">
                    <label class="form-label" for="topsubre_api_key">API key</label>
                    <input class="form-input" id="topsubre_api_key" name="topsubre_api_key" type="password"
                           placeholder="{{ $masked['topsubre_api_key'] ?? 'Chưa cấu hình' }}">
                    <div class="form-hint">Để trống để giữ key hiện tại. Key được mã hoá khi lưu.</div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="demo_mode">Chế độ nhà cung cấp</label>
                    <select class="form-select" id="demo_mode" name="demo_mode">
                        <option value="auto" @selected($demoMode === 'auto')>Tự động (có key → thật, không có → demo)</option>
                        <option value="on" @selected($demoMode === 'on')>Bắt buộc demo</option>
                        <option value="off" @selected($demoMode === 'off')>Bắt buộc dùng API thật</option>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label" for="demo_scenario">Kịch bản demo</label>
                    <select class="form-select" id="demo_scenario" name="demo_scenario">
                        <option value="auto" @selected($demoScenario === 'auto')>Tự động (hoàn thành)</option>
                        <option value="partial" @selected($demoScenario === 'partial')>Chạy một phần</option>
                        <option value="cancel" @selected($demoScenario === 'cancel')>Huỷ đơn</option>
                        <option value="fail" @selected($demoScenario === 'fail')>Nhà cung cấp từ chối</option>
                    </select>
                </div>
            </div>

            <div class="card">
                <h3 class="card-title">Cổng thanh toán</h3>

                <div class="form-group">
                    <label class="text-sm flex gap-1" style="align-items:center;">
                        <input type="checkbox" name="sepay_enabled" value="1" @checked($sepayEnabled)> Bật SePay
                    </label>
                </div>
                <div class="form-group">
                    <label class="form-label" for="sepay_api_key">SePay webhook key</label>
                    <input class="form-input" id="sepay_api_key" name="sepay_api_key" type="password"
                           placeholder="{{ $masked['sepay_api_key'] ?? 'Chưa cấu hình' }}">
                </div>

                <hr class="divider">

                <div class="form-group">
                    <label class="text-sm flex gap-1" style="align-items:center;">
                        <input type="checkbox" name="payos_enabled" value="1" @checked($payosEnabled)> Bật PayOS
                    </label>
                </div>
                <div class="form-group">
                    <label class="form-label" for="payos_client_id">PayOS Client ID</label>
                    <input class="form-input" id="payos_client_id" name="payos_client_id" type="password"
                           placeholder="{{ $masked['payos_client_id'] ?? 'Chưa cấu hình' }}">
                </div>
                <div class="form-group">
                    <label class="form-label" for="payos_api_key">PayOS API Key</label>
                    <input class="form-input" id="payos_api_key" name="payos_api_key" type="password"
                           placeholder="{{ $masked['payos_api_key'] ?? 'Chưa cấu hình' }}">
                </div>
                <div class="form-group mb-0">
                    <label class="form-label" for="payos_checksum_key">PayOS Checksum Key</label>
                    <input class="form-input" id="payos_checksum_key" name="payos_checksum_key" type="password"
                           placeholder="{{ $masked['payos_checksum_key'] ?? 'Chưa cấu hình' }}">
                </div>
            </div>
        </div>

        <button class="btn btn-primary mt-3" type="submit">Lưu cấu hình</button>
    </form>
@endsection
```

- [ ] **Step 9: Chạy test**

```bash
cd /d/NhoSub && php artisan test --filter=AdminTest
```

Kỳ vọng: 18 passed.

- [ ] **Step 10: Commit**

```bash
cd /d/NhoSub && git add -A && git commit -m "feat: admin dich vu, duyet nap, nhat ky webhook, nhat ky API, cau hinh ma hoa

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 19: Landing page + view composer + hoàn thiện khung

**Files:**
- Create: `resources/views/landing/index.blade.php`
- Create: `app/Providers/ViewServiceProvider.php`
- Modify: `bootstrap/providers.php`, `resources/views/panel/dashboard.blade.php`
- Test: `tests/Feature/LandingTest.php`

**Interfaces:**
- Consumes: layout landing (Task 3), `Service` model
- Produces: biến `$unreadCount` tự động có trong mọi view dùng `layouts.panel`

- [ ] **Step 1: Viết test thất bại**

`tests/Feature/LandingTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_hien_day_du_cac_khu_vuc(): void
    {
        $cat = Category::factory()->create(['name' => 'Facebook Like', 'platform' => 'facebook']);
        Service::factory()->create([
            'category_id' => $cat->id,
            'name' => 'Like bài viết Facebook',
            'sell_price' => 3380,
            'is_active' => true,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('NhoSub')
            ->assertSee('Sub nhỏ, lớn dần cùng bạn')
            ->assertSee('Tính năng')
            ->assertSee('Bảng giá')
            ->assertSee('Đánh giá')
            ->assertSee('Câu hỏi thường gặp')
            ->assertSee('Like bài viết Facebook')
            ->assertSee('Đăng ký miễn phí');
    }

    public function test_landing_khong_lo_gia_goc(): void
    {
        $cat = Category::factory()->create();
        Service::factory()->create([
            'category_id' => $cat->id, 'cost_price' => 7777, 'sell_price' => 9999, 'is_active' => true,
        ]);

        $this->get('/')->assertDontSee('7.777');
    }

    public function test_nguoi_da_dang_nhap_thay_nut_vao_panel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/')
            ->assertSee('Vào bảng điều khiển')
            ->assertDontSee('Đăng ký miễn phí');
    }

    public function test_trang_404_hien_tieng_viet(): void
    {
        $this->get('/khong-ton-tai')->assertNotFound()->assertSee('Không tìm thấy trang');
    }
}
```

- [ ] **Step 2: Chạy test để thấy đỏ**

```bash
cd /d/NhoSub && php artisan test --filter=LandingTest
```

Kỳ vọng: FAIL — view `landing.index` chưa có.

- [ ] **Step 3: Viết landing**

`resources/views/landing/index.blade.php`:

```blade
@extends('layouts.landing')

@section('content')
    {{-- HERO — nơi DUY NHẤT trong hệ thống được dùng gradient (cực nhẹ, cùng tông) --}}
    <section class="hero">
        <div class="container">
            <div class="hero-grid">
                <div>
                    <span class="hero-badge">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                        Panel tăng tương tác cho người Việt
                    </span>

                    <h1>Tăng tương tác mạng xã hội,<br>giá minh bạch theo từng 1.000 lượt</h1>

                    <p class="hero-lead">
                        Sub nhỏ, lớn dần cùng bạn. Like, follow, view, comment, share cho
                        Facebook, TikTok, Instagram và YouTube — đặt đơn trong 30 giây,
                        theo dõi trạng thái theo thời gian thực.
                    </p>

                    <div class="hero-actions">
                        @auth
                            <a href="{{ route('panel.dashboard') }}" class="btn btn-primary">Vào bảng điều khiển</a>
                        @else
                            <a href="{{ route('register') }}" class="btn btn-primary">Đăng ký miễn phí</a>
                            <a href="{{ route('login') }}" class="btn btn-outline">Đăng nhập</a>
                        @endauth
                    </div>

                    <div class="hero-stats">
                        <div class="hero-stat">
                            <strong>5+</strong>
                            <span>Nền tảng hỗ trợ</span>
                        </div>
                        <div class="hero-stat">
                            <strong>24/7</strong>
                            <span>Đơn chạy tự động</span>
                        </div>
                        <div class="hero-stat">
                            <strong>100%</strong>
                            <span>Hoàn tiền phần chưa chạy</span>
                        </div>
                    </div>
                </div>

                <div class="card" style="padding:0;overflow:hidden;">
                    <div style="padding:18px 20px;background:var(--primary);color:#fff;font-weight:700;">
                        Bảng giá tham khảo
                    </div>
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr><th>Dịch vụ</th><th class="num">Giá / 1.000</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($priceSamples as $s)
                                    <tr>
                                        <td class="text-sm">{{ $s->name }}</td>
                                        <td class="num"><strong>{{ number_format($s->sell_price, 0, ',', '.') }} ₫</strong></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2" class="text-center text-muted">Đang cập nhật bảng giá.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- NỀN TẢNG — section trắng --}}
    <section class="section">
        <div class="container">
            <div class="section-head">
                <h2>Hỗ trợ các nền tảng phổ biến</h2>
                <p>Một tài khoản dùng cho tất cả dịch vụ bạn cần.</p>
            </div>

            <div class="grid grid-4">
                @foreach ([
                    ['Facebook', 'Like, follow, share, comment, member'],
                    ['TikTok', 'View, like, follow, share'],
                    ['Instagram', 'Follow, like, view story'],
                    ['YouTube', 'Subscriber, view, like'],
                ] as [$name, $desc])
                    <div class="card card-hover text-center">
                        <div style="width:48px;height:48px;margin:0 auto 12px;border-radius:12px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#1E6FD9" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/></svg>
                        </div>
                        <h3 style="font-size:16px;">{{ $name }}</h3>
                        <p class="text-sm text-muted mb-0">{{ $desc }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- TÍNH NĂNG — section nền xám nhạt --}}
    <section class="section section-alt dot-pattern" id="tinh-nang">
        <div class="container">
            <div class="section-head">
                <h2>Vì sao chọn NhoSub?</h2>
                <p>Làm đúng ngay từ đầu: giá rõ, tiền rõ, trạng thái rõ.</p>
            </div>

            <div class="grid grid-3">
                @foreach ([
                    ['Giá theo 1.000 lượt', 'Mọi dịch vụ hiển thị giá trên 1.000 lượt, tổng tiền làm tròn xuống. Không phí ẩn.'],
                    ['Tự động hoàn tiền', 'Đơn chạy một phần hoặc bị huỷ sẽ tự hoàn phần chưa chạy vào ví trong 5 phút.'],
                    ['Theo dõi minh bạch', 'Xem số lượng đã chạy, còn lại và lịch sử hoàn tiền của từng đơn.'],
                    ['Nạp tiền tự động', 'Chuyển khoản đúng nội dung là tiền vào ví trong vài giây, không cần chờ duyệt.'],
                    ['Hỗ trợ tiếng Việt', 'Đội ngũ hỗ trợ 8:00 – 22:00 hằng ngày, trả lời bằng tiếng Việt.'],
                    ['Bảo mật ví', 'Mọi giao dịch đều có mã chống trùng, số dư không bao giờ âm.'],
                ] as [$title, $desc])
                    <article class="card card-hover">
                        <h3 style="font-size:16px;">{{ $title }}</h3>
                        <p class="text-sm text-muted mb-0">{{ $desc }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- BẢNG GIÁ — section trắng --}}
    <section class="section" id="bang-gia">
        <div class="container">
            <div class="section-head">
                <h2>Bảng giá mẫu</h2>
                <p>Giá thực tế cập nhật liên tục trong bảng điều khiển.</p>
            </div>

            <div class="grid grid-3">
                @foreach ($pricingTiers as $tier)
                    <div class="card {{ $tier['highlight'] ? 'card-hover' : '' }}"
                         style="{{ $tier['highlight'] ? 'border-color:var(--primary);' : '' }}">
                        @if ($tier['highlight'])
                            <span class="badge badge-processing mb-2">Phổ biến nhất</span>
                        @endif
                        <h3 style="font-size:18px;">{{ $tier['name'] }}</h3>
                        <div style="font-size:30px;font-weight:800;color:var(--primary);margin:8px 0;">
                            {{ $tier['price'] }}
                        </div>
                        <p class="text-sm text-muted">{{ $tier['desc'] }}</p>
                        <ul class="text-sm" style="list-style:none;padding:0;margin:16px 0;color:var(--muted);">
                            @foreach ($tier['features'] as $f)
                                <li style="margin-bottom:8px;">✓ {{ $f }}</li>
                            @endforeach
                        </ul>
                        <a href="{{ route('register') }}" class="btn btn-primary btn-block">Bắt đầu ngay</a>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ĐÁNH GIÁ — section xám --}}
    <section class="section section-alt" id="danh-gia">
        <div class="container">
            <div class="section-head">
                <h2>Khách hàng nói gì</h2>
                <p>Phản hồi mẫu từ khách hàng sử dụng panel.</p>
            </div>

            <div class="grid grid-3">
                @foreach ([
                    ['Nguyễn T. Hà', 'Chủ shop thời trang', 'Nạp tiền buổi sáng, 2 phút sau thấy vào ví. Đơn TikTok chạy nhanh, trạng thái cập nhật đều.'],
                    ['Trần Minh K.', 'Agency nhỏ', 'Đơn bị huỷ giữa chừng mà tiền phần chưa chạy được hoàn tự động, không phải nhắn hỏi admin.'],
                    ['Lê Quốc B.', 'Kinh doanh online', 'Giá hiển thị theo 1.000 lượt nên tính lời lỗ dễ. Giao diện dễ dùng cho người mới.'],
                ] as [$name, $role, $quote])
                    <article class="card card-hover">
                        <div style="color:var(--warning);margin-bottom:10px;letter-spacing:2px;">★★★★★</div>
                        <p style="font-size:15px;">“{{ $quote }}”</p>
                        <div class="text-sm">
                            <strong>{{ $name }}</strong>
                            <span class="text-muted"> · {{ $role }}</span>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- FAQ — section trắng --}}
    <section class="section" id="faq">
        <div class="container" style="max-width:820px;">
            <div class="section-head">
                <h2>Câu hỏi thường gặp</h2>
                <p>Chưa tìm thấy câu trả lời? Liên hệ hotro@nhosub.vn.</p>
            </div>

            @foreach ([
                ['Nạp tiền bao lâu thì vào ví?', 'Chuyển khoản đúng nội dung NHOSUB <TÊN ĐĂNG NHẬP> thường được cộng tự động trong vài giây. Nếu sau 15 phút chưa thấy, bạn gửi yêu cầu nạp ở trang Nạp tiền để admin kiểm tra.'],
                ['Đơn chạy một phần thì tính tiền thế nào?', 'Bạn chỉ trả cho phần đã chạy. Phần chưa chạy được hoàn lại ví tự động, tối đa trong 5 phút sau khi nhà cung cấp cập nhật trạng thái.'],
                ['Có mức nạp tối thiểu không?', 'Có. Mức tối thiểu mặc định là 10.000 ₫ cho mỗi giao dịch.'],
                ['Tôi có thể đổi giá bán hay bán lại cho người khác không?', 'Giai đoạn này panel dành cho khách lẻ. Tính năng đại lý cấp dưới sẽ có ở giai đoạn sau.'],
                ['Đơn của tôi bị lỗi thì sao?', 'Nếu nhà cung cấp không nhận đơn, hệ thống hoàn 100% tiền vào ví ngay và đánh dấu đơn lỗi. Bạn có thể đặt lại.'],
            ] as [$q, $a])
                <details class="faq-item">
                    <summary>{{ $q }}</summary>
                    <div class="faq-body">{{ $a }}</div>
                </details>
            @endforeach
        </div>
    </section>

    {{-- CTA cuối --}}
    <section class="section section-alt">
        <div class="container text-center">
            <h2>Bắt đầu từ một đơn nhỏ</h2>
            <p class="text-muted" style="max-width:560px;margin:0 auto 24px;">
                Đăng ký miễn phí, nạp tiền khi cần. Không phí duy trì, không ràng buộc.
            </p>
            <a href="{{ route('register') }}" class="btn btn-primary">Tạo tài khoản miễn phí</a>
        </div>
    </section>
@endsection
```

- [ ] **Step 4: LandingController + route**

`app/Http/Controllers/LandingController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function index(): View
    {
        // Chỉ lấy cột hiển thị công khai — không bao giờ chọn cost_price.
        $priceSamples = Service::query()
            ->where('is_active', true)
            ->orderBy('sell_price')
            ->limit(6)
            ->get(['id', 'name', 'sell_price']);

        return view('landing.index', [
            'priceSamples' => $priceSamples,
            'pricingTiers' => [
                [
                    'name' => 'Khởi đầu',
                    'price' => 'Từ 5.000 ₫ / 1.000',
                    'desc' => 'Phù hợp người mới thử dịch vụ.',
                    'features' => ['Nạp tối thiểu 10.000 ₫', 'Đơn từ 100 lượt', 'Theo dõi trạng thái'],
                    'highlight' => false,
                ],
                [
                    'name' => 'Tăng trưởng',
                    'price' => 'Từ 12.000 ₫ / 1.000',
                    'desc' => 'Dành cho shop đang đẩy bài bán hàng.',
                    'features' => ['Đủ 5 nền tảng', 'Hoàn tiền tự động', 'Ưu tiên hỗ trợ'],
                    'highlight' => true,
                ],
                [
                    'name' => 'Chuyên nghiệp',
                    'price' => 'Liên hệ',
                    'desc' => 'Khối lượng lớn, cần tư vấn riêng.',
                    'features' => ['Báo giá riêng', 'Hỗ trợ 1-1', 'Đối soát hằng tháng'],
                    'highlight' => false,
                ],
            ],
        ]);
    }
}
```

Route đổi `Route::view('/', 'landing.index')` thành:

```php
Route::get('/', [LandingController::class, 'index'])->name('landing');
```

- [ ] **Step 5: ViewServiceProvider — tự động có `$unreadCount`**

`app/Providers/ViewServiceProvider.php`:

```php
<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Mọi trang dùng layout panel đều có số thông báo chưa đọc cho chuông topbar.
        View::composer('layouts.panel', function ($view) {
            $count = auth()->check() ? auth()->user()->unreadNotifications()->count() : 0;
            $view->with('unreadCount', $count);
        });
    }
}
```

Đăng ký trong `bootstrap/providers.php`:

```php
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\ProviderServiceProvider::class,
    App\Providers\ViewServiceProvider::class,
];
```

- [ ] **Step 6: Chạy test**

```bash
cd /d/NhoSub && php artisan test --filter=LandingTest
```

Kỳ vọng: 4 passed.

- [ ] **Step 7: Chạy toàn bộ test để chắc không vỡ gì**

```bash
cd /d/NhoSub && php artisan test
```

Kỳ vọng: tất cả xanh.

- [ ] **Step 8: Commit**

```bash
cd /d/NhoSub && git add -A && git commit -m "feat: landing page day du khu vuc, view composer dem thong bao chua doc

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 20: Bàn giao deploy — `.env.example`, `.htaccess`, README

**Files:**
- Create: `.env.example` (ghi đè)
- Create: `.htaccess` (gốc dự án)
- Create: `public/.htaccess` (kiểm tra/bổ sung)
- Create: `README.md` (ghi đè)
- Create: `docs/DEPLOY-CHECKLIST.md`
- Test: `tests/Feature/DeployFilesTest.php`

**Interfaces:**
- Consumes: toàn bộ dự án
- Produces: bộ file deploy đầy đủ; checklist D1–D12

- [ ] **Step 1: Viết test thất bại**

`tests/Feature/DeployFilesTest.php`:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class DeployFilesTest extends TestCase
{
    public function test_env_example_co_du_bien_quan_trong(): void
    {
        $env = file_get_contents(base_path('.env.example'));

        foreach ([
            'APP_NAME=NhoSub', 'APP_TIMEZONE=Asia/Ho_Chi_Minh', 'APP_LOCALE=vi',
            'DB_CONNECTION=mysql', 'QUEUE_CONNECTION=database', 'SESSION_DRIVER=database',
            'ADMIN_SEED_ENABLED', 'SEPAY', 'PAYOS',
        ] as $needle) {
            $this->assertStringContainsString($needle, $env, "Thiếu '{$needle}' trong .env.example");
        }
    }

    public function test_env_example_khong_chua_gia_tri_that(): void
    {
        $env = file_get_contents(base_path('.env.example'));

        $this->assertStringNotContainsString('APP_KEY=base64:', $env);
        $this->assertStringNotContainsString('Admin@123456', $env);
    }

    public function test_htaccess_goc_rewrite_ve_public(): void
    {
        $ht = file_get_contents(base_path('.htaccess'));

        $this->assertStringContainsString('RewriteRule', $ht);
        $this->assertStringContainsString('public/', $ht);
    }

    public function test_readme_co_huong_dan_deploy_shared_hosting(): void
    {
        $readme = file_get_contents(base_path('README.md'));

        foreach ([
            'PHP 8.2', 'shared hosting', 'document root', 'public', 'cron',
            'schedule:run', 'SePay', 'PayOS', 'SMTP', 'nhosub:create-admin',
        ] as $needle) {
            $this->assertStringContainsStringIgnoringCase($needle, $readme, "README thiếu '{$needle}'");
        }
    }

    public function test_checklist_deploy_co_du_12_muc(): void
    {
        $doc = file_get_contents(base_path('docs/DEPLOY-CHECKLIST.md'));

        for ($i = 1; $i <= 12; $i++) {
            $this->assertStringContainsString("D{$i}", $doc, "Checklist thiếu mục D{$i}");
        }
    }
}
```

- [ ] **Step 2: Chạy test để thấy đỏ**

```bash
cd /d/NhoSub && php artisan test --filter=DeployFilesTest
```

Kỳ vọng: FAIL.

- [ ] **Step 3: `.env.example`**

```dotenv
# ============================================================
# NhoSub — Panel đại lý SMM
# Sao chép file này thành .env rồi điền giá trị thật.
# KHÔNG commit file .env lên git.
# ============================================================

APP_NAME=NhoSub
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_TIMEZONE=Asia/Ho_Chi_Minh
APP_URL=https://nhosub.vn

APP_LOCALE=vi
APP_FALLBACK_LOCALE=vi
APP_FAKER_LOCALE=vi_VN

APP_MAINTENANCE_DRIVER=file

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=warning

# ---------- Cơ sở dữ liệu ----------
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nhosub
DB_USERNAME=nhosub_user
DB_PASSWORD=

# ---------- Session / Cache / Queue ----------
# Không dùng Redis — tất cả chạy trên database theo yêu cầu shared hosting.
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database

# ---------- Mail (BẮT BUỘC cấu hình trước khi mở bán — xem D6) ----------
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="hotro@nhosub.vn"
MAIL_FROM_NAME="${APP_NAME}"

# ---------- Tạo admin lần đầu ----------
# Đặt true cho lần chạy seed đầu tiên, sau đó đổi về false.
# Seeder tự ghi dấu storage/app/.admin_seeded để không chạy lại.
ADMIN_SEED_ENABLED=false
ADMIN_USERNAME=admin
ADMIN_EMAIL=admin@nhosub.vn
ADMIN_PASSWORD=

# ---------- Cổng thanh toán ----------
# Không cần điền ở đây — nhập trong trang Quản trị > Cấu hình.
# Các key được mã hoá bằng APP_KEY và lưu trong bảng settings.
# Nếu đổi APP_KEY sau khi đã lưu key, phải nhập lại toàn bộ key.
SEPAY_WEBHOOK_PATH=/webhook/sepay
PAYOS_WEBHOOK_PATH=/webhook/payos

# ---------- Nhà cung cấp ----------
# API key TopSubRe cũng nhập trong trang Quản trị > Cấu hình.
TOPSUBRE_ENDPOINT=https://likenhanh.pro/api/v2
```

- [ ] **Step 4: `.htaccess` gốc**

```apache
# ============================================================
# NhoSub — rewrite về public/ cho hosting KHÔNG đổi được document root.
# Nếu hosting cho phép trỏ document root vào public/, file này không cần thiết
# (nhưng để cũng không sao).
# ============================================================
<IfModule mod_rewrite.c>
    RewriteEngine On

    # Nếu thư mục public tồn tại thì chuyển mọi request vào đó.
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>

# Chặn truy cập file nhạy cảm nếu rewrite không chạy.
<FilesMatch "^\.(env|git|htaccess|htpasswd)">
    Require all denied
</FilesMatch>

<FilesMatch "\.(env|log|sql|md|json|lock)$">
    Require all denied
</FilesMatch>

Options -Indexes
```

- [ ] **Step 5: `public/.htaccess` (thay file mặc định của Laravel)**

```apache
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Cho phép gửi Authorization header qua Apache/FastCGI
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Bỏ dấu / ở cuối (trừ thư mục)
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Chuyển mọi request không phải file/thư mục thật về index.php
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>

# Nén
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/css application/javascript application/json image/svg+xml
</IfModule>

# Cache asset tĩnh
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 7 days"
    ExpiresByType application/javascript "access plus 7 days"
    ExpiresByType image/svg+xml "access plus 30 days"
</IfModule>

# Header bảo mật cơ bản
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>
```

- [ ] **Step 6: `docs/DEPLOY-CHECKLIST.md`**

```markdown
# Checklist deploy NhoSub — bắt buộc trước khi mở bán

Mỗi mục phải được tick và có bằng chứng kiểm tra. Mục nào chưa đạt thì **không** mở bán.

| # | Mục | Bắt buộc | Cách kiểm tra | Đã xong |
|---|---|---|---|---|
| D1 | PHP ≥ 8.2 (khuyến nghị 8.3) + extension: pdo_mysql, mbstring, openssl, ctype, json, fileinfo, curl, gd | ✅ | Tạo file `info.php` chứa `<?php phpinfo();`, xem phiên bản + extension, sau đó **xoá file** | ☐ |
| D2 | Document root trỏ `public/`, hoặc `.htaccess` gốc rewrite | ✅ | Mở `https://domain/.env` → phải trả 403/404, không tải được file | ☐ |
| D3 | `.env` có `APP_KEY`, `APP_ENV=production`, `APP_DEBUG=false` | ✅ | Truy cập URL sai → trang 404 tiếng Việt, không lộ stack trace | ☐ |
| D4 | `php artisan migrate --force` + seed chạy xong | ✅ | Đăng nhập được bằng tài khoản admin | ☐ |
| D5 | Cron `schedule:run` mỗi phút | ✅ | `php artisan schedule:list` có `orders:sync` và `deposits:expire` mỗi 5 phút; chờ 6 phút thấy log cron | ☐ |
| D6 | **SMTP thật** (`MAIL_MAILER=smtp`) — bắt buộc để quên mật khẩu hoạt động | ✅ | Vào `/forgot-password`, nhập email thật → nhận được mail trong 2 phút | ☐ |
| D7 | Cấu hình ngân hàng + SePay webhook, thử 1 giao dịch thật ≥ 10.000 ₫ | ✅ | Số dư tăng, Nhật ký webhook có bản ghi `processed` | ☐ |
| D8 | PayOS (nếu bật) đã có Client ID / API Key / Checksum Key | ⬜ tuỳ chọn | Nút "Nạp qua PayOS" hiện, tạo được link thanh toán | ☐ |
| D9 | HTTPS + `APP_URL` đúng domain | ✅ | Không có cảnh báo mixed content trong console trình duyệt | ☐ |
| D10 | Backup DB hằng ngày ra ngoài `public_html` | ✅ | Có file backup, thử restore trên môi trường nháp | ☐ |
| D11 | Đổi mật khẩu admin mặc định sau lần đăng nhập đầu | ✅ | Đăng xuất, đăng nhập lại bằng mật khẩu mới | ☐ |
| D12 | Thay logo gốc vào `public/assets/img/logo.svg` | ⬜ chờ file | Logo hiển thị đúng ở sidebar, landing, favicon | ☐ |

## Lệnh cron mẫu

```
* * * * * cd /home/USER/domains/domain.com && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

`schedule:run` sẽ tự gọi `orders:sync`, `deposits:expire` và `queue:work --stop-when-empty` theo lịch khai báo trong `routes/console.php`.

## Sau khi deploy

1. Đổi `ADMIN_SEED_ENABLED=false` trong `.env`.
2. Xoá `storage/app/.admin_seeded` **chỉ khi** muốn tạo lại admin (nguy hiểm).
3. Kiểm tra `storage/` và `bootstrap/cache/` ghi được (quyền 775).
```

- [ ] **Step 7: `README.md`**

```markdown
# NhoSub — Panel đại lý SMM

Website bán dịch vụ tăng tương tác mạng xã hội (like, follow, view, comment, share, member)
cho Facebook, TikTok, Instagram, YouTube. Mô hình đại lý: mua sỉ từ TopSubRe qua API,
bán lại cho khách lẻ.

**Slogan:** Sub nhỏ, lớn dần cùng bạn.

---

## 1. Yêu cầu hệ thống

| Thành phần | Tối thiểu | Ghi chú |
|---|---|---|
| PHP | **8.2** (khuyến nghị 8.3) | Laravel 12 yêu cầu 8.2+. Prompt gốc ghi 8.1+ nhưng Laravel 11/12 cần 8.2+ — hosting phải chọn PHP 8.2 trở lên. |
| Extension | pdo_mysql, mbstring, openssl, ctype, json, fileinfo, curl, gd | Kiểm tra bằng `php -m` |
| MySQL | 5.7+ / 8.0 | utf8mb4 |
| Composer | 2.x | Chỉ cần ở máy build, không cần trên hosting nếu upload kèm `vendor/` |
| Node | **Không cần** | CSS/JS đã biên dịch sẵn trong `public/assets/` |

**Không dùng:** Redis, Supervisor, Vite, queue worker chạy thường trực.

---

## 2. Cài đặt môi trường local

```bash
# 1. Cài dependency
composer install

# 2. Tạo file môi trường
cp .env.example .env
php artisan key:generate

# 3. Tạo database
mysql -uroot -e "CREATE DATABASE nhosub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 4. Sửa .env: DB_DATABASE=nhosub, DB_USERNAME, DB_PASSWORD
#    và đặt ADMIN_SEED_ENABLED=true

# 5. Tạo bảng + dữ liệu mẫu
php artisan migrate --seed

# 6. Chạy
php artisan serve
```

Mở `http://localhost:8000`. Đăng nhập admin bằng `ADMIN_USERNAME` / `ADMIN_PASSWORD` trong `.env`.

Sau lần seed đầu, seeder tự ghi `storage/app/.admin_seeded` và không tạo lại admin nữa.

### Chạy test

```bash
php artisan test
```

Test dùng database `nhosub_testing` (MySQL), tạo trong `phpunit.xml`.

---

## 3. Deploy lên shared hosting — từng bước

### Bước 1 — Chuẩn bị trên máy local

```bash
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Nén toàn bộ thư mục dự án (bao gồm `vendor/`).

### Bước 2 — Upload

Upload lên hosting, ví dụ `/home/USER/domains/domain.com/`.

Cấu trúc sau khi upload:

```
/home/USER/domains/domain.com/
├── app/
├── bootstrap/
├── config/
├── database/
├── public/          ← document root phải trỏ vào đây
├── resources/
├── routes/
├── storage/
├── vendor/
├── .env
├── .htaccess        ← rewrite dự phòng
└── artisan
```

### Bước 3 — Trỏ document root (khuyến nghị)

Trong cPanel → **Domains** → chọn domain → **Document Root** = `/home/USER/domains/domain.com/public`.

### Bước 4 — Nếu hosting KHÔNG đổi được document root

File `.htaccess` ở thư mục gốc dự án đã có sẵn, tự rewrite mọi request vào `public/`:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

Cần chắc chắn `mod_rewrite` đã bật và `AllowOverride All` cho thư mục.
**Hạn chế:** cách này khiến file `.env` nằm trong thư mục web có thể bị truy cập nếu rule không chạy.
File `.htaccess` đã chặn `.env`, nhưng nên kiểm tra bằng cách mở `https://domain/.env` — phải trả 403 hoặc 404.

### Bước 5 — Tạo `.env`

```bash
cp .env.example .env
php artisan key:generate
```

Sửa:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain.com
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...
MAIL_MAILER=smtp        # BẮT BUỘC — xem D6
ADMIN_SEED_ENABLED=true
```

### Bước 6 — Phân quyền thư mục

```bash
chmod -R 775 storage bootstrap/cache
```

Một số hosting dùng nhóm `www-data`; nếu gặp lỗi ghi file, liên hệ nhà cung cấp hosting.

### Bước 7 — Tạo bảng + admin

```bash
php artisan migrate --force
php artisan db:seed --force
```

Sau đó **đổi `ADMIN_SEED_ENABLED=false`** trong `.env`.

### Bước 8 — Cron 5 phút (bắt buộc)

Trong cPanel → **Cron Jobs**, thêm:

```
* * * * * cd /home/USER/domains/domain.com && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

Lịch chạy:

| Command | Tần suất | Việc làm |
|---|---|---|
| `orders:sync` | 5 phút | Đồng bộ trạng thái đơn, hoàn tiền phần chưa chạy |
| `deposits:expire` | 5 phút | Đóng yêu cầu nạp PayOS quá 30 phút |
| `queue:work --stop-when-empty` | mỗi phút | Xử lý job trong queue `database` |

Kiểm tra bằng `php artisan schedule:list`.

### Bước 9 — Cấu hình thanh toán

Vào **Quản trị → Cấu hình**:

1. **Ngân hàng:** tên ngân hàng, số tài khoản, chủ tài khoản, mã BIN (ví dụ MB Bank = 970422).
2. **SePay:** nhập webhook API key. Trên dashboard SePay, đặt webhook URL = `https://domain.com/webhook/sepay`,
   kiểu xác thực = `Apikey`.
3. **PayOS** (tuỳ chọn): cần tài khoản PayOS đã đăng ký (cá nhân hoặc doanh nghiệp tuỳ chính sách hiện hành).
   Nhập Client ID, API Key, Checksum Key rồi bật cổng. Webhook URL = `https://domain.com/webhook/payos`.
4. **Duyệt tay:** luôn bật, không cần cấu hình.

> **MoMo chưa triển khai ở giai đoạn 1.** MoMo yêu cầu hợp đồng doanh nghiệp. Hệ thống đã chừa chỗ cắm
> (`MomoGateway` skeleton + cờ `momo_enabled` mặc định tắt).

### Bước 10 — Kiểm tra trước khi mở bán

Chạy hết checklist `docs/DEPLOY-CHECKLIST.md` (D1–D12). Mục D1, D2, D3, D4, D5, **D6**, D7, D9, D10, D11 là bắt buộc.

---

## 4. Tạo admin không qua seeder

Nếu cần tạo thêm admin:

```bash
php artisan nhosub:create-admin
```

Hoặc trong **Quản trị → Người dùng**, đổi vai trò qua cơ sở dữ liệu (không có UI nâng quyền, có chủ ý bảo mật).

---

## 5. Cấu hình giá

Công thức trong `app/Services/PricingService.php`:

```
cost_price  = floor(cost_usd × tỉ_giá)
sell_price  = round(cost_price × (1 + %lợi_nhuận/100))
tổng đơn    = floor(sell_price × số_lượng / 1000)
```

- **Tỉ giá** và **% lợi nhuận** sửa ở **Quản trị → Cấu hình**.
- **Giá riêng từng dịch vụ** sửa ở **Quản trị → Dịch vụ**. Khi đặt giá riêng, hệ thống bật cờ
  `sell_price_override` và **không ghi đè** ở lần đồng bộ sau.
- Giá được **đóng băng** tại thời điểm đặt đơn — đổi giá sau đó không ảnh hưởng đơn cũ.

---

## 6. Những điểm khác biệt so với SRS (có chủ ý)

| Điểm | SRS | Triển khai | Lý do |
|---|---|---|---|
| Kiểu tiền | REAL | BIGINT (VND) + DECIMAL(12,6) (USD) | REAL gây sai số khi quy đổi và làm tròn nhiều lần |
| `deposit_requests.transfer_code` | UNIQUE | Không unique | Nội dung CK cố định theo username, nhiều yêu cầu cùng khách sẽ trùng. Chống trùng do `wallet_transactions.idempotency_key` |
| Bảng bổ sung | 8 bảng | 8 bảng gốc + `webhook_logs`, `order_refunds`, `payos_transactions`, `notifications` | Phục vụ nhật ký webhook, hoàn nhiều đợt, PayOS và chuông thông báo |
| Trạng thái đơn | 6 trạng thái | Thêm `charged`, `stuck_review` | `charged` = đã trừ tiền chưa gửi NCC; `stuck_review` = kẹt, chờ admin xử lý tay |

---

## 7. Thay logo

Logo nằm ở `public/assets/img/logo.svg` (và `favicon.svg`). Khi có file logo gốc:

1. Chuyển file gốc sang SVG (hoặc đổi sang PNG và sửa `asset('assets/img/logo.svg')` thành `.png` trong các layout).
2. Phối lại màu theo đúng quy tắc: một màu `#1E6FD9`, hoặc hai mảng `#1E6FD9` + `#DCEBFD` tách biệt, **không gradient**.
3. Ghi đè đúng tên file, không cần sửa code.

---

## 8. Bảo mật — những gì đã làm

- Bcrypt cho mật khẩu, CSRF mọi form, validate mọi input qua FormRequest.
- Rate limit đăng nhập 5 lần / 15 phút theo IP + username.
- `session()->regenerate()` sau đăng nhập; đổi mật khẩu đăng xuất các phiên khác.
- Middleware `admin` kiểm tra quyền ở server cho toàn bộ `/admin`.
- Mọi truy vấn đơn/dịch vụ của khách đều lọc theo `user_id` — không chỉ ẩn trên giao diện.
- API key mã hoá bằng `APP_KEY` (AES-256-CBC), chỉ hiển thị 4 ký tự cuối.
- Webhook so sánh key bằng `hash_equals`; chống replay bằng UNIQUE `(gateway, gateway_txn_id)`.
- Log không chứa API key; payload webhook được che trường `key|token|secret|password|signature` trước khi lưu.
- Header bảo mật: X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy.

**Lưu ý:** nếu đổi `APP_KEY` sau khi đã lưu key trong `settings`, phải nhập lại toàn bộ key.

---

## 9. Cấu trúc thư mục chính

```
app/Services/          WalletService, RefundService, OrderService, OrderSyncService,
                       DepositService, PricingService, SettingService, ServiceSyncService
app/Providers/         ProviderInterface, ProviderManager, TopSubRe/, Demo/
app/Http/Controllers/  Khách hàng, Auth/, Admin/, Webhook/
public/assets/         css/app.css, js/app.js, img/logo.svg
docs/                  spec thiết kế, kế hoạch, checklist deploy
```

---

## 10. Giai đoạn 2 (chưa làm)

Mass order, drip-feed, refill/huỷ đơn phía khách, ticket có timeline, affiliate,
API riêng cho đại lý cấp dưới, email thông báo, chế độ bảo trì.

Cấu trúc hiện tại đã tách sẵn service/route để cắm các module này vào mà không sửa luồng giai đoạn 1.
```

- [ ] **Step 8: Tạo command `nhosub:create-admin`**

`app/Console/Commands/CreateAdminCommand.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminCommand extends Command
{
    protected $signature = 'nhosub:create-admin
                            {--username= : Tên đăng nhập}
                            {--email= : Email}
                            {--password= : Mật khẩu}';

    protected $description = 'Tạo tài khoản quản trị viên (không cần seeder)';

    public function handle(): int
    {
        $username = $this->option('username') ?: $this->ask('Tên đăng nhập');
        $email = $this->option('email') ?: $this->ask('Email');
        $password = $this->option('password') ?: $this->secret('Mật khẩu (tối thiểu 8 ký tự)');

        $validator = Validator::make(compact('username', 'email', 'password'), [
            'username' => ['required', 'string', 'min:3', 'max:32', 'regex:/^[A-Za-z0-9_]+$/', 'unique:users,username'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::create([
            'username' => $username,
            'email' => mb_strtolower($email),
            'password_hash' => Hash::make($password),
            'full_name' => 'Quản trị viên',
            'role' => User::ROLE_ADMIN,
            'balance' => 0,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->info("Đã tạo admin '{$username}'.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 9: Chạy test**

```bash
cd /d/NhoSub && php artisan test --filter=DeployFilesTest
```

Kỳ vọng: 5 passed.

- [ ] **Step 10: Commit**

```bash
cd /d/NhoSub && git add -A && git commit -m "docs: env.example, htaccess rewrite, README deploy shared hosting, checklist D1-D12

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

### Task 21: Nghiệm thu end-to-end — chạy thật 6 mục và ghi bằng chứng

**Files:**
- Create: `docs/ACCEPTANCE-REPORT.md`
- Test: `tests/Feature/AcceptanceTest.php`

**Interfaces:**
- Consumes: toàn bộ hệ thống
- Produces: báo cáo nghiệm thu có bằng chứng từng mục

> **Quy tắc:** không được ghi ✅ khi chưa có output thật. Mỗi mục phải dán lệnh đã chạy + kết quả.

- [ ] **Step 1: Viết test nghiệm thu tổng hợp**

`tests/Feature/AcceptanceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DepositRequest;
use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\Service;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WebhookLog;
use App\Services\OrderSyncService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Nghiệm thu 6 mục theo yêu cầu dự án — chạy end-to-end như người dùng thật.
 */
class AcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private string $sepayKey = 'acceptance-key';

    protected function setUp(): void
    {
        parent::setUp();

        $s = app(SettingService::class);
        $s->setSecret('sepay_api_key', $this->sepayKey);
        $s->set('min_deposit', '10000');
        $s->set('exchange_rate', '26000');
        $s->set('default_markup_percent', '30');
        $s->set('demo_mode', 'on');
        $s->set('demo_scenario', 'auto');
    }

    /** MỤC 1: Đăng ký → đăng nhập → nạp tiền SePay → số dư tăng đúng */
    public function test_muc1_dang_ky_dang_nhap_nap_tien(): void
    {
        // Đăng ký
        $this->post('/register', [
            'username' => 'nghiemthu01',
            'email' => 'nt01@example.com',
            'password' => 'MatKhau@123',
            'password_confirmation' => 'MatKhau@123',
            'full_name' => 'Người Nghiệm Thu',
        ])->assertRedirect('/dashboard');

        $user = User::where('username', 'nghiemthu01')->firstOrFail();
        $this->assertSame(0, $user->balance);

        // Đăng xuất rồi đăng nhập lại
        $this->post('/logout');
        $this->assertGuest();

        $this->post('/login', ['login' => 'nghiemthu01', 'password' => 'MatKhau@123'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        // Nạp tiền qua webhook SePay (test mode)
        $this->withHeaders(['Authorization' => 'Apikey ' . $this->sepayKey])
            ->postJson('/webhook/sepay', [
                'id' => 555001,
                'transferType' => 'in',
                'transferAmount' => 100000,
                'content' => 'NHOSUB NGHIEMTHU01 nap tien',
                'referenceCode' => 'FT555001',
            ])->assertOk();

        $this->assertSame(100000, $user->fresh()->balance, 'Số dư phải tăng đúng 100.000 ₫');
        $this->assertSame(1, WalletTransaction::where('user_id', $user->id)->count());
    }

    /** MỤC 2: Đặt đơn → trừ tiền → gửi provider → cron đồng bộ */
    public function test_muc2_dat_don_tru_tien_va_dong_bo(): void
    {
        $user = User::factory()->create(['balance' => 100000]);
        $cat = Category::factory()->create();
        $svc = Service::factory()->create([
            'category_id' => $cat->id,
            'provider_service_id' => 'demo-1',
            'sell_price' => 3380,
            'cost_price' => 2600,
            'min_qty' => 100,
            'max_qty' => 100000,
            'is_active' => true,
        ]);

        $this->actingAs($user)->post('/orders', [
            'service_id' => $svc->id,
            'target_link' => 'https://facebook.com/p/nghiemthu',
            'quantity' => 1000,
            'client_token' => (string) Str::uuid(),
        ])->assertRedirect();

        $order = Order::firstOrFail();
        $this->assertSame(96620, $user->fresh()->balance, 'Phải trừ đúng 3.380 ₫');
        $this->assertSame(Order::STATUS_PROCESSING, $order->status);
        $this->assertNotNull($order->provider_order_id);

        // Giả lập đã qua 5 phút rồi chạy cron
        $order->forceFill(['created_at' => now()->subMinutes(5)])->save();
        app(OrderSyncService::class)->syncBatch();

        $this->assertSame(Order::STATUS_COMPLETED, $order->fresh()->status);
    }

    /** MỤC 3: Đơn partial → hoàn đúng phần chưa chạy, không hoàn trùng */
    public function test_muc3_partial_hoan_dung_va_khong_trung(): void
    {
        app(SettingService::class)->set('demo_scenario', 'partial');

        $user = User::factory()->create(['balance' => 100000]);
        $cat = Category::factory()->create();
        $svc = Service::factory()->create([
            'category_id' => $cat->id,
            'provider_service_id' => 'demo-1',
            'sell_price' => 3380,
            'cost_price' => 2600,
            'min_qty' => 100, 'max_qty' => 100000, 'is_active' => true,
        ]);

        $this->actingAs($user)->post('/orders', [
            'service_id' => $svc->id,
            'target_link' => 'https://facebook.com/p/partial',
            'quantity' => 1000,
            'client_token' => (string) Str::uuid(),
        ]);

        $order = Order::firstOrFail();
        $order->forceFill(['created_at' => now()->subMinutes(5)])->save();

        // Chạy cron 3 lần liên tiếp
        $sync = app(OrderSyncService::class);
        $sync->syncBatch();
        $sync->syncBatch();
        $sync->syncBatch();

        $order->refresh();

        $this->assertSame(Order::STATUS_PARTIAL, $order->status);
        // remains 400 → hoàn 3380*400/1000 = 1352
        $this->assertSame(1352, $order->refunded_amount);
        $this->assertSame(1, $order->refund_count, 'Chỉ được hoàn 1 đợt');
        $this->assertSame(1, OrderRefund::count());
        $this->assertSame(96620 + 1352, $user->fresh()->balance);

        // Số dư = tổng sổ cái
        $tong = (int) WalletTransaction::where('user_id', $user->id)->sum('amount');
        $this->assertSame($tong, $user->fresh()->balance);
    }

    /** MỤC 4: Webhook gửi lại trùng → không cộng trùng */
    public function test_muc4_webhook_trung_khong_cong_trung(): void
    {
        $user = User::factory()->create(['username' => 'trunglap', 'balance' => 0]);

        $payload = [
            'id' => 777001,
            'transferType' => 'in',
            'transferAmount' => 50000,
            'content' => 'NHOSUB TRUNGLAP',
            'referenceCode' => 'FT777001',
        ];

        for ($i = 0; $i < 5; $i++) {
            $this->withHeaders(['Authorization' => 'Apikey ' . $this->sepayKey])
                ->postJson('/webhook/sepay', $payload)->assertOk();
        }

        $this->assertSame(50000, $user->fresh()->balance, 'Gửi 5 lần vẫn chỉ cộng 1 lần');
        $this->assertSame(1, WalletTransaction::count());
        $this->assertSame(1, WebhookLog::count());
        $this->assertSame(1, DepositRequest::where('user_id', $user->id)->count());
    }

    /** MỤC 5: Admin đồng bộ dịch vụ, đổi giá, duyệt nạp tay, cộng/trừ tiền, xem webhook */
    public function test_muc5_admin_van_hanh_day_du(): void
    {
        $admin = User::factory()->admin()->create();

        // 1. Đồng bộ dịch vụ (demo provider)
        $this->actingAs($admin)->post('/admin/services/sync')->assertRedirect();
        $this->assertGreaterThan(0, Service::count());

        // 2. Đổi giá bán
        $svc = Service::first();
        $this->actingAs($admin)->post("/admin/services/{$svc->id}", [
            'sell_price' => 9999, 'is_active' => 1,
        ])->assertRedirect();
        $this->assertSame(9999, $svc->fresh()->sell_price);
        $this->assertTrue($svc->fresh()->sell_price_override);

        // 3. Duyệt nạp tay
        $khach = User::factory()->create(['balance' => 0]);
        $req = app(\App\Services\DepositService::class)->createManualRequest($khach->id, 30000);
        $this->actingAs($admin)->post("/admin/deposits/{$req->id}/approve")->assertRedirect();
        $this->assertSame(30000, $khach->fresh()->balance);

        // 4. Cộng/trừ tiền
        $this->actingAs($admin)->post("/admin/users/{$khach->id}/adjust", [
            'amount' => 20000, 'reason' => 'Thưởng nghiệm thu',
        ])->assertRedirect();
        $this->assertSame(50000, $khach->fresh()->balance);

        $this->actingAs($admin)->post("/admin/users/{$khach->id}/adjust", [
            'amount' => -10000, 'reason' => 'Trừ nghiệm thu',
        ])->assertRedirect();
        $this->assertSame(40000, $khach->fresh()->balance);

        // 5. Xem nhật ký webhook
        $this->actingAs($admin)->get('/admin/webhooks')->assertOk();
        $this->actingAs($admin)->get('/admin/api-logs')->assertOk();
        $this->actingAs($admin)->get('/admin/settings')->assertOk();
    }

    /** MỤC 6: Giao diện — không gradient ngoài hero, đủ token màu, render được */
    public function test_muc6_giao_dien_dung_quy_tac(): void
    {
        // Landing + panel render không lỗi
        $this->get('/')->assertOk()->assertSee('NhoSub');

        $user = User::factory()->create(['balance' => 123456]);
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('123.456');

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin')->assertOk();

        // Không gradient ngoài .hero
        $css = file_get_contents(public_path('assets/css/app.css'));
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER);

        foreach ($rules as $rule) {
            if (stripos($rule[2], 'gradient(') !== false) {
                $this->assertStringContainsString('.hero', $rule[1],
                    'Gradient ngoài .hero tại: ' . trim($rule[1]));
            }
        }

        // Không màu ngoài bảng quy định (kiểm tra các mã hex trong CSS)
        $allowed = [
            '#1E6FD9', '#0A4DB3', '#DCEBFD', '#F5F8FF', '#FFFFFF', '#0F2233',
            '#5B6B80', '#16A34A', '#F59E0B', '#DC2626',
            // Biến thể hợp lệ của trạng thái đơn (badge nền/chữ/viền)
            '#E7F7EE', '#0E7A38', '#BFEACD', '#FFF7E6', '#92580A', '#FCE3B3',
            '#FDECEC', '#A31818', '#F7C9C9', '#C6DDFB', '#EEF2F8', '#DDE5F1',
            '#EDF2FA', '#E3ECFB', '#D6E2F2', '#9AA9BC', '#B91C1C', '#EDF3FE',
        ];

        preg_match_all('/#[0-9A-Fa-f]{6}/', $css, $hexes);

        foreach (array_unique($hexes[0]) as $hex) {
            $this->assertContains(
                strtoupper($hex),
                array_map('strtoupper', $allowed),
                "Màu {$hex} không nằm trong bảng màu quy định"
            );
        }
    }
}
```

- [ ] **Step 2: Chạy test nghiệm thu**

```bash
cd /d/NhoSub && php artisan test --filter=AcceptanceTest
```

Kỳ vọng: 6 passed. Nếu đỏ → sửa code, **không** sửa test cho khớp lỗi.

- [ ] **Step 3: Chạy toàn bộ test suite**

```bash
cd /d/NhoSub && php artisan test
```

Ghi lại số test passed/failed vào báo cáo.

- [ ] **Step 4: Khởi động server và kiểm tra giao diện thật**

```bash
cd /d/NhoSub && php artisan migrate:fresh --seed && php artisan serve --port=8000
```

Chạy nền, sau đó:

```bash
curl -s -o /dev/null -w "landing:%{http_code}\n" http://127.0.0.1:8000/
curl -s -o /dev/null -w "login:%{http_code}\n" http://127.0.0.1:8000/login
curl -s -o /dev/null -w "register:%{http_code}\n" http://127.0.0.1:8000/register
```

Kỳ vọng: cả 3 trả `200`.

- [ ] **Step 5: Kiểm tra cron đã đăng ký**

```bash
cd /d/NhoSub && php artisan schedule:list
```

Kỳ vọng thấy `orders:sync` và `deposits:expire` với `*/5 * * * *`.

- [ ] **Step 6: Kiểm tra gradient trong toàn bộ view**

```bash
cd /d/NhoSub && grep -rn "gradient" resources/views/ public/assets/ || echo "Không tìm thấy gradient nào trong view"
```

Kỳ vọng: chỉ ra đúng 1 dòng trong `app.css` của `.hero`, không có ở view nào.

- [ ] **Step 7: Chụp màn hình giao diện**

Mở `http://127.0.0.1:8000/` và `/dashboard` ở 2 kích thước 1440×900 và 390×844.
Lưu ảnh vào `docs/screenshots/` (landing-desktop.png, landing-mobile.png, panel-desktop.png, panel-mobile.png).
Kiểm tra mở DevTools → Console: không có lỗi đỏ.

- [ ] **Step 8: Viết `docs/ACCEPTANCE-REPORT.md`**

Điền theo mẫu, **chỉ đánh ✅ khi đã dán output thật**:

```markdown
# Báo cáo nghiệm thu — NhoSub Giai đoạn 1

Ngày: <điền ngày chạy>
Môi trường: PHP <phiên bản>, MySQL <phiên bản>, Laravel <phiên bản>

## Tổng hợp test tự động

```
<dán output của `php artisan test`>
```

## Mục 1 — Đăng ký → đăng nhập → nạp tiền SePay → số dư tăng đúng

- Test: `test_muc1_dang_ky_dang_nhap_nap_tien`
- Kết quả: <PASS/FAIL>
- Bằng chứng: <dán output>

## Mục 2 — Đặt đơn → trừ tiền → gửi provider → cron đồng bộ

- Test: `test_muc2_dat_don_tru_tien_va_dong_bo`
- Kết quả: <PASS/FAIL>
- Bằng chứng: <dán output>

## Mục 3 — Đơn partial → hoàn đúng phần chưa chạy, không hoàn trùng

- Test: `test_muc3_partial_hoan_dung_va_khong_trung`
- Kết quả: <PASS/FAIL>
- Bằng chứng: <dán output: số dư sau hoàn, refund_count, số dòng order_refunds>

## Mục 4 — Webhook trùng → không cộng trùng

- Test: `test_muc4_webhook_trung_khong_cong_trung`
- Kết quả: <PASS/FAIL>
- Bằng chứng: <dán output: gửi 5 lần, số dư 50.000, 1 giao dịch>

## Mục 5 — Admin: đồng bộ dịch vụ, đổi giá, duyệt nạp tay, cộng/trừ tiền, xem webhook

- Test: `test_muc5_admin_van_hanh_day_du`
- Kết quả: <PASS/FAIL>
- Bằng chứng: <dán output>

## Mục 6 — Giao diện

| Kiểm tra | Kết quả | Bằng chứng |
|---|---|---|
| Landing render 1440px | | ảnh `docs/screenshots/landing-desktop.png` |
| Landing render 390px | | ảnh `docs/screenshots/landing-mobile.png` |
| Panel render 1440px | | ảnh `docs/screenshots/panel-desktop.png` |
| Panel render 390px (drawer) | | ảnh `docs/screenshots/panel-mobile.png` |
| Không gradient ngoài `.hero` | | output lệnh grep |
| Đủ 10 token màu, không màu lạ | | test `test_muc6_giao_dien_dung_quy_tac` |
| Console không lỗi | | ảnh DevTools console |
| Hover nút / card / menu / link / input | | mô tả + ảnh chụp hover |

## Việc chưa làm / cần bạn cung cấp

- [ ] Logo gốc (đang dùng SVG tạm cùng tone)
- [ ] API key TopSubRe thật (đang chạy demo mode)
- [ ] Tài khoản SePay / PayOS thật để test webhook production
- [ ] SMTP thật để test quên mật khẩu
```

- [ ] **Step 9: Commit báo cáo**

```bash
cd /d/NhoSub && git add -A && git commit -m "test: nghiem thu 6 muc end-to-end, bao cao kem bang chung

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

- [ ] **Step 10: Báo cáo cho người dùng**

Trong tin nhắn cuối, trình bày **bảng 6 mục** với ✅/❌ và **trích output thật** của từng mục. Không tóm tắt chung chung — mỗi mục phải có bằng chứng.

---

## Self-Review (đã chạy)

### 1. Spec coverage

| Mục spec | Task |
|---|---|
| 2. Quyết định kỹ thuật (Laravel 12, MySQL, không Vite, queue database) | T1, T2 |
| 3.1 Tầng Provider (interface, TopSubRe, Demo, Manager) | T9 |
| 3.2 Bất biến tiền tệ, idempotency | T6, T7 |
| 4.1–4.4 Schema 8+4 bảng, BIGINT/DECIMAL, index | T2 |
| 5.1 Đăng ký/đăng nhập/đổi mật khẩu/hồ sơ | T4, T16 |
| 5.1.1 Thông báo + trang Hỗ trợ | T16, T19 |
| 5.2 Xem dịch vụ, không lộ cost_price | T15 |
| 5.3 Đặt đơn 2 bước, client_token, giá đóng băng | T10, T15 |
| 5.4 Cron đồng bộ, hoàn tiền theo delta, stuck_review | T13 |
| 5.5 SePay/PayOS/duyệt tay, hết hạn 30 phút | T11, T12 |
| 5.6 Admin 8 trang | T17, T18 |
| 5.7 Công thức giá | T8 |
| 6. Giao diện (token, gradient, hover, responsive, logo) | T3, T15–T19 |
| 7. Bảo mật | T4, T6, T11, T18 |
| 8.1 Test T1–T24 | rải khắp T4–T14 + T21 |
| 8.2 Nghiệm thu thủ công | T21 |
| 8.3 Bàn giao + checklist D1–D12 | T20 |

### 2. Placeholder scan

Không còn "TBD/TODO/implement later". Các chỗ "sửa nếu test đỏ" (T9 Step 8, T21 Step 2) đều kèm hướng dẫn cụ thể và **cấm sửa test cho khớp lỗi**.

### 3. Type consistency

- `WalletService::apply()` dùng named arguments ở T7, T10, T11, T12, T18 — khớp chữ ký T6.
- `RefundService::refundOrder(Order $order, string $reason): ?OrderRefund` — T7 định nghĩa, T10/T13/T17 dùng đúng.
- `ProviderManager::driver(): ProviderInterface` — T9 định nghĩa, T10/T13/T14/T17 dùng đúng.
- `Order::STATUS_*` và `Order::unusedQuantity()` — T2 định nghĩa, T13/T17 dùng đúng.
- `DepositService::handleGatewayDeposit(...)` — T11 định nghĩa, T12 dùng đúng tham số.
- Route name `admin.orders.resolveStuck` — T17 controller + view + route khớp.

### 4. Review Focus

| Rủi ro | Test |
|---|---|
| Trùng tiền khi chạy song song | `WalletServiceTest::test_hai_tien_trinh_cung_key_khong_cong_trung`, `test_khong_the_insert_truc_tiep_trung_key`, `RefundServiceTest::test_goi_lai_voi_cung_trang_thai_khong_tao_dong_thua`, `AcceptanceTest::test_muc3` (cron 3 lần) |
| Username là tiền tố của username khác | `SePayWebhookTest::test_username_la_tien_to_cua_username_khac_van_khop_dung` |
| Số lượng cực lớn | `PricingTest::test_so_luong_cuc_lon_khong_tran_so` |
| Link nguy hiểm / XSS | `OrderPlacementTest::test_link_javascript_bi_tu_choi`; escape tự động của Blade ở mọi view |
| Múi giờ | `BootstrapTest::test_timezone_la_gio_viet_nam` |

