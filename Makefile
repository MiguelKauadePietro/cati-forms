.DEFAULT_GOAL := help

.PHONY: help db-up db-down dev backend frontend test test-auth lint build migrate migrate-status clear-cache check

DOCKER_COMPOSE = docker compose --env-file .env.docker

help: ## Exibir comandos disponíveis
	@printf 'Uso: make <comando>\n\n'
	@awk 'BEGIN { FS = ":.*## " } /^[a-z-]+:.*## / { printf "  %-16s %s\n", $$1, $$2 }' $(MAKEFILE_LIST)

db-up: ## Iniciar o MySQL e aguardar conexão autenticada (até 30 tentativas)
	$(DOCKER_COMPOSE) up -d db
	@attempt=0; until $(DOCKER_COMPOSE) exec -T db sh -c 'MYSQL_PWD="$$MYSQL_PASSWORD" mysql --protocol=TCP --host=127.0.0.1 --user="$$MYSQL_USER" --database="$$MYSQL_DATABASE" --connect-timeout=2 --execute="SELECT 1" >/dev/null 2>&1'; do \
		attempt=$$((attempt + 1)); \
		if [ "$$attempt" -ge 30 ]; then \
			printf '%s\n' 'MySQL não ficou pronto. Confira os logs do serviço db e as credenciais do volume existente.' >&2; \
			exit 1; \
		fi; \
		sleep 2; \
	done

db-down: ## Parar o MySQL preservando o container e o volume de dados
	$(DOCKER_COMPOSE) stop db

dev: db-up ## Iniciar MySQL, Laravel, logs e Vite pelo Composer
	@test -x node_modules/.bin/multiplex || { printf '%s\n' 'Dependência local multiplex ausente. Prepare as dependências npm, incluindo as opcionais, antes de executar make dev.' >&2; exit 1; }
	composer dev

backend: db-up ## Aguardar MySQL e iniciar somente o servidor Laravel
	php artisan serve

frontend: ## Iniciar somente o Vite
	npm run dev

test: ## Executar a suíte Pest
	php artisan test

test-auth: ## Executar os testes de autenticação
	php artisan test tests/Feature/Auth

lint: ## Verificar formatação PHP sem modificar arquivos
	composer lint:check

build: ## Compilar o frontend
	npm run build

migrate: db-up ## Aguardar MySQL e aplicar migrations pendentes
	php artisan migrate

migrate-status: db-up ## Aguardar MySQL e consultar o estado das migrations
	php artisan migrate:status

clear-cache: ## Limpar todos os caches do Laravel
	php artisan optimize:clear

check: ## Executar verificações de CI e build, parando na primeira falha
	composer ci:check
	$(MAKE) build
