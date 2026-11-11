# Banco do MVP — limpeza aprovada em 01/11/2026

## Estrutura resultante

O banco principal usa MySQL. Após a migration incremental de limpeza, existem:

| Tabela     | Responsabilidade                     | Integridade                                                                                       |
| ---------- | ------------------------------------ | ------------------------------------------------------------------------------------------------- |
| users      | RF01–RF02: identidade e autenticação | id primário; username obrigatório e único; name, password, remember_token, created_at, updated_at |
| sessions   | Sessões do guard web                 | id primário; user_id nullable indexado; ip_address, user_agent, payload e last_activity indexado  |
| migrations | Histórico Laravel                    | Sete migrations aplicadas, preservando as seis anteriores                                         |

Na etapa de limpeza não foram criadas entidades de formulários ou respostas. O campo name permanece
porque contém dados e ainda é usado internamente; username é a identidade visual.
remember_token é necessário para a opção Manter conectado e foi preservado.
A estrutura original de sessions foi preservada, inclusive o índice em user_id
sem introduzir uma nova foreign key ou alterar sua política de exclusão.

### Evolução RF03 — migration ainda não aplicada ao MySQL nesta tarefa

`2026_11_02_020137_create_forms_table.php` acrescenta `forms` sem alterar as
tabelas ou dados anteriores. Após aplicá-la, o esquema terá também:

| Campo                   | Tipo                     | Regra                                                  |
| ----------------------- | ------------------------ | ------------------------------------------------------ |
| id                      | bigint unsigned          | Chave primária                                         |
| title                   | varchar(255)             | Obrigatório; títulos repetidos permitidos              |
| description             | text                     | Obrigatória; validação de até 10.000 caracteres na API |
| creator_id              | bigint unsigned nullable | FK para users.id, ON DELETE SET NULL                   |
| created_at / updated_at | timestamp nullable       | Preenchidos pelo Eloquent                              |

A autoria é definida pela sessão, não pelo cliente. A exclusão da conta mantém
o formulário e anula apenas a FK. Os 10.000 caracteres são o limite técnico
aprovado no plano RF03, não uma regra extraída do documento original.

Não foram acrescentadas tabelas de perguntas ou respostas. As migrations
anteriores permanecem intactas. Instalações novas executam todo o histórico.
O `down()` da nova migration remove a tabela e seus dados: é destrutivo e não
deve ser executado sem aprovação. A implantação no MySQL é uma etapa separada;
revisar migrations pendentes e backup antes de aplicar. Testes desta entrega
usam apenas SQLite isolado.

## Auditoria anterior e aprovação

| Estrutura             | Registros anteriores | Dependência e decisão                                                               |
| --------------------- | -------------------: | ----------------------------------------------------------------------------------- |
| cache                 |                    4 | Rate limiting real do Fortify; migrado para arquivos, descarte transitório aprovado |
| cache_locks           |                    0 | Driver de cache em banco; substituído pelo driver file                              |
| jobs                  |                    0 | Driver database e worker de desenvolvimento; substituídos por sync                  |
| job_batches           |                    0 | Configuração herdada, sem uso de batching no código                                 |
| failed_jobs           |                    0 | Persistência de falhas configurada, agora desativada                                |
| passkeys              |                    0 | Funcionalidade desativada; única FK auditada, para users.id                         |
| password_reset_tokens |                    0 | Recuperação por e-mail desativada                                                   |

users e sessions tinham um registro cada. email, email_verified_at,
two_factor_secret, two_factor_recovery_codes e two_factor_confirmed_at estavam
sem dados. A remoção dessas estruturas e dos quatro registros de cache foi
explicitamente aprovada, assim como a alteração dos três drivers no .env local.
Credenciais, APP_KEY e demais valores locais não foram alterados.

## Drivers oficiais

- CACHE_STORE=file: mantém o rate limiting sem tabelas de cache. storage precisa
  estar gravável, incluindo framework/cache/data e framework/cache/locks.
- QUEUE_CONNECTION=sync: trabalhos executam durante a requisição; falhas
  propagam ao chamador. Não há processamento assíncrono no MVP.
- SESSION_DRIVER=database: mantém as sessões no MySQL.
- queue.failed.driver=null: sem persistência em failed_jobs.

A transição reinicia os contadores de rate limiting. Uma implantação com várias
instâncias precisa compartilhar o storage de cache ou revisar o driver antes de
escalar; cache local não fornece um limite global entre máquinas independentes.
O .env.example e os fallbacks refletem MySQL, file, sync e database. No exemplo,
as credenciais são apenas placeholders. Os drivers alternativos do framework
permanecem disponíveis como configuração; sua ativação exige provisionamento e
nova revisão de esquema. A configuração de batching é inerte e não implica
suporte do MVP: não chamar Bus::batch sem uma nova migration de infraestrutura.
O broker de recuperação de senha não possui repositórios configurados.

