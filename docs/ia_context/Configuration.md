# Configurações e Plataformas - ConfigurationController

## 🎯 Objetivo / Contexto

Permite gerenciar o status (Ativo/Inativo) das plataformas de destino disponíveis para as postagens (Tumblr, X, etc). No caso específico do Tumblr, também realiza o controle dos Blogs sincronizados e define qual é o Blog Padrão selecionado.

## 🧩 Arquivos e Componentes Core

- **Controllers:** `src\Http\Controllers\ConfigurationController.php`, `src\Http\Controllers\PlatformController.php`
- **Services Principais:** `ConfigurationService.php`, `SyncAuthService.php`
- **Models Envolvidos:** `Platform`, `Blog`

## ⚙️ Regras de Negócio e Lógica (Core Logic)

- **Tratamento de Alias:** A string `x` sempre é traduzida para `twitter` a nível de código interno através do helper `resolvePlatformAlias()`.
- **Gestão do Tumblr:** Ao salvar as plataformas, caso o array inclua a plataforma `tumblr`, ao menos um "Blog" deve vir acompanhado com a flag `selecionado` igual a `true`.
- **Sincronização Externa (PlatformController):** Busca blogs na API PostSynchronizer (`GET /tumblr/blogs`) e recria os registros locais na tabela de Blogs, mantendo persistida a seleção do blog anterior, caso exista.
- **Retorno de Dados Eager Loading:** O endpoint de listagem sempre carrega as plataformas `with('blogs')` para otimizar queries e formata a resposta escondendo chaves sensíveis e mapeando tipos.

## 🔗 Endpoints e Fluxo

- **GET /api/configuration/platforms:** Lista todas plataformas. Retorna os `blogs` atrelados se for Tumblr.
- **GET /api/configuration/platforms/{name}:** Retorna o status de uma única plataforma.
- **PUT /api/configuration/platforms:** Salva status em lote.
- **PUT /api/configuration/platforms/{name}:** Atualiza o status de uma plataforma específica.
- **GET /api/platform/tumblr/blogs:** Consulta a API do `PostSynchronizer` para listar/atualizar os blogs da conta vinculada ao Tumblr.

## 🌍 Integrações / Dependências Externas (Referência)

- **API Syncronizer:** Endpoint `/tumblr/blogs` via `HttpClient`.
- **Autenticação Externa:** Necessita de JWT válido via `SyncAuthService` para conversar com a API de sincronia.
