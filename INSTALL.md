# ADOMC_App — Installation guide

ADOMC_App is a Symfony 7.0 multi-objective / multi-criteria decision-support platform
(MOKP + AHP + TOPSIS/VIKOR/PROMETHEE II/ELECTRE I + sensitivity / robustness /
inverse preference learning / scenario comparison).

This document covers both the **developer environment** (Docker Compose) and the
**production deployment** (Nginx + PHP-FPM 8.3 + MySQL 8.0 + Redis 7 + Supervisor
+ Certbot + GitHub Actions).

---

## 1. Requirements

| Tool | Minimum version |
|------|-----------------|
| PHP  | 8.3             |
| Composer | 2.5         |
| MySQL | 8.0            |
| Redis | 7              |
| Node.js | 20 (only for asset compilation, optional) |
| Docker | 24 / Docker Compose v2 |

PHP extensions needed: `intl`, `pdo_mysql`, `sodium`, `zip`, `opcache`, `bcmath`.

---

## 2. Quick start — development (Docker Compose)

```bash
git clone <repo> adomc && cd adomc
cp .env .env.local                 # adjust secrets if needed
docker compose build
docker compose up -d
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction
docker compose exec php php bin/console lexik:jwt:generate-keypair --skip-if-exists
docker compose exec php php bin/console assets:install public
```

The app is now reachable on:

- http://localhost:8080               (web UI, Bootstrap 5)
- http://localhost:8080/api/docs      (Swagger UI for the REST API)
- http://localhost:8080/login         (authentication; fixtures create two users)
- http://localhost:8080/api/auth/token (JWT login endpoint)

### Seeded accounts

| Email               | Password     | Role        |
|---------------------|--------------|-------------|
| admin@adomc.local   | Admin1234!   | ROLE_ADMIN  |
| ong@adomc.local     | User1234!    | ROLE_USER (owner of ONG reference case) |

### Useful commands

```bash
docker compose exec php php bin/console cache:clear
docker compose exec php php bin/console debug:router
docker compose exec php vendor/bin/phpunit --colors=always
docker compose exec php vendor/bin/phpunit --coverage-html var/coverage/html
docker compose exec php vendor/bin/phpstan analyse -l 8 src
docker compose exec php vendor/bin/php-cs-fixer fix
```

---

## 3. Environment variables

