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
}