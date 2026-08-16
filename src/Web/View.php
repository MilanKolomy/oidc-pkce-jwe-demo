<?php

declare(strict_types=1);

namespace App\Web;

use RuntimeException;
use Throwable;

/**
 * Plain PHP templates, no engine (ADR-0005).
 *
 * Every template receives $e, and every value written into the page goes through
 * it. A template that interpolates a variable directly is a defect, not a style
 * choice — the registry stores text that came from certificates other people issued.
 */
final class View
{
    public function __construct(private readonly string $directory)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): string
    {
        $file = $this->directory . '/' . $template . '.php';

        if (!is_file($file)) {
            throw new RuntimeException(sprintf('Template %s does not exist.', $template));
        }

        $data['e'] = static fn (mixed $value): string => htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        extract($data, EXTR_SKIP);
        ob_start();

        try {
            require $file;
        } catch (Throwable $exception) {
            // Otherwise a failure halfway through a template would leave its partial
            // output in the buffer and be sent alongside the error response.
            ob_end_clean();

            throw $exception;
        }

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function page(string $template, string $title, ?string $userEmail, array $data = []): string
    {
        return $this->render('layout', [
            'title' => $title,
            'userEmail' => $userEmail,
            'content' => $this->render($template, $data),
        ]);
    }
}
