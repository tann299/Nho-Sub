# Hướng dẫn cấu hình SePay cho NhoSub (tna.id.vn)

Webhook URL của bạn: **`https://tna.id.vn/webhook/sepay`**

---

## Bước 1 — Đăng ký tài khoản SePay

1. Vào **https://sepay.vn** → bấm **Đăng ký**
2. Điền: số điện thoại, email, mật khẩu → xác thực OTP qua SMS/email
3. Đăng nhập vào **Dashboard** SePay

> SePay miễn phí gói cơ bản, không cần thẻ tín dụng.

---

## Bước 2 — Kết nối tài khoản ngân hàng

1. Dashboard SePay → menu trái → **Tài khoản ngân hàng** (hoặc **Liên kết ngân hàng**)
2. Bấm **Thêm tài khoản** → chọn ngân hàng của bạn (MB Bank, Vietcombank, Techcombank, ACB…)
3. Nhập:
   - Số tài khoản
   - Tên đăng nhập Internet Banking
   - Mật khẩu Internet Banking
4. SePay sẽ đọc **sao kê** của tài khoản đó (chỉ đọc, không chuyển tiền được)

> Đây là cơ chế chính thức của SePay: họ đăng nhập vào Internet Banking của bạn để phát hiện giao dịch mới, rồi bắn webhook về NhoSub.

**Lưu ý quan trọng:** Nếu ngân hàng yêu cầu OTP mỗi lần đăng nhập, SePay có hướng dẫn riêng cho từng ngân hàng — xem mục "Hướng dẫn" trong dashboard SePay.

---

## Bước 3 — Tạo Webhook (form 4 bước)

Vào **Dashboard SePay → menu trái → Tích hợp → Webhooks**

Bấm nút **+ Thêm webhook** ở góc trên bên phải. Form trượt ra từ bên phải, đi qua **4 bước**.

### Bước 1/4 — Cơ bản

| Trường | Điền gì |
|---|---|
| **Tên webhook** | `NhoSub - Nạp tiền` (tuỳ ý, tối đa 300 ký tự) |
| **URL nhận webhook** | `https://tna.id.vn/webhook/sepay` |
| **Loại sự kiện** | **Tiền vào** ← chọn đúng cái này, không chọn "Cả hai" |
| **Định dạng dữ liệu** | **JSON** (`application/json`) — giữ mặc định |
| **Tự động gửi lại khi server trả lỗi** | **BẬT** (tick) |

### Bước 2/4 — Tài khoản

| Trường | Điền gì |
|---|---|
| **Tài khoản ngân hàng** | **Tất cả tài khoản** (hoặc tick đúng tài khoản bạn đã kết nối) |
| **Dùng để xác thực thanh toán** | **TẮT** (để trống) — NhoSub tự đối chiếu mã, không cần SePay lọc |

> Nếu bật "Dùng để xác thực thanh toán", SePay sẽ đòi cấu hình "Cấu trúc mã thanh toán" ở Công ty → Cấu hình chung, rồi mới lọc được. Không cần thiết cho NhoSub — để tắt cho đơn giản.

### Bước 3/4 — Bảo mật ⚠️ QUAN TRỌNG NHẤT

Chọn phương thức xác thực: **API Key**

| Trường | Điền gì |
|---|---|
| **Phương thức** | **API Key** (KHÔNG chọn "Không xác thực") |
| **API Key** | Tự đặt một chuỗi bí mật, ví dụ: `nhosub-tna-2026-a7k2p9x4` |

> **Đây là điểm dễ sai nhất.** Cái key bạn gõ ở ô này **chính là** key phải dán vào NhoSub ở Bước 4. Không phải key nào lấy từ chỗ khác — bạn tự đặt, miễn 2 bên giống nhau.
>
> SePay sẽ gửi header `Authorization: Apikey <key bạn đặt>`. NhoSub so sánh bằng `hash_equals` — phải khớp từng ký tự, không có khoảng trắng thừa.
>
> **Ghi lại key này ngay** (copy ra Notepad) — SePay không hiện lại đầy đủ sau khi lưu.

