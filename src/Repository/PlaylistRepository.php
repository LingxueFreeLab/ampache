<?php
/*
 * vim:set softtabstop=4 shiftwidth=4 expandtab:
 *
 * LICENSE: GNU Affero General Public License, version 3 (AGPL-3.0-or-later)
 * Copyright 2001 - 2020 Ampache.org
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 */

declare(strict_types=1);

namespace Ampache\Repository;

use Ampache\Module\System\Core;
use Ampache\Module\System\Dba;

final class PlaylistRepository implements PlaylistRepositoryInterface
{
    /**
     * This function creates an empty playlist, gives it a name and type
     */
    public function create(
        string $name,
        string $type,
        int $userId
    ): int {
        // check for duplicates
        $results    = [];
        $db_results = Dba::read(
            'SELECT `id` FROM `playlist` WHERE `name` = ? AND `user` = ? AND `type` = ?',
            [$name, $userId, $type]
        );

        while ($row = Dba::fetch_assoc($db_results)) {
            $results[] = (int) $row['id'];
        }
        // return the duplicate ID
        if ($results !== []) {
            return $results[0];
        }

        $date = time();
        Dba::write(
            'INSERT INTO `playlist` (`name`, `user`, `type`, `date`, `last_update`) VALUES (?, ?, ?, ?, ?)',
            [$name, $userId, $type, $date, $date]
        );

        return (int) Dba::insert_id();
    }

    /**
     * Returns a list of playlists accessible by the user.
     *
     * @return int[]
     */
    public function getPlaylists(
        bool $includePublic = true,
        int $userId = -1,
        string $playlistName = '',
        bool $like = true
    ): array {
        if (!$userId) {
            $userId = Core::get_global('user')->id;
        }

        $sql    = 'SELECT `id` FROM `playlist`';
        $params = array();

        if ($userId > -1 && $includePublic) {
            $sql .= " WHERE (`user` = ? OR `type` = 'public')";
            $params[] = $userId;
        }
        if ($userId > -1 && !$includePublic) {
            $sql .= ' WHERE `user` = ?';
            $params[] = $userId;
        }
        if (!$userId > -1 && $includePublic) {
            $sql .= " WHERE `type` = 'public'";
        }

        if ($playlistName !== '') {
            $playlistName = (!$like) ? "= '" . $playlistName . "'" : "LIKE  '%" . $playlistName . "%' ";
            if (count($params) > 0 || $includePublic) {
                $sql .= " AND `name` " . $playlistName;
            } else {
                $sql .= " WHERE `name` " . $playlistName;
            }
        }
        $sql .= ' ORDER BY `name`';
        //debug_event(self::class, 'get_playlists query: ' . $sql, 5);

        $db_results = Dba::read($sql, $params);
        $results    = array();
        while ($row = Dba::fetch_assoc($db_results)) {
            $results[] = (int)$row['id'];
        }

        return $results;
    } // get_playlists
}