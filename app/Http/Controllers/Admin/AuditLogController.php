<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->when($request->filled('event'), fn ($q) => $q->where('event', $request->input('event')))
            ->when($request->filled('user'), fn ($q) => $q->where('user_id', $request->integer('user')))
            ->when($request->filled('type'), fn ($q) => $q->where('auditable_type', $request->input('type')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.audit-logs', [
            'logs' => $logs,
            'events' => AuditLog::distinct()->orderBy('event')->pluck('event'),
            'types' => AuditLog::whereNotNull('auditable_type')->distinct()->pluck('auditable_type'),
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
