# Guia de Testes da API SitePost

Esta pasta contém as rotinas de teste automatizadas implementadas com PHPUnit e Mockery.

## Pré-requisitos

1. **Dependências**: Execute `composer install` para garantir que o PHPUnit e Mockery estejam instalados.
2. **Banco de Dados de Teste**:
   - Crie um banco de dados MySQL vazio no seu servidor local destinado apenas para testes (ex: `sitepost_test`).
   - Edite o arquivo `.env.testing` na raiz do projeto com as credenciais deste banco.

# Como Rodar os Testes

Para executar toda a suíte de testes, rode o seguinte comando no terminal na raiz do projeto:

```bash
./vendor/bin/phpunit
```

## Estrutura de Testes

- **`tests/Unit`**: Contém testes unitários para serviços fundamentais (`ImageService`, `PublishService`). Eles usam Mocks para evitar chamadas reais a APIs externas e buckets S3.
- **`tests/Integration`**: Contém testes de integração de endpoints. Atualmente configurado para validar a lógica dos controllers.
- **`tests/TestCase.php`**: Classe base que lida com o ciclo de vida do banco (roda migrations antes dos testes e limpa as tabelas depois).

## Limitação de Envios Externos

Os testes foram desenhados para nunca enviar arquivos reais ao Supabase bucket ou realizar postagens reais na API Syncronizer. Isso é feito através de Injeção de Dependências (Constructor Injection) que permite o uso de objetos Mock nos cenários de teste.

## Testes de Endpoint com Guzzle

Para testar os endpoints via HTTP real com Guzzle (como demonstrado em `PublishEndpointsTest.php`):

1. Inicie o servidor local: `php -S localhost:8080 -t public`
2. Descomente o código em `tests/Integration/PublishEndpointsTest.php` e rode o PHPUnit.
