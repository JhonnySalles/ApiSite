<?php

namespace ApiSite\Http\Controllers;

use ApiSite\Models\Access;
use ApiSite\Models\User;
use ApiSite\Models\RefreshToken;
use DateTimeImmutable;
use Firebase\JWT\JWT;
use DateTime;

class AuthController {

  // POST /login
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
   * required={"username", "password"},
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

    if (!isset($input['username']) || !isset($input['password'])) {
      http_response_code(400);
      echo json_encode(['message' => 'Usuário e senha são obrigatórios.']);
      return;
    }

    $user = User::where('username', $input['username'])->first();

    if (!$user || !password_verify($input['password'], $user->password)) {
      http_response_code(401); // Unauthorized
      echo json_encode(['message' => 'Credenciais inválidas.']);
      return;
    }

    Access::create(['usuario_id' => $user->id]);

    $secretKey = $_ENV['JWT_SECRET'];
    $issuedAt = new DateTimeImmutable();
    $expire = $issuedAt->modify('+24 hours')->getTimestamp();
    $serverName = "apisite.com";
    $data = ['iat' => $issuedAt->getTimestamp(), 'nbf' => $issuedAt->getTimestamp(), 'exp' => $expire, 'iss' => $serverName, 'data' => ['userId' => $user->id, 'userName' => $user->name,]];

    RefreshToken::where('usuario_id', $user->id)->delete();

    $accessToken = JWT::encode($data, $secretKey, 'HS256');
    $refreshToken = bin2hex(random_bytes(32));
    $refreshTokenExpires = (new DateTime())->modify('+30 days');

    RefreshToken::create(['usuario_id' => $user->id, 'token' => $refreshToken, 'expires_at' => $refreshTokenExpires]);

    $response = ['status' => 'success', 'username' => ['name' => $user->nome,], 'authorisation' => ['access_token' => $accessToken, 'refresh_token' => $refreshToken, 'token_type' => 'bearer', 'expires_in' => $expire]];

    header('Content-Type: application/json');
    echo json_encode($response);
  }

  /**
   * @OA\Post(
   * path="/refresh",
   * tags={"Autenticação"},
   * summary="Renova um token de acesso expirado.",
   * description="Recebe um refresh_token válido e retorna um novo par de access_token e refresh_token.",
   * @OA\RequestBody(
   * required=true,
   * description="O token de atualização (refresh_token) recebido no login.",
   * @OA\JsonContent(
   * required={"refresh_token"},
   * @OA\Property(property="refresh_token", type="string", example="a1b2c3d4e5f6...")
   * )
   * ),
   * @OA\Response(
   * response=200,
   * description="Token renovado com sucesso.",
   * @OA\JsonContent(
   * type="object",
   * @OA\Property(property="status", type="string", example="success"),
   * @OA\Property(property="user", type="object", @OA\Property(property="name", type="string", example="Admin")),
   * @OA\Property(
   * property="authorisation",
   * type="object",
   * @OA\Property(property="access_token", type="string"),
   * @OA\Property(property="refresh_token", type="string"),
   * @OA\Property(property="token_type", type="string", example="bearer"),
   * @OA\Property(property="expires_in", type="integer")
   * )
   * )
   * ),
   * @OA\Response(
   * response=401,
   * description="Não autorizado (refresh_token inválido ou expirado).",
   * @OA\JsonContent(type="object", @OA\Property(property="message", type="string", example="Refresh token inválido."))
   * )
   * )
   */
  public function refresh() {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['refresh_token'])) {
      http_response_code(400);
      echo json_encode(['message' => 'Refresh token é obrigatório.']);
      return;
    }

    $refreshToken = RefreshToken::where('token', $input['refresh_token'])->first();

    if (!$refreshToken || new DateTime() > new DateTime($refreshToken->expires_at)) {
      if ($refreshToken)
        RefreshToken::where('usuario_id', $refreshToken->usuario_id)->delete();

      http_response_code(401);
      echo json_encode(['message' => 'Refresh token inválido ou expirado.']);
      return;
    }

    $user = $refreshToken->user;

    $refreshToken->delete();

    $issuedAt = new DateTimeImmutable();
    $expire = $issuedAt->modify('+24 hours')->getTimestamp();
    $newAccessToken = $this->generateJwtToken($user, $issuedAt, $expire);

    $newRefreshTokenString = bin2hex(random_bytes(32));
    $newRefreshTokenExpires = (new DateTime())->modify('+30 days');

    RefreshToken::create(['usuario_id' => $user->id, 'token' => $newRefreshTokenString, 'expires_at' => $newRefreshTokenExpires]);

    $response = ['status' => 'success', 'user' => ['name' => $user->name], 'authorisation' => ['access_token' => $newAccessToken, 'refresh_token' => $newRefreshTokenString, 'token_type' => 'bearer', 'expires_in' => $expire]];

    header('Content-Type: application/json');
    echo json_encode($response);
  }

  private function generateJwtToken(User $user, DateTimeImmutable $issuedAt, int $expire): string {
    $secretKey = $_ENV['JWT_SECRET'];
    $serverName = "your_api.com";
    $data = ['iat' => $issuedAt->getTimestamp(), 'nbf' => $issuedAt->getTimestamp(), 'exp' => $expire, 'iss' => $serverName, 'data' => ['userId' => $user->id, 'userName' => $user->name,]];
    return JWT::encode($data, $secretKey, 'HS256');
  }
}