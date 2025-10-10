<?php

class InsertPlataformas {
  public function run() {
    $platforms = ['tumblr', 'x', 'twitter', 'bluesky', 'threads'];

    foreach ($platforms as $platafrm)
      \ApiSite\Models\Platform::firstOrCreate(['nome' => $platafrm]);
  }
}