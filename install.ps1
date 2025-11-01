# --- Configuração ---
$GitBinPathExample = "C:\Program Files\Git\usr\bin" # Exemplo de caminho

# Define preferência de erro para parar em caso de falha nos comandos
$ErrorActionPreference = 'Stop'

Write-Host "`n======================================================"
Write-Host " Verificando pre-requisitos para Composer Install..."
Write-Host "======================================================`n"

# --- 1. Verificar Git ---
Write-Host "Verificando Git..." -NoNewline
try {
    # Tenta obter o comando git. Se não encontrar, gera erro.
    Get-Command git -ErrorAction Stop | Out-Null
    Write-Host " OK." -ForegroundColor Green
} catch {
    Write-Host "`n******************************************************" -ForegroundColor Red
    Write-Host "* ERRO: Comando 'git' nao encontrado no PATH." -ForegroundColor Red
    Write-Host "* Por favor, instale o Git (https://git-scm.com/) e   *" -ForegroundColor Yellow
    Write-Host "* garanta que ele esteja nas variaveis de ambiente.  *" -ForegroundColor Yellow
    Write-Host "******************************************************`n" -ForegroundColor Red
    exit 1 # Sai com código de erro
}

# --- 2. Verificar patch ---
Write-Host "Verificando ferramenta 'patch'..." -NoNewline
try {
    Get-Command patch -ErrorAction Stop | Out-Null
    Write-Host " OK." -ForegroundColor Green
} catch {
    Write-Host "`n******************************************************" -ForegroundColor Red
    Write-Host "* ERRO: Comando 'patch' nao encontrado no PATH.      *" -ForegroundColor Red
    Write-Host "* Pasta bin do Git pode nao estar no PATH.           *" -ForegroundColor Yellow
    Write-Host "* Adicione: $GitBinPathExample (ou similar) *" -ForegroundColor Yellow
    Write-Host "* FECHE e REABRA este terminal apos adicionar.       *" -ForegroundColor Yellow
    Write-Host "******************************************************`n" -ForegroundColor Red
    exit 1 # Sai com código de erro
}

# --- 3. Executar Composer ---
Write-Host "`n======================================================"
Write-Host " Pre-requisitos OK. Executando Composer install -v..."
Write-Host "======================================================`n"

try {
    # Executa o composer install
    & composer -v install
    # Verifica o código de saída do último comando
    if ($LASTEXITCODE -ne 0) {
        throw "O comando 'composer install' falhou com o código de saída: $LASTEXITCODE"
    }
} catch {
    Write-Host "`n******************************************************" -ForegroundColor Red
    Write-Host "* ERRO: O comando 'composer install' falhou." -ForegroundColor Red
    Write-Host "* Verifique a saida acima para detalhes do erro.     *" -ForegroundColor Red
    Write-Host "* Erro específico: $($_.Exception.Message)            *" -ForegroundColor Red
    Write-Host "******************************************************`n" -ForegroundColor Red
    # Tenta sair com o código de erro do Composer, ou 1 se não disponível
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE } else { exit 1 }
}

# --- Sucesso ---
Write-Host "`n======================================================" -ForegroundColor Green
Write-Host " Instalacao das dependencias concluida com sucesso!" -ForegroundColor Green
Write-Host "======================================================" -ForegroundColor Green
Write-Host "`n"

exit 0 # Sai com sucesso