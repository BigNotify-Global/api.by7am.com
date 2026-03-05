<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;
use Config\Services;

class JwtAuthFilter implements FilterInterface
{
   public function before(RequestInterface $request, $arguments = null)
   {
      $header = $request->getHeaderLine('Authorization');
      $token = null;

      // Extract the token from the "Bearer <token>" string
      if (!empty($header) && preg_match('/Bearer\s(\S+)/', $header, $matches)) {
         $token = $matches[1];
      }

      if (empty($token)) {
         return Services::response()
            ->setJSON(['error' => 'Unauthorized. No token provided.'])
            ->setStatusCode(401);
      }

      try {
         // Here you decode the token. If you use Firebase, you use their SDK.
         // If custom JWT, use the Firebase\JWT\JWT::decode library.
         // $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));

         // SECURITY LOCK: Once decoded, we trust the token.
         // We can inject the verified Account ID directly into the request headers or global scope
         // so the controller NEVER trusts the user payload.

         // Example: $request->setHeader('X-Verified-Account-Id', $decoded->accountId);

      } catch (\Exception $e) {
         return Services::response()
            ->setJSON(['error' => 'Unauthorized. Invalid or expired token.'])
            ->setStatusCode(401);
      }
   }

   public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
   {
      // Do nothing after the controller runs
   }
}