# BOXPULSE

Plataforma SaaS para preparar e apresentar aulas de CrossFit, HYROX e treinamento funcional pelo navegador. Implementação incremental: **Fase 3.2 — Cargas sugeridas e modalidades por box**, mantendo personalização, cronômetros e TV Mode. A execução, o controle do coach e a TV compartilham sessões persistentes e isoladas por box.

## Entregue

- Cadastro inicial de box + administrador em transação, com username único normalizado e senha com hash nativo.
- Login por username, rotação de sessão, limitação por credencial/IP, logout e CSRF nativo do grupo web.
- Contexto de tenant derivado do usuário autenticado, escopo de leitura, proteção de escrita e policies.
- Painel inicial com data no fuso da box, modalidades e consulta de múltiplos treinos publicados no dia.
- CRUD completo/simplificado nas três modalidades, blocos e estações ordenáveis, rascunho/publicação, calendário semanal, histórico, cópia individual e semanal com política de conflitos.
- Revisão estática de treinos e TV Mode funcional por sessão, com cores e logo da box.
- Schema com integridade composta por tenant, factories, seeders locais, testes MySQL e documentação.
- Interface Blade própria, escura e responsiva, com tema derivado das cores já configuradas na box e ajustes de contraste. Configurações da Box permite editar identidade, cores, fuso e logo raster até 2 MB.

## Ambiente verificado

| Tecnologia | Versão |
| --- | --- |
| PHP CLI / Sail | 8.3.6 / 8.5.12 |
| Laravel | 13.40.0 |
| Composer | 2.7.1 |
| MySQL Sail | 8.4 |
| Sail / Boost | 1.69.0 / 2.10.4 |
| PHPUnit | 12.5.43 |
| Node / npm | 22.22.3 / 10.9.8 |
| Tailwind / Vite | 4.3.5 / 8.3.7 |

O lock anterior exigia PHP 8.4.1. Foi resolvido novamente para PHP 8.3, mantendo Laravel 13 e usando Symfony 7.4 compatível. Não usar `--ignore-platform-reqs`. Filament 5.11.2 + Livewire 4.4.8 passaram na resolução Composer `--dry-run`; a instalação não faz parte desta fase de gestão de treinos. Mantivemos Blade e JavaScript nativo, sem novas dependências. Antes de integrar um painel, revisar autenticação por username e regras de tenant.

## Instalação local com Sail

Requisitos: Docker com Compose, PHP 8.3+ e Composer para instalar o vendor inicial; Node 22.12+ para Vite 8.

```sh
composer install
cp .env.example .env
```

Execute a cópia apenas em uma instalação nova. Preserve `.env` e o banco de instalações existentes. Defina uma senha local própria em `DB_PASSWORD`; ajuste `WWWUSER` e `WWWGROUP` aos IDs do seu usuário. `DB_HOST=mysql` funciona dentro do Sail; para PHP executado no host, use `DB_HOST=127.0.0.1`.

```sh
php artisan key:generate
vendor/bin/sail up -d
vendor/bin/sail artisan migrate
npm ci
npm run build
```

