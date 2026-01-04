# 🎯 NexBet Analytics - Système d'Analyse de Paris Sportifs

## Description

NexBet Analytics est une plateforme d'analyse avancée de paris sportifs utilisant des algorithmes mathématiques complexes pour prédire les résultats de matchs de football et de basketball avec une précision proche de l'absolue.

## 🚀 Fonctionnalités

### Football (45+ types de paris)

#### Résultats
- **1X2** : Victoire Domicile, Nul, Victoire Extérieur
- **Double Chance** : 1X, X2, 12
- **Mi-Temps / Fin de Match** : Toutes les combinaisons (1/1, 1/X, 1/2, etc.)
- **Résultat à la Mi-Temps** : 1, X, 2

#### Buts
- **Over/Under** : 0.5, 1.5, 2.5, 3.5, 4.5, 5.5+ buts
- **Score Exact** : Prédiction du score précis
- **BTTS** (Both Teams To Score) : Oui/Non
- **Nombre de buts par équipe** : Over/Under pour chaque équipe

#### Buteurs
- **Premier buteur**
- **Dernier buteur**
- **Buteur à tout moment** (Anytime)
- **Joueur marque 2+ buts**
- **Joueur marque 3+ buts** (Triplé)

#### Statistiques
- **Corners** : Total, par équipe
- **Cartons** : Jaunes, Rouges
- **Clean Sheet** : Cage inviolée

### Basketball (35+ types de paris)

#### Résultats
- **Vainqueur du Match**
- **Handicap** : -3.5 à -12.5 et plus
- **Total Points** : 150.5 à 220.5+
- **Prolongation** : Oui/Non

#### Par Période
- **Vainqueur par Quart-Temps** : Q1, Q2, Q3, Q4
- **Vainqueur par Mi-Temps** : 1ère MT, 2ème MT
- **Total points par période**

#### Performance Joueurs
- **Points joueur** : Over/Under
- **Rebonds, Passes, Double-Double, Triple-Double**

## 🧮 Algorithmes Mathématiques

### 1. Distribution de Poisson
Calcule les probabilités de scores exacts basés sur les moyennes de buts/points.

```
P(X=k) = (λ^k * e^(-λ)) / k!
```

### 2. Rating Elo
Évalue la force relative des équipes et prédit les résultats.

```
Probabilité = 1 / (1 + 10^((Elo_B - Elo_A) / 400))
```

### 3. Expected Goals (xG)
Analyse la qualité des occasions de but pour des prédictions plus précises.

### 4. Simulation Monte Carlo
Effectue 10 000+ simulations pour estimer les probabilités de différents résultats.

## 📊 Système de Confiance

Chaque prédiction inclut un **niveau de confiance** (0-100%) calculé par agrégation pondérée de tous les algorithmes :

- **95-100%** : Absolu - Paris ultra-sûrs
- **85-94%** : Très haute - Paris sûrs recommandés
- **70-84%** : Haute - Bons paris
- **50-69%** : Moyenne - À considérer selon les cotes
- **0-49%** : Faible - Éviter

## 💰 Stratégies de Mise

### Critère de Kelly
Calcul optimal de la mise selon :
```
f* = (bp - q) / b
```
où :
- `f*` = fraction de bankroll à miser
- `b` = cote - 1
- `p` = probabilité de victoire
- `q` = probabilité de perte

### Profils de Parieur

#### 🛡️ Conservateur
- Mise : 1% de la bankroll
- Sélection : Confiance ≥ 85%
- Risque : Très faible

#### ⚖️ Équilibré
- Mise : 1-4% selon confiance
- Sélection : Confiance ≥ 70%
- Risque : Moyen

#### 🔥 Agressif
- Mise : 2-7% selon confiance
- Sélection : Value bets (Edge ≥ 5%)
- Risque : Élevé

## 🎲 Combinaisons et Systèmes

### Combinaisons Sûres
Assemblage de paris à haute confiance pour maximiser les gains avec risque minimal.

### Combinaisons Value
Sélection de paris avec edge positif (probabilité prédite > probabilité implicite des cotes).

### Systèmes
- **Trixie** : 4 paris (3 doubles + 1 triplé)
- **Patent** : 7 paris (3 simples + 3 doubles + 1 triplé)
- **Yankee** : 11 paris (6 doubles + 4 triplés + 1 quadruplé)

## 🔧 Installation

### Prérequis
- PHP 8.2+
- PostgreSQL 16+
- Composer
- Symfony CLI (optionnel)

### Installation

