<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;

class Teacher extends ResourceController
{
   /**
    * Prevents BOLA/IDOR attacks by proving the JWT account actually owns the requested profile.
    */
   private function verifyProfileOwnership(int $accountId, int $profileId): bool
   {
      $db = \Config\Database::connect();
      $count = $db->table('profiles')
         ->where('id', $profileId)
         ->where('account_id', $accountId)
         ->countAllResults();

      return $count > 0;
   }


   public function dashboard()
   {
      // STRATEGIC SECURITY: Identity is derived from the JWT, never the payload.
      $accountId = $this->request->getHeaderLine('X-Account-Id');

      if (empty($accountId)) {
         return $this->failUnauthorized('Security violation: Verified Account ID missing.');
      }

      $db = \Config\Database::connect();
      $query = $db->query(
         "CALL sp_GetTeacherDashboard(?)",
         [$accountId],
      );
      $row = $query->getRowArray();

      if (!$row) {
         return $this->failNotFound('Teacher profile not found.');
      }

      $allocations = json_decode($row['allocationsJson'], true) ?? [];

      $response = [
         "profile" => [
            "profileId" => (int) $row['profileId'],
            "displayName" => $row['profileName'],
            "designation" => $row['designationName'],
            "limitPerSubject" => 3,
            "school" => [
               "schoolId" => (int) $row['schoolId'],
               "name" => $row['schoolName'],
               "city" => $row['city'],
            ],
         ],
         "assignedClass" => null,
         "subjectAllocations" => [],
      ];

      foreach ($allocations as $alloc) {
         $formattedAllocation = [
            "allocationId" => (int) $alloc['allocationId'],
            "sectionId" => (int) $alloc['sectionId'],
            "className" => $alloc['className'],
            "subjectId" => (int) $alloc['subjectId'],
            "subjectName" => $alloc['subjectName'],
            "isClassTeacher" => (bool) $alloc['isClassTeacher'],
            "contextCode" => $alloc['isClassTeacher'] ? "CLASS_TEACHER" : "SUBJECT",
            "stats" => [
               "lastUpdate" => null,
               "unreadInteractions" => 0,
               "totalStudents" => 0,
               "updateCount" => 0,
            ],
         ];

         if ($alloc['isClassTeacher']) {
            $response['assignedClass'] = $formattedAllocation;
         }
         else {
            $response['subjectAllocations'][] = $formattedAllocation;
         }
      }

      return $this->respond($response);
   }

   public function feed()
   {

      $accountId = $this->request->getHeaderLine('X-Account-Id');
      $profileId = $this->request->getVar('profileId');
      $sectionId = $this->request->getVar('sectionId');
      $subjectId = $this->request->getVar('subjectId');

      if (empty($accountId))
         return $this->failUnauthorized(
            'Security violation: Verified Account ID missing.',
         );
      if (empty($profileId || empty($sectionId)))
         return $this->failValidationErrors(
            'Profile ID and Section ID is required.',
         );

      // REPLACED THE TODO: The BOLA Shield
      if (
         !$this->verifyProfileOwnership(
            (int) $accountId,
            (int) $profileId
         )
      ) {
         return $this->failForbidden(
            'Security violation: You do not own this profile.',
         );
      }


      $db = \Config\Database::connect();
      $query = $db->query(
         "CALL sp_GetTeacherFeed(?, ?, ?)",
         [$profileId, $sectionId, $subjectId],
      );
      $results = $query->getResultArray();

      $header = [];
      $allUpdates = [];

      foreach ($results as $row) {
         if (empty($header)) {
            $header = [
               "title" => $row['headerTitle'] ?? 'General',
               "subtitle" => $row['headerSubtitle'],
            ];
         }

         $ackCount = (int) $row['ackCount'];
         $totalStudents = (int) $row['totalStudents'];
         $percentage = ($totalStudents > 0) ? round(($ackCount / $totalStudents) * 100) : 0;

         $allUpdates[] = [
            "updateId" => (int) $row['updateId'],
            "text" => $row['text'],
            "categoryId" => (int) $row['categoryId'],
            "createdAt" => $row['createdAt'],
            "isUrgent" => (bool) $row['isUrgent'],
            "engagement" => [
               "ackCount" => $ackCount,
               "totalStudents" => $totalStudents,
               "percentage" => $percentage,
               "isEveryoneAck" => ($ackCount >= $totalStudents && $totalStudents > 0),
            ],
            "permissions" => [
               "canEdit" => true,
               "canDelete" => true,
            ],
         ];
      }

      return $this->respond([
         "header" => $header,
         "allUpdates" => $allUpdates,
      ]);
   }

