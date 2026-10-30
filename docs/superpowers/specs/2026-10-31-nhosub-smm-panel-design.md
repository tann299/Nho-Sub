# Đặc tả thiết kế — Panel đại lý SMM "NhoSub" (Giai đoạn 1)

- **Ngày:** 2026-10-31
- **Nguồn yêu cầu:** `SRS.pdf` trong thư mục dự án — bản 1.0, 21 trang (bản 1.1 được nhắc tới nhưng **chưa có file trong thư mục**; nếu có, cần gửi để đối chiếu, đặc biệt phần `webhook_logs`) + prompt xây dựng website panel
- **Phạm vi:** Giai đoạn 1. Giai đoạn 2 (mass order, drip-feed, ticket, affiliate, API đại lý, email, bảo trì) **không** nằm trong đặc tả này.
- **Trạng thái:** Đã duyệt thiết kế, chờ lập kế hoạch triển khai.

---

## 1. Bối cảnh và mục tiêu

NhoSub là panel bán dịch vụ tăng tương tác mạng xã hội theo mô hình đại lý: mua sỉ từ nhà cung cấp TopSubRe qua API, bán lại cho khách lẻ Việt Nam, hưởng chênh lệch giá.

**Mục tiêu giai đoạn 1:** một website chạy thật trên shared hosting, khách tự đăng ký → nạp tiền → đặt đơn → theo dõi đơn; admin vận hành toàn bộ qua trang quản trị.

**Tiêu chí thành công:** 6 mục nghiệm thu ở mục 9 của prompt đều đạt, có bằng chứng chạy thật.

**Thương hiệu:** NhoSub — "Sub nhỏ, lớn dần cùng bạn". Giao diện 100% tiếng Việt, tiền tệ VND.

## 2. Quyết định kỹ thuật đã chốt

| Hạng mục | Quyết định | Lý do |
|---|---|---|
| Framework | **Laravel 12** | Bản mới nhất, được hỗ trợ bảo mật dài. Yêu cầu PHP ≥ 8.2 (README ghi rõ; prompt gốc ghi 8.1+ nhưng Laravel 11/12 cần 8.2+, người dùng đã chọn Laravel 12). |
| Cơ sở dữ liệu | MySQL 8. **Tiền VND dùng BIGINT (đồng nguyên), giá gốc USD dùng DECIMAL(12,6), tỉ giá BIGINT, % lợi nhuận DECIMAL(5,2)** — không dùng REAL/FLOAT cho tiền. Test tự động chạy SQLite nhưng kiểu cột vẫn khai báo tường minh. | SRS mục 6.2 ghi REAL, nhưng số thực dấu phẩy động gây lệch khi quy đổi USD→VND; sửa để đúng tiền tệ (xem 4.1). |
| Frontend build | **Không dùng Vite/Node ở production.** CSS + JS viết tay trong `public/assets/`. | Shared hosting không có Node; asset biên dịch sẵn đưa lên là chạy. |
| Queue | `database` driver + `php artisan queue:work --stop-when-empty` chạy trong cron 5 phút. | Không Redis/Supervisor. |
| Cron | 1 dòng crontab duy nhất: `* * * * * php artisan schedule:run`; lịch chạy đồng bộ đơn mỗi 5 phút. | Chuẩn shared hosting. |
| Nhà cung cấp | `ProviderInterface` + `TopSubReProvider` + `DemoProvider` + `ProviderManager`. | Mở rộng nhiều NCC không sửa luồng đặt đơn. |
| Cổng thanh toán | SePay (chính) + PayOS (bổ sung, bật/tắt) + duyệt tay (luôn bật). MoMo **chưa làm ở giai đoạn 1**, chỉ chừa adapter + bật/tắt trong admin. | Người dùng chọn phương án "Thêm PayOS" khi được hỏi (prompt gốc ghi "MoMo và/hoặc PayOS"). PayOS đăng ký được cho cá nhân; MoMo cần hợp đồng doanh nghiệp nên để giai đoạn sau. **Nếu muốn MoMo ngay giai đoạn 1, phải sửa mục này trước khi code.** |
| Logo | SVG tự vẽ lại (mầm cây trái tim) tô phẳng 2 mảng xanh. Thay file khi có logo gốc. | Thư mục dự án không có file logo. |
| Chế độ demo | Tự bật khi chưa cấu hình API key TopSubRe. | Nghiệm thu đủ 6 mục ngay trên máy, không chờ key thật. |

## 3. Kiến trúc hệ thống

```
┌──────────────────────────────────────────────────────────────┐
│  public/            (document root; .htaccess rewrite dự phòng) │
│  ├─ index.php                                                   │
│  └─ assets/  css/app.css · js/app.js · img/logo.svg · img/pattern.svg
├──────────────────────────────────────────────────────────────┤
│  app/                                                           │
│  ├─ Http/Controllers/                                           │
│  │   ├─ LandingController · Auth/* · DashboardController        │
│  │   ├─ ServiceController · OrderController · DepositController │
│  │   ├─ WalletController · ProfileController · NotificationCtrl  │
│  │   ├─ Admin/* (Dashboard, User, Service, Order, Deposit,       │
│  │   │            Webhook, Setting)                              │
│  │   └─ Webhook/SePayController · Webhook/PayOSController        │
│  ├─ Services/                                                   │
│  │   ├─ WalletService          (bút toán ví duy nhất)            │
│  │   ├─ RefundService          (hoàn nhiều đợt + order_refunds)   │
│  │   ├─ OrderService           (đặt đơn 2 bước, client_token)    │
│  │   ├─ OrderSyncService       (đồng bộ trạng thái + hoàn tiền)  │
│  │   ├─ DepositService         (duyệt nạp, webhook nạp, hết hạn) │
│  │   ├─ PricingService         (giá bán/1000)                    │
│  │   └─ SettingService         (đọc/ghi cấu hình mã hóa)         │
│  ├─ Providers/                                                  │
│  │   ├─ ProviderInterface.php · ProviderManager.php             │
│  │   ├─ TopSubRe/TopSubReProvider.php · Dto/*                   │
│  │   └─ Demo/DemoProvider.php                                   │
│  ├─ Models/  (User, Category, Service, Order, OrderRefund,      │
│  │            DepositRequest, WalletTransaction, Setting,       │
│  │            ApiSyncLog, WebhookLog, PayOsTransaction)          │
│  ├─ Jobs/    SyncOrderStatusJob                                 │
│  └─ Console/Commands/ SyncOrdersCommand · ExpireDepositsCommand  │
│                       · CreateAdminCommand                       │
├──────────────────────────────────────────────────────────────┤
│  database/migrations · seeders (AdminSeeder khóa bằng env)      │
│  resources/views  (landing + layouts/panel + auth + admin)      │
│  routes/web.php · routes/console.php                            │
└──────────────────────────────────────────────────────────────┘
```

