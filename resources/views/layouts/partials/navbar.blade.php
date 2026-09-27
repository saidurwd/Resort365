<nav class="app-header navbar navbar-expand bg-body">
    <div class="container-fluid">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button" aria-label="{{ __('Toggle sidebar') }}">
                    <i class="bi bi-list"></i>
                </a>
            </li>
            {{-- TODO(step-0.8): property switcher and business date badge. --}}
        </ul>

        <ul class="navbar-nav ms-auto">
            {{-- TODO(step-1.2): quick search; TODO(step-0.7): notifications. --}}

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
