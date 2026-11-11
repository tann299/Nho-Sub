# Hướng dẫn deploy NhoSub lên 123host (DirectAdmin) — thủ công

Gói cần dùng: `D:\NhoSub-deploy\nhosub-deploy.zip` (đã gồm vendor production, không cần chạy composer trên hosting).

> Đã kiểm tra: tài khoản `befdgfnt` hiện **không đăng nhập được** cả FTP (lỗi 530) và DirectAdmin (lỗi 401) — tức là mật khẩu chưa đúng, không phải bị chặn IP. Cần lấy lại mật khẩu trước khi bắt đầu.

---

## Bước 0 — Lấy đúng mật khẩu

1. Vào https://123host.vn → **Đăng nhập khách hàng** (bằng email bạn đăng ký dịch vụ)
2. **Dịch vụ của tôi** → chọn hosting `free02` → **Đăng nhập DirectAdmin** (nút này đăng nhập 1 lần, không cần gõ mật khẩu)
3. Trong DirectAdmin: **Tài khoản FTP** (`/evo/user/ftp-accounts`) → **Đặt lại mật khẩu** cho `befdgfnt`, hoặc tạo tài khoản FTP mới
4. Ghi lại: *user FTP*, *mật khẩu*, và **mật khẩu DirectAdmin** (menu **Sửa mật khẩu** góc phải)

Nếu quên mật khẩu DirectAdmin: ở bước 2 bấm **Đăng nhập DirectAdmin** rồi đổi mật khẩu mới luôn.

---

## Bước 1 — Tạo cơ sở dữ liệu

Trong DirectAdmin:

1. **Cơ sở dữ liệu MySQL** → **Tạo cơ sở dữ liệu**
2. Điền:
   - Tên database: `nhosub`
   - Tên người dùng: `nhosub`
   - Mật khẩu: *(tự đặt một mật khẩu mạnh — ghi ngoài, KHÔNG commit lên repo)*
3. Bấm **Tạo**. DirectAdmin sẽ đặt tiền tố tài khoản, ví dụ:
   - Database đầy đủ: `befdgfnt_nhosub`
   - User đầy đủ: `befdgfnt_nhosub`
4. **Ghi lại chính xác 3 giá trị này** — sẽ dán vào `.env`

---

## Bước 2 — Upload code

### Cách A: File Manager của DirectAdmin (khuyên dùng)

1. DirectAdmin → **Trình quản lý tệp** (File Manager)
2. Vào thư mục gốc `/home/befdgfnt/`
3. **Tải lên** file `nhosub-deploy.zip` (8 MB)
4. Chuột phải file vừa upload → **Giải nén** (Extract) → giải nén ngay tại `/home/befdgfnt/`
5. Sau khi giải nén, trong `/home/befdgfnt/` phải thấy: `app/`, `bootstrap/`, `config/`, `database/`, `public/`, `resources/`, `routes/`, `storage/`, `vendor/`, `artisan`, `composer.json`, `.htaccess`
6. **Xoá file zip** sau khi giải nén xong

> ⚠️ Nếu hosting đã có sẵn `public_html` với file cũ: xoá các file mặc định (index.html, default.php…) trước.

### Cách B: FileZilla (nếu thích kéo thả)

1. FileZilla → **File → Site Manager → New site**
   - Host: `free02.123host.vn`
   - Protocol: **FTP**
   - Encryption: **Require explicit FTP over TLS**
   - Logon Type: Normal
   - User: `befdgfnt`
   - Password: *(mật khẩu FTP ở Bước 0)*
2. Kết nối → upload `nhosub-deploy.zip` vào `/`
3. Quay lại DirectAdmin File Manager để **giải nén** (FileZilla không giải nén được)

---

## Bước 3 — Trỏ document root vào `public/`

Đây là bước **quan trọng nhất** để bảo mật (`.env` không bị tải về được).

### Nếu domain trỏ thẳng vào `/home/befdgfnt/`

File `.htaccess` trong gói đã tự rewrite mọi request vào `public/`. Kiểm tra: mở `https://domain-cua-ban/.env` → phải ra **403 hoặc 404**, không tải được file.

### Nếu domain có `public_html` riêng

DirectAdmin thường đặt: `/home/befdgfnt/domains/<domain>/public_html`

Làm như sau:
1. Upload + giải nén zip vào `/home/befdgfnt/` (như Bước 2)
2. Trong File Manager, vào `/home/befdgfnt/domains/<domain>/`
3. Xoá thư mục `public_html` (hoặc đổi tên thành `public_html_old`)
4. Tạo **symlink** hoặc **trỏ document root**:
   - **Cách 1 (khuyên dùng)**: DirectAdmin → **Domain Setup** → chọn domain → **Document Root** sửa thành `/home/befdgfnt/public`
   - **Cách 2**: Xoá `public_html`, rồi tạo lại bằng File Manager với **đường dẫn đích** trỏ về `../public` (tuỳ DA có hỗ trợ)
   - **Cách 3**: Copy toàn bộ nội dung `public/` vào `public_html/`, rồi sửa `public_html/index.php`:
     ```php
     require __DIR__.'/../vendor/autoload.php';
     $app = require_once __DIR__.'/../bootstrap/app.php';
     ```
     và copy `public/.htaccess` vào `public_html/.htaccess`. Nhớ copy cả thư mục `assets/`.

