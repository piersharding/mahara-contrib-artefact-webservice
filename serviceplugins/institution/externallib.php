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

require_once(get_config('docroot') . '/lib/institution.php');
require_once(get_config('docroot') . '/lib/searchlib.php');
require_once(get_config('docroot') . '/lib/user.php');

global $WEBSERVICE_OAUTH_USER;

if ($WEBSERVICE_OAUTH_USER) {
    throw new Exception('Not enabled for OAuth');
}

/**
* Class container for core Mahara institution related API calls
*/
class mahara_institution_external extends external_api {

    /**
     * Check that a user exists
     *
     * @param array $user array('id' => .., 'username' => ..)
     * @return array() of user
     */
    private static function checkuser($user) {
        if (isset($user['id'])) {
            $id = $user['id'];
        }
        else if (isset($user['userid'])) {
            $id = $user['userid'];
        }
        else if (isset($user['username'])) {
            $dbuser = get_record('usr', 'username', $user['username']);
            if (empty($dbuser)) {
                throw new invalid_parameter_exception('Invalid username: ' . $user['username']);
            }
            $id = $dbuser->id;
        }
        else {
            throw new invalid_parameter_exception('Must have id, userid or username');
        }
        // now get the user
        if ($user = get_user($id)) {
            return $user;
        }
        else {
            throw new invalid_parameter_exception('Invalid user id: ' . $id);
        }
    }

    /**
     * parameter definition for input of add_members method
     *
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function add_members_parameters() {
        return new external_function_parameters(
            array(
                'institution'     => new external_value(PARAM_TEXT, 'Mahara institution'),
                'users' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id'              => new external_value(PARAM_NUMBER, 'ID of the favourites owner', VALUE_OPTIONAL),
                            'username'        => new external_value(PARAM_RAW, 'Username of the favourites owner', VALUE_OPTIONAL),
                            )
                        )
                    )
            )
        );
    }

    /**
     * Add one or more members to an institution
     *
     * @param array $users
     */
    public static function add_members($institution, $users) {
        global $USER, $WEBSERVICE_INSTITUTION;

        $params = array('institution' => $institution, 'users' => $users);
        $params = self::validate_parameters(self::add_members_parameters(), $params);

        if (!$USER->get('admin') && !$USER->is_institutional_admin()) {
            throw new AccessDeniedException("Institution::add_members: access denied");
        }
        // check the institution is allowed
        if (!$USER->can_edit_institution($params['institution'])) {
            throw new invalid_parameter_exception('add_members: access denied for institution: ' . $params['institution']);
        }
        db_begin();
        $userids = array();
        foreach ($params['users'] as $user) {
            $dbuser = self::checkuser($user);
            // Make sure auth is valid
            if (!$authinstance = get_record('auth_instance', 'id', $dbuser->authinstance)) {
                throw new invalid_parameter_exception('Invalid authentication type: ' . $dbuser->authinstance);
            }
            // check the institution is allowed
            // basic check authorisation to edit for the current institution
            if (!$USER->can_edit_institution($authinstance->institution)) {
                throw new invalid_parameter_exception('add_members: access denied for institution: ' . $authinstance->institution . ' on user: ' . $dbuser->id);
            }
            $userids[]= $dbuser->id;
        }
        $institution = new Institution($params['institution']);
        $maxusers = $institution->maxuseraccounts;
        if (!empty($maxusers)) {
            $members = $institution->countMembers();
            if ($members + count($userids) > $maxusers) {
                throw new AccessDeniedException("Institution::add_members: " . get_string('institutionuserserrortoomanyinvites', 'admin'));
            }
        }
        $institution->add_members($userids);
        db_commit();

        return null;
    }

   /**
    * parameter definition for output of add_members method
    *
     * Returns description of method result value
     * @return external_description
     */
    public static function add_members_returns() {
        return null;
    }

    /**
     * parameter definition for input of invite_members method
     *
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function invite_members_parameters() {
        return new external_function_parameters(
            array(
                'institution'     => new external_value(PARAM_TEXT, 'Mahara institution'),
                'users' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id'              => new external_value(PARAM_NUMBER, 'ID of the favourites owner', VALUE_OPTIONAL),
                            'username'        => new external_value(PARAM_RAW, 'Username of the favourites owner', VALUE_OPTIONAL),
                            )
                        )
                    )
            )
        );
    }

    /**
     * Invite one or more users to an institution
     *
     * @param array $users
     */
    public static function invite_members($institution, $users) {
        global $USER, $WEBSERVICE_INSTITUTION;

        $params = array('institution' => $institution, 'users' => $users);
        $params = self::validate_parameters(self::invite_members_parameters(), $params);

        if (!$USER->get('admin') && !$USER->is_institutional_admin()) {
            throw new AccessDeniedException("Institution::invite_members: access denied");
        }
        // check the institution is allowed
        if (!$USER->can_edit_institution($params['institution'])) {
            throw new invalid_parameter_exception('invite_members: access denied for institution: ' . $params['institution']);
        }
        db_begin();
        $userids = array();
        foreach ($params['users'] as $user) {
            $dbuser = self::checkuser($user);

            // Make sure auth is valid
            if (!$authinstance = get_record('auth_instance', 'id', $dbuser->authinstance)) {
                throw new invalid_parameter_exception('invite_members: Invalid authentication type: ' . $dbuser->authinstance);
            }
            // check the institution is allowed
            // basic check authorisation to edit for the current institution
            if (!$USER->can_edit_institution($authinstance->institution)) {
                throw new invalid_parameter_exception('invite_members: access denied for institution: ' . $authinstance->institution . ' on user: ' . $dbuser->id);
            }
            $userids[]= $dbuser->id;
        }
        $institution = new Institution($params['institution']);
        $maxusers = $institution->maxuseraccounts;
        if (!empty($maxusers)) {
            if ($members + $institution->countInvites() + count($userids) > $maxusers) {
                throw new AccessDeniedException("Institution::invite_members: " . get_string('institutionuserserrortoomanyinvites', 'admin'));
            }
        }

        $institution->invite_users($userids);
        db_commit();

        return null;
    }

