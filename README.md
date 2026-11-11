# CATI Forms

Aplicação web para criar formulários, compartilhar links públicos, coletar respostas
e consultar ou exportar os resultados. Desenvolvida com Laravel 13, Vue 3,
TypeScript, Inertia.js 3 e Vuetify 3, com API REST versionada em `/api/v1`.

## Funcionalidades

- Cadastro com username único, login e logout.
- Criação de formulários com título, descrição e perguntas discursivas, de múltipla
  escolha ou de escolha única com justificativa obrigatória.
- Link público para responder sem cadastro e solicitação opcional de e-mail de contato.
- Validação de todas as perguntas no backend e persistência transacional das respostas.
- Consulta de todos os formulários e respostas por qualquer usuário autenticado.
  O dashboard “Meus formulários” exibe somente os formulários do próprio usuário.
- Exportação de todas as respostas de um formulário para CSV UTF-8 e PDF.

Há um único nível de acesso para usuários cadastrados. Visitantes acessam apenas o
formulário público e enviam respostas; não consultam respostas de outros participantes.
Formulários não podem ser editados nem excluídos após a criação.

## Requisitos

O roteiro abaixo usa **Ubuntu 24.04 LTS e Bash**, com PHP e Node.js na máquina e
**somente o MySQL no Docker**. Em outros sistemas, instale ferramentas equivalentes
nas versões compatíveis antes de seguir os passos do projeto.

| Ferramenta | Versão/configuração                                                                                          |
| ---------- | ------------------------------------------------------------------------------------------------------------ |
| PHP CLI    | **8.3 ou superior, abaixo de 9.0**, conforme `composer.json` e `composer.lock`.                              |
| Composer   | **2.2 ou superior, na série 2**; o lock exige `composer-runtime-api ^2.2`.                                   |
| Node.js    | **24.x a partir de 24.11.0** para este roteiro. O Vite Plus também aceita `^22.18.0` ou `>=26.0.0`.          |
| npm        | Incluído no Node.js; usar `npm ci` com o `package-lock.json` existente. Ambiente verificado com npm 11.19.1. |
| MySQL      | **8.0**, imagem `mysql:8.0` definida em `compose.yaml`.                                                      |
| Docker     | Docker Engine em execução e plugin Compose, com o comando `docker compose`.                                  |
| Outros     | Git, GNU Make, curl e unzip. Acesso à internet para pacotes, imagem Docker e fontes do frontend.             |

Extensões PHP usadas pela aplicação e pelas dependências/testes: `ctype`, `date`,
`dom`, `fileinfo`, `filter`, `hash`, `iconv`, `json`, `libxml`, `mbstring`, `openssl`,
`pcre`, `PDO`, **`pdo_mysql`**, **`pdo_sqlite`**, `Phar`, `Reflection`, `session`,
`SimpleXML`, `tokenizer`, `xml` e `xmlwriter`. Várias já acompanham o PHP CLI.
`curl` e `zip` facilitam a instalação das dependências pelo Composer.
O Pail, iniciado por `make dev`, também exige **`pcntl`**, disponível no PHP CLI
do Ubuntu; confirme com `php -m`. Sem ele, use os dois terminais descritos adiante.

O PDF é gerado pelo Dompdf instalado via Composer, sem serviço externo. Redis,
servidor SMTP e worker de filas não são necessários para a configuração local:
`.env.example` utiliza cache em arquivo, e-mails em log e fila síncrona.

### Preparar uma máquina Ubuntu 24.04

Se essas ferramentas ainda não estiverem instaladas:

```bash
sudo apt update
sudo apt install -y git make curl ca-certificates unzip composer \
    php8.3-cli php8.3-mbstring php8.3-xml php8.3-mysql \
    php8.3-sqlite3 php8.3-curl php8.3-zip
```

