<?php

namespace App\Http\Controllers;

use App\Models\DepositRequest;
use App\Models\User;
use App\Models\WebhookLog;
use App\Services\DepositService;
use App\Services\PayOS\PayOSClient;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
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

        // Lấy yêu cầu nạp đang chờ của khách (duyệt tay) — có mã xác thực.
        $pendingRequest = DepositRequest::where('user_id', $user->id)
            ->where('status', DepositRequest::STATUS_PENDING)
            ->latest('id')
            ->first();

        // Mã xác thực dùng cho chuyển khoản nhanh (không qua yêu cầu duyệt tay):
        // sinh theo phiên — khách chỉ cần kèm mã này trong nội dung CK.
        $transferContent = $user->transferContent();

        return view('panel.deposit', [
            'user' => $user,
            'transferContent' => $transferContent,
            'pendingRequest' => $pendingRequest,
            'minDeposit' => $this->settings->getInt('min_deposit', 10000),
            'bankName' => $this->settings->get('bank_name', ''),
            'bankAccount' => $this->settings->get('bank_account', ''),
            'bankOwner' => $this->settings->get('bank_owner', ''),
            'bankBin' => $this->settings->get('bank_bin', ''),
            'payosEnabled' => $this->payos->isEnabled(),
            // Lịch sử ĐẦY ĐỦ: gộp yêu cầu nạp (mọi loại) + giao dịch SePay tự động
            // theo wallet_transactions — sắp theo thời gian mới nhất trước.
            'requests' => $this->fullHistory($user->id),
        ]);
    }

    /**
     * Lịch sử nạp tiền đầy đủ của một khách: mọi nguồn, không giới hạn 20 dòng.
     *
     * Nguồn 1 — deposit_requests: yêu cầu duyệt tay + nạp SePay được ghi thành request.
     * Nguồn 2 — webhook_logs: mọi giao dịch SePay (kể cả rejected/unmatched) cùng user
     *           (nhận diện qua mã username trong nội dung CK).
     * Nguồn 3 — wallet_transactions: dòng deposit đã cộng ví (luôn chính xác).
     *
     * @return array<int, array{time: string, amount: int, method: string, status: string, note: string}>
     */
    private function fullHistory(int $userId): array
    {
        $rows = [];

        // 1) Yêu cầu nạp (duyệt tay + SePay tạo request)
        foreach (DepositRequest::where('user_id', $userId)->latest('id')->get() as $d) {
            $rows[] = [
                'time' => $d->created_at,
                'amount' => (int) $d->amount,
                'method' => $d->method === DepositRequest::METHOD_PAYOS ? 'PayOS' : ($d->method === 'sepay' ? 'SePay (tự động)' : 'Chuyển khoản'),
                'status' => $d->status,
                'note' => $d->admin_note ?: ($d->verification_code ? 'Mã xác thực ' . $d->verification_code : ''),
            ];
        }

        // 2) Giao dịch SePay qua webhook — mọi trạng thái, khớp user qua nội dung CK
        $username = optional(User::find($userId))->username;
        $codes = DepositRequest::where('user_id', $userId)->pluck('verification_code')->filter()->all();

        foreach (
            WebhookLog::where('gateway', 'sepay')
                ->whereIn('status', [WebhookLog::STATUS_PROCESSED, WebhookLog::STATUS_REJECTED, WebhookLog::STATUS_UNMATCHED])
                ->orderByDesc('id')->get() as $w
        ) {
            $content = (string) $w->content;
            $isMine = $username !== null && stripos($content, 'NHOSUB ' . $username) !== false;
            if (! $isMine) {
                foreach ($codes as $c) {
                    if ($c !== null && str_contains($content, $c)) { $isMine = true; break; }
                }
            }
            if (! $isMine) {
                continue;
            }

            $rows[] = [
                'time' => $w->created_at,
                'amount' => (int) $w->amount,
                'method' => 'SePay (tự động)',
                'status' => $w->status, // processed | rejected | unmatched
                'note' => $w->reason ?: mb_substr($content, 0, 60),
            ];
        }

        // Sắp mới nhất trước, bỏ trùng (cùng thời điểm cùng số tiền cùng trạng thái)
        usort($rows, fn ($a, $b) => $b['time'] <=> $a['time']);

        return $rows;
    }

    /**
     * Trạng thái nạp tiền realtime — đối chiếu mã xác thực của khách.
     *
     * Webhook mới nhất khớp mã đang sống của khách → đã có tiền về.
     * Trả JSON cho trang nạp tiền poll mỗi 5 giây.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        // Yêu cầu nạp có mã xác thực đang sống (pending) của khách này
        $pending = DepositRequest::where('user_id', $user->id)
            ->where('status', DepositRequest::STATUS_PENDING)
            ->whereNotNull('verification_code')
            ->latest('id')
            ->first();

        $liveCodes = DepositRequest::query()
            ->where('user_id', $user->id)
            ->whereNotNull('verification_code')
            ->whereIn('status', [DepositRequest::STATUS_PENDING])
            ->pluck('verification_code');

        // Giao dịch mới nhất khớp mã xác thực của khách (dựa trên nội dung CK)
        $latest = WebhookLog::where('gateway', 'sepay')
            ->whereIn('status', [
                WebhookLog::STATUS_PROCESSED,
                WebhookLog::STATUS_UNMATCHED,
                WebhookLog::STATUS_REJECTED,
            ])
            ->where('created_at', '>=', now()->subMinutes(30))
            ->orderByDesc('id')
            ->first();

        if ($latest !== null && $latest->status === WebhookLog::STATUS_PROCESSED) {
            $content = (string) $latest->content;

            foreach ($liveCodes as $code) {
                if ($code !== null && str_contains($content, $code)) {
                    return response()->json([
                        'status' => 'processed',
                        'amount' => (int) $latest->amount,
                    ]);
                }
            }
        }

        if ($latest !== null && in_array($latest->status, [WebhookLog::STATUS_UNMATCHED, WebhookLog::STATUS_REJECTED], true)) {
            $expected = $latest->expected_code;

            // Unmatched kèm mã khách sắp chuyển (đúng mã phiên) → báo thất bại rõ
            if ($expected !== null && $liveCodes->contains($expected)) {
                return response()->json([
                    'status' => 'unmatched',
                    'reason' => $latest->reason,
                ]);
            }
        }

        return response()->json(['status' => 'waiting']);
    }

    /** Gửi yêu cầu nạp duyệt tay. */
    public function store(Request $request): RedirectResponse
    {
        // min khớp đúng cấu hình min_deposit (tránh thông báo lệch với service).
        $minDeposit = max(1000, $this->settings->getInt('min_deposit', 10000));

        $data = $request->validate([
            'amount' => ['required', 'integer', "min:{$minDeposit}", 'max:1000000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'amount.required' => 'Vui lòng nhập số tiền đã chuyển.',
            'amount.integer' => 'Số tiền phải là số nguyên.',
            'amount.min' => 'Số tiền nạp tối thiểu là ' . number_format($minDeposit, 0, ',', '.') . ' ₫.',
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
