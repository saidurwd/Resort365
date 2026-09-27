<?php

namespace App\Support\Menu;

use Closure;

/**
 * A sidebar entry. A group (no route) holds child items; an item links to a named route.
 * Labels are translation keys, translated when the sidebar renders (after the user's locale is set).
 */
final readonly class MenuItem
{
    /**
     * @param  string|null  $route  route name; null for a group
     * @param  string|null  $parent  key of the group this item belongs to
     * @param  string|null  $permission  permission the user needs to see it
     * @param  string|null  $module  module alias; hidden when the module is disabled for the tenant
     * @param  string|null  $active  route-name pattern that marks it active (defaults to the route)
     * @param  (Closure(): bool)|null  $visible  extra visibility rule
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $icon = 'bi-circle',
        public ?string $route = null,
        public ?string $parent = null,
        public int $order = 500,
        public ?string $permission = null,
        public ?string $module = null,
        public ?string $active = null,
        public ?Closure $visible = null,
    ) {}

    public function isGroup(): bool
    {
        return $this->route === null;
    }
}
