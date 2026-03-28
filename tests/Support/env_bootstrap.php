<?php

/**
 * Script para forçar o ambiente de teste nos servidores embutidos.
 */

$_ENV['APP_ENV'] = 'testing';
putenv('APP_ENV=testing');
