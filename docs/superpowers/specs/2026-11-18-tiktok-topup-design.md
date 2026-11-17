# Spec — Module Nạp xu TikTok qua napxu.vn

Ngày: 2026-11-18. Trạng thái: đã duyệt (trò chuyện + phê duyệt thiết kế trong chat).

## Mục tiêu

Web NhoSub vận hành đồng thời 2 mảng:

1. **SMM tăng tương tác** — TopSubRe qua `ProviderInterface` (hiện có, không đổi).
2. **Nạp xu TikTok** — napxu.vn (mới): khách liên kết tài khoản TikTok (QR hoặc Cookie),
   đặt đơn nạp xu vào tài khoản đó, trả bằng **ví NhoSub**.

## API napxu liên quan (đã kiểm tra thực tế, auth `Authorization: Bearer <key>`)

| Endpoint | Phương thức | Công dụng |
|---|---|---|
| `/api/auth/tiktok/accounts?page&limit&search` | GET | DS tài khoản TikTok đã thêm: `{stats, countries, accounts[], pagination}` |
| `/api/auth/tiktok/add` | POST | Thêm TK bằng cookie: body `{cookie}` → `{account}` |
| `/api/auth/tiktok/qr/generate` | POST | Tạo phiên QR: `{sessionId, qrImage (base64 PNG), expiresAt, timeRemaining}` |
| `/api/auth/tiktok/qr/status/:sessionId` | GET | Poll: `{status: pending|scanned|done|expired|error, account?, errorMessage?}` |
| `/api/topup/orders` | POST | Tạo đơn nạp xu: `{tiktok_account_id, package_id, coins, server, pin?}` → `{order:{id, order_ref "TU-…", status, coins, amount_vnd, package_name, tiktok_username}, newBalance}` |
| `/api/topup/orders?page&limit&status` | GET | DS đơn nạp (`orders[], pagination`) |
| `/api/topup/orders/:id` | GET | Chi tiết đơn |
| `/api/topup/orders/:id/cancel` | POST | Huỷ đơn pending → hoàn tiền napxu |
| `/api/auth/balance` | GET | Số dư ví napxu (VND) |

Lỗi: HTTP 401 key sai; 400/404 kèm `{"error": "..."}`. Không có endpoint public
liệt kê gói xu/máy chủ → admin nhập bảng giá tay.

## Kiến trúc

- Subsystem riêng `app/Services/Napxu/` — không đụng `ProviderInterface`.
- `NapxuClient`: HTTP client Bearer dùng chung (base URL + key từ settings, key đã
  mã hoá `napxu_api_key` từ giai đoạn trước).
- `TikTokAccountService`: QR flow (generate → poll → khi done lưu TK vào DB),
  thêm TK qua cookie, đồng bộ coins.
- `TopupOrderService`: đặt đơn (trừ ví qua `WalletService::apply` với idempotency
  `topup:{localOrderId}` → gọi napxu tạo đơn → lưu `napxu_order_ref`; lỗi napxu →
  hoàn ví ngay); cron sync trạng thái; `failed/refund` → tự hoàn ví.
- Cron `topup:sync` mỗi 5 phút: poll `GET /api/topup/orders?limit=100` cho các
  đơn local `pending|processing`, cập nhật trạng thái, hoàn tiền khi napxu báo
  failed/refund.
- Admin: CRUD `topup_packages` (name, coins, cost_price, sell_price, server,
  napxu_package_id, is_active) + trang đơn nạp xu (xem, hoàn tay khi kẹt).
- Khách: trang DS tài khoản TikTok (thêm qua QR modal hoặc cookie), trang đặt
  đơn nạp xu (chọn TK + gói), lịch sử đơn nạp xu.

## Bảng dữ liệu

- `tiktok_accounts`: id, user_id FK, napxu_account_id (int), username, nickname,
  avatar, coins, account_status, synced_at; UNIQUE (user_id, napxu_account_id).
- `topup_packages`: id, name, coins, cost_price (VND napxu thu), sell_price
  (VND khách trả), server (vd "server1"), napxu_package_id (int), is_active.
- `topup_orders`: id, user_id FK, tiktok_account_id FK, topup_package_id FK,
  coins, sell_price, cost_price, napxu_order_ref (nullable, index), status
  (pending|processing|success|failed|refunded|canceled), synced_at, synced_count,
  created_at/updated_at.

## Luồng đặt đơn (giao dịch tiền)

1. Khách chọn TK + gói → POST /topup.
2. Validate: gói active, TK thuộc user.
3. Trừ ví: `WalletService::apply(user, type='topup_payment', -sell_price,
   idempotency="topup-pay:{uniqid}")` — tạo local order `pending` trước.
4. Gọi napxu `POST /api/topup/orders`. Thành công → lưu `napxu_order_ref`,
   trạng thái `processing`. Lỗi → hoàn ví ngay
   (type `topup_refund`, idempotency `topup-refund:{localOrderId}`), đơn `failed`.
5. Cron napxu báo `success` → đơn `success`. `failed`/`refund` → hoàn ví + đơn
   `refunded`/`failed`.

## Ràng buộc

- Mọi giao dịch tiền qua `WalletService::apply` — không sửa `users.balance` trực tiếp.
- Key napxu không bao giờ xuất hiện trong log/exception (như TopSubRe).
- Khách chỉ thấy/đặt trên TK TikTok của chính mình (lọc user_id, không chỉ ẩn UI).
- Cookie TikTok không lưu DB — chỉ đẩy thẳng lên napxu rồi bỏ đi.
- Test TDD cho mọi service + flow tiền; PHPUnit, DB `nhosub_testing`.
- PHP 8.2+, Laravel 12, không thêm dependency mới (Http client Laravel đủ dùng).
