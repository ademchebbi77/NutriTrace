# NutriTrace : de la ferme à l'assiette

Plateforme web de traçabilité alimentaire (Laravel 13, MySQL, Blade, Vite). Elle permet à chacun de suivre le parcours d'un produit, de la production à la vente, de connaître son empreinte environnementale et de vérifier si ses promesses (bio, local, équitable) sont réellement prouvées.

> « D'où vient ce produit, que lui est-il arrivé, quel est son impact, et peut-on croire ce qu'il annonce ? »

## 1. Installation

Prérequis : PHP 8.3 ou plus (extensions `pdo_mysql`, `gd`, `intl`, `zip`, `fileinfo`), Composer, Node.js 20 ou plus, MySQL 8 ou MariaDB 10.4 ou plus.

```bash
composer install
npm install
cp .env.example .env        # sous Windows : copy .env.example .env
php artisan key:generate
```

Créer une base vide nommée `nutritrace`, puis vérifier dans `.env` :

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nutritrace
DB_USERNAME=root
DB_PASSWORD=
```

Créer les tables et charger les données de démonstration :

```bash
php artisan migrate:fresh --seed
php artisan storage:link
```

Lancer l'application (deux terminaux) :

```bash
npm run dev
php artisan serve
```

Le site est sur `http://localhost:8000`. Pour une version compilée des ressources : `npm run build`.

### Commandes utiles

