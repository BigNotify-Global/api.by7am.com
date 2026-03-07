<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

$routes->group('v1', ['namespace' => 'App\Controllers\Api'], function ($routes) {
   // --- Admin Endpoints ---
   $routes->group('admin', function ($routes) {
      $routes->post('addAccount', 'Admin::addUpdateAccount');
   });

   // --- Profile Endpoints ---
   $routes->group('profile', function ($routes) {
      $routes->post('addAccount', 'Profile::addUpdateAccount');
      $routes->post('createProfile', 'Profile::createProfile');
   });

   // --- Admin Endpoints ---
   $routes->group('uni', function ($routes) {
      $routes->post('createUpdate', 'Uni::createUpdate');
   });

   // --- Student Endpoints ---
   $routes->group('student', function ($routes) {
      $routes->post('dashboard', 'Student::dashboard');
      $routes->post('board', 'Student::board');
      $routes->post('feed', 'Student::feed');
   });

   // --- Teacher Endpoints ---
   $routes->group('teacher', function ($routes) {
      $routes->post('dashboard', 'Teacher::dashboard');
      $routes->post('feed', 'Teacher::feed');

      $routes->group('classes', function ($routes) {
         $routes->post('students', 'Teacher::directory');
         $routes->post('approvals', 'Teacher::approvals');
      });
   });
});