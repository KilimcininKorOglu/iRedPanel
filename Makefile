PHP      ?= php
COMPOSER ?= $(PHP) composer.phar
PHPUNIT  ?= vendor/bin/phpunit --do-not-cache-result
NETWORK  := iredpanel
DEV      := docker compose -f docker-compose.dev.yml
PROD     := docker compose -f docker-compose.prod.yml

.PHONY: install test lint locale-parity network dev-up dev-down dev-logs prod-build prod-up prod-down prod-logs

install:
	$(COMPOSER) install

test:
	$(PHPUNIT)

lint:
	find . -name "*.php" ! -path "./vendor/*" ! -path "./docker-iredmail-*" -print0 | xargs -0 -n1 -P4 $(PHP) -l

locale-parity:
	$(PHP) scripts/check_locale_parity.php

# Shared network between the panel and the docker-iredmail-* backend stacks.
network:
	docker network inspect $(NETWORK) >/dev/null 2>&1 || docker network create $(NETWORK)

# Pick the panel env file with ENV, e.g. make dev-up ENV=.env.docker-pgsql
dev-up: network
	IREDPANEL_ENV_FILE=$(or $(ENV),.env) $(DEV) up -d --build --force-recreate

dev-down:
	$(DEV) down

dev-logs:
	$(DEV) logs -f

prod-build:
	$(PROD) build

prod-up: network
	$(PROD) up -d --build

prod-down:
	$(PROD) down

prod-logs:
	$(PROD) logs -f
