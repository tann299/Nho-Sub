# Báo cáo nghiệm thu — NhoSub Giai đoạn 1

- **Ngày:** 2026-10-31
- **Môi trường:** PHP 8.3.24 (CLI), MySQL 8.0.30, Laravel 12.70.2, Windows 11 (Laragon)
- **Test tự động:** `php artisan test` → **168 passed (616 assertions)**, chạy trên MySQL thật `nhosub_testing`
- **Code review độc lập:** 2 Critical + 4 Medium phát hiện → đã fix 2 Critical + 2 Medium (xem cuối file)

## Mục 1 — Đăng ký → đăng nhập → nạp tiền (SePay test mode) → số dư tăng đúng

**✅ PASS** — `test_muc1_dang_ky_dang_nhap_nap_tien`

```
✓ muc1 dang ky dang nhap nap tien ... 2.94s
- Đăng ký → user role=customer, balance=0, bcrypt hash
- Đăng xuất → đăng nhập lại bằng username → redirect /dashboard
- Webhook SePay 100.000₫ "NHOSUB NGHIEMTHU01 nap tien" → balance = 100.000
- wallet_transactions có đúng 1 dòng type=deposit
```

## Mục 2 — Đặt đơn → trừ tiền → đơn gửi TopSubRe (demo) → trạng thái đồng bộ qua cron

**✅ PASS** — `test_muc2_dat_don_tru_tien_va_dong_bo`

```
✓ muc2 dat don tru tien va dong bo ... 0.25s
- Đặt 1.000 lượt × 3.380₫/1000 → trừ đúng 3.380₫ (balance 96.620)
- Đơn status=processing, provider_order_id="demo:{id}"
- Cron syncBatch() → đơn chuyển completed
```

## Mục 3 — Đơn partial → hoàn đúng phần chưa chạy, không hoàn trùng

**✅ PASS** — `test_muc3_partial_hoan_dung_va_khong_trung`

```
✓ muc3 partial hoan dung va khong trung ... 0.21s
- Demo scenario partial: remains=400 → hoàn 3380×400/1000 = 1.352₫
- Chạy cron 3 lần liên tiếp: refund_count = 1, order_refunds = 1 dòng
- Số dư = 96.620 + 1.352 = 97.972; số dư = 100.000 + tổng sổ cái
```

## Mục 4 — Webhook gửi lại trùng → không cộng trùng

**✅ PASS** — `test_muc4_webhook_trung_khong_cong_trung`

```
✓ muc4 webhook trung khong cong trung ... 0.12s
- Gửi 5 lần cùng transaction id 777001
- Số dư = 50.000 (cộng đúng 1 lần); 1 dòng wallet_transactions,
  1 dòng webhook_logs, 1 dòng deposit_requests
```

## Mục 5 — Admin: đồng bộ dịch vụ, đổi giá, duyệt nạp tay, cộng/trừ tiền, xem nhật ký webhook

**✅ PASS** — `test_muc5_admin_van_hanh_day_du`

```
✓ muc5 admin van hanh day du ... 0.28s
- POST /admin/services/sync → tạo dịch vụ từ demo provider
- Đổi giá 9999 → sell_price_override = true
- Duyệt nạp tay 30.000₫ → cộng ví 1 lần
- Cộng 20.000 / trừ 10.000 → số dư 40.000
- Xem /admin/webhooks, /admin/api-logs, /admin/settings → 200 OK
```

## Mục 6 — Giao diện

**✅ PASS** — `test_muc6_giao_dien_dung_quy_tac` + kiểm tra bằng trình duyệt thật

```
✓ muc6 giao dien dung quy tac ... 0.10s
- Landing / + /dashboard + /admin render 200, không lỗi
- Gradient: chỉ duy nhất 1 chỗ — .hero trong app.css (dòng 318);
  toàn bộ view không có gradient nào
- Bảng màu: mọi hex trong CSS nằm trong bảng quy định
  (10 token gốc + biến thể badge trạng thái đơn)
- Nút chính: background rgb(30,111,217) = #1E6FD9, transition .25s ease
```

Kiểm tra bằng trình duyệt thật (preview browser):

| Kiểm tra | Kết quả |
|---|---|
| Landing 1440px — hero, bảng giá từ DB | ✅ (ảnh chụp trong phiên) |
| Landing 375px mobile — responsive 1 cột | ✅ (ảnh chụp trong phiên) |
| Panel desktop — sidebar 8 nhóm, menu active nền xanh | ✅ |
| Panel mobile — sidebar drawer + overlay | ✅ (body.sidebar-open) |
| /admin — 4 thẻ thống kê, số dư TopSubRe 999 USD, bảng trạng thái | ✅ |
| /services — card dịch vụ giá/1000, min/max, tốc độ | ✅ |
| Console không lỗi | ✅ "No console logs" |
| Nút chính màu #1E6FD9, transition .25s | ✅ (preview_inspect) |

## Code review độc lập — đã xử lý

Reviewer chạy trên toàn branch (18 commit). Kết quả và xử lý:

| Mức | Phát hiện | Xử lý |
|---|---|---|
| Critical | PayOS webhook cộng tiền từ orderCode lạ (description do bên gửi chọn) + số tiền không đối chiếu với yêu cầu nạp | **Đã fix** — orderCode lạ chỉ ghi log `unmatched`; số tiền phải khớp đúng `deposit.amount`; idempotency luôn theo `payos-{orderCode}`. 2 test mới |
| Critical | DemoProvider gắn mã đơn theo link+quantity → 2 đơn giống nhau nhận nhầm mã | **Đã fix** — `ProviderInterface::addOrder` nhận thêm `reference` (client_token); DemoProvider đối chiếu chính xác. 1 test mới |
| Medium | Admin cộng/trừ tiền không có chống double-submit (key uuid mỗi request) | **Đã fix** — form gửi `client_token`, key idempotency theo token. 1 test mới |
| Medium | SePay trả 503 khi chưa cấu hình key → gateway retry vô hạn | **Deferred** — ops, không phải bảo mật |
| Medium | Thông báo "tài khoản bị khoá" lộ thông tin tài khoản hợp lệ | **Deferred** — SRS UC-02 2b yêu cầu thông báo riêng |
| Medium | Form nạp tay `min:1000` lệch cấu hình `min_deposit` | **Đã fix** — validation dùng đúng cấu hình |

**Đã xác nhận an toàn (reviewer kiểm tra code thật):** khoá `FOR UPDATE` + `idempotency_key` UNIQUE không có đường cộng trùng; công thức hoàn tiền nhiều đợt không bao giờ hoàn vượt tổng; regex nội dung CK không cắt nhầm username tiền tố; `intdiv` không tràn số với 100 triệu lượt; chặn `javascript:` + Blade escape toàn bộ view (không `{!! !!}`); SePay `hash_equals` + PayOS HMAC đúng; middleware admin ở server, không IDOR.

## Việc chưa làm / cần bạn cung cấp

- [ ] **Logo gốc** — đang dùng SVG tạm (mầm cây trái tim, 2 mảng #1E6FD9/#DCEBFD). Thay file `public/assets/img/logo.svg` khi có.
- [ ] **API key TopSubRe thật** — đang chạy Demo provider; nhập key ở Quản trị → Cấu hình sẽ tự chuyển sang API thật.
- [ ] **Tài khoản SePay / PayOS thật** — webhook đã code + test bằng mô phỏng; cần key thật để nghiệm thu production.
- [ ] **SMTP thật** — quên mật khẩu cần `MAIL_MAILER=smtp` (checklist D6).
