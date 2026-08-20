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

namespace GlpiPlugin\Simviewer;

use CommonDBTM;
use CommonGLPI;
use Config as GlpiConfig;
use Dropdown;
use Html;
use Session;

/**
 * Plugin configuration, stored in the core `glpi_configs` table under the
 * `plugin:simviewer` context. The plugin creates no custom tables of its own.
 */
class Config extends CommonDBTM
{
    protected static $notable = true;

    /** Reuse the core configuration right: only config managers tune the plugin. */
    public static $rightname = 'config';

    public const CONTEXT = 'plugin:simviewer';

    /**
     * Default configuration. Serial and status columns stay off by default
     * (PRD §9). CSV export defaults ON (2026-07-20 decision, relaxes the
     * original privacy-first RODO recommendation); SIMs without an assigned
     * user are hidden by default (`show_unassigned`). Phone number is read
     * from the Fields plugin custom field `nr_telefonu` (auto-detected when
     * the table is blank).
     *
     * @return array<string, string>
     */
    public static function getDefaults(): array
    {
        return [
            'show_serial'   => '0',
            'show_status'   => '0',
            // Default flipped to '1' per the 2026-07-20 user decision, which
            // consciously relaxes the PRD's original privacy-first (RODO)
            // recommendation of exporting off by default.
            'enable_export' => '1',
            // Hide SIMs without an assigned user by default (94/136 rows on
            // production are unassigned and clutter the catalog); admins can
            // restore the full list via this Yes/No config option.
            'show_unassigned' => '0',
            // Restrict the directory to specific Lines (tariff plans): only SIMs
            // whose lines_id is listed here are shown. Empty = every line, the
            // pre-1.3.0 behaviour. Stored as a comma-separated list of
            // glpi_lines IDs because glpi_configs values are plain strings.
            'lines_filter'  => '',
            // 'fields' (Fields plugin custom field) or 'line' (native Line object).
            'phone_source'  => 'fields',
            // Empty fields_table => auto-detect the Fields plugin container table
            // (scans glpi_plugin_fields_* for one carrying the column below on
            // the Item_DeviceSimcard itemtype). The Fields plugin mangles the
            // declared field name "Nr telefonu" into this physical column.
            'fields_table'  => '',
            'fields_column' => 'nrtelefonufield',
            // Native Line fallback column (the "Caller number").
            'line_column'   => 'caller_num',
        ];
    }

    /**
     * Current configuration, defaults merged with stored values.
     *
     * @return array<string, string>
     */
    public static function getConfig(): array
    {
        $stored = GlpiConfig::getConfigurationValues(self::CONTEXT);

        return array_merge(self::getDefaults(), is_array($stored) ? $stored : []);
    }

    /** Seed default configuration on install (idempotent). */
    public static function install(): void
    {
        $existing = GlpiConfig::getConfigurationValues(self::CONTEXT);
        $missing  = array_diff_key(self::getDefaults(), is_array($existing) ? $existing : []);

        if (!empty($missing)) {
            GlpiConfig::setConfigurationValues(self::CONTEXT, $missing);
        }
    }

    /** Remove all plugin configuration on uninstall. */
    public static function uninstall(): void
    {
        $keys = array_keys(GlpiConfig::getConfigurationValues(self::CONTEXT));
        if (!empty($keys)) {
            (new GlpiConfig())->deleteConfigurationValues(self::CONTEXT, $keys);
        }
    }

    /**
     * Line (tariff plan) IDs the directory is restricted to.
     *
     * @param array<string, string> $cfg
     *
     * @return list<int> Empty list = no restriction (every line is shown).
     */
    public static function getLineIds(array $cfg): array
    {
        return self::parseLineIds($cfg['lines_filter'] ?? '');
    }

