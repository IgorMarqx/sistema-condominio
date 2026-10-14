-- Usuário de banco da aplicação em produção (aplicar à mão, uma vez, como root).
-- O livro-caixa e a auditoria são append-only: a aplicação pode inserir e ler, nunca alterar ou apagar.
-- Troque <senha> e, se o banco tiver outro nome, `condominio`.

CREATE USER IF NOT EXISTS 'condominio_app'@'%' IDENTIFIED BY '<senha>';

GRANT SELECT, INSERT, UPDATE, DELETE, CREATE TEMPORARY TABLES, LOCK TABLES ON condominio.* TO 'condominio_app'@'%';

-- Tira UPDATE e DELETE das tabelas imutáveis. MySQL não tem REVOKE por tabela sobre um GRANT de
-- banco inteiro, então elas recebem permissão própria e o GRANT do banco é refeito tabela a tabela
-- pelo script abaixo (rodar depois das migrations, que criam as tabelas):
--
--   mysql -uroot -p condominio -N -e "SELECT table_name FROM information_schema.tables WHERE table_schema='condominio'" |
--   while read t; do
--     case "$t" in
--       lancamentos_caixa|audit_log)
--         echo "GRANT SELECT, INSERT ON condominio.\`$t\` TO 'condominio_app'@'%';" ;;
--       *)
--         echo "GRANT SELECT, INSERT, UPDATE, DELETE ON condominio.\`$t\` TO 'condominio_app'@'%';" ;;
--     esac
--   done > /tmp/grants.sql
--
--   mysql -uroot -p -e "REVOKE ALL PRIVILEGES ON condominio.* FROM 'condominio_app'@'%';"
--   mysql -uroot -p < /tmp/grants.sql
--
-- As migrations rodam com um usuário administrativo separado (DB_USERNAME de deploy), não com este.

FLUSH PRIVILEGES;
