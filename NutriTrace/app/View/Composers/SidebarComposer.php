<?php

namespace App\View\Composers;

use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SidebarComposer
{
    public function __construct(private readonly Request $request) {}

    public function compose(View $view): void
    {
        $role = $this->request->user()?->role ?? UserRole::CONSOMMATEUR;

        $view->with([
            'sidebarRole' => $role,
            'sidebarSections' => $this->sectionsFor($role),
        ]);
    }

    /**
     * @return list<array{heading: string, items: list<array{label: string, icon: string, url: ?string, active: bool}>}>
     */
    private function sectionsFor(UserRole $role): array
    {
        return array_map(fn (array $section) => [
            'heading' => __($section['heading']),
            'items' => array_map(fn (array $item) => [
                'label' => __($item['label']),
                'icon' => $item['icon'],
                'url' => Route::has($item['route']) ? route($item['route']) : null,
                // "producteur.lots.index" stays active on every "producteur.lots.*" route.
                'active' => $this->request->routeIs(Str::beforeLast($item['route'], '.').'.*'),
            ], $section['items']),
        ], config('menu.'.$role->value, []));
    }
}
