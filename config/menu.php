<?php

/*
|--------------------------------------------------------------------------
| Back office sidebar
|--------------------------------------------------------------------------
|
| One list of sections per role (keys are App\Enums\UserRole values). Each item
| has a translation key (lang/fr/menu.php), a Font Awesome icon and a route name.
| Items whose route does not exist yet are rendered as disabled links, so the
| menu can be declared before the module is built.
|
*/

return [

    'ADMIN' => [
        ['heading' => 'menu.headings.accounts', 'items' => [
            ['label' => 'menu.pending_accounts', 'icon' => 'fa-user-clock', 'route' => 'admin.approvals.index'],
            ['label' => 'menu.users', 'icon' => 'fa-users', 'route' => 'admin.users.index'],
        ]],
        ['heading' => 'menu.headings.catalog', 'items' => [
            ['label' => 'menu.categories', 'icon' => 'fa-tags', 'route' => 'admin.categories.index'],
            ['label' => 'menu.products', 'icon' => 'fa-apple-alt', 'route' => 'admin.products.index'],
        ]],
        ['heading' => 'menu.headings.system', 'items' => [
            ['label' => 'menu.settings', 'icon' => 'fa-sliders-h', 'route' => 'admin.settings.edit'],
            ['label' => 'menu.audit_log', 'icon' => 'fa-clipboard-list', 'route' => 'admin.audit-logs.index'],
        ]],
    ],

    'PRODUCTEUR' => [
        ['heading' => 'menu.headings.production', 'items' => [
            ['label' => 'menu.products', 'icon' => 'fa-apple-alt', 'route' => 'producteur.products.index'],
            ['label' => 'menu.productions', 'icon' => 'fa-tractor', 'route' => 'producteur.productions.index'],
        ]],
        ['heading' => 'menu.headings.account', 'items' => [
            ['label' => 'menu.organization', 'icon' => 'fa-building', 'route' => 'organization.edit'],
        ]],
    ],

    'TRANSFORMATEUR' => [
        ['heading' => 'menu.headings.transformation', 'items' => [
            ['label' => 'menu.products', 'icon' => 'fa-apple-alt', 'route' => 'transformateur.products.index'],
        ]],
        ['heading' => 'menu.headings.account', 'items' => [
            ['label' => 'menu.organization', 'icon' => 'fa-building', 'route' => 'organization.edit'],
        ]],
    ],

    'DISTRIBUTEUR' => [
        ['heading' => 'menu.headings.account', 'items' => [
            ['label' => 'menu.organization', 'icon' => 'fa-building', 'route' => 'organization.edit'],
        ]],
    ],

    'CONSOMMATEUR' => [
        ['heading' => 'menu.headings.my_space', 'items' => [
            ['label' => 'menu.favorites', 'icon' => 'fa-heart', 'route' => 'consommateur.favorites.index'],
        ]],
    ],

];
