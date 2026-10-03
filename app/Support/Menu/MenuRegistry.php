<?php

namespace App\Support\Menu;

use App\Support\Tenancy\ModuleAccess;
use Closure;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;

/**
 * The sidebar (ARCHITECTURE §10.2). Modules register groups and items in their service
 * providers; the sidebar shows only what the current user may see in enabled modules.
 *
 *     $menu->group('setup', 'Setup', 'bi-gear', order: 900);
 *     $menu->add(new MenuItem('iam.users', 'Users', 'bi-people', route: 'iam.users.index',
 *         parent: 'setup', order: 10, permission: 'iam.user.view', module: 'iam'));
 *
 * @phpstan-type RenderedItem array{label: string, icon: string, url: string, active: bool, children?: list<array{label: string, url: string, active: bool}>}
 */
class MenuRegistry
{
    /**
     * @var array<string, MenuItem>
     */
    private array $items = [];

    public function add(MenuItem $item): void
    {
        if (isset($this->items[$item->key])) {
            throw new InvalidArgumentException("Menu item [{$item->key}] is registered twice.");
        }

        $this->items[$item->key] = $item;
    }

    /**
     * Register a group once; later calls with the same key are ignored (several modules share "setup").
     */
    public function group(string $key, string $label, string $icon, int $order = 500): void
    {
        $this->items[$key] ??= new MenuItem($key, $label, $icon, order: $order);
    }

    /**
     * @return array<string, MenuItem>
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * The sidebar for the given user (null = guest), ordered, with empty groups removed.
     *
     * @return list<RenderedItem>
     */
    public function forUser(?Authenticatable $user): array
    {
        $rendered = [];

        foreach ($this->sorted(null) as $item) {
            if (! $this->isVisible($item, $user)) {
                continue;
            }

            if (! $item->isGroup()) {
                $rendered[] = $this->render($item);

                continue;
            }

            $children = [];

            foreach ($this->sorted($item->key) as $child) {
                if (! $child->isGroup() && $this->isVisible($child, $user)) {
                    $children[] = $this->render($child);
                }
            }

            if ($children !== []) {
                $rendered[] = [
                    'label' => __($item->label),
                    'icon' => $item->icon,
                    'url' => '#',
                    'active' => in_array(true, array_column($children, 'active'), true),
                    'children' => array_map(fn (array $child): array => ['label' => $child['label'], 'url' => $child['url'], 'active' => $child['active']], $children),
                ];
            }
        }

        return $rendered;
    }

    /**
     * @return list<MenuItem>
     */
    private function sorted(?string $parent): array
    {
        $items = array_values(array_filter($this->items, fn (MenuItem $item): bool => $item->parent === $parent));
        usort($items, fn (MenuItem $a, MenuItem $b): int => [$a->order, $a->key] <=> [$b->order, $b->key]);

        return $items;
    }

    private function isVisible(MenuItem $item, ?Authenticatable $user): bool
    {
        if ($item->visible instanceof Closure && ! ($item->visible)()) {
            return false;
        }

        // Resolved per call: ModuleAccess is request-scoped, this registry is a singleton.
        if ($item->module !== null && ! app(ModuleAccess::class)->enabled($item->module)) {
            return false;
        }

        if ($item->route !== null && ! Route::has($item->route)) {
            return false;
        }

        return $item->permission === null
            || ($user instanceof Authorizable && $user->can($item->permission));
    }

    /**
     * @return array{label: string, icon: string, url: string, active: bool}
     */
    private function render(MenuItem $item): array
    {
        return [
            'label' => __($item->label),
            'icon' => $item->icon,
            'url' => route((string) $item->route),
            'active' => request()->routeIs(...(array) ($item->active ?? (string) $item->route)),
        ];
    }
}
