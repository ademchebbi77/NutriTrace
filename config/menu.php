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
        ['heading' => 'menu.headings.moderation', 'items' => [
            ['label' => 'menu.certifications_review', 'icon' => 'fa-certificate', 'route' => 'admin.certifications.index'],
            ['label' => 'menu.reports', 'icon' => 'fa-flag', 'route' => 'admin.reports.index'],
        ]],
        ['heading' => 'menu.headings.catalog', 'items' => [
            ['label' => 'menu.categories', 'icon' => 'fa-tags', 'route' => 'admin.categories.index'],
            ['label' => 'menu.products', 'icon' => 'fa-apple-alt', 'route' => 'admin.products.index'],
            ['label' => 'menu.lots', 'icon' => 'fa-boxes', 'route' => 'admin.lots.index'],
        ]],
        ['heading' => 'menu.headings.chain', 'items' => [
            ['label' => 'menu.transformations', 'icon' => 'fa-industry', 'route' => 'admin.transformations.index'],
            ['label' => 'menu.transports', 'icon' => 'fa-truck', 'route' => 'admin.transports.index'],
            ['label' => 'menu.distributions', 'icon' => 'fa-store', 'route' => 'admin.distributions.index'],
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
            ['label' => 'menu.lots', 'icon' => 'fa-boxes', 'route' => 'producteur.lots.index'],
        ]],
        ['heading' => 'menu.headings.sustainability', 'items' => [
            ['label' => 'menu.certifications', 'icon' => 'fa-certificate', 'route' => 'producteur.certifications.index'],
            ['label' => 'menu.environmental_data', 'icon' => 'fa-leaf', 'route' => 'producteur.impacts.index'],
        ]],
        ['heading' => 'menu.headings.logistics', 'items' => [
            ['label' => 'menu.transfers', 'icon' => 'fa-exchange-alt', 'route' => 'producteur.transfers.index'],
            ['label' => 'menu.transports', 'icon' => 'fa-truck', 'route' => 'producteur.transports.index'],
        ]],
        ['heading' => 'menu.headings.account', 'items' => [
            ['label' => 'menu.organization', 'icon' => 'fa-building', 'route' => 'organization.edit'],
        ]],
    ],

    'TRANSFORMATEUR' => [
        ['heading' => 'menu.headings.transformation', 'items' => [
            ['label' => 'menu.receptions', 'icon' => 'fa-inbox', 'route' => 'transformateur.receptions.index'],
            ['label' => 'menu.products', 'icon' => 'fa-apple-alt', 'route' => 'transformateur.products.index'],
            ['label' => 'menu.lots', 'icon' => 'fa-boxes', 'route' => 'transformateur.lots.index'],
            ['label' => 'menu.transformations', 'icon' => 'fa-industry', 'route' => 'transformateur.transformations.index'],
        ]],
        ['heading' => 'menu.headings.sustainability', 'items' => [
            ['label' => 'menu.certifications', 'icon' => 'fa-certificate', 'route' => 'transformateur.certifications.index'],
            ['label' => 'menu.environmental_data', 'icon' => 'fa-leaf', 'route' => 'transformateur.impacts.index'],
        ]],
        ['heading' => 'menu.headings.logistics', 'items' => [
            ['label' => 'menu.transports', 'icon' => 'fa-truck', 'route' => 'transformateur.transports.index'],
            ['label' => 'menu.transfers', 'icon' => 'fa-exchange-alt', 'route' => 'transformateur.transfers.index'],
        ]],
        ['heading' => 'menu.headings.account', 'items' => [
            ['label' => 'menu.organization', 'icon' => 'fa-building', 'route' => 'organization.edit'],
        ]],
    ],

    'DISTRIBUTEUR' => [
        ['heading' => 'menu.headings.distribution', 'items' => [
            ['label' => 'menu.receptions', 'icon' => 'fa-inbox', 'route' => 'distributeur.receptions.index'],
            ['label' => 'menu.lots', 'icon' => 'fa-boxes', 'route' => 'distributeur.lots.index'],
            ['label' => 'menu.distributions', 'icon' => 'fa-store', 'route' => 'distributeur.distributions.index'],
        ]],
        ['heading' => 'menu.headings.sales', 'items' => [
            ['label' => 'menu.qr_labels', 'icon' => 'fa-qrcode', 'route' => 'distributeur.labels.index'],
            ['label' => 'menu.sales', 'icon' => 'fa-cash-register', 'route' => 'distributeur.sales.index'],
        ]],
        ['heading' => 'menu.headings.account', 'items' => [
            ['label' => 'menu.organization', 'icon' => 'fa-building', 'route' => 'organization.edit'],
        ]],
    ],

    'CONSOMMATEUR' => [
        ['heading' => 'menu.headings.my_space', 'items' => [
            ['label' => 'menu.history', 'icon' => 'fa-history', 'route' => 'consommateur.history.index'],
            ['label' => 'menu.favorites', 'icon' => 'fa-heart', 'route' => 'consommateur.favorites.index'],
            ['label' => 'menu.my_reviews', 'icon' => 'fa-star', 'route' => 'consommateur.reviews.index'],
            ['label' => 'menu.my_reports', 'icon' => 'fa-flag', 'route' => 'consommateur.reports.index'],
        ]],
    ],

];
