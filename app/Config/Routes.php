<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

$routes->group('v1', ['namespace' => 'App\Controllers\Api'], function ($routes) {
   // --- Admin Endpoints ---
   $routes->group('admin', function ($routes) {
      $routes->post('addAccount', 'Admin::addUpdateAccount');
      $routes->post('createProfile', 'Admin::createProfile');
   });

   // --- Admin Endpoints ---
   $routes->group('uni', function ($routes) {
      $routes->post('createUpdate', 'Admin::addUpdateAccount');
   });

   // --- Student Endpoints ---
   $routes->group('student', function ($routes) {
      $routes->post('dashboard', 'Student::dashboard'); // Payload: { "id": "142" }
      $routes->post('board', 'Student::board');     // Payload: { "profileId": 123 }
      $routes->post('feed', 'Student::feed');      // Payload: { "profileId": 123, "sectionId": 45 }
   });

   // --- Teacher Endpoints ---
   $routes->group('teacher', function ($routes) {
      $routes->post('dashboard', 'Teacher::dashboard'); // Payload: { "profileId": 123 }
      $routes->post('feed', 'Teacher::feed');      // Payload: { "sectionId": 25, "subjectId": 5 }

      $routes->group('classes', function ($routes) {
         $routes->post('students', 'Teacher::directory'); // Payload: { "sectionId": 25 }
         $routes->post('approvals', 'Teacher::approvals'); // Payload: { "sectionId": 25 }
      });
   });
});