    /**
     * Normalise a stored/posted line filter into a list of positive, unique IDs.
     *
     * @param array<int, mixed>|string $raw
     *
     * @return list<int>
     */
    private static function parseLineIds($raw): array
    {
        if (!is_array($raw)) {
            $raw = trim((string) $raw);
            $raw = $raw === '' ? [] : explode(',', $raw);
        }

        $ids = [];
        foreach ($raw as $value) {
            $id = (int) trim((string) $value);
            if ($id > 0 && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Selectable Lines for the config form, keyed by ID.
     *
     * @param array<string, string> $cfg
     *
     * @return array<int, string>
     */
    public static function getLineOptions(array $cfg): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $options = [];

        if ($DB->tableExists('glpi_lines')) {
            $iterator = $DB->request([
                'SELECT' => ['id', 'name'],
                'FROM'   => 'glpi_lines',
                'WHERE'  => ['is_deleted' => 0],
                'ORDER'  => 'name',
            ]);
            foreach ($iterator as $row) {
                $options[(int) $row['id']] = (string) $row['name'];
            }
        }

        // A configured line that has since been deleted must stay in the list:
        // otherwise the form would show it as unselected and the next save
        // would silently drop a restriction that is still being applied.
        foreach (self::getLineIds($cfg) as $id) {
            if (!isset($options[$id])) {
                $options[$id] = sprintf(__('Line #%d (removed)', 'simviewer'), $id);
            }
        }

        return $options;
    }

    /**
     * Resolve where the phone number physically lives for the active config.
     *
     * @param array<string, string> $cfg
     *
     * @return array{type: string, table: string, column: string}
     *         type is 'fields', 'line' or 'none' (source unavailable — fail gracefully).
     */
    public static function getPhoneSource(array $cfg): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        if (($cfg['phone_source'] ?? 'fields') === 'line') {
            $column = $cfg['line_column'] ?: 'caller_num';
            if ($DB->tableExists('glpi_lines') && $DB->fieldExists('glpi_lines', $column)) {
                return ['type' => 'line', 'table' => 'glpi_lines', 'column' => $column];
            }
            return ['type' => 'none', 'table' => '', 'column' => ''];
        }

        // Fields plugin source.
        $column = $cfg['fields_column'] ?: 'nr_telefonu';
        $table  = $cfg['fields_table'] ?? '';

        if ($table !== '') {
            if ($DB->tableExists($table) && $DB->fieldExists($table, $column)) {
                return ['type' => 'fields', 'table' => $table, 'column' => $column];
            }
            return ['type' => 'none', 'table' => '', 'column' => ''];
        }

        // Auto-detect: the Fields plugin container table name is deployment-specific,
        // so scan glpi_plugin_fields_* tables for one carrying our column on the
        // Item_DeviceSimcard itemtype (items_id + itemtype shape).
        foreach ($DB->listTables('glpi_plugin_fields_%') as $row) {
            $candidate = $row['TABLE_NAME'] ?? (is_array($row) ? reset($row) : null);
            if (
                is_string($candidate)
                && $DB->fieldExists($candidate, $column)
                && $DB->fieldExists($candidate, 'items_id')
                && $DB->fieldExists($candidate, 'itemtype')
            ) {
                return ['type' => 'fields', 'table' => $candidate, 'column' => $column];
            }
        }

        return ['type' => 'none', 'table' => '', 'column' => ''];
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (!$withtemplate && $item instanceof GlpiConfig) {
            return self::createTabEntry(__('SIM Viewer', 'simviewer'));
        }

        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof GlpiConfig) {
            (new self())->showConfigForm();
        }

        return true;
    }

    /** Hook called by core Config when the plugin config form is submitted. */
    public static function configUpdate(array $input): array
    {
        // Normalise checkboxes to '0'/'1' strings.
        foreach (['show_serial', 'show_status', 'enable_export', 'show_unassigned'] as $bool) {
            $input[$bool] = !empty($input[$bool]) ? '1' : '0';
        }

        // The multi-select posts an array of IDs, or the empty string of its
        // companion hidden input when nothing is selected. glpi_configs stores
        // plain strings, so collapse either shape to a comma-separated list.
        if (array_key_exists('lines_filter', $input)) {
            $input['lines_filter'] = implode(',', self::parseLineIds($input['lines_filter']));
        }

        return $input;
    }

