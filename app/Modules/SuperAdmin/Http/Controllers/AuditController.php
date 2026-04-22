<?php

namespace Modules\SuperAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Read-only audit log viewer + CSV export. Every row comes from
 * AuditLogger::record() calls inside SuperAdmin mutating actions —
 * see docs on the service for the full vocabulary of action names.
 */
class AuditController extends Controller
{
    public function index(Request $request): View
    {
        $query = AuditLog::query()->with('superAdmin');

        if ($action = $request->string('action')->toString()) {
            $query->where('action', 'like', "{$action}%");
        }
        if ($sa = $request->integer('super_admin_id')) {
            $query->where('super_admin_id', $sa);
        }
        if ($from = $request->string('from')->toString()) {
            $query->where('created_at', '>=', $from);
        }
        if ($to = $request->string('to')->toString()) {
            $query->where('created_at', '<=', $to . ' 23:59:59');
        }

        return view('super-admin.audit.index', [
            'logs'    => $query->latest()->paginate(50)->withQueryString(),
            'filters' => $request->only(['action', 'super_admin_id', 'from', 'to']),
        ]);
    }

    /**
     * Stream a CSV of the current filter window. Same scope as the
     * list page but without pagination, so operators can pull a month's
     * activity into Excel/Sheets without clicking through 50 pages.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = AuditLog::query()->with('superAdmin');

        if ($action = $request->string('action')->toString()) {
            $query->where('action', 'like', "{$action}%");
        }
        if ($from = $request->string('from')->toString()) {
            $query->where('created_at', '>=', $from);
        }
        if ($to = $request->string('to')->toString()) {
            $query->where('created_at', '<=', $to . ' 23:59:59');
        }

        $filename = 'audit-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel displays Arabic correctly
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['datetime', 'actor', 'action', 'target_type', 'target_id', 'reason', 'ip']);

            $query->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $log) {
                    fputcsv($out, [
                        $log->created_at?->toIso8601String(),
                        $log->superAdmin?->email ?? $log->actor_type,
                        $log->action,
                        $log->target_type,
                        $log->target_id,
                        $log->reason,
                        $log->ip_address,
                    ]);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=utf-8']);
    }
}
