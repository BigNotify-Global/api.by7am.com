<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtAuthFilter implements FilterInterface
{
   public function before(RequestInterface $request, $arguments = null)
   {
      $authHeader = $request->getHeaderLine('Authorization');

      if (empty($authHeader) || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
         return Services::response()
            ->setJSON([
               'status' => 401,
               'error' => 401,
               'messages' => ['error' => 'Unauthorized. Missing Bearer token.'],
            ])
            ->setStatusCode(401);
      }

      $token = $matches[1];

      try {
         $secretKey = getenv('JWT_SECRET');

         if (empty($secretKey)) {
            return Services::response()->setJSON([
               'error' => 'Server Configuration Error.',
            ])->setStatusCode(500);
         }

         // The Math: If the token was altered, or if it has expired, this instantly throws an exception.
         $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));

         // SECURITY LOCK: Ensure our custom payload actually contains the accountId
         if (empty($decoded->accountId)) {
            throw new Exception("Invalid token structure. Missing accountId.");
         }

         // THE STATELESS HANDOFF
         // We inject the ID straight from the token into the request headers. NO DATABASE REQUIRED.
         $request->setHeader('X-Account-Id', $decoded->accountId);
         $request->setHeader('X-Account-Uid', $decoded->sub);

      } catch (Exception $e) {
         return Services::response()
            ->setJSON([
               'status' => 401,
               'error' => 401,
               'messages' => ['error' => 'Unauthorized. Invalid or expired token.'],
               'exception' => $e->getMessage(),
            ])
            ->setStatusCode(401);
      }
   }

   public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}