    public function showConfigForm(): void
    {
        if (!Session::haveRight(self::$rightname, READ)) {
            return;
        }

        $cfg      = self::getConfig();
        $can_edit = Session::haveRight(self::$rightname, UPDATE);

        echo "<form name='form' method='post' action='" . htmlspecialchars(GlpiConfig::getFormURL()) . "'>";
        echo "<div class='card'><div class='card-body'>";
        echo "<input type='hidden' name='config_class' value='" . htmlspecialchars(self::class) . "'>";
        echo "<input type='hidden' name='config_context' value='" . htmlspecialchars(self::CONTEXT) . "'>";

        echo "<h3 class='mb-3'>" . __s('SIM Viewer', 'simviewer') . "</h3>";
        echo "<table class='tab_cadre_fixe'>";

        // Display options.
        echo "<tr class='tab_bg_1'><td>" . __s('Show SIM serial column', 'simviewer') . "</td><td>";
        Dropdown::showYesNo('show_serial', $cfg['show_serial'], -1, ['readonly' => !$can_edit]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>" . __s('Show status column', 'simviewer') . "</td><td>";
        Dropdown::showYesNo('show_status', $cfg['show_status'], -1, ['readonly' => !$can_edit]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>" . __s('Enable CSV export', 'simviewer') . "</td><td>";
        Dropdown::showYesNo('enable_export', $cfg['enable_export'], -1, ['readonly' => !$can_edit]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>" . __s('Show SIMs without assigned user', 'simviewer') . "</td><td>";
        Dropdown::showYesNo('show_unassigned', $cfg['show_unassigned'], -1, ['readonly' => !$can_edit]);
        echo "</td></tr>";

        // Line (tariff plan) restriction: leaving it empty keeps every line.
        echo "<tr class='tab_bg_1'><td>" . __s('Restrict to lines (blank = all lines)', 'simviewer') . "</td><td>";
        Dropdown::showFromArray('lines_filter', self::getLineOptions($cfg), [
            'values'   => self::getLineIds($cfg),
            'multiple' => true,
            'readonly' => !$can_edit,
        ]);
        echo "</td></tr>";

        // Phone number source.
        echo "<tr class='tab_bg_1'><td>" . __s('Phone number source', 'simviewer') . "</td><td>";
        Dropdown::showFromArray('phone_source', [
            'fields' => __('Fields plugin custom field', 'simviewer'),
            'line'   => __('Native Line object', 'simviewer'),
        ], ['value' => $cfg['phone_source'], 'readonly' => !$can_edit]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>" . __s('Fields container table (blank = auto-detect)', 'simviewer') . "</td>";
        echo "<td><input type='text' class='form-control' name='fields_table' value='" . htmlspecialchars($cfg['fields_table']) . "'" . ($can_edit ? '' : ' readonly') . "></td></tr>";

        echo "<tr class='tab_bg_1'><td>" . __s('Phone number column name', 'simviewer') . "</td>";
        echo "<td><input type='text' class='form-control' name='fields_column' value='" . htmlspecialchars($cfg['fields_column']) . "'" . ($can_edit ? '' : ' readonly') . "></td></tr>";

        echo "<tr class='tab_bg_1'><td>" . __s('Line phone column name', 'simviewer') . "</td>";
        echo "<td><input type='text' class='form-control' name='line_column' value='" . htmlspecialchars($cfg['line_column']) . "'" . ($can_edit ? '' : ' readonly') . "></td></tr>";

        echo "</table>";

        if ($can_edit) {
            echo "<div class='text-center mt-3'>";
            // value='1' is required: a value-less button posts an empty string
            // and core config.form.php gates the save on !empty($_POST['update']).
            echo "<button type='submit' name='update' value='1' class='btn btn-primary'>" . _sx('button', 'Save') . "</button>";
            echo "</div>";
        }

        echo "</div></div>";
        Html::closeForm();
    }
}
