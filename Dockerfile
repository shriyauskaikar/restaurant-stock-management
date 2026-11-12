# syntax=docker/dockerfile:1
#
# Dockerfile for the Restaurant Food Stock Management System
# ---------------------------------------------------------------------
# Vercel does not have a built-in PHP runtime, so the app is deployed
# with Vercel's Container runtime using FrankenPHP (PHP + Caddy in one).
#
# The project has no Composer dependencies, so there is no "composer
# install" step - the whole app is simply copied into the image.

FROM dunglas/frankenphp:1-php8.4-alpine

WORKDIR /app

# The base image does not ship the MySQL driver, so add it.
# (The check makes the build work whether or not it is already present.)
RUN if ! php -m | grep -qi '^pdo_mysql$'; then install-php-extensions pdo_mysql; fi \
    && php -m | grep -qi '^pdo_mysql$'

# Copy the application code into the image.
COPY . /app

# Caddy is the web server that serves the pages.
COPY Caddyfile /etc/caddy/Caddyfile

# Vercel sends traffic to this port.
ENV PORT=80

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
