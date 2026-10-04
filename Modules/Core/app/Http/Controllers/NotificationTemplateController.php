<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Core\Actions\ResetNotificationTemplate;
use Modules\Core\Actions\SaveNotificationTemplate;
use Modules\Core\Contracts\NotificationTemplates;
use Modules\Core\DTOs\NotificationTemplateDefinition;
use Modules\Core\Http\Requests\SaveNotificationTemplateRequest;
use Modules\Core\Models\NotificationTemplate;

/**
 * Setup → Email templates: the wording modules register for their emails and in-app notices,
 * which a tenant may rewrite (and reset to the default).
 */
class NotificationTemplateController extends Controller
{
    public function __construct(private readonly NotificationTemplates $templates) {}

    public function index(): View
    {
        $custom = NotificationTemplate::query()->get(['key', 'channel'])->map(fn (NotificationTemplate $template): string => $template->key.':'.$template->channel)->all();

        return view('core::notification-templates.index', [
            'definitions' => collect($this->templates->definitions())->sortBy(fn (NotificationTemplateDefinition $definition): string => $definition->key)->values(),
            'custom' => array_flip($custom),
        ]);
    }

    public function edit(Request $request, string $channel, string $key): View
    {
        $definition = $this->definitionOr404($key, $channel);
        $locale = (string) $request->query('locale', config('app.locale'));
        $template = NotificationTemplate::query()->where('key', $key)->where('channel', $channel)->where('locale', $locale)->first();

        return view('core::notification-templates.edit', [
            'definition' => $definition,
            'template' => $template,
            'locale' => $locale,
            'locales' => (array) config('app.available_locales'),
            'subject' => $template->subject ?? ($definition->subject !== null ? __($definition->subject) : null),
            'body' => $template->body ?? __($definition->body),
        ]);
    }

    public function update(SaveNotificationTemplateRequest $request, string $channel, string $key, SaveNotificationTemplate $save): RedirectResponse
    {
        $this->definitionOr404($key, $channel);
        $subject = $request->validated('subject');

        $save->handle($key, $channel, (string) $request->validated('locale'), is_string($subject) ? $subject : null,
            (string) $request->validated('body'), $request->boolean('is_active', true));

        return to_route('core.notification-templates.index')->with('success', __('Template saved.'));
    }

    public function destroy(string $channel, string $key, ResetNotificationTemplate $reset): RedirectResponse
    {
        $this->definitionOr404($key, $channel);
        $reset->handle($key, $channel);

        return to_route('core.notification-templates.index')->with('success', __('Template reset to the default wording.'));
    }

    private function definitionOr404(string $key, string $channel): NotificationTemplateDefinition
    {
        return $this->templates->definitions()[$key.':'.$channel] ?? abort(404);
    }
}
