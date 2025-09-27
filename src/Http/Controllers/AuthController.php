<?php

namespace ApiSite\Http\Controllers;

use ApiSite\Models\Access;
use ApiSite\Models\User;
use DateTimeImmutable;
use Firebase\JWT\JWT;

class AuthController {
  public function login() {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['user']) || !isset($input['password'])) {
      http_response_code(400);
      echo json_encode(['message' => 'Usuário e senha são obrigatórios.']);
      return;
    }

    $user = User::where('user', $input['user'])->first();

    if (!$user || !password_verify($input['password'], $user->password)) {
      http_response_code(401); // Unauthorized
      echo json_encode(['message' => 'Credenciais inválidas.']);
      return;
    }

    Access::create(['user_id' => $user->id]);

    $secretKey = $_ENV['JWT_SECRET'];
    $issuedAt = new DateTimeImmutable();
    $expire = $issuedAt->modify('+24 hours')->getTimestamp();
    $serverName = "apisite.com";
    $data = ['iat' => $issuedAt->getTimestamp(), 'nbf' => $issuedAt->getTimestamp(), 'exp' => $expire, 'iss' => $serverName, 'data' => ['userId' => $user->id, 'userName' => $user->name,]];

    $accessToken = JWT::encode($data, $secretKey, 'HS256');
    $refreshToken = bin2hex(random_bytes(32));

    $response = ['status' => 'success', 'user' => ['name' => $user->name,], 'authorisation' => ['access_token' => $accessToken, 'refresh_token' => $refreshToken, 'token_type' => 'bearer', 'expires_in' => $expire]];

    header('Content-Type: application/json');
    echo json_encode($response);
  }
}