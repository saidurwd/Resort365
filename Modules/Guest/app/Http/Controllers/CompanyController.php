<?php

namespace Modules\Guest\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Contracts\AuditTrail;
use Modules\Core\Contracts\Settings;
use Modules\Guest\Actions\DeleteCompany;
use Modules\Guest\Actions\SaveCompany;
use Modules\Guest\Http\Requests\SaveCompanyRequest;
use Modules\Guest\Models\Company;

class CompanyController extends Controller
{
    public function __construct(private readonly Settings $settings) {}

    public function index(): View
    {
        Gate::authorize('viewAny', Company::class);

        return view('guest::companies.index', ['records' => Company::query()->orderBy('name')->get(), 'currency' => $this->currency()]);
    }

    public function create(): View
    {
        Gate::authorize('create', Company::class);

        return view('guest::companies.form', ['record' => null, 'currency' => $this->currency(), 'history' => []]);
    }

    public function store(SaveCompanyRequest $request, SaveCompany $save): RedirectResponse
    {
        $record = $save->handle(null, $request->validated());

        return to_route('guest.companies.index')->with('success', __('Company ":name" created.', ['name' => $record->name]));
    }

    public function edit(Company $company): View
    {
        Gate::authorize('update', $company);

        return view('guest::companies.form', ['record' => $company, 'currency' => $this->currency(), 'history' => app(AuditTrail::class)->for($company)]);
    }

    public function update(SaveCompanyRequest $request, Company $company, SaveCompany $save): RedirectResponse
    {
        $save->handle($company, $request->validated());

        return to_route('guest.companies.index')->with('success', __('Company ":name" saved.', ['name' => $company->name]));
    }

    public function destroy(Company $company, DeleteCompany $delete): RedirectResponse
    {
        Gate::authorize('delete', $company);
        $delete->handle($company);

        return to_route('guest.companies.index')->with('success', __('Company ":name" deleted.', ['name' => $company->name]));
    }

    private function currency(): string
    {
        return (string) $this->settings->get('core.currency');
    }
}
