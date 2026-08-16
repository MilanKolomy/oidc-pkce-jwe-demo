<?php

declare(strict_types=1);

/**
 * @var callable $e
 * @var array<string, mixed> $certificate
 * @var list<array<string, mixed>> $keyUsages
 * @var list<array<string, mixed>> $checks
 * @var string $status
 */

$badge = [
    'valid' => 'success',
    'not_yet_valid' => 'secondary',
    'expired' => 'danger',
];
?>
<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <h1 class="h4 mb-1"><?= $e($certificate['common_name']) ?></h1>
        <span class="badge text-bg-<?= $e($badge[$status] ?? 'secondary') ?>">
            <?= $e(str_replace('_', ' ', $status)) ?>
        </span>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="/certificates">Back to list</a>
</div>

<div class="card mb-3">
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-4">Subject</dt>
            <dd class="col-sm-8"><code><?= $e($certificate['subject_dn']) ?></code></dd>

            <dt class="col-sm-4">Issued by</dt>
            <dd class="col-sm-8"><code><?= $e($certificate['authority_subject_dn']) ?></code></dd>

            <dt class="col-sm-4">Serial number</dt>
            <dd class="col-sm-8"><code><?= $e($certificate['serial_number']) ?></code></dd>

            <dt class="col-sm-4">Valid from</dt>
            <dd class="col-sm-8"><?= $e($certificate['valid_from']) ?> UTC</dd>

            <dt class="col-sm-4">Valid to</dt>
            <dd class="col-sm-8"><?= $e($certificate['valid_to']) ?> UTC</dd>

            <dt class="col-sm-4">Signature</dt>
            <dd class="col-sm-8"><?= $e($certificate['signature_algorithm'] ?? '—') ?></dd>

            <dt class="col-sm-4">Public key</dt>
            <dd class="col-sm-8">
                <?= $e($certificate['public_key_algorithm'] ?? '—') ?>
                <?php if ($certificate['public_key_bits'] !== null) { ?>
                    <?= $e((string) $certificate['public_key_bits']) ?> bits
                <?php } ?>
            </dd>

            <dt class="col-sm-4">SHA-256 fingerprint</dt>
            <dd class="col-sm-8"><code class="user-select-all"><?= $e($certificate['fingerprint_sha256']) ?></code></dd>

            <dt class="col-sm-4">Key usage</dt>
            <dd class="col-sm-8">
                <?php if ($keyUsages === []) { ?>
                    —
                <?php } else { ?>
                    <?php foreach ($keyUsages as $usage) { ?>
                        <span class="badge text-bg-light border"><?= $e($usage['label']) ?></span>
                    <?php } ?>
                <?php } ?>
            </dd>

            <dt class="col-sm-4">Registered</dt>
            <dd class="col-sm-8"><?= $e($certificate['created_at']) ?> UTC</dd>
        </dl>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h6 mb-0">Validity checks</h2>
            <form method="post" action="/certificates/<?= $e((string) $certificate['id']) ?>/checks">
                <button class="btn btn-outline-primary btn-sm" type="submit">Check now</button>
            </form>
        </div>

        <table class="table table-sm mb-0">
            <thead>
                <tr>
                    <th scope="col">Checked at</th>
                    <th scope="col">Result</th>
                    <th scope="col">Note</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($checks as $check) { ?>
                    <tr>
                        <td><?= $e($check['checked_at']) ?> UTC</td>
                        <td><?= $e(str_replace('_', ' ', (string) $check['result'])) ?></td>
                        <td class="text-body-secondary"><?= $e($check['detail'] ?? '') ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>

        <p class="text-body-secondary mt-3 mb-0">
            <small>
                A check compares the current time against the validity period. The result
                can differ between checks, which is why each one is kept.
            </small>
        </p>
    </div>
</div>
