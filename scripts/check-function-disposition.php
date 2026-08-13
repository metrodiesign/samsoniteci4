<?php

declare(strict_types=1);

const SOURCE_ROOTS = [
    'application/controllers',
    'application/models',
    'application/helpers',
    'application/libraries',
    'application/config',
];

const ADDITIONAL_PHP_FILES = [
    'lib/aes_encrypt.php',
    'lib/files2.0.php',
    'lib/mail.php',
    'lib/mysqli.php',
    'lib/timer.php',
];

const INLINE_JAVASCRIPT_ROOT = 'application/views';

const CUSTOM_JAVASCRIPT_FILES = [
    'assets/js/addUser.js',
    'assets/js/editUserB.js',
    'assets/js/editUser.js',
    'assets/js/addEstimateprice.js',
    'assets/js/common.js',
    'assets/js/addCondition.js',
    'assets/js/addProvider.js',
    'assets/js/addOrder.js',
    'assets/js/addStatustype.js',
    'assets/js/addFixed.js',
    'assets/js/addContact.js',
    'assets/js/addtrack.js',
    'assets/js/validation.js',
    'assets/js/addBranchtype.js',
    'assets/js/addMenu.js',
    'assets/js/multifreezer.js',
    'assets/js/addUserB.js',
    'assets/js/addProducttype.js',
    'assets/js/addBook.js',
    'assets/js/admin_addOrder.js',
    'assets/js/addBranch.js',
    'assets/js/editBranch.js',
    'assets/js/addBrand.js',
    'assets/js/browse/script.js',
];

if ($argc !== 2) {
    fwrite(STDERR, "Usage: php scripts/check-function-disposition.php <report.md>\n");
    exit(2);
}

$projectRoot = dirname(__DIR__);
$reportPath = $argv[1];
if (!str_starts_with($reportPath, DIRECTORY_SEPARATOR)) {
    $reportPath = $projectRoot . DIRECTORY_SEPARATOR . $reportPath;
}

if (!is_file($reportPath)) {
    fwrite(STDERR, "Report not found: {$reportPath}\n");
    exit(2);
}

$report = file_get_contents($reportPath);
if ($report === false) {
    fwrite(STDERR, "Cannot read report: {$reportPath}\n");
    exit(2);
}
$reportLines = preg_split('/\R/', $report);
if ($reportLines === false) {
    fwrite(STDERR, "Cannot split report into lines: {$reportPath}\n");
    exit(2);
}

