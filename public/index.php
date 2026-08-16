<?php

declare(strict_types=1);

use App\Config\Config;
use App\Exception\ConfigurationException;
use App\Exception\HttpException;
use App\Http\Problem;
use App\Http\Request;
use App\Http\Router;
use App\Http\Session;
use App\Log\Logger;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);

// Identifies this request in the log and in the 500 response, so a production
// failure can be traced back to its entry without the response carrying the
// cause (OMZ-04).
$correlationId = bin2hex(random_bytes(8));

$request = Request::fromGlobals();
$logger = new Logger($root . '/var/app.log', $correlationId);
$config = null;

try {
    $config = Config::load($root . '/.env');

    Session::configure();

    $router = new Router();

    // Routes are registered here as the application grows.

    $response = $router->dispatch($request);
} catch (HttpException $exception) {
    // Expected outcomes described in docs/openapi.yaml, not failures. Not logged:
    // a stream of 404s would bury the entries that matter.
    $response = Problem::fromException($exception, $request->path);
} catch (ConfigurationException $exception) {
    // Startup failed before the environment was known, so the response is the careful
    // one either way. The message names what is missing, never a value, and goes to
    // the log — where an operator without shell access can still reach it.
    $logger->error('Configuration error: ' . $exception->getMessage());

    $response = Problem::internal($request->path, $correlationId);
} catch (Throwable $exception) {
    $logger->error(sprintf('Unhandled %s: %s', $exception::class, $exception->getMessage()), [
        'file' => $exception->getFile() . ':' . $exception->getLine(),
        'method' => $request->method,
        'path' => $request->path,
    ]);

    $response = Problem::internal(
        $request->path,
        $correlationId,
        // If the failure happened before the configuration was read, the environment
        // is unknown — say nothing, which is the production behaviour.
        ($config?->isProduction() ?? true) ? null : $exception->getMessage(),
    );
}

$response->send();
