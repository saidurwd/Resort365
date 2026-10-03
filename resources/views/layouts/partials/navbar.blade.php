<nav class="app-header navbar navbar-expand bg-body">
    <div class="container-fluid">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button" aria-label="{{ __('Toggle sidebar') }}">
                    <i class="bi bi-list"></i>
                </a>
            </li>
            {{-- $propertySwitcher is shared by the Property module for signed-in tenant users. --}}
            @isset($propertySwitcher)
                @if ($propertySwitcher['options'] === [])
                    <li class="nav-item"><span class="nav-link text-warning small"><i class="bi bi-exclamation-triangle me-1"></i>{{ __('No property access') }}</span></li>
                @else
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle fw-semibold" data-bs-toggle="dropdown" aria-expanded="false" data-property-switcher>
                            <i class="bi bi-building me-1"></i><span data-current-property>{{ $propertySwitcher['current']?->name }}</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li><h6 class="dropdown-header">{{ __('Switch property') }}</h6></li>
                            @foreach ($propertySwitcher['options'] as $propertyId => $propertyName)
                                <li>
                                    <form method="POST" action="{{ ($propertySwitcher['switchUrl'])($propertyId) }}">
                                        @csrf
                                        <button type="submit" @class(['dropdown-item d-flex align-items-center', 'active' => $propertySwitcher['current']?->id === $propertyId]) data-property-option="{{ $propertyId }}">
                                            {{ $propertyName }}
                                            @if ($propertySwitcher['current']?->id === $propertyId)<i class="bi bi-check-lg ms-auto"></i>@endif
                                        </button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    </li>
                    @if ($propertySwitcher['businessDate'])
                        <li class="nav-item d-none d-md-flex align-items-center">
                            <span class="badge text-bg-light border" title="{{ __('Business date') }}" data-business-date>
                                <i class="bi bi-calendar-event me-1"></i>{{ $propertySwitcher['businessDate'] }}
                            </span>
                        </li>
                    @endif
                @endif
            @endisset
        </ul>

        <ul class="navbar-nav ms-auto">
            {{-- $quickSearch is shared by the Guest module (guests; reservations join later), $notificationBell by Core. --}}
            @isset($quickSearch)
                <li class="nav-item d-none d-lg-flex align-items-center me-2">
                    <form method="GET" action="{{ $quickSearch['url'] }}" role="search" data-quick-search>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="search" name="search" class="form-control" placeholder="{{ $quickSearch['placeholder'] }}" aria-label="{{ $quickSearch['placeholder'] }}" value="{{ request()->routeIs('guest.guests.index') ? request('search') : '' }}">
                        </div>
                    </form>
                </li>
            @endisset
            @isset($notificationBell)
                <li class="nav-item dropdown">
                    <a class="nav-link position-relative" href="#" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('Notifications') }}" data-notification-bell>
                        <i class="bi bi-bell"></i>
                        @if ($notificationBell['unread'] > 0)
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger" data-unread-count>{{ $notificationBell['unread'] }}</span>
                        @endif
                    </a>
                    <div class="dropdown-menu dropdown-menu-end dropdown-menu-lg p-0">
                        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                            <strong class="small">{{ __('Notifications') }}</strong>
                            @if ($notificationBell['unread'] > 0)
                                <form method="POST" action="{{ $notificationBell['readAllUrl'] }}">
                                    @csrf
                                    <button type="submit" class="btn btn-link btn-sm p-0">{{ __('Mark all as read') }}</button>
                                </form>
                            @endif
                        </div>
                        @forelse ($notificationBell['latest'] as $notification)
                            <div @class(['px-3 py-2 border-bottom small', 'fw-semibold' => $notification->read_at === null])>
                                <i class="bi {{ $notification->data['icon'] ?? 'bi-bell' }} me-1 text-primary"></i>{{ $notification->data['title'] ?? '' }}
                                <div class="text-body-secondary fw-normal">{{ \Illuminate\Support\Str::limit((string) ($notification->data['body'] ?? ''), 90) }}</div>
                            </div>
                        @empty
                            <div class="px-3 py-3 small text-body-secondary">{{ __('No notifications') }}</div>
                        @endforelse
                        <a href="{{ $notificationBell['indexUrl'] }}" class="dropdown-item text-center small py-2">{{ __('See all notifications') }}</a>
                    </div>
                </li>
            @endisset


            <li class="nav-item dropdown">
                <button class="btn btn-link nav-link dropdown-toggle d-flex align-items-center" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('Colour mode') }}">
                    <i class="bi bi-sun-fill" data-lte-theme-icon="light"></i>
                    <i class="bi bi-moon-stars-fill d-none" data-lte-theme-icon="dark"></i>
                    <i class="bi bi-circle-half d-none" data-lte-theme-icon="auto"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    @foreach (['light' => ['bi-sun-fill', __('Light')], 'dark' => ['bi-moon-stars-fill', __('Dark')], 'auto' => ['bi-circle-half', __('Auto')]] as $mode => [$icon, $label])
                        <li>
                            <button type="button" class="dropdown-item d-flex align-items-center" data-bs-theme-value="{{ $mode }}" aria-pressed="false">
                                <i class="bi {{ $icon }} me-2 opacity-50"></i>{{ $label }}
                                <i class="bi bi-check-lg ms-auto d-none"></i>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="#" data-lte-toggle="fullscreen" aria-label="{{ __('Full screen') }}">
                    <i data-lte-icon="maximize" class="bi bi-arrows-fullscreen"></i>
                    <i data-lte-icon="minimize" class="bi bi-fullscreen-exit d-none"></i>
                </a>
            </li>

            {{-- $userMenu is shared by the IAM module for signed-in tenant users. --}}
            @isset($userMenu)
                <li class="nav-item dropdown">
                    <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle me-1"></i>
                        <span class="d-none d-md-inline">{{ $userMenu['name'] }}</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text small text-body-secondary">{{ $userMenu['email'] }}</span></li>
                        <li><hr class="dropdown-divider"></li>
                        @foreach ($userMenu['items'] as $item)
                            <li><a class="dropdown-item" href="{{ $item['url'] }}"><i class="bi {{ $item['icon'] }} me-2"></i>{{ $item['label'] }}</a></li>
                        @endforeach
                        <li>
                            <form method="POST" action="{{ $userMenu['logoutUrl'] }}">
                                @csrf
                                <button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right me-2"></i>{{ __('Sign out') }}</button>
                            </form>
                        </li>
                    </ul>
                </li>
            @endisset
        </ul>
    </div>
</nav>
