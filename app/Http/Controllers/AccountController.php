<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Account::class, 'account');
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // If CSR: show only assigned accounts (policy already blocks viewAny, but we filter too for UX)
        $query = Account::query();

        if (auth()->user()->hasRole('csr')) {
            $query->where('assigned_csr_id', auth()->id());
        }

        $accounts = $query->latest()->paginate(20);

        return view('accounts.index', compact('accounts'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('accounts.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'company_name' => ['required','string','max:255'],
            'account_number' => ['required','string','max:50'],
            'assigned_csr_id' => ['nullable','integer','exists:users,id'],
        ]);

        $account = Account::create($data);

        return redirect()->route('accounts.show', $account)->with('success', 'Account created');
    }

    /**
     * Display the specified resource.
     */
    public function show(Account $account)
    {
        return view('accounts.show', compact('account'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Account $account)
    {
        return view('accounts.edit', compact('account'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Account $account)
    {
        $data = $request->validate([
            'company_name' => ['required','string','max:255'],
            'account_number' => ['required','string','max:50'],
            'assigned_csr_id' => ['nullable','integer','exists:users,id'],
        ]);

        $account->update($data);

        return redirect()->route('accounts.show', $account)->with('success', 'Account updated');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Account $account)
    {
        $account->delete();

        return redirect()->route('accounts.index')->with('success', 'Account deleted');
    }
}
