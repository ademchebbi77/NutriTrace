# IA locale de NutriTrace

IA de NutriTrace pour les modules **Transport - Traçabilité** et **Distribution - Certification**, écrite en Python et exécutée sur la même machine que l'application Laravel. **Aucune API externe n'est appelée**, ni pour l'entraînement ni pour les réponses. Internet ne sert qu'une fois : pour télécharger le modèle de base depuis Hugging Face.

| Module | Fonction | Modèle | Où dans l'application |
|---|---|---|---|
| Transport - Traçabilité | **Questions sur un lot** (« Quelle distance ? », « Est-il local ? ») | Qwen2.5-1.5B + **adaptateur LoRA « transport »** | Page publique de traçabilité, page d'un transport |
| Transport - Traçabilité | **Anomalies de transport** (distance, vitesse, mode, dates) | Forêt aléatoire + explication du LLM | Carte « Analyse IA du transport » |
| Distribution - Certification | **Certificats suspects** : organisme non reconnu ou imité, numéro mal formé ou déjà utilisé, dates, **contenu du PDF comparé au formulaire**, bio sur production conventionnelle | Forêt aléatoire (faible / moyen / élevé) + explication du LLM | Admin : badge et tri dans « Certifications à vérifier », rapport détaillé ; producteur : avertissement après l'envoi |
| Distribution - Certification | **Contrôle avant réception** : date limite, certificats, alertes, journal, transport | Forêt aléatoire (accepter / réserves / refuser) + modèle d'anomalies + LLM | Bouton « Vérifier avec l'IA » des réceptions, carte sur la page de la distribution |
| Distribution - Certification | **Assistant du distributeur** : stock, dates limites, ventes du mois, lots qui se vendent mal, réceptions en attente | Qwen2.5-1.5B + **adaptateur LoRA « distribution »** | Menu « Assistant IA » du distributeur |
| Transformation - Lots | **Contrôle d'une transformation** avant l'enregistrement : quantité produite plausible pour les lots utilisés (rendements de référence par type de transformation), énergie et eau réalistes, lots sources périmés | Forêt aléatoire + explication du LLM (**adaptateur « transformation »**) | Bouton « Vérifier avec l'IA » du formulaire ; confirmation demandée si une anomalie est détectée |
| Transformation - Lots | **Revue des transformations** par l'administrateur | Même modèle, score de risque + explication | Colonne « Contrôle IA » de la liste, carte sur chaque transformation |
| Transformation - Lots | **Dates limites des lots** : durée de conservation attendue pour le produit, date suggérée | Régression (gradient boosting) | Colonne « Date limite (IA) » de la liste des lots (administrateur) |

Le modèle de base n'est chargé **qu'une fois** sur la carte graphique ; chaque module a son propre adaptateur LoRA (quelques dizaines de Mo), choisi à chaque question. Les trois tiennent ainsi dans 6 Go.

Le LLM ne répond jamais « de mémoire » : chaque question lui est envoyée **avec les données réelles** (le lot, ou le stock et les ventes du distributeur), totaux et classements déjà calculés. Il a été entraîné à répondre uniquement à partir de ces données et à le dire quand l'information n'y figure pas.

```
Laravel (PHP)                                         Python (ce dossier, FastAPI)
─────────────                                         ────────────────────────────
AiContext, DistributionAiContext ── données ──►  /chat, /transport/analyze            (adaptateur transport)
AiClient ◄── réponses, scores ───────────────    /distribution/chat, /distribution/reception, /certification/risk
                                                  /train, /train/status, /train/cancel, /models
Résultats en cache dans la base : ai_answers, transport_analyses, distributor_ai_answers, reception_checks, certification_analyses
```

## 1. Installation (une fois)

Prérequis : Python 3.10+, une carte NVIDIA (testé sur RTX 4050 6 Go ; le CPU fonctionne mais il est lent).

```bash
cd NutriTrace/ai
python -m venv .venv
.venv\Scripts\activate                 # Linux/macOS : source .venv/bin/activate
pip install torch --index-url https://download.pytorch.org/whl/cu128
pip install -r requirements.txt
```

