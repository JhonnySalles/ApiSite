# [Nome da Funcionalidade / Módulo] - [NomeDoController Principal]

## 🎯 Objetivo / Contexto

[Uma breve descrição de 1 a 3 frases sobre o objetivo desta funcionalidade na API e o que o cliente (Frontend) resolve com ela].

## 🧩 Arquivos e Componentes Core

- **Controller Principal:** `NomeDoController.php`
- **Service / Domínio:** `NomeDoService.php`
- **Models Envolvidos:** `ModelA.php`, `ModelB.php`
- **Requests/Responders:** [Se houver validadores de request ou formatação de response específica]

## ⚙️ Regras de Negócio e Lógica (Core Logic)

- **Regra 1:** Antes de salvar, a imagem em Base64 deve ser convertida e subida para o bucket XYZ.
- **Regra 2:** Se a plataforma for X, o parâmetro Y é obrigatório.
- **Tratamento de Erros:** Exceções devem ser capturadas pelo Sentry e retornar HTTP 500.

## 🔗 Endpoints e Fluxo

- **POST /api/recurso:** Cria um novo registro.
- **GET /api/recurso/{id}:** Retorna o recurso criado.
- **Webhooks (Opcional):** Envia o status para `FRONTEND_WEBHOOK_URL`.

## 🌍 Integrações / Dependências Externas (Referência)

- **Serviço Externo A:** Comunica via Guzzle.
- **Banco de Dados:** Utiliza as tabelas `tabela_1` e `tabela_2`.
- **Variáveis de Ambiente (.env):** `CHAVE_API_EXTERNA`, `URL_SERVICO`.
