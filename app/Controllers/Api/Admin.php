<?php

namespace App\Controllers\Api; // Keep your APIs cleanly separated in a namespace

use CodeIgniter\RESTful\ResourceController; // MUST use this for failNotFound(), respond(), etc.

class Admin extends ResourceController
{
    public function addUpdateAccount()
    {
        // 1. Extract Variables
        $accountUid = $this->request->getVar('accountUid');
        $email = $this->request->getVar('email');
        $displayName = $this->request->getVar('displayName');
        $profilePhotoUrl = $this->request->getVar('profilePhotoUrl');
        $isAccountVerified = $this->request->getVar('isAccountVerified');

        // 2. The Fortress (Guard Clauses)
        // Never talk to the database if the core identity is missing
        if (empty($accountUid) || empty($email)) {
            return $this->failValidationErrors('Account UID and Email are strictly required to sync an account.');
        }

        // 3. Type Safety
        // Ensure the database gets a strict integer (1 or 0) for the boolean flag
        $isAccountVerified = (int) $isAccountVerified;

        $db = \Config\Database::connect();

        // 4. Use getRowArray() because this SP returns exactly ONE row!
        $row = $db->query(
            "CALL sp_SyncAccount(?, ?, ?, ?, ?)",
            [$accountUid, $email, $displayName, $profilePhotoUrl, $isAccountVerified],
        )->getRowArray();

        // 5. Handle Database Failure
        if (!$row) {
            return $this->failServerError('Critical error: Failed to sync account with the database.');
        }

        // 6. Return Clean Output to Jetpack Compose
        return $this->respond([
            "message" => "Account synchronized successfully.",
            "account" => $row,
        ]);
    }
}