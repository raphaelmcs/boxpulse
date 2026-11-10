# Hotfix local — apresentação adaptativa, etapas opcionais e formatos universais

Implementado e homologado localmente sobre a Fase 4. **Não houve deploy na Hostinger, acesso ao banco de produção ou alteração do domínio useboxpulse.com.** Nenhuma migration nova necessária: tabelas, IDs de formatos, dados de tempo e snapshots anteriores foram preservados.

## Diagnóstico e correções

| Problema | Causa | Correção |
| --- | --- | --- |
| Texto vira páginas ou ultrapassa a área | ContentPager dividia conteúdo, alternava páginas e usava um painel fixo com fontes grandes | AdaptiveContent mede a área e os grupos de instruções; ajusta espaços, fontes, proporção timer/exercícios e uma/duas colunas; não pagina ou alterna automaticamente |
| Aula completa exige três etapas | WorkoutDefinition comparava publicação à sequência completa; editor materializava todos os tipos | Publicação aceita qualquer subsequência não vazia de warmup/skill/workout. Formulário habilita etapas, confirma remoção preenchida, desabilita e reindexa campos sem colidir nomes; edição conserva somente etapas existentes |
| Formatos diferem por etapa/modalidade | TimerConfiguration.formats filtrava listas; estações eram proibidas em CrossFit e fora do principal | Mesmo catálogo universal para qualquer etapa/modalidade, estações onde necessárias, registry com rótulos/campos/defaults/regras. IDs existentes mantidos, incluindo repetitions legado |

## Persistência e motor

Etapas vazias não são gravadas; título/descrição/notas/cargas/estações são considerados conteúdo. Pelo menos uma etapa preenchida é necessária para publicar, na ordem aquecimento → técnica → principal. O simplificado continua somente principal. Duração principal continua em minutos no formulário e segundos no agregado; preparação/intervalos/estações continuam em segundos; compilador usa ms. Reindexar os arrays ativos impede buracos e colisões com os campos de etapas desabilitadas.

Todos os nove formatos pedidos estão disponíveis, além do legado circuito por repetições. EMOM sem intervalo explícito usa 60 s e normaliza esse default. Configurações inválidas continuam rejeitadas; descrição vazia não obriga a criar etapas artificiais. Alteração chama SaveWorkout e incrementa revisão normalmente. Sessões antigas não são modificadas; snapshots novos possuem só as etapas realmente salvas. Próxima/anterior continuam limitadas à quantidade real de timers.

Registry central: TimerConfiguration contém LABELS, settingKeys, defaults, parameterRules e registry. Os formulários recebem esse registro; a API fornece os rótulos para o painel. CompileTimer lê os mesmos defaults, sem trocar o contrato version=1 ou os cálculos do engine.js. Sem novos pacotes.

## Layout e limites de legibilidade

AdaptiveContent mede com DOM/ResizeObserver e agenda uma medição por frame quando conteúdo, fonte ou espaço mudam; não mede em cada tick do relógio. Há cache das dimensões e assinatura do conteúdo. O particionamento usa tempo linear, preserva a ordem e nunca fragmenta uma instrução para caber na coluna.

Tentativas limitadas: uma coluna confortável (32 px de referência), compacta (28), duas colunas com painel ampliado (28/25/22). Os tamanhos escalam com a resolução; mínimo de referência 22 px em 1920×1080, ~29 px em QHD e 44 px em 4K. O timer reduz moderadamente no modo amplo, preservando seu espaço. Não há transform:scale para encolher a apresentação.

Linhas comuns são instruções indivisíveis, com wrapping de linhas longas; cabeçalhos terminados em dois pontos e indicações de rounds permanecem ligados à próxima linha. Parágrafos separados por linha vazia conservam seus grupos integralmente; linhas indentadas continuam a instrução anterior. Não inserir parágrafos enormes se a intenção é distribuir exercícios entre colunas. Estações permanecem grupos completos com nome, métricas e descrição. Leitura: coluna esquerda de cima para baixo, depois direita. Texto é renderizado via textContent; descrições persistidas não são reescritas.

Não existe garantia por quantidade isolada: comprimento das linhas, agrupamentos, título, logo e cargas também consomem área. Foram validados 3/8/15/25 instruções típicas, não 25 parágrafos arbitrariamente extensos.

**Conteúdo excessivo:** o formulário, preview e ready oferecem verificação Full HD/QHD/4K e acesso explícito a reorganizar/resumir. Essa verificação é uma referência conservadora; a TV mede sua área real. Se nem o mínimo cabe, ela mostra um aviso explícito de apresentação indisponível, em vez de cortar parte dos exercícios, paginar ou reduzir indefinidamente. No coach, o conteúdo completo fica disponível em uma seção expansível para reorganização. O texto do banco permanece íntegro. Cargas não são apresentadas isoladamente como se o workout estivesse completo nesse estado. Não há alternância automática de painéis; isso é futuro e opcional.

## Arquivos principais

