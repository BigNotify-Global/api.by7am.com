<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class Profile extends ResourceController
{

    /**
     * Verifies if the authenticated account has an active Admin profile (role_id = 1).
     */
    private function checkIsAdmin(int $accountId): bool
    {
        $db = \Config\Database::connect();
        $count = $db->table('profiles')
            ->where('account_id', $accountId)
            ->where('role_id', 1) // 1 = Admin
            ->countAllResults();

        return $count > 0;
    }

    /**
     * POST /v1/profile/addAccount
     * The Front Door & Token Exchange: Syncs Firebase data and mints the session JWT.
     */
    public function addUpdateAccount()
    {
        $accountUid = $this->request->getVar('accountUid');
        $email = $this->request->getVar('email');
        $displayName = $this->request->getVar('displayName');
        $profilePhotoUrl = $this->request->getVar('profilePhotoUrl');
        $isAccountVerified = (int) $this->request->getVar('isAccountVerified');

        if (empty($accountUid) || empty($email)) {
            return $this->failValidationErrors(
                'Account UID and Email are strictly required to sync an account.',
            );
        }

        $db = \Config\Database::connect();

        // 1. Sync the data with MySQL
        $row = $db->query(
            "CALL sp_SyncAccount(?, ?, ?, ?, ?)",
            [$accountUid, $email, $displayName, $profilePhotoUrl, $isAccountVerified],
        )->getRowArray();

        if (!$row) {
            return $this->failServerError('Critical error: Failed to sync account with the database.');
        }

        // --- THE MINTING PRESS ---

        // Note: Ensure your sp_SyncAccount returns the internal integer ID.
        // I am assuming it returns as 'id' or 'accountId'.
        $internalAccountId = $row['id'] ?? $row['accountId'] ?? null;

        if (!$internalAccountId) {
            return $this->failServerError('Database did not return the internal Account ID required for authentication.');
        }

        $secretKey = getenv('JWT_SECRET');
        $issuedAt = time();
        $expirationTime = $issuedAt + (30 * 24 * 60 * 60); // Token valid for 1 month

        $payload = [
            'iss' => 'api.by7am.com',
            'aud' => 'app.by7am.com',
            'iat' => $issuedAt,
            'exp' => $expirationTime,
            'sub' => $accountUid,
            'accountId' => (int) $internalAccountId
        ];

        // 2. Generate the stateless token
        $customToken = JWT::encode($payload, $secretKey, 'HS256');

        // 3. Return the synced data AND the token in one shot
        return $this->respond([
            "message" => "Account synchronized and authenticated successfully.",
            "token" => $customToken,
            "account" => $row,
        ]);
    }

    /**
     * POST /v1/profile/createProfile
     * Creates a new profile using the Omni-Profile Stored Procedure.
     */
    public function createProfile()
    {
        // STRATEGIC SECURITY: Identity is derived from the JWT, never the payload.
        $accountId = $this->request->getHeaderLine('X-Account-Id');

        if (empty($accountId)) {
            return $this->failUnauthorized(
                'Security violation: Missing verified Account ID.',
            );
        }

        $schoolId = $this->request->getVar('schoolId');
        $displayName = $this->request->getVar('displayName');
        $profileTypeId = $this->request->getVar('profileTypeId');
        $roleId = $this->request->getVar('roleId');
        $genderId = $this->request->getVar('genderId');

        $designationId = $this->request->getVar('designationId') ?: null;
        $sectionId = $this->request->getVar('sectionId') ?: null;
        $rollNumber = $this->request->getVar('rollNumber') ?: null;

        if (empty($schoolId) || empty($displayName) || empty($profileTypeId) || empty($roleId) || empty($genderId)) {
            return $this->failValidationErrors(
                'Missing required fields. School, Name, Profile Type, Role, and Gender are mandatory.',
            );
        }

        // Privilege Escalation Prevention
        $requestedStatusId = $this->request->getVar('statusId');

        $isCallerAdmin = $this->checkIsAdmin((int) $accountId);

        if (!$isCallerAdmin) {
            $finalStatusId = 1; // Force self-serve users into Pending status
            if (in_array($roleId, [1, 2])) {
                return $this->failForbidden(
                    'Security violation: You do not have permission to create an elevated role.',
                );
            }
        }
        else {
            $finalStatusId = $requestedStatusId ?: 1;
        }

        $db = \Config\Database::connect();
        $row = $db->query(
            "CALL sp_CreateGenericProfile(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$accountId, $schoolId, $profileTypeId, $roleId, $finalStatusId, $displayName, $genderId, $designationId, $sectionId, $rollNumber],
        )->getRowArray();

        if (!$row || empty($row['newProfileId'])) {
            return $this->failServerError(
                'Database failed to generate the profile.',
            );
        }

        return $this->respondCreated([
            "message" => $row['message'],
            "profileId" => (int) $row['newProfileId']
        ]);
    }
}