| Commande | Rôle |
|---|---|
| `php artisan test` | Lance les tests (base SQLite en mémoire, aucun réglage nécessaire) |
| `php artisan make:admin` | Crée un administrateur (impossible par l'inscription publique) |
| `php artisan user:verify {email}` | Valide une adresse e-mail sans passer par le lien (développement) |
| `php artisan certifications:expire` | Passe en « expirée » les certifications dont la date est dépassée et recalcule les scores |
| `php artisan scores:refresh` | Recalcule l'empreinte et le score de transparence de tous les lots |
| `php artisan schedule:work` | Fait tourner le planificateur (expiration quotidienne des certifications à 1 h) |

### E-mails

Par défaut `MAIL_MAILER=log` : les e-mails (vérification d'adresse, mot de passe oublié, validation de compte) sont écrits dans `storage/logs/laravel.log`. Le lien à cliquer se trouve à la fin de ce fichier. Pour un envoi réel, renseigner un serveur SMTP dans `.env`. Avec Gmail il faut un « mot de passe d'application » de 16 lettres, saisi sans espaces.

## 2. Comptes de démonstration

Mot de passe de tous les comptes : `password`.

| Adresse | Rôle | Organisation |
|---|---|---|
| `admin@nutritrace.test` | Administrateur | |
| `producteur@nutritrace.test` | Producteur | Domaine Chaâl, Sfax (olives) |
| `producteur2@nutritrace.test` | Producteur | Ferme El Baraka, Béja (lait, blé) |
| `producteur3@nutritrace.test` | Producteur | Maraîchers du Cap Bon, Nabeul (tomates, oranges, piments) |
| `producteur4@nutritrace.test` | Producteur | Rucher du Zaghouan (miels) |
| `transformateur@nutritrace.test` | Transformateur | Huilerie Sidi Mansour, Sfax |
| `transformateur2@nutritrace.test` | Transformateur | Fromagerie de Béja |
| `distributeur@nutritrace.test` | Distributeur | Marché Vert Tunis |
| `distributeur2@nutritrace.test` | Distributeur | Épicerie du Sahel, Sousse |
| `consommateur@nutritrace.test` | Consommateur | |
| `consommateur2@nutritrace.test` | Consommateur | |
| `en-attente@nutritrace.test` | Transformateur | compte en attente de validation |
| `refuse@nutritrace.test` | Distributeur | compte refusé |

S'y ajoutent les comptes du jeu de données étendu, avec le même mot de passe : `producteur5` à `producteur9` (Kébili, Kairouan, Kasserine, Testour, Mornag), `transformateur3` à `transformateur5` (Tozeur, Nabeul, Mateur), `distributeur3` à `distributeur5` (Sfax, Bizerte, La Marsa) et `consommateur3` à `consommateur8`, tous en `@nutritrace.test`.

Les données comprennent 23 produits, 57 lots, plus de 300 événements de traçabilité, 19 certifications (vérifiées, en attente, expirées, refusées), une soixantaine d'avis et 9 signalements. Tout est généré par les services de l'application : les empreintes et les scores affichés sont donc réellement calculés.

Les organisations et les personnes sont **inventées** ; les régions, les variétés, les organismes certificateurs et les ordres de grandeur sont réels.

Le jeu étendu est chargé par `migrate:fresh --seed`. Pour l'ajouter à une base existante sans rien effacer : `php artisan db:seed --class=VolumeSeeder` (sans effet s'il est déjà présent).

Lots intéressants à ouvrir (recherche par numéro sur la page d'accueil) :

| Lot | Ce qu'il montre |
|---|---|
| `LOT-2025-006` Huile d'olive | Parcours de référence : 5 000 kg d'olives à Sfax, 900 L d'huile, 270 km en camion jusqu'à Tunis, ventes |
| `LOT-2025-003` Olives de table | Mention « local » contredite par la distance, signalement en cours d'examen (bandeau) |
| `LOT-2026-013` Fromage de Béja | Produit réellement local, issu d'une transformation |
| `LOT-2026-004` Tomates | Mention « bio » sans certificat valide, certificat refusé |
| `LOT-2026-008` Miel de thym | Score de transparence maximal, données mesurées |
| `LOT-2026-002` Lait | Lot en transit, en attente de réception chez le transformateur |

## 3. Modèles Start Bootstrap

Toutes les interfaces reprennent des modèles gratuits (licence MIT) de [startbootstrap.com](https://startbootstrap.com). Les fichiers de licence sont conservés dans `resources/assets/css/`.

| Interface | Modèle | Pages reprises |
|---|---|---|
| Authentification | SB Admin 2 | `login`, `register`, `forgot-password` ; même carte pour les autres écrans |
| Back office (tous les rôles) | SB Admin 2 | tableau de bord, tableaux, cartes, formulaires, graphiques, page 404 |
| Accueil public | Landing Page | bandeau avec recherche, icônes, vitrines, appel à l'action, pied de page |
| Catalogue et recherche | Shop Homepage | barre de navigation, en-tête, grille de cartes produit |
| Fiche produit et page de traçabilité | Shop Item | image et détails, section « éléments liés » |
| Comparaison, « Comment ça marche » | Shop Homepage et Landing Page | en-tête, tableaux, grille d'icônes |

Points à connaître :

- **SB Admin 2 utilise Bootstrap 4** (avec jQuery) alors que les modèles publics utilisent Bootstrap 5. Les deux ne sont jamais chargés sur la même page : il y a deux lots de ressources séparés.
- Les ressources passent toutes par Vite. Quatre points d'entrée sont déclarés dans `vite.config.js` : `resources/assets/{css,js}/admin/app.*` et `resources/assets/{css,js}/public/app.*`.
- Les bibliothèques (jQuery, Bootstrap, Chart.js, DataTables, Font Awesome, Bootstrap Icons, Leaflet, polices Nunito et Lato) sont installées par npm, pas par CDN.
- Les modèles Shop Homepage et Shop Item ne contiennent que Bootstrap 5.2.3 sans règle propre : la feuille de style du modèle Landing Page sert donc aux trois.
- Les images se référencent avec `Vite::asset('resources/assets/img/...')`.
- Les scripts de page ajoutés avec `@push('scripts')` doivent utiliser `<script type="module">`.

Organisation des vues :

```
resources/views/
  layouts/     layout (back office), public, auth
  partials/    sidebar, topbar, footer, flash, modals, timeline, trust-breakdown, public-navbar, public-footer
  auth/ profile/ organization/        comptes
  products/ productions/ lots/ transfers/ receptions/ transformations/ transports/
  distributions/ sales/ labels/ certifications/ impacts/      un dossier par module
  admin/ producer/ consumer/ pages/   écrans propres à un rôle
  public/                             site public
```

Le menu latéral est défini par rôle dans `config/menu.php`.

## 4. Architecture

Le **lot** est l'objet central : tout s'y rattache.

```
Produit -> Production -> Lot -> Transformation -> Transport -> Distribution -> Vente
                          |
          Certifications, Empreinte, Événements de traçabilité, Avis, Signalements
```

Principes suivis dans tout le code :

- **Contrôleurs fins** : ils autorisent, appellent un service et renvoient une vue.
- **Form Requests** pour la validation, **Policies** pour les autorisations, **Enums** pour les statuts et les types.
- **Services** (`app/Services`) pour la logique métier.
- **Observers** (`app/Observers`) pour les effets entre modules : créer une production crée son lot et son premier événement ; chaque transport, transformation ou distribution ajoute ses événements et déclenche le recalcul des scores.

### Règles métier importantes

- Un lot n'est jamais créé à la main : il naît d'une production ou d'une transformation. Numéro automatique `LOT-AAAA-NNN`, jeton public unique pour le QR code.
- Un lot s'envoie en entier. Il reste à son expéditeur tant que le destinataire n'a pas **confirmé la réception** ; en cas de refus il revient à l'expéditeur.
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

### API

`GET /api/v1/trace/{jeton_public}` renvoie en JSON le lot, son parcours, son empreinte, ses certifications, son score et ses alertes. Lecture seule, limitée à 60 requêtes par minute.

## 5. Répartition des modules

Chaque module a son fichier de routes (`routes/modules/`), son contrôleur, sa Form Request, sa Policy, son service, son dossier de vues, son fichier de traductions (`lang/fr/`) et ses tests. L'authentification, les rôles et les gabarits sont communs à toute l'équipe.

| Membre | Modules | Fichiers de routes |
|---|---|---|
| Membre 1 | Produit, Production | `products.php`, `productions.php` |
| Membre 2 | Lot (avec transferts et étiquettes QR), Transformation | `lots.php`, `transformations.php` |
| Membre 3 | Transport, Distribution (avec ventes) | `transports.php`, `distributions.php` |
| Membre 4 | Certification, Impact environnemental | `certifications.php`, `impacts.php` |
| Membre 5 | Traçabilité, Avis et signalements, site public | `community.php`, routes publiques de `web.php` |

Les noms sont à compléter par l'équipe.

## 6. Formules de calcul

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

Un lot est local si la distance à vol d'oiseau entre chacune de ses fermes d'origine et son lieu de vente ne dépasse pas 150 km. Une mention « local » sur un lot qui a parcouru plus de 250 km déclenche une alerte.

### Modifier les réglages

Deux possibilités :

1. Dans l'application : compte administrateur, menu « Paramètres de calcul ». Les valeurs sont enregistrées dans la table `settings` et tous les scores sont recalculés.
2. Dans le code : modifier `config/footprint.php` ou `config/trust.php`, puis lancer `php artisan scores:refresh`.

## 7. Tests

`php artisan test` lance 144 tests qui couvrent l'authentification, les rôles, les comptes en attente, les modules, le flux complet de transfert, la création des événements par les observers, la reconstitution du parcours, la chaîne d'empreintes, l'empreinte, le score de transparence, les alertes, l'expiration des certifications, les pages publiques et l'API.

Le trait `tests/Concerns/BuildsJourneys.php` construit le parcours de référence de l'huile d'olive avec les vrais services ; il peut servir de base à de nouveaux tests.

## 8. Limites connues

- Un lot s'envoie en entier : il n'y a pas de fractionnement d'un lot entre plusieurs destinataires.
- Les distances automatiques sont à vol d'oiseau ; la distance réelle par la route est à saisir à la main.
- Les justificatifs des certifications de démonstration sont un même PDF fictif.
- Les images des produits de démonstration sont des vignettes générées, à remplacer par de vraies photos depuis la fiche produit.
- La base utilisée en développement est MariaDB (XAMPP) ; les tests tournent sur SQLite.
