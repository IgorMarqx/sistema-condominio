# Sistema de Gestão de Condomínio

Energia, água, caixa e auditoria de um prédio de 12 unidades (101–403) em João Pessoa – PB, sem
administradora. Implementa o documento de implementação v1.1 (13/10/2026) e os mockups de tela.

**Stack:** Laravel 13 · Inertia v3 + React 19 · Tailwind v4 · Fortify (2FA TOTP e passkeys) ·
MySQL 8 · Redis · S3 · dompdf · PWA.

## Rodar localmente

```bash
docker compose up -d            # MySQL 8 na porta 3307 (e o banco condominio_testing)
cp .env.example .env && php artisan key:generate
composer install && npm ci
php artisan migrate --seed      # prédio + cenário de demonstração dos mockups
npm run build                   # ou: npm run dev
php artisan serve --port=8010
php artisan queue:work          # e-mails de aviso
```

Acessos da demonstração (só em `APP_ENV=local`):

| Quem         | Login                                                 | Senha                                                      |
| ------------ | ----------------------------------------------------- | ---------------------------------------------------------- |
| Apartamentos | `101` … `403` (tela de grade)                         | `password`                                                 |
| Síndico      | `sindico@condominio.local` (link "Acesso do síndico") | `password` + código 2FA (o segredo aparece no fim do seed) |
| Conselho     | `conselho@condominio.local`                           | `password`                                                 |

Para gerar o código 2FA do síndico no terminal:

```bash
php artisan tinker --execute="echo (new PragmaRX\Google2FA\Google2FA)->getCurrentOtp(decrypt(App\Models\User::where('login','sindico@condominio.local')->value('two_factor_secret')));"
```

Testes: `php artisan test` (rodam no MySQL `condominio_testing`, criado pelo compose).

## Instalação real

```bash
php artisan migrate --force
php artisan condominio:instalar --admin-email=voce@email.com --admin-nome="Seu Nome"
php artisan condominio:senhas-unidades     # senhas iniciais (16 caracteres) para entregar em papel
```

Depois, no sistema: trocar a senha, ativar o 2FA (obrigatório para abrir a gestão), cadastrar os
medidores do condomínio (UC da Energisa e matrícula da Cagepa), a chave Pix e a contribuição mensal
em **Configurações**. Os medidores das unidades se cadastram sozinhos com a primeira fatura.

## Como as partes se encaixam

| Seção do documento             | Onde está                                                                                                                                                                    |
| ------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 2 · Perfis e login por unidade | `FortifyServiceProvider`, `Support/Aparelho` (lembrar aparelho), `Listeners/RegistrarAcesso` (nome informado + `sessoes_log`), middlewares `SomenteGestao`/`ExigeTrocaSenha` |
| 4 · Faturas                    | `Services/Faturas` (conta lançada no painel ou PDF importado) e `Services/Ingestao/ProcessadorPdf` → extratores                                                              |
| 5 · Calendário e jobs          | `Jobs/*` agendados em `routes/console.php` (horário de Brasília), `Services/Faturas/CompetenciaService`                                                                      |
| 6 · Rateio                     | `Services/Rateio`: cada conta gera as cobranças na hora, em divisão igual entre as unidades ativas (centavos inteiros; resto 1 centavo por unidade na ordem fixa)            |
| 7 · Caixa                      | `Services/Caixa` (append-only, estorno, transferência, fechamento com PDF)                                                                                                   |
| 8 · Auditoria                  | `Services/Auditoria` (hash encadeado; a cabeça da cadeia é travada com `FOR UPDATE`)                                                                                         |
| 9 · LGPD                       | `ArquivosController` (quem pode abrir cada arquivo), privacidade e exclusão em Minhas contas                                                                                 |

Telas: `resources/js/pages` (`unidade/*`, `gestao/*`, `auth/*`). Componentes visuais dos mockups em
`resources/js/components/condominio`.

## Produção

- **Deploy automático** a cada push na `main` (`.github/workflows/deploy.yml`, mesmo modelo do
  sistema-ponto): o Actions monta as imagens `app` e `nginx` do `Dockerfile` (php-fpm 8.3 com
  `poppler-utils`, `qpdf` e cliente MySQL), publica no GHCR e, por SSH, faz `git reset` no servidor,
  `docker compose -f docker-compose.prod.yml up`, `migrate --force`, `optimize` e `queue:restart`.
- Serviços: `app` (php-fpm), `queue` (`queue:work`), `scheduler` (`schedule:work`, jobs diários) e
  `nginx`, ligado à rede externa `public-proxy-network` (o proxy com TLS fica fora deste compose).
- Secrets do repositório: `GHCR_USERNAME`, `GHCR_TOKEN`, `DEPLOY_HOST`, `DEPLOY_USER`,
  `DEPLOY_SSH_KEY`, `DEPLOY_PATH` e, opcionais, `DEPLOY_PORT` e `DEPLOY_BRANCH`. No servidor, o
  `.env` de produção fica em `DEPLOY_PATH` e precisa de `APP_URL`, `DB_USERNAME` e `DB_PASSWORD`.
- Banco: RDS ou MySQL do host. Aplique `database/sql/permissoes.sql`: o usuário da aplicação não
  tem `UPDATE`/`DELETE` em `lancamentos_caixa` e `audit_log`.
- S3 com versionamento + Object Lock (modo governance) para `CONDOMINIO_DISCO=s3`.
- Backup diário cifrado do banco às 03:00 (`BackupBanco`, retenção de 90 dias).

## Lacunas conhecidas

- **Layout real das faturas:** os extratores foram escritos e testados com fixtures **sintéticas**
  (`tests/Fixtures/faturas/LEIAME.md`). Antes da fase 3 em produção, separar 3 a 6 PDFs reais
  (Energisa e Cagepa, condomínio e individual), anonimizar e trocar as fixtures. Falha de leitura
  cai em revisão, nunca vira valor errado em silêncio.
- **Soma dos itens:** se na fatura real os tributos vierem "inclusos" na tarifa, a verificação
  "soma dos itens bate com o total" precisa ser ajustada ao layout real.
- **Fase 5** (WhatsApp, mural, documentos, votação) não foi implementada. Avisos saem na tela e por
  e-mail (opcional, por unidade).
