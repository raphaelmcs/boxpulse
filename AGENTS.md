<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.3. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>


# BOXPULSE — regras permanentes

- Objetivo: SaaS comercial para boxes de CrossFit, HYROX e funcional; navegador no computador e TV, sem app mobile. Implementar por fases, testando cada entrega. Fases 1, 2, 3, 3.1 (consistência, minutos e identidade), 3.2 (cargas e modalidades por box), 4 (ambientes) e hotfix de apresentação/etapas/formatos implementados. O pedido atual reorganizou a numeração do roadmap. Próximos passos em `docs/roadmap.md`.
- Stack: PHP 8.3+ (Sail usa 8.5), Laravel 13, MySQL 8.4, Blade, Tailwind 4, Vite 8, PHPUnit 12. Filament 5/Livewire 4 planejados e compatibilidade resolvida em dry-run, ainda não instalados. Sem React/Vue/Redis/WebSockets por padrão.
- Antes de decidir arquitetura, conferir versões reais com `composer show --direct`, `php -v`, `npm ls --depth=0` e PHP do Sail. Não ignorar requisitos de plataforma. Node 22.12+ para Vite 8.
- Código: classes e métodos em inglês, interface em português; controllers curtos, Form Requests para validação, Actions para operações transacionais, relações Eloquent tipadas, Blade com escape padrão, rotas nomeadas. Usar Artisan para scaffolding e Pint para formatação.
- Tenant: o único tenant HTTP válido é `auth()->user()->box_id`. Nunca aceitar box_id de formulário, URL ou header. `ResolveCurrentBox` deve executar depois de auth e antes de route binding, limpando contexto em finally.
- Recursos de tenant estendem `TenantModel`; sem contexto a leitura retorna zero linhas e escrita lança erro. Nunca transferir recursos entre boxes. Policies verificam box e papel mesmo quando o modelo já está carregado.
- `User` é exceção ao escopo global por necessidade do provider de login. `Box` é raiz e `TrainingModality` catálogo global. Não expor listagens ou bindings globais de User/Box. Consultar box por usuário autenticado e autorizar relações.
- Não contornar isolamento com SQL bruto, `withoutGlobalScopes`, `insert`, `upsert` ou updates em massa. Não usar mass assignment de box_id/role. Futuros filhos precisam de FKs compostas por box e testes de referências cruzadas.
- Jobs/comandos devem estabelecer contexto explicitamente e limpar em try/finally. Não manter tenant em variáveis estáticas ou singletons de processo.
- Datas civis de treino pertencem ao fuso IANA da box; eventos persistidos usam UTC. Não alterar timezone global do processo. Vários workouts por dia são permitidos.
- Timers: seguir `docs/timers.md`. Usar timestamps + performance.now(), nunca decremento ingênuo de setInterval. Backend autoritativo, versão otimista, idempotência, restauração, lease de controlador e conflitos explícitos. Não iniciar ou avançar aula sem interação/configuração explícita. Áudio requer gesto do usuário.
- Segurança: CSRF, limite por IP/credencial, hash nativo, senha bcrypt limitada a 72 bytes sem caracteres nulos, escape de texto, uploads raster com MIME/tamanho validados e testes de tenant. Nunca logar senha, cookie, token ou credencial. Não adicionar bypass de segurança para facilitar seed/teste.
- Banco: novas migrations aditivas; preservar migrations já aplicadas e usuários legados. Nunca `migrate:fresh`, `db:wipe`, apagar volume ou dados úteis. Operações destrutivas e produção exigem autorização explícita. Revisar SQL, índices e backups.
- Testes: `vendor/bin/sail artisan test --compact` (ou `docker compose exec -T laravel.test php artisan test --compact`); banco MySQL dedicado `testing`, protegido por TestCase. Nunca apontar RefreshDatabase para o banco da aplicação. Limpar config cache antes de testar.
- Validação: `vendor/bin/pint --dirty --format agent`, `composer check-platform-reqs`, `npm run build`. Testar isolamento, permissões, datas e funcionalidade alterada; regressão ao finalizar cada fase. Não declarar TV/4K ou sincronização validados sem testes reais.
- Gestão de treinos: alterações de aggregate em Actions transacionais. SaveWorkout e PublishWorkout compartilham WorkoutDefinition. Rascunho pode estar incompleto; publicar exige estrutura e parâmetros válidos. Não permitir início de rascunho.
- TimerConfiguration define um catálogo universal, compartilhado por qualquer modalidade e etapa, incluindo repetitions legado. Configurações persistidas são version=1 com segundos inteiros; converter para ms na fronteira do futuro motor, sem misturar unidades.
- Blocos/estações têm FKs compostas de tenant e posições atribuídas pela ordem dos arrays validados. Não aceitar IDs de filhos ou parent IDs do navegador. Editar substitui filhos em transação; sessões usam snapshots imutáveis e não FKs para os filhos originais. Não remover workouts com histórico.
- Toda cópia gera novos IDs e draft. Cópia semanal exige confirmação e política abort/append; mantém existentes e é transacional. Semana começa explicitamente na segunda-feira, independentemente do locale.
- Preview do workout é estático; execução fica em sessões próprias. Iniciar apresentação prepara, não inicia timer. Só publicado prepara sessão. Coach e TV usam engine.js/SessionClock; TV só lê. Renderizar descrições como texto escapado e paginado, nunca HTML cru.
- Tema: sanitizar hex no servidor e manter contraste; nunca interpolar CSS não validado. Logos só podem ser servidas por rota autenticada, do prefixo boxes/{id}/ da própria box, em formato raster.
- Configurações da Box é admin-only e deriva box/admin do usuário autenticado. Logo PNG/JPEG/WebP até 2 MB, hashName seguro no disco public; armazenar nova antes da transação, limpar em rollback e remover antiga só após commit. Não aceitar caminhos/IDs de tenant do cliente. Preservar proporções e contraste.
- Sessões: PrepareWorkoutSession faz snapshot, lock no workout e deduplica sessão ativa. Toda transição usa lock na aula, versão esperada, command_id idempotente e lease da aba. Só responsável/admin da mesma box controlam. HTTP 409 retorna snapshot confirmado; 422 valida transição.
- Timestamps UTC com precisão em ms; elapsed inclui preparação. Não persistir performance.now(). Frontend reconstrói âncora pelo server_time_ms e timestamps, depois usa relógio monotônico. Backend normaliza fronteiras e não grava cada segundo.
- Lease padrão 6 s, renovado ~2 s, takeover explícito. Outra aba acompanha e comandos de versões antigas não reaplicam. Sem comandos novos offline; só reenviar o mesmo comando em trânsito com UUID/payload original. Nunca inventar avanço local.
- Auto avanço vem false; quando true, avança apenas por conclusão temporal nova e conserva horário real da fronteira. Voltar a etapa concluída não pode acioná-lo de novo. Reiniciar pede confirmação e retorna idle.
- TV: 100dvh, fontes por viewport, AdaptiveContent sem HTML cru, sem paginação/rotação automática, mínimo de legibilidade e aviso explícito de excesso, AudioContext só por gesto, fullscreen por clique. Não declarar som físico validado apenas por teste headless.
- Rodar também `npm run test:timers` (núcleo puro com relógio injetável, fixtures compartilhadas). Testar snapshot imutável, ms, pause/resume, preparação, cap, rounds/estações, idempotência, lease/takeover, conflitos, rede e tenant. Sem sleeps reais nos testes lógicos.
- Manter README.md, AGENTS.md e docs/{architecture,database,timers,roadmap}.md. Ao concluir fase, informar arquivos, funcionalidades, validação e pendências.
- Fase 3.1: SaveWorkout incrementa content_version do agregado em cada edição, sob lock/transação. Novas sessões capturam workout_version. Não confundir com session.version operacional. Não editar workouts por gravações parciais que ignorem a Action.
- Preparação ativa da mesma revisão pode ser retomada. Se a revisão mudou, preparação sem atividade é substituída por novo snapshot e a anterior fica no histórico com superseded_by_id. Execuções com atividade permanecem imutáveis, com aviso e opção explícita de nova execução. Não iniciar preparação pendente desatualizada por commands.
- Substituir aula com atividade exige confirmação, policy control, lease da aba e versões atuais da sessão/workout. Manter ordem de locks workout → sessão para preparação/substituição. Nunca atualizar retrospectivamente títulos/timers de sessões encerradas. Versão nula significa correspondência legada desconhecida; não fazer backfill arbitrário.
- Formulários principais usam minutos inteiros + segundos adicionais opcionais. Converter uma vez no WorkoutRequest/WorkoutDurationInput; Actions e banco continuam em segundos, compilador em ms. Não converter dados já salvos. Intervalos/estações/preparação seguem em segundos. Preservar segundos legados com divisão inteira/resto e rejeitar unidades duplicadas/valores fracionários.
- Dashboard destaca identidade da box; não duplicar logo pequena no cabeçalho dessa página. Logo sempre contain, sem corte; múltiplos treinos do dia exigem escolha explícita.
- Gestão/Aula implementados conforme docs/coach-workspaces.md. /workspace é a entrada após autenticar; /manage e /coach compartilham auth/tenant/Actions/motor. URLs anteriores e nomes de rotas permanecem compatíveis. Não criar outro projeto.
- Fase 3.2: `Box::modalities()` reutiliza o catálogo global em `box_modalities`, par único e FKs. HTTP exige seleção não vazia no cadastro/configurações; nunca aceitar box_id. Factories e seeders escolhem explicitamente as modalidades. Backfill global é exceção restrita à migration inicial, não pode ser executado em cada request ou configuração.
- Modalidade desativada preserva dados e sessões com atividade. Bloquear novos treinos (inclusive draft), publicação, cópias individuais e novas preparações no backend. Cópia semanal filtra modalidades ativas. Registros desativados continuam consultáveis e identificados no calendário/histórico; edição é permitida como draft. Preparações sem atividade não podem iniciar enquanto desativadas. Reativar recupera operações sem recriar treinos.
- Escritas operacionais e configurações serializam pelo lock na Box autenticada antes do workout/sessão; manter ordem box → workout → sessão (comandos: box → sessão). Não introduzir locks inversos. Claim/poll não alteram a disponibilidade da modalidade.
- Cargas opcionais ficam em `workout_blocks.suggested_loads` (JSON), com equipment/rx/intermediate/scale/unit e limites explícitos. Nesta fase, formulário/validação permitem somente principal. Sem níveis preenchidos, normalizar null; preservar o texto "0". Unidade kg/lb só é acrescentada a números isolados, nunca converter ou anexar a descrições/pares.
- Cargas omitidas ao editar outros campos mantêm as referências do mesmo tipo de bloco; null ou três níveis vazios removem explicitamente. Duplicação preserva cargas. `timer_sessions.suggested_loads` é snapshot imutável: renderizar apenas cargas da etapa atual, ocultando seção vazia sem reservar espaço. Usar Blade escapado/textContent, nunca innerHTML.

