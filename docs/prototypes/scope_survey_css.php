<?php
/**
 * Scope the Survey-Module prototype CSS under .survey-proto so it can be
 * loaded inside the main KLAES app layout without hijacking the app chrome.
 *
 *  - :root            -> .survey-proto   (variables inherit to descendants)
 *  - * / html / body  -> scoped, or dropped where meaningless
 *  - shell selectors  -> dropped entirely (app supplies its own sidebar/header)
 *  - everything else  -> prefixed with .survey-proto
 *  - @keyframes       -> emitted verbatim
 *  - @media           -> recursed into
 */

const SCOPE = '.survey-proto';

// Prototype shell selectors the app already provides. Verified unused by all
// 24 section partials, so dropping them is safe and removes every collision.
const SHELL = [
    'sidebar', 'sidebar-overlay', 'sidebar-brand', 'sidebar-nav', 'sidebar-footer',
    'main-content', 'header', 'header-left', 'header-right', 'menu-toggle',
    'search-box', 'icon-btn', 'nav-item', 'nav-label', 'logo-icon',
    'user-card', 'user-info', 'survey-body',
];

/** Split a CSS body into top-level chunks: [type, prelude, block|null]. */
function chunks(string $css): array
{
    $out = [];
    $i = 0;
    $n = strlen($css);
    while ($i < $n) {
        // skip whitespace
        while ($i < $n && ctype_space($css[$i])) $i++;
        if ($i >= $n) break;

        // comment
        if (substr($css, $i, 2) === '/*') {
            $end = strpos($css, '*/', $i);
            $end = $end === false ? $n : $end + 2;
            $out[] = ['comment', substr($css, $i, $end - $i), null];
            $i = $end;
            continue;
        }

        // read prelude up to { or ;
        $start = $i;
        while ($i < $n && $css[$i] !== '{' && $css[$i] !== ';') $i++;

        if ($i < $n && $css[$i] === ';') {           // statement at-rule
            $out[] = ['stmt', trim(substr($css, $start, $i - $start + 1)), null];
            $i++;
            continue;
        }
        if ($i >= $n) break;

        $prelude = trim(substr($css, $start, $i - $start));

        // capture balanced block
        $depth = 0;
        $bstart = $i;
        while ($i < $n) {
            if ($css[$i] === '{') $depth++;
            elseif ($css[$i] === '}') { $depth--; if ($depth === 0) { $i++; break; } }
            elseif (substr($css, $i, 2) === '/*') { $e = strpos($css, '*/', $i); $i = $e === false ? $n : $e + 1; }
            $i++;
        }
        $block = substr($css, $bstart + 1, $i - $bstart - 2);
        $out[] = [$prelude[0] === '@' ? 'at' : 'rule', $prelude, $block];
    }
    return $out;
}

/** True when a selector targets prototype shell chrome. */
function isShell(string $sel): bool
{
    // first simple selector's class, e.g. ".main-content .kpi" -> main-content
    if (!preg_match('/^\s*\.([A-Za-z0-9_-]+)/', $sel, $m)) return false;
    return in_array($m[1], SHELL, true);
}

function scopeSelector(string $sel): ?string
{
    $sel = trim($sel);
    if ($sel === '') return null;

    if ($sel === ':root')  return SCOPE;
    if ($sel === 'html')   return null;                       // page-level, drop
    if ($sel === 'body')   return SCOPE;
    if ($sel === '*' || preg_match('/^\*::?(before|after)$/', $sel)) return SCOPE . ' ' . $sel;
    if (str_starts_with($sel, 'html') || str_starts_with($sel, 'body')) {
        $sel = preg_replace('/^(html|body)\s*/', '', $sel);
        if ($sel === '') return SCOPE;
    }
    if (isShell($sel)) return null;                           // app provides it

    // already scoped? (defensive)
    if (str_starts_with($sel, SCOPE)) return $sel;

    return SCOPE . ' ' . $sel;
}

/** Keep only inheritable look-and-feel declarations from the body rule. */
function filterBodyProps(string $block): string
{
    $allow = ['font-family', 'font-size', 'font-weight', 'line-height', 'background',
              'background-color', 'color', '-webkit-font-smoothing', '-moz-osx-font-smoothing'];
    $kept = [];
    foreach (explode(';', $block) as $decl) {
        if (trim($decl) === '') continue;
        $prop = strtolower(trim(explode(':', $decl, 2)[0]));
        if (in_array($prop, $allow, true)) $kept[] = trim($decl);
    }
    return $kept ? "\n    " . implode(";\n    ", $kept) . ";\n" : "\n";
}

function transform(string $css): string
{
    $out = [];
    foreach (chunks($css) as [$type, $prelude, $block]) {
        if ($type === 'comment' || $type === 'stmt') { $out[] = $prelude; continue; }

        if ($type === 'at') {
            $name = strtolower(preg_split('/[\s(]/', ltrim($prelude, '@'))[0]);
            if (in_array($name, ['keyframes', '-webkit-keyframes', 'font-face'], true)) {
                $out[] = $prelude . " {" . $block . "}";       // verbatim
            } elseif (in_array($name, ['media', 'supports'], true)) {
                $out[] = $prelude . " {\n" . transform($block) . "\n}";
            } else {
                $out[] = $prelude . " {" . $block . "}";
            }
            continue;
        }

        // plain rule: rewrite each selector in the list
        $sels = [];
        $isBody = false;
        foreach (explode(',', $prelude) as $s) {
            if (trim($s) === 'body') $isBody = true;
            $r = scopeSelector($s);
            if ($r !== null) $sels[] = $r;
        }
        if (!$sels) continue;                                  // whole rule dropped

        // The prototype's body rule existed to sit .sidebar beside .main-content.
        // On a content wrapper those box properties break the page; keep only the
        // typographic/background ones.
        if ($isBody) $block = filterBodyProps($block);

        $out[] = implode(",\n", $sels) . " {" . $block . "}";
    }
    return implode("\n", $out);
}

// ---- run ----
$src = $argv[1] ?? 'resources/views/survey_module/partials/_prototype_css.blade.php';
$dst = $argv[2] ?? 'resources/views/survey_module/partials/_prototype_css_scoped.blade.php';

$raw = file_get_contents($src);
$css = preg_replace('/^\s*<style>|<\/style>\s*$/s', '', $raw);

$scoped = transform($css);

$header = "{{-- Survey-Module prototype CSS, scoped under .survey-proto so it cannot\n"
        . "     affect the main KLAES chrome. Generated from _prototype_css.blade.php\n"
        . "     by scope_css.php — regenerate rather than hand-editing. --}}\n";

file_put_contents($dst, $header . "<style>\n" . $scoped . "\n</style>\n");

// ---- report ----
$dropShell = 0; $dropHtml = 0;
foreach (chunks($css) as [$t, $p, $b]) {
    if ($t !== 'rule') continue;
    foreach (explode(',', $p) as $s) {
        if (scopeSelector($s) === null) { isShell(trim($s)) ? $dropShell++ : $dropHtml++; }
    }
}
printf("wrote %s\n  %d bytes in -> %d bytes out\n  dropped %d shell selectors, %d html/page selectors\n",
    $dst, strlen($css), strlen($scoped), $dropShell, $dropHtml);