   /**
    * parameter definition for output of invite_members method
    *
    * Returns description of method result value
    * @return external_description
    */
    public static function invite_members_returns() {
        return null;
    }

    /**
     * parameter definition for input of remove_members method
     *
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function remove_members_parameters() {
        return new external_function_parameters(
            array(
                'institution'     => new external_value(PARAM_TEXT, 'Mahara institution'),
                'users' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id'              => new external_value(PARAM_NUMBER, 'ID of the favourites owner', VALUE_OPTIONAL),
                            'username'        => new external_value(PARAM_RAW, 'Username of the favourites owner', VALUE_OPTIONAL),
                            )
                        )
                    )
            )
        );
    }

    /**
     * remove one or more users from an institution
     *
     * @param array $users
     */
    public static function remove_members($institution, $users) {
        global $USER, $WEBSERVICE_INSTITUTION;

        $params = array('institution' => $institution, 'users' => $users);
        $params = self::validate_parameters(self::remove_members_parameters(), $params);

        if (!$USER->get('admin') && !$USER->is_institutional_admin()) {
            throw new AccessDeniedException("Institution::remove_members: access denied");
        }
            // check the institution is allowed
        if (!$USER->can_edit_institution($params['institution'])) {
            throw new invalid_parameter_exception('remove_members: access denied for institution: ' . $params['institution']);
        }
        db_begin();
        $userids = array();
        foreach ($params['users'] as $user) {
            $dbuser = self::checkuser($user);

            // Make sure auth is valid
            if (!$authinstance = get_record('auth_instance', 'id', $dbuser->authinstance)) {
                throw new invalid_parameter_exception('remove_members: Invalid authentication type: ' . $dbuser->authinstance);
            }

            // check the institution is allowed
            // basic check authorisation to edit for the current institution
            if (!$USER->can_edit_institution($authinstance->institution)) {
                throw new invalid_parameter_exception('remove_members: access denied for institution: ' . $authinstance->institution . ' on user: ' . $dbuser->id);
            }
            $userids[]= $dbuser->id;
        }
        $institution = new Institution($params['institution']);
        $institution->removeMembers($userids);
        db_commit();

        return null;
    }

   /**
    * parameter definition for output of remove_members method
    *
    * Returns description of method result value
    * @return external_description
    */
    public static function remove_members_returns() {
        return null;
    }

    /**
     * parameter definition for input of decline_members method
     *
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function decline_members_parameters() {
        return new external_function_parameters(
            array(
                'institution'     => new external_value(PARAM_TEXT, 'Mahara institution'),
                'users' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id'              => new external_value(PARAM_NUMBER, 'ID of the favourites owner', VALUE_OPTIONAL),
                            'username'        => new external_value(PARAM_RAW, 'Username of the favourites owner', VALUE_OPTIONAL),
                            )
                        )
                    )
            )
        );
    }

    /**
     * decline one or more users request for membership to an institution
     *
     * @param array $users
     */
    public static function decline_members($institution, $users) {
        global $USER, $WEBSERVICE_INSTITUTION;

        $params = array('institution' => $institution, 'users' => $users);
        $params = self::validate_parameters(self::decline_members_parameters(), $params);

        if (!$USER->get('admin') && !$USER->is_institutional_admin()) {
            throw new AccessDeniedException("Institution::decline_members: access denied");
        }
            // check the institution is allowed
        if (!$USER->can_edit_institution($params['institution'])) {
            throw new invalid_parameter_exception('decline_members: access denied for institution: ' . $params['institution']);
        }
        db_begin();
        $userids = array();
        foreach ($params['users'] as $user) {
            $dbuser = self::checkuser($user);

            // Make sure auth is valid
            if (!$authinstance = get_record('auth_instance', 'id', $dbuser->authinstance)) {
                throw new invalid_parameter_exception('decline_members: Invalid authentication type: ' . $dbuser->authinstance);
            }

            // check the institution is allowed
            // basic check authorisation to edit for the current institution
            if (!$USER->can_edit_institution($authinstance->institution)) {
                throw new invalid_parameter_exception('decline_members: access denied for institution: ' . $authinstance->institution . ' on user: ' . $dbuser->id);
            }
            $userids[]= $dbuser->id;
        }
        $institution = new Institution($params['institution']);
        $institution->decline_requests($userids);
        db_commit();

        return null;
    }

   /**
    * parameter definition for output of decline_members method
    *
    * Returns description of method result value
    * @return external_description
    */
    public static function decline_members_returns() {
        return null;
    }

    /**
     * parameter definition for input of get_members method
     *
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
            throw new invalid_parameter_exception('get_members: access denied for institution: ' . $params['institution']);
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
    * parameter definition for output of get_members method
    *
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
     * parameter definition for input of get_requests method
     *
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
            throw new invalid_parameter_exception('get_requests: access denied for institution: ' . $params['institution']);
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
    * parameter definition for output of get_requests method
    *
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
