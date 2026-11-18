<?php

namespace App\Http\Controllers;

use App\Exceptions\NapxuException;
use App\Http\Requests\StoreTikTokCookieRequest;
use App\Models\TikTokAccount;
use App\Services\Napxu\TikTokAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TikTokAccountController extends Controller
{
    public function __construct(private readonly TikTokAccountService $accounts)
    {
    }

    public function index(Request $request): View
    {
        return view('panel.tiktok-accounts', ['accounts' => TikTokAccount::where('user_id', $request->user()->id)
            ->latest('id')->paginate(15)]);
    }

    public function qr(Request $request): JsonResponse
    {
        abort_if($request->user()->isLocked(), 403);
        try {
            return response()->json($this->accounts->createQrSession($request->user()->id));
        } catch (NapxuException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }
    }

    public function poll(Request $request, string $sessionId): JsonResponse
    {
        abort_if($request->user()->isLocked(), 403);
        try {
            return response()->json($this->accounts->pollQrSession($request->user()->id, $sessionId));
        } catch (NapxuException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }
    }

    public function cookie(StoreTikTokCookieRequest $request): RedirectResponse
    {
        try {
            $this->accounts->addByCookie($request->user()->id, (string) $request->input('cookie'));
        } catch (NapxuException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('panel.tiktok.index')->with('success', 'Đã liên kết tài khoản TikTok.');
    }

    public function refresh(Request $request, int $id): RedirectResponse
    {
        $account = TikTokAccount::where('user_id', $request->user()->id)->findOrFail($id);
        try {
            $this->accounts->syncCoins($account);
        } catch (NapxuException $e) {
            return back()->with('warning', $e->getMessage());
        }

        return back()->with('success', 'Đã cập nhật tài khoản TikTok.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $account = TikTokAccount::where('user_id', $request->user()->id)->findOrFail($id);
        $account->delete();

        return back()->with('success', 'Đã gỡ liên kết trên NhoSub. Lịch sử đơn vẫn được giữ.');
    }
}
