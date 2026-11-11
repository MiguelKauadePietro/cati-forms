# Autenticação por username e isolamento de testes

## Decisões e contratos

RF01–RF02: cadastro exige username único, senha e confirmação de senha. Login
exige username e senha. Username é normalizado com trim e minúsculas no cadastro
e perfil; o login mantém a normalização do Fortify e o trim do middleware web.
O campo aceita string não vazia de até 255 caracteres, sem restrições adicionais
não estabelecidas nos requisitos. A senha permanece protegida pelo cast hashed.

Não se exige nome nem e-mail. O campo interno name recebe o username em novos
cadastros e alterações de perfil. A identificação visual e as passkeys usam
username. Segundo fator, passkeys, logout, alteração de senha e exclusão de conta
continuam disponíveis. Recuperação e verificação por e-mail foram desativadas;
suas rotas, páginas e testes antigos foram substituídos por testes de ausência
desses endpoints. Nenhum teste foi desativado para esconder falhas.

A migration incremental adiciona username obrigatório e único. Contas antigas
recebem user_{id}; nome, e-mail e senha existentes são preservados. E-mail passa a
ser nullable e mantém seu índice único. No rollback, username é removido, mas
email permanece nullable para não invalidar contas criadas sem e-mail.

**A migration não foi aplicada ao MySQL.** Aplicações que usam o banco principal
precisam aplicar essa migration em uma etapa posterior de implantação. Antes
disso, o novo código não deve ser utilizado com o esquema antigo.

## Proteção dos testes

Pest aplica RefreshDatabase a todos os testes Feature. phpunit.xml força ambiente
testing, SQLite :memory:, DB_URL vazio, sessão/cache em array e fila síncrona.
TestCase verifica a configuração efetiva antes de iniciar RefreshDatabase. Se
houver ambiente diferente, conexão diferente, arquivo SQLite persistente ou URL
de conexão, a suíte interrompe a execução antes das migrations.

A execução não consulta nem migra o MySQL principal. Não se altera .env.
Configuração em cache incompatível é rejeitada. Para executar ignorando um cache
de configuração existente, use um caminho inexistente exclusivo da tarefa:

```sh
APP_CONFIG_CACHE=/tmp/cati-forms-test-config-absent.php vendor/bin/pest --compact
```

O driver PHP pdo_sqlite precisa estar disponível. Os testes verificam tanto a
rejeição de configurações inseguras quanto a ausência de dados entre testes.

## Resultados desta revisão

A situação inicial já tinha RefreshDatabase ativo e pdo_sqlite disponível:
44 testes e 174 asserções passaram. As 32 falhas anteriores não foram
reproduzidas; sem sua saída original não é possível atribuir suas causas
individualmente.

Depois da adaptação, a suíte completa passou em duas execuções consecutivas:
44 testes e 203 asserções, sem falhas ou testes ignorados. O total igual de testes
não significa que os cenários sejam os mesmos: cenários de e-mail foram
substituídos por cenários de username, proteção do banco e migração legada.

TypeScript, PHPStan nível 7, lint JavaScript, builds de cliente e SSR passaram.
O bundle SSR renderizou login, cadastro, perfil e dashboard. O npm run check
continua apontando apenas formatação preexistente em skills-lock.json, preservado.
O comando composer lint:check usa Pint paralelo, cujo socket é bloqueado neste
ambiente; a verificação equivalente é executada com vendor/bin/pint --test.
Não houve validação visual em navegador nem migração do MySQL.

Esta documentação está sob /docs, diretório ignorado pela regra atual do projeto.
