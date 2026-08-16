<?php

declare(strict_types=1);

/**
 * @var callable $e
 * @var string $title
 * @var string $content
 * @var string|null $userEmail
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($title) ?> — Certificate registry</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
      integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>
<body class="bg-body-tertiary">

<nav class="navbar navbar-expand bg-body border-bottom mb-4">
    <div class="container">
        <a class="navbar-brand" href="/">Certificate registry</a>
        <?php if ($userEmail !== null) { ?>
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="/certificates">Certificates</a></li>
                <li class="nav-item"><a class="nav-link" href="/profile">Profile</a></li>
            </ul>
            <span class="navbar-text me-3"><?= $e($userEmail) ?></span>
            <a class="btn btn-outline-secondary btn-sm" href="/logout">Sign out</a>
        <?php } ?>
    </div>
</nav>

<main class="container pb-5">
<?= $content /* already escaped by the inner template */ ?>
</main>

<footer class="container text-body-secondary border-top py-3">
    <small>
        Study project. The application does not issue certificates, and validity is
        evaluated against the validity period only — revocation and the trust chain
        are not verified.
        <a href="/swagger/">API documentation</a>
    </small>
</footer>

</body>
</html>
