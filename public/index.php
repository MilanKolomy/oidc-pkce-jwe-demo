<?php

declare(strict_types=1);

use App\Config\Config;
use App\Exception\ConfigurationException;
use App\Exception\HttpException;
use App\Http\Problem;
use App\Http\Request;
use App\Http\Router;
use App\Http\Session;
use App\Http\UrlBuilder;
use App\Log\Logger;
use App\Oidc\Discovery;
use App\Oidc\HttpClient;
use App\Oidc\IdTokenValidator;
use App\Oidc\JwksCache;
use App\Oidc\TokenClient;
use App\Persistence\Database;
use App\Persistence\UserRepository;
use App\Token\TokenIssuer;
use App\Token\TokenKey;
use App\Web\AuthController;

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

    $urls = new UrlBuilder($_SERVER);
    $httpClient = new HttpClient();
    $database = new Database($config);

    $tokenKey = TokenKey::fromFile($root . '/keys/app-token.key');
    $auth = new AuthController(
        new Discovery($httpClient),
        new TokenClient($httpClient, $config->googleClientId, $config->googleClientSecret),
        new IdTokenValidator(new JwksCache($httpClient, $root . '/var/jwks.json'), $config->googleClientId),
        new UserRepository($database),
        new TokenIssuer($tokenKey),
        $urls,
        $logger,
        $config->googleClientId,
    );

    $router = new Router();

    $router->add('GET', '/login', $auth->login(...));
    $router->add('GET', '/callback', $auth->callback(...));

    $response = $router->dispatch($request);
} catch (HttpException $exception) {
    // Expected outcomes described in docs/openapi.yaml, not failures, so they are not
    // logged by default — a stream of 404s would bury the entries that matter. The
    // exceptions that deliberately tell the caller less than they know carry the real
    // reason, and that much is worth keeping.
    if ($exception->logReason() !== null) {
        $logger->warning(sprintf('%d %s', $exception->status(), $exception->title()), [
            'reason' => $exception->logReason(),
            'method' => $request->method,
            'path' => $request->path,
        ]);
    }

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