Côté Laravel : `php artisan migrate`, puis une fois `php artisan ai:demo-certificates` pour donner à chaque certificat de démonstration son propre justificatif PDF (ceux des certificats refusés contiennent volontairement un autre numéro).

## 2. Lancer le service

**Double-cliquer sur `ai/start.bat`** et laisser la fenêtre « NutriTrace AI (ne pas fermer) » ouverte pendant l'utilisation du site (chargement : environ 30 secondes). Ou dans un terminal :

```bash
cd NutriTrace/ai
.venv\Scripts\activate
uvicorn server:app --host 127.0.0.1 --port 8001
```

`http://127.0.0.1:8001/health` indique les modèles chargés. Côté Laravel (`.env`, tout est facultatif) :

```
AI_ENABLED=true
AI_SERVICE_URL=http://127.0.0.1:8001
AI_SERVICE_TOKEN=          # secret partagé, à définir aussi dans l'environnement du service
AI_TIMEOUT=90
```

Pour une démonstration fluide : `php artisan ai:warm` répond à l'avance aux questions suggérées de chaque lot.

**Quand le service est arrêté**, l'application continue de fonctionner : les résultats déjà calculés sont servis depuis la base et un message invite à lancer `ai/start.bat`. Un résultat n'est réutilisé que tant que les données n'ont pas changé (`data_version` = empreinte des données vues par l'IA). Sans carte graphique, `set NUTRITRACE_NO_LLM=1` lance le service sans LLM : les scores fonctionnent, avec des explications modèles.

## 3. Entraîner depuis l'application

Menu **Administrateur → IA locale** : état du service, modèles chargés, scores de chaque modèle, et entraînement d'un module en un clic. L'application exporte ses données (`ai:export`), puis le service lance `scripts/train_module.py` dans un processus séparé :

1. génération du jeu de données (données réelles + synthétiques) ;
2. entraînement des modèles scikit-learn (quelques secondes) ;
3. affinage de l'adaptateur LoRA du module sur la carte graphique (30 à 60 min) ;
4. évaluation sur des données jamais vues.

La page suit la progression en direct (étape, pas, perte, temps restant) et permet d'annuler. **Pendant l'entraînement, le LLM est déchargé** pour libérer la carte graphique : les réponses en cache restent disponibles, les scores scikit-learn continuent de fonctionner, et tous les modèles sont rechargés automatiquement à la fin.

## 4. Entraîner en ligne de commande

```bash
php artisan ai:export                                     # depuis le dossier NutriTrace

python scripts/train_module.py --module distribution       # tout le module, comme la page d'administration
# ou étape par étape :
python scripts/generate_dataset.py --module distribution   # (transport par défaut)
python scripts/train_distribution_models.py                # risque des certificats + contrôle de réception
python scripts/train_transformation_models.py              # contrôle des transformations + durée de conservation
python scripts/train_anomaly.py                            # anomalies de transport
python scripts/train_llm.py --module distribution          # adaptateur LoRA (--resume pour reprendre)
python scripts/evaluate_llm.py --module distribution       # --base pour comparer avec le modèle non affiné

python scripts/chat_cli.py LOT-2026-034                    # discuter avec le modèle transport dans le terminal
```

Si la carte graphique manque de mémoire, `train_llm.py` réessaie seul avec Qwen2.5-0.5B ; tous les adaptateurs doivent alors être entraînés sur ce même modèle de base pour être servis ensemble.

## 5. Les données d'entraînement