### 3.1 Tầng Provider

```php
interface ProviderInterface {
    public function balance(): ProviderBalance;                 // USD + VND tương đương
    public function services(): array;                          // ProviderServiceDto[]
    public function addOrder(string $serviceId, string $link, int $quantity): ProviderOrderResult;
    public function orderStatus(string $providerOrderId): ProviderOrderStatus;
}
```

- `TopSubReProvider`: POST JSON tới `https://likenhanh.pro/api/v2` với `key` + `action` (`balance`, `services`, `add`, `status`). Timeout 20s, retry 1 lần cho `status`. **Không log key** (log chỉ ghi action + tóm tắt).
- `DemoProvider`: sinh `provider_order_id` giả, trạng thái tiến hoá theo thời gian đặt đơn (0–1 phút `pending`, 1–3 phút `processing`, sau 3 phút `partial` nếu số lượng lẻ hoặc `completed`), cho phép bật kịch bản partial/cancel qua cấu hình `demo_scenario` để test hoàn tiền.
- `ProviderManager::driver()`: nếu `topsubre_api_key` rỗng **hoặc** `demo_mode = on` → Demo; ngược lại → TopSubRe. Mọi nơi trong code gọi manager, không `new TopSubReProvider`.

### 3.2 Bất biến tiền tệ (nguồn sự thật)

1. **Mọi thay đổi số dư đi qua `WalletService::apply()`** — không có chỗ nào UPDATE `users.balance` trực tiếp.
2. `apply()` chạy trong transaction, `SELECT ... FOR UPDATE` trên dòng `users`, kiểm tra không âm, ghi 1 dòng `wallet_transactions` với `idempotency_key` **UNIQUE**, cập nhật `balance_after`.
3. Nếu `idempotency_key` đã tồn tại → trả về giao dịch cũ, **không** áp dụng lần hai (chống trùng webhook, chống cron chạy lại, chống double-click).
4. Khoá idempotency theo nghiệp vụ:
   - `order:{id}:charge` — trừ tiền khi đặt đơn (một lần/đơn)
   - `order:{id}:refund:{refund_seq}` — **hoàn tiền theo từng đợt**, `refund_seq` = số thứ tự lần hoàn (1, 2, 3…), lấy bằng `orders.refund_count + 1` trong cùng transaction khoá đơn. Cho phép đơn partial hoàn một phần rồi canceled hoàn tiếp phần còn lại mà vẫn chống trùng ở tầng DB.
   - `deposit:sepay:{gateway_txn_id}` — webhook SePay
   - `deposit:payos:{orderCode}` — webhook PayOS
   - `deposit:manual:{deposit_request_id}` — duyệt tay
   - `adjust:{uuid}` — admin cộng/trừ tiền
   - `order:{id}:manual_refund:{uuid}` — admin hoàn tiền thủ công (uuid do form sinh ra, chống double-submit)
5. **Chống hoàn trùng kép** (cả ở tầng key lẫn tầng số liệu):
   - `wallet_transactions.idempotency_key` UNIQUE — chặn cùng một lần hoàn ghi 2 lần.
   - `orders.refunded_amount` + `orders.refund_count` cập nhật **trong cùng transaction** với bút toán, có `SELECT ... FOR UPDATE` trên dòng đơn → hai tiến trình cron song song không thể cùng tính delta giống nhau.
   - Tổng hoàn không bao giờ vượt `orders.total_amount` (ràng buộc kiểm tra cuối cùng trong `WalletService::apply` khi `type = refund`).
6. Số dư TopSubRe là **số dư bên ngoài**, chỉ đọc qua `balance` action, không lưu thành ví nội bộ. Hiển thị ở admin kèm cảnh báo khi thấp hơn ngưỡng (BR-07).

## 4. Thiết kế dữ liệu

Theo ERD SRS mục 6–7: **8 bảng gốc** + **4 bảng bổ sung** (`webhook_logs` vốn có trong SRS v1.1 theo người dùng xác nhận nhưng không có trong file SRS.pdf bản 1.0 đang có; `payos_transactions`, `notifications`, `order_refunds` là phần mở rộng của thiết kế này).

### 4.1 Bảng gốc (giữ tên cột SRS, sửa kiểu tiền cho đúng)