composer dev continua iniciando servidor, logs e Vite, mas exclui o worker queue
quando a fila efetiva é sync. O Makefile e os scripts de instalação foram
preservados; uma instalação do zero aplica todo o histórico com artisan migrate
e termina no mesmo esquema reduzido. O teste dessa instalação usa SQLite em
memória; a compatibilidade MySQL foi verificada na atualização real.

## Migration e dados

2026_11_01_000000_remove_unused_starter_kit_structures valida todos os drivers,
contagens, campos e FKs antes de qualquer DDL. Recusa dados em qualquer tabela
legada, exceto cache; recusa qualquer campo removido não nulo e dependências
externas às estruturas candidatas. Remove passkeys primeiro, respeitando sua FK.

DDL no MySQL não é transacional; executar em janela sem escritores concorrentes.
Os guardas reduzem o risco, mas não substituem essa coordenação. Uma falha de DDL
pode exigir inspeção e correção incremental. Nunca usar reset ou limpeza total
do banco. down recusa rollback, pois recriar tabelas não recuperaria dados.
Restauração de backup é uma operação separada, potencialmente destrutiva, que
exige aprovação; não ocorre automaticamente.

Antes da aplicação, mysqldump gerou backup completo em
storage/app/private/database-backups, com arquivo SQL 0600 e diretório privado.
As credenciais foram passadas por arquivo temporário 0600, removido após o uso,
sem argumentos sensíveis nem saída no relatório. Backup e snapshot estão
ignorados pelo Git; não publicar ou anexar esses arquivos.

A migration foi aplicada no MySQL, após as verificações. A conferência final
confirmou apenas users, sessions e migrations; os dados retidos do usuário e a
sessão inteira foram comparados ao snapshot anterior e permaneceram iguais.
O histórico aumentou de seis para sete migrations. As cinco colunas removidas
estavam nulas e o único conteúdo descartado foram os quatro registros de cache.

## Código e testes

Foram retiradas referências aos campos removidos em User, UserFactory, tipos
TypeScript e testes. Os testes de endpoints fora do escopo continuam presentes.
O cenário histórico de migração de username agora usa uma conexão SQLite
separada, efêmera, para verificar o esquema anterior sem contaminar a suíte.
Também se testa preservação de nome, hash, remember_token e sessões; recusa de
campos preenchidos, trabalhos pendentes, cache database e FKs inesperadas. O cache file foi exercitado em diretório
temporário com contagem de rate limiting, limpeza da chave de teste e locks,
sem tabelas cache/cache_locks.

- make test: 64 testes e 269 asserções, sem falhas.
- make test-auth: 27 testes e 132 asserções, sem falhas.
- PHPStan e TypeScript: aprovados.
- make build: aprovado; aviso opcional de fontaine não impede o build.
- make lint: bloqueado pelo socket do Pint paralelo no sandbox.
- vendor/bin/pint --test: aprovado, verificação serial equivalente.

Skills consultadas: laravel-best-practices, incluindo migrations, cache e
configuração, aplicada à migration, configs e provider; security-guidance,
consultada para integridade e proteção dos dados. Seus arquivos ASVS indicados
pelo índice não estão instalados, portanto não se afirma conformidade formal.

## RF04 — questions (migration pendente no MySQL nesta tarefa)

A migration 2026_11_03_010114_create_questions_table.php acrescenta questions:
id, form_id obrigatório com FK restritiva para forms.id, type varchar, prompt
text, position unsigned integer, created_at e updated_at. O índice único composto
(form_id, position) garante posições distintas por formulário. A API aceita
somente discursive neste estágio; prompt é validado até 10.000 caracteres.
O vínculo com users continua indireto via forms: excluir uma conta preserva
formulários e perguntas. Nenhuma tabela de respostas foi criada. Migrations
anteriores e dados existentes permanecem intactos; rollback desta tabela apagaria
dados e exige aprovação. A persistência conjunta usa transação.

## RF05–RF06 — alternativas (migration pendente no MySQL nesta tarefa)

2026_11_03_205430_create_question_options_table.php cria question_options com id,
question_id obrigatório (FK restritiva para questions), label text, position
unsigned integer e timestamps. Há unicidade (question_id, position), sem unicidade
de texto. IDs e posições são determinados no servidor. Question::options() tem
várias alternativas ordenadas e QuestionOption::question() pertence a uma pergunta.
Não há tabela diferente por tipo. questions.type continua varchar; a API limita
os valores. Discursivas existentes permanecem sem alternativas e não sofrem backfill.
A criação inteira usa uma transação; excluir conta preserva o conjunto via
forms.creator_id SET NULL. A migração não foi aplicada ao MySQL nesta entrega;
rollback apagaria alternativas e requer aprovação. RF06 reutiliza essa estrutura,
sem campos de respostas ou justificativa antecipados.
