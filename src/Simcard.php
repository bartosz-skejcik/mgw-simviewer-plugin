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
use Glpi\Application\View\TemplateRenderer;
use Session;

/**
 * Read-only SIM directory for the self-service interface.
 *
 * This is not a stored itemtype: it reads core `glpi_items_devicesimcards`
 * rows (entity-scoped) and renders a curated, read-only native GLPI table of
 * User + phone number (+ optional serial / status). Sensitive SIM fields
 * (PIN/PUK/PIN2/PUK2/MSIN) are never selected.
 */
class Simcard extends CommonDBTM
{
    protected static $notable = true;

    public static $rightname = 'plugin_simviewer';

    public const SIM_ITEMTYPE = 'Item_DeviceSimcard';

    public static function getTypeName($nb = 0)
    {
        return _n('SIM card', 'SIM cards', $nb, 'simviewer');
    }

    public static function getMenuName()
    {
        return __('SIM Viewer', 'simviewer');
    }

    public static function getIcon()
    {
        return 'ti ti-device-sim';
    }

    /** Read-only directory: only READ is ever required, never create/update/delete. */
    public static function canView(): bool
    {
        return Session::haveRight(self::$rightname, READ);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /** Absolute URL of the listing controller. */
    public static function getListUrl(): string
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        return $CFG_GLPI['root_doc'] . '/plugins/simviewer/front/simcard.php';
    }

