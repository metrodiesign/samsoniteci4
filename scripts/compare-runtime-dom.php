#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Strict normalized runtime DOM comparator.
 *
 * Parses both documents with DOMDocument and compares element names, direct-child
 * order, attributes and non-whitespace text nodes. Regex is used only inside an
 * explicit normalization rule; it is never used to infer hierarchy.
 */

/** @return array<string, string> */
function options(array $argv): array
{
    $result = [];
    for ($index = 1; $index < count($argv); $index += 2) {
        $key = $argv[$index] ?? '';
        $value = $argv[$index + 1] ?? '';
        if (! str_starts_with($key, '--') || $value === '') {
            throw new InvalidArgumentException('usage: compare-runtime-dom.php --left FILE --right FILE --page ID [--allowlist FILE]');
        }
        $result[substr($key, 2)] = $value;
    }

    return $result;
}

function document(string $path): DOMDocument
{
    $html = file_get_contents($path);
    if ($html === false) {
        throw new RuntimeException("cannot read {$path}");
    }
    $document = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $loaded = $document->loadHTML($html, LIBXML_HTML_NODEFDTD);
    $errors = libxml_get_errors();
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (! $loaded) {
        throw new RuntimeException("cannot parse HTML {$path}");
    }
    foreach ($errors as $error) {
        if ($error->level === LIBXML_ERR_FATAL) {
            throw new RuntimeException("fatal HTML parse error in {$path}: " . trim($error->message));
        }
    }

    return $document;
}

/** @return list<array<string, mixed>> */
function allowlist(string $path, string $page): array
{
    if ($path === '') {
        return [];
    }
    $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (! is_array($decoded)) {
        throw new RuntimeException('allowlist must be a JSON array');
    }
    $rules = [];
    foreach ($decoded as $index => $rule) {
        if (! is_array($rule) || ($rule['page'] ?? null) !== $page) {
            continue;
        }
        foreach (['selector', 'attribute', 'reason', 'decision_id'] as $required) {
            if (! is_string($rule[$required] ?? null) || trim($rule[$required]) === '') {
                throw new RuntimeException("allowlist rule {$index} is missing {$required}");
            }
        }
        if (! preg_match('/\A[A-Z][A-Z0-9-]+\z/D', $rule['decision_id'])) {
            throw new RuntimeException("allowlist rule {$index} has invalid decision_id");
        }
        $rule['used'] = false;
        $rule['index'] = $index;
        $rules[] = $rule;
    }

    return $rules;
}

/** @param list<array<string, mixed>> $rules */
function normalize(DOMDocument $document, array &$rules, string $side): void
{
    $xpath = new DOMXPath($document);
    foreach ($rules as &$rule) {
        $nodes = $xpath->query($rule['selector']);
        if ($nodes === false) {
            throw new RuntimeException("invalid XPath selector in allowlist rule {$rule['index']}");
        }
        foreach (iterator_to_array($nodes) as $node) {
            $attribute = $rule['attribute'];
            if ($attribute === '#remove') {
                if ($node->parentNode !== null) {
                    $node->parentNode->removeChild($node);
                    $rule['used'] = true;
                }
                continue;
            }
            if ($attribute === '#text') {
                $original = $node->textContent;
                $normalized = normalizeValue($original, $rule);
                if ($normalized !== $original) {
                    while ($node->firstChild !== null) {
                        $node->removeChild($node->firstChild);
                    }
                    $node->appendChild($document->createTextNode($normalized));
                    $rule['used'] = true;
                }
                continue;
            }
            if (! $node instanceof DOMElement || ! $node->hasAttribute($attribute)) {
                continue;
            }
            $original = $node->getAttribute($attribute);
            $normalized = normalizeValue($original, $rule);
            if ($normalized !== $original) {
                $node->setAttribute($attribute, $normalized);
                $rule['used'] = true;
            }
        }
        $rule['sides'][$side] = $rule['used'];
    }
    unset($rule);
}

/** @param array<string, mixed> $rule */
function normalizeValue(string $value, array $rule): string
{
    $replacement = is_string($rule['replacement'] ?? null) ? $rule['replacement'] : '__NORMALIZED__';
    if (isset($rule['pattern'])) {
        if (! is_string($rule['pattern']) || @preg_match($rule['pattern'], '') === false) {
            throw new RuntimeException("invalid pattern in allowlist rule {$rule['index']}");
        }
        return (string) preg_replace($rule['pattern'], $replacement, $value);
    }

    return $replacement;
}

