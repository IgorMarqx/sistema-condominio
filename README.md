# Sistema de Gestão de Condomínio

Energia, água, caixa e auditoria de um prédio de 12 unidades (101–403) em João Pessoa – PB, sem
administradora. Implementa o documento de implementação v1.1 (13/10/2026) e os mockups de tela.

**Stack:** Laravel 13 · Inertia v3 + React 19 · Tailwind v4 · Fortify (2FA TOTP e passkeys) ·
MySQL 8 · Redis + Horizon · S3 + SES · dompdf · PWA.

## Rodar localmente

```bash
docker compose up -d            # MySQL 8 na porta 3307 (e o banco condominio_testing)
cp .env.example .env && php artisan key:generate
composer install && npm ci
php artisan migrate --seed      # prédio + cenário de demonstração dos mockups
npm run build                   # ou: npm run dev
php artisan serve --port=8010
php artisan queue:work --queue=ingestao,default   # processa e-mails recebidos
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
| 4 · Ingestão por e-mail        | `Services/Ingestao`: `RecebedorEmail` → fila `ingestao` → `ProcessadorEmail` → `ProcessadorPdf` → extratores; webhooks em `Webhooks/EmailController`                         |
| 5 · Calendário e jobs          | `Jobs/*` agendados em `routes/console.php` (horário de Brasília), `Services/Faturas/CompetenciaService`                                                                      |
| 6 · Rateio                     | `Services/Rateio` (centavos inteiros; resto 1 centavo por unidade na ordem fixa, continuando entre energia e água)                                                           |
| 7 · Caixa                      | `Services/Caixa` (append-only, estorno, transferência, fechamento com PDF)                                                                                                   |
| 8 · Auditoria                  | `Services/Auditoria` (hash encadeado; a cabeça da cadeia é travada com `FOR UPDATE`)                                                                                         |
| 9 · LGPD                       | `ArquivosController` (quem pode abrir cada arquivo), privacidade e exclusão em Minhas contas                                                                                 |

Telas: `resources/js/pages` (`unidade/*`, `gestao/*`, `auth/*`). Componentes visuais dos mockups em
`resources/js/components/condominio`.

## Receber faturas por e-mail (AWS SES)

Modelo mental: o SES é a "caixa de correio" do domínio `faturas.<seu-domínio>`. Todo e-mail que
chega vira um arquivo `.eml` no S3, e o SES avisa o sistema por HTTPS. O sistema lê o arquivo,
descobre de qual apartamento é pelo endereço (`ap302-x8vd@…`) e processa.

1. **Região:** use uma região com SES inbound (ex.: `us-east-1`).
2. **SES → Identities → Create identity → Domain:** `faturas.seudominio.com.br`; publique os
   registros DKIM no DNS.
3. **DNS:** registro `MX 10 inbound-smtp.us-east-1.amazonaws.com` em `faturas.seudominio.com.br`.
4. **SNS → Create topic** (Standard) `condominio-faturas`; **Create subscription** HTTPS para
   `https://seu-site/webhooks/email/sns`. O sistema confirma a assinatura sozinho quando o ARN
   está em `FATURAS_SNS_TOPIC_ARN`.
5. **SES → Email receiving → Create rule set → Create rule:** recipients = `faturas.seudominio.com.br`;
   ação **S3** (bucket dos e-mails, prefixo `raw/`) com **SNS topic** = o tópico do passo 4.
   Ative o rule set.
6. **.env:** `FATURAS_DOMINIO`, `FATURAS_SNS_TOPIC_ARN`, `AWS_*`, `FATURAS_SES_DISCO=s3`.
7. **Teste:** mande um e-mail com um PDF para o endereço do condomínio (tela "Receber faturas").

Alternativa sem SES: Mailgun "store and forward" ou Cloudflare Email Worker apontando para
`/webhooks/email/bruto/<FATURAS_WEBHOOK_TOKEN>`. Para testar sem nada disso:
`php artisan faturas:importar-eml arquivo.eml --para=ap302-xxxx@faturas.seudominio.com.br`.

## Produção

- `docker-compose.prod.yml` + `docker/php/Dockerfile` (php-fpm com `poppler-utils`, `qpdf` e cliente
  MySQL), Horizon na fila `ingestao,default` e `schedule:work`.
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
