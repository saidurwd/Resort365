{{-- $menu: rendered by App\Support\Menu\MenuRegistry (filtered by permission and enabled modules). --}}
<aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
    <div class="sidebar-brand">
        <a href="{{ url('/') }}" class="brand-link">
            <i class="bi bi-tree-fill brand-image text-success fs-4 lh-1"></i>
            <span class="brand-text fw-semibold">{{ config('app.name') }}</span>
        </a>
    </div>

    <div class="sidebar-wrapper">
        <nav class="mt-2">
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="navigation" aria-label="{{ __('Main navigation') }}" data-accordion="false">
                @foreach ($menu as $item)
                    @php($hasChildren = ! empty($item['children']))
                    <li @class(['nav-item', 'menu-open' => $hasChildren && $item['active']])>
                        <a href="{{ $hasChildren ? '#' : $item['url'] }}" @class(['nav-link', 'active' => $item['active']]) @if (! $hasChildren && $item['active']) aria-current="page" @endif>
                            <i class="nav-icon bi {{ $item['icon'] }}"></i>
                            <p>
                                {{ $item['label'] }}
                                @if ($hasChildren)
                                    <i class="nav-arrow bi bi-chevron-right"></i>
                                @endif
                            </p>
                        </a>

                        @if ($hasChildren)
                            <ul class="nav nav-treeview">
                                @foreach ($item['children'] as $child)
                                    <li class="nav-item">
                                        <a href="{{ $child['url'] }}" @class(['nav-link', 'active' => $child['active']]) @if ($child['active']) aria-current="page" @endif>
                                            <i class="nav-icon bi bi-circle"></i>
                                            <p>{{ $child['label'] }}</p>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
        </nav>
    </div>
</aside>
