# Gerenciamento de Rascunhos - DraftController

## 🎯 Objetivo / Contexto

Realiza o CRUD de rascunhos da aplicação. As postagens salvas neste fluxo recebem o tipo 'RASCUNHO'. O objetivo é permitir que o usuário inicie uma publicação, salve o progresso com imagens e retome posteriormente.

## 🧩 Arquivos e Componentes Core

- **Controller Principal:** `src\Http\Controllers\DraftController.php`
- **Services Principais:** `PublishService.php`, `ImageService.php`
- **Models Envolvidos:** `Post`, `Tag`, `Image`

## ⚙️ Regras de Negócio e Lógica (Core Logic)

- **Diferenciação:** Compartilha a tabela de Postagens, mas se diferencia pelo campo `tipo = 'RASCUNHO'`.
- **Lógica de Salvamento:** Exclui imagens e tags anteriores relacionadas à postagem antes de inserir as novas em caso de edição (`updateOrCreate` dentro de um Transaction).
- **Upload de Imagens:** As imagens em base64 recebidas nos rascunhos também sofrem upload para o S3 via `ImageService`.
- **Exclusão Lógica:** O delete (DELETE /api/draft/{id}) executa um Soft Delete alterando a coluna `situacao` para `EXCLUIDO`.

## 🔗 Endpoints e Fluxo

- **GET /api/draft:** Retorna lista paginada de rascunhos (usando `getDraftsPaginated`).
- **GET /api/draft/{id}:** Recupera detalhes de um rascunho.
- **POST /api/draft:** Salva um rascunho único. Se enviar `id`, faz Update.
- **POST /api/draft/saveAll:** Salva múltiplos rascunhos em lote.
- **DELETE /api/draft/{id}:** Exclusão lógica do rascunho.

## 🌍 Integrações / Dependências Externas (Referência)

- **Storage (S3):** Requer upload no Supabase.
