<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\ScopeContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function __construct(private ScopeContext $scope)
    {
    }

    public function index(Request $request): View
    {
        $query = AuditLog::with(['user', 'company'])
            ->when($this->scope->companyId(), fn ($q, $id) => $q->where(
                fn ($w) => $w->where('company_id', $id)->orWhereNull('company_id')
            ))
            ->when($request->filled('entity_type'), fn ($q) => $q->where('entity_type', strtoupper($request->string('entity_type'))))
            ->when($request->filled('action'), fn ($q) => $q->where('action', strtoupper($request->string('action'))))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->date('from')->startOfDay()))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->date('to')->endOfDay()));

        return view('audit.index', [
            'logs'        => $query->orderByDesc('created_at')->paginate(30)->withQueryString(),
            'entityTypes' => DB::connection('oracle')->table('audit_logs')
                ->distinct()->orderBy('entity_type')->pluck('entity_type'),
            'actions'     => DB::connection('oracle')->table('audit_logs')
                ->distinct()->orderBy('action')->pluck('action'),
            'filters'     => $request->only(['entity_type', 'action', 'user_id', 'from', 'to']),
        ]);
    }

    public function show(AuditLog $audit): View
    {
        return view('audit.show', ['log' => $audit->load(['user', 'company'])]);
    }
}