| Variable                   | Description                                    |
|----------------------------|------------------------------------------------|
| `APP_ENV`                  | `dev` / `test` / `prod`                        |
| `APP_SECRET`               | Symfony secret (≥ 32 random bytes)             |
| `DATABASE_URL`             | `mysql://user:pwd@host:3306/db`                |
| `REDIS_URL`                | `redis://redis:6379`                           |
| `MAILER_DSN`               | SMTP DSN for notifications                     |
| `JWT_SECRET_KEY`           | Path to the private RSA key (PEM)              |
| `JWT_PUBLIC_KEY`           | Path to the public RSA key (PEM)               |
| `JWT_PASSPHRASE`           | Pass-phrase used when the keypair was generated |
| `MESSENGER_TRANSPORT_DSN`  | Async queue DSN (doctrine:// or redis://)      |

---

## 4. Production deployment (VPS Linux)

### 4.1. Stack

- **Nginx** (TLS termination via Certbot / Let's Encrypt)
- **PHP-FPM 8.3** with OPcache enabled
- **MySQL 8.0** (`innodb_buffer_pool_size=512M`)
- **Redis 7** with AOF persistence (`appendonly yes`)
- **Supervisor** running 2 Messenger workers (see `docker/supervisor/adomc-worker.conf`)
- **GitHub Actions** pipeline: lint → tests → `composer install --no-dev` → migrations → PHP-FPM reload

### 4.2. Server setup

```bash
# 1. System packages
apt install -y nginx mysql-server redis-server supervisor certbot python3-certbot-nginx \
    php8.3-fpm php8.3-cli php8.3-mysql php8.3-redis php8.3-intl php8.3-xml \
    php8.3-mbstring php8.3-curl php8.3-zip php8.3-bcmath php8.3-opcache

# 2. Clone the application
sudo -u www-data git clone <repo> /var/www/adomc && cd /var/www/adomc

# 3. Install production dependencies
sudo -u www-data composer install --no-dev --no-interaction --optimize-autoloader

# 4. Environment (copy and edit)
sudo -u www-data cp .env .env.local
sudo -u www-data nano .env.local    # set APP_ENV=prod, DATABASE_URL, APP_SECRET, MAILER_DSN, etc.

# 5. JWT keys
sudo -u www-data php bin/console lexik:jwt:generate-keypair

# 6. Database
sudo -u www-data php bin/console doctrine:database:create --if-not-exists
sudo -u www-data php bin/console doctrine:migrations:migrate --no-interaction

# 7. Cache
sudo -u www-data php bin/console cache:clear
sudo -u www-data php bin/console cache:warmup
```

### 4.3. Nginx + TLS

```bash
cp docker/nginx/default.conf /etc/nginx/sites-available/adomc
ln -s /etc/nginx/sites-available/adomc /etc/nginx/sites-enabled/adomc
# In the new file: replace `php:9000` with `unix:/run/php/php8.3-fpm.sock`
nginx -t && systemctl reload nginx
certbot --nginx -d adomc.example.com
```

### 4.4. Messenger workers (Supervisor)

```bash
cp docker/supervisor/adomc-worker.conf /etc/supervisor/conf.d/adomc-worker.conf
supervisorctl reread && supervisorctl update && supervisorctl start adomc-worker:*
```

### 4.5. Cron (optional — stale solution cleanup, audit rotation)

```cron
# /etc/cron.d/adomc
0 3 * * * www-data /usr/bin/php /var/www/adomc/bin/console app:audit:rotate --older-than=90d > /dev/null 2>&1
```

---

## 5. Continuous integration

See `.github/workflows/ci.yml` for the GitHub Actions pipeline:

1. `composer validate` + PHP-CS-Fixer (`--dry-run`)
2. PHPStan (level 8)
3. PHPUnit — unit + integration tests, with coverage ≥ 85 % on calculation services
4. Behat (10 business scenarios, JUnit output)
5. On merge into `main`: build artefact, push to VPS, run migrations, reload PHP-FPM

---

## 6. Reference data — Humanitarian NGO case

Loaded by `App\DataFixtures\HumanitarianOngFixtures`. The expected numerical
invariants enforced by the PHPUnit test-suite are:

- Vector normalization on **f₁**: √(9²+10²+8²+6²+7²+9²+5²+6²) = **√472 ≈ 21.732**
- Medicines on f₁ → **10 / 21.732 ≈ 0.4603**
- Radio on f₁ → **5 / 21.732 ≈ 0.2301**
- AHP reference matrix ⇒ **CR < 0.10**, weights ≈ **[0.50, 0.30, 0.20]**
- ε-constraint ⇒ **≥ 5 non-dominated solutions** on the 8-item / 30-kg problem
- TOPSIS top-1 **stable** for λ₁ ∈ **[0.40, 0.65]** (sensitivity regression)

Run the regression suite with:

```bash
vendor/bin/phpunit --testsuite unit
vendor/bin/phpunit --coverage-html var/coverage/html
```

---

## 7. API access

```bash
# 1. Obtain a JWT
curl -X POST -H "Content-Type: application/json" \
     -d '{"email":"ong@adomc.local","password":"User1234!"}' \
     http://localhost:8080/api/auth/token

# 2. Use the token
curl -H "Authorization: Bearer <TOKEN>" http://localhost:8080/api/projects
```

Rate limit: **60 req/min per IP** on `/api/*` (enforced by `ApiRateLimitListener`).
Login brute-force: **10 attempts ⇒ 15 min lockout** (enforced by Symfony’s login rate limiter).

---

## 8. Troubleshooting

| Symptom                                       | Fix                                           |
|-----------------------------------------------|-----------------------------------------------|
| `Unable to find JWT key`                      | Run `php bin/console lexik:jwt:generate-keypair` |
| `SQLSTATE[HY000] [2002] Connection refused`   | `docker compose up -d mysql` and wait for health |
| 403 on `/api/projects`                        | Include `Authorization: Bearer <JWT>` header  |
| CSP warnings in the browser console           | Inline scripts are forbidden — use `public/js` |
| `Argon2id` missing                            | Ensure PHP was built with `--with-sodium`     |