- `app/Workouts/WorkoutDefinition.php`: etapas opcionais, conteúdo e validação estrutural.
- `app/Workouts/TimerConfiguration.php`, `app/Timers/CompileTimer.php`, `app/Http/Resources/WorkoutSessionResource.php`: catálogo/defaults/regras/rótulos comuns.
- `resources/views/workouts/form.blade.php`, `components/workouts/block-fields.blade.php`, `resources/js/app.js`: seleção segura, confirmação, campos dinâmicos, unidades e reindexação.
- `resources/js/timers/adaptive-content.js`: grupos, equilíbrio de colunas, medição e limite.
- `resources/js/timers/presentation-check.js`, `components/workouts/presentation-check.blade.php`: conferência antes da apresentação e edição explícita.
- `resources/js/timers/session-app.js`, `components/sessions/board.blade.php`, `resources/css/app.css`: integração coach/TV, aviso e remoção dos controles de paginação.
- `workouts/preview.blade.php`, `classroom/ready.blade.php`, `classroom/today.blade.php`: preview/conferência e composição real das etapas.
- `tests/Feature/{OptionalWorkoutStagesTest,UniversalTimerFormatsTest}.php`, `tests/js/adaptive-content.test.js`, payloads de regressão de minutos/formatos e script `test:presentation` em package.json.
- AGENTS, README, architecture, database, timers, roadmap e documento de ambientes atualizados.

O arquivo ContentPager anterior foi removido e substituído pelo mecanismo adaptativo. Não restaurar paginação como fallback implícito.

## Homologação realizada

- Sete combinações de etapas × três modalidades; sequência, edição, publicação, preparação, navegação, restauração e preservação de snapshots.
- Dez formatos existentes × três etapas × três modalidades, com criação/edição/publicação, start/pause/resume, leitura restaurada, TV e finalização. Default EMOM explícito também testado.
- Onze testes JS de preservação de instruções, parágrafos, estações, linhas longas, cabeçalhos, ordem e equilíbrio; dezesseis testes do motor temporal preservados.
- Chrome: confirmação cancelada não remove conteúdo; Warm-up EMOM + WOD gera dois timers e não recria Skill ao editar.
- 24 cenários reais de TV: 3/8/15/25 exercícios × com/sem cargas × Full HD/QHD/4K. Todos os grupos medidos dentro do painel, sem scroll ou paginação. Com 25 instruções e cargas, referência de 25/33,3/50 px, respectivamente.
- Título e oito instruções longas nas três resoluções e fullscreen. Conteúdo de 350 instruções: aviso no editor/preview, aviso explícito na TV, sem scroll, conteúdo completo acessível no coach. Sem exceções JS no fluxo concluído.
- Regressão final PHP e comandos de build/qualidade registrados no README e roadmap após a execução final.

## Roteiro manual

1. `/workspace` → Gestão → treino completo. Habilitar Aquecimento e WOD, deixar Skill desabilitado. Cadastrar instruções e EMOM no Aquecimento, salvar/publicar. Editar novamente: somente as duas etapas ativas.
2. Desmarcar uma etapa preenchida e cancelar a confirmação: nada deve mudar. Confirmar e salvar: somente essa etapa é removida do treino atual; snapshots anteriores continuam intactos.
3. Repetir com cada combinação de um/dois/três blocos e nas três modalidades. Simplificado continua somente principal. Tentar publicar sem conteúdo: deve falhar.
4. Selecionar qualquer cronômetro em cada etapa; conferir unidades e campos específicos. Preparar no Modo Aula, executar, pausar, continuar e recarregar coach/TV. Não deve zerar nem criar outra sessão.
5. Descrever 3, 8, 15 e 25 instruções típicas. Na verificação de legibilidade, selecionar a resolução. Abrir TV, com e sem cargas: todos os exercícios simultâneos, sem navegação de páginas. Testar fullscreen e redimensionamento.
6. Título/linhas longas e grande volume de texto: aviso antes da apresentação; usar o link para reorganizar explicitamente. Confirmar que nenhum texto foi apagado. Validar o mínimo de fonte no equipamento físico e distância real da box.
7. Repetir com outra box; confirmar que workouts, sessões, logo, cores e modalidades não vazam entre tenants.

## Preparação do deploy — comandos para execução manual

Executar build e testes no ambiente local/homologação, nunca apontar RefreshDatabase ao banco da aplicação:

```sh
composer validate --no-check-publish
composer check-platform-reqs
vendor/bin/pint --dirty --format agent
vendor/bin/sail artisan test --compact
npm ci
npm run test:timers
npm run test:presentation
npm run build
```

Montar uma release com código e public/build/manifest.json + assets correspondentes. Não enviar `public/hot`, node_modules ou configurações `.env` locais. Preservar `.env`, storage e uploads do servidor. Na configuração do servidor, preservar APP_KEY, banco e credenciais existentes; conferir APP_ENV=production, APP_DEBUG=false e APP_URL=https://useboxpulse.com. Isso deve ser verificado na release autorizada, sem copiar o .env local.

Instalar vendor para a plataforma alvo em uma pasta de release/homologação:

```sh
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
composer check-platform-reqs
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
php artisan migrate:status
```

Esses comandos de preparação não foram executados na Hostinger. **Este hotfix não requer migrate.** Se houver migrations pendentes de outras fases, revisar cada uma e backup antes de qualquer aplicação autorizada; não usar fresh/wipe. Publicação e ativação da release exigem aprovação explícita. Após publicação autorizada, conferir login/ambientes, logo autenticada, duas etapas, formato novo em Warm-up, sessão em curso, TV real e assets versionados. Manter a release anterior disponível para retorno de código, sem rollback destrutivo de banco.


Validação final integrada: **284 testes PHP / 3.293 verificações em PHP 8.3 e Sail PHP 8.5; 27 testes JavaScript (16 timers + 11 apresentação). Build Vite, Pint, Composer, compilação Blade e diff check aprovados.**