$phpFiles = [];
foreach (SOURCE_ROOTS as $sourceRoot) {
    $absoluteRoot = $projectRoot . DIRECTORY_SEPARATOR . $sourceRoot;
    if (!is_dir($absoluteRoot)) {
        fwrite(STDERR, "Source root not found: {$sourceRoot}\n");
        exit(2);
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($absoluteRoot, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $phpFiles[] = $file->getPathname();
    }
}

foreach (ADDITIONAL_PHP_FILES as $relativePath) {
    $absolutePath = $projectRoot . DIRECTORY_SEPARATOR . $relativePath;
    if (!is_file($absolutePath)) {
        fwrite(STDERR, "PHP source not found: {$relativePath}\n");
        exit(2);
    }
    $phpFiles[] = $absolutePath;
}

$phpFiles = array_values(array_unique($phpFiles));
sort($phpFiles);
$functions = [];
$functionCollisions = [];
foreach ($phpFiles as $phpFile) {
    $contents = file_get_contents($phpFile);
    if ($contents === false) {
        fwrite(STDERR, "Cannot read source: {$phpFile}\n");
        exit(2);
    }

    $relativePath = substr($phpFile, strlen($projectRoot) + 1);
    $tokens = token_get_all($contents);
    $tokenCount = count($tokens);

    for ($index = 0; $index < $tokenCount; $index++) {
        if (!is_array($tokens[$index]) || $tokens[$index][0] !== T_FUNCTION) {
            continue;
        }

        for ($lookahead = $index + 1; $lookahead < $tokenCount; $lookahead++) {
            $token = $tokens[$lookahead];
            if (is_array($token) && in_array($token[0], whitespaceAndAmpersandTokens(), true)) {
                continue;
            }

            if (is_array($token) && $token[0] === T_STRING) {
                $citation = $relativePath . ':' . $token[2];
                if (isset($functions[$citation])) {
                    $functionCollisions[] = $citation;
                }
                $functions[$citation] = $token[1];
            }
            break;
        }
    }
}

ksort($functions);
$missing = [];
$semanticMissing = [];
$matchedRowLines = [];
foreach ($functions as $citation => $symbol) {
    if (!str_contains($report, '`' . $citation . '`')) {
        $missing[$citation] = $symbol;
        continue;
    }

    $rowLine = findEvidenceRow($reportLines, $citation, 'PHP');
    if ($rowLine === null) {
        $semanticMissing[$citation] = $symbol;
    } else {
        $matchedRowLines[] = $rowLine;
    }
}

$javascript = discoverJavascriptFunctions($projectRoot);
$missingJavascript = [];
$semanticMissingJavascript = [];
foreach ($javascript as $citation) {
    if (!str_contains($report, '`' . $citation . '`')) {
        $missingJavascript[] = $citation;
        continue;
    }

    $rowLine = findEvidenceRow($reportLines, $citation, 'JS');
    if ($rowLine === null) {
        $semanticMissingJavascript[] = $citation;
    } else {
        $matchedRowLines[] = $rowLine;
    }
}

$registeredRows = registeredEvidenceRows($reportLines);
$registeredIds = array_column($registeredRows, 'id');
$registeredAcceptanceIds = array_column($registeredRows, 'acceptance');
$duplicateIds = count($registeredIds) - count(array_unique($registeredIds));
$duplicateAcceptanceIds = count($registeredAcceptanceIds) - count(array_unique($registeredAcceptanceIds));
$duplicateJavascriptCitations = count($javascript) - count(array_unique($javascript));
$expectedRows = count($functions) + count($javascript);
$uniqueMatchedRows = count(array_unique($matchedRowLines));

$total = count($functions);
$covered = $total - count($missing);
printf("PHP named functions: %d\n", $total);
printf("Exact source citations: %d/%d\n", $covered, $total);
printf("Missing citations: %d\n", count($missing));
printf("PHP semantic evidence rows: %d/%d\n", $total - count($missing) - count($semanticMissing), $total);
printf("JavaScript raw callable tokens: %d\n", count($javascript));
printf(
    "Exact JavaScript citations: %d/%d\n",
    count($javascript) - count($missingJavascript),
    count($javascript)
);
printf("Missing JavaScript citations: %d\n", count($missingJavascript));
printf(
    "JavaScript semantic evidence rows: %d/%d\n",
    count($javascript) - count($missingJavascript) - count($semanticMissingJavascript),
    count($javascript)
);
printf("Registered Function rows: %d/%d\n", count($registeredRows), $expectedRows);
printf("Unique matched row lines: %d/%d\n", $uniqueMatchedRows, $expectedRows);
printf("Duplicate Function IDs: %d\n", $duplicateIds);
printf("Duplicate AC-FUNC IDs: %d\n", $duplicateAcceptanceIds);
printf("PHP same-line declaration collisions: %d\n", count($functionCollisions));
printf("Duplicate JavaScript citations: %d\n", $duplicateJavascriptCitations);
printf("Report SHA-256: %s\n", hash_file('sha256', $reportPath));

if ($missing !== []) {
    foreach ($missing as $citation => $symbol) {
        fwrite(STDERR, "MISSING {$citation} {$symbol}\n");
    }
}

foreach ($missingJavascript as $citation) {
    fwrite(STDERR, "MISSING JS {$citation}\n");
}

foreach ($semanticMissing as $citation => $symbol) {
    fwrite(STDERR, "INVALID ROW {$citation} {$symbol}\n");
}

foreach ($semanticMissingJavascript as $citation) {
    fwrite(STDERR, "INVALID JS ROW {$citation}\n");
}

foreach ($functionCollisions as $citation) {
    fwrite(STDERR, "PHP SAME-LINE COLLISION {$citation}\n");
}

if (
    $missing !== []
    || $missingJavascript !== []
    || $semanticMissing !== []
    || $semanticMissingJavascript !== []
    || count($registeredRows) !== $expectedRows
    || $uniqueMatchedRows !== $expectedRows
    || $duplicateIds !== 0
    || $duplicateAcceptanceIds !== 0
    || $functionCollisions !== []
    || $duplicateJavascriptCitations !== 0
) {
    exit(1);
}

exit(0);

function whitespaceAndAmpersandTokens(): array
{
    $tokens = [T_WHITESPACE];
    foreach (['T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG', 'T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG'] as $name) {
        if (defined($name)) {
            $tokens[] = constant($name);
        }
    }
    return $tokens;
}

function findEvidenceRow(array $reportLines, string $citation, string $language): ?int
{
    $identityPattern = '/^\| `F-' . preg_quote($language, '/') . '-([A-F0-9]{12})` \|/';
    $dispositionPattern = '/`(?:MIGRATE|REPLACE|RETAIN_TEMP|RETIRE_PROPOSED|RETIRE_VERIFIED|UNKNOWN_BLOCKED)`/';
    $statePattern = '/`(?:BASELINED|PLANNED_NOT_IMPLEMENTED|IMPLEMENTED_NOT_VERIFIED|VERIFIED|RETIRED_VERIFIED|INVALIDATED)`/';

    foreach ($reportLines as $lineNumber => $line) {
        if (!str_contains($line, '`' . $citation . '`')) {
            continue;
        }
        if (preg_match($identityPattern, $line, $identity) !== 1) {
            continue;
        }
        if (preg_match($dispositionPattern, $line) !== 1 || preg_match($statePattern, $line) !== 1) {
            continue;
        }
        if (!str_contains($line, '`AC-FUNC-' . $identity[1] . '`')) {
            continue;
        }
        return $lineNumber;
    }

    return null;
}

function registeredEvidenceRows(array $reportLines): array
{
    $rows = [];
    foreach ($reportLines as $line) {
        if (preg_match('/^\| `F-(?:PHP|JS)-([A-F0-9]{12})` \|/', $line, $identity) !== 1) {
            continue;
        }
        if (preg_match('/`AC-FUNC-([A-F0-9]{12})`/', $line, $acceptance) !== 1) {
            $acceptance[1] = 'MISSING';
        }
        $rows[] = ['id' => $identity[1], 'acceptance' => $acceptance[1]];
    }
    return $rows;
}

function discoverJavascriptFunctions(string $projectRoot): array
{
    $files = [];
    $viewRoot = $projectRoot . DIRECTORY_SEPARATOR . INLINE_JAVASCRIPT_ROOT;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($viewRoot, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    foreach (CUSTOM_JAVASCRIPT_FILES as $relativePath) {
        $absolutePath = $projectRoot . DIRECTORY_SEPARATOR . $relativePath;
        if (!is_file($absolutePath)) {
            fwrite(STDERR, "JavaScript source not found: {$relativePath}\n");
            exit(2);
        }
        $files[] = $absolutePath;
    }

    sort($files);
    $citations = [];
    foreach ($files as $path) {
        $contents = file_get_contents($path);
        if ($contents === false) {
            fwrite(STDERR, "Cannot read JavaScript source: {$path}\n");
            exit(2);
        }

        $relativePath = substr($path, strlen($projectRoot) + 1);
        preg_match_all(
            '/\bfunction\s*(?:[A-Za-z_$][A-Za-z0-9_$]*)?\s*\(|(?:\([^()\r\n]*\)|\b[A-Za-z_$][A-Za-z0-9_$]*)\s*=>/',
            $contents,
            $matches,
            PREG_OFFSET_CAPTURE
        );

        $ordinalByLine = [];
        foreach ($matches[0] as [, $offset]) {
            $line = substr_count($contents, "\n", 0, $offset) + 1;
            $ordinalByLine[$line] = ($ordinalByLine[$line] ?? 0) + 1;
            $citations[] = $relativePath . ':' . $line . '#' . $ordinalByLine[$line];
        }
    }

    return $citations;
}