Instale o Node.js pelo [nvm](https://github.com/nvm-sh/nvm#installing-and-updating).
Execute como seu usuário, sem `sudo`:

```bash
curl -fsSL https://raw.githubusercontent.com/nvm-sh/nvm/v0.41.0/install.sh -o /tmp/cati-nvm-install.sh
bash /tmp/cati-nvm-install.sh
source ~/.bashrc
nvm install 24
nvm alias default 24
nvm use 24
```

Para uma máquina limpa sem Docker, os pacotes do Ubuntu incluem Engine e Compose:

```bash
sudo apt install -y docker.io docker-compose-v2
sudo systemctl enable --now docker
sudo usermod -aG docker "$USER"
```

Encerre a sessão do sistema e entre novamente para aplicar o grupo `docker`.
O grupo permite administrar o daemon Docker. Se já usa Docker Engine ou Docker
Desktop, mantenha a instalação existente; não misture os pacotes acima com ela.
A instalação pelo repositório oficial também está descrita na
[documentação do Docker](https://docs.docker.com/engine/install/ubuntu/).

Confira as ferramentas antes de continuar:

```bash
php --version
composer --version
node --version
npm --version
docker compose version
docker info
```

## Instalação do projeto

### 1. Clonar o repositório

```bash
git clone https://github.com/MiguelKauadePietro/cati-forms.git
cd cati-forms
```

Execute os próximos comandos na raiz do projeto, com seu usuário normal.

### 2. Configurar os ambientes Laravel e Docker

Em um clone novo:

```bash
cp .env.example .env
cp .env.docker.example .env.docker
```

Edite `.env.docker`. Exemplo **somente para desenvolvimento local**:

```dotenv
MYSQL_ROOT_PASSWORD=senha_root_local_de_exemplo
MYSQL_DATABASE=cati_forms
MYSQL_USER=cati_forms
MYSQL_PASSWORD=senha_local_de_exemplo
MYSQL_PORT=3308
```

Em `.env`, ajuste os campos abaixo e mantenha as demais configurações do exemplo:

```dotenv
APP_NAME="CATI Forms"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3308
DB_DATABASE=cati_forms
DB_USERNAME=cati_forms
DB_PASSWORD=senha_local_de_exemplo
```

Escolha suas próprias senhas e mantenha `DB_PASSWORD` igual a `MYSQL_PASSWORD`.
O Laravel utiliza o usuário `cati_forms`, não o usuário root. `APP_KEY` será
preenchida pelo Artisan adiante. `.env` e `.env.docker` são arquivos locais
ignorados pelo Git; nunca os inclua em commits.

O exemplo Laravel usa originalmente a porta **3306**: para este Compose, altere
`DB_PORT` para **3308**, conforme acima. Como o PHP roda no host, use
`DB_HOST=127.0.0.1`; `db` é o nome do serviço dentro da rede Docker.

### 3. Iniciar e verificar o MySQL

```bash
docker compose --env-file .env.docker up -d db
docker compose --env-file .env.docker ps
docker compose --env-file .env.docker logs --tail=30 db
```

Aguarde a inicialização e a mensagem de que o MySQL está pronto para conexões.
Para verificar o acesso ao banco, informe a senha `MYSQL_PASSWORD` no prompt:

```bash
docker compose --env-file .env.docker exec db mysql -u cati_forms -p cati_forms -e "SELECT 1;"
```

O Compose publica **`127.0.0.1:3308` → porta `3306` do container**. O banco
`cati_forms` e o usuário são criados na primeira inicialização. Os dados ficam no
volume nomeado `mysql_data` do projeto Compose `cati-forms`
(normalmente `cati-forms_mysql_data`), montado em `/var/lib/mysql`.

Use `--env-file .env.docker` em todos os comandos Compose: esse arquivo não é
carregado automaticamente pelo nome. Alterar senhas ou nome do banco no arquivo
não reconfigura um volume que já foi inicializado.

### 4. Instalar dependências e preparar a aplicação

```bash
composer install
composer check-platform-reqs
npm ci --include=optional
php artisan key:generate
php artisan config:clear
php artisan migrate
php artisan migrate:status
npm run build
```

Use os arquivos de lock; não é necessário executar `composer update` nem
`npm update`. Dependências opcionais do npm incluem os executáveis nativos do
Vite Plus e o Multiplex usado pelo ambiente de desenvolvimento.

`migrate` cria/aplica apenas as migrations pendentes no MySQL configurado. Confira
seu `.env` antes de executá-lo. Não use `migrate:fresh`, `migrate:reset` ou remoção
de volumes para instalar em um banco com dados. Não é necessário executar seeders.

O build gera `public/build` e os helpers de rotas do Wayfinder. Esses artefatos
são ignorados pelo Git e devem ser gerados localmente. Mantenha `storage/` e
`bootstrap/cache/` graváveis pelo usuário que executa o PHP.

### 5. Executar e acessar a aplicação

Para iniciar o ambiente de desenvolvimento:

```bash
make dev
```

Esse comando executa `composer dev` e `php artisan dev`, iniciando Laravel, Vite e
os logs do Pail. Com `QUEUE_CONNECTION=sync`, o projeto dispensa o processo de fila.
Mantenha o terminal aberto e acesse **http://localhost:8000**. Cadastre uma conta
em `/register` e faça login para criar formulários. A porta do Vite, normalmente
5173, serve assets e não é o endereço de entrada da aplicação.

Como alternativa, use **dois terminais**, ambos na raiz do projeto:

```bash
# Terminal 1
php artisan serve
```

```bash
# Terminal 2
npm run dev
```

Esses comandos equivalem a `make backend` e `make frontend`. Escolha uma das
formas; não as execute simultaneamente com `make dev`. Encerre com `Ctrl+C`.
Após `npm run build`, também é possível usar somente `php artisan serve` com os
assets compilados, sem atualização automática do frontend. SSR não é necessário
para executar esse fluxo local.

Para parar o banco preservando o volume:

```bash
docker compose --env-file .env.docker stop db
```

## Testes e verificações

O `phpunit.xml` força `APP_ENV=testing`, `DB_CONNECTION=sqlite`,
`DB_DATABASE=:memory:` e `DB_URL` vazia. Os testes Feature usam `RefreshDatabase`.
Antes das migrations de testes, `Tests\Support\DatabaseSafety` recusa ambientes,
conexões ou arquivos de banco que não sejam o SQLite em memória esperado.
**Os testes não utilizam o MySQL de desenvolvimento**, e não precisam dele ligado.
Não altere as configurações de isolamento nem use o banco real como banco de testes.

Para verificar também o texto extraído dos PDFs, instale a ferramenta opcional:

```bash
sudo apt install -y poppler-utils
```

Ela fornece `pdftotext` e não é necessária para gerar ou baixar PDFs. Sem ela,
os testes que dependem dessa extração são marcados como ignorados.

Com as dependências e o build preparados:

```bash
php artisan config:clear
php artisan test
```

Ou execute `make test`. Para os testes específicos de exportação:

```bash
php artisan test tests/Feature/Api/V1/ExportResponsesTest.php
```

| Comando                | Finalidade                                                                             |
| ---------------------- | -------------------------------------------------------------------------------------- |
| `make help`            | Lista os targets do Makefile.                                                          |
| `make test-auth`       | Testes de autenticação.                                                                |
| `make lint`            | Verifica formatação PHP com Pint, sem corrigir.                                        |
| `composer types:check` | Análise estática PHP com PHPStan/Larastan.                                             |
| `npm run types:check`  | Verifica tipos Vue/TypeScript.                                                         |
| `npm run check`        | Verifica formatação e lint do frontend/projeto.                                        |
| `make build`           | Compila o frontend sem SSR.                                                            |
| `make migrate-status`  | Consulta migrations no banco configurado.                                              |
| `make check`           | Verificações de frontend, tipos, Pint, PHPStan e testes; depois build, se tudo passar. |

## Organização e API REST

Controllers e Resources estão em `app/Http`, as operações de criação/exportação
em `app/Actions` e as entidades relacionadas em `app/Models`. Formulários possuem
perguntas e alternativas; cada submissão pertence a um formulário, com respostas
ligadas às perguntas e alternativas escolhidas. A criação utiliza transações.
O frontend Vue/Vuetify usa páginas Inertia e consulta a API na mesma origem.

A API é registrada por `bootstrap/app.php`, com prefixo `/api/v1` e middleware
`web`. A autenticação usa sessão Fortify (`auth:web`), não token Bearer.
Envie `Accept: application/json` para consultar JSON. Requisições POST exigem os
cookies da sessão e o token CSRF, inclusive no envio anônimo. O frontend já faz
esse tratamento. As exportações retornam arquivos com `Content-Disposition:
attachment`, sem envelope JSON.

| Método | Caminho após `/api/v1`                  | Acesso e finalidade                                             |
| ------ | --------------------------------------- | --------------------------------------------------------------- |
| GET    | `/forms`                                | Autenticado: todos os formulários, paginados.                   |
| GET    | `/forms/mine`                           | Autenticado: formulários do usuário atual.                      |
| POST   | `/forms`                                | Autenticado: criar formulário, perguntas e alternativas.        |
| GET    | `/forms/{form}`                         | Autenticado: detalhes de qualquer formulário.                   |
| GET    | `/forms/{form}/submissions`             | Autenticado: respostas recebidas, paginadas.                    |
| GET    | `/forms/{form}/exports/csv`             | Autenticado: todas as respostas em CSV.                         |
| GET    | `/forms/{form}/exports/pdf`             | Autenticado: todas as respostas em PDF.                         |
| GET    | `/public/forms/{public_id}`             | Público: definição do formulário, sem respostas recebidas.      |
| POST   | `/public/forms/{public_id}/submissions` | Público: enviar respostas, com CSRF e limitação de requisições. |

`{form}` é o ID numérico; `{public_id}` é o UUID do link público
`/f/{public_id}`. As consultas autenticadas não são restritas ao criador.
Os contratos detalhados estão em [API v1](docs/api/v1/forms.md), e os fluxos de
login, CSRF e download em [Testes com Postman](docs/testes/postman.md).

## Problemas de instalação e execução

| Sintoma                                                               | Verificação/correção                                                                                                                                                                                                                                          |
| --------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Composer informa PHP/extensão incompatível ou `could not find driver` | Confira o PHP **CLI** com `php --version` e `php -m`; execute `composer check-platform-reqs`. Instale `pdo_mysql` para a aplicação e `pdo_sqlite` para os testes.                                                                                             |
| Node incompatível ou executável nativo/Multiplex ausente              | Confira `node --version`, use `nvm use 24` e execute `npm ci --include=optional`. Não omita as dependências opcionais nem use `--ignore-platform-reqs` no Composer.                                                                                           |
| MySQL: conexão recusada                                               | Confira `docker compose --env-file .env.docker ps` e os logs; aguarde a inicialização. No host, o Laravel usa `127.0.0.1:3308`, não `db:3306`.                                                                                                                |
| Porta 3308 já ocupada                                                 | Escolha outra porta livre em `MYSQL_PORT` e use a mesma em `DB_PORT`; reaplique `docker compose --env-file .env.docker up -d db`.                                                                                                                             |
| MySQL: acesso negado após mudar senhas no arquivo                     | Confira se `DB_PASSWORD` corresponde ao usuário já criado. As variáveis `MYSQL_*` inicializam somente volumes novos; não apague o volume para corrigir credenciais.                                                                                           |
| `.env` alterado, mas a configuração não mudou                         | Execute `php artisan config:clear` e reinicie os processos.                                                                                                                                                                                                   |
| Ausência de chave ou tabela `sessions`                                | Execute `php artisan key:generate` em uma instalação nova e `php artisan migrate` com a conexão correta.                                                                                                                                                      |
| Manifest do Vite ausente ou assets indisponíveis                      | Execute `npm run build` ou mantenha o Vite ativo com `npm run dev`. Acesse Laravel em `http://localhost:8000`. Se o Vite encerrou deixando `public/hot`, inicie-o novamente; para usar só o build, remova esse arquivo gerado enquanto o Vite estiver parado. |
| Erro de sessão/CSRF (419)                                             | Use o mesmo host em `APP_URL` e no navegador; não alterne entre `localhost` e `127.0.0.1`. No Postman, preserve o Cookie Jar e envie o XSRF da mesma sessão.                                                                                                  |
| Aviso sobre pacote opcional `fontaine` no build                       | O build verificado conclui sem esse pacote; o aviso diz respeito à otimização de fontes de fallback. Não é necessário instalá-lo para executar a aplicação.                                                                                                   |
| Pint/PHPStan não conseguem abrir sockets em ambiente restrito         | Verifique em modo serial com `vendor/bin/pint --test` e `vendor/bin/phpstan analyse --debug --no-progress`.                                                                                                                                                   |

## Arquivos da instalação

Os manifests e lockfiles, `.env.example`, `.env.docker.example`, `compose.yaml`,
`Makefile`, configurações, migrations, código-fonte e testes fazem parte da entrega.
`vendor/`, `node_modules/`, helpers gerados, assets compilados, `.env` e
`.env.docker` não precisam estar no Git: são instalados, gerados ou configurados
pelos passos acima. Este roteiro é de execução local; não configura hospedagem de
produção.
