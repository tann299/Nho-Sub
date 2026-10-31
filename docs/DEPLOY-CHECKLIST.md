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