- **Données réelles**, exportées par `ai:export` (vue publique uniquement pour les lots : ni e-mail, ni téléphone, ni adresse) : lots, transports, stocks et ventes des distributeurs, réceptions, certificats avec le texte de leur PDF.
- **Données synthétiques** au même format, avec des lieux, produits et acteurs tunisiens : parcours de lots (`synth.py`), stocks de distributeurs avec leurs ventes, certificats authentiques ou défectueux (organisme imité, numéro copié, PDF qui ne correspond pas…) et réceptions (`synth_distribution.py`).
- **Questions-réponses** calculées à partir des données : 24 types pour les lots (`qa.py`), 15 pour les distributeurs (`stock_qa.py`), en plusieurs formulations avec du bruit (sans accents, sans majuscules), y compris des questions sans réponse dans les données et hors sujet, auxquelles le modèle apprend à ne pas répondre.
- **Explications** : des rapports (anomalie de transport, contrôle de réception, risque d'un certificat) et l'explication attendue.
- Les contextes (lots, distributeurs) sont répartis entre entraînement et validation : le score est mesuré sur des **données jamais vues**.

### Les modèles scikit-learn

Chaque modèle est une forêt aléatoire entraînée sur des exemples étiquetés par des **règles de référence** explicites (lisibles dans le code), ce qui permet d'expliquer chaque verdict par des raisons en français :

- **Anomalies de transport** (`features.py`, 18 variables) : route plus longue que ×2,5 la ligne droite ou plus courte qu'elle, vitesse au-delà du maximum du mode, avion sous 150 km, dates incohérentes, distance manquante.
- **Risque d'un certificat** (`certificates.py`, 27 variables) : registre des organismes reconnus par type de label (avec détection des noms imités par similarité), format du numéro propre à l'organisme, numéro déjà utilisé sur NutriTrace, durée de validité, justificatif absent ou illisible, numéro / organisme / titulaire / label / date absents du PDF, label bio sur une production conventionnelle.
- **Contrôle de réception** (`reception.py`, 18 variables) : jours restants et part de la durée de conservation, certificats valides / en attente / refusés, alertes NutriTrace, journal intègre, signalement en cours, transparence, verdict du modèle d'anomalies sur le transport.
- **Contrôle des transformations** (`transformations.py`, 18 variables) : rendement (masse produite / masse utilisée) comparé au profil de référence du type de transformation (huile d'olive 14-23 %, fromage 9-15 %, semoule 65-78 %, concentré de tomates 13-21 %…, calibrés sur les données de démonstration), énergie et eau par kg comparées au profil, âge et péremption des lots sources. Les profils inconnus n'appliquent que des limites physiques larges.
- **Durée de conservation** (`shelf_life.py`, régression par gradient boosting sur le logarithme des jours) : catégorie, mots-clés du produit (fromage, huile, miel, dattes…), unité, produit transformé ou non. La date saisie est jugée trop lointaine au-delà de 2,5 fois la durée attendue, trop proche en dessous de 0,35 fois.

Les scripts d'entraînement affichent précision, rappel et matrice de confusion sur un jeu de test jamais vu, et la justesse sur les cas réels de NutriTrace ; la page « IA locale » les reprend.

## 6. Tests

```bash
python -m pytest tests                          # Python : règles, jeux de données, API (faux LLM)
php artisan test --filter="TransportAi|DistributionAi|TransformationAi"   # Laravel : assistants, contrôles, cache, page d'administration
```

## Fichiers

| Fichier | Rôle |
|---|---|
| `nutritrace_ai/context.py`, `stock.py` | Données d'un lot / d'un distributeur → texte du prompt, totaux et classements calculés |
| `nutritrace_ai/prompts.py` | Prompts système, communs à l'entraînement et aux réponses |
| `nutritrace_ai/qa.py`, `stock_qa.py` | Questions/réponses calculées à partir des données |
| `nutritrace_ai/synth.py`, `synth_distribution.py` | Données synthétiques, injection d'anomalies et de défauts |
| `nutritrace_ai/features.py`, `certificates.py`, `reception.py` | Variables, règles de référence et raisons en français des trois modèles |
| `nutritrace_ai/pdf_text.py` | Texte des justificatifs PDF (pypdf, sans OCR) |
| `nutritrace_ai/tabular.py`, `anomaly.py`, `distribution_models.py` | Forêts aléatoires : sauvegarde, chargement, prédiction |
| `nutritrace_ai/llm.py` | Modèle de base chargé une fois + un adaptateur LoRA par module |
| `nutritrace_ai/train_status.py` | État en direct d'un entraînement (lu par la page d'administration) |
| `server.py` | API FastAPI appelée par Laravel |
| `scripts/` | Jeux de données, entraînements, évaluation, chat en terminal |
