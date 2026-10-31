<?php

namespace App\Http\Controllers;

use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WalletController extends Controller
{
    public function index(Request $request): View
    {
        $type = (string) $request->query('type', '');

        $transactions = WalletTransaction::where('user_id', $request->user()->id)
            ->when($type !== '', fn ($q) => $q->where('type', $type))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('panel.wallet', [
            'user' => $request->user(),
            'transactions' => $transactions,
            'type' => $type,
        ]);
    }
}
