<?php

/*
| The approval workflow engine (Step 5.1): workflows per document type with amount-based steps, requests that wait for
| each level's approvers, delegation, notifications, and a module's handler that runs on final approval.
*/
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Core\Actions\SaveApprovalDelegation;
use Modules\Core\Actions\SaveApprovalWorkflow;
use Modules\Core\Actions\SeedApprovalWorkflows;
use Modules\Core\Contracts\ApprovalEngine;
use Modules\Core\Contracts\ApprovalHandler;
use Modules\Core\Contracts\ApprovalRegistry;
use Modules\Core\DTOs\ApprovalDecision;
use Modules\Core\DTOs\ApprovalDocumentType;
use Modules\Core\DTOs\ApprovalStepDefinition;
use Modules\Core\DTOs\ApprovalSummary;
use Modules\Core\Enums\ApprovalStatus;
use Modules\Core\Exceptions\ApprovalExecutionFailed;
use Modules\Core\Exceptions\ApprovalRuleViolated;
use Modules\Core\Models\ApprovalAction;
use Modules\Core\Models\ApprovalRequest;
use Modules\Core\Models\ApprovalWorkflow;
use Modules\Core\Notifications\ApprovalNotification;
use Modules\IAM\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

uses(RefreshDatabase::class);

final class RecordingApprovalHandler implements ApprovalHandler
{
    /** @var list<string> */
    public static array $calls = [];

    public static bool $fail = false;

    public function approved(ApprovalDecision $decision): void
    {
        if (self::$fail) {
            throw new ApprovalExecutionFailed('It can no longer go ahead.');
        }

        self::$calls[] = 'approved:'.$decision->reference.':'.($decision->payload['note'] ?? '');
    }

    public function rejected(ApprovalDecision $decision): void
    {
        self::$calls[] = 'rejected:'.$decision->reference.':'.$decision->comment;
    }
}

beforeEach(function (): void {
    RecordingApprovalHandler::$calls = [];
    RecordingApprovalHandler::$fail = false;
    Notification::fake();
    app(ApprovalRegistry::class)->register(new ApprovalDocumentType('test_order', 'Test order', 'A document for tests.', [
        new ApprovalStepDefinition('Desk', 'frontoffice.desk.view', '0.00'),
        new ApprovalStepDefinition('General Manager', 'billing.refund.approve', '50000.00'),
    ]), RecordingApprovalHandler::class);
    withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise']));
    approvalTenant(fn () => app(SeedApprovalWorkflows::class)->handle());
});

function approvalTenant(Closure $callback): mixed
{
    return app(TenantContext::class)->run(tenant('sunrise'), $callback);
}

function apUser(DefaultRole $role): User
{
    return tenantUserAs(tenant('sunrise'), $role);
}

/**
 * @param  array<string, mixed>|null  $payload
 */
function apSubmit(string $amount, User $by, ?array $payload = ['note' => 'n1']): ?ApprovalSummary
{
    return approvalTenant(fn (): ?ApprovalSummary => app(ApprovalEngine::class)->submit('test_order', $amount, 'Order 1', $by->id, $payload, null, 'thing', 7));
}

function apRequest(int $id): ApprovalRequest
{
    return approvalTenant(fn (): ApprovalRequest => ApprovalRequest::query()->findOrFail($id));
}

it('needs no approval when the workflow is off or the amount is below every step', function (): void {
    $by = apUser(DefaultRole::Accountant);
    approvalTenant(fn () => ApprovalWorkflow::query()->where('document_type', 'test_order')->firstOrFail()->steps()->update(['min_amount' => '1000.00']));

    expect(apSubmit('999.99', $by))->toBeNull();

    approvalTenant(fn () => ApprovalWorkflow::query()->where('document_type', 'test_order')->update(['is_active' => false]));
    expect(apSubmit('90000.00', $by))->toBeNull()->and(approvalTenant(fn (): int => ApprovalRequest::query()->count()))->toBe(0);
    expect(approvalTenant(fn () => app(ApprovalEngine::class)->submit('unknown_type', '5.00', 'x', $by->id)))->toBeNull();
});

it('picks the steps by amount and tells the approvers of the first one, not the requester', function (): void {
    $desk = apUser(DefaultRole::FrontDeskAgent);
    $by = apUser(DefaultRole::Accountant);
    $small = apSubmit('1000.00', $by);
    $big = apSubmit('60000.00', $by);

    expect(array_column(apRequest($small->id)->steps, 'label'))->toBe(['Desk'])->and(array_column(apRequest($big->id)->steps, 'label'))->toBe(['Desk', 'General Manager'])
        ->and($small->status)->toBe(ApprovalStatus::Pending)->and($small->currentStep)->toBe('Desk');
    Notification::assertSentTo($desk, ApprovalNotification::class, fn (ApprovalNotification $notification): bool => $notification->kind === 'requested' && $notification->reference === 'Order 1');
    Notification::assertNotSentTo($by, ApprovalNotification::class);
});