---

## Bước 4 — Tạo file `.env`

1. File Manager → `/home/befdgfnt/` → **Tạo file mới** tên `.env`
2. Chuột phải → **Sửa** → dán nội dung dưới, **thay các chỗ `<...>`**:

```env
APP_NAME=NhoSub
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_TIMEZONE=Asia/Ho_Chi_Minh
APP_URL=https://domain-cua-ban

APP_LOCALE=vi
APP_FALLBACK_LOCALE=vi
APP_FAKER_LOCALE=vi_VN

APP_MAINTENANCE_DRIVER=file

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=befdgfnt_nhosub
DB_USERNAME=befdgfnt_nhosub
DB_PASSWORD=<mật khẩu DB ở Bước 1>

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=<email gửi thư>
MAIL_PASSWORD=<app password Gmail 16 ký tự>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="hotro@domain-cua-ban"
MAIL_FROM_NAME="${APP_NAME}"

SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

ADMIN_SEED_ENABLED=true
ADMIN_USERNAME=admin
ADMIN_EMAIL=admin@domain-cua-ban
ADMIN_PASSWORD=<MAT_KHAU_ADMIN_MANH> *(tự đặt)*

TOPSUBRE_ENDPOINT=https://likenhanh.pro/api/v2
```

> **APP_KEY** để trống ở bước này, sẽ sinh ở Bước 5.

---

## Bước 5 — Chạy lệnh cài đặt

### Cách A: Terminal trong DirectAdmin (nếu có)

DirectAdmin → **Terminal** (hoặc **SSH**), chạy lần lượt:

```bash
cd /home/befdgfnt

# 1. Sinh APP_KEY (copy kết quả vào .env nếu lệnh không tự ghi)
php artisan key:generate --force

# 2. Tạo bảng + dữ liệu mẫu (admin, cấu hình, danh mục)
php artisan migrate --force
php artisan db:seed --force

# 3. Xoá cache cấu hình cũ
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 4. Tối ưu cho production
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Cách B: Không có Terminal — dùng Cron chạy 1 lần

1. DirectAdmin → **Cron Jobs** → **Tạo Cron Job**
2. Command:
   ```
   cd /home/befdgfnt && php artisan key:generate --force && php artisan migrate --force && php artisan db:seed --force && php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```
3. Chọn **Once** / chạy 1 lần, lưu lại
4. Chờ 2–3 phút, vào **Nhật ký cron** xem kết quả
5. **Xoá cron job đó** sau khi xong

### Cách C: Tạo file tạm `install.php` (nếu cả 2 cách trên không được)

Tạo `/home/befdgfnt/install.php`:

```php
<?php
// XOÁ FILE NÀY NGAY SAU KHI CHẠY XONG
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$cmds = [
    'key:generate --force',
    'migrate --force',
    'db:seed --force',
    'config:cache',
    'route:cache',
    'view:cache',
];

echo "<pre>";
foreach ($cmds as $c) {
    echo ">>> artisan {$c}\n";
    Illuminate\Support\Facades\Artisan::call($c);
    echo Illuminate\Support\Facades\Artisan::output() . "\n";
}
echo "XONG. XOÁ FILE install.php NGAY!";
```

Mở `https://domain-cua-ban/install.php` → chờ chạy xong → **xoá file ngay**.

---

## Bước 6 — Phân quyền thư mục

Trong File Manager, chuột phải 2 thư mục sau → **Đặt quyền** (Permissions) = **775**:

- `/home/befdgfnt/storage`
- `/home/befdgfnt/bootstrap/cache`

Áp dụng **đệ quy** (Recursive / "Áp dụng cho thư mục con").

---

## Bước 7 — Cron tự động (bắt buộc)

DirectAdmin → **Cron Jobs** → **Tạo Cron Job**:

- **Phút**: `*` (mỗi phút)
- **Giờ / Ngày / Tháng / Thứ**: `*`
- **Command**:
  ```
  cd /home/befdgfnt && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
  ```

Nếu `/usr/local/bin/php` không đúng, thử `php` hoặc `/usr/bin/php`. Kiểm tra bằng cách tạo cron `php -v > /home/befdgfnt/php-version.txt` rồi xem file.

Cron này chạy 3 việc:
- Đồng bộ trạng thái đơn mỗi 5 phút
- Đóng yêu cầu nạp quá hạn
- Xử lý queue (job gửi đơn sang TopSubRe)

---

## Bước 8 — Bật HTTPS

DirectAdmin → **SSL Certificates** → chọn domain → **Free & automatic certificate from Let's Encrypt** → **Save**

