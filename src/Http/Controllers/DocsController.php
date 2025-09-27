<?php

namespace ApiSite\Http\Controllers;

use OpenApi\Generator;

class DocsController {
  /**
   * Gera e serve a especificação OpenAPI (swagger.json) dinamicamente.
   */
  public function json() {
    $sourcePath = __DIR__ . '/';

    try {
      $openapi = Generator::scan([$sourcePath]);
      header('Content-Type: application/json');
      echo $openapi->toJson();

    } catch (\Exception $e) {
      http_response_code(500);
      echo json_encode(['message' => 'Erro ao gerar a documentação OpenAPI.', 'error' => $e->getMessage()]);
    }
  }

  /**
   * Serve a página HTML do Swagger UI.
   */
  public function ui() {
    echo <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
          <meta charset="utf-8" />
          <meta name="viewport" content="width=device-width, initial-scale=1" />
          <title>Documentação da API</title>
          <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css" />
        </head>
        <body>
        
        <div id="swagger-ui"></div>
        
        <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js" crossorigin></script>
        <script>
          window.onload = () => {
            window.ui = SwaggerUIBundle({
              url: '/docs/openapi.json', // <-- Aponta para o nosso próprio endpoint JSON
              dom_id: '#swagger-ui',
            });
          };
        </script>
        </body>
        </html>
        HTML;
  }
}