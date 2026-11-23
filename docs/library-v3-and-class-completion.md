# Biblioteca de Movimentos e Cargas V3 e finalização de aulas

## Catálogo e armazenamento

- O [catálogo-base](../app/Workouts/MovementCatalog.php) fornece os movimentos iniciais de barra, halter, kettlebell, bola, sandbag, trenó e anilha. Cada entrada tem chave estável, nome canônico e normalizado, equipamento, modalidades, aliases, variação quando aplicável e estado ativo.
- Movimentos personalizados ficam em `load_movements`. O [Form Request](../app/Http/Requests/LoadMovementRequest.php), a [Action](../app/Actions/SaveLoadMovement.php) e o índice único validam o tenant e evitam duplicidade de nome por equipamento.
- A tabela `load_presets` guarda nome, contexto opcional, referência de origem, observações e os seis pesos em kg. A FK composta `box_id, movement_id` vincula movimentos privados. Presets da biblioteca-base e registros antigos usam os nomes de movimento e equipamento. Múltiplos presets podem coexistir. As referências da biblioteca-base podem servir de ponto de partida para uma cópia editável na box.
- A [API](../app/Http/Controllers/LoadLibraryController.php) reaproveita as rotas de consulta, presets e análise e acrescenta `POST /load-library/movements` e `PUT /load-library/movements/{movement}`. A leitura respeita o tenant e as escritas exigem administrador.
- As [Configurações da Box](../resources/views/settings/box.blade.php) reutilizam o [componente do editor](../resources/views/components/workouts/load-library.blade.php). Filtre por equipamento, busque por nome ou alias e selecione a variação desejada. É possível editar, desativar e restaurar os pesos da sugestão-base disponível para aquele movimento.

## Interpretação e aplicação

O [analisador local](../app/Workouts/WorkoutLoadAnalyzer.php) avalia título, descrição, observações e estações. Correspondências completas têm prioridade sobre nomes dentro do movimento, como Clean & Jerk sobre Clean. A análise usa aliases da própria box, identifica contexto e verifica a modalidade. Partes ambíguas pedem revisão e percentuais continuam como prescrições relativas a 1RM. A carga absoluta exige kg ou lb associados ao movimento, com exceção do campo numérico de carga de uma estação.

A revisão começa com a prescrição explícita do texto, seguida por valor manual compatível já registrado, preset específico da box e sugestão específica da biblioteca-base. Para presets da box, o contexto correspondente vem antes de um preset sem contexto. Cargas genéricas ficam disponíveis como fallback manual com confirmação. Percentuais, nomes ambíguos ou movimentos sem referência suficiente ficam sem valores propostos automaticamente.

O [editor](../resources/js/workout-loads.js) espera a seleção explícita para aplicar. O coach pode alterar valores e marcar carga individual, dupla por unidade ou total. O autosave guarda a revisão e preserva o publicado. [SuggestedLoads](../app/Workouts/SuggestedLoads.php) acrescenta metadados opcionais ao JSON existente. As sessões copiam os valores, então editar um preset depois preserva os snapshots anteriores. A [exibição de cargas](../resources/js/timers/suggested-loads.js) prioriza o movimento e usa a mesma medição de capacidade da TV.

## Finalizar e preparar outra aula