- Fase 4: confirmação de outra aula após histórico encerrado exige new_session_confirmation + previous_session_id atual sob lock, nos dois endpoints de preparo. Repetição enquanto nova preparação está ativa deduplica; pedido atrasado após novo encerramento deve falhar. Ready e TV nunca iniciam timer/lease. Lista/ready normalizam sessões via SessionState, sem cálculo duplicado.
- Etapas opcionais: modo full permite qualquer subsequência não vazia de warmup/skill/workout ao publicar. main permite apenas workout. Não persistir etapas sem conteúdo nem acrescentar ausentes ao editar. Campos de etapas desabilitadas não são enviados; reindexar ativas e evitar colisões com nomes das desabilitadas. Confirmar remoção preenchida e preservar demais etapas/snapshots.
- Formatos: usar TimerConfiguration (LABELS, registry, settingKeys, defaults, parameterRules), sem filtros por modalidade/etapa. Circuito/estações podem existir em qualquer bloco/modalidade. Tempos/unidades e IDs persistidos continuam iguais; EMOM sem intervalo explícito usa 60 s. Não renomear registros legados.
- Apresentação: AdaptiveContent mede mudanças com ResizeObserver, até duas colunas ordenadas e agrupamentos indivisíveis. Fonte mínima de referência 22 px Full HD (escala com resolução). Não reduzir indefinidamente, cortar silenciosamente, usar scale indiscriminado ou reativar paginação automática. Excesso deve gerar aviso no cadastro/preview/ready e apresentação explicitamente indisponível na TV; coach mantém conteúdo completo acessível para reorganizar. Descrição persistida não é resumida automaticamente.
- Rodar também npm run test:presentation. Homologar 3/8/15/25 instruções com/sem cargas em Full HD/QHD/4K, linhas/títulos longos, fullscreen e excesso. Não alegar caber qualquer volume de texto. Não fazer deploy Hostinger nem tocar banco de produção sem aprovação explícita; não enviar public/hot. Ver docs/production-hotfix.md.
