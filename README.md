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
copy .env.example .env
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

Test dùng database `nhosub_testing` (MySQL), cấu hình trong `phpunit.xml`.

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
copy .env.example .env
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
> (cờ `momo_enabled` mặc định tắt).

### Bước 10 — Kiểm tra trước khi mở bán

Chạy hết checklist `docs/DEPLOY-CHECKLIST.md` (D1–D12). Mục D1, D2, D3, D4, D5, **D6**, D7, D9, D10, D11 là bắt buộc.

---

## 4. Tạo admin không qua seeder

```bash
php artisan nhosub:create-admin
```

Lệnh sẽ hỏi tên đăng nhập, email, mật khẩu (hoặc truyền `--username= --email= --password=`).

---

## 5. Cấu hình giá

Công thức trong `app/Services/PricingService.php`:

```
cost_price  = floor(cost_usd × tỉ_giá)
sell_price  = round(cost_price × (1 + %lợi_nhuận/100))
tổng đơn    = floor(sell_price × số_lượng / 1000)
```

- **Tỉ giá** và **% lợi nhuận** sửa ở **Quản trị → Cấu hình** (mặc định 26.000 và 30%).
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
- Rate limit đăng nhập 5 lần / 15 phút theo IP + username (trả HTTP 429).
- `session()->regenerate()` sau đăng nhập; đổi mật khẩu huỷ các phiên khác.
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