```bash
# Cloner le repository
git clone https://github.com/votre-username/nexbet_analytics.git
cd nexbet_analytics/app/symfony

# Installer les dépendances
composer install

# Configurer la base de données
# Modifier le fichier .env avec vos paramètres PostgreSQL
DATABASE_URL="postgresql://user:password@127.0.0.1:5432/nexbet_analytics?serverVersion=16&charset=utf8"

# Créer la base de données
php bin/console doctrine:database:create

# Créer les tables
php bin/console doctrine:migrations:migrate

# Lancer le serveur
symfony server:start
# OU
php -S localhost:8000 -t public
```

Accédez à l'application : `http://localhost:8000`

## 📖 Utilisation

### 1. Tableau de bord
Visualisez les paris sûrs du jour et les matchs à venir.

### 2. Analyse de match
Cliquez sur un match pour obtenir :
- Prédiction 1X2 avec probabilités
- Over/Under toutes lignes
- BTTS
- Score exact le plus probable
- Buteurs potentiels
- Mi-Temps / Fin de Match
- Confrontations directes

### 3. Combinaisons
Générez automatiquement des combinaisons optimales :
- Combinaisons sûres (confiance ≥ 80%)
- Combinaisons value (edge positif)
- Systèmes (Trixie, Patent, Yankee)

### 4. Stratégies
Sélectionnez votre profil et obtenez des recommandations de mise personnalisées.

## ⚙️ Configuration

### Sources de données

Le système peut être configuré pour récupérer les données depuis :
- **Livescore** : Résultats et calendrier
- **Whoscored** : Statistiques avancées (xG, tirs, etc.)
- **Bookmakers** : Cotes (Betclic, Unibet, PMU, etc.)
- **APIs Météo** : Conditions climatiques

> **Note** : Les services de scraping sont des templates et doivent être adaptés selon les structures HTML actuelles des sites. Respectez toujours les Terms of Service.

### Personnalisation

Modifiez les paramètres dans `config/services.yaml` :

```yaml
parameters:
    app.default_bankroll: 1000
    app.max_stake_percentage: 5.0
    app.min_confidence_safe_bet: 85.0
    app.min_edge_value_bet: 5.0
```

## 🏗️ Architecture

```
src/
├── Entity/              # Entités Doctrine (Match, Team, Player, etc.)
├── Repository/          # Repositories personnalisés avec requêtes optimisées
├── Service/
│   ├── Math/           # Services mathématiques (Poisson, Elo, xG, Monte Carlo)
│   ├── Prediction/     # Services de prédiction par type de pari
│   ├── Betting/        # Stratégies, Kelly, Combinaisons, Value Bets
│   └── Data/           # Scraping et agrégation de données
└── Controller/         # Contrôleurs HTTP
```

## 🧪 Tests

```bash
# Lancer les tests
php bin/phpunit

# Tests avec couverture
php bin/phpunit --coverage-html var/coverage
```

## 📈 Bonnes Pratiques

### Gestion de Bankroll
1. ✅ Ne jamais miser plus de 5% sur un seul pari
2. ✅ Utiliser le critère de Kelly (Half-Kelly recommandé)
3. ✅ Réévaluer la bankroll régulièrement
4. ✅ Privilégier les paris à haute confiance pour les grosses mises

### Utilisation des Prédictions
1. ✅ Combiner plusieurs algorithmes (Poisson + Elo + xG + Monte Carlo)
2. ✅ Vérifier la confiance et le niveau de risque
3. ✅ Comparer avec les cotes des bookmakers
4. ✅ Analyser les confrontations directes et la forme récente

### Value Betting
1. ✅ Edge minimum de 5% recommandé
2. ✅ Vérifier la cohérence entre algorithmes
3. ✅ Privilégier les paris avec données solides (échantillon ≥ 10 matchs)

## ⚠️ Avertissement

**Ce système est à but éducatif et informatif uniquement.**

- Les paris sportifs comportent des risques financiers
- Aucune garantie de gain n'est fournie
- Pariez de manière responsable
- Ne misez que ce que vous pouvez vous permettre de perdre
- Les probabilités ne garantissent pas les résultats
    - https://the-odds-api.com/
    - https://www.football-data.org/
    - https://www.api-football.com/
    - https://openweathermap.org/

    admin@nexbet.com
    password

## 📝 Licence

Propriétaire - Tous droits réservés

## 🤝 Contribution

Les contributions sont bienvenues ! Créez une issue ou soumettez une pull request.

## 📧 Contact

Pour toute question : contact@nexbet-analytics.com

---

**Développé avec ❤️ et beaucoup de mathématiques par l'équipe NexBet Analytics**
