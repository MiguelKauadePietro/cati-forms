.DEFAULT_GOAL := help

.PHONY: help dev backend frontend test test-auth lint build migrate migrate-status clear-cache check

help: ## Exibir comandos disponíveis
	@printf 'Uso: make <comando>\n\n'
	@awk 'BEGIN { FS = ":.*## " } /^[a-z-]+:.*## / { printf "  %-16s %s\n", $$1, $$2 }' $(MAKEFILE_LIST)

dev: ## Iniciar o ambiente completo pelo Composer
	@test -x node_modules/.bin/multiplex || { printf '%s\n' 'Dependência local multiplex ausente. Prepare as dependências npm, incluindo as opcionais, antes de executar make dev.' >&2; exit 1; }
	composer dev

backend: ## Iniciar somente o servidor Laravel
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

migrate: ## Aplicar migrations pendentes ao banco configurado
	php artisan migrate

migrate-status: ## Consultar o estado das migrations
	php artisan migrate:status

clear-cache: ## Limpar todos os caches do Laravel
	php artisan optimize:clear

check: ## Executar verificações de CI e build, parando na primeira falha
	composer ci:check
	$(MAKE) build
