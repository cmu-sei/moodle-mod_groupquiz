<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_groupquiz\utils;

defined('MOODLE_INTERNAL') || die();

/**
 * Group manager class for groupquiz
 *
 * @package     mod_groupquiz
 * @copyright   2014 Carnegie Mellon University
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
Group Quiz Plugin for Moodle
Copyright 2020 Carnegie Mellon University.
NO WARRANTY. THIS CARNEGIE MELLON UNIVERSITY AND SOFTWARE ENGINEERING INSTITUTE MATERIAL IS FURNISHED ON AN "AS-IS" BASIS. CARNEGIE MELLON UNIVERSITY MAKES NO WARRANTIES OF ANY KIND, EITHER EXPRESSED OR IMPLIED, AS TO ANY MATTER INCLUDING, BUT NOT LIMITED TO, WARRANTY OF FITNESS FOR PURPOSE OR MERCHANTABILITY, EXCLUSIVITY, OR RESULTS OBTAINED FROM USE OF THE MATERIAL. CARNEGIE MELLON UNIVERSITY DOES NOT MAKE ANY WARRANTY OF ANY KIND WITH RESPECT TO FREEDOM FROM PATENT, TRADEMARK, OR COPYRIGHT INFRINGEMENT.
Released under a GNU GPL 3.0-style license, please see license.txt or contact permission@sei.cmu.edu for full terms.
[DISTRIBUTION STATEMENT A] This material has been approved for public release and unlimited distribution.  Please see Copyright notice for non-US Government use and distribution.
This Software includes and/or makes use of the following Third-Party Software subject to its own license:
1. Moodle (https://docs.moodle.org/dev/License) Copyright 1999 Martin Dougiamas.
2. mod_activequiz (https://github.com/jhoopes/moodle-mod_activequiz/blob/master/README.md) Copyright 2014 John Hoopes and the University of Wisconsin.
DM20-0197
 */

class groupmanager {


    /** @var \mod_groupquiz\groupquiz $rtq */
    protected $rtq;


    /**
     * Construct new instance
     *
     * @param \mod_groupquiz\groupquiz
     */
    public function __construct($rtq) {
        $this->rtq = $rtq;

    }


    /**
     *
     *
     * @param int|null $userid
     *
     * @return array An array of group objects keyed by groupid
     */
    public function get_user_groups($userid = null) {
        global $USER;

        // assume current user when none specified
        if (empty($userid)) {
            $userid = $USER->id;
        }

        if (!empty($this->rtq->getRTQ()->grouping)) {
            return groups_get_all_groups($this->rtq->getCourse()->id, $userid, $this->rtq->getRTQ()->grouping);
        } else {
            return array(); // return empty array when there is no grouping
        }
    }

    /**
     * Gets an array of group names keyed by their group id.  is useful for selects and simple foreaches
     *
     * @param int|null $userid If left empty, current user is assumed
     * @param bool     $withdots Whether or not to have the choosedots string be the first element in the array
     * @return array An array of group names keyed by their id
     */
    public function get_user_groups_name_array($userid = null, $withdots = false) {

        $groups = $this->get_user_groups($userid);
        $retgroups = array();

        if ($withdots) {
            $retgroups[''] = get_string('choosedots');
        }

        foreach ($groups as $group) {
            $retgroups[ $group->id ] = $group->name;
        }

        return $retgroups;
    }

    /**
     * Get the group name for the specified groupid
     *
     * @param int $groupid The groupid
     *
     * @return string
     */
    public function get_group_name($groupid) {
        return groups_get_group_name($groupid);
    }

    /**
     * Wrapper function to get the group members
     *
     * @param int $groupid The groupid to get the members of
     *
     * @return array An array of user table user objects
     */
    public function get_group_members($groupid) {
        return groups_get_members($groupid);
    }

    /**
     * Wrapper function for groups is member
     *
     * @param int $groupid
     * @param int $userid Can be left blank and will assume current user if so
     *
     * @return bool
     */
    public function is_member_of_group($groupid, $userid = null) {
        return groups_is_member($groupid, $userid);
    }

    public function get_user_group() {

        $groups = $this->get_user_groups();
        if (count($groups) != 1) {
            echo "error - cannot find user group";
            // TODO display no user groups error
            exit;
        }
        $groupid = array_values($groups)[0]->id;

	return $groupid;
    }

}

