<?php
/**
 * Mahara: Electronic portfolio, weblog, resume builder and social networking
 * Copyright (C) 2009 Moodle Pty Ltd (http://moodle.com)
 * Copyright (C) 2011 Catalyst IT Ltd (http://www.catalyst.net.nz)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

/**
 * External user API
 *
 * @package    artefact
 * @subpackage webservice
 * @copyright  2009 Moodle Pty Ltd (http://moodle.com)
 * @copyright  Copyright (C) 2011 Catalyst IT Ltd (http://www.catalyst.net.nz)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Piers Harding
 */

require_once(get_config('docroot') . "/artefact/webservice/serviceplugins/lib.php");

global $WEBSERVICE_OAUTH_USER;

class mahara_group_external extends external_api {

    private static $member_roles = array('admin', 'tutor', 'member');

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function create_groups_parameters() {

        $group_types = group_get_grouptypes();
        $group_edit_roles = array_keys(group_get_editroles_options());
        return new external_function_parameters(
            array(
                'groups' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name'            => new external_value(PARAM_RAW, 'Group name'),
                            'shortname'       => new external_value(PARAM_RAW, 'Group shortname for API only controlled groups', VALUE_OPTIONAL),
                            'description'     => new external_value(PARAM_NOTAGS, 'Group description'),
                            'institution'     => new external_value(PARAM_TEXT, 'Mahara institution - required for API controlled groups', VALUE_OPTIONAL),
                            'grouptype'       => new external_value(PARAM_ALPHANUMEXT, 'Group type: ' . implode(',', $group_types)),
                            'category'        => new external_value(PARAM_TEXT, 'Group category - the title of an existing group category', VALUE_OPTIONAL),
                            'editroles'       => new external_value(PARAM_ALPHANUMEXT, 'Edit roles allowed: ' . implode(',', $group_edit_roles), VALUE_OPTIONAL),
                            'open'            => new external_value(PARAM_INTEGER, 'Boolean 1/0 open - Users can join the group without approval from group administrators', VALUE_DEFAULT, '0'),
                            'controlled'      => new external_value(PARAM_INTEGER, 'Boolean 1/0 controlled - Group administrators can add users to the group without their consent, and members cannot choose to leave', VALUE_DEFAULT, '0'),
                            'request'         => new external_value(PARAM_INTEGER, 'Boolean 1/0 request - Users can send membership requests to group administrators', VALUE_DEFAULT, '0'),
                            'submitpages'     => new external_value(PARAM_INTEGER, 'Boolean 1/0 submitpages - Members can submit pages to the group', VALUE_DEFAULT),
                            'public'          => new external_value(PARAM_INTEGER, 'Boolean 1/0 public group', VALUE_DEFAULT),
                            'viewnotify'      => new external_value(PARAM_INTEGER, 'Boolean 1/0 for Shared page notifications', VALUE_DEFAULT),
                            'usersautoadded'  => new external_value(PARAM_INTEGER, 'Boolean 1/0 for auto-adding users', VALUE_DEFAULT),
                            'members'         => new external_multiple_structure(
                                                            new external_single_structure(
                                                                array(
                                                                    'id' => new external_value(PARAM_NUMBER, 'member user Id', VALUE_OPTIONAL),
                                                                    'username' => new external_value(PARAM_RAW, 'member username', VALUE_OPTIONAL),
                                                                    'role' => new external_value(PARAM_ALPHANUMEXT, 'member role: ' . implode(', ', self::$member_roles))
                                                                ), 'Group membership')
                                                        ),
                            )
                    )
                )
            )
        );
    }

    /**
     * Create one or more group
     *
     * @param array $groups  An array of groups to create.
     * @return array An array of arrays
     */
    public static function create_groups($groups) {
        global $USER, $WEBSERVICE_INSTITUTION;

        // Do basic automatic PARAM checks on incoming data, using params description
        $params = self::validate_parameters(self::create_groups_parameters(), array('groups' => $groups));
        db_begin();
        $groupids = array();
        foreach ($params['groups'] as $group) {
            // Make sure that the group doesn't already exist
            if (!empty($group['name'])) {
                // don't checked deleted as the real function doesn't
                if (get_record('group', 'name', $group['name'])) {
                    throw new invalid_parameter_exception('Group already exists: ' . $group['name']);
                }
            }
            // special API controlled group creations
            else if (isset($group['shortname']) && strlen($group['shortname'])) {
                // check the institution is allowed
                if (isset($group['institution']) && strlen($group['institution'])) {
                    if ($WEBSERVICE_INSTITUTION != $group['institution']) {
                        throw new invalid_parameter_exception('create_groups: access denied for institution: ' . $group['institution'] . ' on group: ' . $group['name']);
                    }
                    if (!$USER->can_edit_institution($group['institution'])) {
                        throw new invalid_parameter_exception('create_groups: access denied for institution: ' . $group['institution'] . ' on group: ' . $group['name']);
                    }
                }
                else {
                    throw new invalid_parameter_exception('create_groups: institution must be set on group: ' . $group['name'] . '/' . $group['shortname']);
                }
                // does the group exist?
                if (get_record('group', 'shortname', $group['shortname'], 'institution', $group['institution'])) {
                    throw new invalid_parameter_exception('Group already exists: ' . $group['shortname']);
                }
            }
            else {
                throw new invalid_parameter_exception('create_groups: no name or shortname specified');
            }

            // convert the category
            if (!empty($group['category'])) {
                $groupcategory = get_record('group_category','title', $group['category']);
                if (empty($groupcategory)) {
                    throw new invalid_parameter_exception('create_groups: category invalid: ' . $group['category']);
                }
                $group['category'] = $groupcategory->id;
            }

            // validate the join type combinations
            if ($group['open'] && $group['request']) {
                throw new invalid_parameter_exception('create_groups: invalid join type combination open+request');
            }
            if ($group['open'] && $group['controlled']) {
                throw new invalid_parameter_exception('create_groups: invalid join type combination open+controlled');
            }

            if (!$group['open'] && !$group['request'] && !$group['controlled']) {
                throw new invalid_parameter_exception('create_groups: must select correct join type open, request, and/or controlled');
            }
            if (isset($group['editroles']) && !in_array($group['editroles'], array_keys(group_get_editroles_options()))) {
                throw new invalid_parameter_exception("create_groups: group edit roles specified(" . $group['editroles'] . ") must be one of: " . implode(', ', array_keys(group_get_editroles_options())));
            }

            // check that the members exist and we are allowed to administer them
            $members = array($USER->get('id') => 'admin');
            foreach ($group['members'] as $member) {
                if (!empty($member['id'])) {
                    $dbuser = get_record('usr', 'id', $member['id'], 'deleted', 0);
                }
                else if (!empty($member['username'])) {
                    $dbuser = get_record('usr', 'username', $member['username'], 'deleted', 0);
                }
                else {
                    throw new invalid_parameter_exception('create_groups: no username or id for member - group: ' . $group['name']);
                }
                if (empty($dbuser)) {
                    throw new invalid_parameter_exception('create_groups: invalid user: ' . $member['id'] . '/' . $member['username'] . ' - group: ' . $group['name']);
                }

                // check user is in this institution if this is an institution controlled group
                if ((isset($group['shortname']) && strlen($group['shortname'])) && (isset($group['institution']) && strlen($group['institution']))) {
                    if (!mahara_external_in_institution($dbuser, $WEBSERVICE_INSTITUTION)) {
                        throw new invalid_parameter_exception('Not authorised to add user id: ' . $dbuser->id . ' institution: ' . $WEBSERVICE_INSTITUTION . ' to group: ' . $group['shortname']);
                    }
                }
                else {
                    // Make sure auth is valid
                    if (!$authinstance = get_record('auth_instance', 'id', $dbuser->authinstance)) {
                        throw new invalid_parameter_exception('Invalid authentication type: ' . $dbuser->authinstance);
                    }
                    // check the institution is allowed
                    // basic check authorisation to edit for the current institution of the user
                    if (!$USER->can_edit_institution($authinstance->institution)) {
                        throw new invalid_parameter_exception('create_groups: access denied for institution: ' . $authinstance->institution . ' on user: ' . $dbuser->username);
                    }
                }
                // check the specified role
                if (!in_array($member['role'], self::$member_roles)) {
                    throw new invalid_parameter_exception('update_groups: Invalid group membership role: ' . $member['role'] . ' for user: ' . $dbuser->username);
                }
                $members[$dbuser->id]= $member['role'];
            }

            // set the basic elements
            $create = array(
                'shortname'      => (isset($group['shortname']) ? $group['shortname'] : null),
                'name'           => (isset($group['name']) ? $group['name'] : null),
                'description'    => $group['description'],
                'institution'    => (isset($group['institution']) ? $group['institution'] : null),
                'grouptype'      => $group['grouptype'],
                'members'        => $members,
            );

            // check for the rest
            foreach (array('category', 'open', 'controlled', 'request', 'submitpages', 'editroles',
                           'hidemembers', 'invitefriends', 'suggestfriends', 'hidden', 'quota',
                           'hidemembersfrommembers', 'public', 'usersautoadded', 'viewnotify',) as $attr) {
                if (isset($group[$attr]) && $group[$attr] !== false && $group[$attr] !== null && strlen("" . $group[$attr])) {
                    $create[$attr] = $group[$attr];
                }
            }

            // create the group
            $id = group_create($create);

            $groupids[] = array('id'=> $id, 'name'=> $group['name']);
        }
        db_commit();

        return $groupids;
    }

   /**
     * Returns description of method result value
     * @return external_description
     */
    public static function create_groups_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id'       => new external_value(PARAM_INT, 'group id'),
                    'name'     => new external_value(PARAM_RAW, 'group name'),
                )
            )
        );
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function delete_groups_parameters() {
        return new external_function_parameters(
            array(
                'groups' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id'              => new external_value(PARAM_NUMBER, 'ID of the group', VALUE_OPTIONAL),
                            'name'            => new external_value(PARAM_RAW, 'Group name', VALUE_OPTIONAL),
                            'shortname'       => new external_value(PARAM_RAW, 'Group shortname for API only controlled groups', VALUE_OPTIONAL),
                            'institution'     => new external_value(PARAM_TEXT, 'Mahara institution - required for API controlled groups', VALUE_OPTIONAL),
                        )
                    )
                )
            )
        );
    }

    /**
     * Delete one or more groups
     *
     * @param array $groups
     */
    public static function delete_groups($groups) {
        global $USER, $WEBSERVICE_INSTITUTION;

        $params = self::validate_parameters(self::delete_groups_parameters(), array('groups'=>$groups));

        db_begin();
        foreach ($params['groups'] as $group) {
            // Make sure that the group doesn't already exist
            if (!empty($group['id'])) {
                if (!$dbgroup = get_record('group', 'id', $group['id'], 'deleted', 0)) {
                    throw new invalid_parameter_exception('delete_groups: Group does not exist: ' . $group['id']);
                }
            }
            else if (!empty($group['name'])) {
                if (!$dbgroup = get_record('group', 'name', $group['name'], 'deleted', 0)) {
                    throw new invalid_parameter_exception('delete_groups: Group does not exist: ' . $group['name']);
                }
            }
            else if (!empty($group['shortname'])) {
                if (empty($group['institution'])) {
                    throw new invalid_parameter_exception('delete_groups: institution must be set for: ' . $group['shortname']);
                }
                if (!$dbgroup = get_record('group', 'shortname', $group['shortname'], 'institution', $group['institution'], 'deleted', 0)) {
                    throw new invalid_parameter_exception('delete_groups: Group does not exist: ' . $group['shortname'] . '/' . $group['institution']);
                }
            }
            else {
                throw new invalid_parameter_exception('delete_groups: no group specified');
            }

            // are we allowed to delete for this institution
            if (!empty($dbgroup->institution)) {
                if ($WEBSERVICE_INSTITUTION != $dbgroup->institution) {
                    throw new invalid_parameter_exception('delete_groups: access denied for institution: ' . $group['institution'] . ' on group: ' . $group['name']);
                }
                if (!$USER->can_edit_institution($dbgroup->institution)) {
                    throw new invalid_parameter_exception('delete_groups: access denied for institution: ' . $group['institution'] . ' on group: ' . $group['shortname']);
                }
            }

            // now do the delete
            group_delete($dbgroup->id);
        }
        db_commit();

        return null;
    }

   /**
     * Returns description of method result value
     * @return external_description
     */
    public static function delete_groups_returns() {
        return null;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function update_groups_parameters() {

        $group_types = group_get_grouptypes();
        $group_edit_roles = array_keys(group_get_editroles_options());
        return new external_function_parameters(
            array(
                'groups' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id'              => new external_value(PARAM_NUMBER, 'ID of the group', VALUE_OPTIONAL),
                            'name'            => new external_value(PARAM_RAW, 'Group name', VALUE_OPTIONAL),
                            'shortname'       => new external_value(PARAM_RAW, 'Group shortname for API only controlled groups', VALUE_OPTIONAL),
                            'description'     => new external_value(PARAM_NOTAGS, 'Group description'),
                            'institution'     => new external_value(PARAM_TEXT, 'Mahara institution - required for API controlled groups', VALUE_OPTIONAL),
                            'grouptype'       => new external_value(PARAM_ALPHANUMEXT, 'Group type: ' . implode(',', $group_types), VALUE_OPTIONAL),
                            'category'        => new external_value(PARAM_TEXT, 'Group category - the title of an existing group category', VALUE_OPTIONAL),
                            'editroles'       => new external_value(PARAM_ALPHANUMEXT, 'Edit roles allowed: ' . implode(',', $group_edit_roles), VALUE_OPTIONAL),
                            'open'            => new external_value(PARAM_INTEGER, 'Boolean 1/0 open - Users can join the group without approval from group administrators', VALUE_DEFAULT),
                            'controlled'      => new external_value(PARAM_INTEGER, 'Boolean 1/0 controlled - Group administrators can add users to the group without their consent, and members cannot choose to leave', VALUE_DEFAULT),
                            'request'         => new external_value(PARAM_INTEGER, 'Boolean 1/0 request - Users can send membership requests to group administrators', VALUE_DEFAULT),
                            'submitpages'     => new external_value(PARAM_INTEGER, 'Boolean 1/0 submitpages - Members can submit pages to the group', VALUE_DEFAULT),
                            'public'          => new external_value(PARAM_INTEGER, 'Boolean 1/0 public group', VALUE_DEFAULT),
                            'viewnotify'      => new external_value(PARAM_INTEGER, 'Boolean 1/0 for Shared page notifications', VALUE_DEFAULT),
                            'usersautoadded'  => new external_value(PARAM_INTEGER, 'Boolean 1/0 for auto-adding users', VALUE_DEFAULT),
                            'members'         => new external_multiple_structure(
                                                            new external_single_structure(
                                                                array(
                                                                    'id' => new external_value(PARAM_NUMBER, 'member user Id', VALUE_OPTIONAL),
                                                                    'username' => new external_value(PARAM_RAW, 'member username', VALUE_OPTIONAL),
                                                                    'role' => new external_value(PARAM_ALPHANUMEXT, 'member role: ' . implode(', ', self::$member_roles))
                                                                ), 'Group membership')
                                                        ),
                            )
                    )
                )
            )
        );
    }

    /**
     * update one or more users
     *
     * @param array $users
     */
    public static function update_groups($groups) {
        global $USER, $WEBSERVICE_INSTITUTION;

        // Do basic automatic PARAM checks on incoming data, using params description
        $params = self::validate_parameters(self::update_groups_parameters(), array('groups'=>$groups));

        db_begin();
        $groupids = array();
        foreach ($params['groups'] as $group) {
            // Make sure that the group doesn't already exist
            if (!empty($group['id'])) {
                if (!$dbgroup = get_record('group', 'id', $group['id'], 'deleted', 0)) {
                    throw new invalid_parameter_exception('update_groups: Group does not exist: ' . $group['id']);
                }
            }
            else if (!empty($group['shortname'])) {
                if (empty($group['institution'])) {
                    throw new invalid_parameter_exception('update_groups: institution must be set for: ' . $group['shortname']);
                }
                if (!$dbgroup = get_record('group', 'shortname', $group['shortname'], 'institution', $group['institution'], 'deleted', 0)) {
                    throw new invalid_parameter_exception('update_groups: Group does not exist: ' . $group['shortname'] . '/' . $group['institution']);
                }
            }
            else if (!empty($group['name'])) {
                if (!$dbgroup = get_record('group', 'name', $group['name'], 'deleted', 0)) {
                    throw new invalid_parameter_exception('update_groups: Group does not exist: ' . $group['name']);
                }
            }
            else {
                throw new invalid_parameter_exception('update_groups: no group specified');
            }

            // are we allowed to delete for this institution
            if ($WEBSERVICE_INSTITUTION != $dbgroup->institution) {
                throw new invalid_parameter_exception('update_groups: access denied for institution: ' . $group['institution'] . ' on group: ' . $group['name']);
            }
            if (!$USER->can_edit_institution($dbgroup->institution)) {
                throw new invalid_parameter_exception('update_groups: access denied for institution: ' . $group['institution'] . ' on group: ' . $group['shortname']);
            }

            // convert the category
            if (!empty($group['category'])) {
                $groupcategory = get_record('group_category','title', $group['category']);
                if (empty($groupcategory)) {
                    throw new invalid_parameter_exception('create_groups: category invalid: ' . $group['category']);
                }
                $group['category'] = $groupcategory->id;
            }

            // validate the join type combinations
            if (isset($group['open']) || isset($group['request']) || isset($group['controlled'])) {
                foreach (array('open', 'request', 'controlled') as $membertype) {
                    if (!isset($group[$membertype]) || empty($group[$membertype])) {
                        $group[$membertype] = 0;
                    }
                }
                if ($group['open'] && $group['request']) {
                    throw new invalid_parameter_exception('create_groups: invalid join type combination open+request');
                }
                if ($group['open'] && $group['controlled']) {
                    throw new invalid_parameter_exception('create_groups: invalid join type combination open+controlled');
                }

                if (!$group['open'] && !$group['request'] && !$group['controlled']) {
                    throw new invalid_parameter_exception('create_groups: must select correct join type open, request, and/or controlled');
                }
            }
            if (isset($group['editroles']) && !in_array($group['editroles'], array_keys(group_get_editroles_options()))) {
                throw new invalid_parameter_exception("create_groups: group edit roles specified(" . $group['editroles'] . ") must be one of: " . implode(', ', array_keys(group_get_editroles_options())));
            }

            // check that the members exist and we are allowed to administer them
            $members = array($USER->get('id') => 'admin');
            foreach ($group['members'] as $member) {
                if (!empty($member['id'])) {
                    $dbuser = get_record('usr', 'id', $member['id'], 'deleted', 0);
                }
                else if (!empty($member['username'])) {
                    $dbuser = get_record('usr', 'username', $member['username'], 'deleted', 0);
                }
                else {
                    throw new invalid_parameter_exception('update_groups: no username or id for member - group: ' . $group['name']);
                }
                if (empty($dbuser)) {
                    throw new invalid_parameter_exception('update_groups: invalid user: ' . $member['id'] . '/' . $member['username'] . ' - group: ' . $group['name']);
                }

                // check user is in this institution if this is an institution controlled group
                if (!empty($dbgroup->shortname) && !empty($dbgroup->institution)) {
                    if (!mahara_external_in_institution($dbuser, $WEBSERVICE_INSTITUTION)) {
                        throw new invalid_parameter_exception('update_groups: Not authorised to add user id: ' . $dbuser->id . ' institution: ' . $WEBSERVICE_INSTITUTION . ' to group: ' . $group['shortname']);
                    }
                }
                else {
                    // Make sure auth is valid
                    if (!$authinstance = get_record('auth_instance', 'id', $dbuser->authinstance)) {
                        throw new invalid_parameter_exception('update_groups: Invalid authentication type: ' . $dbuser->authinstance);
                    }
                    // check the institution is allowed
                    // basic check authorisation to edit for the current institution of the user
                    if (!$USER->can_edit_institution($authinstance->institution)) {
                        throw new invalid_parameter_exception('update_groups: access denied for institution: ' . $authinstance->institution . ' on user: ' . $dbuser->username);
                    }
                }

                // check the specified role
                if (!in_array($member['role'], self::$member_roles)) {
                    throw new invalid_parameter_exception('update_groups: Invalid group membership role: ' . $member['role'] . ' for user: ' . $dbuser->username);
                }
                $members[$dbuser->id] = $member['role'];
            }

            // build up the changes
            // not allowed to change these
            $newvalues = (object) array(
                'id'             => $dbgroup->id,
            );
            foreach (array('name', 'description', 'grouptype', 'category', 'editroles',
                           'open', 'controlled', 'request', 'submitpages', 'quota',
                           'hidemembers', 'invitefriends', 'suggestfriends',
                           'hidden', 'hidemembersfrommembers',
                           'usersautoadded', 'public', 'viewnotify') as $attr) {
                if (isset($group[$attr]) && $group[$attr] !== false && $group[$attr] !== null && strlen("" . $group[$attr])) {
                    $newvalues->{$attr} = $group[$attr];
                }
            }
            group_update($newvalues);

            // now update the group membership
            group_update_members($dbgroup->id, $members);

        }
        db_commit();

        return null;
    }

   /**
     * Returns description of method result value
     * @return external_description
     */
    public static function update_groups_returns() {
        return null;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function update_group_members_parameters() {

        return new external_function_parameters(
            array(
                'groups' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id'              => new external_value(PARAM_NUMBER, 'ID of the group', VALUE_OPTIONAL),
                            'name'            => new external_value(PARAM_RAW, 'Group name', VALUE_OPTIONAL),
                            'shortname'       => new external_value(PARAM_RAW, 'Group shortname for API only controlled groups', VALUE_OPTIONAL),
                            'institution'     => new external_value(PARAM_TEXT, 'Mahara institution - required for API controlled groups', VALUE_OPTIONAL),
                            'members'         => new external_multiple_structure(
                                                            new external_single_structure(
                                                                array(
                                                                    'id' => new external_value(PARAM_NUMBER, 'member user Id', VALUE_OPTIONAL),
                                                                    'username' => new external_value(PARAM_RAW, 'member username', VALUE_OPTIONAL),
                                                                    'role' => new external_value(PARAM_ALPHANUMEXT, 'member role: admin, tutor, member', VALUE_OPTIONAL),
                                                                    'action' => new external_value(PARAM_ALPHANUMEXT, 'member action: add, or remove')
                                                                ), 'Group membership actions')
                                                        ),
                            )
                    )
                )
            )
        );
    }

    /**
     * update one or more sets of group membership
     *
     * @param array $users
     */
    public static function update_group_members($groups) {
        global $USER, $WEBSERVICE_INSTITUTION;

        // Do basic automatic PARAM checks on incoming data, using params description
        $params = self::validate_parameters(self::update_group_members_parameters(), array('groups'=>$groups));

        db_begin();
        $groupids = array();
        foreach ($params['groups'] as $group) {
            // Make sure that the group doesn't already exist
            if (!empty($group['id'])) {
                if (!$dbgroup = get_record('group', 'id', $group['id'], 'deleted', 0)) {
                    throw new invalid_parameter_exception('update_group_members: Group does not exist: ' . $group['id']);
                }
            }
            else if (!empty($group['name'])) {
                if (!$dbgroup = get_record('group', 'name', $group['name'], 'deleted', 0)) {
                    throw new invalid_parameter_exception('update_groups: Group does not exist: ' . $group['name']);
                }
            }
            else if (!empty($group['shortname'])) {
                if (empty($group['institution'])) {
                    throw new invalid_parameter_exception('update_group_members: institution must be set for: ' . $group['shortname']);
                }
                if (!$dbgroup = get_record('group', 'shortname', $group['shortname'], 'institution', $group['institution'], 'deleted', 0)) {
                    throw new invalid_parameter_exception('update_groups: Group does not exist: ' . $group['shortname'] . '/' . $group['institution']);
                }
            }
            else {
                throw new invalid_parameter_exception('update_group_members: no group specified');
            }

            // are we allowed to administer this group
            if (!empty($dbgroup->institution) && $WEBSERVICE_INSTITUTION != $dbgroup->institution) {
                throw new invalid_parameter_exception('update_group_members: access denied for institution: ' . $group['institution'] . ' on group: ' . $group['name']);
            }
            if (!empty($dbgroup->institution) && !$USER->can_edit_institution($dbgroup->institution)) {
                throw new invalid_parameter_exception('update_group_members: access denied for institution: ' . $group['institution'] . ' on group: ' . $group['shortname']);
            }

            // get old members
            $oldmembers = get_records_array('group_member', 'group', $dbgroup->id, '', 'member,role');
            $existingmembers = array();
            if (!empty($oldmembers)) {
                foreach ($oldmembers as $member) {
                    $existingmembers[$member->member] = $member->role;
                }
            }

            // check that the members exist and we are allowed to administer them
            foreach ($group['members'] as $member) {
                if (!empty($member['id'])) {
                    $dbuser = get_record('usr', 'id', $member['id'], 'deleted', 0);
                }
                else if (!empty($member['username'])) {
                    $dbuser = get_record('usr', 'username', $member['username'], 'deleted', 0);
                }
                else {
                    throw new invalid_parameter_exception('update_group_members: no username or id for member - group: ' . $group['name']);
                }
                if (empty($dbuser)) {
                    throw new invalid_parameter_exception('update_group_members: invalid user: ' . $member['id'] . '/' . $member['username'] . ' - group: ' . $group['name']);
                }


                // check user is in this institution if this is an institution controlled group
                if (!empty($dbgroup->shortname) && !empty($dbgroup->institution)) {
                    if (!mahara_external_in_institution($dbuser, $WEBSERVICE_INSTITUTION)) {
                        throw new invalid_parameter_exception('update_group_members: Not authorised to manage user id: ' . $dbuser->id . ' institution: ' . $WEBSERVICE_INSTITUTION . ' to group: ' . $group['shortname']);
                    }
                }
                else {
                    // Make sure auth is valid
                    if (!$authinstance = get_record('auth_instance', 'id', $dbuser->authinstance)) {
                        throw new invalid_parameter_exception('update_group_members: Invalid authentication type: ' . $dbuser->authinstance);
                    }
                    // check the institution is allowed
                    // basic check authorisation to edit for the current institution of the user
                    if (!$USER->can_edit_institution($authinstance->institution)) {
                        throw new invalid_parameter_exception('update_group_members: access denied for institution: ' . $authinstance->institution . ' on user: ' . $dbuser->username);
                    }
                }

                // determine the changes to the group membership
                if ($member['action'] == 'remove') {
                    if (isset($existingmembers[$dbuser->id])) {
                        unset($existingmembers[$dbuser->id]);
                    }
                    // silently fail
                }
                // add also can be used to update role
                else if ($member['action'] == 'add') {
                    // check the specified role
                    if (!in_array($member['role'], self::$member_roles)) {
                        throw new invalid_parameter_exception('update_group_members: Invalid group membership role: ' . $member['role'] . ' for user: ' . $dbuser->username);
                    }
                    $existingmembers[$dbuser->id] = $member['role'];
                    // silently fail
                }
                else {
                    throw new invalid_parameter_exception('update_group_members: invalid action(' . $member['action'] . ') for user: ' . $dbuser->id . '/' . $dbuser->username . ' - group: ' . $group['name']);
                }
            }

            // now update the group membership
            group_update_members($dbgroup->id, $existingmembers);

        }
        db_commit();

        return null;
    }

   /**
     * Returns description of method result value
     * @return external_description
     */
    public static function update_group_members_returns() {
        return null;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_groups_by_id_parameters() {
        return new external_function_parameters(
            array(
                'groups' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id'              => new external_value(PARAM_NUMBER, 'ID of the group', VALUE_OPTIONAL),
                            'shortname'       => new external_value(PARAM_RAW, 'Group shortname for API only controlled groups', VALUE_OPTIONAL),
                            'institution'     => new external_value(PARAM_TEXT, 'Mahara institution - required for API controlled groups', VALUE_OPTIONAL),
                            )
                        )
                    )
                )
            );
    }

    /**
     * Get user information
     *
     * @param array $groups  array of groups
     * @return array An array of arrays describing groups
     */
    public static function get_groups_by_id($groups) {
        global $WEBSERVICE_INSTITUTION, $USER;

        $params = self::validate_parameters(self::get_groups_by_id_parameters(),
                array('groups' => $groups));

        // if this is a get all users - then lets get them all
        if (empty($params['groups'])) {
            $params['groups'] = array();
            $dbgroups = get_records_sql_array('SELECT * FROM {group} WHERE institution = ? AND deleted = 0', array($WEBSERVICE_INSTITUTION));
            if ($dbgroups) {
                foreach ($dbgroups as $dbgroup) {
                    $params['groups'][] = array('id' => $dbgroup->id);
                }
            }
        }

        // now process the ids
        $groups = array();
        foreach ($params['groups'] as $group) {
            // Make sure that the group doesn't already exist
            if (!empty($group['id'])) {
                if (!$dbgroup = get_record('group', 'id', $group['id'], 'deleted', 0)) {
                    throw new invalid_parameter_exception('get_groups_by_id: Group does not exist: ' . $group['id']);
                }
            }
            else if (!empty($group['shortname'])) {
                if (empty($group['institution'])) {
                    throw new invalid_parameter_exception('get_groups_by_id: institution must be set for: ' . $group['shortname']);
                }
                if (!$dbgroup = get_record('group', 'shortname', $group['shortname'], 'institution', $group['institution'], 'deleted', 0)) {
                    throw new invalid_parameter_exception('get_groups_by_id: Group does not exist: ' . $group['shortname']);
                }
            }
            else {
                throw new invalid_parameter_exception('get_groups_by_id: no group specified');
            }

            // must have access to the related institution
            if ($WEBSERVICE_INSTITUTION != $dbgroup->institution) {
                throw new invalid_parameter_exception('get_group: access denied(' . $WEBSERVICE_INSTITUTION . ') for institution: ' . $dbgroup->institution . ' on group: ' . $dbgroup->shortname);
            }
            if (!$USER->can_edit_institution($dbgroup->institution)) {
                throw new invalid_parameter_exception('get_group: access denied(2) for institution: ' . $dbgroup->institution . ' on group: ' . $dbgroup->shortname);
            }

            // get the members
            $dbmembers = get_records_sql_array('SELECT gm.member AS userid, u.username AS username, gm.role AS role FROM {group_member} gm LEFT JOIN {usr} u ON gm.member = u.id WHERE "group" = ?', array($dbgroup->id));
            $members = array();
            if ($dbmembers) {
                foreach ($dbmembers as $member) {
                    $members []= array('id' => $member->userid, 'username' => $member->username, 'role' => $member->role);
                }
            }

            // form up the output
            $groups[]= array(
                        'id'             => $dbgroup->id,
                        'name'           => $dbgroup->name,
                        'shortname'      => $dbgroup->shortname,
                        'description'    => $dbgroup->description,
                        'institution'    => $dbgroup->institution,
                        'grouptype'      => $dbgroup->grouptype,
                        'category'       => ($dbgroup->category ? get_field('group_category', 'title', 'id', $dbgroup->category) : ''),
                        'editroles'      => $dbgroup->editroles,
                        'open'           => ($dbgroup->jointype == 'open' ? 1 : 0),
                        'controlled'     => ($dbgroup->jointype == 'controlled' ? 1 : 0),
                        'request'        => $dbgroup->request,
                        'submitpages'    => (isset($dbgroup->submitpages) ? $dbgroup->submitpages : 0),
                        'public'         => $dbgroup->public,
                        'viewnotify'     => $dbgroup->viewnotify,
                        'usersautoadded' => $dbgroup->usersautoadded,
                        'members'        => $members,
                );
        }
        return $groups;
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_groups_by_id_returns() {
        $group_types = group_get_grouptypes();
        $group_edit_roles = array_keys(group_get_editroles_options());
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id'              => new external_value(PARAM_NUMBER, 'ID of the group'),
                    'name'            => new external_value(PARAM_RAW, 'Group name'),
                    'shortname'       => new external_value(PARAM_RAW, 'Group shortname for API only controlled groups'),
                    'description'     => new external_value(PARAM_NOTAGS, 'Group description'),
                    'institution'     => new external_value(PARAM_TEXT, 'Mahara institution - required for API controlled groups'),
                    'grouptype'       => new external_value(PARAM_ALPHANUMEXT, 'Group type: ' . implode(',', $group_types)),
                    'category'        => new external_value(PARAM_TEXT, 'Group category - the title of an existing group category'),
                    'editroles'       => new external_value(PARAM_ALPHANUMEXT, 'Edit roles allowed: ' . implode(',', $group_edit_roles)),
                    'open'            => new external_value(PARAM_INTEGER, 'Boolean 1/0 open - Users can join the group without approval from group administrators'),
                    'controlled'      => new external_value(PARAM_INTEGER, 'Boolean 1/0 controlled - Group administrators can add users to the group without their consent, and members cannot choose to leave'),
                    'request'         => new external_value(PARAM_INTEGER, 'Boolean 1/0 request - Users can send membership requests to group administrators'),
                    'submitpages'     => new external_value(PARAM_INTEGER, 'Boolean 1/0 submitpages - Members can submit pages to the group'),
                    'public'          => new external_value(PARAM_INTEGER, 'Boolean 1/0 public group'),
                    'viewnotify'      => new external_value(PARAM_INTEGER, 'Boolean 1/0 for Shared page notifications'),
                    'usersautoadded'  => new external_value(PARAM_INTEGER, 'Boolean 1/0 for auto-adding users'),
                    'members'         => new external_multiple_structure(
                                                    new external_single_structure(
                                                        array(
                                                            'id' => new external_value(PARAM_NUMBER, 'member user Id'),
                                                            'username' => new external_value(PARAM_RAW, 'member username'),
                                                            'role' => new external_value(PARAM_ALPHANUMEXT, 'member role: admin, ')
                                                        ), 'Group membership')
                                                ),
                    )
            )
        );
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_groups_parameters() {
        return new external_function_parameters(array());
    }

    /**
     * Get group information
     *
     * @param array $groupids  array of group ids
     * @return array An array of arrays describing groups
     */
    public static function get_groups() {
        return self::get_groups_by_id(array());
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_groups_returns() {
        return self::get_groups_by_id_returns();
    }
}
