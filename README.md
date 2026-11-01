# CATI Forms

Plataforma de criação, compartilhamento e coleta de respostas de formulários,
com Laravel, Vue 3, TypeScript, Inertia.js e Vuetify 3.

## Pré-requisitos

- Ubuntu com GNU Make, PHP compatível com `^8.3` e Composer 2.
- Extensões PHP exigidas pelo Composer, incluindo `pdo_mysql` para o ambiente
  principal e `pdo_sqlite` para os testes.
- Node.js compatível com o Vite Plus instalado: `^22.18.0`, `^24.11.0` ou
  `>=26.0.0`, e npm.
- MySQL disponível para o ambiente de desenvolvimento.
- Dependências PHP e JavaScript previamente instaladas conforme os arquivos de
  lock. A preparação manual pode usar `composer install` e
  `npm ci --include=optional`; o ambiente completo depende do executável local
  `@laravel/multiplex`.
- `.env` preparado localmente, com chave da aplicação e conexão ao banco
  configuradas. Não versione credenciais. O Makefile não cria nem sobrescreve
  esse arquivo.

Prepare o banco com `make migrate` quando necessário, antes de iniciar o ambiente.
Esse comando altera o banco configurado; confirme o ambiente antes de executá-lo.

## Comandos

Execute os comandos na raiz do projeto. `make` sem argumentos equivale a
`make help`.

| Comando               | Descrição                                                                                                        |
| --------------------- | ---------------------------------------------------------------------------------------------------------------- |
| `make help`           | Lista os comandos e suas descrições.                                                                             |
| `make dev`            | Executa `composer dev`: servidor Laravel, processos de desenvolvimento e Vite conforme a configuração existente. |
| `make backend`        | Inicia somente o servidor Laravel com `php artisan serve`.                                                       |
| `make frontend`       | Inicia somente o Vite com `npm run dev`.                                                                         |
| `make test`           | Executa a suíte Pest pelo comando de testes do Laravel.                                                          |
| `make test-auth`      | Executa os testes em `tests/Feature/Auth`.                                                                       |
| `make lint`           | Executa `composer lint:check`, verificando Pint sem corrigir arquivos.                                           |
| `make build`          | Compila o frontend com `npm run build`.                                                                          |
| `make migrate`        | Aplica migrations pendentes ao banco configurado.                                                                |
| `make migrate-status` | Consulta o estado das migrations.                                                                                |
| `make check`          | Executa `composer ci:check` e, se passar, `make build`.                                                          |

Para iniciar os processos separadamente, execute `make backend` e
`make frontend` em terminais distintos. Encerre os processos com `Ctrl+C`.
Não combine essa opção com `make dev` para evitar processos duplicados.

Nenhum target instala dependências automaticamente. `dev` verifica a presença
do Multiplex local para evitar sua obtenção implícita pelo comando do Laravel.
`dev` e `check` não aplicam migrations ao banco principal. O Makefile não usa
`sudo`, operações de limpeza do banco ou o script `composer setup`.

## Testes e qualidade

O PHPUnit configura SQLite em memória, e os testes Feature usam
`RefreshDatabase`. A proteção existente interrompe os testes antes das migrations
se a configuração efetiva não for a de testes com SQLite em memória. Essas
migrations pertencem somente ao banco efêmero de testes.

`check` reutiliza o fluxo de CI existente: verificação de frontend, TypeScript,
Pint, análise estática PHP e testes; depois executa o build. A primeira falha
interrompe o fluxo, inclusive quando `make check` recebe `-j`. O script Composer
existente limpa o cache de configuração antes das verificações PHP. O build pode
gerar assets e contratos de rotas pelo Wayfinder.

Há uma pendência preexistente de formatação em `skills-lock.json`, que pode
interromper `make check`. Ela não é corrigida automaticamente pelo Makefile.
Em ambientes restritos que bloqueiam sockets locais, o Pint paralelo usado pelo
script Composer pode falhar; a verificação serial equivalente é
`vendor/bin/pint --test`. A análise PHP paralela pode ter a mesma limitação;
`vendor/bin/phpstan analyse --debug --no-progress` permite verificá-la em modo
serial.

Para inspecionar receitas sem executar servidores ou migrations, use, por
exemplo, `make -n dev`, `make -n migrate` ou `make -n check`.