it('walks a two-level chain and runs the handler only on the last approval', function (): void {
    $desk = apUser(DefaultRole::FrontDeskAgent);
    $gm = apUser(DefaultRole::GeneralManager);
    $by = apUser(DefaultRole::Accountant);
    $request = apSubmit('60000.00', $by);
    $engine = fn () => app(ApprovalEngine::class);

    expect(fn () => approvalTenant(fn () => $engine()->approve($request->id, apUser(DefaultRole::Waiter)->id)))->toThrow(ApprovalRuleViolated::class, 'Desk');
    $second = approvalTenant(fn () => $engine()->approve($request->id, $desk->id, 'Looks right'));
    expect($second->currentStep)->toBe('General Manager')->and($second->status)->toBe(ApprovalStatus::Pending)->and(RecordingApprovalHandler::$calls)->toBe([]);
    Notification::assertSentTo($gm, ApprovalNotification::class);

    expect(fn () => approvalTenant(fn () => $engine()->approve($request->id, $desk->id)))->toThrow(ApprovalRuleViolated::class);
    $done = approvalTenant(fn () => $engine()->approve($request->id, $gm->id));

    expect($done->status)->toBe(ApprovalStatus::Approved)->and(RecordingApprovalHandler::$calls)->toBe(['approved:Order 1:n1'])
        ->and(approvalTenant(fn (): int => ApprovalAction::query()->count()))->toBe(3)
        ->and(fn () => approvalTenant(fn () => $engine()->approve($request->id, $gm->id)))->toThrow(ApprovalRuleViolated::class, 'already decided');
    Notification::assertSentTo($by, ApprovalNotification::class, fn (ApprovalNotification $notification): bool => $notification->kind === 'approved');
});

it('never lets the requester approve their own request', function (): void {
    $gm = apUser(DefaultRole::GeneralManager);
    $request = apSubmit('1000.00', $gm);

    expect(approvalTenant(fn () => app(ApprovalEngine::class)->canDecide($request->id, $gm->id)))->toBeFalse()
        ->and(fn () => approvalTenant(fn () => app(ApprovalEngine::class)->approve($request->id, $gm->id)))->toThrow(ApprovalRuleViolated::class, 'yourself');
});

it('rejects with a reason, tells the requester and runs the handler\'s rejection', function (): void {
    $desk = apUser(DefaultRole::FrontDeskAgent);
    $by = apUser(DefaultRole::Accountant);
    $request = apSubmit('1000.00', $by);

    expect(fn () => approvalTenant(fn () => app(ApprovalEngine::class)->reject($request->id, $desk->id, ' ')))->toThrow(ApprovalRuleViolated::class, 'why');
    $rejected = approvalTenant(fn () => app(ApprovalEngine::class)->reject($request->id, $desk->id, 'Too much'));

    expect($rejected->status)->toBe(ApprovalStatus::Rejected)->and(RecordingApprovalHandler::$calls)->toBe(['rejected:Order 1:Too much']);
    Notification::assertSentTo($by, ApprovalNotification::class, fn (ApprovalNotification $notification): bool => $notification->kind === 'rejected' && $notification->comment === 'Too much');
});

it('lets only the requester (or a manager) withdraw a pending request', function (): void {
    $by = apUser(DefaultRole::Accountant);
    $other = apUser(DefaultRole::FrontDeskAgent);
    $request = apSubmit('1000.00', $by);

    expect(fn () => approvalTenant(fn () => app(ApprovalEngine::class)->cancel($request->id, $other->id)))->toThrow(ApprovalRuleViolated::class);
    expect(approvalTenant(fn () => app(ApprovalEngine::class)->cancel($request->id, $by->id))->status)->toBe(ApprovalStatus::Cancelled)
        ->and(RecordingApprovalHandler::$calls)->toBe([]);
    expect(fn () => approvalTenant(fn () => app(ApprovalEngine::class)->cancel($request->id, $by->id)))->toThrow(ApprovalRuleViolated::class);
});

