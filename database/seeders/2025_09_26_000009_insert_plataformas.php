<?php

class InsertPlataformas {
  public function run() {
    $platforms = ['tumblr', 'twitter', 'bluesky', 'threads'];

    foreach ($platforms as $platafrm)
      \ApiSite\Models\Platform::firstOrCreate(['nome' => $platafrm]);
  }
}