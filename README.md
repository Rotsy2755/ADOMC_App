ADOMC
=====

*Aide à la Décision Multi-Objectifs et Multi-Critères.*

ADOMC est une plate-forme d'aide à la décision destinée aux organisations
humanitaires. Elle prend en entrée un problème multi-objectifs discret
(MOKP — **Multi-Objective Knapsack Problem**) et restitue un classement
robuste des alternatives en combinant :

- l'extraction du **front de Pareto** (NSGA-II, ε-constraint),
- la pondération des objectifs par **AHP** (Analytic Hierarchy Process),
- le classement des alternatives par plusieurs méthodes **MCDM**
  (TOPSIS, VIKOR, PROMETHEE II, ELECTRE I, somme pondérée),
- des **analyses de sensibilité** et de **robustesse** (Monte-Carlo),
- la **comparaison de scénarios** et l'**apprentissage inverse** de
  préférences à partir de choix passés.

Le projet a été conçu comme outil de terrain pour les ONG de développement
(eau, santé, logistique humanitaire) où la décision doit rester
traçable, auditable et partageable avec les parties prenantes.


Sommaire
--------

1. [Fonctionnalités](#fonctionnalités)
2. [Pile technique](#pile-technique)
3. [Démarrage rapide](#démarrage-rapide)
4. [Comptes d'amorçage](#comptes-damorçage)
5. [Architecture du dépôt](#architecture-du-dépôt)
6. [Tests et qualité](#tests-et-qualité)
7. [Déploiement](#déploiement)
8. [Licence](#licence)


Fonctionnalités
---------------

- Gestion multi-projets, multi-problèmes, multi-utilisateurs.
- Saisie de matrices de comparaison par paires avec contrôle de cohérence
  (CR < 0,10) et calcul automatique du vecteur de poids par moyenne
  géométrique.
- Calcul asynchrone du front de Pareto (Symfony Messenger) et polling
  de progression côté client.
- Visualisations interactives : nuage Pareto 2D/3D (Plotly.js),
  histogrammes de sensibilité (Chart.js).
- Export des résultats : **PDF** (Dompdf), **CSV**, **JSON**.
- Partage en lecture seule via **liens tokenisés** (expiration + révocation).
- **Journal d'audit** horodaté sur toutes les actions critiques.
- API REST (API Platform 3) avec authentification **JWT** pour
  l'intégration à des SI tiers.


Pile technique
--------------

| Couche       | Outils                                                        |
|--------------|---------------------------------------------------------------|
| Langage      | PHP 8.3                                                       |
| Framework    | Symfony 7.1, API Platform 3                                   |
| Persistance  | Doctrine ORM 2.x, MySQL 8.0                                   |
| Asynchrone   | Symfony Messenger (transport Doctrine ou Redis)               |
| Cache        | Adaptateur filesystem (dev), Redis (prod, optionnel)          |
| Sécurité     | Argon2id, LexikJWTAuthenticationBundle, RateLimiter           |
| Front        | Twig, Bootstrap 5, Plotly.js, Chart.js, Font Awesome          |
| Tests        | PHPUnit 10, Behat, PHPStan niveau 8, PHP-CS-Fixer             |
| Conteneurs   | Docker Compose (dev), Nginx + PHP-FPM + Supervisor (prod)     |


Démarrage rapide
----------------

### Option A — Environnement natif (Windows / WampServer)

```powershell
# Pré-requis : PHP 8.3 CLI, Composer 2, WampServer (MySQL 8) en cours d'exécution
composer install

# 1. Variables locales
copy .env .env.local
# puis ajustez DATABASE_URL et JWT_PASSPHRASE dans .env.local

# 2. Paire de clés JWT (sans dépendance au binaire openssl)
php bin/gen-jwt.php

# 3. Base de données
& "C:\wamp64\bin\mysql\mysql8.3.0\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS mokp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:fixtures:load --no-interaction

# 4. Serveur de développement
php -d memory_limit=512M -d max_execution_time=0 -S 127.0.0.1:8000 -t public
```

Vous pouvez aussi simplement double-cliquer sur **`serve.bat`** à la racine du projet :
le script pré-positionne `memory_limit=512M`, `max_execution_time=0` et
`default_socket_timeout=120` afin d'éviter les timeouts du compilateur de cache
de Symfony lors du premier démarrage.

L'application est alors disponible sur <http://127.0.0.1:8000>.

### Option B — Docker Compose

```bash
cp .env .env.local
docker compose build
docker compose up -d
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction
docker compose exec php php bin/console lexik:jwt:generate-keypair --skip-if-exists
```

Le détail du déploiement en production (Nginx + Certbot + Supervisor + GitHub
Actions) est décrit dans [`INSTALL.md`](INSTALL.md).


Comptes d'amorçage
------------------

Les fixtures (`doctrine:fixtures:load`) créent deux comptes prêts à l'emploi
pour tester immédiatement l'interface :

| Rôle                 | Courriel              | Mot de passe  |
|----------------------|-----------------------|---------------|
| Administrateur       | `admin@adomc.local`   | `Admin1234!`  |
| Décideur (ONG)       | `ong@adomc.local`     | `User1234!`   |

> Ces identifiants sont destinés à l'environnement de démonstration.
> Changez-les, ou désactivez les fixtures, avant toute mise en production.


Architecture du dépôt
---------------------

```
src/
├── ApiPlatform/          # Extensions / filtres / providers API Platform
├── ApiResource/          # DTO exposés par l'API
├── Controller/           # Contrôleurs HTTP (web + API)
├── DataFixtures/         # Jeux de données d'amorçage
├── Entity/               # Modèle Doctrine (Project, Problem, Item, ...)
├── EventListener/        # Limitation de débit, audit, CORS
├── Message/              # Messages asynchrones (calcul Pareto, sensibilité)
├── MessageHandler/       # Handlers Messenger correspondants
├── Repository/           # Requêtes Doctrine dédiées
├── Security/             # Voters, authenticators
└── Service/              # AHP, MCDM, Pareto, Sensitivity, Export, Audit...
config/
├── packages/             # Bundles (doctrine, security, messenger, ...)
├── jwt/                  # Clés RSA (hors Git)
└── routes/               # Définitions de routes
templates/                # Twig (dashboard, AHP, MCDM, Pareto, exports)
tests/                    # PHPUnit unitaires + fonctionnels
migrations/               # Doctrine Migrations versionnées
```


Tests et qualité
----------------

```bash
vendor/bin/phpunit                          # tests unitaires et fonctionnels
vendor/bin/phpstan analyse src --level 8    # analyse statique
vendor/bin/php-cs-fixer fix --dry-run --diff
vendor/bin/behat                            # scénarios d'acceptation
```

La politique de branches et les critères de revue de code sont décrits
dans `docs/` (branche `main` protégée, PR obligatoire, couverture PHPUnit
minimale 80 % sur `src/Service`).


Déploiement
-----------

Le déploiement de référence est un VPS Linux équipé de :

- Nginx (reverse-proxy + TLS Let's Encrypt via Certbot),
- PHP-FPM 8.3 avec opcache / JIT activés,
- MySQL 8.0, Redis 7,
- Supervisor pour les workers `messenger:consume`,
- GitHub Actions pour l'intégration continue et le déploiement par SSH.

Tous les fichiers associés se trouvent dans `docker/`, `docker-compose.yml`,
et `INSTALL.md`.


Licence
-------

Distribué sous licence **MIT**. Voir le fichier [`LICENSE`](LICENSE).

---

*ADOMC — version 1.0.0 — contact : `tahiriniaina.rotsy@adomc.local`*
