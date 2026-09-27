<?php

namespace App\Support\Ui;

use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Route;

/**
 * Sidebar items for the admin layout.
 *
 * TODO(step-0.6): replace with the menu registry, filtered by permission and enabled modules.
 */
class SidebarMenu
{
    /**
     * @return list<array{label: string, icon: string, url: string, active: bool, children?: list<array{label: string, url: string, active: bool}>}>
     */
    public static function items(): array
    {
        $items = [];

        if (app(TenantContext::class)->check() && auth('web')->check()) {
            $items[] = ['label' => __('Dashboard'), 'icon' => 'bi-speedometer2', 'url' => route('dashboard'), 'active' => request()->routeIs('dashboard')];

            if (Route::has('iam.users.index')) {
                $items[] = ['label' => __('Users'), 'icon' => 'bi-people', 'url' => route('iam.users.index'), 'active' => request()->routeIs('iam.users.*')];
            }
        } else {
            $items[] = ['label' => __('Home'), 'icon' => 'bi-house', 'url' => url('/'), 'active' => request()->is('/')];
        }

        if (app()->isLocal()) {
            $items[] = [
                'label' => __('UI Kit'),
                'icon' => 'bi-palette',
                'url' => route('ui-kit.index'),
                'active' => request()->routeIs('ui-kit.*'),
                'children' => [
                    ['label' => __('Components'), 'url' => route('ui-kit.index'), 'active' => request()->routeIs('ui-kit.index')],
                    ['label' => __('Print layout'), 'url' => route('ui-kit.print'), 'active' => request()->routeIs('ui-kit.print')],
                ],
            ];
        }

        return $items;
    }
}
