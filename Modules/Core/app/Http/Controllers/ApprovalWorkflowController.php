<?php

namespace Modules\Core\Http\Controllers;

use App\Support\Authorization\PermissionRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Core\Actions\SaveApprovalWorkflow;
use Modules\Core\Contracts\ApprovalRegistry;
use Modules\Core\Exceptions\ApprovalRuleViolated;
use Modules\Core\Http\Requests\SaveApprovalWorkflowRequest;
use Modules\Core\Models\ApprovalWorkflow;

/**
 * Setup → Approval workflows: for each kind of document, who must approve which amounts. Authorized by
 * the core.approval.manage route middleware.
 */
class ApprovalWorkflowController extends Controller
{
    public function index(ApprovalRegistry $registry): View
    {
        $workflows = ApprovalWorkflow::query()->with('steps')->get()->keyBy('document_type');

        return view('core::approval-workflows.index', ['types' => $registry->types(), 'workflows' => $workflows]);
    }

    public function edit(ApprovalWorkflow $workflow, ApprovalRegistry $registry, PermissionRegistry $permissions): View
    {
        $workflow->load('steps');
        $labels = [];

        foreach ($permissions->all() as $definition) {
            $labels[$definition->name] = $definition->name.' · '.$definition->label;
        }

        return view('core::approval-workflows.edit', [
            'workflow' => $workflow, 'type' => $registry->types()[$workflow->document_type] ?? null, 'permissions' => $labels,
            'steps' => old('steps', $workflow->steps->map(fn ($step): array => ['label' => $step->label, 'permission' => $step->permission, 'min_amount' => (string) $step->min_amount])->all()),
        ]);
    }

    public function update(SaveApprovalWorkflowRequest $request, ApprovalWorkflow $workflow, SaveApprovalWorkflow $save): RedirectResponse
    {
        try {
            $save->handle($workflow, $request->boolean('is_active'), array_values(array_map(
                fn (array $step): array => ['label' => (string) $step['label'], 'permission' => (string) $step['permission'], 'min_amount' => (string) ($step['min_amount'] ?? '0')],
                (array) $request->validated('steps', []),
            )));
        } catch (ApprovalRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('core.approval-workflows.index')->with('success', __('Workflow ":name" saved.', ['name' => $workflow->name]));
    }
}
