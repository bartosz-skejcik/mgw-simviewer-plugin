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
     * Default configuration. Privacy-first (PRD §9): serial, status and CSV
     * export are all off by default. Phone number is read from the Fields
     * plugin custom field `nr_telefonu` (auto-detected when the table is blank).
     *
     * @return array<string, string>
     */
    public static function getDefaults(): array
    {
        return [
            'show_serial'   => '0',
            'show_status'   => '0',
            'enable_export' => '0',
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
            // Top nav-bar anchor for the self-service link (PRD FR-1a).
            'nav_selector'  => PLUGIN_SIMVIEWER_DEFAULT_NAV_SELECTOR,
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
        foreach (['show_serial', 'show_status', 'enable_export'] as $bool) {
            $input[$bool] = !empty($input[$bool]) ? '1' : '0';
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

        // Top nav-bar anchor (PRD FR-1a).
        echo "<tr class='tab_bg_1'><td>" . __s('Top nav-bar anchor (CSS selector)', 'simviewer') . "</td>";
        echo "<td><input type='text' class='form-control' name='nav_selector' value='" . htmlspecialchars($cfg['nav_selector']) . "'" . ($can_edit ? '' : ' readonly') . "></td></tr>";

        echo "</table>";

        if ($can_edit) {
            echo "<div class='text-center mt-3'>";
            echo "<button type='submit' name='update' class='btn btn-primary'>" . _sx('button', 'Save') . "</button>";
            echo "</div>";
        }

        echo "</div></div>";
        Html::closeForm();
    }
}