   public function directory()
   {
      $accountId = $this->request->getHeaderLine('X-Account-Id');
      $profileId = $this->request->getVar('profileId');
      $sectionId = $this->request->getVar('sectionId');

      if (empty($accountId))
         return $this->failUnauthorized(
            'Security violation: Verified Account ID missing.',
         );
      if (empty($profileId) || empty($sectionId))
         return $this->failValidationErrors(
            'Profile ID and Section ID are required.',
         );

      // REPLACED THE TODO: The BOLA Shield
      if (
         !$this->verifyProfileOwnership(
            (int) $accountId,
            (int) $profileId
         )
      ) {
         return $this->failForbidden(
            'Security violation: You do not own this teacher profile.',
         );
      }

      $db = \Config\Database::connect();
      $query = $db->query(
         "CALL sp_GetTeacherStudentDirectory(?, ?)",
         [$profileId, $sectionId],
      );
      $results = $query->getResultArray();

      $students = []; // Bug successfully squashed.
      $classInfo = [
         "sectionId" => (int) $sectionId,
         "className" => "",
         "totalStudents" => 0,
      ];

      foreach ($results as $row) {
         if (empty($classInfo['className'])) {
            $classInfo['className'] = $row['className'];
         }

         $genderCode = strtolower($row['genderCode'] ?? '');
         $studentGender = ($genderCode === 'm') ? 'Male' : (($genderCode === 'f') ? 'Female' : 'Other');

         $students[] = [
            "profileId" => (int) $row['studentProfileId'],
            "rollNumber" => $row['rollNumber'],
            "displayName" => $row['studentName'],
            "sectionId" => (int) $row['sectionId'],
            "gender" => $studentGender,
            "guardian" => json_decode($row["guardiansJson"], true) ?? [],
         ];
      }

      $classInfo['totalStudents'] = count($students);

      return $this->respond([
         "permissions" => [
            "canEditStudent" => true,
            "canRemoveStudent" => true,
         ],
         "class" => $classInfo,
         "students" => $students,
      ]);
   }

   public function approvals()
   {
      $accountId = $this->request->getHeaderLine('X-Account-Id');
      $profileId = $this->request->getVar('profileId');
      $sectionId = $this->request->getVar('sectionId');

      if (empty($accountId))
         return $this->failUnauthorized(
            'Security violation: Verified Account ID missing.',
         );
      if (empty($profileId) || empty($sectionId))
         return $this->failValidationErrors(
            'Profile ID and Section ID are required.',
         );

      // REPLACED THE TODO: The BOLA Shield
      if (
         !$this->verifyProfileOwnership(
            (int) $accountId,
            (int) $profileId
         )
      ) {
         return $this->failForbidden(
            'Security violation: You do not own this teacher profile.',
         );
      }

      $db = \Config\Database::connect();
      $query = $db->query(
         "CALL sp_GetTeacherPendingRequests(?, ?)",
         [$profileId, $sectionId],
      );
      $results = $query->getResultArray();

      $requests = [];
      $classInfo = [
         "sectionId" => (int) $sectionId,
         "className" => "",
         "totalStudents" => 0,
         "totalPending" => 0,
      ];

      foreach ($results as $row) {
         if (empty($classInfo['className'])) {
            $classInfo['className'] = $row['className'];
         }

         // Properly decode the JSON from the Stored Procedure!
         $contacts = json_decode($row['contactsJson'], true) ?? [];
         $genderCode = strtolower($row['genderCode'] ?? '');
         $studentGender = ($genderCode === 'm') ? 'Male' : (($genderCode === 'f') ? 'Female' : 'Other');

         $requests[] = [
            "membershipId" => (int) $row['membershipId'],
            "requestedAt" => null,
            "profileId" => (int) $row['studentProfileId'],
            "displayName" => $row['studentName'],
            "gender" => $studentGender,
            "requestedRollNumber" => $row['requestedRollNumber'],
            "contacts" => $contacts,
            "conflictWarning" => null,
         ];
      }

      $classInfo['totalPending'] = count($requests);

      return $this->respond([
         "class" => $classInfo,
         "requests" => $requests,
      ]);
   }

