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
require_once($CFG->docroot.'/lib/institution.php');
require_once($CFG->docroot.'/lib/searchlib.php');

class mahara_institution_external extends external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function add_members_parameters() {
        return new external_function_parameters(
            array(
                'institution'     => new external_value(PARAM_TEXT, 'Mahara institution'),
                'userids'         => new external_multiple_structure(new external_value(PARAM_INT, 'user ID')),
            )
        );
    }

    /**
     * Add one or more users
     *
     * @param array $userids
     */
    public static function add_members($institution, $userids) {
        global $USER, $WEBSERVICE_INSTITUTION;

        $params = array('institution' => $institution, 'userids' => $userids);
        $params = self::validate_parameters(self::add_members_parameters(), $params);

        if (!$USER->get('admin') && !$USER->is_institutional_admin()) {
            throw new AccessDeniedException("Institution::add_members: access denied");
        }
        // check the institution is allowed
        if (!$USER->can_edit_institution($params['institution'])) {
            throw new invalid_parameter_exception('add_members: access denied for institution: '.$params['institution']);
        }
        db_begin();
        foreach ($params['userids'] as $userid) {
            $user = get_record('usr', 'id', $userid, 'deleted', 0);
            if (empty($user)) {
                throw new invalid_parameter_exception('add_members: invalid user id: '.$userid);
            }

            // Make sure auth is valid
            if (!$authinstance = get_record('auth_instance', 'id', $user->authinstance)) {
                throw new invalid_parameter_exception('Invalid authentication type: '.$user->authinstance);
            }
            // check the institution is allowed
            // basic check authorisation to edit for the current institution
            if (!$USER->can_edit_institution($authinstance->institution)) {
                throw new invalid_parameter_exception('add_members: access denied for institution: '.$authinstance->institution.' on user: '.$userid);
            }
        }
        $institution = new Institution($params['institution']);
        $maxusers = $institution->maxuseraccounts;
        if (!empty($maxusers)) {
            $members = $institution->countMembers();
            if ($members + count($params['userids']) > $maxusers) {
                throw new AccessDeniedException("Institution::add_members: ".get_string('institutionuserserrortoomanyinvites', 'admin'));
            }
        }
        $institution->add_members($params['userids']);
        db_commit();

        return null;
    }

   /**
     * Returns description of method result value
     * @return external_description
     */
    public static function add_members_returns() {
        return null;
    }


    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function invite_members_parameters() {
        return new external_function_parameters(
            array(
                'institution'     => new external_value(PARAM_TEXT, 'Mahara institution'),
                'userids'         => new external_multiple_structure(new external_value(PARAM_INT, 'user ID')),
            )
        );
    }

    /**
     * Invite one or more users
     *
     * @param array $userids
     */
    public static function invite_members($institution, $userids) {
        global $USER, $WEBSERVICE_INSTITUTION;

        $params = array('institution' => $institution, 'userids' => $userids);
        $params = self::validate_parameters(self::invite_members_parameters(), $params);

        if (!$USER->get('admin') && !$USER->is_institutional_admin()) {
            throw new AccessDeniedException("Institution::invite_members: access denied");
        }
        // check the institution is allowed
        if (!$USER->can_edit_institution($params['institution'])) {
            throw new invalid_parameter_exception('invite_members: access denied for institution: '.$params['institution']);
        }
        db_begin();
        foreach ($params['userids'] as $userid) {
            $user = get_record('usr', 'id', $userid, 'deleted', 0);
            if (empty($user)) {
                throw new invalid_parameter_exception('invite_members: invalid user id: '.$userid);
            }

            // Make sure auth is valid
            if (!$authinstance = get_record('auth_instance', 'id', $user->authinstance)) {
                throw new invalid_parameter_exception('invite_members: Invalid authentication type: '.$user->authinstance);
            }
            // check the institution is allowed
            // basic check authorisation to edit for the current institution
            if (!$USER->can_edit_institution($authinstance->institution)) {
                throw new invalid_parameter_exception('invite_members: access denied for institution: '.$authinstance->institution.' on user: '.$userid);
            }
        }
        $institution = new Institution($params['institution']);
        $maxusers = $institution->maxuseraccounts;
        if (!empty($maxusers)) {
            if ($members + $institution->countInvites() + count($params['userids']) > $maxusers) {
                throw new AccessDeniedException("Institution::invite_members: ".get_string('institutionuserserrortoomanyinvites', 'admin'));
            }
        }

        $institution->invite_users($params['userids']);
        db_commit();

        return null;
    }

   /**
     * Returns description of method result value
     * @return external_description
     */
    public static function invite_members_returns() {
        return null;
    }


    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function remove_members_parameters() {
        return new external_function_parameters(
            array(
                'institution'     => new external_value(PARAM_TEXT, 'Mahara institution'),
                'userids'         => new external_multiple_structure(new external_value(PARAM_INT, 'user ID')),
            )
        );
    }

    /**
     * remove one or more users
     *
     * @param array $userids
     */
    public static function remove_members($institution, $userids) {
        global $USER, $WEBSERVICE_INSTITUTION;

        $params = array('institution' => $institution, 'userids' => $userids);
        $params = self::validate_parameters(self::remove_members_parameters(), $params);

        if (!$USER->get('admin') && !$USER->is_institutional_admin()) {
            throw new AccessDeniedException("Institution::remove_members: access denied");
        }
            // check the institution is allowed
        if (!$USER->can_edit_institution($params['institution'])) {
            throw new invalid_parameter_exception('remove_members: access denied for institution: '.$params['institution']);
        }
        db_begin();
        foreach ($params['userids'] as $userid) {
            $user = get_record('usr', 'id', $userid, 'deleted', 0);
            if (empty($user)) {
                throw new invalid_parameter_exception('remove_members: invalid user id: '.$userid);
            }

            // Make sure auth is valid
            if (!$authinstance = get_record('auth_instance', 'id', $user->authinstance)) {
                throw new invalid_parameter_exception('remove_members: Invalid authentication type: '.$user->authinstance);
            }

            // check the institution is allowed
            // basic check authorisation to edit for the current institution
            if (!$USER->can_edit_institution($authinstance->institution)) {
                throw new invalid_parameter_exception('remove_members: access denied for institution: '.$authinstance->institution.' on user: '.$userid);
            }
        }
        $institution = new Institution($params['institution']);
        $institution->removeMembers($params['userids']);
        db_commit();

        return null;
    }

   /**
     * Returns description of method result value
     * @return external_description
     */
    public static function remove_members_returns() {
        return null;
    }


    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function decline_members_parameters() {
        return new external_function_parameters(
            array(
                'institution'     => new external_value(PARAM_TEXT, 'Mahara institution'),
                'userids'         => new external_multiple_structure(new external_value(PARAM_INT, 'user ID')),
            )
        );
    }

    /**
     * decline one or more users
     *
     * @param array $userids
     */
    public static function decline_members($institution, $userids) {
        global $USER, $WEBSERVICE_INSTITUTION;

        $params = array('institution' => $institution, 'userids' => $userids);
        $params = self::validate_parameters(self::decline_members_parameters(), $params);

        if (!$USER->get('admin') && !$USER->is_institutional_admin()) {
            throw new AccessDeniedException("Institution::decline_members: access denied");
        }
            // check the institution is allowed
        if (!$USER->can_edit_institution($params['institution'])) {
            throw new invalid_parameter_exception('decline_members: access denied for institution: '.$params['institution']);
        }
        db_begin();
        foreach ($params['userids'] as $userid) {
            $user = get_record('usr', 'id', $userid, 'deleted', 0);
            if (empty($user)) {
                throw new invalid_parameter_exception('decline_members: invalid user id: '.$userid);
            }

            // Make sure auth is valid
            if (!$authinstance = get_record('auth_instance', 'id', $user->authinstance)) {
                throw new invalid_parameter_exception('decline_members: Invalid authentication type: '.$user->authinstance);
            }

            // check the institution is allowed
            // basic check authorisation to edit for the current institution
            if (!$USER->can_edit_institution($authinstance->institution)) {
                throw new invalid_parameter_exception('decline_members: access denied for institution: '.$authinstance->institution.' on user: '.$userid);
            }
        }
        $institution = new Institution($params['institution']);
        $institution->decline_requests($params['userids']);
        db_commit();

        return null;
    }

   /**
     * Returns description of method result value
     * @return external_description
     */
    public static function decline_members_returns() {
        return null;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_members_parameters() {

        return new external_function_parameters(
            array(
                'institution'     => new external_value(PARAM_TEXT, 'Mahara institution'),
            )
        );
    }

    /**
     * Get institution members
     *
     * @param array $groups  An array of groups to create.
     * @return array An array of arrays
     */
    public static function get_members($institution) {
        global $USER, $WEBSERVICE_INSTITUTION;

        // Do basic automatic PARAM checks on incoming data, using params description
        $params = self::validate_parameters(self::get_members_parameters(), array('institution'=>$institution));
        if (!$USER->get('admin') && !$USER->is_institutional_admin()) {
            throw new AccessDeniedException("Institution::get_members: access denied");
        }
            // check the institution is allowed
        if (!$USER->can_edit_institution($params['institution'])) {
            throw new invalid_parameter_exception('get_members: access denied for institution: '.$params['institution']);
        }
        $institution = new Institution($params['institution']);
        $data = institutional_admin_user_search('', $institution, 0);
        $users = array();

        if (!empty($data['data'])) {
            foreach ($data['data'] as $user) {
                $users[] = array('id'=> $user['id'], 'username'=>$user['username']);
            }
        }
        return $users;
    }

   /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_members_returns() {
        return new external_multiple_structure(
                new external_single_structure(
                        array(
                    'id'              => new external_value(PARAM_NUMBER, 'ID of the user'),
                    'username'        => new external_value(PARAM_RAW, 'Username policy is defined in Mahara security config'),
                        )
                )
        );
    }


    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_requests_parameters() {

        return new external_function_parameters(
            array(
                'institution'     => new external_value(PARAM_TEXT, 'Mahara institution'),
            )
        );
    }

    /**
     * Get institution requests
     *
     * @param array $groups  An array of groups to create.
     * @return array An array of arrays
     */
    public static function get_requests($institution) {
        global $USER, $WEBSERVICE_INSTITUTION;

        // Do basic automatic PARAM checks on incoming data, using params description
        $params = self::validate_parameters(self::get_members_parameters(), array('institution'=>$institution));
        if (!$USER->get('admin') && !$USER->is_institutional_admin()) {
            throw new AccessDeniedException("Institution::get_requests: access denied");
        }
            // check the institution is allowed
        if (!$USER->can_edit_institution($params['institution'])) {
            throw new invalid_parameter_exception('get_requests: access denied for institution: '.$params['institution']);
        }

        $users = array();
        $dbrequests = get_records_array('usr_institution_request', 'institution', $params['institution']);

        if (!empty($dbrequests)) {
            foreach ($dbrequests as $user) {
                $dbuser = get_record('usr', 'id', $user->usr);
                $users[] = array('id'=> $user->usr, 'username'=>$dbuser->username);
            }
        }
        return $users;
    }

   /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_requests_returns() {
        return new external_multiple_structure(
                new external_single_structure(
                        array(
                    'id'              => new external_value(PARAM_NUMBER, 'ID of the user'),
                    'username'        => new external_value(PARAM_RAW, 'Username policy is defined in Mahara security config'),
                        )
                )
        );
    }


}