it('records nothing when the handler can no longer carry the document out', function (): void {
    $desk = apUser(DefaultRole::FrontDeskAgent);
    $request = apSubmit('1000.00', apUser(DefaultRole::Accountant));
    RecordingApprovalHandler::$fail = true;

    expect(fn () => approvalTenant(fn () => app(ApprovalEngine::class)->approve($request->id, $desk->id)))->toThrow(ApprovalExecutionFailed::class);
    expect(apRequest($request->id)->status)->toBe(ApprovalStatus::Pending)->and(approvalTenant(fn (): int => ApprovalAction::query()->where('action', 'approved')->count()))->toBe(0);
});

it('lets a delegate decide while the delegation runs, never on the delegator\'s own request', function (): void {
    $desk = apUser(DefaultRole::FrontDeskAgent);
    $stand = apUser(DefaultRole::Waiter);
    $by = apUser(DefaultRole::Accountant);
    $request = apSubmit('1000.00', $by);
    $engine = fn () => app(ApprovalEngine::class);

    expect(approvalTenant(fn () => $engine()->canDecide($request->id, $stand->id)))->toBeFalse();
    approvalTenant(fn () => app(SaveApprovalDelegation::class)->handle($desk->id, $stand->id, now()->subDay()->toDateString(), now()->addDay()->toDateString()));
    expect(approvalTenant(fn () => $engine()->canDecide($request->id, $stand->id)))->toBeTrue();

    approvalTenant(fn () => $engine()->approve($request->id, $stand->id, 'In place of the desk'));
    $action = approvalTenant(fn (): ApprovalAction => ApprovalAction::query()->where('action', 'approved')->sole());
    expect([$action->user_id, $action->on_behalf_of])->toBe([$stand->id, $desk->id]);

    $own = apSubmit('1000.00', $desk);
    expect(approvalTenant(fn () => $engine()->canDecide($own->id, $stand->id)))->toBeFalse();

    approvalTenant(fn () => app(SaveApprovalDelegation::class)->handle($desk->id, $stand->id, now()->subWeek()->toDateString(), now()->subDays(2)->toDateString()));
    expect(fn () => approvalTenant(fn () => app(SaveApprovalDelegation::class)->handle($desk->id, $desk->id, now()->toDateString(), now()->toDateString())))->toThrow(ApprovalRuleViolated::class)
        ->and(fn () => approvalTenant(fn () => app(SaveApprovalDelegation::class)->handle($desk->id, $stand->id, '2026-10-10', '2026-10-01')))->toThrow(ApprovalRuleViolated::class);
});

it('seeds the missing workflows once and leaves edited ones alone', function (): void {
    approvalTenant(fn () => ApprovalWorkflow::query()->where('document_type', 'test_order')->update(['name' => 'Edited']));

    expect(approvalTenant(fn (): int => app(SeedApprovalWorkflows::class)->handle()))->toBe(0)
        ->and(approvalTenant(fn (): string => ApprovalWorkflow::query()->where('document_type', 'test_order')->value('name')))->toBe('Edited');
});

it('saves a workflow\'s steps with checks, and keeps the steps frozen on requests already waiting', function (): void {
    $by = apUser(DefaultRole::Accountant);
    $request = apSubmit('1000.00', $by);
    $workflow = approvalTenant(fn (): ApprovalWorkflow => ApprovalWorkflow::query()->where('document_type', 'test_order')->firstOrFail());

    expect(fn () => approvalTenant(fn () => app(SaveApprovalWorkflow::class)->handle($workflow, true, [['label' => 'X', 'permission' => 'no.such.permission', 'min_amount' => '0']])))->toThrow(ApprovalRuleViolated::class)
        ->and(fn () => approvalTenant(fn () => app(SaveApprovalWorkflow::class)->handle($workflow, true, [])))->toThrow(ApprovalRuleViolated::class)
        ->and(fn () => approvalTenant(fn () => app(SaveApprovalWorkflow::class)->handle($workflow, true, [['label' => 'X', 'permission' => 'billing.refund.approve', 'min_amount' => '-1']])))->toThrow(ApprovalRuleViolated::class);

    approvalTenant(fn () => app(SaveApprovalWorkflow::class)->handle($workflow, true, [['label' => 'Only GM', 'permission' => 'billing.refund.approve', 'min_amount' => '0']]));

    expect(array_column(apRequest($request->id)->steps, 'label'))->toBe(['Desk'])
        ->and(approvalTenant(fn (): int => $workflow->fresh()->steps()->count()))->toBe(1);
});

