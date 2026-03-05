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

      // 1. Check if the token exists and is formatted correctly
      if (empty($authHeader) || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
         return Services::response()
            ->setJSON([
               'status' => 401,
               'error' => 401,
               'messages' => ['error' => 'Unauthorized. Missing or malformed Bearer token.'],
            ])
            ->setStatusCode(401);
      }

      $token = $matches[1];

      try {
         // 2. Decode the Token
         // If using standard JWT:
         $secretKey = getenv('JWT_SECRET');
         $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));

         // The unique string ID from the token (often called 'sub' for subject, or 'uid' in Firebase)
         $uid = $decoded->sub ?? $decoded->uid;

         if (empty($uid)) {
            throw new Exception("Token does not contain a valid user identifier.");
         }

         // 3. The Database Shield
         // Find the internal integer account_id linked to this string UID
         $db = \Config\Database::connect();
         $account = $db->query("SELECT id FROM accounts WHERE uid = ? LIMIT 1", [$uid])->getRow();

         if (!$account) {
            return Services::response()
               ->setJSON([
                  'status' => 403,
                  'error' => 403,
                  'messages' => ['error' => 'Forbidden. Token is valid but account does not exist in the system.'],
               ])
               ->setStatusCode(403);
         }

         // 4. INJECT THE VERIFIED DATA
         // We stamp the verified integer ID directly into the request headers.
         // Your controllers will pull from here, NEVER from the user's POST body.
         $request->setHeader('X-Account-Id', $account->id);
         $request->setHeader('X-Account-Uid', $uid);

      } catch (Exception $e) {
         // Token is expired, tampered with, or invalid
         return Services::response()
            ->setJSON([
               'status' => 401,
               'error' => 401,
               'messages' => ['error' => 'Unauthorized. Invalid or expired token.'],
            ])
            ->setStatusCode(401);
      }
   }

   public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
   {
      // No action needed after the controller executes
   }
}