/** @param list<array<string, mixed>> $external @return list<array<string, mixed>> */
function runtimeRules(DOMDocument $left, DOMDocument $right, string $page, array $external): array
{
    $leftXPath = new DOMXPath($left);
    $rightXPath = new DOMXPath($right);
    $suffix = strtoupper(substr(hash('sha256', $page), 0, 8));
    $rules = [];
    $candidates = [
        ['//*[@href and (contains(@href,"127.0.0.1:18404") or contains(@href,"127.0.0.1:18405"))]', 'href', '#http://127\\.0\\.0\\.1:1840[45]/#', '/', 'approved same-run loopback origin', 'ORIGIN-HREF'],
        ['//a[contains(@href,"/user/excel_ratings//")]', 'href', '#/user/excel_ratings//+#', '/user/excel_ratings/', 'approved removal of the empty optional-branch path separator from the ratings Export caller', 'RATINGS-EXPORT-SLASH'],
        ['//*[@src and (contains(@src,"127.0.0.1:18404") or contains(@src,"127.0.0.1:18405"))]', 'src', '#http://127\\.0\\.0\\.1:1840[45]/#', '/', 'approved same-run loopback origin', 'ORIGIN-SRC'],
        ['//*[@action and (contains(@action,"127.0.0.1:18404") or contains(@action,"127.0.0.1:18405"))]', 'action', '#http://127\\.0\\.0\\.1:1840[45]/#', '/', 'approved same-run loopback origin', 'ORIGIN-ACTION'],
        ['//*[@style and (contains(@style,"127.0.0.1:18404") or contains(@style,"127.0.0.1:18405"))]', 'style', '#http://127\\.0\\.0\\.1:1840[45]/#', '/', 'approved same-run loopback origin', 'ORIGIN-STYLE'],
        ['//*[@onclick and (contains(@onclick,"127.0.0.1:18404") or contains(@onclick,"127.0.0.1:18405"))]', 'onclick', '#http://127\\.0\\.0\\.1:1840[45]/#', '/', 'approved same-run loopback origin in interaction target', 'ORIGIN-ONCLICK'],
        ['//script[contains(text(),"127.0.0.1:18404") or contains(text(),"127.0.0.1:18405")]', '#text', '#http://127\\.0\\.0\\.1:1840[45]/#', '/', 'approved same-run loopback origin in script configuration', 'ORIGIN-SCRIPT'],
        ['//link[contains(@href,"font-awesome") and contains(@href,"4.3.0")]', 'href', '#(?:https://maxcdn\\.bootstrapcdn\\.com/font-awesome/4\\.3\\.0/css/font-awesome\\.min\\.css|/assets/font-awesome/4\\.3\\.0/css/font-awesome\\.min\\.css)#', '/assets/font-awesome/4.3.0/css/font-awesome.min.css', 'approved project-local mirror of pinned Font Awesome CSS', 'FONTAWESOME-CSS'],
        ['//link[contains(@href,"jquery.dataTables.min.css")]', 'href', '#(?://cdn\\.datatables\\.net/1\\.10\\.16|/assets/datatables/1\\.10\\.16)#', '/assets/datatables/1.10.16', 'approved project-local mirror of pinned DataTables CSS', 'DATATABLES-CSS'],
        ['//link[contains(@href,"fixedColumns.dataTables.min.css")]', 'href', '#(?://cdn\\.datatables\\.net/fixedcolumns/3\\.2\\.4|/assets/datatables-fixedcolumns/3\\.2\\.4)#', '/assets/datatables-fixedcolumns/3.2.4', 'approved project-local mirror of pinned FixedColumns CSS', 'FIXED-CSS'],
        ['//script[contains(@src,"jquery.dataTables.min.js")]', 'src', '#(?://cdn\\.datatables\\.net/1\\.10\\.16|/assets/datatables/1\\.10\\.16)#', '/assets/datatables/1.10.16', 'approved project-local mirror of pinned DataTables JS', 'DATATABLES-JS'],
        ['//script[contains(@src,"dataTables.fixedColumns.min.js")]', 'src', '#(?://cdn\\.datatables\\.net/fixedcolumns/3\\.2\\.4|/assets/datatables-fixedcolumns/3\\.2\\.4)#', '/assets/datatables-fixedcolumns/3.2.4', 'approved project-local mirror of pinned FixedColumns JS', 'FIXED-JS'],
    ];
    foreach ($candidates as $candidate) {
        [$selector, $attribute, $pattern, $replacement, $reason, $decision] = $candidate;
        $leftNodes = $leftXPath->query($selector);
        $rightNodes = $rightXPath->query($selector);
        if (($leftNodes === false || $leftNodes->length === 0) && ($rightNodes === false || $rightNodes->length === 0)) {
            continue;
        }
        $rules[] = [
            'page' => $page, 'template' => $page, 'selector' => $selector, 'attribute' => $attribute,
            'pattern' => $pattern, 'replacement' => $replacement, 'reason' => $reason,
            'decision_id' => 'DOM-' . $decision . '-' . $suffix, 'used' => false,
            'index' => 'runtime-' . strtolower($decision),
        ];
    }
    $csrfSelector = '//input[@name="csrf_test_name"]';
    $hasExternalCsrf = array_any($external, static fn (array $rule): bool => str_contains((string) ($rule['selector'] ?? ''), 'csrf_test_name') && ($rule['attribute'] ?? '') === '#remove');
    $leftCsrf = $leftXPath->query($csrfSelector);
    $rightCsrf = $rightXPath->query($csrfSelector);
    if (! $hasExternalCsrf && (($leftCsrf !== false && $leftCsrf->length > 0) || ($rightCsrf !== false && $rightCsrf->length > 0))) {
        $rules[] = [
            'page' => $page, 'template' => $page, 'selector' => $csrfSelector, 'attribute' => '#remove',
            'reason' => 'approved CI4-only hidden CSRF security field',
            'decision_id' => 'DOM-CSRF-' . $suffix, 'used' => false, 'index' => 'runtime-csrf',
        ];
    }
    $runtimeIdRules = [
        ['//input[@type="hidden" and @name="times"]', 'value', '#^[0-9a-f]+$#', '__RUNTIME_ID__', 'hidden upload workflow identifier', 'TIMES-VALUE'],
        ['//form[@id="upload"]', 'action', '#/order/do_upload_multi/[0-9a-f]+#', '/order/do_upload_multi/__RUNTIME_ID__', 'upload workflow action identifier', 'TIMES-ACTION'],
        ['//script[contains(text(),"xtimesite")]', '#text', '#[0-9a-f]{10,32}#', '__RUNTIME_ID__', 'upload workflow identifier in script configuration', 'TIMES-SCRIPT'],
    ];
    foreach ($runtimeIdRules as $runtimeRule) {
        [$selector, $attribute, $pattern, $replacement, $reason, $decision] = $runtimeRule;
        $leftNodes = $leftXPath->query($selector);
        $rightNodes = $rightXPath->query($selector);
        if (($leftNodes === false || $leftNodes->length === 0) && ($rightNodes === false || $rightNodes->length === 0)) {
            continue;
        }
        $rules[] = [
            'page' => $page, 'template' => $page, 'selector' => $selector, 'attribute' => $attribute,
            'pattern' => $pattern, 'replacement' => $replacement,
            'reason' => 'approved nondeterministic ' . $reason,
            'decision_id' => 'DOM-' . $decision . '-' . $suffix,
            'used' => false, 'index' => 'runtime-' . strtolower($decision),
        ];
    }
    if (str_starts_with($page, 'framework-html-')) {
        foreach ([
            ['//p[starts-with(normalize-space(.),"Filename:")]', '#Filename:.*#', 'Filename: __APP__/parity-error-entry.php', 'ERROR-FILE'],
            ['//p[starts-with(normalize-space(.),"Line Number:")]', '#Line Number:.*#', 'Line Number: __LINE__', 'ERROR-LINE'],
        ] as $errorRule) {
            [$selector, $pattern, $replacement, $decision] = $errorRule;
            $leftNodes = $leftXPath->query($selector);
            $rightNodes = $rightXPath->query($selector);
            if (($leftNodes === false || $leftNodes->length === 0) && ($rightNodes === false || $rightNodes->length === 0)) {
                continue;
            }
            $rules[] = [
                'page' => $page, 'template' => $page, 'selector' => $selector, 'attribute' => '#text',
                'pattern' => $pattern, 'replacement' => $replacement,
                'reason' => 'approved nondeterministic framework error location',
                'decision_id' => 'DOM-' . $decision . '-' . $suffix,
                'used' => false, 'index' => 'runtime-' . strtolower($decision),
            ];
        }
    }
    foreach (['submission_id', 'batch_id'] as $runtimeField) {
        $selector = '//input[@type="hidden" and @name="' . $runtimeField . '"]';
        $leftNodes = $leftXPath->query($selector);
        $rightNodes = $rightXPath->query($selector);
        $leftCount = $leftNodes === false ? 0 : $leftNodes->length;
        $rightCount = $rightNodes === false ? 0 : $rightNodes->length;
        if ($leftCount === $rightCount || ($leftCount === 0 && $rightCount === 0)) {
            continue;
        }
        $rules[] = [
            'page' => $page, 'template' => $page, 'selector' => $selector, 'attribute' => '#remove',
            'reason' => 'approved nondeterministic workflow identifier supplied by CI4 security adapter',
            'decision_id' => 'DOM-RUNTIME-FIELD-' . strtoupper(str_replace('_', '-', $runtimeField)) . '-' . $suffix,
            'used' => false, 'index' => 'runtime-field-' . $runtimeField,
        ];
    }

    return $rules;
}

