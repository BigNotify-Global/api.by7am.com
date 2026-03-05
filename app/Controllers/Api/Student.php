<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;

class Student extends ResourceController
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
      // SECURITY LOCK: The frontend no longer dictates who it is requesting data for.
      $accountId = $this->request->getHeaderLine('X-Account-Id');

      if (empty($accountId)) {
         return $this->failUnauthorized('Security violation: Verified Account ID missing.');
      }

      $db = \Config\Database::connect();
      $results = $db->query(
         "CALL sp_GetStudentDashboard(?)",
         [$accountId],
      )->getResultArray();

      if (empty($results)) {
         return $this->failNotFound('Student profile not found.');
      }

      $linkedSchools = [];

      foreach ($results as $row) {
         $sId = (int) $row['schoolId'];

         if (!isset($linkedSchools[$sId])) {
            $linkedSchools[$sId] = [
               "schoolId" => $sId,
               "schoolName" => $row['schoolName'],
               "address" => $row['address'],
               "city" => $row['city'],
               "students" => [],
            ];
         }

         $linkedSchools[$sId]['students'][] = [
            "profileId" => (int) $row['profileId'],
            "profileName" => $row['profileName'],
            "status" => $row['statusName'],
            "academic" => [
               "sessionName" => $row['sessionName'],
               "className" => $row['standardName'],
               "section" => $row['sectionName'],
               "sectionId" => (int) $row['sectionId'],
               "rollNumber" => $row['rollNumber'],
            ],
            "stats" => [
               "todaysUpdates" => (int) $row['todaysUpdates'],
               "hasUrgent" => (bool) $row['hasUrgent'],
               "isActive" => (bool) $row['isActive'],
            ],
         ];
      }

      return $this->respond(["linkedSchools" => array_values($linkedSchools)]);
   }

   public function board()
   {
      $accountId = $this->request->getHeaderLine('X-Account-Id');
      $profileId = $this->request->getVar('profileId');

      if (empty($accountId))
         return $this->failUnauthorized(
            'Security violation: Verified Account ID missing.',
         );
      if (empty($profileId))
         return $this->failValidationErrors(
            'Profile ID is required.',
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
      $results = $db->query(
         "CALL sp_GetStudentBoard(?)",
         [$profileId],
      )->getResultArray();

      if (empty($results)) {
         return $this->failNotFound('Board data not found.');
      }

      $response = [
         "schoolBoard" => ["totalUpdates" => (int) $results[0]["boardTotalUpdates"]],
         "classTeacher" => null,
         "academicBoard" => [],
      ];

      foreach ($results as $row) {
         $item = [
            "profileId" => (int) $row['teacherProfileId'],
            "name" => $row['teacherName'],
            "designation" => $row['teacherDesignation'],
            "subjectId" => (int) $row['subjectId'],
            "subjectName" => $row['subjectName'],
            "linkParams" => [
               "sectionId" => (int) $row['sectionId'],
               "subjectId" => (int) $row['subjectId']
            ],
            "stats" => [
               "totalUpdates" => (int) $row['subjectTotalUpdates'],
            ],
         ];

         if ($row['isClassTeacher']) {
            $response['classTeacher'] = $item;
         }
         else {
            $response['academicBoard'][] = $item;
         }
      }

      return $this->respond($response);
   }


   public function feed()
   {
      $accountId = $this->request->getHeaderLine('X-Account-Id');
      $profileId = $this->request->getVar('profileId');
      $subjectId = $this->request->getVar('subjectId');

      if (empty($accountId))
         return $this->failUnauthorized(
            'Security violation: Verified Account ID missing.',
         );
      if (empty($profileId || empty($subjectId)))
         return $this->failValidationErrors(
            'Profile ID and Subject ID is required.',
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
      $results = $db->query(
         "CALL sp_GetStudentFeed(?, ?)",
         [$profileId, $subjectId],
      )->getResultArray();

      if (empty($results)) {
         return $this->respond(
            ["header" => null, "allUpdates" => []],
         );
      }

      $header = [
         "title" => $results[0]['subjectName'],
         "subtitle" => $results[0]['teacherName'],
         "designation" => $results[0]['teacherDesignation'],
      ];

      $allUpdates = array_map(function ($row) {
         return [
            "updateId" => (int) $row['updateId'],
            "categoryId" => (int) $row['categoryId'],
            "categoryName" => $row['categoryName'],
            "text" => $row['text'],
            "isUrgent" => (bool) $row['isUrgent'],
            "isLocked" => (bool) $row['isLocked'],
            "createdAt" => $row['createdAt'],
            "expiresAt" => $row['expiresAt'],
         ];
      }, $results);

      return $this->respond([
         "header" => $header,
         "allUpdates" => $allUpdates,
      ]);
   }
}