### Bước 4/4 — Cảnh báo (tuỳ chọn)

| Trường | Điền gì |
|---|---|
| **Bật cảnh báo** | Tuỳ bạn — bật thì SePay báo qua Telegram/Slack khi webhook lỗi |
| **Ngưỡng lỗi liên tiếp** | `3` (mặc định) |
| **Kênh nhận** | Telegram / Slack / Discord (nếu có) |

Bấm **Thêm** ở cuối form để lưu.

### Kiểm tra ngay bằng nút "Gửi thử"

Trong danh sách webhook, bấm **⋮** (ba chấm) trên dòng vừa tạo → **Gửi thử**.

SePay gửi payload mẫu tới `https://tna.id.vn/webhook/sepay` và hiện ngay kết quả:

| Kết quả | Nghĩa |
|---|---|
| **Thành công** + HTTP 200 + body `{"success":true,...}` | ✅ Đúng — key khớp, endpoint chạy |
| **Thất bại** + HTTP 401 | ❌ Key chưa nhập vào NhoSub, hoặc nhập sai → làm Bước 4 |
| **Thất bại** + HTTP 503 | ❌ NhoSub chưa bật SePay / chưa có key → làm Bước 4 |
| **Thất bại** + timeout / không kết nối | ❌ URL sai, hoặc SSL lỗi |

> Payload "Gửi thử" có `id` là số mock (thường `0`) — nên nó sẽ bị NhoSub xử lý như giao dịch thật nếu key đúng. Xem Nhật ký webhook rồi xoá dòng test đó nếu cần.

---

## Bước 4 — Nhập key vào NhoSub

1. Đăng nhập **https://tna.id.vn/login** bằng `admin` / `NhoSub@2026!`
2. Vào **Quản trị → Cấu hình** (menu trái, nhóm Quản trị)
3. Kéo xuống mục **Cổng thanh toán**:
   - **SePay đang bật**: ✓ tick
   - **SePay webhook key**: dán **đúng key bạn tự đặt ở Bước 3/4 — ô "API Key"**
4. Bấm **Lưu cấu hình**
5. Quay lại SePay → **⋮ → Gửi thử** → phải ra **Thành công**

Key được mã hoá AES-256 bằng `APP_KEY` trước khi lưu vào database — không ai đọc được, kể cả bạn (chỉ hiện dạng `••••••••x4`).

---

## Bước 5 — Cấu hình thông tin nhận tiền

Cùng trang **Quản trị → Cấu hình**, mục **Thông tin ngân hàng**:

| Trường | Giá trị ví dụ |
|---|---|
| **Tên ngân hàng** | `MB Bank` |
| **Số tài khoản** | `0123456789` |
| **Chủ tài khoản** | `NGUYEN VAN A` |
| **Mã BIN** | `970422` (MB Bank) |

**Mã BIN các ngân hàng phổ biến:**

| Ngân hàng | BIN |
|---|---|
| MB Bank | `970422` |
| Vietcombank | `970436` |
| Techcombank | `970407` |
| ACB | `970416` |
| BIDV | `970418` |
| VietinBank | `970415` |
| VPBank | `970432` |
| TPBank | `970423` |
| Sacombank | `970403` |
| Agribank | `970405` |

Sau khi lưu, vào **Nạp tiền** — mã QR VietQR sẽ hiện đúng số tài khoản + số tiền + nội dung có mã xác thực.

---

## Bước 6 — Test thật

1. Đăng nhập tài khoản khách (hoặc tạo tài khoản mới)
2. Vào **Nạp tiền**
3. Nhập **20.000** → QR hiện ra + nội dung CK dạng:
   ```
   NHOSUB KHACHTEST N20000 A7K2P9
   ```
4. Mở app ngân hàng → **quét QR** (số tiền và nội dung tự điền)
5. Bấm chuyển
6. Chờ **5–30 giây**, tải lại trang → **số dư tăng 20.000 ₫**
7. Vào **Quản trị → Nhật ký webhook** (`https://tna.id.vn/admin/webhooks`) → phải thấy 1 dòng trạng thái **`processed`**

