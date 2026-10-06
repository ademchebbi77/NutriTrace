# NutriTrace — De la ferme à l'assiette

![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8-4479A1?logo=mysql&logoColor=white)
![Vite](https://img.shields.io/badge/Vite-8-646CFF?logo=vite&logoColor=white)
![Tests](https://img.shields.io/badge/Tests-144-2AA63E)
![Licence](https://img.shields.io/badge/Licence-MIT-blue)

Plateforme web de **traçabilité alimentaire** : suivez le parcours d'un produit, de la production à la vente, connaissez son empreinte environnementale et vérifiez si ses promesses (bio, local, équitable) sont réellement prouvées.

> « D'où vient ce produit, que lui est-il arrivé, quel est son impact, et peut-on croire ce qu'il annonce ? »

Un consommateur scanne le QR code d'un lot et obtient toute la chaîne : producteurs, transformations, transports, distribution — avec un journal de traçabilité **infalsifiable** (chaîne d'empreintes SHA-256), une note environnementale de A à E, un score de transparence sur 100 et des **alertes anti-greenwashing**.

---

## Table des matières

1. [Fonctionnalités](#fonctionnalités)
2. [Stack technique](#stack-technique)
3. [Démarrage rapide](#démarrage-rapide)
4. [Comptes de démonstration](#comptes-de-démonstration)
5. [Données de démonstration](#données-de-démonstration)
6. [Structure du projet](#structure-du-projet)
7. [Architecture](#architecture)
8. [Modules](#modules)
9. [Formules de calcul](#formules-de-calcul)
10. [API](#api)
11. [Tests](#tests)
12. [Commandes utiles](#commandes-utiles)
13. [Modèles et ressources](#modèles-et-ressources)
14. [Limites connues](#limites-connues)
15. [Équipe](#équipe)
16. [Licence](#licence)

---

## Fonctionnalités

- **Traçabilité de bout en bout** : chaque production, transformation, transport, distribution et vente est journalisée. Le consommateur suit le parcours complet via un QR code.
- **Journal infalsifiable** : chaque événement contient l'empreinte SHA-256 du précédent. Toute modification directe en base est détectée et affichée (chaîne « rompue »).
- **Empreinte environnementale** : CO₂, eau, énergie, transport et déchets par lot, normalisés au kilogramme et notés de A à E.
- **Score de transparence** (0-100) avec explication détaillée de chaque composante.
- **Anti-greenwashing** : la mention « local » est mesurée (distance réelle ferme → lieu de vente), la mention « bio » exige un certificat vérifié par l'administration. Les incohérences déclenchent des alertes publiques.
- **Certifications** : dépôt de justificatifs sur un disque privé, revue par l'administrateur, expiration automatique quotidienne.
- **Rôles** : producteur, transformateur, distributeur, consommateur et administrateur, chacun avec son espace et ses permissions. Les comptes professionnels sont validés par un administrateur.
- **Site public** : catalogue avec filtres, fiche produit, page de traçabilité (carte Leaflet + graphiques), comparaison de lots, avis, signalements.
- **API JSON** publique, en lecture seule, respectueuse de la vie privée (nom et ville des organisations uniquement).
- **Paramétrage en direct** : l'administrateur ajuste les facteurs de calcul depuis l'interface, avec recalcul immédiat de tous les scores.
- **Journal d'audit** : toutes les actions sensibles sont tracées.

## Stack technique

| Couche | Technologie |
|---|---|
| Backend | PHP 8.3, Laravel 13 |
| Base de données | MySQL 8 / MariaDB 10.4+ (SQLite en mémoire pour les tests) |
| Frontend | Blade, Vite 8 |
| Back office | SB Admin 2 (Bootstrap 4, jQuery, DataTables, Chart.js) |
| Site public | Bootstrap 5, Chart.js 4, Leaflet (OpenStreetMap), Bootstrap Icons |
| QR codes | `simplesoftwareio/simple-qrcode` |
| Authentification | Laravel Breeze (adapté : rôles, statuts de compte) |
| Tests | PHPUnit 12 |

## Démarrage rapide

**Prérequis** : PHP 8.3+ (extensions `pdo_mysql`, `gd`, `intl`, `zip`, `fileinfo`), Composer, Node.js 20+, MySQL 8 ou MariaDB 10.4+.

```bash
composer install
npm install
cp .env.example .env        # sous Windows : copy .env.example .env
php artisan key:generate
```

Créez une base vide nommée `nutritrace`, puis vérifiez dans `.env` :

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nutritrace
DB_USERNAME=root
DB_PASSWORD=
```

Créez les tables et chargez les données de démonstration :

```bash
php artisan migrate:fresh --seed
php artisan storage:link
```

Lancez l'application (deux terminaux) :

```bash
npm run dev
php artisan serve
```

Le site est accessible sur `http://localhost:8000`. Pour des ressources compilées : `npm run build`.

**Raccourcis** :

- `composer setup` : installation complète (dépendances, `.env`, clé, migrations, build).
- `composer dev` : lance `php artisan dev` (serveur + Vite).

### E-mails

Par défaut `MAIL_MAILER=log` : les e-mails (vérification d'adresse, mot de passe oublié, validation de compte) sont écrits dans `storage/logs/laravel.log` ; le lien à cliquer se trouve en fin de fichier. Pour un envoi réel, renseignez un serveur SMTP dans `.env`. Avec Gmail, utilisez un « mot de passe d'application » de 16 lettres, saisi sans espaces.

## Comptes de démonstration

Mot de passe de tous les comptes : `password`.

| Adresse | Rôle | Organisation |
|---|---|---|
| `admin@nutritrace.test` | Administrateur | — |
| `producteur@nutritrace.test` | Producteur | Domaine Chaâl, Sfax (olives) |
| `producteur2@nutritrace.test` | Producteur | Ferme El Baraka, Béja (lait, blé) |
| `producteur3@nutritrace.test` | Producteur | Maraîchers du Cap Bon, Nabeul (tomates, oranges, piments) |
| `producteur4@nutritrace.test` | Producteur | Rucher du Zaghouan (miels) |
| `transformateur@nutritrace.test` | Transformateur | Huilerie Sidi Mansour, Sfax |
| `transformateur2@nutritrace.test` | Transformateur | Fromagerie de Béja |
| `distributeur@nutritrace.test` | Distributeur | Marché Vert Tunis |
| `distributeur2@nutritrace.test` | Distributeur | Épicerie du Sahel, Sousse |
| `consommateur@nutritrace.test` | Consommateur | — |
| `consommateur2@nutritrace.test` | Consommateur | — |
| `en-attente@nutritrace.test` | Transformateur | compte en attente de validation |
| `refuse@nutritrace.test` | Distributeur | compte refusé |

Le jeu de données étendu ajoute, avec le même mot de passe : `producteur5` à `producteur9` (Kébili, Kairouan, Kasserine, Testour, Mornag), `transformateur3` à `transformateur5` (Tozeur, Nabeul, Mateur), `distributeur3` à `distributeur5` (Sfax, Bizerte, La Marsa) et `consommateur3` à `consommateur8`, tous en `@nutritrace.test`.

## Données de démonstration

Le seed contient **23 produits, 57 lots, plus de 300 événements de traçabilité, 19 certifications** (vérifiées, en attente, expirées, refusées), une soixantaine d'avis et 9 signalements. Tout est généré par les **vrais services** de l'application : les empreintes et les scores affichés sont réellement calculés, les chaînes d'empreintes réellement vérifiables.

Les organisations et les personnes sont **inventées** ; les régions, les variétés, les organismes certificateurs et les ordres de grandeur sont réels.

Pour ajouter le jeu étendu à une base existante sans rien effacer :

```bash
php artisan db:seed --class=VolumeSeeder
```

Lots intéressants à ouvrir (recherche par numéro sur la page d'accueil) :

| Lot | Ce qu'il montre |
|---|---|
| `LOT-2025-006` — Huile d'olive | Parcours de référence : 5 000 kg d'olives à Sfax, 900 L d'huile, 270 km en camion jusqu'à Tunis, ventes |
| `LOT-2025-003` — Olives de table | Mention « local » contredite par la distance, signalement en cours d'examen (bandeau) |
| `LOT-2026-013` — Fromage de Béja | Produit réellement local, issu d'une transformation |
| `LOT-2026-004` — Tomates | Mention « bio » sans certificat valide, certificat refusé |
| `LOT-2026-008` — Miel de thym | Score de transparence maximal, données mesurées |
| `LOT-2026-002` — Lait | Lot en transit, en attente de réception chez le transformateur |

## Structure du projet

```
app/
  Console/Commands/       commandes (make:admin, certifications:expire, scores:refresh, ...)
  Enums/                  rôles, statuts de lot, types d'événements, ...
  Http/Controllers/       racine (modules), Admin/, Auth/, PublicSite/, Consumer/, Producer/, Api/
  Http/Middleware/        rôles (EnsureUserHasRole), comptes actifs
  Http/Requests/          validation (Form Requests)
  Http/Resources/         API (TraceResource)
  Models/                 18 modèles
  Observers/              effets entre modules (7 observers)
  Policies/               autorisations (13 policies)
  Services/               logique métier, dont Scoring/ et Traceability/
  helpers.php             area_route(), format_quantity(), ...
config/
  footprint.php           facteurs d'émission et note A-E
  trust.php               score de transparence et pénalités
  menu.php                menu latéral par rôle
database/
  migrations/             14 migrations
  seeders/                données de démonstration (via les vrais services)
lang/fr/                  traductions françaises
resources/
  assets/css,js/{admin,public}/   deux lots de ressources séparés
  views/                  layouts/, partials/, un dossier par module, public/, admin/
routes/
  web.php, api.php, auth.php, console.php
  modules/                10 fichiers de routes (un par module)
tests/
  Feature/                Auth/, Modules/, site public, données de démo
  Concerns/BuildsJourneys.php   construit le parcours de référence via les services
```

## Architecture

Le **lot** est l'objet central : tout s'y rattache.

```
Produit → Production → Lot → Transformation → Transport → Distribution → Vente
                         |
         Certifications, Empreinte, Événements de traçabilité, Avis, Signalements
```

Flux de traitement : **Route → Contrôleur (fin) → Form Request (validation) / Policy (autorisation) / Service (logique) → Observer (effets entre modules) → Modèle → Vue**.

Principes suivis dans tout le code :

- **Contrôleurs fins** : ils autorisent, appellent un service et renvoient une vue.
- **Form Requests** pour la validation, **Policies** pour les autorisations, **Enums** pour les statuts et les types.
- **Services** (`app/Services`) pour la logique métier.
- **Observers** (`app/Observers`) pour les effets entre modules : créer une production crée son lot et son premier événement ; chaque transport, transformation ou distribution ajoute ses événements et déclenche le recalcul des scores.

### Règles métier importantes

- Un lot n'est jamais créé à la main : il naît d'une production ou d'une transformation. Numéro automatique `LOT-AAAA-NNN`, jeton public unique pour le QR code.
- Un lot s'envoie en entier. Il reste chez son expéditeur tant que le destinataire n'a pas **confirmé la réception** ; en cas de refus il revient à l'expéditeur.
- L'envoi à un transformateur crée un **transfert**, l'envoi à un distributeur crée une **distribution**. Dans les deux cas un **transport** est créé, avec une distance calculée à vol d'oiseau (formule de Haversine) ou saisie à la main.
- Une transformation consomme un ou plusieurs lots sources et crée un nouveau lot relié à eux. Elle est définitive.
- Les **événements de traçabilité** ne se modifient ni ne se suppriment. Une correction est un nouvel événement. Chaque événement contient l'empreinte SHA-256 du précédent : toute modification directe en base est détectée et affichée.
- Les justificatifs de certification sont sur un disque privé et servis par des routes contrôlées.
- Les pages publiques n'affichent que le nom et la ville des organisations, jamais leurs coordonnées.

### Services principaux

| Service | Rôle |
|---|---|
| `LotDispatcher`, `LotReceptionService` | Envoi d'un lot, confirmation ou refus de réception, mise en rayon |
| `TransformationService`, `SaleService` | Transformation de lots, enregistrement des ventes |
| `Traceability\TraceabilityRecorder` | Seul point d'écriture des événements, calcule les empreintes |
| `Traceability\ChainVerifier` | Vérifie la chaîne d'empreintes d'un lot ou d'un parcours |
| `Traceability\JourneyBuilder` | Reconstitue le parcours complet en remontant les transformations jusqu'aux fermes |
| `Scoring\FootprintCalculator` | Empreinte environnementale, note de A à E |
| `Scoring\TrustScoreCalculator` | Score de transparence et son explication |
| `Scoring\GreenwashingWarnings` | Liste des points de vigilance |
| `Scoring\LocalRule` | Règle « Local » mesurée |
| `Scoring\LotScoreManager` | Tient à jour les scores mis en cache |
| `CertificationService`, `ReportService`, `AuditLogger` | Certifications, signalements, journal d'audit |

## Modules

| Module | Contenu | Fichier de routes |
|---|---|---|
| 1. Produits | CRUD produit (code-barres EAN validé, images, statuts), catégories | `routes/modules/products.php` |
| 2. Productions | Enregistrement d'une production → création automatique du lot | `routes/modules/productions.php` |
| 3. Lots | Transferts entre acteurs, réceptions, étiquettes QR | `routes/modules/lots.php` |
| 4. Transformations | Consommation de lots sources → nouveau lot | `routes/modules/transformations.php` |
| 5. Transports | Suivi et corrections des transports | `routes/modules/transports.php` |
| 6. Distributions | Réception, mise en rayon, ventes | `routes/modules/distributions.php` |
| 7. Certifications | Dépôt, justificatifs privés, revue administrateur | `routes/modules/certifications.php` |
| 8. Impact environnemental | Empreinte par lot, déclarations des acteurs | `routes/modules/impacts.php` |
| 9. Traçabilité | Journal chaîné SHA-256, vérification de la chaîne | (services) |
| 10. Communauté | Espace consommateur, avis, signalements, audit | `routes/modules/community.php` |

## Formules de calcul

Les valeurs se trouvent dans `config/footprint.php` et `config/trust.php`. La page publique « Comment ça marche » affiche les valeurs en vigueur.

> Les facteurs d'émission sont des **valeurs simplifiées à but pédagogique**. Ils donnent un ordre de grandeur, pas un bilan carbone certifié.

### Empreinte environnementale

Pour un lot :

- **Production** : `énergie × facteur électricité + engrais × facteur engrais + phytosanitaires × facteur phytosanitaires`.
- **Transformation** : `énergie × facteur électricité`, plus une part de l'empreinte de chaque lot source égale à `quantité utilisée / quantité initiale du lot source`.
- **Transport** : `distance (km) × masse (tonnes) × facteur du mode de transport`. Un litre est compté pour un kilogramme.
- **Eau et énergie** : somme des quantités déclarées par les acteurs.
- **Distance parcourue** : somme des transports du lot, plus la moyenne pondérée de celle de ses lots sources.

Chaque valeur porte son origine : **mesuré**, **déclaré** ou **calculé**. Le détenteur peut déclarer ses propres chiffres ; ils remplacent alors la valeur calculée de l'indicateur.

La **note** ramène le CO₂, l'eau et l'énergie au kilogramme de produit. Chaque indicateur vaut 100 en dessous du seuil « meilleur », 0 au-dessus du seuil « pire », avec une variation linéaire entre les deux. Le score est leur moyenne pondérée (CO₂ 40 %, eau 20 %, énergie 20 %, distance 20 %). Un indicateur inconnu est écarté et les poids restants sont remis à l'échelle. Notes : A à partir de 80, B de 60, C de 40, D de 20, E en dessous.

### Score de transparence (sur 100)

| Critère | Points | Mesure |
|---|---|---|
| Parcours complet | 25 | origine connue, origine géolocalisée, transport documenté, distribution enregistrée, transformation décrite |
| Acteurs vérifiés | 20 | part des organisations du parcours vérifiées par l'administrateur |
| Certifications valides | 20 | 0 sans certification valide, 60 % sans justificatif ni numéro, 100 % avec |
| Données environnementales | 20 | part des six indicateurs chiffrés |
| Intégrité du journal | 15 | chaîne d'empreintes intacte sur tout le parcours |

Pénalités : 5 points par certification expirée, 10 par certification refusée, 5 par signalement ouvert, 5 par incohérence de dates ou de lieux, avec un plafond de 40 points.

### Règle « Local »

Un lot est local si la distance à vol d'oiseau entre chacune de ses fermes d'origine et son lieu de vente ne dépasse pas **150 km**. Une mention « local » sur un lot qui a parcouru plus de **250 km** déclenche une alerte.

### Modifier les réglages

1. **Dans l'application** : compte administrateur, menu « Paramètres de calcul ». Les valeurs sont enregistrées dans la table `settings` et tous les scores sont recalculés.
2. **Dans le code** : modifier `config/footprint.php` ou `config/trust.php`, puis lancer `php artisan scores:refresh`.

## API

```
GET /api/v1/trace/{jeton_public}
```

Renvoie en JSON le lot, son parcours, l'état de la chaîne d'empreintes, son empreinte environnementale, ses certifications, son score de transparence et ses alertes. Lecture seule, limitée à **60 requêtes par minute**, respectueuse de la vie privée (nom et ville des organisations uniquement).

## Tests

```bash
php artisan test
```

**144 tests**, exécutés sur une base SQLite en mémoire (aucun réglage nécessaire). Ils couvrent :

- l'authentification, la vérification d'e-mail, les comptes en attente et le workflow d'approbation ;
- les rôles et les permissions (accès croisés refusés en 403) ;
- les modules : produits, productions, lots, transferts, transformations, certifications, impacts ;
- le flux logistique complet (ferme → rayon), les refus, les contrôles de quantité ;
- la traçabilité : création des événements par les observers, chaîne d'empreintes, détection de falsification, journal en ajout seul ;
- le calcul : héritage d'empreinte, notes, score de transparence, pénalités, alertes, expiration des certifications ;
- le site public, les filtres du catalogue, la recherche, la comparaison, l'API JSON et sa limite de débit ;
- la confidentialité des coordonnées et l'affichage d'un journal falsifié.

Le trait `tests/Concerns/BuildsJourneys.php` construit le parcours de référence de l'huile d'olive avec les vrais services ; il sert de base à de nouveaux tests.

## Commandes utiles

| Commande | Rôle |
|---|---|
| `php artisan test` | Lance les tests (base SQLite en mémoire) |
| `php artisan make:admin` | Crée un administrateur (impossible par l'inscription publique) |
| `php artisan user:verify {email}` | Valide une adresse e-mail sans passer par le lien (développement) |
| `php artisan certifications:expire` | Passe en « expirée » les certifications dont la date est dépassée et recalcule les scores |
| `php artisan scores:refresh` | Recalcule l'empreinte et le score de transparence de tous les lots |
| `php artisan schedule:work` | Fait tourner le planificateur (expiration quotidienne des certifications à 1 h) |
| `php artisan db:seed --class=VolumeSeeder` | Ajoute le jeu de données étendu sans rien effacer |

## Modèles et ressources

Toutes les interfaces reprennent des modèles gratuits (licence MIT) de [startbootstrap.com](https://startbootstrap.com). Les fichiers de licence sont conservés dans `resources/assets/css/`.

| Interface | Modèle |
|---|---|
| Authentification et back office (tous les rôles) | SB Admin 2 |
| Accueil public | Landing Page |
| Catalogue et recherche | Shop Homepage |
| Fiche produit et page de traçabilité | Shop Item |
| Comparaison, « Comment ça marche » | Shop Homepage et Landing Page |

Points à connaître :

- **SB Admin 2 utilise Bootstrap 4** (avec jQuery) alors que les modèles publics utilisent Bootstrap 5. Les deux ne sont jamais chargés sur la même page : il y a deux lots de ressources séparés.
- Les ressources passent toutes par Vite. Quatre points d'entrée sont déclarés dans `vite.config.js` : `resources/assets/{css,js}/admin/app.*` et `resources/assets/{css,js}/public/app.*`.
- Les bibliothèques (jQuery, Bootstrap, Chart.js, DataTables, Font Awesome, Bootstrap Icons, Leaflet, polices Nunito et Lato) sont installées par npm, pas par CDN.
- Les images se référencent avec `Vite::asset('resources/assets/img/...')`.
- Les scripts de page ajoutés avec `@push('scripts')` doivent utiliser `<script type="module">`.

Le menu latéral est défini par rôle dans `config/menu.php`.

## Limites connues

- Un lot s'envoie en entier : il n'y a pas de fractionnement d'un lot entre plusieurs destinataires.
- Les distances automatiques sont à vol d'oiseau ; la distance réelle par la route est à saisir à la main.
- Les justificatifs des certifications de démonstration sont un même PDF fictif.
- Les images des produits de démonstration sont des vignettes générées, à remplacer par de vraies photos depuis la fiche produit.
- La base utilisée en développement est MariaDB (XAMPP) ; les tests tournent sur SQLite.

## Équipe

Chaque module a son fichier de routes (`routes/modules/`), son contrôleur, sa Form Request, sa Policy, son service, son dossier de vues, son fichier de traductions (`lang/fr/`) et ses tests. L'authentification, les rôles et les gabarits sont communs à toute l'équipe.

| Membre | Modules | Fichiers de routes |
|---|---|---|
| Membre 1 | Produit, Production | `products.php`, `productions.php` |
| Membre 2 | Lot (avec transferts et étiquettes QR), Transformation | `lots.php`, `transformations.php` |
| Membre 3 | Transport, Distribution (avec ventes) | `transports.php`, `distributions.php` |
| Membre 4 | Certification, Impact environnemental | `certifications.php`, `impacts.php` |
| Membre 5 | Traçabilité, Avis et signalements, site public | `community.php`, routes publiques de `web.php` |

Les noms sont à compléter par l'équipe.

## Licence

Projet académique. Les modèles Start Bootstrap utilisés sont sous licence MIT (fichiers de licence conservés dans `resources/assets/css/`).