- **Próxima etapa** aparece quando existe uma etapa seguinte. **Finalizar etapa** permite concluir manualmente cronômetros livres, progressivos, regressivos ou pausados. Após concluir a última etapa, o painel habilita **Finalizar aula** para o controlador com lease válido.
- O [comando existente](../app/Actions/ControlWorkoutSession.php) valida policy, box, versão e lease e verifica posição e estado da última etapa sob lock. A operação grava a hora UTC, libera o vínculo ativo e preserva todos os timers. O mesmo UUID devolve o resultado confirmado. Um comando novo após encerrar recebe conflito.
- O [motor de estado](../app/Timers/SessionState.php) continua avançando entre etapas quando habilitado. Depois da última, espera o encerramento explícito. Sessões históricas encerradas permanecem terminais.
- O [painel do coach](../resources/views/sessions/control.blade.php) mostra o resumo e **Iniciar nova aula** após encerrar. O botão volta ao Modo Aula. Selecionar outro treino ou confirmar outra sessão do mesmo treino cria um cronômetro independente pelo fluxo existente.
- A TV vinculada à sessão mostra **Aula encerrada**. A [TV do Box](tv-do-box.md) mostra o encerramento com a identidade da box e volta à espera após `BOXPULSE_TV_END_SECONDS` (padrão 8 s, intervalo de 0 a 60 s). A próxima aula iniciada ocupa a tela automaticamente.

## Arquivos e cobertura

- Catálogo e presets em [LoadMovement](../app/Models/LoadMovement.php), [LoadPreset](../app/Models/LoadPreset.php), [LoadPresetRequest](../app/Http/Requests/LoadPresetRequest.php), [LoadLibrary](../app/Workouts/LoadLibrary.php) e [AnalyzeWorkoutRequest](../app/Http/Requests/AnalyzeWorkoutRequest.php), além dos componentes e rotas descritos acima.
- Apresentação na [tela compartilhada](../resources/views/components/sessions/screen.blade.php), na [TV de sessão](../resources/views/sessions/tv.blade.php), no [estado da TV do Box](../app/Http/Controllers/BoxTvController.php), na [configuração](../config/boxpulse.php) e na [sessão no navegador](../resources/js/timers/session-app.js).
- Testes em [LoadLibraryTest](../tests/Feature/LoadLibraryTest.php), [WorkoutLoadAnalyzerTest](../tests/Unit/WorkoutLoadAnalyzerTest.php), [WorkoutSessionTest](../tests/Feature/WorkoutSessionTest.php), [ClassroomTest](../tests/Feature/ClassroomTest.php), [BoxTvTest](../tests/Feature/BoxTvTest.php) e [homologação no Chrome](../tests/js/presentation-browser.test.js). As regressões de modalidades, etapas opcionais, formatos, versões e Editor V2 exercitam o encerramento pelo fluxo de duas ações.

## Migration e homologação local

A [migration aditiva](../database/migrations/2026_11_23_181551_add_movement_catalog_to_load_library.php) cria `load_movements` e adiciona campos opcionais a `load_presets`. Dispensa backfill de treinos, cargas e sessões. A finalização usa as colunas existentes.

A regressão passou com 357 testes PHP, 4.426 asserções e 52 testes JavaScript. O Chrome headless conferiu a biblioteca, o autosave, os botões reais do coach, a TV do Box e o layout em Full HD, QHD e 4K. A migration foi aplicada no MySQL local. Pint, build Vite e verificação de plataforma do Composer passaram. A homologação em TV física permanece pendente.

```sh
docker compose exec -T laravel.test php artisan migrate --pretend --path=database/migrations/2026_11_23_181551_add_movement_catalog_to_load_library.php --no-interaction
docker compose exec -T laravel.test php artisan migrate --path=database/migrations/2026_11_23_181551_add_movement_catalog_to_load_library.php --no-interaction
docker compose exec -T laravel.test php artisan config:clear --no-interaction
docker compose exec -T laravel.test php artisan test --compact
npm run test:editor
npm run test:timers
npm run test:presentation
npm run build
npm run test:presentation:browser
```

1. Em **Configurações da Box**, escolha **Barbell**, busque **Power Clean**, cadastre dois presets com contextos diferentes e salve uma carga decimal. Confira que outro movimento permanece separado. Cadastre um movimento com alias próprio.
2. No editor, analise uma descrição com Power Snatch, Clean & Jerk, DB Snatch com 22,5/15 kg e Front Squat a 70%. Veja a origem e o contexto, selecione o que deseja aplicar e aguarde o autosave. Compare o rascunho com a TV da sessão publicada.
3. Abra o controle e a TV do Box. Use **Próxima etapa** em uma etapa intermediária, finalize a última e confirme **Finalizar aula**. Repita com WOD livre e cronômetro regressivo. Confira que recarregar conserva o encerramento.
4. Selecione **Iniciar nova aula**, escolha um treino e confirme quando houver histórico. Inicie a nova sessão e confira a atualização na TV. Confira legibilidade e áudio na TV física.