it('shows the approvals screens to those concerned and keeps others out', function (): void {
    $desk = apUser(DefaultRole::FrontDeskAgent);
    $gm = apUser(DefaultRole::GeneralManager);
    $by = apUser(DefaultRole::Accountant);
    $stranger = apUser(DefaultRole::Waiter);
    $request = apSubmit('1000.00', $by);

    actingAs($desk);
    get(tenantUrl('sunrise', '/core/approvals'))->assertOk()->assertSeeHtml('data-approval="'.$request->id.'"');
    get(tenantUrl('sunrise', '/core/approvals/'.$request->id))->assertOk()->assertSee('Order 1')->assertSeeHtml('data-approval-summary')->assertSee('Desk');

    actingAs($by);
    get(tenantUrl('sunrise', '/core/approvals/'.$request->id))->assertOk()->assertSeeHtml('data-cancel');
    post(tenantUrl('sunrise', '/core/approvals/'.$request->id.'/approve'))->assertForbidden();

    actingAs($stranger);
    get(tenantUrl('sunrise', '/core/approvals/'.$request->id))->assertForbidden();

    actingAs($gm);
    get(tenantUrl('sunrise', '/core/approvals/'.$request->id))->assertOk();
    get(tenantUrl('sunrise', '/core/approvals'))->assertOk()->assertSee('All requests');
});

it('approves, rejects and delegates through the screens', function (): void {
    $desk = apUser(DefaultRole::FrontDeskAgent);
    $by = apUser(DefaultRole::Accountant);
    $stand = apUser(DefaultRole::Waiter);
    $first = apSubmit('1000.00', $by);
    $second = apSubmit('2000.00', $by);

    actingAs($desk);
    post(tenantUrl('sunrise', '/core/approvals/'.$first->id.'/approve'), ['comment' => 'Fine'])->assertSessionHas('success');
    expect(apRequest($first->id)->status)->toBe(ApprovalStatus::Approved)->and(RecordingApprovalHandler::$calls)->toBe(['approved:Order 1:n1']);
    post(tenantUrl('sunrise', '/core/approvals/'.$second->id.'/reject'), [])->assertSessionHas('error');
    post(tenantUrl('sunrise', '/core/approvals/'.$second->id.'/reject'), ['comment' => 'No'])->assertSessionHas('success');
    expect(apRequest($second->id)->status)->toBe(ApprovalStatus::Rejected);

    post(tenantUrl('sunrise', '/core/approval-delegations'), ['delegate_user_id' => $stand->id, 'starts_on' => now()->toDateString(), 'ends_on' => now()->addDays(3)->toDateString()])->assertSessionHas('success');
    post(tenantUrl('sunrise', '/core/approval-delegations'), ['delegate_user_id' => $desk->id, 'starts_on' => now()->toDateString(), 'ends_on' => now()->addDays(3)->toDateString()])->assertSessionHas('error');
    post(tenantUrl('sunrise', '/core/approval-delegations'), ['delegate_user_id' => $stand->id, 'starts_on' => now()->toDateString(), 'ends_on' => now()->subDay()->toDateString()])->assertSessionHasErrors('ends_on');
    get(tenantUrl('sunrise', '/core/approvals'))->assertOk()->assertSeeHtml('data-delegation');
});

it('lets a general manager change a workflow on screen, and nobody else', function (): void {
    $gm = apUser(DefaultRole::GeneralManager);
    $workflow = approvalTenant(fn (): ApprovalWorkflow => ApprovalWorkflow::query()->where('document_type', 'test_order')->firstOrFail());

    actingAs($gm);
    get(tenantUrl('sunrise', '/core/approval-workflows'))->assertOk()->assertSeeHtml('data-workflow="test_order"');
    get(tenantUrl('sunrise', '/core/approval-workflows/'.$workflow->id.'/edit'))->assertOk()->assertSeeHtml('data-workflow-form')->assertSeeHtml('data-add-step');
    put(tenantUrl('sunrise', '/core/approval-workflows/'.$workflow->id), ['is_active' => '1', 'steps' => [['label' => 'Boss', 'permission' => 'billing.refund.approve', 'min_amount' => '2500']]])->assertSessionHas('success');
    expect(approvalTenant(fn (): array => $workflow->fresh()->steps->map(fn ($step): array => [$step->label, $step->min_amount])->all()))->toBe([['Boss', '2500.00']]);
    put(tenantUrl('sunrise', '/core/approval-workflows/'.$workflow->id), ['is_active' => '1', 'steps' => [['label' => 'Bad', 'permission' => 'nope', 'min_amount' => '0']]])->assertSessionHas('error');

    actingAs(apUser(DefaultRole::FrontDeskAgent));
    get(tenantUrl('sunrise', '/core/approval-workflows'))->assertForbidden();
    put(tenantUrl('sunrise', '/core/approval-workflows/'.$workflow->id), ['steps' => []])->assertForbidden();
});
