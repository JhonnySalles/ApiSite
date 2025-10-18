<?php

date_default_timezone_set('America/Sao_Paulo');

require __DIR__ . '/bootstrap/app.php';

use ApiSite\Models\Platform;
use ApiSite\Services\LogService;

echo "--- Iniciando rotina agendada: Ping no Supabase ---\n";
echo "Horário: " . date('Y-m-d H:i:s') . "\n";

try {
  $platformCount = Platform::count();

  if ($platformCount > 0) {
    $message = "Ping no Supabase realizado com sucesso. Total de plataformas encontradas: $platformCount.";

    LogService::getInstance()->info('Rotina agendada executada: Ping Supabase OK.', ['platforms' => $platformCount]);

    echo "Resultado: Sucesso. $message\n";
  } else {
    $message = "A consulta ao Supabase foi executada, mas nenhuma plataforma foi encontrada.";
    LogService::getInstance()->warning('Rotina agendada: Ping Supabase executado, mas nenhuma plataforma no banco.', []);
    echo "Resultado: Alerta. $message\n";
  }

} catch (\Exception $e) {
  $errorMessage = "Falha ao executar o ping no Supabase: " . $e->getMessage();

  LogService::getInstance()->error('Rotina agendada: Falha no Ping Supabase.', ['error' => $e->getMessage()]);

  echo "Resultado: ERRO. $errorMessage\n";
}

echo "--- Fim da rotina agendada ---\n";