    /** Absolute URL of the CSV export controller (front/export.php). */
    public static function getExportUrl(): string
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        return $CFG_GLPI['root_doc'] . '/plugins/simviewer/front/export.php';
    }

    /**
     * Fetch the entity-scoped, read-only SIM directory rows.
     *
     * @param string|null $filter Free-text filter matched against user + phone.
     *
     * @return list<array{user: string, phone: string, serial: string, status: string}>
     */
    public static function getRows(?string $filter = null): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $cfg   = Config::getConfig();
        $phone = Config::getPhoneSource($cfg);

        $isc = 'glpi_items_devicesimcards';

        $select = [
            "{$isc}.id AS sim_id",
            "{$isc}.serial AS serial",
            'glpi_users.id AS user_id',
            'glpi_users.name AS user_login',
            'glpi_users.realname AS user_realname',
            'glpi_users.firstname AS user_firstname',
            'glpi_states.name AS state_name',
        ];

        $leftjoin = [
            'glpi_users' => [
                'ON' => [$isc => 'users_id', 'glpi_users' => 'id'],
            ],
            'glpi_states' => [
                'ON' => [$isc => 'states_id', 'glpi_states' => 'id'],
            ],
        ];

        // Phone number source (Fields plugin custom field or native Line).
        $has_phone = $phone['type'] !== 'none';
        if ($phone['type'] === 'fields') {
            $ft = $phone['table'];
            $select[] = "{$ft}.{$phone['column']} AS phone";
            $leftjoin[$ft] = [
                'ON' => [
                    $ft  => 'items_id',
                    $isc => 'id',
                    ['AND' => ["{$ft}.itemtype" => self::SIM_ITEMTYPE]],
                ],
            ];
        } elseif ($phone['type'] === 'line') {
            $select[] = "glpi_lines.{$phone['column']} AS phone";
            $leftjoin['glpi_lines'] = [
                'ON' => [$isc => 'lines_id', 'glpi_lines' => 'id'],
            ];
        }

        // Entity scoping is enforced in SQL, never in the view layer (PRD FR-4).
        $where = ["{$isc}.is_deleted" => 0];
        $entity_restrict = getEntitiesRestrictCriteria($isc);
        if (!empty($entity_restrict)) {
            $where[] = $entity_restrict;
        }

        // Unassigned-SIM filter is enforced in SQL, never in the view layer,
        // same as the entity restriction above (TBL-01). Filter on the base
        // table's users_id column (not the joined glpi_users fields) so both
        // 0 and NULL-joined rows are excluded when show_unassigned is falsy.
        if (empty($cfg['show_unassigned'])) {
            $where[] = ["{$isc}.users_id" => ['>', 0]];
        }

        $iterator = $DB->request([
            'SELECT'    => $select,
            'DISTINCT'  => true,
            'FROM'      => $isc,
            'LEFT JOIN' => $leftjoin,
            'WHERE'     => $where,
        ]);

        $rows   = [];
        $needle = ($filter !== null && $filter !== '') ? mb_strtolower($filter) : null;

        foreach ($iterator as $data) {
            $user = '';
            if (!empty($data['user_id'])) {
                $user = formatUserName(
                    $data['user_id'],
                    $data['user_login'] ?? '',
                    $data['user_realname'] ?? '',
                    $data['user_firstname'] ?? '',
                );
            }
            if ($user === '') {
                $user = $data['user_login'] ?? '';
            }

            $phone_value  = $has_phone ? (string) ($data['phone'] ?? '') : '';
            $serial_value = (string) ($data['serial'] ?? '');
            $status_value = (string) ($data['state_name'] ?? '');

            // Server-side free-text filter on User + Phone (PRD FR-3).
            if ($needle !== null) {
                $haystack = mb_strtolower($user . ' ' . $phone_value);
                if (mb_strpos($haystack, $needle) === false) {
                    continue;
                }
            }

            $rows[] = [
                'user'   => $user,
                'phone'  => $phone_value,
                'serial' => $serial_value,
                'status' => $status_value,
            ];
        }

        // Default sort: User ascending (PRD FR-2).
        usort($rows, static fn(array $a, array $b): int => strnatcasecmp($a['user'], $b['user']));

        return $rows;
    }

    /**
     * Render the read-only listing using the native GLPI datatable component
     * (PRD FR-2: native GLPI table, not bespoke HTML).
     */
    public static function show(): void
    {
        $filter = isset($_GET['filter']) ? trim((string) $_GET['filter']) : '';
        $cfg    = Config::getConfig();
        $phone  = Config::getPhoneSource($cfg);

        $rows = self::getRows($filter !== '' ? $filter : null);

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

        $entries = [];
        foreach ($rows as $row) {
            $entry = [
                'user'  => $row['user'] !== '' ? $row['user'] : '—',
                'phone' => $row['phone'] !== '' ? $row['phone'] : '—',
            ];
            if (!empty($cfg['show_serial'])) {
                $entry['serial'] = $row['serial'] !== '' ? $row['serial'] : '—';
            }
            if (!empty($cfg['show_status'])) {
                $entry['status'] = $row['status'] !== '' ? $row['status'] : '—';
            }
            $entries[] = $entry;
        }

        $datatable = [
            'datatable_id'        => 'simviewer_list',
            // No per-column filter row (the page has its own search box)
            // and no pager (use_pager auto-resolves to false without
            // start/limit). Sorting stays disabled: the datatable sort
            // headers call reloadTab(), which only exists on item tabs,
            // not on a standalone plugin page.
            'nofilter'            => true,
            'nosort'              => true,
            'columns'             => $columns,
            'entries'             => $entries,
            'total_number'        => count($entries),
            'filtered_number'     => count($entries),
            'showmassiveactions'  => false,
        ];

        // Native export button (EXP-01/EXP-02): the core datatable component
        // only renders the Export anchor when csv_url is non-empty, so this
        // key is added exclusively when the admin has enabled export. The
        // export controller mirrors this exact query param so it reads the
        // same filter as the current view.
        if (!empty($cfg['enable_export'])) {
            $csv_url = self::getExportUrl();
            if ($filter !== '') {
                $csv_url .= '?filter=' . urlencode($filter);
            }
            $datatable['csv_url'] = $csv_url;
        }

        TemplateRenderer::getInstance()->display('@simviewer/list.html.twig', [
            'title'        => self::getMenuName(),
            'filter'       => $filter,
            'self_url'     => self::getListUrl(),
            'phone_source' => $phone['type'],
            'datatable'    => $datatable,
        ]);
    }
}