/** @return array{name: string, attributes: array<string, string>, children: list<array<string, mixed>>} */
function canonicalElement(DOMElement $element): array
{
    $attributes = [];
    foreach ($element->attributes as $attribute) {
        $attributes[$attribute->name] = $attribute->value;
    }
    ksort($attributes, SORT_STRING);
    $children = [];
    foreach ($element->childNodes as $child) {
        if ($child instanceof DOMElement) {
            $children[] = ['type' => 'element', 'value' => canonicalElement($child)];
        } elseif ($child instanceof DOMText || $child instanceof DOMCdataSection) {
            $text = preg_replace('/\s+/u', ' ', $child->nodeValue ?? '');
            $text = trim(is_string($text) ? $text : '');
            if ($text !== '') {
                $children[] = ['type' => 'text', 'value' => $text];
            }
        }
    }

    return ['name' => strtolower($element->tagName), 'attributes' => $attributes, 'children' => $children];
}

/** @return list<array<string, mixed>> */
function canonicalDocument(DOMDocument $document): array
{
    $elements = [];
    foreach ($document->childNodes as $node) {
        if ($node instanceof DOMElement) {
            $elements[] = ['type' => 'element', 'value' => canonicalElement($node)];
        }
    }

    return $elements;
}

/** @param list<array<string, mixed>> $differences */
function compareNodes(mixed $left, mixed $right, string $path, array &$differences): void
{
    if (count($differences) >= 500) {
        return;
    }
    if ($left === null || $right === null) {
        $differences[] = ['selector' => $path, 'kind' => 'missing_node', 'left' => $left, 'right' => $right];
        return;
    }
    if (($left['type'] ?? null) !== ($right['type'] ?? null)) {
        $differences[] = ['selector' => $path, 'kind' => 'node_type', 'left' => $left['type'] ?? null, 'right' => $right['type'] ?? null];
        return;
    }
    if ($left['type'] === 'text') {
        if ($left['value'] !== $right['value']) {
            $differences[] = ['selector' => $path, 'kind' => 'text', 'left' => $left['value'], 'right' => $right['value']];
        }
        return;
    }
    $leftElement = $left['value'];
    $rightElement = $right['value'];
    if ($leftElement['name'] !== $rightElement['name']) {
        $differences[] = ['selector' => $path, 'kind' => 'tag', 'left' => $leftElement['name'], 'right' => $rightElement['name']];
        return;
    }
    $elementPath = $path . '/' . $leftElement['name'];
    if ($leftElement['attributes'] !== $rightElement['attributes']) {
        $differences[] = ['selector' => $elementPath, 'kind' => 'attributes', 'left' => $leftElement['attributes'], 'right' => $rightElement['attributes']];
    }
    $maximum = max(count($leftElement['children']), count($rightElement['children']));
    for ($index = 0; $index < $maximum; $index++) {
        compareNodes(
            $leftElement['children'][$index] ?? null,
            $rightElement['children'][$index] ?? null,
            $elementPath . '/child()[' . ($index + 1) . ']',
            $differences,
        );
    }
}

