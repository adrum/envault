# syntax=docker/dockerfile:1.7

# ----- Stage 1: PHP dependencies -----
FROM composer:2 AS vendor
WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

# ----- Stage 2: Generate wayfinder + typescript artifacts -----
FROM dunglas/frankenphp:1-php8.4-alpine AS codegen
WORKDIR /app

RUN install-php-extensions bcmath intl pcntl pdo_mysql redis sodium zip

COPY --from=vendor /app/vendor ./vendor
COPY . .

RUN cp .env.example .env \
    && php artisan key:generate --force \
    && php artisan wayfinder:generate --with-form --path=resources/js/wayfinder \
    && php artisan typescript:enums || true

# ----- Stage 3: Frontend build -----
FROM node:22-alpine AS frontend
WORKDIR /app

ENV DOCKER=1

RUN corepack enable

COPY package.json pnpm-lock.yaml ./
RUN --mount=type=cache,target=/root/.local/share/pnpm/store \
    pnpm install --frozen-lockfile

COPY resources ./resources
COPY public ./public
COPY vite.config.ts tsconfig.json ./
COPY routes ./routes
COPY app ./app
COPY --from=codegen /app/resources/js/wayfinder ./resources/js/wayfinder

RUN pnpm run build

# ----- Stage 4: Runtime -----
FROM dunglas/frankenphp:1-php8.4-alpine AS runtime

ENV SERVER_NAME=:80 \
    APP_ENV=production \
    APP_DEBUG=false \
    OCTANE_SERVER=frankenphp

RUN apk add --no-cache \
        git \
        unzip \
        icu-data-full \
        libzip \
        supervisor \
    && install-php-extensions \
        bcmath \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        redis \
        sodium \
        zip

WORKDIR /app

COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build
COPY . .

RUN cp .env.example .env \
    && php artisan key:generate --force \
    && php artisan storage:link \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

RUN mkdir -p /etc/supervisor/conf.d && cat > /etc/supervisor/conf.d/app.conf <<'EOF'
[supervisord]
nodaemon=true
user=root
logfile=/dev/null
logfile_maxbytes=0
pidfile=/run/supervisord.pid

[program:frankenphp]
command=frankenphp run --config /etc/frankenphp/Caddyfile
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
stopsignal=SIGTERM
stopwaitsecs=30

[program:scheduler]
command=php /app/artisan schedule:work
autostart=true
autorestart=true
user=www-data
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:queue]
command=php /app/artisan queue:work --sleep=3 --tries=3 --timeout=60 --max-time=3600
autostart=true
autorestart=true
user=www-data
stopwaitsecs=3600
stopsignal=SIGTERM
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
EOF

EXPOSE 80 443 443/udp

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s \
    CMD wget -qO- http://localhost/up || exit 1

CMD ["supervisord", "-c", "/etc/supervisor/conf.d/app.conf", "-n"]