| Bảng | Cột chính | Ghi chú |
|---|---|---|
| `users` | id, username UNIQUE, email UNIQUE, password_hash, full_name, phone, role (customer/admin), **balance BIGINT ≥ 0**, status (active/locked), created_at | Thêm `remember_token`, `email_verified_at`, `last_login_at`, `failed_login_attempts`, `locked_until` phục vụ bảo mật. |
| `categories` | id, name, platform, sort_order, is_active | |
| `services` | id, category_id FK, provider_service_id UNIQUE, name, description, **cost_usd DECIMAL(12,6)**, **cost_price BIGINT** (VND/1000), **sell_price BIGINT** (VND/1000), **sell_price_override TINYINT**, min_qty, max_qty, is_active, synced_at | Tách `cost_usd` (giá gốc USD gốc từ NCC) khỏi `cost_price` (đã quy đổi VND) để đổi tỉ giá không làm mất dữ liệu gốc. `sell_price_override = 1` khi admin đặt giá riêng → đồng bộ không ghi đè. |
| `orders` | id, user_id, service_id, **client_token CHAR(36) UNIQUE**, provider_order_id, target_link, quantity, **unit_price BIGINT**, **total_amount BIGINT**, **cost_amount BIGINT**, status, start_count, remains, **refunded_amount BIGINT default 0**, **refund_count INT default 0**, created_at, updated_at | Thêm `synced_at`, `error_message`, `sync_attempts`. `client_token` sinh ở form bước 2 → chống double-click (xem 5.3).<br>**Trạng thái hợp lệ:** `pending` (vừa tạo, chưa trừ tiền xong) → `charged` (đã trừ tiền, chưa có provider_order_id) → `processing` (đã gửi NCC thành công) → `completed` / `partial` / `canceled` / `error`; `stuck_review` (kẹt, chờ admin xử lý tay). |
| `deposit_requests` | id, user_id, **amount BIGINT**, method, transfer_code, status (pending/approved/rejected/**expired**), admin_note, created_at, reviewed_at | `transfer_code` không unique toàn cục (xem 4.3). Thêm `expires_at` cho yêu cầu PayOS. |
| `wallet_transactions` | id, user_id, order_id nullable, deposit_id nullable, type (deposit/order_charge/refund/adjust), **amount BIGINT ±**, **balance_after BIGINT**, note, created_at | **Thêm `idempotency_key` UNIQUE** — trụ cột chống trùng. |
| `settings` | key PK, value TEXT, updated_at | Giá trị nhạy cảm lưu `Crypt::encryptString`. |
| `api_sync_logs` | id, action, order_id nullable, request_summary, response_summary, success, created_at | Không chứa API key. |

### 4.2 Bảng bổ sung

| Bảng | Nguồn | Mục đích |
|---|---|---|
| `webhook_logs` | SRS v1.1 (người dùng xác nhận; không có trong SRS.pdf bản 1.0) + prompt yêu cầu admin xem nhật ký webhook | gateway (sepay/payos), mã giao dịch, số tiền, nội dung, trạng thái xử lý (processed/duplicate/unmatched/rejected), lý do, payload đã che key. |
| `order_refunds` | **Mở rộng của thiết kế này** | Sổ hoàn tiền theo từng đợt: order_id, seq, amount, reason (auto_partial / auto_canceled / provider_error / admin_manual / stuck_review), wallet_transaction_id, created_at. Là nguồn tra cứu "đơn này đã hoàn mấy lần, bao nhiêu" — khắc phục gốc lỗi key hoàn trùng. |
| `payos_transactions` | Mở rộng (prompt yêu cầu PayOS) | Liên kết `deposit_request` ↔ `orderCode` PayOS ↔ trạng thái thanh toán, `expires_at`. |
| `notifications` | Mở rộng (**không nằm trong prompt gốc**, thêm để trang tổng quan có "thông báo mới nhất" như prompt yêu cầu) | Bảng chuẩn Laravel — thông báo trong panel (mục 5.1.1). |

### 4.3 Điều chỉnh so với SRS (ghi rõ trong README)

- **`deposit_requests.transfer_code` không UNIQUE toàn cục.** Nội dung chuyển khoản là `NHOSUB <USERNAME>` (chốt có 1 khoảng trắng khi hiển thị, xem 5.5) cố định theo người dùng, nhiều yêu cầu của cùng một khách sẽ trùng. Chống cộng trùng do `wallet_transactions.idempotency_key` theo mã giao dịch ngân hàng đảm nhiệm.
- **Giới hạn 1 yêu cầu nạp `pending`** chỉ áp dụng cho yêu cầu **duyệt tay** (tránh admin đối soát nhầm). Yêu cầu PayOS được tạo tự động và **tự hết hạn sau 30 phút** (status `expired`) — không khoá khách.
- **`orders.refunded_amount` + `orders.refund_count` + bảng `order_refunds`** thêm vào để hoàn tiền nhiều đợt mà không trùng.
- **Kiểu tiền:** SRS dùng REAL; spec này dùng BIGINT cho VND và DECIMAL(12,6) cho USD. Lý do: REAL/FLOAT gây sai số khi `floor(giá × số lượng / 1000)` lặp nhiều lần.

### 4.4 Index bắt buộc

`orders(user_id)`, `orders(status)`, `orders(provider_order_id)`, **`orders(client_token) UNIQUE`**, `wallet_transactions(user_id, created_at)`, `wallet_transactions(idempotency_key) UNIQUE`, `api_sync_logs(order_id)`, `deposit_requests(user_id, status)`, **`deposit_requests(status, expires_at)`**, `webhook_logs(gateway, gateway_txn_id) UNIQUE`, **`order_refunds(order_id, seq) UNIQUE`**.

## 5. Luồng nghiệp vụ

### 5.1 Đăng ký / đăng nhập (UC-01, UC-02)

- Đăng ký: username (3–32, chữ/số/_), email, mật khẩu ≥ 8, họ tên, SĐT. Băm bcrypt, `role = customer`, `balance = 0`, `status = active`. Tên đăng nhập không phân biệt hoa thường khi kiểm tra trùng. Không có trang đăng ký admin.
- Đăng nhập: so khớp username **hoặc** email + mật khẩu; thông báo lỗi chung "Tên đăng nhập hoặc mật khẩu không đúng". Rate limit 5 lần/15 phút theo IP + username; tài khoản `locked` báo hướng dẫn liên hệ admin. Thành công → `session()->regenerate()`, ghi `last_login_at`.
- Quên mật khẩu: dùng luồng reset của Laravel. **SMTP là bắt buộc trước khi mở bán** — xem checklist deploy mục 8.3; nếu `MAIL_MAILER=log` thì trang quên mật khẩu hiển thị cảnh báo "chưa cấu hình gửi mail" và admin phải reset hộ.
- Đổi mật khẩu (UC-09): nhập mật khẩu cũ + mật khẩu mới (≥ 8, nhập lại khớp); sai mật khẩu cũ thì từ chối; thành công huỷ các phiên đăng nhập khác và gửi lại session cho phiên hiện tại.
- Cập nhật hồ sơ (UC-08): sửa họ tên, SĐT; email mới phải duy nhất; **không** cho sửa username và role.

### 5.1.1 Thông báo và trang hỗ trợ

- Dùng bảng `notifications` chuẩn của Laravel (database channel). Sinh thông báo khi: nạp tiền được duyệt, đơn chuyển trạng thái quan trọng (processing → completed / partial / canceled / error), admin cộng/trừ tiền. Dashboard hiển thị 5 thông báo mới nhất; chuông ở topbar có badge số chưa đọc + đánh dấu đã đọc.
- Nhóm menu **Hỗ trợ** giai đoạn 1 là trang tĩnh: hướng dẫn sử dụng, câu hỏi thường gặp, thông tin liên hệ admin. Ticket có timeline để giai đoạn 2 (cấu trúc route đã tách sẵn để gắn vào).

### 5.2 Xem dịch vụ (UC-03)

Chỉ hiện `services.is_active = 1` và `categories.is_active = 1`. Nhóm theo nền tảng → danh mục; tìm kiếm theo tên; hiển thị giá/1000, min/max, mô tả, tốc độ, thời gian bắt đầu. **Không bao giờ trả về `cost_price`** trong response của khách (kiểm tra bằng test).

### 5.3 Đặt đơn hai bước (UC-05)

**Bước 1** — chọn dịch vụ, nhập link + số lượng → server tính `total = floor(sell_price × qty / 1000)` và hiện màn xem trước (số dư hiện tại, số dư sau khi đặt, số lượng, giá/1000, tổng tiền). **Bước 2** — xác nhận.

**Chống double-submit (T8):** form bước 1 sinh `client_token` (UUID v4) lưu vào hidden field + session; form bước 2 gửi lại token này. Server ghi `orders.client_token` **UNIQUE** — nếu request thứ hai mang cùng token, insert ném lỗi UNIQUE → bắt lỗi, trả về đơn đã tạo thay vì tạo đơn mới. Đây là chốt chặn ở tầng DB, không phụ thuộc JS hay session. Ngoài ra nút xác nhận bị disable sau lần bấm đầu ở phía client (chỉ là trải nghiệm, không phải biện pháp bảo vệ).

Khi xác nhận:
1. Validate lại toàn bộ (dịch vụ còn bán, qty trong [min,max], link hợp lệ http/https, `client_token` là UUID).
2. `DB::transaction` — **thứ tự bắt buộc**:
   ```
   a. SELECT ... FOR UPDATE dòng user           (khoá số dư)
   b. INSERT orders (client_token, unit_price = sell_price đóng băng,
                     cost_amount = floor(cost_price × qty / 1000),
                     total_amount = total, status = 'pending')
      → nhận $order->id                       (đơn phải có id trước)
   c. WalletService::apply('order_charge', -total,
                           "order:{$order->id}:charge", order_id: $order->id)
      → kiểm tra đủ tiền, trừ tiền, ghi wallet_transactions
   d. UPDATE orders SET status = 'charged'
   ```
   Nếu bước (b) ném UNIQUE trên `client_token` → bắt `QueryException`, rollback, tìm đơn cũ theo token và trả về đơn đó (không tạo mới, không trừ tiền lần hai).
   Nếu bước (c) ném "số dư không đủ" → rollback toàn bộ, không có đơn nào được tạo.
3. Commit. Gọi `ProviderManager->addOrder()`:
   - Thành công → lưu `provider_order_id`, `status = processing`.
   - Thất bại/timeout/exception → `OrderService::refund()` với reason `provider_error`, hoàn **toàn bộ** `total_amount` (seq 1), `status = error`. Ghi `api_sync_logs`.
4. **Đơn kẹt ở trạng thái `charged` quá 10 phút** (request bị ngắt sau khi trừ tiền nhưng trước khi gọi/ghi nhận provider):
   - **KHÔNG tự hoàn tiền.** Đẩy sang trạng thái `stuck_review` + tạo thông báo cho admin.
   - Lý do: đơn có thể đã gửi thành công lên TopSubRe nhưng `provider_order_id` chưa kịp lưu → tự hoàn sẽ khiến khách vừa được chạy vừa được hoàn.
   - Admin xử lý tay ở trang Đơn hàng: tra log `api_sync_logs` → nếu chưa gửi thì hoàn tiền + đóng đơn; nếu đã gửi thì nhập `provider_order_id` để tiếp tục đồng bộ.
   - Đơn `stuck_review` không bị cron chạm vào.

**Giá đóng băng:** đơn lưu `unit_price` và `cost_amount` tại thời điểm đặt; đổi giá bán sau đó không ảnh hưởng đơn cũ (BR-04).

**Nút "Cập nhật trạng thái" ở trang đơn của khách:** gọi đồng bộ ngay cho đơn đó (dùng chung `OrderSyncService`), rate limit 1 lần/15 giây/đơn để không đốt quota API; đơn đã kết thúc thì nút ẩn.

### 5.4 Đồng bộ trạng thái + hoàn tiền (UC-18, cron 5 phút)

1. Lấy các đơn `processing` có `provider_order_id` (bỏ qua đơn vừa tạo < 60 giây). **Không** lấy `charged`, `stuck_review`, `completed`, `canceled`, `error`.
2. Gọi `status` cho từng đơn (giới hạn batch 100, có thể dùng job song song qua queue).
3. Map trạng thái: `Pending→pending`, `In progress→processing`, `Completed→completed`, `Partial→partial`, `Canceled→canceled`, `Refunded→canceled`.
4. Cập nhật `start_count`, `remains` nếu NCC trả về.
5. **Hoàn tiền theo delta, mỗi lần là một bản ghi `order_refunds` riêng** — chạy trong transaction khoá dòng đơn:
   ```
   DB::transaction:
     $order = Order::lockForUpdate()->find($id);          // khoá đơn
     $chua_chay   = $order->remains ?? ($order->quantity - $order->start_count);
     $chua_chay   = max(0, min($chua_chay, $order->quantity));   // kẹp 0..quantity (T11b)
     $dang_hoan   = $order->status === 'completed' ? 0
                  : floor($order->unit_price * $chua_chay / 1000);
     $con_phai_hoan = $dang_hoan - $order->refunded_amount;
     if ($con_phai_hoan > 0) {
         $seq = $order->refund_count + 1;
         $tx  = WalletService::apply('refund', +$con_phai_hoan,
                                     "order:{$order->id}:refund:{$seq}", $order->id);
         OrderRefund::create([order_id, seq: $seq, amount: $con_phai_hoan,
                              reason: 'auto_partial'|'auto_canceled', wallet_transaction_id: $tx->id]);
         $order->refunded_amount += $con_phai_hoan;
         $order->refund_count    += 1;
         $order->save();
     }
   ```
   - **Key phân biệt từng đợt** (`refund:1`, `refund:2`…) → đợt hoàn thứ hai không bị UNIQUE chặn oan.
   - **Khoá dòng đơn + tính delta trong cùng transaction** → cron chạy chồng nhau cũng không hoàn trùng.
   - **Chặn cứng:** `WalletService::apply()` khi `type = refund` kiểm tra `refunded_amount + amount ≤ total_amount`, vượt → ném exception, rollback.
   - Kịch bản T11 (partial rồi canceled, phần chưa chạy tăng lên): đơn 1000, lần 1 NCC báo partial `remains = 600` (đã chạy 400) → hoàn 600 (seq 1). Lần sau NCC báo canceled `remains = 800` (thực tế chỉ chạy 200) → `đang_hoàn = 800`, `con_phải_hoàn = 800 − 600 = 200` → hoàn tiếp 200 (seq 2, key `order:{id}:refund:2`). Tổng hoàn 800 = đúng phần chưa chạy, không vượt `total_amount`.
   - Kịch bản T10 (cron chạy lại, số liệu không đổi): `remains` vẫn 800, `refunded_amount` đã 800 → `con_phải_hoàn = 0` → không hoàn thêm.
6. Đơn đã kết thúc (`completed`, `canceled`, `error`) không đồng bộ lại.
7. Trạng thái lạ từ NCC → giữ nguyên + ghi `api_sync_logs` để admin xem.
8. Mọi lần gọi ghi `api_sync_logs` (action, order_id, tóm tắt request/response **không có key**, success).

### 5.5 Nạp tiền

**Format nội dung chuyển khoản — CHỐT:** hiển thị và khuyến nghị nhập là **`NHOSUB <USERNAME>`** (có đúng 1 khoảng trắng sau `NHOSUB`, username viết hoa toàn bộ — ví dụ `NHOSUB KHACHVIP01`). Parser chấp nhận cả 3 biến thể `NHOSUB <USERNAME>`, `NHOSUB<USERNAME>`, `nhosub  <username>` (nhiều khoảng trắng, khác hoa thường) bằng regex `/\bNHOSUB\s*([A-Za-z0-9_]{3,32})\b/iu`, nhưng **mọi chỗ hiển thị UI, QR, README, email đều dùng đúng một format `NHOSUB <USERNAME>`**.

**SePay (chính):**
- Trang nạp hiển thị: số tài khoản, tên ngân hàng, chủ tài khoản, số tiền tối thiểu 10.000₫, nội dung chuyển khoản `NHOSUB <USERNAME>` (copy 1 chạm) + mã QR VietQR dựng từ cấu hình.
- Webhook `POST /webhook/sepay`: so sánh `Authorization` với `Apikey <key>` bằng `hash_equals` → chỉ nhận `transferType = in` → parse nội dung bằng regex trên → tìm user → số tiền ≥ 10.000 → `DepositService::handleGatewayDeposit()`:
  - ghi `webhook_logs` (dedupe theo `gateway_txn_id` UNIQUE),
  - tạo `deposit_requests` status `approved` + `wallet_transactions` type `deposit` với key `deposit:sepay:{id}`,
  - trả 200 `{"success": true}`. Lỗi xác thực → 401; không khớp user → log `unmatched` + vẫn trả 200 để SePay không retry vô hạn (admin xử lý tay).
- Giao dịch không khớp (không tìm thấy user, số tiền < min, nội dung sai) → `webhook_logs` trạng thái `unmatched`/`rejected` + lý do, hiển thị trong admin.

**PayOS (bổ sung, bật/tắt):**
- Bật/tắt bằng `settings.payos_enabled`. Khi bật, trang nạp có thêm nút "Nạp qua PayOS" → tạo `deposit_requests` pending với `expires_at = now + 30 phút` + `payos_transactions` với `orderCode` duy nhất → gọi API tạo link → redirect.
- Webhook xác thực chữ ký HMAC-SHA256 bằng `payos_checksum_key`; thành công → cộng ví với key `deposit:payos:{orderCode}` và đánh dấu `deposit_requests.approved`.
- **Tự hết hạn (không khoá khách):** cron mỗi 5 phút chuyển các `deposit_requests` PayOS `pending` có `expires_at < now` sang `expired`. Khách bấm PayOS rồi bỏ dở → 30 phút sau yêu cầu tự giải phóng, tạo được yêu cầu mới. Nếu webhook PayOS về muộn sau khi đã `expired` mà tiền thật đã vào → vẫn cộng ví (idempotency key theo `orderCode`) và ghi `webhook_logs` trạng thái `processed_late` để admin biết.
- **Ghi rõ README:** PayOS cần tài khoản đăng ký với PayOS (cá nhân/doanh nghiệp tuỳ chính sách hiện hành); khi chưa cấu hình thì nút bị ẩn.

**Duyệt tay (luôn bật):** khách gửi yêu cầu nạp (số tiền, phương thức, ghi chú) → `pending` → admin duyệt → cộng ví `deposit:manual:{id}` (một lần duy nhất; nút duyệt biến mất/không tác dụng lần hai) hoặc từ chối kèm lý do bắt buộc. **Chỉ yêu cầu duyệt tay** mới bị giới hạn "1 pending/khách"; yêu cầu PayOS do hệ thống tạo nên không tính vào giới hạn này.

### 5.6 Admin

| Trang | Chức năng |
|---|---|
| Tổng quan | Doanh thu, lợi nhuận gộp, số đơn theo trạng thái, số dư TopSubRe (gọi `balance`, cache 5 phút) + cảnh báo thấp, top dịch vụ bán chạy, biểu đồ 7/30 ngày (SVG thuần, không thư viện). |
| Người dùng | Tìm kiếm, xem số dư/tổng nạp/số đơn, khoá/mở, cộng/trừ tiền (bắt buộc lý do; không cho âm; ghi `adjust`). Không hiển thị mật khẩu. Không thể khoá admin cuối cùng. |
| Dịch vụ | Đồng bộ từ TopSubRe (thêm mới/cập nhật `cost_usd`/`cost_price`/min/max; ẩn dịch vụ biến mất — **không xoá**), đặt giá bán từng dịch vụ (`sell_price_override = 1`) hoặc áp % lợi nhuận toàn cục, ẩn/hiện, sửa tên/mô tả. Cảnh báo nếu giá bán ≤ giá gốc. |
| Đơn hàng | Xem tất cả, lọc trạng thái/khách/dịch vụ/thời gian, xem log API liên quan, xem lịch sử `order_refunds`, hoàn tiền thủ công (sinh `uuid` mỗi form, key `order:{id}:manual_refund:{uuid}`). Riêng đơn `stuck_review` có 2 nút: "Đã gửi — nhập mã provider" và "Chưa gửi — hoàn tiền & đóng". |
| Duyệt nạp | Danh sách `pending`, duyệt/từ chối, xem lịch sử. |
| Nhật ký webhook | Danh sách, lọc trạng thái, xem payload (đã che key), nút "cộng tay cho user X" cho giao dịch `unmatched`. |
| Nhật ký API | `api_sync_logs` lọc theo action/đơn/thành công. |
| Cấu hình | Tỉ giá (mặc định 26.000), % lợi nhuận (30%), thông tin ngân hàng, SePay (api key, webhook secret), PayOS (client id/key/checksum, bật/tắt), MoMo (chỗ cắm, chưa dùng), ngưỡng cảnh báo số dư, tên site, chế độ demo. **Key đã lưu không hiển thị lại** — chỉ hiện 4 ký tự cuối + ô nhập mới để thay. |

### 5.7 Công thức giá

```
cost_price (VND/1000) = floor(cost_usd × tỉ_giá)          // cost_usd DECIMAL(12,6), tỉ_giá BIGINT
sell_price (VND/1000) = round(cost_price × (1 + %/100))    // % DECIMAL(5,2)
tổng đơn (VND)        = floor(sell_price × quantity / 1000)
cost_amount (VND)     = floor(cost_price × quantity / 1000)
lợi nhuận đơn         = total_amount − cost_amount − refunded_amount
```
- Toàn bộ phép tính trên **số nguyên VND** (BIGINT) sau khi đã quy đổi; không dùng float ở bước tính tiền đơn.
- Khi đồng bộ: cập nhật `cost_usd` và `cost_price`; nếu `sell_price_override = 0` → tính lại `sell_price` theo % hiện hành; nếu `= 1` → **giữ nguyên** `sell_price`.
- Tổng tiền đơn làm tròn xuống (`floor`), VND nguyên (BR-10).
- Cảnh báo khi `sell_price ≤ cost_price` (bán lỗ).

## 6. Giao diện

### 6.1 Token thiết kế (`public/assets/css/app.css`)

```css
--primary:#1E6FD9; --primary-dark:#0A4DB3; --primary-light:#DCEBFD;
--bg:#F5F8FF; --card:#FFFFFF; --text:#0F2233; --muted:#5B6B80;
--success:#16A34A; --warning:#F59E0B; --danger:#DC2626;
--radius:12px;
--shadow-sm:0 1px 2px rgba(15,34,51,.06), 0 1px 3px rgba(15,34,51,.05);
--shadow-md:0 4px 12px rgba(15,34,51,.07), 0 1px 3px rgba(15,34,51,.05);
--shadow-lg:0 12px 32px rgba(15,34,51,.10), 0 4px 8px rgba(15,34,51,.05);
--ease:.25s ease;
```

**Quy tắc gradient:** toàn hệ thống **0 gradient**, ngoại lệ duy nhất `linear-gradient(180deg,#F5F8FF 0%,#EDF3FE 100%)` cho nền hero landing (chênh lệch độ sáng < 10%, cùng tông). Kiểm tra bằng test tự động: quét mọi file `.css`/`.blade.php` tìm `gradient(` và chỉ cho phép đúng selector `.hero`.

**Chiều sâu không gradient:** section xen kẽ trắng / `#F5F8FF`; card viền `1px solid #DCEBFD` + `--shadow-md`; họa tiết chấm SVG (`pattern.svg`, màu `#DCEBFD` phẳng) ở nền section; thanh nhấn `4px` màu primary đầu mỗi section; khoảng cách thở 64–96px desktop.

### 6.2 Hover (áp dụng thống nhất)

| Thành phần | Hover |
|---|---|
| Nút chính | nền `#0A4DB3`, `translateY(-2px)`, `--shadow-lg`, `transition: var(--ease)` |
| Card dịch vụ | `translateY(-4px)`, viền `#1E6FD9`, `--shadow-lg` |
| Menu sidebar | nền `#DCEBFD`, chữ `#0A4DB3`, thanh dọc 3px bên trái |
| Menu đang chọn | nền phẳng `#1E6FD9`, chữ trắng, thanh dọc trắng |
| Dòng bảng | nền `#F5F8FF` |
| Link | gạch chân chạy trái → phải bằng `::after` scaleX |
| Input focus | viền `#1E6FD9` + `box-shadow: 0 0 0 3px rgba(30,111,217,.15)` |

Tất cả transition 0.2–0.3s ease. Không shine, không gradient, không `transform: scale` gây giật.

### 6.3 Bố cục

- **Landing** `/`: header sticky → hero (badge, H1, slogan, 2 CTA, 3 số liệu) → nền tảng hỗ trợ (logo SVG đơn sắc) → tính năng (6 card) → bảng giá mẫu (3 gói) → đánh giá (3 thẻ) → FAQ (accordion `<details>`) → CTA cuối → footer.
- **Panel**: sidebar 260px cố định, thu gọn 76px (lưu localStorage), logo + NhoSub ở đầu, 7 nhóm: Tổng quan / Dịch vụ / Đặt đơn / Đơn hàng / Nạp tiền / Hỗ trợ / Cài đặt. Mobile < 1024px: sidebar thành drawer trượt + overlay, nút hamburger; nội dung không bị che.
- Topbar: tiêu đề trang, số dư ví, chuông thông báo, menu người dùng.
- Bảng: bọc `.table-wrap{overflow-x:auto}` + `min-width` để cuộn ngang trên mobile; nút cao ≥ 44px.
- Font: `system-ui, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif` — landing và panel dùng chung.
- Không lỗi console: JS thuần, có `defer`, không phụ thuộc CDN.

### 6.4 Logo

`public/assets/img/logo.svg` — hình mầm cây trái tim: thân mầm cong nét đậm `#0A4DB3`, hai lá trái tim tô phẳng `#1E6FD9` và `#DCEBFD` tách mảng rõ, nền trong suốt. Kích thước dùng 28–40px. Favicon dựng từ cùng hình. Khi có file gốc: thay đúng file này, giữ tên.

## 7. Bảo mật

- Mật khẩu bcrypt (Laravel `Hash`), CSRF mọi form (Blade `@csrf`), validate mọi input qua FormRequest.
- Eloquent/Query Builder tham số hoá — không nối chuỗi SQL.
- Login rate limit 5 lần/15 phút; `session()->regenerate()` sau đăng nhập; session lifetime 120 phút.
- Middleware `role:admin` kiểm tra ở server cho toàn bộ route `/admin`; khách không truy cập được dữ liệu người khác (route model binding + `where user_id = auth()->id()`).
- Key nhạy cảm: `Crypt::encryptString` lưu trong `settings`; hiển thị dạng `••••••••abcd`; không log, không trả về trong response; `.env` nằm trong `.gitignore`, chỉ commit `.env.example`.
- Webhook: so sánh key bằng `hash_equals`; chống replay bằng UNIQUE `gateway_txn_id`; rate limit webhook 120/phút/IP.
- Header bảo mật: `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` qua middleware.
- Upload (avatar giai đoạn 2) — giai đoạn 1 chưa có upload.

## 8. Kiểm thử và nghiệm thu

### 8.1 Test tự động (Pest/PHPUnit, chạy SQLite in-memory)

| # | Kịch bản | Kỳ vọng |
|---|---|---|
| T1 | Đăng ký → đăng nhập | user tạo với role customer, balance 0; session đổi ID sau đăng nhập |
| T2 | Webhook SePay hợp lệ | số dư tăng đúng, có `wallet_transactions` type deposit |
| T3 | Webhook SePay gửi lại cùng mã GD | số dư không đổi, chỉ 1 giao dịch |
| T4 | Webhook sai Apikey / tiền ra / nội dung sai / dưới min | 401 hoặc log unmatched, số dư không đổi |
| T5 | Đặt đơn thành công (demo) | trừ đúng `floor(price×qty/1000)`, đơn `processing`, có `provider_order_id` |
| T6 | Đặt đơn khi API lỗi | đơn `error`, hoàn 100% (`refund_count = 1`, có 1 dòng `order_refunds`), `refunded_amount = total` |
| T7 | Đặt đơn số dư không đủ | 422, **không có dòng orders nào được tạo**, không trừ tiền |
| T8 | Gửi 2 request cùng `client_token` (double submit) | chỉ 1 đơn trong DB, chỉ 1 giao dịch `order_charge`, request 2 trả về đơn cũ (redirect trang đơn) |
| T8b | Gửi 2 request song song (giả lập race bằng 2 tiến trình) | UNIQUE `client_token` chặn được, không âm tiền |
| T9 | Cron: partial 400/1000 | hoàn 600, `refund_count = 1`, `order_refunds.seq = 1` |
| T10 | Cron chạy lại sau partial, số liệu không đổi | không hoàn thêm, `refund_count` vẫn 1 |
| T11 | Partial (remains 600) → canceled (remains 800) | hoàn đợt 1 = 600 (key `refund:1`), đợt 2 = 200 (key `refund:2`), tổng 800, `refund_count = 2`, không vượt `total_amount` |
| T11b | Provider báo remains > quantity | bị kẹp về `quantity`, không hoàn vượt tổng |
| T12 | Đơn completed | không hoàn tiền |
| T12b | Đơn kẹt `charged` > 10 phút | chuyển `stuck_review`, **không tự hoàn tiền**, có thông báo admin |
| T13 | Admin duyệt nạp 2 lần | chỉ cộng 1 lần |
| T14 | Admin trừ quá số dư | bị chặn, số dư không âm |
| T15 | Khách xem dịch vụ | response không chứa `cost_price` |
| T16 | Khách A truy cập đơn của khách B | 403/404 |
| T17 | Đồng bộ dịch vụ | thêm mới, cập nhật cost_price, **giữ** sell_price override, dịch vụ mất → is_active=0 không xoá |
| T18 | Quét CSS/Blade | không có `gradient(` ngoài `.hero`; có đủ token màu |
| T19 | Đổi giá sau khi đặt | đơn cũ giữ `unit_price` |
| T20 | Cron 5 phút | `schedule:list` hiển thị `orders:sync` + `deposits:expire` mỗi 5 phút |
| T21 | PayOS bỏ dở quá 30 phút | `deposit_requests` chuyển `expired`, khách tạo được yêu cầu mới |
| T22 | Parse nội dung CK | `NHOSUB ABC`, `NHOSUBABC`, `nhosub  abc` đều ra user `ABC`; `NHOSUB` không có username → unmatched |
| T23 | Đặt giá riêng rồi đồng bộ lại | `sell_price` giữ nguyên, `cost_usd`/`cost_price` cập nhật, `sell_price_override = 1` |
| T24 | `refunded_amount` không vượt `total_amount` | cố hoàn vượt → exception, rollback, số dư không đổi |

### 8.2 Nghiệm thu thủ công (báo cáo từng mục)

1. Đăng ký → đăng nhập → nạp tiền (SePay test mode qua curl mô phỏng webhook) → số dư tăng đúng.
2. Đặt đơn → trừ tiền → đơn gửi provider (demo) → cron đồng bộ trạng thái.
3. Đơn partial → hoàn đúng phần chưa chạy, không hoàn trùng.
4. Webhook trùng → không cộng trùng.
5. Admin: đồng bộ dịch vụ, đổi giá, duyệt nạp tay, cộng/trừ tiền, xem nhật ký webhook.
6. Giao diện: chụp màn hình landing + panel ở 1440px và 390px; kiểm tra hover bằng DevTools/Playwright; quét gradient; kiểm tra console rỗng.

### 8.3 Bàn giao và checklist deploy bắt buộc

**File bàn giao:**
- `.env.example` đầy đủ (APP, DB, MAIL, SEPAY/PAYOS để trống, ADMIN_SEED_*).
- `database/migrations` + `database/seeders` (admin đầu tiên, danh mục mẫu, dịch vụ demo, cấu hình mặc định). Seeder admin **chỉ chạy khi `ADMIN_SEED_ENABLED=true`** và tự đặt `false` sau lần đầu (ghi file `storage/app/.admin_seeded`).
- `public/.htaccess` + `.htaccess` gốc rewrite về `public/` cho hosting không đổi được document root.
- Cron mẫu: `* * * * * cd /home/user/domain && php artisan schedule:run >> /dev/null 2>&1`.

**Checklist deploy — mục nào chưa tick thì KHÔNG được mở bán** (README có cùng danh sách, đánh số):

| # | Mục | Bắt buộc | Kiểm tra |
|---|---|---|---|
| D1 | **PHP ≥ 8.2** (khuyến nghị 8.3) + extension: pdo_mysql, mbstring, openssl, ctype, json, fileinfo, curl, gd | ✅ | `php -v`, trang `admin > hệ thống` hiện phiên bản |
| D2 | Document root trỏ `public/`, hoặc `.htaccess` rewrite | ✅ | Mở `/` không lộ `/.env` |
| D3 | `.env` với `APP_KEY`, `APP_ENV=production`, `APP_DEBUG=false` | ✅ | Trang lỗi không lộ stack trace |
| D4 | `php artisan migrate --force` + seed | ✅ | Đăng nhập được admin |
| D5 | **Cron `schedule:run` mỗi phút** | ✅ | `schedule:list` có `orders:sync` 5 phút, `deposits:expire` 5 phút |
| D6 | **SMTP thật** (`MAIL_MAILER=smtp`) — bắt buộc để quên mật khẩu chạy được | ✅ | Gửi thử reset mật khẩu nhận được mail |
| D7 | Cấu hình ngân hàng + SePay webhook, gọi thử 1 giao dịch thật ≥ 10.000₫ | ✅ | Số dư tăng, `webhook_logs` = processed |
| D8 | PayOS (nếu bật) đã có `client_id`, `api_key`, `checksum_key` | ⬜ tuỳ chọn | Nút PayOS hiện, tạo link được |
| D9 | HTTPS + `APP_URL` đúng domain | ✅ | Không mixed content |
| D10 | Backup DB hằng ngày ra ngoài `public_html` | ✅ | Có file backup |
| D11 | Đổi mật khẩu admin mặc định sau lần đăng nhập đầu | ✅ | |
| D12 | **Thay logo gốc** vào `public/assets/img/logo.svg` | ⬜ chờ file | |

## 9. Ngoài phạm vi

**Giai đoạn 2 (theo prompt mục 7):** mass order, drip-feed, refill/hủy đơn phía khách, ticket có timeline, tin tức/FAQ động, affiliate, API riêng cho đại lý, email thông báo (ngoài reset mật khẩu), chế độ bảo trì.

**Dời khỏi giai đoạn 1 nhưng cần bạn biết rõ:**
- **MoMo:** không triển khai ở giai đoạn 1. Chỉ có `settings.momo_enabled` (mặc định tắt) + `MomoGateway` skeleton ném `NotImplemented`, để giai đoạn sau cắm vào không phải sửa UI. Lý do: MoMo yêu cầu hợp đồng doanh nghiệp, không test được ngay; prompt gốc ghi "MoMo và/hoặc PayOS" nên chọn PayOS. **Nếu bạn muốn MoMo ngay từ giai đoạn 1, nói trước khi tôi bắt đầu code.**
- **PayOS** cũng cần bạn đăng ký tài khoản PayOS trước khi dùng thật; chưa có thì hệ thống vẫn chạy đủ với SePay + duyệt tay.

**Thêm ngoài prompt gốc (đã ghi rõ để bạn quyết định giữ hay bỏ):**
- `notifications` — chuông thông báo trong panel, phục vụ yêu cầu "thông báo mới nhất" ở trang tổng quan.
- `order_refunds` — sổ hoàn tiền nhiều đợt, cần thiết để hoàn tiền partial đúng và không trùng.
- Trang **Hỗ trợ** tĩnh (hướng dẫn/FAQ/liên hệ) trong menu — prompt yêu cầu có nhóm menu Hỗ trợ.

Cấu trúc dữ liệu và tầng service được thiết kế để thêm các module này mà không sửa luồng giai đoạn 1.
