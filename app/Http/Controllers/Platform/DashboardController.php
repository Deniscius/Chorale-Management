<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Platform\Audit;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class DashboardController extends Controller
{
    public function index()
    {
        $now = now();
        $stats = $this->customerSpaces()->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN suspended_at IS NOT NULL THEN 1 ELSE 0 END) as suspended')
            ->selectRaw('SUM(CASE WHEN suspended_at IS NULL AND (paid_perpetual = ? OR paid_until > ?) THEN 1 ELSE 0 END) as paid', [true, $now])
            ->selectRaw('SUM(CASE WHEN suspended_at IS NULL AND paid_perpetual = ? AND (paid_until IS NULL OR paid_until <= ?) AND trial_ends_at > ? THEN 1 ELSE 0 END) as trial', [false, $now, $now])
            ->selectRaw('SUM(CASE WHEN suspended_at IS NULL AND paid_perpetual = ? AND (paid_until IS NULL OR paid_until <= ?) AND trial_ends_at <= ? THEN 1 ELSE 0 END) as expired', [false, $now, $now])->first();
        $usage = $this->usage();
        $signups = $this->customerSpaces()->where('created_at', '>=', now()->subDays(30))->count();
        $recent = Tenant::whereIn('id', $this->customerSpaces()->select('tenants.id'))->orderByDesc('id')->limit(5)->get();

        return view('platform.dashboard', compact('stats', 'usage', 'signups', 'recent'));
    }

    public function tenants(Request $request)
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:120'], 'status' => ['nullable', Rule::in(['trial', 'paid', 'expired', 'suspended'])]]);
        $query = Tenant::whereIn('id', $this->customerSpaces()->select('tenants.id'));
        if (filled($filters['q'] ?? null)) {
            $query->where('name', 'like', '%'.$filters['q'].'%');
        }
        switch ($filters['status'] ?? '') {
            case 'suspended': $query->whereNotNull('suspended_at');
                break;
            case 'paid': $query->whereNull('suspended_at')->where(fn ($q) => $q->where('paid_perpetual', true)->orWhere('paid_until', '>', now()));
                break;
            case 'trial':
            case 'expired':
                $query->whereNull('suspended_at')->where('paid_perpetual', false)->where(fn ($q) => $q->whereNull('paid_until')->orWhere('paid_until', '<=', now()))->where('trial_ends_at', ($filters['status'] === 'trial' ? '>' : '<='), now());
                break;
        }
        $tenants = $query->orderByDesc('id')->paginate(20)->withQueryString();

        return view('platform.tenants', compact('tenants', 'filters'));
    }

    public function show(Tenant $tenant)
    {
        // Explicit global metadata queries: no business model scope is disabled.
        $counts = [];
        foreach (['users', 'members', 'works', 'documents'] as $table) {
            $counts[$table] = DB::table($table)->where('tenant_id', $tenant->id)->count();
        }
        $counts['bytes'] = DB::table('documents')->where('tenant_id', $tenant->id)->sum('size');
        $users = DB::table('users')->where('tenant_id', $tenant->id)->select('name', 'email', 'username', 'role')->orderBy('id')->paginate(15);
        $licenses = DB::table('tenant_licenses')->where('tenant_id', $tenant->id)->select('provider_id', 'expires_at', 'created_at', 'status', 'sync_status', 'last_checked_at')->orderByDesc('id')->limit(10)->get();
        $usage = $this->usage($tenant->id);

        return view('platform.tenant', compact('tenant', 'counts', 'users', 'licenses', 'usage'));
    }

    public function update(Request $request, Tenant $tenant)
    {
        $data = $request->validate(['action' => ['required', Rule::in(['suspend', 'resume', 'extend_trial'])], 'reason' => ['required', 'string', 'min:5', 'max:500'], 'days' => ['required_if:action,extend_trial', 'nullable', 'integer', 'min:1', 'max:30'], 'current_password' => ['required', 'string']]);
        if (! Hash::check($data['current_password'], Auth::guard('platform')->user()->password)) {
            throw ValidationException::withMessages(['current_password' => 'Le mot de passe est incorrect.']);
        }
        DB::transaction(function () use ($tenant, $data) {
            $locked = Tenant::lockForUpdate()->findOrFail($tenant->id);
            $before = $locked->only(['suspended_at', 'trial_ends_at']);
            switch ($data['action']) {
                case 'suspend': $locked->forceFill(['suspended_at' => $locked->suspended_at ?? now()]);
                    break;
                case 'resume': $locked->forceFill(['suspended_at' => null]);
                    break;
                case 'extend_trial':
                    if ($locked->suspended_at !== null) {
                        throw ValidationException::withMessages(['action' => 'Réactivez cet espace avant de prolonger son essai.']);
                    }
                    $locked->forceFill(['trial_ends_at' => ($locked->trial_ends_at->isFuture() ? $locked->trial_ends_at : now())->addDays((int) $data['days'])]);
                    break;
            }
            $locked->save();
            Audit::record($data['action'], $locked->id, $data['reason'], ['before' => $before, 'after' => $locked->only(['suspended_at', 'trial_ends_at'])]);
        });

        return redirect()->route('platform.tenants.show', $tenant)->with('status', 'La modification a été enregistrée dans le journal.');
    }

    public function health()
    {
        DB::select('select 1');
        $usage = $this->usage();
        $queued = DB::table('jobs')->count();
        $failed = DB::table('failed_jobs')->count();
        $oldest = DB::table('jobs')->min('created_at');
        $daily = DB::table('platform_usage_daily')->where('day', '>=', today()->subDays(6)->toDateString())->select('day')->selectRaw('SUM(requests) as requests, SUM(errors) as errors, SUM(slow_requests) as slow_requests')->groupBy('day')->orderBy('day')->get();

        return view('platform.health', compact('usage', 'queued', 'failed', 'oldest', 'daily'));
    }

    public function audit()
    {
        $events = DB::table('platform_audit_events as e')->leftJoin('platform_admins as a', 'a.id', '=', 'e.platform_admin_id')->leftJoin('tenants as t', 't.id', '=', 'e.tenant_id')->select('e.*', 'a.name as admin_name', 't.name as tenant_name')->orderByDesc('e.id')->paginate(30);

        return view('platform.audit', compact('events'));
    }

    private function usage(?int $tenant = null): object
    {
        return DB::table('platform_usage_daily')->when($tenant !== null, fn ($q) => $q->where('tenant_id', $tenant))->where('day', '>=', today()->subDays(6)->toDateString())
            ->selectRaw('COUNT(DISTINCT user_id) as active_users, COUNT(DISTINCT tenant_id) as active_tenants, COALESCE(SUM(requests), 0) as requests, COALESCE(SUM(errors), 0) as errors, COALESCE(SUM(slow_requests), 0) as slow_requests, COALESCE(SUM(duration_ms), 0) as duration_ms')->first();
    }

    private function customerSpaces(): Builder
    {
        // Ignore only the reserved migration space while it has no users.
        return DB::table('tenants')->where(function ($query) {
            $query->where('tenants.id', '<>', 1)->orWhereExists(fn ($users) => $users->selectRaw('1')->from('users')->whereColumn('users.tenant_id', 'tenants.id'));
        });
    }
}
