<?php

namespace App\Presentation;

use CodeIgniter\Debug\ExceptionHandlerInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/** Exception renderer used only by the loopback parity environment. */
final class ParityExceptionHandler implements ExceptionHandlerInterface
{
    public function handle(
        Throwable $exception,
        RequestInterface $request,
        ResponseInterface $response,
        int $statusCode,
        int $exitCode,
    ): void {
        $mode = $request instanceof IncomingRequest ? 'html' : 'cli';
        $kind = $this->kind($mode);
        $heading = match ($kind) {
            '404' => $mode === 'html' ? '404 Page Not Found' : 'Not Found',
            'db' => 'A Database Error Occurred',
            'general' => 'Parity General Error',
            default => 'Error',
        };
        $message = match ($kind) {
            '404' => $mode === 'html'
                ? '<p>The page you requested was not found.</p>'
                : "\tThe controller/method pair you requested was not found.",
            'db' => $mode === 'html'
                ? "<p>Error Number: 1146</p><p>Table 'samsonitetracking.__parity_missing_table' doesn't exist</p><p>SELECT * FROM __parity_missing_table</p><p>Filename: controllers/Parityerrors.php</p><p>Line Number: 40</p>"
                : "\tError Number: 1146\n\tTable 'samsonitetracking.__parity_missing_table' doesn't exist\n\tSELECT * FROM __parity_missing_table\n\tFilename: controllers/Parityerrors.php\n\tLine Number: 40",
            'exception' => 'Synthetic parity exception.',
            'general' => $mode === 'html' ? '<p>Synthetic parity error.</p>' : "\tSynthetic parity error.",
            'php' => 'Synthetic parity PHP error.',
            default => 'Synthetic parity error.',
        };
        $variables = [
            'exception' => $exception,
            'heading' => $heading,
            'message' => $message,
            'severity' => 'User Warning',
            'filepath' => 'controllers/Parityerrors.php',
            'line' => 35,
        ];
        $body = (new LegacyViewRenderer())->render('errors/' . $mode . '/error_' . $kind, $variables);

        if ($mode === 'html') {
            $response->setStatusCode($statusCode)->setContentType('text/html')->setBody($body)->send();
        } else {
            echo $body;
        }
        if (ENVIRONMENT !== 'testing') {
            exit($exitCode);
        }
    }

    private function kind(string $mode): string
    {
        $candidate = '';
        if ($mode === 'html') {
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
            $candidate = is_string($path) ? basename($path) : '';
        } else {
            $arguments = $_SERVER['argv'] ?? [];
            if (is_array($arguments)) {
                foreach (array_reverse($arguments) as $argument) {
                    if (in_array($argument, ['404', 'db', 'exception', 'general', 'php'], true)) {
                        $candidate = $argument;
                        break;
                    }
                }
            }
        }

        return in_array($candidate, ['404', 'db', 'exception', 'general', 'php'], true)
            ? $candidate
            : 'exception';
    }
}
