<?php

namespace ApiSite\Seeders;

use ApiSite\Models\User;

class AdminUserSeeder {
  /**
   * Roda o seeder para popular o banco.
   *
   * @return void
   */
  public function run() {
    User::firstOrCreate(['user' => 'Admin'], ['user' => 'Admin', 'name' => 'Admin',  'password' => password_hash('password123', PASSWORD_DEFAULT)]);
    User::firstOrCreate(['user' => 'Site'], ['user' => 'Site', 'name' => 'Site',  'password' => password_hash('password123', PASSWORD_DEFAULT)]);
  }
}