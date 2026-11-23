# Editor de treinos V2

## Edição e publicação

- Use **Formatar título** ou **Formatar exercício** para editar peso e cor. Em descrições e observações, selecione a linha no texto ou no controle **Linha**. A opção **Subtítulo** aplica um destaque e agrupa a linha com a instrução seguinte do mesmo parágrafo na TV.
- As setas movem a linha e sua formatação. Use **Inserir abaixo** ou **Remover linha** para reorganizar. As estações mantêm formatação no nome e nas instruções durante a reordenação.
- As cores rápidas incluem a primária e a secundária da box. A escolha grava o hexadecimal vigente, inclusive nos snapshots. A cor exibida pode ser clareada para manter contraste de pelo menos 4,5:1 no fundo escuro usado como referência. **Restaurar padrão** limpa somente o estilo selecionado.
- **Visualizar na TV** abre um modal com as etapas preenchidas e o conteúdo atual do formulário. **Salvar e pré-visualizar** abre a revisão persistida em outra aba.
- O autosave espera **1 segundo** após a última alteração. A remoção confirmada de uma etapa solicita gravação imediata. **Salvar** envia o rascunho pendente sem esperar o debounce. O editor indica alterações pendentes, gravação, confirmação, falhas e conflitos.
- A aba envia uma gravação por vez e mantém os campos editáveis. Cada envio registra versão esperada e UUID. Se a rede falhar, **Tentar novamente** reenvia a operação original e, depois da confirmação, salva as alterações mais recentes. Se houver conflito HTTP 409, a aba mantém seus campos e oferece abrir a revisão mais recente e baixar as alterações em JSON para consulta. A saída é sinalizada enquanto há alterações sem confirmação.
- Quando o treino está publicado, as edições vão para `workouts.editor_draft`. A programação, a preparação e as cópias continuam usando a revisão aprovada. O coach revisa e escolhe **Republicar treino**. A publicação valida o rascunho e aplica a revisão em transação. **Retornar a rascunho** retira o treino da programação e mantém a revisão de trabalho.

## Prévia da TV e dimensionamento

No editor, abra **Visualizar na TV**, junto aos botões de salvamento e publicação. Selecione uma etapa quando houver mais de uma preenchida. **Simular tela cheia** encaixa a composição de referência na janela. Feche pelo botão **Fechar** ou pela tecla Esc para voltar ao mesmo formulário. A prévia reflete as edições locais, inclusive antes do autosave.

A prévia, a TV e o painel do coach compartilham `sessions/board.blade.php` e `PresentationBoard`. A prévia e a TV também compartilham `sessions/screen.blade.php`, com logo, modalidade, cabeçalho, painel principal e etapas. A divisão horizontal usa 35% para cronômetro e 65% para treino, descontado o vão do grid. O modal mede uma composição 16:9 de referência a 1920 × 1080 em pixels CSS e enquadra os elementos HTML na janela. A TV mede automaticamente a área disponível na tela real. A organização do painel do coach e de telas em formato retrato pode mudar para preservar espaço.

