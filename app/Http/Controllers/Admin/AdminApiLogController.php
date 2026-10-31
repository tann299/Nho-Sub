<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiSyncLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminApiLogController extends Controller
{
    public function index(Request $request): View
    {
        $action = (string) $request->query('action', '');
        $success = $request->query('success');

        $logs = ApiSyncLog::query()
            ->when($action !== '', fn ($x) => $x->where('action', $action))
            ->when($success !== null && $success !== '', fn ($x) => $x->where('success', (bool) $success))
            ->latest('id')
            ->paginate(40)
            ->withQueryString();

        return view('admin.api-logs', [
            'logs' => $logs,
            'action' => $action,
            'success' => $success,
            'actions' => ApiSyncLog::distinct()->pluck('action'),
        ]);
    }
}
