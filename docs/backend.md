# Backend e banco de dados — CATI Forms

## Laravel e organização

- Seguir as convenções e o código já instalado no projeto; não presumir que os arquivos do starter kit estejam intactos.
- Controllers pequenos; validação com Form Requests, quando apropriado.
- Usar Actions/Services para casos de uso e regras de negócio; não criar Services que apenas deleguem um método trivial ao Eloquent.
- Models Eloquent e relacionamentos explícitos; utilizar transações nas operações com múltiplas gravações dependentes.
- Injeção de dependência quando facilitar organização e testes.
- Alterações estruturais por migrations versionadas, não por edição manual do esquema.

## Autenticação e segurança

- Reutilizar Laravel Fortify e a autenticação por sessão do Laravel.
- Cadastro e login utilizam **username único e senha**, sem exigir e-mail do usuário cadastrado.
- Garantir unicidade no banco e nas validações; usar hash seguro para senhas.
- Proteger rotas privadas com middleware `auth` e manter limitação de tentativas de login.
- Respostas de formulários e exportações só podem ser consultadas por usuários autenticados; **qualquer** usuário autenticado pode consultar **todos** os formulários e respostas (não limitar ao criador).
- Respondentes externos acessam e enviam respostas pelos links públicos, sem autenticação; endpoints públicos não expõem respostas anteriores ou dados internos.
- Validar entradas **no servidor**, inclusive estrutura de perguntas, opções pertencentes à pergunta e justificativas obrigatórias.
- Prevenir exposição de dados, XSS, SQL Injection, abuso dos endpoints públicos e injeção de fórmulas em CSV.
- Não versionar `.env`, tokens, chaves ou credenciais.
- Recursos herdados do starter kit (passkeys, 2FA, recuperação de senha, exclusão de conta) não são requisitos do MVP. Revisar dependências antes de desativar ou remover código/tabelas.

## Banco de dados e migrations

- Banco principal: MySQL. Os testes podem utilizar SQLite em memória **somente em ambiente de teste isolado**.
- Antes de remover tabelas/colunas legadas, inspecionar uso real, migrations, constraints e presença de dados.
- Não executar `migrate:fresh`, `db:wipe`, `DROP TABLE` ou operações destrutivas sem autorização explícita.
- Tabelas técnicas de sessões, cache e filas dependem dos drivers efetivamente configurados: verificar antes de remover.
- Documentar novas entidades, relacionamentos, chaves estrangeiras, unicidade e decisões de migração.

## Testes e verificações

- Utilizar Pest e o isolamento de banco já configurado; não conectar os testes ao MySQL de desenvolvimento.
- Cobrir fluxos positivos, validações negativas, autenticação, permissões e regras de negócio relevantes.
- Preferir targets existentes do Makefile (`make help`) para testes, lint e build.
- Não declarar que testes passaram sem executá-los; informar falhas remanescentes.

## Skills aplicáveis

- `laravel-best-practices`: em tarefas Laravel, Eloquent, Fortify, migrations e arquitetura do backend.
- `security-guidance`: em autenticação, validação, armazenamento e endpoints públicos/privados.
- Verificar se cada skill existe e consultar seu `SKILL.md` aplicável antes de alegar seu uso.

Regras de negócio detalhadas: `docs/requirements.md`.

## Infraestrutura simplificada do MVP

Drivers oficiais: MySQL, cache file, fila sync e sessões database. O banco contém
users, sessions e migrations; as estruturas de cache, filas, recuperação por
e-mail e passkeys foram removidas por migration aprovada. Ver auditoria, guardas,
backup e implantação em [banco-de-dados/estrutura.md](banco-de-dados/estrutura.md).
Não ativar filas database, batching ou recuperação por e-mail sem preparar o
esquema correspondente. composer dev não inicia worker com fila sync.

## RF03 — criação por API

`POST /api/v1/forms` usa `web` e `auth:web`; manter CSRF habilitado e retornar
JSON, nunca redirecionamento para sucesso/validação. `StoreFormRequest` valida
título e descrição; `FormController::store` cria pela relação do usuário da
sessão usando somente os campos validados. `FormResource` delimita a resposta.
Sem Service redundante enquanto houver apenas uma gravação simples.

O criador não vem do corpo JSON. A FK nullable com SET NULL preserva formulários
quando uma conta é excluída. Não criar filtros de autorização por autoria.
Testes CSRF usam `EnforceRequestForgeryProtection` somente em testes para retirar
o bypass de unit tests do middleware real, preservando o ambiente testing e a
proteção SQLite. O esquema e o contrato estão documentados em
[banco](banco-de-dados/estrutura.md) e [API](api/v1/forms.md).

## Evolução RF04

CreateForm agora coordena formulário e perguntas em DB::transaction. O controller
passa somente entradas validadas; a relação define o vínculo e a Action define a
ordem. QuestionResource serializa a lista carregada. StoreFormRequest valida os
campos aninhados sem aceitar tipos de RF05/RF06. Ver explicacao.md na raiz.

## RF05–RF06

QuestionOption armazena alternativas relacionadas a Question. StoreFormRequest
valida lista condicionalmente por tipo, com chaves permitidas em cada nível;
CreateForm atribui vínculos/posições e grava tudo na mesma transação. Os Resources
recebem questions.options carregadas antecipadamente. O contrato preserva as
entradas RF03/RF04 e acrescenta options à saída das perguntas. A regra de escolha
única/justificativa ainda não é validação de resposta: não há submissões nesta etapa.