   /**
    * POST /v1/teacher/classes/enrollments
    * Handles the lifecycle of a student's enrollment (ACCEPT, REJECT, TRANSFER, UPDATE).
    */
   public function manageEnrollment()
   {
      $accountId = $this->request->getHeaderLine('X-Account-Id');

      // Extract all required fields to match the Phase 1 Stored Procedure
      $profileId = $this->request->getVar('profileId');
      $membershipId = $this->request->getVar('membershipId'); // MUST BE MEMBERSHIP ID, NOT STUDENT PROFILE ID
      $actionString = strtoupper((string) $this->request->getVar('action'));
      $targetSectionId = $this->request->getVar('targetSectionId') ?: null;
      $rollNumber = $this->request->getVar('rollNumber') ?: null;
      $studentName = $this->request->getVar('studentName') ?: null;
      $genderId = $this->request->getVar('genderId') ?: null;

      if (empty($accountId))
         return $this->failUnauthorized(
            'Security violation: Verified Account ID missing.',
         );
      if (empty($profileId) || empty($membershipId) || empty($actionString)) {
         return $this->failValidationErrors(
            'Profile ID, Membership ID, and Action are required.',
         );
      }

      // Map String Actions to Integer Actions for the Database
      $actionMap = ['ACCEPT' => 1, 'REJECT' => 2, 'TRANSFER' => 3, 'UPDATE' => 4];
      if (!array_key_exists($actionString, $actionMap)) {
         return $this->failValidationErrors("Invalid action.");
      }
      $actionInt = $actionMap[$actionString];

      if (
         !$this->verifyProfileOwnership(
            (int) $accountId,
            (int) $profileId
         )
      ) {
         return $this->failForbidden(
            'Security violation: You do not own this teacher profile.',
         );
      }

      $db = \Config\Database::connect();

      try {
         $query = $db->query(
            "CALL sp_ManageStudentEnrollment(?, ?, ?, ?, ?, ?, ?)",
            [
               $profileId,
               $actionInt,
               $membershipId,
               $targetSectionId,
               $rollNumber,
               $studentName,
               $genderId,
            ],
         );
         $row = $query->getRowArray();

         // Catch custom SP rejections (like the cross-tenant block)
         if (isset($row['success']) && (int) $row['success'] === 0) {
            return $this->failForbidden($row['confirmationMessage']);
         }

         return $this->respond([
            "message" => $row['confirmationMessage'] ?? "Processed successfully.",
            "action" => $actionString,
         ]);

      } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
         log_message(
            'error',
            '[DB Error in manageEnrollment]: ' . $e->getMessage()
         );
         return $this->failServerError(
            'A database error occurred while processing the request.',
         );
      }
   }


   /**
    * POST /v1/uni/createUpdate
    * Creates a new feed update/post.
    */
   public function createUpdate()
   {
      $accountId = $this->request->getHeaderLine('X-Account-Id');

      // 1. Extract the Raw Payload (Notice how lean this is now)
      $schoolId = $this->request->getVar('schoolId');
      $profileId = $this->request->getVar('profileId');
      $sectionId = $this->request->getVar('sectionId');
      $text = (string) $this->request->getVar('text');

      $subjectId = $this->request->getVar('subjectId') ?: 1;
      $categoryId = $this->request->getVar('categoryId') ?: 2;
      $isLocked = $this->request->getVar('isLocked') ? 1 : 0;

      // 2. The Smart Inferences (Business Logic Enforced by Server)

      // Context inference
      // 2. The Smart Inferences & Temporal Validation (Business Logic Enforced by Server)
      $contextId = ((int) $subjectId === 1) ? 1 : 2;

      $clientVisibleFrom = $this->request->getVar('visibleFrom');
      $clientExpiresAt = $this->request->getVar('expiresAt');
      $currentServerTime = time();

      // Block Backdating (with a 5-minute buffer for device clock drift/latency)
      if (!empty($clientVisibleFrom)) {
         $visibleUnix = strtotime($clientVisibleFrom);
         if ($visibleUnix === false) {
            return $this->failValidationErrors('Invalid visibleFrom format. Must be a valid datetime string.');
         }
         if ($visibleUnix < ($currentServerTime - 300)) {
            return $this->failValidationErrors('Temporal manipulation blocked: visibleFrom cannot be in the past.');
         }
      }

      // Block Illogical Expirations
      if (!empty($clientExpiresAt)) {
         $expiresUnix = strtotime($clientExpiresAt);
         if ($expiresUnix === false) {
            return $this->failValidationErrors('Invalid expiresAt format. Must be a valid datetime string.');
         }

         $compareVisible = !empty($clientVisibleFrom) ? strtotime($clientVisibleFrom) : strtotime('+1 day 06:30:00');

         if ($expiresUnix <= $compareVisible) {
            return $this->failValidationErrors('Temporal manipulation blocked: expiresAt must be strictly after visibleFrom.');
         }
      }

      // Finalize the timestamps
      $visibleFrom = !empty($clientVisibleFrom)
         ? $clientVisibleFrom
         : date('Y-m-d 06:30:00', strtotime('+1 day'));

      $expiresAt = !empty($clientExpiresAt)
         ? $clientExpiresAt
         : date('Y-m-d 23:59:59', strtotime('+1 day'));
      // 3. Core Identity & Strict Validation
      if (empty($accountId)) {
         return $this->failUnauthorized('Security violation: Verified Account ID missing.');
      }

      if (empty($schoolId) || empty($profileId) || empty($sectionId) || trim($text) === '') {
         return $this->failValidationErrors(
            'School ID, Profile ID, Section ID, and Text are strictly required.',
         );
      }

      // 4. Frontline BOLA Shield
      if (!$this->verifyProfileOwnership((int) $accountId, (int) $profileId)) {
         return $this->failForbidden('Security violation: You do not own this profile.');
      }

      $db = \Config\Database::connect();

      // --- THE SAAS TIER ENFORCER ---

      // 1. Fetch the School's Plan Limits dynamically
      $planQuery = $db->query("
          SELECT
              mp.name AS plan_name,
              mp.max_updates_per_day,
              mp.max_future_days,
              (SELECT COUNT(id) FROM updates
               WHERE source_profile_id = ?
                 AND section_id = ?
                 AND DATE(created_at) = CURDATE()
                 AND deleted_at IS NULL) AS updates_today
          FROM schools s
          JOIN master_plans mp ON s.plan_id = mp.id
          WHERE s.id = ?
      ", [$profileId, $sectionId, $schoolId]);

      $planData = $planQuery->getRowArray();

      if (!$planData) {
         return $this->failServerError('Critical Error: Could not verify school subscription plan.');
      }

      // 2. Enforce the Rate Limit (Max Updates Per Day Per Section)
      $maxUpdates = (int) $planData['max_updates_per_day'];
      $updatesToday = (int) $planData['updates_today'];

      if ($updatesToday >= $maxUpdates) {
         return $this->failForbidden(
            "Rate limit exceeded: Your current plan allows a maximum of {$maxUpdates} updates per section per day.",
         );
      }

      // 3. Enforce the Scheduling Horizon Limit
      $maxFutureDays = (int) $planData['max_future_days'];

      if (!empty($clientVisibleFrom)) {
         // Calculate the absolute maximum allowed timestamp for this tier
         $maxAllowedUnix = strtotime("+{$maxFutureDays} days 23:59:59");
         $visibleUnix = strtotime($clientVisibleFrom);

         if ($visibleUnix > $maxAllowedUnix) {
            return $this->failForbidden(
               "Upgrade required: The {$planData['plan_name']} plan only allows scheduling up to {$maxFutureDays} days in advance.",
            );
         }
      }
      // --- END SAAS TIER ENFORCER ---

      try {
         $query = $db->query(
            "CALL sp_CreateUpdate(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
               $schoolId,
               $profileId,
               $contextId,
               $categoryId,
               $sectionId,
               $subjectId,
               $text,
               $visibleFrom,
               $expiresAt,
               $isLocked,
            ],
         );

         $row = $query->getRowArray();

         return $this->respondCreated([
            "message" => "Update created successfully.",
            "updateId" => (int) $row['newUpdateId'],
            "schedule" => [
               "visibleFrom" => $visibleFrom,
               "expiresAt" => $expiresAt,
            ],
         ]);

      } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
         if (strpos($e->getMessage(), '45000') !== false || strpos($e->getMessage(), 'SECURITY_VIOLATION') !== false) {
            return $this->failForbidden(
               'Security violation: Profile does not belong to the specified school or unauthorized cross-tenant action blocked.',
            );
         }

         log_message('error', '[DB Error in createUpdate]: ' . $e->getMessage());
         return $this->failServerError('Database failed to create the update.');
      }
   }
}