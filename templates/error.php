<?php

declare(strict_types=1);

/**
 * @var callable $e
 * @var int $status
 * @var string $heading
 * @var string $detail
 * @var string|null $correlationId
 */
?>
<div class="row justify-content-center">
    <div class="col-md-8">
        <h1 class="h4"><?= $e($heading) ?></h1>
        <?php if ($detail !== '') { ?>
            <p><?= $e($detail) ?></p>
        <?php } ?>
        <?php if ($correlationId !== null) { ?>
            <p class="text-body-secondary"><small>Reference: <code><?= $e($correlationId) ?></code></small></p>
        <?php } ?>
        <a class="btn btn-outline-secondary btn-sm" href="/">Back</a>
    </div>
</div>
