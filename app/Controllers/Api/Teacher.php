<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;

class Teacher extends ResourceController
{
   /**
    * GET /v1/teacher/dashboard
    */
   public function dashboard()
   {
      // 1. Guard Clause: Fail fast, don't wrap your entire function in an IF block.
      $accountId = $this->request->getVar('accountId');
      if (empty($accountId)) {
         return $this->respond(["error" => "Account ID not provided"], 400);
      }

      $db = \Config\Database::connect();

      // 2. Fetch the SINGLE row from our highly optimized SP
      $query = $db->query("CALL sp_GetTeacherDashboard(?)", [$accountId]);
      $row = $query->getRowArray(); // Grab EXACTLY one row

      if (!$row) {
         return $this->respond(["error" => "Teacher profile not found"], 404);
      }

      // 3. Decode the MySQL JSON array immediately
      $allocations = json_decode($row['allocationsJson'], true) ?? [];

      // 4. Build the parent profile instantly (No nested loops, no redundant checks)
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

      // 5. Route the allocations cleanly
      foreach ($allocations as $alloc) {
         $formattedAllocation = [
            "allocationId" => (int) $alloc['allocationId'],
            "sectionId" => (int) $alloc['sectionId'],
            "className" => $alloc['className'], // SP already formatted this as "VIII-C"
            "subjectId" => (int) $alloc['subjectId'],
            "subjectName" => $alloc['subjectName'],

            // UI Mock Data - The DB doesn't provide these yet
            "isClassTeacher" => (bool) $alloc['isClassTeacher'],
            "contextCode" => $alloc['isClassTeacher'] ? "CLASS_TEACHER" : "SUBJECT",

            "stats" => [
               "lastUpdate" => null,
               "unreadInteractions" => 0,
               "totalStudents" => 0,
               "updateCount" => 0,
            ],
         ];

         // Route to the correct bucket based on their role
         if ($alloc['isClassTeacher']) {
            $response['assignedClass'] = $formattedAllocation;
         }
         else {
            $response['subjectAllocations'][] = $formattedAllocation;
         }
      }

      return $this->respond($response);
   }


   /**
    * POST /v1/teacher/feed
    * Logic: Calculates engagement percentages for teacher-posted updates.
    * Payload: { "sectionId": 25, "subjectId": 3 }
    */
   public function feed()
   {
      $db = \Config\Database::connect();
      $profileId = $this->request->getVar('profileId');
      $sectionId = $this->request->getVar('sectionId');
      $subjectId = $this->request->getVar('subjectId');

      if (!$sectionId) {
         return $this->fail('Section ID is required for analytics.');
      }

      // SP returns: updateId, text, categoryId, iconKey, createdAt, isUrgent, ackCount, totalStudents
      $query = $db->query("CALL sp_GetTeacherFeed(?, ?, ?)", [$profileId, $sectionId, $subjectId]);
      $results = $query->getResultArray();

      $header = [];
      $allUpdates = [];

      foreach ($results as $row) {
         // Set header from the first available row
         if (empty($header)) {
            $header = [
               "title" => $row['headerTitle'] ?? 'General',
               "subtitle" => $row['headerSubtitle'],
            ];
         }

         $ackCount = (int) $row['ackCount'];
         $totalStudents = (int) $row['totalStudents'];

         // Strategic Rigor: Avoid division by zero if a class has no students
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


   /**
    * GET /v1/teacher/classes/{sectionId}/students
    */
   public function directory()
   {
      $profileId = $this->request->getVar('profileId');
      $sectionId = $this->request->getVar('sectionId');

      $db = \Config\Database::connect();
      $query = $db->query("CALL sp_GetTeacherStudentDirectory(?, ?)", [$profileId, $sectionId]);
      $results = $query->getResultArray();

      $studentsMap = [];
      $classInfo = ["sectionId" => (int) $sectionId, "className" => "", "totalStudents" => 0];

      foreach ($results as $row) {
         // Grab class name from the first row (it's the same for all rows)
         if (empty($classInfo['className'])) {
            $classInfo['className'] = $row['className'];
         }

         // Map gender quickly
         $genderCode = strtolower($row['genderCode'] ?? '');
         $studentGender = ($genderCode === 'm') ? 'Male' : (($genderCode === 'f') ? 'Female' : 'Other');

         // Because the DB already grouped the rows, we just decode and push!
         $students[] = [
            "profileId" => (int) $row['studentProfileId'],
            "rollNumber" => $row['rollNumber'],
            "displayName" => $row['studentName'],
            "sectionId" => (int) $row['sectionId'],
            "gender" => $studentGender,
            "guardian" => json_decode($row["guardiansJson"], true) ?? [], // Instant mapping
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

   /**
    * GET /v1/teacher/classes/{sectionId}/approvals
    */
   public function approvals()
   {
      $profileId = $this->request->getVar('profileId');
      $sectionId = $this->request->getVar('sectionId');

      $db = \Config\Database::connect();
      $query = $db->query("CALL sp_GetTeacherPendingRequests(?, ?)", [$profileId, $sectionId]);
      $results = $query->getResultArray();

      $requests = [];
      $classInfo = ["sectionId" => (int) $sectionId, "className" => "", "totalStudents" => 0, "totalPending" => 0];

      foreach ($results as $row) {
         if (empty($classInfo['className'])) {
            $classInfo['className'] = $row['className'];
         }

         // Properly decode the JSON from the Stored Procedure!
         $contacts = json_decode($row['contactsJson'], true) ?? [];
         $studentGender = ($genderCode === 'm') ? 'Male' : (($genderCode === 'f') ? 'Female' : 'Other');

         $requests[] = [
            "membershipId" => (int) $row['membershipId'],
            "requestedAt" => null, // Note: We didn't add createdAt to the SP earlier, you may need to add it!
            "profileId" => (int) $row['studentProfileId'],
            "displayName" => $row['studentName'],
            "gender" => $studentGender,
            "requestedRollNumber" => $row['requestedRollNumber'],
            "contacts" => $contacts,
            "conflictWarning" => null, // Backend conflict logic goes here later
         ];
      }

      $classInfo['totalPending'] = count($requests);

      return $this->respond([
         "class" => $classInfo,
         "requests" => $requests,
      ]);
   }
}