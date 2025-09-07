<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     title="Duolingo API",
 *     version="1.0.0",
 *     description="API documentation for Duolingo application."
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 *
 * @OA\Server(
 *     url="http://localhost:8000",
 *     description="Local development server"
 * )
 */
class ApiDoc extends Controller {}