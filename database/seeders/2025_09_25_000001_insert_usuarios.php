<?php

use ApiSite\Models\User;

class InsertUsuarios {
  /**
   * Roda o seeder para popular o banco.
   *
   * @return void
   */
  public function run() {
    User::firstOrCreate(['username' => 'Admin'], ['username' => 'Admin', 'nome' => 'Admin',  'password' => password_hash('.8jU6Es4e8Su', PASSWORD_DEFAULT)]);
    User::firstOrCreate(['username' => 'Site'], ['username' => 'Site', 'nome' => 'Site',  'password' => password_hash('Bxj34f5B"Mu^', PASSWORD_DEFAULT)]);
  }
}