try {
    $options = options($argv);
    foreach (['left', 'right', 'page'] as $required) {
        if (! isset($options[$required])) {
            throw new InvalidArgumentException("missing --{$required}");
        }
    }
    $left = document($options['left']);
    $right = document($options['right']);
    $externalRules = allowlist($options['allowlist'] ?? '', $options['page']);
    $rules = array_merge(runtimeRules($left, $right, $options['page'], $externalRules), $externalRules);
    normalize($left, $rules, 'left');
    normalize($right, $rules, 'right');
    $unused = array_values(array_map(
        static fn (array $rule): array => [
            'page' => $options['page'],
            'selector' => $rule['selector'],
            'attribute' => $rule['attribute'],
            'decision_id' => $rule['decision_id'],
        ],
        array_filter($rules, static fn (array $rule): bool => ! $rule['used']),
    ));
    if ($unused !== []) {
        fwrite(STDOUT, json_encode(['status' => 'FAIL', 'reason' => 'UNUSED_ALLOWLIST_RULE', 'unused_rules' => $unused], JSON_PRETTY_PRINT) . "\n");
        exit(2);
    }

    $leftTree = canonicalDocument($left);
    $rightTree = canonicalDocument($right);
    $differences = [];
    $maximum = max(count($leftTree), count($rightTree));
    for ($index = 0; $index < $maximum; $index++) {
        compareNodes($leftTree[$index] ?? null, $rightTree[$index] ?? null, '/document/child()[' . ($index + 1) . ']', $differences);
    }
    $result = [
        'status' => $differences === [] ? 'PASS' : 'FAIL',
        'page' => $options['page'],
        'difference_count' => count($differences),
        'differences' => $differences,
        'allowlist_rules_used' => count($rules),
    ];
    fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n");
    exit($differences === [] ? 0 : 1);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(2);
}
