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

require_once("$CFG->docroot/artefact/webservice/libs/externallib.php");
require_once($CFG->docroot.'/lib/group.php');


/**
 * Adds a member to a group.
 *
 * Doesn't do any jointype checking, that should be handled by the caller.
 *
 * TODO: it should though. We should probably have group_user_can_be_added
 *
 * @param int $groupid
 * @param int $userid
 * @param string $role
 */
// group_add_user($groupid, $userid, $role=null);

/**
 * Removes a user from a group.
 *
 * @param int $groupid ID of group
 * @param int $userid  ID of user to remove
 */
//group_remove_user($groupid, $userid=null, $force=false);





class mahara_group_external extends external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function create_groups_parameters() {

        $group_types = group_get_grouptypes();
        return new external_function_parameters(
            array(
                'groups' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name'            => new external_value(PARAM_RAW, 'Group name'),
                            'shortname'       => new external_value(PARAM_RAW, 'Group shortname for API only controlled groups', VALUE_OPTIONAL),
                            'description'     => new external_value(PARAM_NOTAGS, 'Group description'),
                            'institution'     => new external_value(PARAM_TEXT, 'Mahara institution - required for API controlled groups', VALUE_OPTIONAL),
                            'grouptype'       => new external_value(PARAM_ALPHANUMEXT, 'Group type: '.implode(',', $group_types)),
                            'jointype'        => new external_value(PARAM_ALPHANUMEXT, 'Join type - these are specific to group type - the complete set are: open, invite, request or controlled', VALUE_DEFAULT, 'controlled'),
                            'category'        => new external_value(PARAM_TEXT, 'Group category - the title of an existing group category'),
                            'public'          => new external_value(PARAM_INTEGER, 'Boolean 1/0 public group', VALUE_DEFAULT, '0'),
                            'usersautoadded'  => new external_value(PARAM_INTEGER, 'Boolean 1/0 for auto-adding users', VALUE_DEFAULT, '0'),
//                            'viewnotify'      => new external_value(PARAM_INTEGER, 'Boolean 1/0 for Shared page notifications', VALUE_DEFAULT, '0'),
                            'members'         => new external_multiple_structure(
                                                            new external_single_structure(
                                                                array(
                                                                    'id' => new external_value(PARAM_NUMBER, 'member user Id', VALUE_OPTIONAL),
                                                                    'username' => new external_value(PARAM_RAW, 'member username', VALUE_OPTIONAL),
                                                                    'role' => new external_value(PARAM_ALPHANUMEXT, 'member role: admin, ')
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
        $params = self::validate_parameters(self::create_groups_parameters(), array('groups'=>$groups));

        db_begin();
        $groupids = array();
        foreach ($params['groups'] as $group) {
            // Make sure that the group doesn't already exist
            if (!empty($group['name'])) {
                if (get_record('group', 'name', $group['name'])) { // don't checked deleted as the real function doesn't
                    throw new invalid_parameter_exception('Group already exists: '.$group['name']);
                }
            }
            // special API controlled group creations
            else if (isset($group['shortname']) && strlen($group['shortname'])) {
                // check the institution is allowed
                if (isset($group['institution']) && strlen($group['institution'])) {
                    if ($WEBSERVICE_INSTITUTION != $group['institution']) {
                        throw new invalid_parameter_exception('create_groups: access denied for institution: '.$group['institution'].' on group: '.$group['name']);
                    }
                    if (!$USER->can_edit_institution($group['institution'])) {
                        throw new invalid_parameter_exception('create_groups: access denied for institution: '.$group['institution'].' on group: '.$group['name']);
                    }
                }
                else {
                    throw new invalid_parameter_exception('create_groups: institution must be set on group: '.$group['name'].'/'.$group['shortname']);
                }
                // does the group exist?
                if (get_record('group', 'shortname', $group['shortname'], 'institution', $group['institution'])) {
                    throw new invalid_parameter_exception('Group already exists: '.$group['shortname']);
                }
            }
            else {
                throw new invalid_parameter_exception('create_groups: no name or shortname specified');
            }

            // convert the category
            $groupcategory = get_record('group_category','title', $group['category']);
            if (empty($groupcategory)) {
                // create the category on the fly
                $displayorders = get_records_array('group_category', '', '', '', 'displayorder');
                $max = 0;
                if ($displayorders) {
                    foreach ($displayorders as $r) {
                        $max = $r->displayorder >= $max ? $r->displayorder + 1 : $max;
                    }
                }
                $data = new StdClass;
                $data->title = $group['category'];
                $data->displayorder = $max;
                insert_record('group_category', $data);
                $groupcategory = get_record('group_category','title', $group['category']);
                if (empty($groupcategory)) {
                    throw new invalid_parameter_exception('create_groups: category invalid: '.$group['category']);
                }
            }
            $group['category'] = $groupcategory->id;

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
                    throw new invalid_parameter_exception('create_groups: no username or id for member - group: '.$group['name']);
                }
                if (empty($dbuser)) {
                    throw new invalid_parameter_exception('create_groups: invalid user: '.$member['id'].'/'.$member['username'].' - group: '.$group['name']);
                }

                // Make sure auth is valid
                if (!$authinstance = get_record('auth_instance', 'id', $dbuser->authinstance)) {
                    throw new invalid_parameter_exception('Invalid authentication type: '.$dbuser->authinstance);
                }

                // check the institution is allowed
                // basic check authorisation to edit for the current institution of the user
                if (!$USER->can_edit_institution($authinstance->institution)) {
                    throw new invalid_parameter_exception('create_groups: access denied for institution: '.$authinstance->institution.' on user: '.$dbuser->username);
                }

                $members[$dbuser->id]= $member['role'];
            }

            // create the group
            $id = group_create(array(
                'shortname'      => (isset($group['shortname']) ? $group['shortname'] : null),
                'name'           => (isset($group['name']) ? $group['name'] : null),
                'description'    => $group['description'],
                'institution'    => (isset($group['institution']) ? $group['institution'] : null),
                'grouptype'      => $group['grouptype'],
                'category'       => $group['category'],
                'jointype'       => $group['jointype'],
                'public'         => intval($group['public']),
                'usersautoadded' => intval($group['usersautoadded']),
                'members'        => $members,
//                'viewnotify'     => intval($group['viewnotify']),
            ));

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
                    throw new invalid_parameter_exception('update_groups: Group does not exist: '.$group['id']);
                }
            }
            else if (!empty($group['shortname'])) {
                if (empty($group['institution'])) {
                    throw new invalid_parameter_exception('update_groups: institution must be set for: '.$group['shortname']);
                }
                if (!$dbgroup = get_record('group', 'shortname', $group['shortname'], 'institution', $group['institution'], 'deleted', 0)) {
                    throw new invalid_parameter_exception('update_groups: Group does not exist: '.$group['shortname']);
                }
            }
            else {
                throw new invalid_parameter_exception('update_groups: no group specified');
            }

            // are we allowed to delete for this institution
            if ($WEBSERVICE_INSTITUTION != $dbgroup->institution) {
                throw new invalid_parameter_exception('create_groups: access denied for institution: '.$group['institution'].' on group: '.$group['name']);
            }
            if (!$USER->can_edit_institution($dbgroup->institution)) {
                throw new invalid_parameter_exception('create_groups: access denied for institution: '.$group['institution'].' on group: '.$group['shortname']);
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
                            'grouptype'       => new external_value(PARAM_ALPHANUMEXT, 'Group type: '.implode(',', $group_types)),
                            'jointype'        => new external_value(PARAM_ALPHANUMEXT, 'Join type - these are specific to group type - the complete set are: open, invite, request or controlled', VALUE_DEFAULT, 'controlled'),
                            'category'        => new external_value(PARAM_TEXT, 'Group category - the title of an existing group category'),
                            'public'          => new external_value(PARAM_INTEGER, 'Boolean 1/0 public group', VALUE_DEFAULT, '0'),
                            'usersautoadded'  => new external_value(PARAM_INTEGER, 'Boolean 1/0 for auto-adding users', VALUE_DEFAULT, '0'),
                            'viewnotify'      => new external_value(PARAM_INTEGER, 'Boolean 1/0 for Shared page notifications', VALUE_DEFAULT, '0'),
                            'members'         => new external_multiple_structure(
                                                            new external_single_structure(
                                                                array(
                                                                    'id' => new external_value(PARAM_NUMBER, 'member user Id', VALUE_OPTIONAL),
                                                                    'username' => new external_value(PARAM_RAW, 'member username', VALUE_OPTIONAL),
                                                                    'role' => new external_value(PARAM_ALPHANUMEXT, 'member role: admin, ')
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
                    throw new invalid_parameter_exception('update_groups: Group does not exist: '.$group['id']);
                }
            }
//            else if (!empty($group['name'])) {
//                if (!$dbgroup = get_record('group', 'name', $group['name'], 'deleted', 0)) {
//                    throw new invalid_parameter_exception('update_groups: Group does not exist: '.$group['name']);
//                }
//            }
            else if (!empty($group['shortname'])) {
                if (empty($group['institution'])) {
                    throw new invalid_parameter_exception('update_groups: institution must be set for: '.$group['shortname']);
                }
                if (!$dbgroup = get_record('group', 'shortname', $group['shortname'], 'deleted', 0)) {
                    throw new invalid_parameter_exception('update_groups: Group does not exist: '.$group['shortname']);
                }
            }
            else {
                throw new invalid_parameter_exception('update_groups: no group specified');
            }

            // are we allowed to delete for this institution
            if ($WEBSERVICE_INSTITUTION != $dbgroup->institution) {
                throw new invalid_parameter_exception('update_groups: access denied for institution: '.$group['institution'].' on group: '.$group['name']);
            }
            if (!$USER->can_edit_institution($dbgroup->institution)) {
                throw new invalid_parameter_exception('update_groups: access denied for institution: '.$group['institution'].' on group: '.$group['shortname']);
            }

            // convert the category
            $groupcategory = get_record('group_category','title', $group['category']);
            if (empty($groupcategory)) {
                throw new invalid_parameter_exception('create_groups: category invalid: '.$group['category']);
            }
            $group['category'] = $groupcategory->id;

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
                    throw new invalid_parameter_exception('update_groups: no username or id for member - group: '.$group['name']);
                }
                if (empty($dbuser)) {
                    throw new invalid_parameter_exception('update_groups: invalid user: '.$member['id'].'/'.$member['username'].' - group: '.$group['name']);
                }

                // Make sure auth is valid
                if (!$authinstance = get_record('auth_instance', 'id', $dbuser->authinstance)) {
                    throw new invalid_parameter_exception('update_groups: Invalid authentication type: '.$dbuser->authinstance);
                }

                // check the institution is allowed
                // basic check authorisation to edit for the current institution of the user
                if (!$USER->can_edit_institution($authinstance->institution)) {
                    throw new invalid_parameter_exception('update_groups: access denied for institution: '.$authinstance->institution.' on user: '.$dbuser->username);
                }

                $members[$dbuser->id] = $member['role'];
            }

            // build up the changes
            $newvalues = (object) array( // not allowed to change these
                'id'             => $dbgroup->id,
                'shortname'      => $dbgroup->shortname,
                'institution'    => $dbgroup->institution,
                );
            foreach (array('name', 'description', 'grouptype', 'category',
                            'jointype', 'usersautoadded', 'public', 'viewnotify') as $attr) {
                if (isset($group[$attr])) {
                    $newvalues->{$attr} = ($dbgroup->{$attr} == $group[$attr] ? $dbgroup->{$attr} : $group[$attr]);
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
        global $CFG, $WEBSERVICE_INSTITUTION, $USER;

        $params = self::validate_parameters(self::get_groups_by_id_parameters(),
                array('groups' => $groups));

        // if this is a get all users - then lets get them all

        if (empty($params['groups'])) {
            $params['groups'] = array();
            $dbgroups =get_records_sql_array('SELECT * FROM {group} WHERE institution = ? AND deleted = 0', array($WEBSERVICE_INSTITUTION));
            foreach ($dbgroups as $dbgroup) {
                $params['groups'][] = array('id' => $dbgroup->id);
            }
        }

        // now process the ids
        $groups = array();
        foreach ($params['groups'] as $group) {
            // Make sure that the group doesn't already exist
            if (!empty($group['id'])) {
                if (!$dbgroup = get_record('group', 'id', $group['id'], 'deleted', 0)) {
                    throw new invalid_parameter_exception('update_groups: Group does not exist: '.$group['id']);
                }
            }
            else if (!empty($group['shortname'])) {
                if (empty($group['institution'])) {
                    throw new invalid_parameter_exception('update_groups: institution must be set for: '.$group['shortname']);
                }
                if (!$dbgroup = get_record('group', 'shortname', $group['shortname'], 'institution', $group['institution'], 'deleted', 0)) {
                    throw new invalid_parameter_exception('update_groups: Group does not exist: '.$group['shortname']);
                }
            }
            else {
                throw new invalid_parameter_exception('update_groups: no group specified');
            }

            // must have access to the related institution
            if ($WEBSERVICE_INSTITUTION != $dbgroup->institution) {
                throw new invalid_parameter_exception('get_group: access denied('.$WEBSERVICE_INSTITUTION.') for institution: '.$dbgroup->institution.' on group: '.$dbgroup->shortname);
            }
            if (!$USER->can_edit_institution($dbgroup->institution)) {
                throw new invalid_parameter_exception('get_group: access denied(2) for institution: '.$dbgroup->institution.' on group: '.$dbgroup->shortname);
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
                        'jointype'       => $dbgroup->jointype,
                        'category'       => $dbgroup->category,
                        'public'         => $dbgroup->public,
                        'usersautoadded' => $dbgroup->usersautoadded,
                        'viewnotify'     => $dbgroup->viewnotify,
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
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id'              => new external_value(PARAM_NUMBER, 'ID of the group'),
                    'name'            => new external_value(PARAM_RAW, 'Group name'),
                    'shortname'       => new external_value(PARAM_RAW, 'Group shortname for API only controlled groups'),
                    'description'     => new external_value(PARAM_NOTAGS, 'Group description'),
                    'institution'     => new external_value(PARAM_TEXT, 'Mahara institution - required for API controlled groups'),
                    'grouptype'       => new external_value(PARAM_ALPHANUMEXT, 'Group type: '.implode(',', $group_types)),
                    'jointype'        => new external_value(PARAM_ALPHANUMEXT, 'Join type - these are specific to group type - the complete set are: open, invite, request or controlled'),
                    'category'        => new external_value(PARAM_ALPHANUMEXT, 'Group category - the title of an existing group category'),
                    'public'          => new external_value(PARAM_INTEGER, 'Boolean 1/0 public group'),
                    'usersautoadded'  => new external_value(PARAM_INTEGER, 'Boolean 1/0 for auto-adding users'),
                    'viewnotify'      => new external_value(PARAM_INTEGER, 'Boolean 1/0 for Shared page notifications'),
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
