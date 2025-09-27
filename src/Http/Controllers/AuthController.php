<?php

namespace ApiSite\Http\Controllers;

use ApiSite\Models\Access;
use ApiSite\Models\User;
use DateTimeImmutable;
use Firebase\JWT\JWT;

class AuthController {

  /**
   * @OA\Post(
   * path="/login",
   * tags={"Autenticação"},
   * summary="Autentica um usuário e retorna um token JWT.",
   * description="Este endpoint valida as credenciais (usuário e senha) e, em caso de sucesso, retorna um token de acesso (JWT) com validade de 24 horas, um token de atualização e os dados básicos do usuário. A requisição deve ser autenticada com uma chave de API estática no cabeçalho.",
   * @OA\Parameter(
   * name="X-API-KEY",
   * in="header",
   * required=true,
   * description="Chave de API estática para autorizar a requisição.",
   * @OA\Schema(type="string")
   * ),
   * @OA\RequestBody(
   * required=true,
   * description="Credenciais do usuário para login.",
   * @OA\JsonContent(
   * required={"usuario", "password"},
   * @OA\Property(property="usuario", type="string", format="text", example="admin"),
   * @OA\Property(property="password", type="string", format="password", example="password123")
   * )
   * ),
   * @OA\Response(
   * response=200,
   * description="Autenticação bem-sucedida.",
   * @OA\JsonContent(
   * type="object",
   * @OA\Property(property="status", type="string", example="success"),
   * @OA\Property(
   * property="user",
   * type="object",
   * @OA\Property(property="name", type="string", example="Admin")
   * ),
   * @OA\Property(
   * property="authorisation",
   * type="object",
   * @OA\Property(property="access_token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."),
   * @OA\Property(property="refresh_token", type="string", example="a1b2c3d4e5f6..."),
   * @OA\Property(property="token_type", type="string", example="bearer"),
   * @OA\Property(property="expires_in", type="integer", example=1727395200)
   * )
   * )
   * ),
   * @OA\Response(
   * response=400,
   * description="Dados inválidos (usuário ou senha ausentes).",
   * @OA\JsonContent(
   * type="object",
   * @OA\Property(property="message", type="string", example="Usuário e senha são obrigatórios.")
   * )
   * ),
   * @OA\Response(
   * response=401,
   * description="Não autorizado (credenciais incorretas).",
   * @OA\JsonContent(
   * type="object",
   * @OA\Property(property="message", type="string", example="Credenciais inválidas.")
   * )
   * ),
   * @OA\Response(
   * response=403,
   * description="Acesso proibido (X-API-KEY inválida ou ausente).",
   * @OA\JsonContent(
   * type="object",
   * @OA\Property(property="message", type="string", example="Acesso não autorizado.")
   * )
   * )
   * )
   */
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