O Compose publica a aplicação em [localhost](http://localhost). Abra `/register` para criar uma box e seu administrador. O banco padrão do exemplo novo é `boxpulse`; o ambiente preexistente mantém sua configuração. MySQL e app devem usar o mesmo `DB_DATABASE` e as credenciais definidas no `.env`. Não reaproveite um volume de outro projeto. Não remova volumes para resolver problemas de configuração.

A aplicação usa fontes do sistema, sem depender de download de fontes no build. Para desenvolvimento, use `npm run dev` quando desejar atualização de assets.

## Verificação

```sh
vendor/bin/sail artisan test --compact
vendor/bin/pint --dirty --format agent
composer check-platform-reqs
npm run build
```

Os testes usam **exclusivamente o banco MySQL `testing`**, criado pelo script do Sail na primeira inicialização de um volume novo. O `TestCase` bloqueia a inicialização se ambiente/conexão/banco não forem os esperados. `RefreshDatabase` recria tabelas desse banco de testes. Nunca aponte testes para um banco com dados úteis. Se o volume já existia sem `testing`, crie um banco separado e conceda acesso ao usuário local; não apague o volume existente. Limpe configuração em cache antes dos testes (`vendor/bin/sail artisan config:clear`).

No ambiente desta entrega, `docker compose exec -T laravel.test php artisan test --compact` usa o mesmo PHP e banco do Sail.

## Dados demonstrativos

`vendor/bin/sail artisan db:seed` cria, apenas em `local`/`testing`, uma box fictícia com usuário `boxpulse.demo`. A senha é aleatória e não é exibida nem possui valor padrão compartilhado. O seeder é idempotente e não altera usuários existentes. Para experimentar login e o painel, crie sua própria conta em `/register`. O `WorkoutDemoSeeder` adiciona um treino principal publicado por modalidade à box demo, sem sobrescrever os existentes. Nenhum dado demo é inserido em outras boxes.

## Documentação

- [Arquitetura e compatibilidade](docs/architecture.md)
- [Banco de dados e esquema proposto](docs/database.md)
- [Configuração dos formatos e contrato do motor futuro](docs/timers.md)
- [Roadmap e validação](docs/roadmap.md)
- [Instruções permanentes para agentes](AGENTS.md)

## Preparação para produção

Deploy em produção não faz parte desta entrega. Antes de produção: concluir as fases funcionais, configurar HTTPS, `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, credenciais próprias, backups e restauração, raiz web em `public/`, limites de upload e execução de migrations revisadas. Não usar contas demo ou banco `testing` em produção. Recuperação de senha e gestão de usuários ainda não foram implementadas.

## Gestão e programação de treinos

Após login, abra `/dashboard`, escolha uma modalidade e cadastre um treino completo ou principal. `/schedule` abre a semana atual no fuso da box; `/workouts` mostra o histórico. Na página de um treino é possível editar, copiar para outra data, publicar/retornar a rascunho e excluir com confirmação.

`/workouts/{id}/preview` permite revisar inclusive rascunhos. `/workouts/{id}/presentation` prepara a pré-visualização somente de publicados. Essas rotas permanecem como revisão estática. Para executar uma aula, use o formulário **Iniciar apresentação** disponível para publicados. O calendário permite copiar uma semana inteira: `abort` cancela se houver qualquer treino no destino; `append` preserva os existentes e adiciona cópias como rascunhos.

Validação da Fase 2: **85 testes / 359 verificações no PHP 8.3 e no Sail PHP 8.5**, incluindo a regressão da Fase 1. Fluxo real no Chrome: cadastro QA, aula HYROX completa, sugestão de oito estações, reordenação/remoção, publicação, preview Full HD, calendário, campos dinâmicos e formulário a 390 px sem overflow horizontal. O fluxo de navegador utilizou uma conta local identificada como `QA • BoxPulse Fase 2`; não alterou contas existentes.

## Executar a Fase 3

1. Entre como administrador e abra **Configurações da Box** (`/box/settings`). Salve nome, Head Coach, nome do administrador, fuso, cores e logo. A imagem aceita PNG/JPEG/WebP até 2 MB, sem cortes ou distorção.
2. Abra um treino publicado e clique em **Iniciar apresentação**. Escolha preparação ou use a configuração de cada bloco. Avanço automático vem desabilitado; habilitá-lo autoriza iniciar os próximos blocos após finalizações temporais.
3. No painel do coach, clique em **Iniciar etapa / aula**. Preparar a sessão não inicia a contagem. Use pausar, continuar, reiniciar com confirmação, anterior/próxima, finalizar etapa e finalizar/cancelar aula.
4. Abra **TV Mode** em outra janela/monitor. A TV só lê o estado. Clique em **Entrar em tela cheia** e, se desejar, **Ativar sons**. Esc ou o botão correspondente sai do fullscreen sem encerrar a aula.
5. Recarregue a TV para verificar restauração. Desconecte/reconecte a rede: o relógio local continua calculando, mostra a desconexão e recebe o estado autoritativo ao reconectar. Comandos novos ficam bloqueados offline.
6. Abra outra aba de controle: ela acompanha a mesma sessão sem controlar simultaneamente. **Assumir controle** transfere o lease explicitamente; a aba anterior perde seus controles.
7. Consulte **Sessões de aula** (`/sessions`) e o resumo após encerrar. Há uma sessão ativa por workout; a preparação retoma uma revisão compatível ou preserva uma execução com atividade. Preparações desatualizadas ainda sem atividade são substituídas conforme a política da Fase 3.1. Encerradas permanecem no histórico e permitem uma nova sessão.

Execute também os testes puros do motor:

```sh
npm run test:timers
```

A nova migration `2026_11_10_132606_create_workout_session_tables` adiciona workout_sessions, timer_sessions e session_commands. Não altera configurações dos treinos anteriores. Edição do treino não modifica snapshots já preparados; exclusão de treino com histórico é bloqueada para preservar suas sessões.

O polling padrão é 1000 ms, configurável por `BOXPULSE_POLL_INTERVAL_MS` (500–5000). O relógio visual usa performance.now(), não depende de uma requisição a cada segundo exibido. Somente o responsável ou um administrador da própria box controla; outros coaches podem acompanhar. Lease padrão de 6 s, renovado enquanto a aba controla; ao atualizar o painel, assuma o controle ou aguarde sua expiração.

O link `public/storage` está configurado como link relativo para `../storage/app/public`, válido no host e no Sail. Em uma instalação nova, `vendor/bin/sail artisan storage:link` configura o link padrão se ainda não existir. As telas utilizam a rota autenticada `/box/logo`.

Validação da Fase 3: suíte PHP e motor Node, além de Chrome real com upload, três blocos, pausa/reload, Full HD/1440p/4K sem overflow, paginação de conteúdo longo, fullscreen, áudio por gesto/mute, offline, takeover e comandos concorrentes (200/409, repetição idempotente 200). Não houve validação em hardware físico de TV/alto-falantes.

Resultado final: **114 testes PHP / 656 verificações aprovados no host PHP 8.3 e no Sail PHP 8.5**, mais **16 testes puros Node**. Guia completo de rotas, etapas e limitações: [homologação manual](docs/qa-phase3.md).

## Fase 3.1 — revisões, minutos e identidade

Os formulários completos/simplificados agora recebem durações principais em **minutos inteiros**. Segundos adicionais são opcionais: 1 minuto + 30 segundos preserva um treino antigo de 90 s sem arredondar. Trabalho, descanso, transição, preparação e intervalo EMOM continuam em segundos. Valores do banco e do motor não foram reconvertidos.

A preparação usa a revisão publicada atual. Se houver preparação antiga ainda sem atividade, cria uma nova e mantém a anterior como substituída. Aulas já iniciadas mantêm o conteúdo original e exibem aviso, revisão do cadastro e uma opção confirmada para reiniciar a aula com a versão publicada atual. Histórico de aulas finalizadas permanece intacto. O campo de versão do treino é independente da versão usada na concorrência dos comandos.

O dashboard destaca logo, nome/Head Coach, data e ações de aula/programação, mantendo BOXPULSE no cabeçalho. Com vários treinos hoje, o usuário escolhe qual aula preparar.

Migration aditiva: `2026_11_10_144718_add_workout_content_versions_to_sessions`. A proposta de separação futura de Gestão e Aula está em [coach-workspaces.md](docs/coach-workspaces.md); essa refatoração ainda não foi feita.

Validação da Fase 3.1: **130 testes PHP / 803 verificações**, incluindo criação/edição em minutos, segundos legados, cenários pending/running/finished, autorização/tenant e regressões anteriores; **16 testes Node** mantidos. Chrome reproduziu a troca Front Squat → Clean & Jerk e o fluxo de nova sessão/TV, além de identidade em três proporções e mobile. Detalhes em [roadmap](docs/roadmap.md) e [proposta de ambientes](docs/coach-workspaces.md).


## Fase 3.2 — cargas e modalidades por box

Cadastro e Configurações exigem pelo menos uma modalidade do catálogo existente. Boxes anteriores receberam as três; a seleção filtra dashboard e criação no servidor. Desativar preserva treinos e histórico, bloqueia novas operações e mantém aulas com atividade funcionando. Programação e histórico identificam modalidades desativadas. Reativar restaura as operações.

No bloco principal de qualquer modalidade, **Cargas sugeridas** aceita equipamento, RX, Intermediate e Scale como texto livre, com kg/lb opcional para números isolados. Pares e descrições são exibidos como cadastrados. Sem níveis, a seção desaparece. Cópias e novas sessões incluem as cargas; aulas iniciadas preservam seu snapshot. Detalhes, preview, coach e TV usam a mesma referência por bloco.

Duas migrations aditivas aplicadas: `2026_11_10_153006_create_box_modalities_table` e `2026_11_10_153007_add_suggested_loads_to_workout_blocks_and_timer_sessions`. Sem reescrita de tempos ou histórico.

Validação: **152 testes PHP / 1.089 verificações** em PHP 8.3 e Sail PHP 8.5, **16 testes Node**, build/Pint/Composer aprovados. Chrome conferiu onboarding, seleção/reativação, cargas por etapa, aula em execução após desativação e TV Full HD/QHD/4K. Consulte o [relatório e roteiro manual](docs/phase-3.2.md).
