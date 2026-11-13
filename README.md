# CATI Forms

Plataforma para criar formulários, coletar respostas por link público e consultar
ou exportar resultados em CSV e PDF. Utiliza Laravel 13, PHP, MySQL, Vue 3,
TypeScript, Inertia.js 3, Vuetify 3, Vite Plus e Dompdf.

## Pré-requisitos

O roteiro usa Ubuntu 24.04 e Bash. PHP e Node.js executam no host; somente o
MySQL executa no Docker.

- PHP **8.3–8.x**, Composer **2.2+ da série 2** e extensões padrão do PHP CLI,
  `mbstring`, DOM/XML, `pdo_mysql` e `pdo_sqlite`. O Pail usado por `make dev` exige `pcntl`.
- Node.js **24.11+ da série 24**, com npm. Também são aceitos `^22.18.0` ou `>=26.0.0` pelo Vite Plus.
- Docker Engine ativo e Docker Compose com o comando `docker compose`.
- Git, GNU Make, curl, unzip e internet para dependências, fontes e imagem MySQL **8.0**.

Em uma máquina Ubuntu limpa, instale PHP e ferramentas básicas:

```bash
sudo apt update
sudo apt install -y git make curl ca-certificates unzip composer \
    php8.3-cli php8.3-mbstring php8.3-xml php8.3-mysql \
    php8.3-sqlite3 php8.3-curl php8.3-zip
```

Instale Node.js pelo nvm, como usuário normal:

```bash
curl -fsSL https://raw.githubusercontent.com/nvm-sh/nvm/v0.41.0/install.sh -o /tmp/cati-nvm-install.sh
bash /tmp/cati-nvm-install.sh
source ~/.bashrc
nvm install 24
nvm alias default 24
nvm use 24
```

Se o Docker ainda não estiver instalado, use os pacotes do Ubuntu:

```bash
sudo apt install -y docker.io docker-compose-v2
sudo systemctl enable --now docker
sudo usermod -aG docker "$USER"
```

Saia da sessão do sistema e entre novamente para aplicar o grupo. Confira
`docker info` e `docker compose version` sem sudo. Se já utiliza Docker Engine
ou Desktop, preserve sua instalação existente.

## Instalação

### 1. Clonar e configurar

```bash
git clone https://github.com/MiguelKauadePietro/cati-forms.git
cd cati-forms
cp .env.example .env
cp .env.docker.example .env.docker
```

Edite `.env.docker`, substituindo as senhas de exemplo por senhas locais próprias:

```dotenv
MYSQL_ROOT_PASSWORD=senha_root_local_de_exemplo
MYSQL_DATABASE=cati_forms
MYSQL_USER=cati_forms
MYSQL_PASSWORD=senha_local_de_exemplo
MYSQL_PORT=3308
```

Em `.env`, ajuste os campos abaixo e preserve as demais configurações do template:

```dotenv
APP_NAME="CATI Forms"
APP_URL=http://localhost:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3308
DB_DATABASE=cati_forms
DB_USERNAME=cati_forms
DB_PASSWORD=senha_local_de_exemplo
```

`DB_PASSWORD` deve corresponder a `MYSQL_PASSWORD`. A porta do template Laravel
é 3306; altere-a para **3308**, conforme o Compose. O host é `127.0.0.1`, pois o
PHP executa fora do container. Não versione `.env` nem `.env.docker`.

### 2. Instalar dependências e preparar o banco

```bash
composer install
composer check-platform-reqs
npm ci --include=optional
php artisan key:generate
php artisan config:clear
make db-up
make migrate
make migrate-status
make build
```

`db-up` inicia o MySQL e aguarda uma consulta autenticada bem-sucedida.
`migrate` e `migrate-status` também iniciam/aguardam o banco automaticamente.
Não é necessário executar seeders. Use os lockfiles existentes; não execute
`composer update` ou `npm update` para instalar.

O Compose publica `127.0.0.1:3308` → `3306` e mantém os dados no volume
`mysql_data`, montado em `/var/lib/mysql`. Alterar senhas no arquivo não altera
usuários de um volume já inicializado. Não remova volumes nem recrie o banco
para corrigir credenciais. Para investigar a inicialização:

```bash
docker compose --env-file .env.docker logs --tail=30 db
```

O build gera assets e helpers Wayfinder ignorados pelo Git. `storage/` e
`bootstrap/cache/` precisam ser graváveis pelo usuário que executa o PHP.

### 3. Executar

```bash
make dev
```

O comando garante que o MySQL esteja pronto e inicia Laravel, Vite e logs pelo
Composer, sem iniciar um segundo conjunto de servidores. Acesse
**http://localhost:8000** e cadastre uma conta em `/register`. Encerre os processos
com `Ctrl+C`; o banco continua disponível até executar `make db-down`.

Alternativamente, execute `make backend` e `make frontend` em terminais separados.
Não combine essa alternativa com `make dev`. Redis, SMTP e worker de fila não
são necessários na configuração padrão (cache em arquivo e fila síncrona).

## Comandos principais

| Comando                                | Finalidade                                                      |
| -------------------------------------- | --------------------------------------------------------------- |
| `make help`                            | Listar todos os comandos.                                       |
| `make db-up`                           | Iniciar MySQL e aguardar acesso autenticado ao banco.           |
| `make db-down`                         | Parar MySQL preservando container e volume.                     |
| `make dev`                             | Iniciar banco e ambiente completo.                              |
| `make backend` / `make frontend`       | Executar Laravel e Vite separadamente; backend aguarda o banco. |
| `make migrate` / `make migrate-status` | Aplicar ou consultar migrations após aguardar MySQL.            |
| `make build`                           | Compilar frontend sem SSR.                                      |
| `make test` / `make test-auth`         | Executar testes completos ou de autenticação.                   |
| `make lint`                            | Verificar formatação PHP.                                       |
| `make check`                           | Executar verificações de qualidade, testes e build.             |

Os testes usam SQLite **em memória**, com proteção contra uso do MySQL real.
Execute `php artisan config:clear` antes dos testes se houver configuração em
cache. Opcionalmente, instale `poppler-utils` para os testes de extração de texto
PDF; sem `pdftotext`, esses casos são marcados como ignorados.
