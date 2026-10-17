# IA de NutriTrace — module Transport - Traçabilité

IA locale de NutriTrace, écrite en Python et exécutée sur la même machine que l'application Laravel. **Aucune API externe n'est appelée**, ni pour l'entraînement ni pour les réponses. Internet ne sert qu'une fois : pour télécharger le modèle de base depuis Hugging Face.

| Fonction | Modèle | Où dans l'application |
|---|---|---|
| **Questions sur un lot** (« Quelle distance ? », « Est-il local ? », « La traçabilité est-elle fiable ? ») | **Qwen2.5-1.5B-Instruct affiné par LoRA** sur les données NutriTrace (0.5B si la carte graphique est trop petite) | Page publique de traçabilité (QR code) et page d'un transport dans le back-office |
| **Détection d'anomalies de transport** (distance, vitesse, mode, dates) | **Forêt aléatoire** (scikit-learn) qui détecte, puis **le même LLM** qui explique | Carte « Analyse IA du transport » de la page d'un transport |

Le modèle ne répond jamais « de mémoire » : chaque question lui est envoyée **avec les données réelles du lot** (parcours, transports, empreinte, certifications, scores). Il a été entraîné à répondre uniquement à partir de ces données et à le dire quand l'information n'y figure pas.

```
Laravel (PHP)                                      Python (ce dossier)
─────────────                                      ───────────────────
AiContext  ── données du lot / transport ──►  POST /chat               ──►  context.py → prompt → Qwen2.5 + LoRA
AiClient   ◄── réponse, verdict ───────────   POST /transport/analyze  ──►  features.py → forêt aléatoire → explication du LLM
LotAssistant, TransportAnalyzer : cache en base (ai_answers, transport_analyses)
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

## 2. Entraînement

```bash
# 1. Exporter les lots et transports réels de NutriTrace (depuis le dossier NutriTrace)
php artisan ai:export

# 2. Générer les données d'entraînement (≈ 3 400 questions/réponses + explications)
python scripts/generate_dataset.py

# 3. Entraîner le modèle d'anomalies (quelques secondes) → models/anomaly.joblib + anomaly-metrics.json
python scripts/train_anomaly.py

# 4. Affiner le LLM par LoRA (≈ 30 à 60 min sur RTX 4050) → models/llm-adapter/
python scripts/train_llm.py
#    mémoire GPU insuffisante ? le script réessaie seul avec Qwen2.5-0.5B-Instruct, ou :
python scripts/train_llm.py --base-model Qwen/Qwen2.5-0.5B-Instruct

# 5. Mesurer le gain : modèle de base contre modèle affiné, sur des lots jamais vus à l'entraînement
python scripts/evaluate_llm.py --base
python scripts/evaluate_llm.py

# Discuter avec le modèle dans le terminal
python scripts/chat_cli.py LOT-2026-034
```

### Contenu des données d'entraînement

- **Données réelles** : les 57 lots et 51 transports de la base de démonstration, exportés par `ai:export` (vue publique uniquement : ni e-mail, ni téléphone, ni adresse).
- **Données synthétiques** (`nutritrace_ai/synth.py`) : des parcours générés au même format, avec des lieux, produits et acteurs tunisiens et les mêmes phrases d'événements que `lang/fr/events.php`. Elles apprennent au modèle à lire *n'importe quel* lot au lieu de retenir ceux de la démonstration.
- **Questions-réponses** (`nutritrace_ai/qa.py`) : 24 types de questions (distance, modes de transport, origine, producteur, bio, dates, statut, local, CO₂, note, certifications, fiabilité, alertes, transformation, quantité, durée, signalements, résumé, questions sans réponse dans les données, coordonnées, hors sujet). Chacune existe en plusieurs formulations, avec du bruit (sans accents, sans majuscules). Les réponses sont **calculées à partir des données**.
- **Explications** : des rapports d'anomalie et l'explication attendue, pour chaque type d'anomalie.
- Les lots sont répartis entre entraînement et validation : le score de validation est donc mesuré sur des **lots que le modèle n'a jamais vus**.

### Le modèle d'anomalies

`features.py` calcule 18 variables par transport : le mode, la distance déclarée, la distance à vol d'oiseau (formule de Haversine, la même que `App\Services\GeoDistance`), le rapport entre les deux, la durée, la vitesse moyenne comparée au maximum du mode, les dates et la masse.

Les étiquettes d'entraînement viennent de règles de référence :
- route plus longue que ×2,5 la ligne droite, ou distance plus courte que la ligne droite ;
- vitesse au-delà du maximum du mode (camion 90 km/h, train 160, bateau 45, avion 800) ;
- avion sous 150 km, bateau sous 30 km ;
- arrivée avant le départ, départ avant la production ;
- distance manquante.

La forêt aléatoire apprend ces étiquettes sur environ 9 600 exemples (réels, synthétiques, limites et avec anomalies injectées). `train_anomaly.py` affiche sa précision et son rappel sur un jeu de test jamais vu, ainsi que sa justesse sur les transports réels de NutriTrace.

## 3. Lancer le service

Le plus simple : **double-cliquer sur `ai/start.bat`** et laisser la fenêtre « NutriTrace AI (ne pas fermer) » ouverte pendant l'utilisation du site. Le modèle met environ 30 secondes à se charger ; l'assistant apparaît ensuite sur les pages de traçabilité (dans la minute).

Pour une démonstration fluide, une fois le service lancé : `php artisan ai:warm` répond à l'avance aux questions suggérées de chaque lot (quelques secondes par lot). Un clic sur une suggestion est alors instantané ; seules les questions libres sont générées à la demande (1 à 20 s selon la charge de la carte graphique).

Ou dans un terminal :

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

Puis lancer une fois `php artisan migrate` (tables `ai_answers` et `transport_analyses`).

**Quand le service est arrêté**, l'application continue de fonctionner. Les réponses déjà données sont servies depuis la base (la question apparaît sous « Déjà répondu ») et les blocs d'IA sont masqués s'il n'y a rien à montrer. Une réponse n'est réutilisée que tant que les données du lot n'ont pas changé (`data_version` = empreinte des données vues par le modèle).

Sans carte graphique, on peut lancer le service sans le LLM : la détection d'anomalies fonctionne, avec des explications modèles.

```bash
set NUTRITRACE_NO_LLM=1 && uvicorn server:app --port 8001
```

## 4. Tests

```bash
python -m pytest tests                  # Python : règles, jeu de données, API (avec un faux LLM)
php artisan test --filter=TransportAi   # Laravel : assistant, cache, analyse, export
```

## Fichiers

| Fichier | Rôle |
|---|---|
| `nutritrace_ai/context.py` | Données du lot → texte du prompt (avec les totaux déjà calculés) |
| `nutritrace_ai/prompts.py` | Prompts système, communs à l'entraînement et aux réponses |
| `nutritrace_ai/qa.py` | Questions/réponses calculées à partir d'un lot |
| `nutritrace_ai/synth.py` | Lots et transports synthétiques, injection d'anomalies |
| `nutritrace_ai/features.py` | Variables d'un transport, règles de référence, raisons en français |
| `nutritrace_ai/anomaly.py` | Forêt aléatoire : entraînement, sauvegarde, prédiction |
| `nutritrace_ai/explain.py` | Explications de référence (cibles d'entraînement et secours sans LLM) |
| `nutritrace_ai/llm.py` | Chargement de Qwen2.5 + adaptateur LoRA, génération |
| `server.py` | API FastAPI appelée par Laravel |
| `scripts/` | Génération du jeu de données, entraînement, évaluation, chat en terminal |
