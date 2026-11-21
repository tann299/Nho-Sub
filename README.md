<div align="center">

<img src="public/assets/img/logo.png" alt="NhoSub" width="120">

# NhoSub

**Panel đại lý SMM & Nạp xu TikTok**

*Sub nhỏ, lớn dần cùng bạn.*

[![Laravel](https://img.shields.io/badge/Laravel_12-FF2D20?style=flat-square&logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=flat-square&logo=mysql&logoColor=white)](https://www.mysql.com)
[![Tests](https://img.shields.io/badge/tests-249_passing-16A34A?style=flat-square&logo=githubactions&logoColor=white)](docs/HUONG-DAN-CHI-TIET.md)
[![Deploy](https://img.shields.io/badge/shared_hosting-ready-1E6FD9?style=flat-square)](docs/DEPLOY-HUONG-DAN.md)

**Website bán dịch vụ tăng tương tác mạng xã hội (like, follow, view, comment) cho
Facebook, TikTok, Instagram, YouTube — kèm mảng nạp xu TikTok.** Mô hình đại lý: mua
sỉ qua API từ nhà cung cấp, bán lại cho khách lẻ, trả bằng ví nội bộ.

[🚀 Cài đặt](#-quick-start) · [📖 Hướng dẫn chi tiết](docs/HUONG-DAN-CHI-TIET.md) · [🚢 Deploy hosting](docs/DEPLOY-HUONG-DAN.md) · [🔐 Bảo mật](#-bảo-mật)

</div>

---

## ✨ Tính năng

| | |
|---|---|
| 🛒 **Đặt đơn SMM tự động** | Mua sỉ từ TopSubRe qua API V2, giá đóng băng theo đơn, cron đồng bộ trạng thái 5 phút và tự hoàn tiền phần chưa chạy |
| ⚡ **Napxu — 65 dịch vụ boost** | NCC thứ hai cho TikTok/Facebook/Instagram/YouTube; chọn NCC trong trang Cấu hình |
| 🪙 **Nạp xu TikTok** | Khách liên kết tài khoản TikTok (QR hoặc cookie), đặt gói xu trả bằng ví NhoSub; đơn timeout → chờ đối soát, không tự hoàn nhầm |
| 💰 **Ví nội bộ có sổ cái** | Mỗi giao dịch một khóa idempotency UNIQUE — chống cộng/trừ trùng kể cả webhook gửi lại hay bấm hai lần |
| 💳 **Thanh toán** | SePay webhook + PayOS + duyệt tay; QR VietQR theo mã BIN |
| 🛡️ **Bảo mật nhiều lớp** | Key NCC mã hoá AES-256, webhook `hash_equals` + chống replay, rate limit đăng nhập, log admin mọi POST |
| 📊 **Trang quản trị** | Dashboard tổng quan, quản lý đơn/dịch vụ/gói nạp xu, xử lý đơn kẹt, nhật ký API |
| 🧪 **Bộ test thật** | 249 test / 957 assertions trên MySQL thật — khoá dòng, UNIQUE, deadlock đều kiểm chứng |

## 🚀 Quick Start

```bash
composer install
copy .env.example .env
php artisan key:generate
```

```bash
mysql -uroot -e "CREATE DATABASE nhosub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Sửa `.env`: `DB_DATABASE=nhosub`, `DB_USERNAME`, `DB_PASSWORD`, `ADMIN_SEED_ENABLED=true` — rồi:

```bash
php artisan migrate --seed
php artisan serve
```

Mở `http://localhost:8000` — đăng nhập admin bằng `ADMIN_USERNAME` / `ADMIN_PASSWORD` trong `.env`.

> 💡 **Không cần Node/Redis/Vite** — CSS/JS đã biên dịch sẵn trong `public/assets/`.

## 🏗️ Kiến trúc

```
                          ┌──────────────────────────┐
        Khách hàng ──────▶│      Ví NhoSub (sổ cái)   │
                          └────────────┬─────────────┘
                     ┌─────────────────┴──────────────────┐
                     ▼                                    ▼
        ┌────────────────────────┐          ┌────────────────────────┐
        │ OrderService (SMM)      │          │ TopupOrderService (xu) │
        │ ProviderInterface       │          │ NapxuClient (Bearer)   │
        └───────────┬────────────┘          └───────────┬────────────┘
                    ▼                                   ▼
        ┌────────────────────────┐          ┌────────────────────────┐
        │ TopSubRe  /  Napxu      │          │  Napxu.vn (nạp xu)     │
        │ (boost 65 dịch vụ)      │          │  QR / cookie / đơn xu  │
        └────────────────────────┘          └────────────────────────┘
```

## 🔐 Bảo mật

- 🔑 API key nhà cung cấp mã hoá **AES-256-CBC** bằng `APP_KEY`, chỉ hiện 4 ký tự cuối
- 🪪 Cookie TikTok **không lưu DB** — đẩy thẳng tới Napxu rồi bỏ đi
- 🧱 Webhook so sánh key bằng `hash_equals`, chống replay bằng UNIQUE `(gateway, gateway_txn_id)`
- 🚫 Khách chỉ truy vấn đơn/tài khoản của chính mình — lọc `user_id` ở server, không chỉ ẩn UI
- 📜 Mọi POST quản trị ghi log admin 90 ngày; log không chứa key/mật khẩu

## 📚 Tài liệu

| | |
|---|---|
| 📖 [Hướng dẫn chi tiết](docs/HUONG-DAN-CHI-TIET.md) | Cấu hình giá, thay logo, khác biệt so với SRS, cấu trúc thư mục |
| 🚢 [Deploy hosting](docs/DEPLOY-HUONG-DAN.md) | Đẩy lên shared hosting cPanel/DirectAdmin từng bước |
| ✅ [Checklist triển khai](docs/DEPLOY-CHECKLIST.md) | D1–D12 kiểm tra trước khi mở bán |
| 💳 [SePay](docs/HUONG-DAN-SEPAY.md) | Cấu hình webhook ngân hàng |

## 🗺️ Lộ trình

- [x] SMM đặt đơn qua TopSubRe + hoàn tiền tự động
- [x] Napxu boost 65 dịch vụ (NCC chọn được)
- [x] Nạp xu TikTok: QR/cookie, ví, cron đối soát
- [ ] Drip-feed, mass order, refill phía khách
- [ ] Ticket timeline, affiliate, API đại lý cấp dưới

<div align="center">

*NhoSub — Sub nhỏ, lớn dần cùng bạn* 🌱

</div>
