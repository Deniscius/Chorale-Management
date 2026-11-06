<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\Contribution;
use App\Models\Transaction;
use App\Services\ChoraleIdentity;
use App\Services\Letterhead;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\HeaderUtils;

/** Official, tenant-scoped evidence report for activities and financial activity. */
final class ActivityFinancialReportController
{
    public function __invoke(Request $request, Letterhead $letterhead, ChoraleIdentity $identity)
    {
        abort_unless($request->user()?->canManageModule(UserRole::MODULE_ACTIVITES)
            || $request->user()?->canManageModule(UserRole::MODULE_COMPTABILITE), 403);
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'], 'orientation' => ['nullable', 'in:portrait,landscape']]);
        $from = isset($data['from']) ? CarbonImmutable::parse($data['from'])->startOfDay() : now()->startOfYear();
        $to = isset($data['to']) ? CarbonImmutable::parse($data['to'])->endOfDay() : now()->endOfDay();
        $orientation = $data['orientation'] ?? 'landscape';

        $activities = Activity::query()->with(['commission:id,name'])->withCount('participants')
            ->whereBetween('starts_at', [$from, $to])->orderBy('starts_at')->orderBy('id')->get();
        // Only validated/closed accounting entries are evidence. Drafts and rejected records are excluded.
        $transactions = Transaction::query()->validated()->with(['activity:id,title', 'member:id,first_name,last_name'])
            ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])->orderBy('transaction_date')->orderBy('id')->get();
        $receipts = (float) $transactions->where('type', TransactionType::Recette)->sum('amount');
        $expenses = (float) $transactions->where('type', TransactionType::Depense)->sum('amount');
        $due = (float) Contribution::query()->whereNotNull('activity_id')->whereBetween('due_on', [$from->toDateString(), $to->toDateString()])->sum('due_amount');
        $paid = (float) Contribution::query()->whereNotNull('activity_id')->whereBetween('paid_on', [$from->toDateString(), $to->toDateString()])->sum('paid_amount');

        return response()->view('documents.activity-financial-report', compact('identity', 'from', 'to', 'orientation', 'activities', 'transactions') + [
            'header' => $letterhead->headerDataUri(), 'headerHeightMm' => $letterhead->headerHeightMm(),
            'summary' => ['budget' => (float) $activities->sum('budget_allocated'), 'activity_spent' => (float) $activities->sum('budget_spent'), 'receipts' => $receipts, 'expenses' => $expenses, 'balance' => $receipts - $expenses, 'participation_due' => $due, 'participation_paid' => $paid],
        ], 200, ['Cache-Control' => 'private, no-store', 'Content-Disposition' => HeaderUtils::makeDisposition('inline', 'rapport-activites-finances-'.$from->format('Y-m-d').'-'.$to->format('Y-m-d').'.html'), 'X-Content-Type-Options' => 'nosniff']);
    }
}
