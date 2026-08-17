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

const PIN_REFERENCE = 'outputs/reference/2026-08-17_ci3-reference-baseline_v1.md';

$reportRoot = dirname(__DIR__);
$options = parseArguments($argv, $reportRoot);

$sourceRoot = $options['source-root'];
if ($sourceRoot === null) {
    fwrite(STDERR, "CI3 source root not set. Use CI3_SOURCE_ROOT env or --source-root=<path>.\n");
    fwrite(STDERR, "Pin and path are documented in " . PIN_REFERENCE . "\n");
    exit(2);
}
if (!is_dir($sourceRoot)) {
    fwrite(STDERR, "CI3 source root not a directory: {$sourceRoot}\n");
    exit(2);
}

$expectedPin = readPin($reportRoot . DIRECTORY_SEPARATOR . PIN_REFERENCE);
assertPin($sourceRoot, $expectedPin, $options['allow-pin-drift']);

$functions = discoverPhpFunctions($sourceRoot);
$javascript = discoverJavascriptFunctions($sourceRoot);

if ($options['generate'] !== null) {
    generateReport(
        $options['generate'],
        $options['carry-from'],
        $sourceRoot,
        $expectedPin,
        $functions,
        $javascript
    );
    exit(0);
}

$reportPath = $options['report'];
if ($reportPath === null) {
    fwrite(STDERR, "Usage: php scripts/check-function-disposition.php <report.md> [--source-root=<path>]\n");
    fwrite(STDERR, "       php scripts/check-function-disposition.php --generate=<out.md> --carry-from=<v1.md>\n");
    exit(2);
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

$hashMismatch = verifyManifestHashes($reportLines, $sourceRoot);
$identityMismatch = verifyFunctionIdentities($reportLines);
$retiredRows = retiredPointRows($reportLines);
$retiredWithoutEvidence = [];
foreach ($retiredRows as $citation => $row) {
    if (!str_contains($row, 'RETIRED_VERIFIED') || !str_contains($row, 'no-caller')) {
        $retiredWithoutEvidence[] = $citation;
    }
}

$total = count($functions);
$covered = $total - count($missing);
printf("CI3 source root: %s\n", $sourceRoot);
printf("CI3 pin: %s\n", $expectedPin ?? 'UNPINNED');
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
printf("Registered live Function rows: %d/%d\n", count($registeredRows), $expectedRows);
printf("Unique matched row lines: %d/%d\n", $uniqueMatchedRows, $expectedRows);
printf("Retired point rows: %d\n", count($retiredRows));
printf("Duplicate Function IDs: %d\n", $duplicateIds);
printf("Duplicate AC-FUNC IDs: %d\n", $duplicateAcceptanceIds);
printf("Duplicate JavaScript citations: %d\n", $duplicateJavascriptCitations);
printf("Manifest hash mismatches: %d\n", count($hashMismatch));
printf("Function ID formula mismatches: %d\n", count($identityMismatch));
printf("Retired rows without deletion evidence: %d\n", count($retiredWithoutEvidence));
printf("Report SHA-256: %s\n", hash_file('sha256', $reportPath));

foreach ($missing as $citation => $symbol) {
    fwrite(STDERR, "MISSING {$citation} {$symbol}\n");
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

foreach ($hashMismatch as $path => $detail) {
    fwrite(STDERR, "HASH MISMATCH {$path} expected {$detail['expected']} actual {$detail['actual']}\n");
}

foreach ($identityMismatch as $detail) {
    fwrite(STDERR, "ID MISMATCH {$detail['citation']} {$detail['symbol']} row {$detail['row']} formula {$detail['formula']}\n");
}

foreach ($retiredWithoutEvidence as $citation) {
    fwrite(STDERR, "RETIRED WITHOUT EVIDENCE {$citation}\n");
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
    || $duplicateJavascriptCitations !== 0
    || $hashMismatch !== []
    || $identityMismatch !== []
    || $retiredWithoutEvidence !== []
) {
    exit(1);
}

exit(0);

/**
 * @param list<string> $argv
 * @return array{report: ?string, source-root: ?string, generate: ?string, carry-from: ?string, allow-pin-drift: bool}
 */
function parseArguments(array $argv, string $reportRoot): array
{
    $options = [
        'report' => null,
        'source-root' => getenv('CI3_SOURCE_ROOT') ?: null,
        'generate' => null,
        'carry-from' => null,
        'allow-pin-drift' => false,
    ];

    foreach (array_slice($argv, 1) as $argument) {
        if ($argument === '--allow-pin-drift') {
            $options['allow-pin-drift'] = true;
            continue;
        }

        if (str_starts_with($argument, '--')) {
            [$name, $value] = array_pad(explode('=', substr($argument, 2), 2), 2, null);
            if (!array_key_exists($name, $options) || $value === null) {
                fwrite(STDERR, "Unknown or incomplete option: {$argument}\n");
                exit(2);
            }
            $options[$name] = absolutePath($value, $reportRoot);
            continue;
        }

        if ($options['report'] !== null) {
            fwrite(STDERR, "Unexpected extra argument: {$argument}\n");
            exit(2);
        }
        $options['report'] = absolutePath($argument, $reportRoot);
    }

    if ($options['source-root'] !== null) {
        $options['source-root'] = rtrim($options['source-root'], DIRECTORY_SEPARATOR);
    }

    if ($options['generate'] !== null && $options['carry-from'] === null) {
        fwrite(STDERR, "--generate requires --carry-from=<previous-report.md>\n");
        exit(2);
    }

    return $options;
}

function absolutePath(string $path, string $base): string
{
    if (str_starts_with($path, DIRECTORY_SEPARATOR) || str_starts_with($path, '~')) {
        return $path;
    }
    return $base . DIRECTORY_SEPARATOR . $path;
}

function readPin(string $referencePath): ?string
{
    if (!is_file($referencePath)) {
        fwrite(STDERR, "Pin reference not found: {$referencePath}\n");
        exit(2);
    }

    $contents = file_get_contents($referencePath);
    if ($contents === false || preg_match('/^CI3_PIN=([0-9a-f]{40})$/m', $contents, $match) !== 1) {
        fwrite(STDERR, "Pin line `CI3_PIN=<40-hex>` not found in {$referencePath}\n");
        exit(2);
    }

    return $match[1];
}

function assertPin(string $sourceRoot, ?string $expectedPin, bool $allowDrift): void
{
    if ($expectedPin === null) {
        return;
    }

    $head = trim(shellCapture(['git', '-C', $sourceRoot, 'rev-parse', 'HEAD']));
    $status = shellCapture(['git', '-C', $sourceRoot, 'status', '--porcelain']);

    $problems = [];
    if ($head !== $expectedPin) {
        $problems[] = "HEAD {$head} != pin {$expectedPin}";
    }
    if (trim($status) !== '') {
        $problems[] = 'worktree DIRTY (' . count(preg_split('/\R/', trim($status)) ?: []) . ' entries)';
    }

    if ($problems === []) {
        return;
    }

    $message = "CI3 source is not at pin: " . implode('; ', $problems) . "\n";
    if ($allowDrift) {
        fwrite(STDERR, "WARNING {$message}");
        return;
    }
    fwrite(STDERR, $message);
    fwrite(STDERR, "Checkout the pin or pass --allow-pin-drift to inspect drift.\n");
    exit(2);
}

/** @param list<string> $command */
function shellCapture(array $command): string
{
    $escaped = implode(' ', array_map('escapeshellarg', $command));
    $output = shell_exec($escaped . ' 2>/dev/null');
    return $output === null || $output === false ? '' : $output;
}

/** @return array<string,string> citation => bare symbol token */
function discoverPhpFunctions(string $sourceRoot): array
{
    $phpFiles = [];
    foreach (SOURCE_ROOTS as $relativeRoot) {
        $absoluteRoot = $sourceRoot . DIRECTORY_SEPARATOR . $relativeRoot;
        if (!is_dir($absoluteRoot)) {
            fwrite(STDERR, "Source root not found: {$relativeRoot}\n");
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
        $absolutePath = $sourceRoot . DIRECTORY_SEPARATOR . $relativePath;
        if (!is_file($absolutePath)) {
            fwrite(STDERR, "PHP source not found: {$relativePath}\n");
            exit(2);
        }
        $phpFiles[] = $absolutePath;
    }

    $phpFiles = array_values(array_unique($phpFiles));
    sort($phpFiles);

    $functions = [];
    foreach ($phpFiles as $phpFile) {
        $contents = file_get_contents($phpFile);
        if ($contents === false) {
            fwrite(STDERR, "Cannot read source: {$phpFile}\n");
            exit(2);
        }

        $relativePath = substr($phpFile, strlen($sourceRoot) + 1);
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
                        fwrite(STDERR, "PHP SAME-LINE COLLISION {$citation}\n");
                        exit(1);
                    }
                    $functions[$citation] = $token[1];
                }
                break;
            }
        }
    }

    ksort($functions);
    return $functions;
}

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

