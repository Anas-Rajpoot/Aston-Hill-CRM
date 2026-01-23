<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\Request;
use App\Http\Requests\ExpenseStoreRequest;
use App\Http\Requests\ExpenseUpdateRequest;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use App\Models\User;
use Illuminate\Support\Carbon;
use App\Support\Format;

class ExpenseController extends Controller
{
    public function __construct()
    {
        // Spatie Permission middleware
        // $this->middleware('crud:expense_tracker');
        $this->middleware('permission:expense_tracker.view')->only(['index','datatable','show']);
        
        $this->middleware('permission:expense_tracker.create')->only(['create','store']);
        $this->middleware('permission:expense_tracker.update')->only(['edit','update']);
        $this->middleware('permission:expense_tracker.delete')->only(['destroy']);
        $this->middleware('permission:expense_tracker.export')->only(['exportCsv','exportSingleCsv']);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $users = User::select('id','name','email')->orderBy('name')->get();
        $categories = Expense::select('product_category')
            ->distinct()
            ->orderBy('product_category')
            ->pluck('product_category');

        return view('expenses.index', compact('users','categories'));
    }

    public function datatable(Request $request)
    {
        $query = Expense::query()
            ->with(['user:id,name,email'])
            ->select('expenses.*');

        // Optional: non-superadmin only see own
        if (!$request->user()->hasRole('superadmin')) {
            $query->where('user_id', $request->user()->id);
        }

        // Restrict non-view_all users to own records
        // if (!auth()->user()->can('expenses.view_all')) {
        //     $query->where('user_id', auth()->id());
        // }

        $query->filter([
            'user_id' => $request->user_id,
            'category' => $request->category,
            'invoice' => $request->invoice,
            'from' => $request->from,
            'to' => $request->to,
        ]);

        return DataTables::eloquent($query)
            ->addColumn('user', function (Expense $e) {
                return $e->user ? ($e->user->name . ' (' . $e->user->email . ')') : '-';
            })
            ->editColumn('expense_date', function (Expense $e) {
                return optional($e->expense_date)->format('d-M-Y');
            })
            ->editColumn('vat_amount', function (Expense $e) {
                    if (!$e->vat_amount || $e->vat_amount == 0) {
                        return '-';
                    }

                    return number_format((float)$e->vat_amount, 2) . '%';
                })
            ->editColumn('amount_without_vat', fn(Expense $e) => number_format((float)$e->amount_without_vat, 2))
            ->editColumn('full_amount', fn(Expense $e) => number_format((float)$e->full_amount, 2))
            ->addColumn('actions', function (Expense $e) use ($request) {
                $show = route('expenses.show', $e);
                $edit = route('expenses.edit', $e);
                $del  = route('expenses.destroy', $e);
                $csv  = route('expenses.export.single', $e);

                $btn = '<div class="flex gap-2 flex-wrap">';

                $btn .= '<a class="px-3 py-1 rounded bg-gray-800 text-white text-xs" href="'.$show.'">View</a>';

                if ($request->user()->can('expenses.update')) {
                    $btn .= '<a class="px-3 py-1 rounded bg-indigo-600 text-white text-xs" href="'.$edit.'">Edit</a>';
                }

                if ($request->user()->can('expenses.export')) {
                    $btn .= '<a class="px-3 py-1 rounded bg-emerald-600 text-white text-xs" href="'.$csv.'">CSV</a>';
                }

                if ($request->user()->can('expenses.delete')) {
                    $btn .= '<form method="POST" action="'.$del.'" onsubmit="return confirm(\'Delete this expense?\')" style="display:inline">';
                    $btn .= csrf_field().method_field('DELETE');
                    $btn .= '<button class="px-3 py-1 rounded bg-red-600 text-white text-xs">Delete</button>';
                    $btn .= '</form>';
                }

                $btn .= '</div>';
                return $btn;
            })
            ->rawColumns(['actions'])
            ->toJson();
    }
    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('expenses.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ExpenseStoreRequest $request)
    {
        $data = $request->validated();

        // Auto-calc full_amount if missing
        if (empty($data['full_amount'])) {
            $net = (float)$data['amount_without_vat'];
            $rate = (float)($data['vat_amount'] ?? 0);
            $data['full_amount'] = round($net + ($net * $rate / 100), 2);
        }

        $data['user_id'] = $request->user()->id;
        
        $expense = Expense::create($data);
        
        return redirect()->route('expenses.index')->with('success', 'Expense added');
    }

    /**
     * Display the specified resource.
     */
    public function show(Expense $expense)
    {
        $this->authorizeOwnerOrSuperadmin($expense);
        $expense->load('user:id,name,email');
        return view('expenses.show', compact('expense'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Expense $expense)
    {
        $this->authorizeOwnerOrSuperadmin($expense);
        return view('expenses.edit', compact('expense'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ExpenseUpdateRequest $request, Expense $expense)
    {
        $this->authorizeOwnerOrSuperadmin($expense);

        $data = $request->validated();

        if (empty($data['full_amount'])) {
            $net = (float)$data['amount_without_vat'];
            $rate = (float)($data['vat_amount'] ?? 0);
            $data['full_amount'] = round($net + ($net * $rate / 100), 2);
        }

        $expense->update($data);

        return redirect()->route('expenses.show', $expense)->with('success', 'Expense updated');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Expense $expense)
    {
        $this->authorizeOwnerOrSuperadmin($expense);

        $expense->delete();
        return redirect()->route('expenses.index')->with('success', 'Expense deleted');
    }

    public function exportCsv(Request $request)
    {
        $this->validate($request, [
            'range' => ['nullable','in:custom,month,quarter,half_year,year'],
            'from'  => ['nullable','date'],
            'to'    => ['nullable','date'],
            'month' => ['nullable','date_format:Y-m'],
            'year'  => ['nullable','integer','min:2000','max:2100'],
            'quarter' => ['nullable','integer','min:1','max:4'],
            'user_id' => ['nullable','integer'],
            'category' => ['nullable','string'],
            'invoice' => ['nullable','string'],
        ]);

        [$from, $to] = $this->resolveDateRange($request);

        $query = Expense::query()->with('user:id,name,email');

        if (!$request->user()->hasRole('superadmin')) {
            $query->where('user_id', $request->user()->id);
        }

        $query->filter([
            'user_id' => $request->user_id,
            'category' => $request->category,
            'invoice' => $request->invoice,
            'from' => $from,
            'to' => $to,
        ])->orderBy('expense_date', 'asc');

        $fileName = 'expenses_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename={$fileName}",
        ];

        $columns = [
            'Date',
            'User',
            'Email',
            'Product Category',
            'Product Description',
            'Invoice Number',
            'VAT (%)',
            'Amount without VAT',
            'Full Amount',
            'Comment',
        ];

        $callback = function () use ($query, $columns) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);

            $query->chunk(1000, function ($rows) use ($out) {
                foreach ($rows as $e) {
                    fputcsv($out, [
                        optional($e->expense_date)->format('d-M-Y'),
                        optional($e->user)->name,
                        optional($e->user)->email,
                        $e->product_category,
                        $e->product_description,
                        $e->invoice_number,
                        $e->vat_amount !== null ? number_format((float)$e->vat_amount, 2) : '',
                        number_format((float)$e->amount_without_vat, 2),
                        number_format((float)$e->full_amount, 2),
                        $e->comment,
                    ]);
                }
            });

            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportSingleCsv(Request $request, Expense $expense)
    {
        $this->authorizeOwnerOrSuperadmin($expense);

        $expense->load('user:id,name,email');

        $fileName = 'expense_' . $expense->id . '_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename={$fileName}",
        ];

        $columns = [
            'Date','User','Email','Product Category','Product Description','Invoice Number',
            'VAT (%)','Amount without VAT','Full Amount','Comment'
        ];

        $callback = function () use ($expense, $columns) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);

            fputcsv($out, [
                optional($expense->expense_date)->format('d-M-Y'),
                optional($expense->user)->name,
                optional($expense->user)->email,
                $expense->product_category,
                $expense->product_description,
                $expense->invoice_number,
                $expense->vat_amount !== null ? number_format((float)$expense->vat_amount, 2) : '',
                number_format((float)$expense->amount_without_vat, 2),
                number_format((float)$expense->full_amount, 2),
                $expense->comment,
            ]);

            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function authorizeOwnerOrSuperadmin(Expense $expense): void
    {
        $user = request()->user();
        if ($user->hasRole('superadmin')) return;

        if ($expense->user_id !== $user->id) {
            abort(403);
        }
    }

    private function resolveDateRange(Request $request): array
    {
        $range = $request->input('range', 'custom');

        if ($range === 'custom') {
            $from = $request->input('from');
            $to   = $request->input('to');
            return [$from, $to];
        }

        if ($range === 'month' && $request->filled('month')) {
            $start = $request->month . '-01';
            $end = date('Y-m-t', strtotime($start));
            return [$start, $end];
        }

        if ($range === 'quarter' && $request->filled('year') && $request->filled('quarter')) {
            $year = (int)$request->year;
            $q    = (int)$request->quarter;
            $startMonth = ($q - 1) * 3 + 1;
            $start = sprintf('%04d-%02d-01', $year, $startMonth);
            $endMonth = $startMonth + 2;
            $end = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $year, $endMonth)));
            return [$start, $end];
        }

        if ($range === 'half_year') {
            $end = now()->toDateString();
            $start = now()->subMonths(6)->toDateString();
            return [$start, $end];
        }

        if ($range === 'year' && $request->filled('year')) {
            $year = (int)$request->year;
            return ["{$year}-01-01", "{$year}-12-31"];
        }

        // fallback
        return [null, null];
    }
}
