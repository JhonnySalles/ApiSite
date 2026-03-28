<?php

namespace Tests\Support;

use RuntimeException;

class HttpServer {
    private $host;
    private $port;
    private $docRoot;
    private $process;
    private $pipes = [];

    public function __construct(string $host, int $port, string $docRoot) {
        $this->host = $host;
        $this->port = $port;
        $this->docRoot = $docRoot;
    }

    public function start() {
        if ($this->isPortActive()) {
            throw new RuntimeException(sprintf(
                "ERRO: A porta %d já está ativa. Por favor, encerre o processo manualmente (ex: taskkill /F /IM php.exe) antes de rodar os testes.",
                $this->port
            ));
        }

        $logFile = "logs/server_{$this->port}.log";

        $prepend = realpath('tests/Support/env_bootstrap.php');
        $command = is_dir($this->docRoot) 
            ? sprintf('php -d auto_prepend_file="%s" -S %s:%d -t %s', $prepend, $this->host, $this->port, $this->docRoot)
            : sprintf('php -d auto_prepend_file="%s" -S %s:%d %s', $prepend, $this->host, $this->port, $this->docRoot);
        
        $descriptorspec = [
            0 => ["pipe", "r"],
            1 => ["file", $logFile, "w"],
            2 => ["file", $logFile, "w"]
        ];

        $this->process = proc_open($command, $descriptorspec, $this->pipes, null, [
            'APP_ENV' => 'testing',
            'SystemRoot' => getenv('SystemRoot'),
            'PATH' => getenv('PATH')
        ]);

        if (!is_resource($this->process)) {
            throw new RuntimeException("Falha ao iniciar o servidor embutido do PHP.");
        }

        // Aguarda até o servidor estar pronto (máximo 5 segundos)
        $attempts = 0;
        while (!$this->isPortActive() && $attempts < 10) {
            usleep(500000); // 0.5s
            $attempts++;
            
            $status = proc_get_status($this->process);
            if (!$status['running']) {
                $error = file_exists($logFile) ? file_get_contents($logFile) : "Sem log.";
                throw new RuntimeException("O servidor PHP morreu ao iniciar na porta {$this->port}. Erro: " . $error);
            }
        }
        
        if (!$this->isPortActive()) {
             throw new RuntimeException("O servidor iniciou na porta {$this->port} mas não responde em localhost após 5 segundos.");
        }
    }

    public function stop() {
        if (is_resource($this->process)) {
            $status = proc_get_status($this->process);
            if ($status['running']) {
                $pid = $status['pid'];
                exec("taskkill /F /T /PID $pid 2>NUL");
            }
            
            foreach ($this->pipes as $pipe) {
                if (is_resource($pipe)) fclose($pipe);
            }
            proc_close($this->process);
        }
        @unlink("logs/server_{$this->port}.log");
    }

    private function isPortActive(): bool {
        $connection = @fsockopen($this->host, $this->port, $errno, $errstr, 0.1);
        if (is_resource($connection)) {
            fclose($connection);
            return true;
        }
        return false;
    }

    public function getUrl(): string {
        return "http://{$this->host}:{$this->port}";
    }
}