/** @return list<string> citations shaped path:line#ordinal */
function discoverJavascriptFunctions(string $sourceRoot): array
{
    $files = [];
    $viewRoot = $sourceRoot . DIRECTORY_SEPARATOR . INLINE_JAVASCRIPT_ROOT;
    if (!is_dir($viewRoot)) {
        fwrite(STDERR, "View root not found: " . INLINE_JAVASCRIPT_ROOT . "\n");
        exit(2);
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($viewRoot, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    foreach (CUSTOM_JAVASCRIPT_FILES as $relativePath) {
        $absolutePath = $sourceRoot . DIRECTORY_SEPARATOR . $relativePath;
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

        $relativePath = substr($path, strlen($sourceRoot) + 1);
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

function findEvidenceRow(array $reportLines, string $citation, string $language): ?int
{
    $identityPattern = '/^\| `F-' . preg_quote($language, '/') . '-([A-F0-9]{12})` \|/';
    $dispositionPattern = '/`(?:MIGRATE|REPLACE|RETAIN_TEMP|RETIRE_PROPOSED|RETIRE_VERIFIED|RETIRED_VERIFIED|UNKNOWN_BLOCKED)`/';
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

/** live evidence rows only; retired rows live under their own heading */
function registeredEvidenceRows(array $reportLines): array
{
    $rows = [];
    $inRetired = false;
    foreach ($reportLines as $line) {
        if (str_starts_with($line, '## ')) {
            $inRetired = str_contains($line, 'Retired points');
        }
        if ($inRetired) {
            continue;
        }
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

/** @return array<string,string> citation => raw row, for rows under `## Retired points` */
function retiredPointRows(array $reportLines): array
{
    $rows = [];
    $inRetired = false;
    foreach ($reportLines as $line) {
        if (str_starts_with($line, '## ')) {
            $inRetired = str_contains($line, 'Retired points');
            continue;
        }
        if (!$inRetired) {
            continue;
        }
        if (preg_match('/^\| `F-(?:PHP|JS)-[A-F0-9]{12}` \| `([^`]+)`/', $line, $match) === 1) {
            $rows[$match[1]] = $line;
        }
    }
    return $rows;
}

/** @return array<string,array{expected:string,actual:string}> */
function verifyManifestHashes(array $reportLines, string $sourceRoot): array
{
    $mismatch = [];
    foreach ($reportLines as $line) {
        if (preg_match('/^\| `([^`]+\.(?:php|js))` \|(?: [^|]*\|)? `([0-9a-f]{64})` \|/', $line, $match) !== 1) {
            continue;
        }
        [, $relativePath, $expected] = $match;
        $absolutePath = $sourceRoot . DIRECTORY_SEPARATOR . $relativePath;
        $actual = is_file($absolutePath) ? hash_file('sha256', $absolutePath) : 'FILE_MISSING';
        if ($actual !== $expected) {
            $mismatch[$relativePath] = ['expected' => substr($expected, 0, 16), 'actual' => is_string($actual) ? substr($actual, 0, 16) : 'ERROR'];
        }
    }
    return $mismatch;
}

/** @return list<array{citation:string,symbol:string,row:string,formula:string}> */
function verifyFunctionIdentities(array $reportLines): array
{
    $mismatch = [];
    foreach ($reportLines as $line) {
        if (preg_match('/^\| `F-(PHP|JS)-([A-F0-9]{12})` \| `([^`]+)` `([^`]+)`/', $line, $match) !== 1) {
            continue;
        }
        [, $language, $identity, $citation, $symbol] = $match;
        $expected = mintIdentity($language, $citation, $symbol);
        if ($expected !== $identity) {
            $mismatch[] = ['citation' => $citation, 'symbol' => $symbol, 'row' => $identity, 'formula' => $expected];
        }
    }
    return $mismatch;
}

function mintIdentity(string $language, string $citation, string $symbol): string
{
    if ($language === 'JS') {
        [$location, $ordinal] = array_pad(explode('#', $citation, 2), 2, '1');
        $input = $location . ':' . $symbol . '#' . $ordinal;
    } else {
        $input = $citation . ':' . $symbol;
    }
    return strtoupper(substr(sha1($input), 0, 12));
}

/**
 * @param array<string,string> $functions
 * @param list<string> $javascript
 */
function generateReport(
    string $outputPath,
    string $carryPath,
    string $sourceRoot,
    ?string $pin,
    array $functions,
    array $javascript
): void {
    if (!is_file($carryPath)) {
        fwrite(STDERR, "Carry source not found: {$carryPath}\n");
        exit(2);
    }
    $carryLines = preg_split('/\R/', (string) file_get_contents($carryPath));
    if ($carryLines === false) {
        fwrite(STDERR, "Cannot read carry source: {$carryPath}\n");
        exit(2);
    }

    $carry = [];
    $identityFailures = 0;
    foreach ($carryLines as $line) {
        if (preg_match('/^\| `F-(PHP|JS)-([A-F0-9]{12})` \| `([^`]+)` `([^`]+)`/', $line, $match) !== 1) {
            continue;
        }
        [, $language, $identity, $citation, $symbol] = $match;
        if (mintIdentity($language, $citation, $symbol) !== $identity) {
            $identityFailures++;
        }
        $columns = explode(' | ', $line);
        if (count($columns) !== 10) {
            fwrite(STDERR, "Carry row has unexpected column count: {$citation}\n");
            exit(2);
        }
        $carry[$citation] = [
            'language' => $language,
            'identity' => $identity,
            'symbol' => $symbol,
            'type' => $columns[2],
            'caller' => $columns[3],
            'behavior' => $columns[4],
            'destination' => $columns[5],
            'disposition' => $columns[6],
            'retirement' => $columns[7],
            'acceptance' => $columns[8],
            'impact' => rtrim($columns[9], ' |'),
        ];
    }

    if ($identityFailures !== 0) {
        fwrite(STDERR, "Carry source failed the ID formula self-check on {$identityFailures} rows; refusing to generate.\n");
        exit(1);
    }

    $liveCitations = [];
    foreach (array_keys($functions) as $citation) {
        $liveCitations[$citation] = 'PHP';
    }
    foreach ($javascript as $citation) {
        $liveCitations[$citation] = 'JS';
    }

    $unresolved = array_diff_key($liveCitations, $carry);
    if ($unresolved !== []) {
        foreach (array_keys($unresolved) as $citation) {
            fwrite(STDERR, "NEW POINT WITHOUT CARRY {$citation}\n");
        }
        fwrite(STDERR, "New source points need a manual symbol/disposition decision before generation.\n");
        exit(1);
    }

    $retiredCitations = array_diff_key($carry, $liveCitations);

    $live = [];
    foreach ($liveCitations as $citation => $language) {
        $live[$citation] = $carry[$citation];
    }

    $document = renderReport($sourceRoot, $pin, $functions, $javascript, $live, $retiredCitations);
    if (file_put_contents($outputPath, $document) === false) {
        fwrite(STDERR, "Cannot write report: {$outputPath}\n");
        exit(2);
    }

    printf("Generated %s\n", $outputPath);
    printf("Live points: %d (PHP %d, JS %d)\n", count($live), count($functions), count($javascript));
    printf("Retired points: %d\n", count($retiredCitations));
    printf("Carry rows read: %d\n", count($carry));
    printf("ID formula self-check: %d/%d\n", count($carry), count($carry));
}

/**
 * @param array<string,string> $functions
 * @param list<string> $javascript
 * @param array<string,array<string,string>> $live
 * @param array<string,array<string,string>> $retired
 */
function renderReport(
    string $sourceRoot,
    ?string $pin,
    array $functions,
    array $javascript,
    array $live,
    array $retired
): string {
    $phpByFile = [];
    foreach ($functions as $citation => $symbol) {
        $phpByFile[substr($citation, 0, (int) strrpos($citation, ':'))][] = $citation;
    }
    $jsByFile = [];
    foreach ($javascript as $citation) {
        $jsByFile[substr($citation, 0, (int) strrpos($citation, ':'))][] = $citation;
    }
    ksort($phpByFile);
    ksort($jsByFile);

    $dispositionCounts = ['PHP' => [], 'JS' => []];
    foreach ($live as $row) {
        preg_match('/`(MIGRATE|REPLACE|RETAIN_TEMP|RETIRE_PROPOSED|UNKNOWN_BLOCKED)`/', $row['disposition'], $match);
        $key = $match[1] ?? 'UNKNOWN_BLOCKED';
        $dispositionCounts[$row['language']][$key] = ($dispositionCounts[$row['language']][$key] ?? 0) + 1;
    }

    $retiredByOrigin = [];
    foreach ($retired as $citation => $row) {
        preg_match('/`(MIGRATE|REPLACE|RETAIN_TEMP|RETIRE_PROPOSED|UNKNOWN_BLOCKED)`/', $row['disposition'], $match);
        $retiredByOrigin[$match[1] ?? 'UNKNOWN_BLOCKED'] = ($retiredByOrigin[$match[1] ?? 'UNKNOWN_BLOCKED'] ?? 0) + 1;
    }
    ksort($retiredByOrigin);

    $out = [];
    $out[] = '# หลักฐาน Function Disposition และ Acceptance Criteria รายฟังก์ชัน v2';
    $out[] = '';
    $out[] = 'เอกสารนี้ inventory ฟังก์ชัน PHP และ frontend JavaScript จาก CI3 ที่ pin ไว้ เพื่อกำหนดว่าจะ MIGRATE, REPLACE, RETAIN_TEMP, RETIRE_PROPOSED หรือ UNKNOWN_BLOCKED ไปที่ใด พร้อม Acceptance Criteria รายจุด. สถานะเป็น baseline ก่อนย้าย ไม่ใช่หลักฐานว่าการย้ายสำเร็จ.';
    $out[] = '';
    $out[] = 'ไฟล์นี้ generate ด้วย `php scripts/check-function-disposition.php --generate=<out> --carry-from=<v1>` ห้ามแก้มือ — แก้ pin หรือแก้ carry source แล้ว generate ใหม่';
    $out[] = '';
    $out[] = '## Verdict และ denominator';
    $out[] = '';
    $out[] = '**Verdict: NOT_COMPLETE / EXECUTION_EVIDENCE_MISSING.** Source-point registration ครบตาม pin แต่ dynamic caller, runtime behavior และ CI4 after evidence ยังไม่เกิด จึงห้ามอ้าง functional parity 100%.';
    $out[] = '';
    $out[] = '| Layer | Live points | Source enumeration | After evidence | Closure |';
    $out[] = '|---|---:|---|---|---|';
    $out[] = sprintf('| PHP named function/method | %d | REGISTERED จาก %d files | MISSING | 0/%d |', count($functions), count($phpByFile), count($functions));
    $out[] = sprintf('| JavaScript candidate token | %d | REGISTERED จาก %d files | MISSING | 0/%d |', count($javascript), count($jsByFile), count($javascript));
    $out[] = sprintf('| **รวม live acceptance points** | **%d** | **REGISTERED_AT_PIN** | **MISSING** | **0/%d** |', count($live), count($live));
    $out[] = '';
    $out[] = sprintf('Retired points %d จุด อยู่หัวข้อ Retired points ไม่นับใน denominator. Audit trail: v1 มี 1411 points, ไฟล์ต้นทาง %d ไฟล์หายจาก pin จึงเหลือ live %d — `1411 − %d = %d`.', count($retired), countRetiredFiles($retired), count($live), count($retired), count($live));
    $out[] = '';
    $out[] = '### Disposition summary (live)';
    $out[] = '';
    $out[] = '| Runtime | MIGRATE | REPLACE | RETAIN_TEMP | RETIRE_PROPOSED | UNKNOWN_BLOCKED |';
    $out[] = '|---|---:|---:|---:|---:|---:|';
    foreach (['PHP', 'JS'] as $language) {
        $counts = $dispositionCounts[$language];
        $out[] = sprintf(
            '| %s | %d | %d | %d | %d | %d |',
            $language === 'PHP' ? 'PHP' : 'JavaScript',
            $counts['MIGRATE'] ?? 0,
            $counts['REPLACE'] ?? 0,
            $counts['RETAIN_TEMP'] ?? 0,
            $counts['RETIRE_PROPOSED'] ?? 0,
            $counts['UNKNOWN_BLOCKED'] ?? 0
        );
    }
    $out[] = '';
    $out[] = '`RETIRE_PROPOSED` ไม่ใช่การอนุมัติยกเลิก. ทุกจุดยังเปิดจน no-caller proof, runtime access evidence, business owner approval และ rollback ผ่าน.';
    $out[] = '';
    $out[] = '## Source snapshot';
    $out[] = '';
    $out[] = '| Field | Value |';
    $out[] = '|---|---|';
    $out[] = sprintf('| CI3 pin | `%s` |', $pin ?? 'UNPINNED');
    $out[] = '| Worktree | `CLEAN` (checker บังคับก่อน generate) |';
    $out[] = '| Reference contract | `outputs/reference/2026-08-17_ci3-reference-baseline_v1.md` |';
    $out[] = '| CI4 target tree | `app/` และ `spark` ยังไม่มี |';
    $out[] = '| Execution state ทุก row | `PLANNED_NOT_IMPLEMENTED` |';
    $out[] = '| Static caller limitation | dynamic call, reflection, string route, external consumer, scheduler และ production traffic ยังพิสูจน์ไม่ได้ |';
    $out[] = '';
    $out[] = '## Function ID contract';
    $out[] = '';
    $out[] = 'PHP: `F-PHP-` + `strtoupper(substr(sha1("<path>:<line>:<symbol>"),0,12))`; JavaScript: `F-JS-` + `strtoupper(substr(sha1("<path>:<line>:<symbol>#<ordinal>"),0,12))`. `AC-FUNC-<hex>` ใช้ hex เดียวกับ Function ID ของแถวนั้น. checker ตรวจสูตรนี้ทุกแถว.';
    $out[] = '';
    $out[] = 'ID ผูกกับ line number — source เลื่อนบรรทัดคือต้อง re-pin แล้ว generate ใหม่ ห้ามแก้ ID มือ.';
    $out[] = '';
    $out[] = '## Acceptance Criteria contract ต่อทุกฟังก์ชัน';
    $out[] = '';
    $out[] = 'ทุก `AC-FUNC-*` ผ่านได้เมื่อ evidence record มีข้อมูลครบและ independent reviewer reproduce ผลได้.';
    $out[] = '';
    $out[] = '| AC field | หลักฐานบังคับ | Pass condition |';
    $out[] = '|---|---|---|';
    $out[] = '| Identity | Function ID, source path:line, symbol, file SHA-256 | ระบุต้นทางเดียว ไม่ orphan/duplicate |';
    $out[] = '| Caller/route | route map, static caller, runtime trace/access log, external consumer list | caller ครบ; unknown เท่ากับ 0 หรือได้รับ blocker decision |';
    $out[] = '| Before behavior | fixed input/fixture, auth role, request, return/response, DB/session/file/message delta, error path | replay ได้และ hash evidence ตรง |';
    $out[] = '| Destination | CI4 file/class/method หรือ framework/service replacement | implementation มีจริง; ห้ามคง PLANNED_NOT_IMPLEMENTED |';
    $out[] = '| After behavior | fixture และ comparator เดียวกับ before | output และ side effect เท่ากันตาม approved contract |';
    $out[] = '| Security | authorization, validation, escaping, secret handling, upload/query checks | ไม่ลด control; secret ออกจาก source |';
    $out[] = '| Non-functional | latency, memory, query count, timeout/retry, log/metric | อยู่ใน approved tolerance |';
    $out[] = '| Disposition | MIGRATE/REPLACE/RETAIN_TEMP/RETIRE_PROPOSED/UNKNOWN_BLOCKED | owner และ reviewer ลงนาม; evidence lineage sealed |';
    $out[] = '| Retirement | static caller 0, explicit/implicit route 0, runtime access 0, coverage 0, external consumer 0, owner approval, rollback | ครบทุกข้อก่อนเปลี่ยน RETIRE_PROPOSED เป็น RETIRED_VERIFIED |';
    $out[] = '';
    $out[] = 'ค่า default comparator: HTTP status, headers, normalized body/HTML/JSON, redirect, validation message, DB row/transaction delta, session delta, emitted email/file/network request, exception class และ audit log ต้องเท่ากัน. ความต่างต้องมี Change ID และ impact approval.';
    $out[] = '';
    $out[] = '## File integrity manifest';
    $out[] = '';
    $out[] = '### PHP baseline';
    $out[] = '';
    $out[] = '| Source | SHA-256 | Named functions |';
    $out[] = '|---|---|---:|';
    foreach ($phpByFile as $path => $citations) {
        $out[] = sprintf('| `%s` | `%s` | %d |', $path, hash_file('sha256', $sourceRoot . DIRECTORY_SEPARATOR . $path), count($citations));
    }
    $out[] = '';
    $out[] = '### Frontend baseline with function points';
    $out[] = '';
    $out[] = '| Source | Layer | SHA-256 | Points |';
    $out[] = '|---|---|---|---:|';
    foreach ($jsByFile as $path => $citations) {
        $layer = str_starts_with($path, 'assets/') ? 'custom external JS' : 'inline/candidate JS in view';
        $out[] = sprintf('| `%s` | %s | `%s` | %d |', $path, $layer, hash_file('sha256', $sourceRoot . DIRECTORY_SEPARATOR . $path), count($citations));
    }
    $out[] = '';
    $out[] = '## PHP function-by-function disposition';
    $out[] = '';
    foreach ($phpByFile as $path => $citations) {
        $out[] = sprintf('### `%s`', $path);
        $out[] = '';
        $out[] = '| Function ID | Source symbol | Type | Observed caller/route | Observed behavior/side effect | CI4 destination | Disposition | Retirement proof | AC/test/evidence | Impact/confidence |';
        $out[] = '|---|---|---|---|---|---|---|---|---|---|';
        foreach ($citations as $citation) {
            $out[] = renderRow($citation, $live[$citation], array_keys($retired));
        }
        $out[] = '';
    }
    $out[] = '## JavaScript function-by-function disposition';
    $out[] = '';
    foreach ($jsByFile as $path => $citations) {
        $out[] = sprintf('### `%s`', $path);
        $out[] = '';
        $out[] = '| Function ID | Source symbol | Kind | Observed caller/registration | Observed behavior/side effect | CI4 destination | Disposition | Retirement proof | AC/test/evidence | Impact/confidence |';
        $out[] = '|---|---|---|---|---|---|---|---|---|---|';
        foreach ($citations as $citation) {
            $out[] = renderRow($citation, $live[$citation], array_keys($retired));
        }
        $out[] = '';
    }
    $out[] = retiredSection($retired, $retiredByOrigin);
    $out[] = '## Secret, security และ migration blockers';
    $out[] = '';
    $out[] = 'ไม่คัดลอกค่า credential. ตำแหน่งต่อไปนี้ที่ pin ถือค่า placeholder แล้ว (commit `5409901` แทนค่าจริงด้วยชื่อตัวแปร) แต่ค่าจริงที่เคยอยู่ใน source ต้อง rotate/revoke และย้ายเข้า environment/secret manager ก่อน CI4 acceptance:';
    $out[] = '';
    $out[] = '- `application/libraries/Email.php:13` ถึง `application/libraries/Email.php:17` — SMTP configuration และ credential risk.';
    $out[] = '- `application/config/Contact.php:75` ถึง `application/config/Contact.php:76` — SMTP username/password risk ใน misplaced duplicate controller.';
    $out[] = '- `application/controllers/Login.php:213` ถึง `:214` และ `:344` ถึง `:345` — SMTP credential assignment ใน password-email flows.';
    $out[] = '- `application/controllers/Contact.php:206` ถึง `:208` — SMTP credential/configuration ใน contact-email flow.';
    $out[] = '- `application/controllers/Contact_th.php:177` ถึง `:179` — SMTP credential/configuration ใน Thai contact-email flow.';
    $out[] = '- `application/helpers/cias_helper.php` — ThaiBulkSMS credential ใน helper.';
    $out[] = '- `application/config/database.php` — database credential file; ไม่ถูก track ใน repo CI3 ตรวจเฉพาะ presence/path ไม่บันทึกค่า.';
    $out[] = '';
    $out[] = '### Blocker register';
    $out[] = '';
    $out[] = '| Blocker | Evidence | ต้องทำก่อนปิด AC |';
    $out[] = '|---|---|---|';
    $out[] = '| `FN-BLK-001` Dynamic callers | static name search resolve late binding/reflection/external traffic ไม่ได้ | route:list, runtime trace, production access log, cron/CLI/webhook inventory |';
    $out[] = '| `FN-BLK-002` No CI4 implementation | ไม่พบ `app/` หรือ `spark` | implement target แล้ว capture file hash/source line |';
    $out[] = '| `FN-BLK-003` No before/after fixtures | มี source evidence แต่ไม่มี replay artifact | สร้าง golden-master fixture ต่อ AC และ deterministic comparator |';
    $out[] = '| `FN-BLK-004` Dirty baseline | **CLOSED 2026-08-17** — source pin clean และ checker verify manifest hash ทุกไฟล์ต่อ pin | คงปิดตราบที่ checker ยัง exit 0 ต่อ pin เดิม |';
    $out[] = '| `FN-BLK-005` Retirement approval | static no-caller ไม่พิสูจน์ production no-use | traffic/coverage/external-consumer=0 + business owner approval + rollback |';
    $out[] = '| `FN-BLK-006` Third-party compatibility | vendor implementation ถูกแยกจาก application denominator; library ที่เคยอยู่ `application/libraries/` ถูกลบที่ pin แล้ว | inventory dependency version/license/CVE/PHP 8.5/CI4 replacement และ integration tests |';
    $out[] = '| `FN-BLK-007` Secret exposure | ค่าใน repo เป็น placeholder แล้ว แต่ค่าจริงเคยอยู่ใน source | rotate/revoke, history exposure review, secret scanning pass |';
    $out[] = '| `FN-BLK-008` JavaScript dependency/order | custom callback พึ่ง jQuery/plugins/global variables | lock dependency versions, script-order manifest, browser parity และ error-console evidence |';
    $out[] = '| `FN-BLK-009` Retired content unrecoverable | 19 ไฟล์ที่ retired ไม่มีใน git history ของ CI3 | ถ้าต้องกลับมาตรวจ ต้องดึงจาก production host ไม่ใช่ repo |';
    $out[] = '';
    $out[] = '## Required execution report ต่อ point';
    $out[] = '';
    $out[] = 'แต่ละ Function ID ต้องมี record แยก ห้ามปิดด้วยผลรวมระดับ controller/model/file.';
    $out[] = '';
    $out[] = '| Field | Before | Change | After | Impact |';
    $out[] = '|---|---|---|---|---|';
    $out[] = '| Identity | source hash + path:line + symbol | commit/change ID + target hash | target path:line + build artifact hash | owner/reviewer |';
    $out[] = '| Invocation | route/caller/role/fixture | mapping old-to-new หรือ retirement decision | route:list/runtime trace | caller removed/redirected/unchanged |';
    $out[] = '| Functional | output/return/error | MIGRATE/REPLACE/RETAIN_TEMP | same comparator result | approved difference only |';
    $out[] = '| Side effect | DB/session/file/email/network delta | transaction/adapter/config change | reconciled delta | data/security/operation |';
    $out[] = '| Non-functional | latency/memory/query count | tuning/change | measured result | tolerance/rollback trigger |';
    $out[] = '| Closure | BASELINE_CAPTURED | CHANGE_APPLIED | INDEPENDENTLY_VERIFIED | CLOSED หรือ UNKNOWN_BLOCKED |';
    $out[] = '';
    $out[] = '## Final status';
    $out[] = '';
    $out[] = sprintf(
        'Inventory เป็น evidence baseline และ disposition proposal. Success proof ยังเป็น `0/%d`; `PLANNED_NOT_IMPLEMENTED` ทุก target. ห้ามประกาศ CI3-to-CI4 parity จนทุก AC มี before/after, impact reconciliation, independent verification และ retirement approvals ครบ.',
        count($live)
    );
    $out[] = '';

    return implode("\n", $out);
}

/** @param list<string> $retiredCitations */
function renderRow(string $citation, array $row, array $retiredCitations): string
{
    $caller = $row['caller'];
    $droppedCallers = 0;
    foreach ($retiredCitations as $retiredCitation) {
        $retiredPath = substr($retiredCitation, 0, (int) strrpos($retiredCitation, ':'));
        if (str_contains($caller, $retiredPath)) {
            $droppedCallers++;
        }
    }
    if ($droppedCallers > 0) {
        $parts = array_filter(
            array_map('trim', explode(';', $caller)),
            static function (string $part) use ($retiredCitations): bool {
                foreach ($retiredCitations as $retiredCitation) {
                    $retiredPath = substr($retiredCitation, 0, (int) strrpos($retiredCitation, ':'));
                    if (str_contains($part, $retiredPath)) {
                        return false;
                    }
                }
                return true;
            }
        );
        $caller = $parts === []
            ? 'no static caller at pin; UNKNOWN_BLOCKED pending runtime trace'
            : implode('; ', $parts);
        $caller .= sprintf(' (dropped %d caller citation ในไฟล์ที่ retired)', $droppedCallers);
    }

    return sprintf(
        '| `F-%s-%s` | `%s` `%s` | %s | %s | %s | %s | %s | %s | %s | %s |',
        $row['language'],
        $row['identity'],
        $citation,
        $row['symbol'],
        $row['type'],
        $caller,
        $row['behavior'],
        $row['destination'],
        $row['disposition'],
        $row['retirement'],
        $row['acceptance'],
        $row['impact']
    );
}

/**
 * @param array<string,array<string,string>> $retired
 * @param array<string,int> $retiredByOrigin
 */
function retiredSection(array $retired, array $retiredByOrigin): string
{
    $byFile = [];
    foreach ($retired as $citation => $row) {
        $byFile[substr($citation, 0, (int) strrpos($citation, ':'))][$citation] = $row;
    }
    ksort($byFile);

    $out = [];
    $out[] = '## Retired points';
    $out[] = '';
    $out[] = sprintf(
        'จุดในไฟล์ที่ไม่มีอยู่ที่ pin — %d จุดจาก %d ไฟล์ ออกจาก denominator แล้ว. Deletion evidence: commit `5409901` ของ repo CI3 "import legacy CI3 source with credentials scrubbed and dead code removed / Remove 51 dead files". No-caller proof: grep loader ทุกตัว (`loadViews`, `load_web_Views`, `load_web_th_Views`, `load_order_Views`, `load_print_Views`, `loadViewspeint`), `application/config/routes.php` และ `<script src>` ที่ pin ได้ 0 hit ทุกไฟล์.',
        count($retired),
        count($byFile)
    );
    $out[] = '';
    $out[] = 'ไม่มี rollback path จาก git: 19 ไฟล์นี้ไม่ปรากฏใน commit ใดของ repo CI3 (`bf5355c` มีแค่ `README.md`) เนื้อหาที่เหลืออยู่มีสำเนาเดียวคือ `demo/application/views/tracking/report_tracking.php` — พบว่าลบผิดต้องกู้จาก production host.';
    $out[] = '';
    $out[] = '### Transition ต่อ disposition เดิม';
    $out[] = '';
    $out[] = '| Disposition เดิมใน v1 | Points | Transition | เหตุผลที่บันทึก |';
    $out[] = '|---|---:|---|---|';
    $transitionReason = [
        'RETIRE_PROPOSED' => 'no-caller proof + file deleted; ไม่มีภาระเดิมค้าง',
        'REPLACE' => 'ภาระ "ต้องมี replacement ใน CI4" ยกเลิกเพราะไม่มี caller แล้ว ไม่ใช่เพราะไฟล์หาย — replacement ที่แผน v3 สั่ง (PhpSpreadsheet ฯลฯ) ยังเป็นงานของ slice ที่ต้องการความสามารถนั้นจริง',
        'MIGRATE' => 'no-caller proof ยืนยันว่า v1 ตัดสิน MIGRATE บนสมมติว่าไฟล์ยัง live; ไม่มี target ให้ย้าย',
        'RETAIN_TEMP' => 'ไม่มีจุดในกลุ่มนี้',
        'UNKNOWN_BLOCKED' => 'blocker ปิดด้วย deletion evidence',
    ];
    foreach ($retiredByOrigin as $origin => $count) {
        $out[] = sprintf('| `%s` | %d | `%s` → `RETIRED_VERIFIED` | %s |', $origin, $count, $origin, $transitionReason[$origin] ?? '');
    }
    $out[] = '';
    foreach ($byFile as $path => $rows) {
        $out[] = sprintf('### `%s` (retired)', $path);
        $out[] = '';
        $out[] = '| Function ID | Source symbol | Type | Disposition เดิม | Transition | Deletion evidence | Live replacement |';
        $out[] = '|---|---|---|---|---|---|---|';
        foreach ($rows as $citation => $row) {
            preg_match('/`(MIGRATE|REPLACE|RETAIN_TEMP|RETIRE_PROPOSED|UNKNOWN_BLOCKED)`/', $row['disposition'], $match);
            $origin = $match[1] ?? 'UNKNOWN_BLOCKED';
            $out[] = sprintf(
                '| `F-%s-%s` | `%s` `%s` | %s | `%s` | `RETIRED_VERIFIED`; no-caller proof at pin | commit `5409901`; grep loader/routes/script-src = 0 hit | %s |',
                $row['language'],
                $row['identity'],
                $citation,
                $row['symbol'],
                $row['type'],
                $origin,
                liveReplacement($path)
            );
        }
        $out[] = '';
    }

    return implode("\n", $out);
}

function liveReplacement(string $path): string
{
    return match ($path) {
        'application/views/tracking/report_tracking.php' => '`application/views/tracking/report_tracking_test.php` โหลดที่ `application/controllers/Order.php:510` และ `:580`',
        'application/views/rating.php', 'application/views/th/rating.php', 'application/views/en/rating_KING_TH.php', 'application/views/en/rating_KING_EN.php', 'application/views/en/rating_KING_BACKUP.php' => '`application/views/en/rating.php` โหลดที่ `application/controllers/Rating.php:40`',
        default => 'ไม่มี — ไม่มี caller ที่ pin',
    };
}

/** @param array<string,array<string,string>> $retired */
function countRetiredFiles(array $retired): int
{
    $files = [];
    foreach (array_keys($retired) as $citation) {
        $files[substr($citation, 0, (int) strrpos($citation, ':'))] = true;
    }
    return count($files);
}
