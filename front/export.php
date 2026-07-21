<?php

/**
 * -------------------------------------------------------------------------
 * Simviewer plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * MIT License
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2026 by the Simviewer plugin team.
 * @license   MIT https://opensource.org/licenses/mit-license.php
 * @link      https://github.com/pluginsGLPI/simviewer
 * -------------------------------------------------------------------------
 */

/**
 * SIM Viewer CSV export — read-only, GET-only controller (EXP-01 / EXP-02).
 * Streams the current directory view (same text filter, entity scope and
 * show_unassigned filter as front/simcard.php) as a CSV attachment.
 * No write endpoints exist in this plugin: this controller never handles
 * POST and never mutates state.
 */

use GlpiPlugin\Simviewer\Config;
use GlpiPlugin\Simviewer\Simcard;

include(__DIR__ . '/../../../inc/includes.php');

// First gate: no right => right error, no data (PRD FR-5 / §8), identical to
// front/simcard.php. Session::checkRight() displays the right error and
// exits when the right is missing.
Session::checkRight(Simcard::$rightname, READ);

// Second gate (new pattern — no analog elsewhere in this plugin): CSV export
// must be explicitly enabled via config. When disabled, behave like a right
// error (403-equivalent, no data) before any CSV header is sent, so a direct
// GET of this endpoint cannot be used to bypass the disabled export button.
$cfg = Config::getConfig();
if (empty($cfg['enable_export'])) {
    Html::displayRightError();
    exit;
}

// Read the filter exactly like Simcard::show() (src/Simcard.php).
$filter = isset($_GET['filter']) ? trim((string) $_GET['filter']) : '';

// Single shared data path: entity scope, the show_unassigned filter and the
// omission of sensitive SIM fields are all enforced inside the shared row
// accessor below — this controller issues no query of its own and adds no
// extra columns.
$rows = Simcard::getRows($filter !== '' ? $filter : null);

// Column set mirrors Simcard::show(): same config keys, same conditional
// order, so the CSV always matches the on-screen view.
$columns = [
    'user'  => __('User', 'simviewer'),
    'phone' => __('Phone number', 'simviewer'),
];
if (!empty($cfg['show_serial'])) {
    $columns['serial'] = __('SIM serial', 'simviewer');
}
if (!empty($cfg['show_status'])) {
    $columns['status'] = __('Status', 'simviewer');
}

/**
 * CSV formula-injection defense (escape decision): prefix a leading
 * apostrophe on any cell whose first character could trigger spreadsheet
 * formula evaluation (=, +, -, @) or a leading tab/carriage-return, so
 * exported values are never evaluated as formulas when opened in a
 * spreadsheet application.
 */
$escape_cell = static function (string $value): string {
    if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value) === 1) {
        return "'" . $value;
    }
    return $value;
};

// Stream a CSV attachment: UTF-8 BOM, ';' delimiter (Excel PL locale), no
// HTML chrome — this file never emits a page header or footer.
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="sim-viewer_' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, array_map($escape_cell, array_values($columns)), ';');

$active_keys = array_keys($columns);
foreach ($rows as $row) {
    $line = [];
    foreach ($active_keys as $key) {
        $line[] = $escape_cell((string) ($row[$key] ?? ''));
    }
    fputcsv($out, $line, ';');
}

fclose($out);
exit;
