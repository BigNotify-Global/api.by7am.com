<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;

class Student extends ResourceController
{
   public function dashboard()
   {
      $accountId = $this->request->getVar('accountId');
      if (empty($accountId))
         return $this->failValidationErrors('Account ID is required.');

      $db = \Config\Database::connect();
      $results = $db->query("CALL sp_GetStudentDashboard(?)", [$accountId])->getResultArray();

      if (empty($results))
         return $this->failNotFound('Student profile not found.');

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
      $profileId = $this->request->getVar('profileId');
      if (empty($profileId))
         return $this->failValidationErrors('Profile ID is required.');

      $db = \Config\Database::connect();
      $results = $db->query("CALL sp_GetStudentBoard(?)", [$profileId])->getResultArray();

      if (empty($results))
         return $this->failNotFound('Board data not found.');

      // Initialize the response structure instantly from the first row
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
      $profileId = $this->request->getVar('profileId');
      $subjectId = $this->request->getVar('subjectId');

      if (empty($profileId) || empty($subjectId)) {
         return $this->failValidationErrors('Profile ID and Subject ID are required.');
      }

      $db = \Config\Database::connect();
      $results = $db->query("CALL sp_GetStudentFeed(?, ?)", [$profileId, $subjectId])->getResultArray();

      if (empty($results)) {
         return $this->respond(["header" => null, "allUpdates" => []]); // Empty state, not a 404 error
      }

      // Header comes from the first row natively
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