`AdaptiveContent` mede os exercícios na coluna direita e as cargas abaixo do cronômetro de forma independente. Nos exercícios, testa por busca binária tamanhos equivalentes a 22–76 px no Full HD, com uma ou duas colunas e quebras reais. Grupos ficam inteiros e a leitura segue pela primeira coluna e depois pela segunda. As cargas usam [os modos de exibição da V3.1](library-v3-and-class-completion.md#apresentação-das-cargas-v31).

Se o mínimo não couber, o painel exibe um aviso de capacidade. O modal identifica todas as etapas com excesso e oferece um atalho para edição. O painel do coach disponibiliza o conteúdo completo para consulta. A TV e a composição no modal mantêm a divisão do painel, sem recortar trechos, rolar o conteúdo ou trocar páginas.

A prévia trabalha com dados do formulário e a mesma projeção visual do cronômetro em repouso. Em **Pronto para a aula**, ela usa a configuração do snapshot. A simulação dispensa polling, lease e comandos de sessão. Uma aula aberta continua com sua própria execução, enquanto o editor pode mostrar o rascunho.

A suíte do Chrome compara diretamente o painel do editor e a rota da TV em Full HD. Na TV, repete a verificação em QHD e 4K, incluindo treinos de 3, 8, 15 e 25 linhas, títulos longos, estilos, contraste, cargas e aviso de excesso com 350 linhas. Verifica também a abertura e o fechamento do modal, a tela cheia, a navegação por etapas, a atualização antes do salvamento e a preservação de uma aula ativa.

Componentes desta evolução:

- [Tela compartilhada](../resources/views/components/sessions/screen.blade.php) e [painel da aula](../resources/views/components/sessions/board.blade.php).
- [Controles da prévia](../resources/views/components/workouts/presentation-check.blade.php) e [leitura do formulário](../resources/js/timers/presentation-check.js).
- [Projeção visual compartilhada](../resources/js/timers/presentation-board.js), [configuração visual do rascunho](../resources/js/timers/preview-timer.js) e [medição adaptativa](../resources/js/timers/adaptive-content.js).
- [Estilos da apresentação](../resources/css/app.css) e [comparação no navegador](../tests/js/presentation-browser.test.js).

O zoom do navegador e telas de outras proporções mudam o espaço disponível na TV real. Confira a distância de leitura na TV da box.

## Configuração no início da aula

- No **Modo Aula**, escolha o treino em **Aulas de hoje** e configure as opções antes de **Preparar aula**. O mesmo controle aparece em **Iniciar apresentação** na Gestão.
- **Por etapa** usa a preparação cadastrada em cada bloco, com 10 s quando ela estiver ausente. **Sem contagem**, **10 segundos**, **20 segundos**, **30 segundos** e **Personalizar** (0–60 s) aplicam uma única preparação às etapas cronometradas da nova sessão. Etapas livres dispensam contagem.
- Por padrão, o coach comanda cada troca. **Avançar automaticamente para a próxima etapa ao concluir o cronômetro** usa a conclusão por tempo e respeita a preparação da próxima etapa. Uma etapa livre aguarda o coach para prosseguir.
- Preferências antigas do treino continuam armazenadas e preenchem as opções iniciais. Alterar as opções na preparação aplica a escolha somente à nova sessão. Ao continuar uma aula, vale a configuração do snapshot, inclusive para registros legados. O refinamento não adiciona migrations.
- As opções usam o [componente de preparação](../resources/views/components/sessions/preparation-options.blade.php), o [fluxo da Gestão](../resources/views/components/sessions/prepare.blade.php), a [lista do Modo Aula](../resources/views/classroom/today.blade.php) e a [validação compartilhada](../app/Http/Requests/PrepareClassroomRequest.php).

### Arquivos do refinamento

- Editor e modal em [workouts/form.blade.php](../resources/views/workouts/form.blade.php), [workouts/presentation-check.blade.php](../resources/views/components/workouts/presentation-check.blade.php), [presentation-check.js](../resources/js/timers/presentation-check.js) e [app.css](../resources/css/app.css).
- Preparação em [classroom/today.blade.php](../resources/views/classroom/today.blade.php), [sessions/prepare.blade.php](../resources/views/components/sessions/prepare.blade.php), [sessions/preparation-options.blade.php](../resources/views/components/sessions/preparation-options.blade.php) e [app.js](../resources/js/app.js).
- Recebimento das opções em [PrepareClassroomRequest](../app/Http/Requests/PrepareClassroomRequest.php), [ClassroomController](../app/Http/Controllers/ClassroomController.php) e [WorkoutSessionController](../app/Http/Controllers/WorkoutSessionController.php).
- Regressão em [ClassroomTest](../tests/Feature/ClassroomTest.php), [WorkoutSessionTest](../tests/Feature/WorkoutSessionTest.php), [WorkoutEditorV2Test](../tests/Feature/WorkoutEditorV2Test.php) e [presentation-browser.test.js](../tests/js/presentation-browser.test.js).
- Guias de uso neste documento, no [README](../README.md) e em [Timers](timers.md).

### Homologação local da prévia e da preparação

A regressão deste refinamento passou com 339 testes PHP, 3.989 asserções e 51 testes JavaScript. No Chrome headless, foram verificados o modal em desktop e celular, a preparação personalizada, a paridade em Full HD e a adaptação da TV em QHD e 4K. A TV física e o áudio ficam para a validação na box.

1. Abra um treino novo e clique em **Visualizar na TV**. Confira a orientação para preencher uma etapa. Feche o modal pelo botão e por Esc.
2. Preencha Aquecimento, Técnica e WOD. Abra o modal antes de salvar, troque de etapa e confira logo, cores, texto e cronômetro em repouso. Feche e verifique os campos. Repita com uma só etapa preenchida e com conteúdo acima da capacidade.
3. Publique e prepare a aula pelo **Modo Aula**. Teste uma contagem geral com avanço automático ligado e outra aula com **Por etapa** e avanço desligado. Abra o controle e comande o início. Verifique a preparação da próxima etapa e a espera pelo coach em uma etapa livre.
4. Com a TV aberta, edite o treino publicado e visualize o rascunho. Compare com a TV em reprodução. Confira legibilidade e ausência de rolagem na janela real. Os testes automatizados e o comando de navegador estão em [Testes locais](#testes-locais).

## Etapas opcionais e programação semanal

Os atalhos genéricos enviavam o usuário para `workouts.create` sem indicar a estrutura. O controller usava `main` como padrão, então o Blade renderizava somente o principal. A comparação com a `main` confirmou que esse caminho já existia. O catálogo de etapas e a validação do modo completo continuavam aceitando Aquecimento, Técnica e WOD.

O cadastro geral agora abre em `full`. A opção **Somente treino principal** segue pelo link explícito `structure=main`. **Adicionar outras etapas** expande um treino simplificado no mesmo editor. É possível usar qualquer uma das sete combinações não vazias. A edição conserva a ordem Aquecimento → Técnica → WOD, independentemente da ordem dos cliques.

Uma etapa habilitada com configurações de cronômetro, mas ainda sem conteúdo, é guardada no JSON `editor_draft` e reabre habilitada após o autosave. Os blocos canônicos e a apresentação recebem as etapas preenchidas. Remover uma etapa retira seus dados do próximo rascunho salvo. A publicação continua sendo uma ação explícita e as sessões em andamento mantêm seu snapshot.

A rota **GET `/schedule`** recebe `date=AAAA-MM-DD`, `month=AAAA-MM` e `modality=slug`. `date` define a seleção, a semana começa na segunda-feira, e `month` controla o calendário independentemente. Ao selecionar um dia ou navegar pela semana, o calendário acompanha o mês da nova seleção. As setas do mês mantêm `date` e o filtro. **Hoje** volta à data civil da box. A cópia semanal mantém o filtro no redirecionamento, com o mesmo conjunto de origem de antes.

`ScheduleController` reutiliza a consulta de treinos com escopo de box para a lista semanal e para os marcadores. Uma consulta `DISTINCT` por par data/modalidade cobre toda a grade mensal mostrada. Os marcadores consideram modalidades habilitadas, filtro e visibilidade da Gestão, incluindo rascunhos. Um ponto indica treinos em uma modalidade e dois pontos indicam mais de uma. A semana conserva a consulta de treinos de modalidades desativadas com o aviso já usado pela Gestão. Os atalhos de cadastro e a navegação após salvar ou excluir levam à data do treino.

Arquivos principais desta entrega:

- [Programação semanal](../resources/views/schedule/index.blade.php), [calendário mensal](../resources/views/components/schedule/month-calendar.blade.php), [consulta e navegação](../app/Http/Controllers/ScheduleController.php) e [estilos responsivos](../resources/css/app.css).
- [Editor de treinos](../resources/views/workouts/form.blade.php), [controles de etapas](../resources/js/app.js), [prévia da TV](../resources/js/timers/presentation-check.js), [validação](../app/Workouts/WorkoutDefinition.php) e [persistência do rascunho](../app/Actions/SaveWorkout.php).
- A entrada, a revisão e o retorno à semana passam por [WorkoutController](../app/Http/Controllers/WorkoutController.php), [WorkoutPreviewController](../app/Http/Controllers/WorkoutPreviewController.php), [Workout](../app/Models/Workout.php), [autosave da interface](../resources/js/workout-editor.js), [navegação da Gestão](../resources/views/components/workouts/navigation.blade.php), [detalhe do treino](../resources/views/workouts/show.blade.php) e [validação da cópia semanal](../app/Http/Requests/CopyWeekRequest.php).
- [Testes da programação](../tests/Feature/WorkoutProgrammingTest.php), [testes do editor](../tests/Feature/WorkoutEditorV2Test.php) e [homologação no Chrome](../tests/js/presentation-browser.test.js).

### Homologação local da programação

A suíte final passou com 329 testes PHP, 3.705 verificações e 51 testes JavaScript. O Chrome headless conferiu as cinco larguras, navegação com filtro, retorno à data salva, reabertura e remoção de etapa, sessão ativa e paridade da prévia com a TV.

1. Abra **Programação semanal**, aplique uma modalidade e avance de dezembro para janeiro. Confira que a semana continua aberta. Clique em uma data de janeiro, confira a segunda e o domingo nos cards, recarregue e use **Hoje**.
2. Pelo card de uma data, abra **Adicionar treino**. Ative Aquecimento e Técnica, preencha apenas o Aquecimento e ajuste o timer da Técnica. Aguarde o autosave e recarregue. A Técnica deve voltar habilitada com o timer escolhido e ficar fora da prévia enquanto estiver vazia.
3. Remova a Técnica, aguarde salvar e recarregue. Publique apenas o Aquecimento e confira a prévia e a TV. Use o link **Programação semanal** no editor para retornar à data do treino e conferir o marcador.
4. Rode a suíte PHP no Sail, faça o build e execute `npm run test:presentation:browser`. O teste usa Chrome headless e o MySQL `testing` para verificar o layout em 1920 × 1080, 1440 × 900, 1366 × 768, 768 × 1024 e 390 × 844.

## Biblioteca de cargas

Use os botões de equipamento por bloco. Os valores só são preenchidos quando o coach seleciona e aplica uma referência. **Restaurar sugestão** recupera o preset original disponível na biblioteca, com confirmação se houver valores preenchidos. Campos legados aparecem em **Campos de referência anteriores**. As referências antigas e novas são exibidas quando há pesos preenchidos.

A ordem dos pares é sempre **masculino / feminino**. Os presets da biblioteca usam kg. As cargas escritas livremente preservam a unidade digitada. Os exemplos vêm de prescrições em treinos específicos e devem ser ajustados ao volume, ao movimento e ao estímulo da aula.

| Equipamento e movimento | RX (kg) | Intermediate (kg) | Scale (kg) | Referência |
| --- | --- | --- | --- | --- |
| Dumbbell Snatch | 22,5 / 15 | — | 15 / 10 | [CrossFit Open 24.1, RX e Scaled de 16 a 54 anos](https://games-assets.crossfit.com/s3fs-public/2024-02/CFG24%20Open%2024.1%20Scorecard_aosdifw0_1.pdf) |
| Wall Ball | 9 / 6 | — | 6 / 4 | [CrossFit Open 26.1, RX e Scaled de 16 a 54 anos](https://games-assets.crossfit.com/s3fs-public/2026-02/CFG26%20Open%2026.1%20Scorecard_V9.pdf) |
| Barbell · Thruster, primeira série | 43 / 29 | 29 / 20 | 20 / 15 | [CrossFit Open 25.2, RX e Scaled](https://games-assets.crossfit.com/s3fs-public/2025-03/CFG25%20Open%2025.2%20Scorecard_V7_edcvfrtgb_4.pdf), [Foundations](https://games-assets.crossfit.com/s3fs-public/2025-03/CFG25%20Open%2025.2%20Scorecard_Foundations_V4_eruawihfajknk.pdf) |

A biblioteca-base é compartilhada e somente para leitura. Administradores podem cadastrar e editar referências privadas da box, ativá-las ou desativá-las. Coaches da mesma box podem usar os presets ativos e incluir referências livres no treino com **Outros**. A Biblioteca V3 organiza referências por movimento e mantém presets genéricos no fallback manual, com confirmação. Veja o [guia da Biblioteca V3](library-v3-and-class-completion.md).

## Análise local

**Analisar treino e sugerir cargas** examina o bloco selecionado, incluindo título, descrição, observações e estações, mesmo antes de salvar. `WorkoutLoadAnalyzer` usa aliases locais e consulta somente os presets acessíveis à box. O resultado identifica movimento, equipamento, localização do texto, valores explícitos com kg ou lb, porcentagens e referências disponíveis. Casos com múltiplos pesos incompatíveis ou unidades sem associação clara ficam para revisão. Atualize a análise se o texto mudar.

Na revisão, escolha a origem do valor, edite os níveis e marque somente os itens que deseja aplicar. **Aplicar sugestões selecionadas** atualiza o rascunho e pede confirmação antes de substituir uma referência preenchida. Prescrição do texto, preset da biblioteca e edição manual têm origens registradas separadamente. Percentuais de 1RM são exibidos como relativos. A análise local não calcula peso a partir de percentuais, repetições, distância ou tempo. Nenhum serviço externo recebe o conteúdo.

## Arquitetura e migrations

- `SaveWorkout`, `PublishWorkout` e `WorkoutDefinition` mantêm o contrato canônico de validação e publicação. `edit_version` coordena o editor sob lock da box e do treino. `content_version` continua identificando o conteúdo canônico associado aos snapshots. UUIDs permitem repetir a gravação confirmada sem reaplicá-la.
- `WorkoutTextStyle` valida propriedades de exibição e associa metadados à posição da linha no texto original. `workout-text.js` acompanha inserções e remoções. Blade e `AdaptiveContent` renderizam texto escapado e estilos permitidos.
- `LoadPreset` usa `TenantModel`. `LoadLibrary` reúne presets por box e referências-base. `WorkoutLoadAnalyzer` recebe dados simples de um bloco e devolve resultados revisáveis, permitindo substituir o provedor no futuro. `workout-loads.js` aplica somente as seleções aprovadas.
- `2026_11_23_135715_add_editor_v2_to_workouts_and_sessions` adiciona rascunho, versões, UUIDs, opções de apresentação e metadados de estilo/cargas aos agregados e snapshots. `2026_11_23_135716_create_load_presets_table` cria a tabela privada de presets. As migrations não modificam textos, cargas ou sessões legadas. A biblioteca-base é distribuída em código e dispensa seed em produção.

## Testes locais

```sh
docker compose exec -T laravel.test php artisan config:clear --no-interaction
docker compose exec -T laravel.test php artisan test --compact
docker compose exec -T laravel.test composer check-platform-reqs
npm run test:editor
npm run test:timers
npm run test:presentation
npm run build
```

A regressão do Editor V2 original registrou 320 testes PHP com 3.575 verificações no Sail e 37 testes JavaScript. O build Vite, o Pint e a verificação de plataforma do Composer passaram. No Chrome headless, a homologação cobriu conflito de abas, falha de rede e reenvio, preset privado, restauração sob confirmação e editor a 390 px. Em Full HD, QHD e 4K, a TV exibiu 3, 8, 15 e 25 linhas com e sem cargas. Foram verificados título e instruções longos, fullscreen e aviso de excesso com 350 linhas. A validação de TV física e áudio permanece pendente.

Para medir prévia e TV com o navegador, execute após o build e a suíte PHP. O teste inicia um servidor temporário no Sail, confirma MySQL `testing` e cria seus próprios dados de teste. Requer Chrome/Chromium no host Linux e acesso ao Docker local. Quando necessário, use `BOXPULSE_CHROME=/caminho/para/chromium`.

```sh
npm run test:presentation:browser
```

Para validar o fluxo no navegador, abra o editor de um publicado em duas abas. Formate uma linha, aplique uma carga revisada e confira o autosave na primeira aba. Edite e tente salvar na segunda. A primeira revisão publicada deve continuar na programação até republicar. Prepare a aula, confira as cargas e estilos na TV, depois edite e compare com o snapshot já iniciado. Verifique legibilidade, fullscreen e áudio por gesto na TV da box.

## Preparar a release para Hostinger Premium

1. Antes de uma janela aprovada, validar o backup e a restauração do MySQL e conservar uma cópia da release anterior, dos uploads e das configurações do servidor. Confirmar que o PHP do site e da CLI atende ao `composer.lock`.
2. Empacotar o código com `public/build/manifest.json` e os assets do mesmo build. Preservar `.env`, `APP_KEY` e o diretório de uploads existentes. Excluir `public/hot`, `node_modules` e configurações locais do pacote. Validar o document root em `public/` e HTTPS.
3. Na pasta de release, instalar dependências de produção e revisar as migrations pendentes e o SQL. Executar a aplicação das migrations somente na janela aprovada, antes de liberar o novo código. Usar `php artisan migrate --force --no-interaction` após a revisão. O catálogo compartilhado não exige seed.

```sh
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
composer check-platform-reqs
php artisan migrate:status --no-interaction
php artisan migrate --pretend --force --no-interaction
```

4. Com o código e o esquema alinhados, executar `php artisan optimize:clear --no-interaction`, `php artisan config:cache --no-interaction` e `php artisan view:cache --no-interaction`. Confirmar login, logo, calendário, rascunho separado, análise e preparação/TV. Se precisar retornar à release anterior, preservar as colunas adicionais e os dados do editor para uma recuperação posterior.

## Arquivos adicionados e modificados

<details>
<summary>Aplicação (25)</summary>

- [app/Actions/DuplicateWorkout.php](../app/Actions/DuplicateWorkout.php)
- [app/Actions/PrepareWorkoutSession.php](../app/Actions/PrepareWorkoutSession.php)
- [app/Actions/PublishWorkout.php](../app/Actions/PublishWorkout.php)
- [app/Actions/SaveWorkout.php](../app/Actions/SaveWorkout.php)
- [app/Http/Controllers/LoadLibraryController.php](../app/Http/Controllers/LoadLibraryController.php)
- [app/Http/Controllers/WorkoutController.php](../app/Http/Controllers/WorkoutController.php)
- [app/Http/Controllers/WorkoutPreviewController.php](../app/Http/Controllers/WorkoutPreviewController.php)
- [app/Http/Controllers/WorkoutStatusController.php](../app/Http/Controllers/WorkoutStatusController.php)
- [app/Http/Requests/AnalyzeWorkoutRequest.php](../app/Http/Requests/AnalyzeWorkoutRequest.php)
- [app/Http/Requests/LoadPresetRequest.php](../app/Http/Requests/LoadPresetRequest.php)
- [app/Http/Requests/WorkoutRequest.php](../app/Http/Requests/WorkoutRequest.php)
- [app/Http/Resources/WorkoutSessionResource.php](../app/Http/Resources/WorkoutSessionResource.php)
- [app/Models/LoadPreset.php](../app/Models/LoadPreset.php)
- [app/Models/TimerSession.php](../app/Models/TimerSession.php)
- [app/Models/Workout.php](../app/Models/Workout.php)
- [app/Models/WorkoutBlock.php](../app/Models/WorkoutBlock.php)
- [app/Models/WorkoutStation.php](../app/Models/WorkoutStation.php)
- [app/Support/BoxTheme.php](../app/Support/BoxTheme.php)
- [app/Workouts/LoadLibrary.php](../app/Workouts/LoadLibrary.php)
- [app/Workouts/SuggestedLoads.php](../app/Workouts/SuggestedLoads.php)
- [app/Workouts/WorkoutDefinition.php](../app/Workouts/WorkoutDefinition.php)
- [app/Workouts/WorkoutLoadAnalyzer.php](../app/Workouts/WorkoutLoadAnalyzer.php)
- [app/Workouts/WorkoutTextStyle.php](../app/Workouts/WorkoutTextStyle.php)
- [bootstrap/app.php](../bootstrap/app.php)
- [routes/web.php](../routes/web.php)

</details>

<details>
<summary>Banco (5)</summary>

- [database/factories/LoadPresetFactory.php](../database/factories/LoadPresetFactory.php)
- [database/migrations/2026_11_23_135715_add_editor_v2_to_workouts_and_sessions.php](../database/migrations/2026_11_23_135715_add_editor_v2_to_workouts_and_sessions.php)
- [database/migrations/2026_11_23_135716_create_load_presets_table.php](../database/migrations/2026_11_23_135716_create_load_presets_table.php)
- [database/seeders/DatabaseSeeder.php](../database/seeders/DatabaseSeeder.php)
- [database/seeders/LoadPresetSeeder.php](../database/seeders/LoadPresetSeeder.php)

</details>

<details>
<summary>Editor e apresentação (26)</summary>

- [resources/css/app.css](../resources/css/app.css)
- [resources/js/app.js](../resources/js/app.js)
- [resources/js/timers/adaptive-content.js](../resources/js/timers/adaptive-content.js)
- [resources/js/timers/presentation-check.js](../resources/js/timers/presentation-check.js)
- [resources/js/timers/session-app.js](../resources/js/timers/session-app.js)
- [resources/js/timers/suggested-loads.js](../resources/js/timers/suggested-loads.js)
- [resources/js/workout-autosave.js](../resources/js/workout-autosave.js)
- [resources/js/workout-editor.js](../resources/js/workout-editor.js)
- [resources/js/workout-loads.js](../resources/js/workout-loads.js)
- [resources/js/workout-text.js](../resources/js/workout-text.js)
- [resources/views/classroom/ready.blade.php](../resources/views/classroom/ready.blade.php)
- [resources/views/components/sessions/board.blade.php](../resources/views/components/sessions/board.blade.php)
- [resources/views/components/sessions/prepare.blade.php](../resources/views/components/sessions/prepare.blade.php)
- [resources/views/components/sessions/snapshot-block.blade.php](../resources/views/components/sessions/snapshot-block.blade.php)
- [resources/views/components/workouts/block-display.blade.php](../resources/views/components/workouts/block-display.blade.php)
- [resources/views/components/workouts/block-fields.blade.php](../resources/views/components/workouts/block-fields.blade.php)
- [resources/views/components/workouts/load-library.blade.php](../resources/views/components/workouts/load-library.blade.php)
- [resources/views/components/workouts/load-tools.blade.php](../resources/views/components/workouts/load-tools.blade.php)
- [resources/views/components/workouts/presentation-check.blade.php](../resources/views/components/workouts/presentation-check.blade.php)
- [resources/views/components/workouts/station-fields.blade.php](../resources/views/components/workouts/station-fields.blade.php)
- [resources/views/components/workouts/styled-lines.blade.php](../resources/views/components/workouts/styled-lines.blade.php)
- [resources/views/components/workouts/suggested-loads.blade.php](../resources/views/components/workouts/suggested-loads.blade.php)
- [resources/views/components/workouts/text-tools.blade.php](../resources/views/components/workouts/text-tools.blade.php)
- [resources/views/workouts/form.blade.php](../resources/views/workouts/form.blade.php)
- [resources/views/workouts/preview.blade.php](../resources/views/workouts/preview.blade.php)
- [resources/views/workouts/show.blade.php](../resources/views/workouts/show.blade.php)

</details>

<details>
<summary>Testes e documentação (13)</summary>

- [AGENTS.md](../AGENTS.md)
- [README.md](../README.md)
- [docs/architecture.md](../docs/architecture.md)
- [docs/database.md](../docs/database.md)
- [docs/roadmap.md](../docs/roadmap.md)
- [docs/timers.md](../docs/timers.md)
- [docs/workout-editor-v2.md](../docs/workout-editor-v2.md)
- [package.json](../package.json)
- [tests/Feature/LoadLibraryTest.php](../tests/Feature/LoadLibraryTest.php)
- [tests/Feature/WorkoutEditorV2Test.php](../tests/Feature/WorkoutEditorV2Test.php)
- [tests/Unit/WorkoutLoadAnalyzerTest.php](../tests/Unit/WorkoutLoadAnalyzerTest.php)
- [tests/js/adaptive-content.test.js](../tests/js/adaptive-content.test.js)
- [tests/js/workout-editor.test.js](../tests/js/workout-editor.test.js)

</details>
