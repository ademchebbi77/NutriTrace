<?php

return [
    'title' => 'Produits',
    'singular' => 'Produit',
    'add' => 'Nouveau produit',
    'create_title' => 'Nouveau produit',
    'edit_title' => 'Modifier le produit',
    'list' => 'Liste des produits',
    'information' => 'Informations du produit',
    'image_card' => 'Image',
    'no_image' => 'Aucune image',
    'lots_card' => 'Lots de ce produit',
    'no_lots' => 'Aucun lot pour ce produit.',
    'empty' => 'Aucun produit pour le moment.',

    'created' => 'Le produit a été créé.',
    'updated' => 'Le produit a été mis à jour.',
    'deleted' => 'Le produit a été supprimé.',
    'delete_blocked' => 'Ce produit possède des lots : archivez-le plutôt que de le supprimer.',

    'fields' => [
        'name' => 'Nom du produit',
        'category' => 'Catégorie',
        'choose_category' => 'Choisir une catégorie',
        'description' => 'Description',
        'origin' => 'Origine',
        'origin_help' => 'Région ou terroir, par exemple « Sfax, Tunisie ».',
        'status' => 'Statut',
        'status_help' => 'Seuls les produits publiés apparaissent sur le site public.',
        'barcode' => 'Code-barres EAN (facultatif)',
        'barcode_help' => '8 ou 13 chiffres.',
        'image' => 'Image du produit',
        'owner' => 'Propriétaire',
        'lots_count' => 'Lots',
        'created_at' => 'Créé le',
    ],

    'attributes' => [
        'name' => 'nom du produit',
        'category_id' => 'catégorie',
        'description' => 'description',
        'origin' => 'origine',
        'status' => 'statut',
        'barcode' => 'code-barres',
        'image' => 'image',
    ],

    'validation' => [
        'barcode' => 'Le code-barres doit être un code EAN-8 ou EAN-13 valide.',
    ],

    'categories' => [
        'title' => 'Catégories',
        'list' => 'Liste des catégories',
        'add' => 'Ajouter une catégorie',
        'edit_title' => 'Modifier la catégorie',
        'name' => 'Nom de la catégorie',
        'name_attribute' => 'nom de la catégorie',
        'products_count' => 'Produits',
        'created' => 'La catégorie a été ajoutée.',
        'updated' => 'La catégorie a été mise à jour.',
        'deleted' => 'La catégorie a été supprimée.',
        'in_use' => 'Utilisée par des produits',
    ],
];