Sau khi có SSL, **bắt buộc** bật:
DirectAdmin → **Domain Setup** → chọn domain → tick **Force SSL** / **Redirect to HTTPS**

---

## Bước 9 — Kiểm tra sau deploy

| # | Kiểm tra | Kết quả đúng |
|---|---|---|
| 1 | `https://domain-cua-ban` | Landing page hiện, logo NhoSub |
| 2 | `https://domain-cua-ban/.env` | **403 hoặc 404** (không tải được file) |
| 3 | `https://domain-cua-ban/login` | Form đăng nhập tiếng Việt |
| 4 | Đăng nhập `admin` + mật khẩu ở Bước 4 | Vào được, thấy nhóm **Quản trị** trên menu |
| 5 | Quản trị → Cấu hình | Nhập key TopSubRe, bấm kiểm tra → số dư hiện |
| 6 | Quản trị → Dịch vụ → Đồng bộ | Báo số dịch vụ đã sync (286) |
| 7 | Nạp tiền: nhập 50.000 → QR hiện | QR VietQR đúng số tiền + nội dung |
| 8 | Chuyển khoản thật ≥ 10.000 ₫ kèm mã | Ví tăng trong 30 giây |
| 9 | Quản trị → Nhật ký webhook (`/admin/webhooks`) | Có bản ghi `processed` |
| 10 | Chờ 6 phút, xem log | Cron chạy, không lỗi |

### Sau khi kiểm tra xong

1. Sửa `.env`: `ADMIN_SEED_ENABLED=false`
2. Đổi mật khẩu admin (Quản trị → Hồ sơ → Đổi mật khẩu)
3. Xoá file `install.php` nếu đã dùng Cách C

---

## Cấu hình SePay (sau khi có domain thật)

1. https://sepay.vn → đăng ký → **Tài khoản ngân hàng** → kết nối MB Bank
2. **Cài đặt → Tuỳ chọn nhà phát triển** → copy **API key**
3. Cùng trang → **Webhook**:
   - URL: `https://domain-cua-ban/webhook/sepay`
   - Kiểu xác thực: **Apikey**
   - Token: dán API key vừa copy
   - Tự động xác nhận: **BẬT**
4. NhoSub → **Quản trị → Cấu hình** → ô **SePay webhook key** → dán key → Lưu
5. NhoSub → **Quản trị → Cấu hình** → điền Ngân hàng / Số TK / Chủ TK / BIN (MB Bank = `970422`)
6. Chuyển thử 10.000 ₫ kèm nội dung `NHOSUB <username> N10000 <MÃ6KÝTỰ>` → ví tự tăng

---

## Cấu hình TopSubRe

NhoSub → **Quản trị → Cấu hình**:
- **TopSubRe endpoint**: `https://likenhanh.pro/api/v2`
- **TopSubRe API key**: `<YOUR_TOPSUBRE_API_KEY>` *(lấy từ trang API của likenhanh.pro — KHÔNG commit key thật lên repo)*
- **IP ghim**: `203.205.52.111` *(chỉ cần nếu hosting bị chặn DNS)*

Sau đó **Quản trị → Dịch vụ → Đồng bộ ngay**.

---

## Xử lý sự cố thường gặp

| Lỗi | Nguyên nhân | Cách sửa |
|---|---|---|
| **500 Internal Server Error** | Thiếu `APP_KEY` hoặc `storage/` không ghi được | Chạy `php artisan key:generate --force`; đặt quyền `storage` = 775 |
| **Trang trắng** | PHP < 8.2 | DirectAdmin → **Chọn phiên bản PHP** → đặt **8.2** hoặc **8.3** |
| **Không kết nối DB** | Sai user/pass DB hoặc thiếu tiền tố `befdgfnt_` | Kiểm tra lại Bước 1, dùng đúng tên đầy đủ |
| **CSS/ảnh không tải** | Document root chưa trỏ `public/` | Làm lại Bước 3 |
| **`.env` tải được về máy** | Document root sai → **NGUY HIỂM** | Sửa ngay Bước 3, đổi lại toàn bộ mật khẩu |
| **Webhook SePay không về** | Chưa bật Force SSL hoặc chưa nhập key | Kiểm tra Bước 8 + mục SePay |
| **Đơn không cập nhật trạng thái** | Cron chưa chạy | Kiểm tra Bước 7, xem log cron |
| **Lỗi "could not find driver"** | Thiếu `pdo_mysql` | DirectAdmin → **Chọn extension PHP** → bật `pdo_mysql`, `mbstring`, `curl`, `gd`, `fileinfo` |

---

## Sau khi deploy xong — báo lại

Gửi tôi:
1. Domain thật
2. Kết quả 10 mục kiểm tra Bước 9
3. Ảnh chụp trang chủ + dashboard

Tôi sẽ kiểm tra từ xa (không cần mật khẩu) và xử lý tiếp nếu có lỗi.