## Apresentação das cargas V3.1

Coach, TV e modal reaproveitam [board.blade.php](../resources/views/components/sessions/board.blade.php), [PresentationBoard](../resources/js/timers/presentation-board.js) e o [painel de cargas](../resources/views/components/workouts/suggested-loads.blade.php). O [mesmo card Blade](../resources/views/components/workouts/suggested-load-row.blade.php) serve ao HTML inicial e ao template atualizado em JavaScript. O [CSS compartilhado](../resources/css/app.css) mantém os 35% para cronômetro e cargas e os 65% para instruções em telas horizontais. A tela estreita do coach empilha os dois painéis.

| Categoria | Fundo | Rótulo e valor |
| --- | --- | --- |
| RX | `#303039` | `#F1F5F9` |
| INTER | `#2A3526` | `#EAC54F` |
| SCALE | `#26354D` | `#82B7FF` |

O [renderizador](../resources/js/timers/suggested-loads.js) monta um grupo por referência na ordem gravada, preserva o texto e a unidade informada e oculta o painel em etapas sem cargas. Uma referência antiga sem movimento usa a identificação de equipamento ou **Carga geral**. A mudança de etapa já entrega o snapshot correto. A prévia recebe o conteúdo atual do editor. O motor e os dados persistidos conservam seus contratos.

[AdaptiveContent](../resources/js/timers/adaptive-content.js) mede o espaço abaixo do cronômetro na TV. Reduz o espaçamento e oferece duas colunas de movimentos quando necessário, mantendo juntos os cards de cada movimento. O timer mantém sua escala. Se o painel de cargas ultrapassar a capacidade, a prévia identifica a etapa e oferece um atalho para revisar as cargas. Na TV, o aviso ocupa o lugar do painel de cargas e mantém visíveis o timer e os exercícios que couberem. No coach, a coluna pode crescer para manter as cargas acessíveis. A TV segue sem rolagem ou paginação.

### Homologação visual

A regressão passou com 357 testes PHP, 4.428 asserções e 54 testes JavaScript. O Chrome headless confirmou a paleta, a troca de etapa, o 35/65 em Full HD, QHD e 4K, oito movimentos com cargas curtas e o aviso para doze referências com descrições longas. A [cobertura de snapshots legados](../tests/Feature/SuggestedLoadsTest.php), os [testes de apresentação](../tests/js/adaptive-content.test.js) e a [verificação no navegador](../tests/js/presentation-browser.test.js) cobrem o painel e a [prévia](../resources/js/timers/presentation-check.js).

1. Em um WOD, cadastre cargas RX, INTER e SCALE para dois movimentos do mesmo equipamento e uma referência legada. Abra **Visualizar na TV** e compare com o painel do coach e a TV. Confira os nomes, as unidades e o contraste.
2. Avance para uma etapa sem cargas e depois para outra com valores distintos. Confira que o painel some e volta com os valores da etapa. Observe que as instruções permanecem na coluna direita.
3. Abra um WOD com mais movimentos e outro cujo volume de cargas ultrapasse a capacidade. Confira a compactação, o aviso no modal e a leitura do cronômetro. Repita na TV em Full HD, QHD e 4K.
4. Para capturas do [teste em Chrome](../tests/js/presentation-browser.test.js), faça o build e execute `BOXPULSE_SCREENSHOT_DIR=/tmp/boxpulse-cargas npm run test:presentation:browser`. Confirme legibilidade à distância na TV física.
