# TV do Box

O administrador abre **Configurações da Box → TV do Box** e escolhe **Gerar link da TV**. Depois pode copiar o endereço, abrir a TV ou gerar um novo link. A substituição exige confirmação e a TV que estiver usando o endereço revogado pede o link atualizado. A geração inicial é explícita e funciona também para boxes cadastradas antes da migration.

## Rotas e componentes

| Rota | Acesso e função |
| --- | --- |
| `GET /tv` | Página pública estática. O segredo está no fragmento do endereço, como `/tv#<segredo>`. |
| `GET /tv/state` | Bearer da TV. Entrega identidade e o snapshot visual da sessão exibida, ou o estado de espera. |
| `GET /tv/logo` | Bearer da TV. Entrega a imagem raster da box verificada. |
| `POST /box/settings/tv` | Administrador autenticado, com CSRF e confirmação para substituir um link existente. |
| `GET /box/settings/tv/presence` | Administrador autenticado. Entrega o estado da última comunicação da TV. |
| `GET /box/settings` | Rota existente que passa a exibir a seção e o link atual. |

- [Rotas](../routes/web.php), [verificação da capacidade](../app/Http/Middleware/ResolveBoxTv.php), [cabeçalhos](../app/Http/Middleware/ProtectBoxTvResponse.php) e [limites por IP e por administrador](../app/Providers/AppServiceProvider.php).
- [Rotação transacional](../app/Actions/RotateBoxTvLink.php), [link e presença](../app/Support/BoxTvLink.php), [tela de configurações](../resources/views/settings/box.blade.php) e [logo compartilhada](../app/Support/BoxLogo.php).
- [Leitura da sessão](../app/Actions/ReadBoxTvState.php), [resposta pública](../app/Http/Controllers/BoxTvController.php) e [serialização de apresentação](../app/Http/Resources/SessionPresentationResource.php).
- [Tela compartilhada](../resources/views/components/sessions/screen.blade.php), [aplicação de sessão](../resources/js/timers/session-app.js), [polling](../resources/js/timers/polling-transport.js) e [relógio](../resources/js/timers/engine.js).

## Token e isolamento

O segredo tem 256 bits gerados por `random_bytes`, representados por 64 caracteres hexadecimais. O banco guarda um hash SHA-256 único para busca e verificação e uma cópia criptografada com a chave da aplicação para exibição ao administrador. As colunas ficam ocultas na serialização da box. A rotação grava os dois valores sob lock da box. Novas requisições com o segredo antigo recebem HTTP 404.

O navegador mantém o fragmento no endereço para permitir favoritos e recarregamento. O fragmento fica fora da linha da requisição e do Referer. O JavaScript envia o Bearer apenas para estado e logo, na mesma origem, sem cookies. O middleware retira o cabeçalho do objeto da requisição após lê-lo e limpa o contexto da box ao terminar. O endereço dá acesso à apresentação para quem o possui. Em ambiente de publicação, preservar HTTPS, encaminhar `Authorization` ao PHP e excluir esse cabeçalho e os valores do formulário administrativo de logs e analytics. A regra existente do Apache já encaminha o cabeçalho.

As rotas públicas têm limite por IP, cache privado com `no-store`, `no-referrer`, bloqueio de incorporação e cabeçalhos de não indexação. O Bearer não cria login e não autoriza endpoints de configuração, histórico, edição, lease ou comandos. O payload visual contém o snapshot da aula em exibição. Campos de autenticação, controle, histórico, usuários e origem editorial ficam nas rotas autenticadas.

## Comportamento

- A TV aguarda um comando de início do coach. Havendo mais de uma aula da box, mostra a de início mais recente, desempatada pelo ID. Uma preparação sem início não toma a tela. A regra também funciona com sessões anteriores à criação do link.
- A TV usa o layout 35/65 e mostra a preparação, execução, pausa, descanso, transição e conclusão da etapa pelo mesmo renderizador. Após o coach selecionar **Finalizar aula**, exibe **Aula encerrada** antes de voltar à espera. O atraso padrão de oito segundos é configurável por `BOXPULSE_TV_END_SECONDS` (0–60 s). Uma aula mais antiga permanece no histórico.
- O polling usa ao menos 1,5 s durante a aula e 3 s em espera, respeitando um intervalo maior configurado. Um snapshot inalterado retorna HTTP 304 com o horário do servidor e sem corpo. A projeção usa o mesmo relógio monotônico do painel do coach. A reconciliação temporal grava somente transições naturais devidas, usando o estado autoritativo e lock da sessão, sem criar comandos.
- Sem comunicação, a TV informa que está tentando reconectar e conserva a última projeção confirmada. O primeiro retorno válido reconcilia o estado. Um link inválido ou revogado exibe **Sessão indisponível**.
- O indicador administrativo reflete uma consulta válida ao estado. Registra a hora no fuso da box, com gravações no cache espaçadas em cerca de dez segundos. A presença expira após 25 segundos da última gravação. Abrir a página sem um Bearer válido não conta como conexão.

## Migration e homologação local

A migration [2026_11_23_175511_add_tv_access_to_boxes.php](../database/migrations/2026_11_23_175511_add_tv_access_to_boxes.php) adiciona as colunas da credencial e o índice de seleção em `workout_sessions`. O schema e os snapshots anteriores permanecem compatíveis.

A suíte passou com 346 testes PHP, 4.180 asserções e 52 testes JavaScript. A [cobertura HTTP](../tests/Feature/BoxTvTest.php) verifica rotação, isolamento, presença, bloqueio de comandos e estados da aula. A [homologação no Chrome](../tests/js/presentation-browser.test.js) exercita o link em um contexto sem login, incluindo cópia, perda de rede, reconexão e layout 35/65 em Full HD, QHD e 4K.

```sh
docker compose exec -T laravel.test php artisan config:show database.connections.mysql.database --no-interaction
docker compose exec -T laravel.test php artisan migrate --pretend --path=database/migrations/2026_11_23_175511_add_tv_access_to_boxes.php --no-interaction
docker compose exec -T laravel.test php artisan migrate --path=database/migrations/2026_11_23_175511_add_tv_access_to_boxes.php --no-interaction
docker compose exec -T laravel.test php artisan config:clear --no-interaction
docker compose exec -T laravel.test php artisan test --compact
npm run test:editor
npm run test:timers
npm run test:presentation
npm run build
npm run test:presentation:browser
```

1. Em uma box local com logo e cores, gere o link e copie. Abra o endereço numa janela anônima. Confira nome, relógio no fuso da box, tema e espera. Veja a comunicação registrada nas configurações.
2. Prepare uma aula sem iniciá-la. Comande o início no painel do coach. Acompanhe preparação, execução, pausa, retomada, intervalo e troca de etapa. Compare a formatação e as cargas.
3. Desconecte apenas o navegador da TV, pause ou retome pelo coach e reconecte a TV. Confira a atualização sem recarregar. Encerre a aula e aguarde o retorno à espera.
4. Gere um novo link. Confira que a TV antiga pede o endereço atualizado. Abra o novo link e confira a tela em uma TV no ambiente local.
5. A regressão usa o MySQL `testing`. O teste de navegador abre um contexto sem cookies de login e exercita ambos os painéis. A homologação em TV física inclui legibilidade à distância, tela cheia e áudio por gesto.
