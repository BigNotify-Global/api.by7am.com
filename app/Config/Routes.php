<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

$routes->group('v1', ['namespace' => 'App\Controllers\Api'], function ($routes) {
   // --- Admin Endpoints ---
   $routes->group('admin', function ($routes) {
      $routes->post('demo', 'Admin::demo');
   });

   // --- Profile Endpoints ---
   $routes->group('profile', function ($routes) {
      $routes->post('addAccount', 'Profile::addUpdateAccount');
      $routes->post('createProfile', 'Profile::createProfile');
   });

   // --- Uni Endpoints ---
   $routes->group('uni', function ($routes) {
      $routes->post('createUpdate', 'Uni::createUpdate');
   });

   // --- Student Endpoints ---
   $routes->group('student', function ($routes) {
      $routes->get('dashboard', 'Student::dashboard');
      $routes->get('board', 'Student::board');
      $routes->get('feed', 'Student::feed');
   });

   // --- Teacher Endpoints ---
   $routes->group('teacher', function ($routes) {
      $routes->get('dashboard', 'Teacher::dashboard');
      $routes->get('feed', 'Teacher::feed');

      $routes->group('classes', function ($routes) {
         $routes->get('students', 'Teacher::directory');
         $routes->get('approvals', 'Teacher::approvals');
      });
   });
});