<?php

namespace Modules\Core\Http\Controllers;

use App\Models\DatabaseNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * The signed-in user's in-app notifications.
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('core::notifications.index', ['notifications' => $this->notifications($request)->latest()->paginate(20)]);
    }

    public function readAll(Request $request): RedirectResponse
    {
        $this->notifications($request)->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('success', __('All notifications marked as read.'));
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        /** @var DatabaseNotification $found */
        $found = $this->notifications($request)->findOrFail($notification);
        $found->markAsRead();

        $url = $found->data['url'] ?? null;

        return is_string($url) && $url !== '' ? redirect()->to($url) : back();
    }

    /**
     * @return MorphMany<\Illuminate\Notifications\DatabaseNotification, covariant Model>
     */
    private function notifications(Request $request): MorphMany
    {
        $user = $request->user();
        abort_if($user === null, 403);

        return $user->notifications();
    }
}
