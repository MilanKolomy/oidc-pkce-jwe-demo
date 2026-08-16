<?php

declare(strict_types=1);

/**
 * @var callable $e
 * @var string|null $error
 */
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <h1 class="h4 mb-3">Add certificate</h1>

        <?php if ($error !== null) { ?>
            <div class="alert alert-warning"><?= $e($error) ?></div>
        <?php } ?>

        <form method="post" action="/certificates">
            <div class="mb-3">
                <label class="form-label" for="pem">Certificate in PEM form</label>
                <textarea class="form-control font-monospace" id="pem" name="pem" rows="14" required
                          maxlength="16384"
                          placeholder="-----BEGIN CERTIFICATE-----&#10;...&#10;-----END CERTIFICATE-----"></textarea>
                <div class="form-text">
                    Paste the public part only. Input containing a private key is refused
                    and is neither stored nor written to the log.
                </div>
            </div>

            <button class="btn btn-primary" type="submit">Add</button>
            <a class="btn btn-outline-secondary" href="/certificates">Cancel</a>
        </form>
    </div>
</div>
