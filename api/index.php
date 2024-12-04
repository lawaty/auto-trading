<?php

ob_start();

require_once "autoload.php";


try {
  // Initialize the logger
  $logger = ArteLogger::getLogger();
} catch (PermissionDenied $e) {
  die('Fix project permissions');
}

$router = ArteRouter::getInst();
$route = $router->route();

if (!isset($route->endpoint))
  $response = new Response("Endpoint Not Found", 404);
else {
  try {
    if ($route->test && $route->endpoint instanceof Testable)
      $response = $route->endpoint->test();
    else
      $response = $route->endpoint->run();
  } catch (Exception | Error $e) {
    if ($e instanceof Error)
      $code = 500;
    else
      $code = is_integer($e->getCode()) ? $e->getCode() : 500;
    if (DEBUG)
      $response = new Response(trace($e), $code);
    else {
      echo trace($e); // Echoing the error to the hidden buffer before client echo
      $response = new Response('Something Went Wrong!', $code);
    }
  }
}


$response->echo();

// Log the request and response
$logger->log($route, $response);
