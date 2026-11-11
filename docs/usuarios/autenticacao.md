# RF01–RF02 — Autenticação do CATI Forms

## Requisitos e contrato

Fonte: documento Banco de Talentos CATI Jr. 2026, RF01–RF02 e RN01–RN03.
Cadastro exige username único, senha e confirmação; e-mail não é exigido.
Existe um único tipo de usuário. O acesso privado usa auth, sem verified, papéis
ou restrições por autoria. A criação já está implementada em RF03–RF04; ver [formulários](../formularios/criacao.md).

Fortify mantém os endpoints GET/POST /register, GET/POST /login e POST /logout.
No fluxo web, cadastro e login encaminham ao dashboard. Com Accept JSON,
POST /register retorna 201 com string JSON vazia, POST /login retorna 200 com
{"two_factor": false}, e POST /logout retorna 204 sem corpo. Esse campo da
resposta nativa do Fortify não habilita 2FA. O [guia Postman](../testes/postman.md)
centraliza os exemplos e o preparo automático de sessão/CSRF. Usuários autenticados
podem acessar as configurações existentes. Recuperação/verificação de e-mail,
2FA e passkeys ficam desativados; seus endpoints e descoberta pública não existem.
Login de contas com dados antigos de 2FA usa somente username e senha.

Username é obrigatório, string de até 255 caracteres, normalizado com trim e
minúsculas, com validação de unicidade e índice único no banco. Não há regra
adicional de caracteres. Senhas usam a política já configurada pelo Laravel:
Password::default e confirmed; em produção, a configuração existente exige 12
caracteres, maiúsculas/minúsculas, números, símbolos e verificação de vazamentos.
A checagem de vazamentos depende de comunicação externa em produção.
CreateNewUser é o ponto de extensão do Fortify para validação e criação, sem
controller ou camada de serviços redundantes. O model usa cast hashed e oculta
senha e remember_token na serialização atual.

## Sessões e proteção

O guard web usa sessão e os endpoints mantêm o middleware web e CSRF do Laravel.
Fortify renova o identificador da sessão no login. Logout remove a autenticação,
invalida a sessão e renova o token CSRF. O login tem cinco tentativas por minuto
por username normalizado e IP. Erros de credenciais não distinguem usuário
inexistente de senha incorreta. A configuração de cookies existente mantém
HttpOnly e SameSite; HTTPS e Secure devem corresponder ao ambiente de produção.
Nenhum valor de credencial ou conteúdo do .env foi documentado ou alterado.

## Banco e implantação

A migration incremental de username preencheu contas legadas com user_{id}.
A limpeza posterior aprovada removeu campos de e-mail e 2FA e as tabelas de
passkeys e recuperação. Nome, username, hash e remember_token foram preservados.
O [registro do banco](../banco-de-dados/estrutura.md) concentra o histórico.
Não deduzir o estado atual das migrations do MySQL a partir de uma nota histórica.

Os testes usam SQLite :memory:, RefreshDatabase e uma proteção que rejeita outras
configurações antes das migrations. Não executar fresh/wipe/rollback no banco
principal para experimentar autenticação.

## Interface e dependências

Login.vue e Register.vue usam Vue 3, TypeScript, useForm do Inertia e componentes
Vuetify 3. AuthVuetifyLayout atende somente essas páginas; confirmação de senha
e configurações permanecem no layout legado. As novas telas não importam Reka
ou shadcn. Campos têm rótulos, autocomplete e mensagens de erro do servidor;
o envio tem indicador de carregamento, bloqueio de duplicação e limpeza de
senhas ao terminar. Erros e envio são anunciados por aria-live.

Foram removidos os componentes específicos de 2FA/passkeys, o composable de
segundo fator, imports de actions geradas, o request específico e os métodos de
passkey do model. Dependências instaladas e infraestrutura legada não relacionada
não foram removidas. Vite/Wayfinder regeneram os contratos de rotas no build.

## Testes e validação histórica da entrega RF01–RF02

Pest cobre cadastro, unicidade e normalização, validação/hash de senha, login
válido/inválido, rate limiting, sessão renovada, logout, rotas privadas,
inexistência dos endpoints fora do escopo, migração legada e isolamento SQLite.
A suíte passou com 54 testes e 236 asserções, sem testes ignorados.

Comandos: vendor/bin/pest --compact, vendor/bin/pint --test,
npm run types:check, vendor/bin/phpstan analyse --debug --no-progress,
vp lint, npm run build e npm run build:ssr. O flag --debug evita sockets do
paralelismo do PHPStan bloqueados no sandbox. Pint usa modo serial pelo mesmo
motivo. Naquela execução, npm run check apontou a formatação preexistente de
skills-lock.json; esse arquivo foi preservado. O build informa ausência da
dependência opcional fontaine, sem impedir a compilação.

## Skills consultadas e recomendações aplicadas

- laravel-best-practices: regras de validação, arquitetura, segurança e migrations;
  aplicadas no FortifyServiceProvider, config/fortify.php, User, SecurityController,
  routes/settings.php e testes. Preservam-se Fortify, validação server-side,
  mass assignment controlado e migration incremental existente.
- security-guidance: SKILL.md consultado para autenticação, sessões e validação.
  As referências data/asvs indicadas pelo índice não estão instaladas. Não se
  afirma conformidade formal ASVS. As verificações de sessão, CSRF, hash e
  acesso estão registradas nos testes de autenticação e acesso.
- ui-taste: playbooks polish e craft consultados; AuthVuetifyLayout, Login.vue e
  Register.vue usam hierarquia simples, rótulos, foco visível, estados de envio
  e layout adaptável. Preserva-se a identidade existente sem redesenho geral.

O diretório /docs permanece ignorado pela regra existente do .gitignore. Este
arquivo precisa ser incluído explicitamente quando a documentação for versionada.

## Conferência visual

Capturas reais de login e cadastro foram inspecionadas no Chromium headless em
390×844 e 1440×1000, no tema claro. Após corrigir a precedência do padding do
card com CSS local, as quatro telas mantiveram campos, botões e links visíveis,
sem corte horizontal observado. Os arquivos temporários estão em
/tmp/cati-{login,register}-{mobile,desktop}.png. Não se afirma validação manual
dos estados de carregamento ou erro no navegador; o comportamento de requisições
e validação foi exercitado pela suíte Pest. O SSR renderizou login, cadastro,
confirmação de senha, configurações de segurança e dashboard sem erros.

## Atualização de 01/11/2026

A limpeza do legado foi aprovada e aplicada ao MySQL: campos de e-mail e 2FA,
tabelas de recuperação/passkeys e infraestrutura de filas/cache em banco foram
removidos. As notas históricas acima sobre preservação dessas colunas e migration
pendente descrevem o estado anterior. A referência atual é
[estrutura do banco](../banco-de-dados/estrutura.md). Nome, username, hash,
remember_token e sessões foram preservados; rate limiting agora usa cache file.
