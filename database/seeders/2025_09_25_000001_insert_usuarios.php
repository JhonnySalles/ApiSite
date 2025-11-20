<?php

use ApiSite\Models\User;

class InsertUsuarios {
  /**
   * Roda o seeder para popular o banco.
   *
   * @return void
   */
  public function run() {
    User::firstOrCreate(['username' => 'Admin'], ['username' => 'Admin', 'nome' => 'Admin',  'password' => password_hash('.gRh-/}5$@i=7MT~8uy5x?*<(', PASSWORD_DEFAULT)]);
    User::firstOrCreate(['username' => 'Site'], ['username' => 'Site', 'nome' => 'Site',  'password' => password_hash('£%)v}YhI[)d6KoO1w9&vwo]Y', PASSWORD_DEFAULT)]);
  }
}