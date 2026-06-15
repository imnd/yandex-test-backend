# Используем официальный образ PHP 8.3 CLI на базе Debian Bookworm
FROM php:8.3-cli-bookworm

# Устанавливаем рабочую директорию в контейнере
WORKDIR /var/www

# Устанавливаем системные зависимости для PHP-расширений
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libpq-dev \
    libzip-dev \
    gnupg \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Устанавливаем необходимые PHP-расширения для Laravel и PostgreSQL
RUN docker-php-ext-install pdo pdo_pgsql pgsql zip bcmath opcache

# Копируем Composer из официального образа
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Устанавливаем Node.js 20 и npm (необходимо для работы парсера Playwright)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Копируем исходный код бэкенда в контейнер
COPY . .

# Копируем .env.example в .env, если файл .env не скопирован (для хостинга обычно переопределяется)
RUN [ -f .env ] || cp .env.example .env

# Устанавливаем PHP-зависимости через Composer
RUN composer install --no-interaction --no-dev --optimize-autoloader

# Устанавливаем зависимости парсера и качаем Chromium со всеми его системными библиотеками
RUN cd parser && \
    rm -rf node_modules && \
    npm install && \
    npx playwright install chromium && \
    npx playwright install-deps chromium

# Настраиваем права доступа для директорий кэша и логов Laravel
RUN chmod -R 775 storage bootstrap/cache && \
    chown -R www-data:www-data storage bootstrap/cache

# Экспонируем порт 8000
EXPOSE 8000

# Копируем скрипт точки входа и даем права на запуск
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Задаем точку входа
ENTRYPOINT ["docker-entrypoint.sh"]
