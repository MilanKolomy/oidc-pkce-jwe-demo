<?php

declare(strict_types=1);

/**
 * @var callable $e
 * @var list<array<string, mixed>> $certificates
 * @var string|null $status
 * @var int $total
 */

$badge = [
    'valid' => 'success',
    'not_yet_valid' => 'secondary',
    'expired' => 'danger',
];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Certificates</h1>
    <a class="btn btn-primary" href="/certificates/new">Add certificate</a>
</div>

<ul class="nav nav-pills mb-3">
    <?php foreach (['' => 'All', 'valid' => 'Valid', 'not_yet_valid' => 'Not yet valid', 'expired' => 'Expired'] as $value => $label) { ?>
        <li class="nav-item">
            <a class="nav-link <?= ($status ?? '') === $value ? 'active' : '' ?>"
               href="/certificates<?= $value === '' ? '' : '?status=' . $e($value) ?>"><?= $e($label) ?></a>
        </li>
    <?php } ?>
</ul>

<?php if ($certificates === []) { ?>
    <div class="card">
        <div class="card-body text-body-secondary">
            Nothing here yet. <a href="/certificates/new">Add a certificate</a> in PEM form.
        </div>
    </div>
<?php } else { ?>
    <div class="table-responsive">
        <table class="table table-hover bg-body align-middle">
            <thead>
                <tr>
                    <th scope="col">Common name</th>
                    <th scope="col">Issued by</th>
                    <th scope="col">Valid to</th>
                    <th scope="col">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($certificates as $certificate) { ?>
                    <tr>
                        <td>
                            <a href="/certificates/<?= $e((string) $certificate['id']) ?>">
                                <?= $e($certificate['common_name']) ?>
                            </a>
                        </td>
                        <td><?= $e($certificate['authority_common_name']) ?></td>
                        <td><?= $e($certificate['valid_to']) ?></td>
                        <td>
                            <span class="badge text-bg-<?= $e($badge[$certificate['status']] ?? 'secondary') ?>">
                                <?= $e(str_replace('_', ' ', (string) $certificate['status'])) ?>
                            </span>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

    <p class="text-body-secondary"><small><?= $e((string) $total) ?> certificate(s).</small></p>
<?php } ?>
