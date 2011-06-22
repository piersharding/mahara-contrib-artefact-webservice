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
require_once($CFG->docroot.'/lib/user.php');
require_once('institution.php');


class mahara_user_external extends external_api {

    static private $ALLOWEDKEYS = array(
            'remoteuser',
            'introduction',
            'officialwebsite',
            'personalwebsite',
            'blogaddress',
            'address',
            'town',
            'city',
            'country',
            'homenumber',
            'businessnumber',
            'mobilenumber',
            'faxnumber',
            'icqnumber',
            'msnnumber',
            'aimscreenname',
            'yahoochat',
            'skypeusername',
            'jabberusername',
            'occupation',
            'industry',
        );

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function create_users_parameters() {

        return new external_function_parameters(
            array(
                'users' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'username'        => new external_value(PARAM_RAW, 'Username policy is defined in Mahara security config'),
                            'password'        => new external_value(PARAM_RAW, 'Plain text password consisting of any characters'),
                            'firstname'       => new external_value(PARAM_NOTAGS, 'The first name(s) of the user'),
                            'lastname'        => new external_value(PARAM_NOTAGS, 'The family name of the user'),
                            'email'           => new external_value(PARAM_EMAIL, 'A valid and unique email address'),
                            'institution'     => new external_value(PARAM_SAFEDIR, 'Mahara institution', VALUE_DEFAULT, 'mahara', NULL_NOT_ALLOWED),
                            'auth'            => new external_value(PARAM_SAFEDIR, 'Auth plugins include manual, ldap, imap, etc', VALUE_DEFAULT, 'internal', NULL_NOT_ALLOWED),
                            'quota'           => new external_value(PARAM_INTEGER, 'Option storage quota', VALUE_OPTIONAL),
                            'forcepasswordchange' => new external_value(PARAM_INTEGER, 'Boolean 1/0 for forcing password change on first login', VALUE_DEFAULT, '0'),
                            'studentid'       => new external_value(PARAM_RAW, 'An arbitrary ID code number for the student', VALUE_DEFAULT, ''),
                            'remoteuser'      => new external_value(PARAM_RAW, 'Remote user Id', VALUE_DEFAULT, ''),
                            'preferredname'   => new external_value(PARAM_TEXT, 'Userpreferred name', VALUE_OPTIONAL),
                            'address'         => new external_value(PARAM_RAW, 'Introduction text', VALUE_OPTIONAL),
                            'town'            => new external_value(PARAM_NOTAGS, 'Home town of the user', VALUE_OPTIONAL),
                            'city'            => new external_value(PARAM_NOTAGS, 'Home city of the user', VALUE_OPTIONAL),
                            'country'         => new external_value(PARAM_ALPHA, 'Home country code of the user, such as NZ', VALUE_OPTIONAL),
                            'homenumber'      => new external_value(PARAM_RAW, 'Home phone number', VALUE_OPTIONAL),
                            'businessnumber'  => new external_value(PARAM_RAW, 'business phone number', VALUE_OPTIONAL),
                            'mobilenumber'    => new external_value(PARAM_RAW, 'mobile phone number', VALUE_OPTIONAL),
                            'faxnumber'       => new external_value(PARAM_RAW, 'fax number', VALUE_OPTIONAL),
                            'introduction'    => new external_value(PARAM_RAW, 'Introduction text', VALUE_OPTIONAL),
                            'officialwebsite' => new external_value(PARAM_RAW, 'Official user website', VALUE_OPTIONAL),
                            'personalwebsite' => new external_value(PARAM_RAW, 'Personal website', VALUE_OPTIONAL),
                            'blogaddress'     => new external_value(PARAM_RAW, 'Blog web address', VALUE_OPTIONAL),
                            'aimscreenname'   => new external_value(PARAM_ALPHANUMEXT, 'AIM screen name', VALUE_OPTIONAL),
                            'icqnumber'       => new external_value(PARAM_ALPHANUMEXT, 'ICQ Number', VALUE_OPTIONAL),
                            'msnnumber'       => new external_value(PARAM_ALPHANUMEXT, 'MSN Number', VALUE_OPTIONAL),
                            'yahoochat'       => new external_value(PARAM_ALPHANUMEXT, 'Yahoo chat', VALUE_OPTIONAL),
                            'skypeusername'   => new external_value(PARAM_ALPHANUMEXT, 'Skype username', VALUE_OPTIONAL),
                            'jabberusername'  => new external_value(PARAM_RAW, 'Jabber/XMPP username', VALUE_OPTIONAL),
                            'occupation'      => new external_value(PARAM_TEXT, 'Occupation', VALUE_OPTIONAL),
                            'industry'        => new external_value(PARAM_TEXT, 'Industry', VALUE_OPTIONAL),
                        )
                    )
                )
            )
        );
    }

    /**
     * Create one or more users
     *
     * @param array $users  An array of users to create.
     * @return array An array of arrays
     */
    public static function create_users($users) {
        global $USER, $WEBSERVICE_INSTITUTION;

        // Do basic automatic PARAM checks on incoming data, using params description
        // If any problems are found then exceptions are thrown with helpful error messages
        $params = self::validate_parameters(self::create_users_parameters(), array('users'=>$users));
        db_begin();
        $userids = array();
        foreach ($params['users'] as $user) {
            // Make sure that the username doesn't already exist
            if (get_record('usr', 'username', $user['username'])) {
                throw new invalid_parameter_exception('Username already exists: '.$user['username']);
            }

            // check the institution is allowed
            // basic check authorisation to edit for the current institution
            if (!$USER->can_edit_institution($user['institution'])) {
                throw new invalid_parameter_exception('create_users: access denied for institution: '.$user['institution']);
            }

            // Make sure auth is valid
            if (!$authinstance = get_record('auth_instance', 'institution', $user['institution'], 'authname', $user['auth'])) {
                throw new invalid_parameter_exception('Invalid authentication type: '.$user['institution'].'/'.$user['auth']);
            }

            $institution = new Institution($authinstance->institution);

            $maxusers = $institution->maxuseraccounts;
            if (!empty($maxusers)) {
                $members = count_records_sql('
                    SELECT COUNT(*) FROM {usr} u INNER JOIN {usr_institution} i ON u.id = i.usr
                    WHERE i.institution = ? AND u.deleted = 0', array($institution->name));
                if ($members + 1 > $maxusers) {
                    throw new invalid_parameter_exception('Institution exceeded max allowed: '.$institution->name);
                }
            }

            $new_user = new StdClass;
            $new_user->authinstance = $authinstance->id;
            $new_user->username     = $user['username'];
            $new_user->firstname    = $user['firstname'];
            $new_user->lastname     = $user['lastname'];
            $new_user->password     = $user['password'];
            $new_user->email        = $user['email'];
            if (isset($user['quota'])) {
                $new_user->quota        = $user['quota'];
            }
            if (isset($user['forcepasswordchange'])) {
                $new_user->passwordchange = (int)$user['forcepasswordchange'];
            }

            if (isset($user['studentid'])) {
                $new_user->studentid = $user['studentid'];
            }
            if (isset($user['preferredname'])) {
                $new_user->preferredname = $user['preferredname'];
            }

            $profilefields = new StdClass;
            $remoteuser = null;
            foreach (self::$ALLOWEDKEYS as $field) {
                if (isset($user[$field])) {
                    if ($field == 'remoteuser') {
                        $remoteuser = $user[$field];
                        continue;
                    }
                    $profilefields->{$field} = $user[$field];
                }
            }
            $new_user->id = create_user($new_user, $profilefields, $institution, $authinstance, $remoteuser);
            $addedusers[] = $new_user;
            $userids[] = array('id'=> $new_user->id, 'username'=>$user['username']);
        }

        // now sort out the passwords
        foreach ($addedusers as $user) {
            // Add salt and encrypt the pw, if the auth instance allows for it
            $userobj = new User();
            $userobj = $userobj->find_by_id($user->id);
            $authobj_tmp = AuthFactory::create($user->authinstance);
            if (method_exists($authobj_tmp, 'change_password')) {
                $authobj_tmp->change_password($userobj, $user->password, false);
            } else {
                $userobj->password = '';
                $userobj->salt = auth_get_random_salt();
                $userobj->commit();
            }
        }
        unset($authobj_tmp, $userobj);
        db_commit();

        return $userids;
    }

   /**
     * Returns description of method result value
     * @return external_description
     */
    public static function create_users_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id'       => new external_value(PARAM_INT, 'user id'),
                    'username' => new external_value(PARAM_RAW, 'user name'),
                )
            )
        );
    }


    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function delete_users_parameters() {
        return new external_function_parameters(
            array(
                'userids' => new external_multiple_structure(new external_value(PARAM_INT, 'user ID')),
            )
        );
    }


    /**
     * Delete one or more users
     *
     * @param array $userids
     */
    public static function delete_users($userids) {
        global $USER, $WEBSERVICE_INSTITUTION;
        require_once(get_config('docroot').'/artefact/lib.php');

        $params = self::validate_parameters(self::delete_users_parameters(), array('userids'=>$userids));

        error_log('delete_user: userids: '.var_export($params, true));
        db_begin();
        foreach ($params['userids'] as $userid) {
            $user = get_record('usr', 'id', $userid, 'deleted', 0);
            if (empty($user)) {
                throw new invalid_parameter_exception('delete_users: invalid user id: '.$userid);
            }

            // Make sure auth is valid
            if (!$authinstance = get_record('auth_instance', 'id', $user->authinstance)) {
                throw new invalid_parameter_exception('Invalid authentication type: '.$user->authinstance);
            }
            // check the institution is allowed
            // basic check authorisation to edit for the current institution
            if (!$USER->can_edit_institution($authinstance->institution)) {
                throw new invalid_parameter_exception('delete_users: access denied for institution: '.$authinstance->institution.' on user: '.$userid);
            }

            // must not allow deleting of admins or self!!!
            if ($user->admin) {
                throw new MaharaException('useradminodelete', 'error');
            }
            if ($USER->id == $user->id) {
                throw new MaharaException('usernotdeletederror', 'error');
            }
            delete_user($userid);
        }
        db_commit();

        return null;
    }

   /**
     * Returns description of method result value
     * @return external_description
     */
    public static function delete_users_returns() {
        return null;
    }


    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function update_users_parameters() {

       return new external_function_parameters(
            array(
                'users' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id'              => new external_value(PARAM_NUMBER, 'ID of the user'),
                            'username'        => new external_value(PARAM_RAW, 'Username policy is defined in Mahara security config', VALUE_OPTIONAL),
                            'password'        => new external_value(PARAM_RAW, 'Plain text password consisting of any characters', VALUE_OPTIONAL),
                            'salt'            => new external_value(PARAM_RAW, 'Set the password salt', VALUE_OPTIONAL),
                            'firstname'       => new external_value(PARAM_NOTAGS, 'The first name(s) of the user', VALUE_OPTIONAL),
                            'lastname'        => new external_value(PARAM_NOTAGS, 'The family name of the user', VALUE_OPTIONAL),
                            'email'           => new external_value(PARAM_EMAIL, 'A valid and unique email address', VALUE_OPTIONAL),
                            'institution'     => new external_value(PARAM_TEXT, 'Mahara institution', VALUE_OPTIONAL),
                            'auth'            => new external_value(PARAM_TEXT, 'Auth plugins include manual, ldap, imap, etc', VALUE_OPTIONAL),
                            'quota'           => new external_value(PARAM_INTEGER, 'Option storage quota', VALUE_OPTIONAL),
                            'forcepasswordchange' => new external_value(PARAM_INTEGER, 'Boolean 1/0 for forcing password change on first login', VALUE_OPTIONAL),
                            'studentid'       => new external_value(PARAM_RAW, 'An arbitrary ID code number for the student', VALUE_OPTIONAL),
                            'remoteuser'      => new external_value(PARAM_RAW, 'Remote user Id', VALUE_OPTIONAL),
                            'preferredname'   => new external_value(PARAM_TEXT, 'Userpreferred name', VALUE_OPTIONAL),
                            'address'         => new external_value(PARAM_RAW, 'Introduction text', VALUE_OPTIONAL),
                            'town'            => new external_value(PARAM_NOTAGS, 'Home town of the user', VALUE_OPTIONAL),
                            'city'            => new external_value(PARAM_NOTAGS, 'Home city of the user', VALUE_OPTIONAL),
                            'country'         => new external_value(PARAM_ALPHA, 'Home country code of the user, such as NZ', VALUE_OPTIONAL),
                            'homenumber'      => new external_value(PARAM_RAW, 'Home phone number', VALUE_OPTIONAL),
                            'businessnumber'  => new external_value(PARAM_RAW, 'business phone number', VALUE_OPTIONAL),
                            'mobilenumber'    => new external_value(PARAM_RAW, 'mobile phone number', VALUE_OPTIONAL),
                            'faxnumber'       => new external_value(PARAM_RAW, 'fax number', VALUE_OPTIONAL),
                            'introduction'    => new external_value(PARAM_RAW, 'Introduction text', VALUE_OPTIONAL),
                            'officialwebsite' => new external_value(PARAM_RAW, 'Official user website', VALUE_OPTIONAL),
                            'personalwebsite' => new external_value(PARAM_RAW, 'Personal website', VALUE_OPTIONAL),
                            'blogaddress'     => new external_value(PARAM_RAW, 'Blog web address', VALUE_OPTIONAL),
                            'aimscreenname'   => new external_value(PARAM_ALPHANUMEXT, 'AIM screen name', VALUE_OPTIONAL),
                            'icqnumber'       => new external_value(PARAM_ALPHANUMEXT, 'ICQ Number', VALUE_OPTIONAL),
                            'msnnumber'       => new external_value(PARAM_ALPHANUMEXT, 'MSN Number', VALUE_OPTIONAL),
                            'yahoochat'       => new external_value(PARAM_ALPHANUMEXT, 'Yahoo chat', VALUE_OPTIONAL),
                            'skypeusername'   => new external_value(PARAM_ALPHANUMEXT, 'Skype username', VALUE_OPTIONAL),
                            'jabberusername'  => new external_value(PARAM_RAW, 'Jabber/XMPP username', VALUE_OPTIONAL),
                            'occupation'      => new external_value(PARAM_TEXT, 'Occupation', VALUE_OPTIONAL),
                            'industry'        => new external_value(PARAM_TEXT, 'Industry', VALUE_OPTIONAL),
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
    public static function update_users($users) {
        global $USER, $WEBSERVICE_INSTITUTION;

        $params = self::validate_parameters(self::update_users_parameters(), array('users'=>$users));

        db_begin();
        foreach ($params['users'] as $user) {
            if (!empty($user['id'])) {
                $dbuser = get_record('usr', 'id', $user['id'], 'deleted', 0);
            }
            else if (!empty($user['username'])) {
                $dbuser = get_record('usr', 'username', $user['username'], 'deleted', 0);
            }
            else {
                throw new invalid_parameter_exception('update_users: no username or id ');
            }
            if (empty($dbuser)) {
                throw new invalid_parameter_exception('update_users: invalid user: '.$user['id'].'/'.$user['username']);
            }

            // Make sure auth is valid
            if (!$authinstance = get_record('auth_instance', 'id', $dbuser->authinstance)) {
                throw new invalid_parameter_exception('Invalid authentication type: '.$dbuser->authinstance);
            }
            // check the institution is allowed
            // basic check authorisation to edit for the current institution
            if (!$USER->can_edit_institution($authinstance->institution)) {
                throw new invalid_parameter_exception('update_users: access denied for institution: '.$authinstance->institution.' on user: '.$dbuser->id);
            }

            $updated_user = $dbuser;
            $updated_user->id = $dbuser->id;
            foreach (array('username', 'firstname', 'lastname', 'email', 'quota', 'studentid', 'preferredname', 'password') as $field) {
                if (isset($user[$field])) {
                    $updated_user->{$field} = $user[$field];
                }
            }
            if (isset($user['forcepasswordchange'])) {
                $updated_user->passwordchange = (int)$user['forcepasswordchange'];
            }

            $profilefields = new StdClass;
            $remoteuser = null;
            foreach (self::$ALLOWEDKEYS as $field) {
                if (isset($user[$field])) {
                    if ($field == 'remoteuser') {
                        $remoteuser = $user[$field];
                        continue;
                    }
                    $profilefields->{$field} = $user[$field];
                }
            }
            update_user($updated_user, $profilefields, $remoteuser);
        }
        db_commit();

        return null;
    }

   /**
     * Returns description of method result value
     * @return external_description
     */
    public static function update_users_returns() {
        return null;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_users_by_id_parameters() {
        return new external_function_parameters(
                array(
                    'userids' => new external_multiple_structure(new external_value(PARAM_INT, 'user ID')),
                )
        );
    }

    /**
     * Get user information
     *
     * @param array $userids  array of user ids
     * @return array An array of arrays describing users
     */
    public static function get_users_by_id($userids) {
        global $CFG, $WEBSERVICE_INSTITUTION;

        $params = self::validate_parameters(self::get_users_by_id_parameters(),
                array('userids'=>$userids));

        // if this is a get all users - then lets get them all
        if (empty($params['userids'])) {
            $params['userids'] = array();
            $dbusers = get_records_sql_array('SELECT u.id AS id FROM {usr} u INNER JOIN {auth_instance} ai ON u.authinstance = ai.id WHERE u.deleted = 0 AND ai.institution = \''.$WEBSERVICE_INSTITUTION.'\'', null);
            foreach ($dbusers as $dbuser) {
                // eliminate bad uid
                if ($dbuser->id == 0) {
                    continue;
                }
                $params['userids'][] = $dbuser->id;
            }
        }

        //TODO: check if there is any performance issue: we do one DB request to retrieve
        //  all user, then for each user the profile_load_data does at least two DB requests
        $users = array();
        foreach ($params['userids'] as $userid) {
            if ($user = get_user($userid)) {
                $users[]= $user;
            }
            else {
                throw new invalid_parameter_exception('Invalid user id: '.$userid);
            }
        }
        $result = array();
        foreach ($users as $user) {
            if (empty($user->deleted)) {
                // check the institution
                $auth_instance = get_record('auth_instance', 'id', $user->authinstance);
                if (empty($auth_instance) || $WEBSERVICE_INSTITUTION != $auth_instance->institution) {
                    throw new invalid_parameter_exception('Not authorised for access to user id: '.$userid);
                }

                $userarray = array();
               //we want to return an array not an object
                /// now we transfer all profile_field_xxx into the customfields
                // external_multiple_structure required by description
                $userarray['id'] = $user->id;
                $userarray['username'] = $user->username;
                $userarray['firstname'] = $user->firstname;
                $userarray['lastname'] = $user->lastname;
                $userarray['email'] = $user->email;
                $userarray['auth'] = $auth_instance->authname;
                $userarray['studentid'] = $user->studentid;
                $userarray['preferredname'] = $user->preferredname;
                foreach (self::$ALLOWEDKEYS as $field) {
                    $userarray[$field] = (isset($user->{$field}) ? $user->{$field} : '');
                }
                $userarray['institution'] = $auth_instance->institution;
                $result[] = $userarray;
            }
        }

        return $result;
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_users_by_id_returns() {
        return new external_multiple_structure(
                new external_single_structure(
                        array(
                    'id'              => new external_value(PARAM_NUMBER, 'ID of the user'),
                    'username'        => new external_value(PARAM_RAW, 'Username policy is defined in Mahara security config'),
                    'firstname'       => new external_value(PARAM_NOTAGS, 'The first name(s) of the user'),
                    'lastname'        => new external_value(PARAM_NOTAGS, 'The family name of the user'),
                    'email'           => new external_value(PARAM_TEXT, 'An email address - allow email as root@localhost'),
                    'auth'            => new external_value(PARAM_SAFEDIR, 'Auth plugins include manual, ldap, imap, etc'),
                    'studentid'       => new external_value(PARAM_RAW, 'An arbitrary ID code number perhaps from the institution'),
                    'institution'     => new external_value(PARAM_SAFEDIR, 'Mahara institution'),
                    'preferredname'   => new external_value(PARAM_RAW, 'User preferred name'),
                    'introduction'    => new external_value(PARAM_RAW, 'User introduction'),
                    'country'         => new external_value(PARAM_ALPHA, 'Home country code of the user, such as AU or CZ'),
                    'city'            => new external_value(PARAM_NOTAGS, 'Home city of the user'),
                    'address'         => new external_value(PARAM_RAW, 'Introduction text'),
                    'town'            => new external_value(PARAM_NOTAGS, 'Home town of the user'),
                    'homenumber'      => new external_value(PARAM_RAW, 'Home phone number'),
                    'businessnumber'  => new external_value(PARAM_RAW, 'business phone number'),
                    'mobilenumber'    => new external_value(PARAM_RAW, 'mobile phone number'),
                    'faxnumber'       => new external_value(PARAM_RAW, 'fax number'),
                    'officialwebsite' => new external_value(PARAM_RAW, 'Official user website'),
                    'personalwebsite' => new external_value(PARAM_RAW, 'Personal website'),
                    'blogaddress'     => new external_value(PARAM_RAW, 'Blog web address'),
                    'aimscreenname'   => new external_value(PARAM_ALPHANUMEXT, 'AIM screen name'),
                    'icqnumber'       => new external_value(PARAM_ALPHANUMEXT, 'ICQ Number'),
                    'msnnumber'       => new external_value(PARAM_ALPHANUMEXT, 'MSN Number'),
                    'yahoochat'       => new external_value(PARAM_ALPHANUMEXT, 'Yahoo chat'),
                    'skypeusername'   => new external_value(PARAM_ALPHANUMEXT, 'Skype username'),
                    'jabberusername'  => new external_value(PARAM_RAW, 'Jabber/XMPP username'),
                    'occupation'      => new external_value(PARAM_TEXT, 'Occupation'),
                    'industry'        => new external_value(PARAM_TEXT, 'Industry'),
                        )
                )
        );
    }


    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_users_parameters() {
        return new external_function_parameters(array());
    }

    /**
     * Get user information
     *
     * @param array $userids  array of user ids
     * @return array An array of arrays describing users
     */
    public static function get_users() {
        return self::get_users_by_id(array());
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_users_returns() {
        return self::get_users_by_id_returns();
    }


    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function update_favourites_parameters() {

       return new external_function_parameters(
            array(
                'users' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id'              => new external_value(PARAM_NUMBER, 'ID of the favourites owner', VALUE_OPTIONAL),
                            'username'        => new external_value(PARAM_RAW, 'Username of the favourites owner', VALUE_OPTIONAL),
                            'shortname'       => new external_value(PARAM_SAFEDIR, 'Favourites shorname', VALUE_DEFAULT, 'favourites', NULL_NOT_ALLOWED),
                            'institution'     => new external_value(PARAM_SAFEDIR, 'Mahara institution', VALUE_DEFAULT, 'mahara', NULL_NOT_ALLOWED),
                            'favourites'      => new external_multiple_structure(
                                                            new external_single_structure(
                                                                array(
                                                                    'id' => new external_value(PARAM_NUMBER, 'favourite user Id', VALUE_OPTIONAL),
                                                                    'username' => new external_value(PARAM_RAW, 'favourite username', VALUE_OPTIONAL),
                                                                ), 'User favourites')
                                                        ),
                            )
                    )
                )
            )
        );
    }


    /**
     * update one or more user favourites
     *
     * @param array $users
     */
    public static function update_favourites($users) {
        global $USER, $WEBSERVICE_INSTITUTION;

        $params = self::validate_parameters(self::update_favourites_parameters(), array('users'=>$users));

        db_begin();
        foreach ($params['users'] as $user) {
            if (!empty($user['id'])) {
                $dbuser = get_record('usr', 'id', $user['id'], 'deleted', 0);
            }
            else if (!empty($user['username'])) {
                $dbuser = get_record('usr', 'username', $user['username'], 'deleted', 0);
            }
            else {
                throw new invalid_parameter_exception('update_favourites: no username or id ');
            }
            if (empty($dbuser)) {
                throw new invalid_parameter_exception('update_favourites: invalid user: '.$user['id'].'/'.$user['username']);
            }
            $ownerid = $dbuser->id;

            // Make sure auth is valid
            if (!$authinstance = get_record('auth_instance', 'id', $dbuser->authinstance)) {
                throw new invalid_parameter_exception('update_favourites: Invalid authentication type: '.$dbuser->authinstance);
            }
            // check the institution is allowed
            // basic check authorisation to edit for the current institution
            if (!$USER->can_edit_institution($authinstance->institution)) {
                throw new invalid_parameter_exception('update_favourites: access denied for institution: '.$authinstance->institution.' on user: '.$dbuser->id);
            }

            // are we allowed to delete for this institution
            if ($WEBSERVICE_INSTITUTION != $user['institution'] || !$USER->can_edit_institution($user['institution'])) {
                throw new invalid_parameter_exception('update_favourites: access denied for institution: '.$user['institution']);
            }

            // check that the favourites exist and we are allowed to administer them
            $favourites = array($USER->get('id') => 'admin');
            foreach ($user['favourites'] as $favourite) {
                if (!empty($favourite['id'])) {
                    $dbuser = get_record('usr', 'id', $favourite['id'], 'deleted', 0);
                }
                else if (!empty($favourite['username'])) {
                    $dbuser = get_record('usr', 'username', $favourite['username'], 'deleted', 0);
                }
                else {
                    throw new invalid_parameter_exception('update_favourites: no username or id for favourite  owner');
                }
                if (empty($dbuser)) {
                    throw new invalid_parameter_exception('update_favourites: invalid user: '.$favourite['id'].'/'.$favourite['username']);
                }

                // Make sure auth is valid
                if (!$authinstance = get_record('auth_instance', 'id', $dbuser->authinstance)) {
                    throw new invalid_parameter_exception('update_favourites: Invalid authentication type: '.$dbuser->authinstance);
                }

                // check the institution is allowed
                // basic check authorisation to edit for the current institution of the user
                if (!$USER->can_edit_institution($authinstance->institution)) {
                    throw new invalid_parameter_exception('update_favourites: access denied for institution: '.$authinstance->institution.' on user: '.$dbuser->username);
                }
                $favourites[]= $dbuser->id;
            }

            // now do the update
            update_favorites($ownerid, $user['shortname'], $user['institution'], $favourites);
        }
        db_commit();

        return null;
    }

   /**
     * Returns description of method result value
     * @return external_description
     */
    public static function update_favourites_returns() {
        return null;
    }


    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_favourites_parameters() {
        return new external_function_parameters(
            array(
                'users'=> new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'shortname' => new external_value(PARAM_SAFEDIR, 'Favourites shorname', VALUE_DEFAULT, 'favourites', NULL_NOT_ALLOWED),
                            'userid' => new external_value(PARAM_INT, 'user id'),
                        )
                    )
                )
            )
        );
    }

    /**
     * Get user favourites
     *
     * @param array $userids  array of user ids
     * @return array An array of arrays describing users favourites
     */
    public static function get_favourites($users) {
        global $CFG, $WEBSERVICE_INSTITUTION;

        $params = self::validate_parameters(self::get_favourites_parameters(), array('users' => $users));
//        $params = self::validate_parameters(self::get_users_by_id_parameters(), $users);
//        // if this is a get all users - then lets get them all
//        if (empty($params['favourites']['userids'])) {
//            $params['favourites']['userids'] = array();
//            $dbusers = get_records_sql_array('SELECT u.id AS id FROM {usr} u INNER JOIN {auth_instance} ai ON u.authinstance = ai.id WHERE u.deleted = 0 AND ai.institution = \''.$WEBSERVICE_INSTITUTION.'\'', null);
//            foreach ($dbusers as $dbuser) {
//                // eliminate bad uid
//                if ($dbuser->id == 0) {
//                    continue;
//                }
//                $params['favourites']['userids'][] = $dbuser->id;
//            }
//        }

        //TODO: check if there is any performance issue: we do one DB request to retrieve
        //  all user, then for each user the profile_load_data does at least two DB requests
//        $users = array();
//        foreach ($params['users'] as $user) {
//            if ($user = get_user($user['userid'])) {
//                $users[]= $user;
//            }
//            else {
//                throw new invalid_parameter_exception('get_favourites: Invalid user id: '.$user['userid']);
//            }
//        }
//        $shortname = $params['favourites']['shortname'];

    // build the final results
        $result = array();
        foreach ($params['users'] as $user) {
            $dbuser = get_record('usr', 'id', $user['userid'], 'deleted', 0);
            if (empty($dbuser)) {
                throw new invalid_parameter_exception('get_favourites: Invalid user id: '.$user['userid']);
            }
            // check the institution
            $auth_instance = get_record('auth_instance', 'id', $dbuser->authinstance);
            if (empty($auth_instance) || $WEBSERVICE_INSTITUTION != $auth_instance->institution) {
                throw new invalid_parameter_exception('get_favourites: Not authorised for access to user id: '.$user['userid']);
            }

            // get the favourite for the shortname for this user
            $favs = array();
            $favourites = get_user_favorites($dbuser->id, 100);
            $dbfavourite = get_record('favorite', 'shortname', $user['shortname'], 'institution', $WEBSERVICE_INSTITUTION, 'owner', $dbuser->id);
            if (empty($dbfavourite)) {
                throw new invalid_parameter_exception('get_favourites: Invalid favourite: '.$user['shortname'].'/'.$WEBSERVICE_INSTITUTION);
            }
            if (!empty($favourites)) {
                foreach ($favourites as $fav) {
                    $dbfavuser = get_record('usr', 'id', $fav->id, 'deleted', 0);
                    $favs[]= array('id' => $fav->id, 'username' => $dbfavuser->username);
                }
            }

            $result[] = array(
                            'id'            => $dbuser->id,
                            'username'      => $dbuser->username,
                            'shortname'     => $dbfavourite->shortname,
                            'institution'   => $dbfavourite->institution,
                            'favourites'    => $favs,
                            );
        }

        return $result;
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_favourites_returns() {
        return new external_multiple_structure(
                new external_single_structure(
                        array(
                            'id'              => new external_value(PARAM_NUMBER, 'ID of the favourites owner'),
                            'username'        => new external_value(PARAM_RAW, 'Username of the favourites owner'),
                            'shortname'       => new external_value(PARAM_SAFEDIR, 'Favourites shorname'),
                            'institution'     => new external_value(PARAM_SAFEDIR, 'Mahara institution'),
                            'favourites'      => new external_multiple_structure(
                                                            new external_single_structure(
                                                                array(
                                                                    'id' => new external_value(PARAM_NUMBER, 'favourite user Id', VALUE_OPTIONAL),
                                                                    'username' => new external_value(PARAM_RAW, 'favourite username', VALUE_OPTIONAL),
                                                                ), 'User favourites')
                                                        ),
                                                )
                )
        );
    }




    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_all_favourites_parameters() {
        return new external_function_parameters(
            array(
                'shortname' => new external_value(PARAM_SAFEDIR, 'Favourites shorname', VALUE_DEFAULT, 'favourites', NULL_NOT_ALLOWED),
                )
        );
    }

    /**
     * Get all user favourites
     *
     * @param string $shortname  shortname of the favourites
     */
    public static function get_all_favourites($shortname) {
        global $CFG, $WEBSERVICE_INSTITUTION;

        $params = self::validate_parameters(self::get_all_favourites_parameters(), array('shortname' => $shortname));

        $dbfavourites = get_records_sql_array('SELECT * from {favorite} WHERE shortname = ? AND institution = ?',array($shortname, $WEBSERVICE_INSTITUTION));
        if (empty($dbfavourites)) {
            throw new invalid_parameter_exception('get_favourites: Invalid favourite: '.$user['shortname'].'/'.$WEBSERVICE_INSTITUTION);
        }

        $result = array();
        foreach ($dbfavourites as $dbfavourite) {
            $dbuser = get_record('usr', 'id', $dbfavourite->owner, 'deleted', 0);
            if (empty($dbuser)) {
                continue;
            }
            $favourites = get_user_favorites($dbuser->id, 100);
            $favs = array();
            if (!empty($favourites)) {
                foreach ($favourites as $fav) {
                    $dbfavuser = get_record('usr', 'id', $fav->id, 'deleted', 0);
                    $favs[]= array('id' => $fav->id, 'username' => $dbfavuser->username);
                }
            }

            $result[] = array(
                            'id'            => $dbuser->id,
                            'username'      => $dbuser->username,
                            'shortname'     => $dbfavourite->shortname,
                            'institution'   => $dbfavourite->institution,
                            'favourites'    => $favs,
                            );
        }

        return $result;
    }


    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_all_favourites_returns() {
        return self::get_favourites_returns();
    }

}
