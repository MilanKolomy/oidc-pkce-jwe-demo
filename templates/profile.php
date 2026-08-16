<?php

declare(strict_types=1);

/**
 * @var callable $e
 * @var array<string, mixed> $user
 * @var int $certificateCount
 */
?>
<h1 class="h4 mb-3">Profile</h1>

<div class="card">
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-4">E-mail</dt>
            <dd class="col-sm-8"><?= $e($user['email']) ?></dd>

            <dt class="col-sm-4">Name</dt>
            <dd class="col-sm-8"><?= $e($user['display_name'] ?? '—') ?></dd>

            <dt class="col-sm-4">Registered</dt>
            <dd class="col-sm-8"><?= $e($user['created_at']) ?> UTC</dd>

            <dt class="col-sm-4">Last sign-in</dt>
            <dd class="col-sm-8"><?= $e($user['last_login_at'] ?? '—') ?> UTC</dd>

            <dt class="col-sm-4">Certificates</dt>
            <dd class="col-sm-8"><?= $e((string) $certificateCount) ?></dd>
        </dl>
    </div>
</div>

<p class="text-body-secondary mt-3">
    <small>
        The account is paired to the immutable subject identifier from the provider, not
        to the e-mail address, so changing the address there does not create a new account.
    </small>
</p>
