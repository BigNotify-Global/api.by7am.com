<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;

class Admin extends ResourceController
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
     * POST /v1/admin/sync
     * This is the front door. It expects a raw UID from Firebase and syncs it.
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
        $row = $db->query(
            "CALL sp_SyncAccount(?, ?, ?, ?, ?)",
            [$accountUid, $email, $displayName, $profilePhotoUrl, $isAccountVerified],
        )->getRowArray();

        if (!$row) {
            return $this->failServerError(
                'Critical error: Failed to sync account with the database.',
            );
        }

        return $this->respond([
            "message" => "Account synchronized successfully.",
            "account" => $row,
        ]);
    }

    /**
     * POST /v1/profile/create
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

        // REPLACED THE TODO: We actually verify admin status against the database now.
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