---

## Cách hoạt động (để hiểu khi debug)

```
Khách quét QR
    ↓
Chuyển khoản vào MB Bank
    ↓
SePay đọc sao kê (5-30 giây)
    ↓
SePay POST https://tna.id.vn/webhook/sepay
    Header: Authorization: Apikey <key>
    Body: {id, transferType:"in", transferAmount, content, referenceCode}
    ↓
NhoSub kiểm tra:
    - Header khớp key? → không khớp: 401
    - Đã xử lý txn id này chưa? → rồi: bỏ qua (chống trùng)
    - Số tiền >= 10.000? → không: reject
    - Tìm mã xác thực 6 ký tự trong nội dung → khớp: cộng đúng người
    - Không có mã → fallback tìm username "NHOSUB <username>"
    - Không tìm được → ghi log "unmatched" để admin xử tay
    ↓
Cộng tiền vào ví + ghi wallet_transactions
```

---

## Xử lý sự cố

| Vấn đề | Nguyên nhân | Cách sửa |
|---|---|---|
| **Chuyển tiền nhưng ví không tăng** | Webhook chưa về | Vào `https://tna.id.vn/admin/webhooks` xem có bản ghi nào chưa |
| **Log webhook trống** | SePay chưa gọi được | Kiểm tra URL webhook trên SePay đúng `https://tna.id.vn/webhook/sepay`; thử nút "Test webhook" trên SePay |
| **Log có nhưng `unmatched`** | Nội dung CK không có mã/username đúng | Admin vào Nhật ký webhook → bấm **Cộng tay** sau khi xác minh sao kê |
| **Log `rejected` "dưới mức tối thiểu"** | Chuyển < 10.000 ₫ | Tăng `min_deposit` hoặc chuyển đủ |
| **401 Unauthorized** | Key NhoSub ≠ Token SePay | So sánh lại, dán chính xác (không có khoảng trắng) |
| **503 "Cổng chưa cấu hình"** | Chưa nhập key vào NhoSub | Làm Bước 4 |
| **SePay báo webhook lỗi timeout** | Server chậm | Kiểm tra log NhoSub, đảm bảo cron chạy |
| **Cộng trùng 2 lần** | Không thể — đã chặn bằng UNIQUE `(gateway, gateway_txn_id)` | — |

---

## Kiểm tra webhook thủ công (test nhanh)

Dùng lệnh sau trên máy bạn để giả lập SePay gọi về:

```bash
curl -X POST https://tna.id.vn/webhook/sepay \
  -H "Authorization: Apikey <KEY_CỦA_BẠN>" \
  -H "Content-Type: application/json" \
  -d '{
    "id": 999001,
    "transferType": "in",
    "transferAmount": 20000,
    "content": "NHOSUB admin N20000 TEST01",
    "referenceCode": "FT999001"
  }'
```

Kết quả đúng:
```json
{"success":true,"message":"Đã cộng tiền."}
```

Sau đó vào **Quản trị → Nhật ký webhook** thấy dòng `processed`.

> Nhớ xoá giao dịch test khỏi DB nếu không muốn admin bị cộng 20.000 ₫ ảo — hoặc dùng đúng tài khoản admin và trừ lại sau.

---

## Sau khi cấu hình xong

Checklist hoàn tất:

- [ ] SePay đã kết nối ngân hàng
- [ ] API Key đã copy
- [ ] Webhook URL = `https://tna.id.vn/webhook/sepay`
- [ ] Kiểu xác thực = Apikey, token = API Key
- [ ] NhoSub đã nhập key + tick "SePay đang bật"
- [ ] Thông tin ngân hàng đã điền (BIN đúng)
- [ ] Đã test 1 giao dịch thật → ví tăng
- [ ] Nhật ký webhook có `processed`

Xong là khách nạp tiền tự động 24/7, không cần admin duyệt tay.
