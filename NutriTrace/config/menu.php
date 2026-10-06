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
            ['label' => 'menu.lots', 'icon' => 'fa-boxes', 'route' => 'admin.lots.index'],
        ]],
        ['heading' => 'menu.headings.chain', 'items' => [
            ['label' => 'menu.transformations', 'icon' => 'fa-industry', 'route' => 'admin.transformations.index'],
        ]],
    ],

    'PRODUCTEUR' => [
        ['heading' => 'menu.headings.production', 'items' => [
            ['label' => 'menu.lots', 'icon' => 'fa-boxes', 'route' => 'producteur.lots.index'],
        ]],
        ['heading' => 'menu.headings.account', 'items' => [
            ['label' => 'menu.organization', 'icon' => 'fa-building', 'route' => 'organization.edit'],
        ]],
    ],

    'TRANSFORMATEUR' => [
        ['heading' => 'menu.headings.transformation', 'items' => [
            ['label' => 'menu.lots', 'icon' => 'fa-boxes', 'route' => 'transformateur.lots.index'],
            ['label' => 'menu.transformations', 'icon' => 'fa-industry', 'route' => 'transformateur.transformations.index'],
        ]],
        ['heading' => 'menu.headings.account', 'items' => [
            ['label' => 'menu.organization', 'icon' => 'fa-building', 'route' => 'organization.edit'],
        ]],
    ],

    'DISTRIBUTEUR' => [
        ['heading' => 'menu.headings.distribution', 'items' => [
            ['label' => 'menu.lots', 'icon' => 'fa-boxes', 'route' => 'distributeur.lots.index'],
        ]],
        ['heading' => 'menu.headings.account', 'items' => [
            ['label' => 'menu.organization', 'icon' => 'fa-building', 'route' => 'organization.edit'],
        ]],
    ],

    'CONSOMMATEUR' => [],

];
