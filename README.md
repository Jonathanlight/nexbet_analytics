# nexbet_analytics
NEXBET - Plateforme d'Intelligence Prédictive pour Paris Sportifs
Nexbet est une plateforme SaaS professionnelle d'analyse prédictive et d'aide à la décision pour les paris sportifs. Utilisant des modèles mathématiques avancés (Poisson, ELO, Machine Learning, Monte Carlo) et l'agrégation massive de données en temps réel, Nexbet analyse plus de 150 variables par match pour générer des prédictions ultra-précises avec scores de confiance. L'application détecte automatiquement les "value bets", optimise les mises selon le profil utilisateur (Kelly Criterion), et propose des combinaisons rentables avec calculs ROI. Couvrant Football et Basketball avec 45+ types de paris, Nexbet transforme les paris sportifs en décisions data-driven.

### Requirements
---

- PHP 8.3
- Symfony 7.4
- Apache 2.4
- MySQL 5.7
- Composer 2

### Usage
---

### Installation
---

```
git clone git@github.com:Jonathanlight/nexbet_analytics.git
$ cd nexbet_analytics

# or start docker containers
$ make docker-run

# install dependencies
$ make docker-exec apache bash
$ composer install

# create migrations
$ make migrate

# load fixtures
$ make fixtures

server running on http://localhost:8000
```

### Installation SSl
---
```
cd docker/etc/apache/ssl/

openssl req -x509 -out server.crt -keyout server.key \
-newkey rsa:2048 -nodes -sha256 \
-subj '/CN=localhost' -extensions EXT -config <( \
printf "[dn]\nCN=localhost\n[req]\ndistinguished_name = dn\n[EXT]\nsubjectAltName=DNS:localhost\nkeyUsage=digitalSignature\nextendedKeyUsage=serverAuth")
```

### Configuration
---

### Pipeline
---

```yaml
make pre-push # load all test quality
make fixtures # load fixtures
make quality # run quality checks
make translations-lint # check translations
make phpunit # run unit tests
make format-twig # reindent template twig
```

### Authors
---

- Jonathan 