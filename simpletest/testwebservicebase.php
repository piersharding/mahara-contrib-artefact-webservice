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
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 */

/**
 * Test the different web service protocols.
 *
 * @author jerome@moodle.com
 * @author     Piers Harding
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package web service
 * @copyright  Copyright (C) 2011 Catalyst IT Ltd (http://www.catalyst.net.nz)
 */

// do not allow to run directly
if (!defined('INTERNAL')) {
    die();
}

// disable the WSDL cache
ini_set("soap.wsdl_cache_enabled", "0");

//define('NO_DEBUG_DISPLAY', true);
define('PUBLIC', 1);


// must be run from the command line
if (isset($_SERVER['REMOTE_ADDR']) || isset($_SERVER['GATEWAY_INTERFACE'])){
    die('Direct access to this script is forbidden.');
}

// disable the WSDL cache
ini_set("soap.wsdl_cache_enabled", "0");


// Catch anything that goes wrong in init.php
ob_start();
    require(dirname(dirname(dirname(dirname(__FILE__)))).'/init.php');
    $errors = trim(ob_get_contents());
ob_end_clean();
$path = get_config('docroot').'artefact/webservice/libs/zend';
set_include_path($path . PATH_SEPARATOR . get_include_path());

require_once(get_config('docroot').'/artefact/webservice/libs/externallib.php');
require_once(get_config('docroot').'/artefact/webservice/libs/moodlelib.php');
require_once(get_config('docroot').'/artefact/webservice/libs/weblib.php');
require_once(get_config('docroot').'/artefact/lib.php');
require_once('institution.php');
require_once('group.php');

require_once(dirname(dirname(__FILE__)) . '/libs/simpletestlib/autorun.php');


/**
 * How to configure this unit tests:
 * 0- Enable the web service you wish to test
 * 1- Create a service with all functions
 * 2- Create a token associate this service and to an admin (or a user with all required capabilities)
 * 3- Configure setUp() function:
 *      a- write the token
 *      b- activate the protocols you wish to test
 *      c- activate the functions you wish to test (readonlytests and writetests arrays)
 *      d- set the number of times the web services are run
 * Do not run WRITE test function on a production site as they impact the DB (even though every
 * test should clean the modified data)
 *
 * How to write a new function:
 * 1- Add the function name to the array readonlytests/writetests
 * 2- Set it as false when you commit!
 * 3- write the function  - Do not prefix the function name by 'test'
 */
class webservice_test_base extends UnitTestCase {

    public $testtoken;
    public $testrest;
    public $testxmlrpc;
    public $testsoap;
    public $timerrest;
    public $timerxmlrpc;
    public $timersoap;
    public $readonlytests;
    public $writetests;
    public $servicename;
    public $testuser;
    public $testpasswd;
    public $created_users;
    public $created_groups;
    public $test_institution;
    public $consumer;
    public $consumer_key;
    public $request_token;
    public $access_token;

    function setUp() {
        // default current user to admin
        global $USER;
        $USER->id = 1;
        $USER->admin = 1;

        //token to test
        $this->servicename = 'test webservices';
        $this->testuser = 'wstestuser';
        $this->testinstitution = 'mytestinstitution1';

        // clean out first
        $this->tearDown();

        if (!$authinstance = get_record('auth_instance', 'institution', 'mahara', 'authname', 'webservice')) {
            throw new Exception('missing authentication type: mahara/webservce - configure the mahara institution');
        }
        $this->authinstance = $authinstance;
        $this->institution = new Institution($authinstance->institution);

        // create the new test user
        if (!$dbuser = get_record('usr', 'username', $this->testuser)) {
            db_begin();

            error_log('creating test user');

            $new_user = new StdClass;
            $new_user->authinstance = $authinstance->id;
            $new_user->username     = $this->testuser;
            $new_user->firstname    = $this->testuser;
            $new_user->lastname     = $this->testuser;
            $new_user->password     = $this->testuser;
            $new_user->email        = $this->testuser.'@hogwarts.school.nz';
            $new_user->passwordchange = 0;
            $new_user->admin        = 1;
            $profilefields = new StdClass;
            $userid = create_user($new_user, $profilefields, $this->institution, $authinstance);
            $dbuser = get_record('usr', 'username', $this->testuser);
            db_commit();
        }

        // construct a test service from all available functions
        $dbservice = get_record('external_services', 'name', $this->servicename);
        if (empty($dbservice)) {
            $service = array('name' => $this->servicename, 'restrictedusers' => 0, 'enabled' => 1, 'component' => 'webservice', 'timecreated' => time());
            insert_record('external_services', $service);
            $dbservice = get_record('external_services', 'name', $this->servicename);
        }
        $dbfunctions = get_records_array('external_functions', null, null, 'name');
        foreach ($dbfunctions as $function) {
            $sfexists = record_exists('external_services_functions', 'externalserviceid', $dbservice->id, 'functionname', $function->name);
            if (!$sfexists) {
                $service_function = array('externalserviceid' => $dbservice->id, 'functionname' => $function->name);
                insert_record('external_services_functions', $service_function);
                $dbservice->timemodified = time();
                update_record('external_services', $dbservice);
            }
        }

        // create an OAuth registry object
        require_once(dirname(dirname(__FILE__)).'/libs/oauth-php/OAuthServer.php');
        require_once(dirname(dirname(__FILE__)).'/libs/oauth-php/OAuthStore.php');
        require_once(dirname(dirname(__FILE__)).'/libs/oauth-php/OAuthRequester.php');        
        $store = OAuthStore::instance('Mahara');
        $new_app = array(
                    'application_title' => 'Test Application',
                    'application_uri'   => 'http://example.com',
                    'requester_name'    => $dbuser->firstname.' '.$dbuser->lastname,
                    'requester_email'   => $dbuser->email,
                    'callback_uri'      => 'http://example.com',
                    'institution'       => 'mahara',
                    'externalserviceid' => $dbservice->id,
          );
        $this->consumer_key = $store->updateConsumer($new_app, $dbuser->id, true);
        $this->consumer = (object) $store->getConsumer($this->consumer_key, $dbuser->id);
        //$store->deleteConsumerRequestToken($token);
//        error_log('consumer: '.var_export($this->consumer,true));
        
        // Now do the request and access token
        $this->request_token  = $store->addConsumerRequestToken($this->consumer_key, array());
//        error_log('request token: '.var_export($this->request_token,true));
        
        // authorise
        $verifier = $store->authorizeConsumerRequestToken($this->request_token['token'], $dbuser->id, 'localhost');
//        error_log('verifier: '.var_export($verifier,true));
        
        // exchange access token
        $options = array();
        $options['verifier'] = $verifier;
        $this->access_token  = $store->exchangeConsumerRequestForAccessToken($this->request_token['token'], $options);
//        error_log('access token: '.var_export($this->access_token,true));
        
        // generate a test token
        $token = external_generate_token(EXTERNAL_TOKEN_PERMANENT, $dbservice, $dbuser->id);
        $dbtoken = get_record('external_tokens', 'token', $token);
        $this->testtoken = $dbtoken->token;

        // create an external test user instance
        $dbserviceuser = (object) array('externalserviceid' => $dbservice->id,
                        'userid' => $dbuser->id,
                        'institution' => 'mahara',
                        'timecreated' => time());
        $dbserviceuser->id = insert_record('external_services_users', $dbserviceuser, 'id', true);


        $groupcategories = get_records_array('group_category','','','displayorder');
        if (empty($groupcategories)) {
            throw new Exception('missing group categories: you must create atleast one group category');
        }
        $category = array_shift($groupcategories);

        // setup test groups
        $groupid = group_create(array(
            'shortname'      => 'mytestgroup1',
            'name'           => 'The test group 1',
            'description'    => 'a description for test group 1',
            'institution'    => 'mahara',
            'grouptype'      => 'standard',
            'category'       => $category->id,
            'jointype'       => 'open',
            'open'           => 1,
            'controlled'     => 0,
            'request'        => 0,
            'submitpages'    => 0,
            'hidemembers'    => 0,
            'invitefriends'  => 0,
            'suggestfriends' => 0,
            'hidden'         => 0,
            'hidemembersfrommembers' => 0,
            'public'         => 0,
            'usersautoadded' => 0,
            'members'        => array($dbuser->id => 'admin'),
            'viewnotify'     => 0,
        ));

        // create test institution
        $dbinstitution = get_record('institution', 'name', $this->testinstitution);
        if (empty($dbinstitution)) {
            db_begin();
            $newinstitution = new StdClass;
            $institution = $newinstitution->name    = $this->testinstitution;
            $newinstitution->displayname            = $institution.' - display name';
            $newinstitution->authplugin             = 'internal';
            $newinstitution->showonlineusers        = 1;
            $newinstitution->registerallowed        = 0;
            $newinstitution->theme                  =  null;
            $newinstitution->defaultquota           = get_config_plugin('artefact', 'file', 'defaultquota');
            $newinstitution->defaultmembershipperiod  = null;
            $newinstitution->maxuseraccounts        = null;
    //        $newinstitution->expiry               = db_format_timestamp($values['expiry']);
            $newinstitution->allowinstitutionpublicviews  = 1;
            insert_record('institution', $newinstitution);
            $authinstance = (object)array(
                'instancename' => 'internal',
                'priority'     => 0,
                'institution'  => $newinstitution->name,
                'authname'     => 'internal',
            );
            insert_record('auth_instance', $authinstance);
            db_commit();
        }

        //protocols to test
        $this->testrest = false;
        $this->testxmlrpc = false;
        $this->testsoap = false;

        ////// READ-ONLY DB tests ////
        $this->readonlytests = array(
        );

        ////// WRITE DB tests ////
        $this->writetests = array(
        );

        //performance testing: number of time the web service are run
        $this->iteration = 1;

        // keep track of users created and deleted
        $this->created_users = array();

        // keep track of groups
        $this->created_groups = array();

        //DO NOT CHANGE
        //reset the timers
        $this->timerrest = 0;
        $this->timerxmlrpc = 0;
        $this->timersoap = 0;
    }



    public function clean_institution() {

        // clean down the institution
        $dbinstitution = get_record('institution', 'name', $this->testinstitution);
        if (!empty($dbinstitution)) {
            db_begin();
            $institution = new Institution($this->testinstitution);
            $dbinvites = get_records_array('usr_institution_request', 'institution', $this->testinstitution);
            if (!empty($dbinvites)){
                $userids = array();
                foreach ($dbinvites as $dbinvite) {
                    $userids[]= $dbinvite->usr;
                }
                $institution->decline_requests($userids);
            }
            $dbmembers = get_records_array('usr_institution', 'institution', $this->testinstitution);
            if (!empty($dbmembers)){
                $userids = array();
                foreach ($dbmembers as $dbmember) {
                    $userids[]= $dbmember->usr;
                }
                $institution->removeMembers($userids);
            }
            db_commit();
        }
    }


    function tearDown() {

        // clean down the institution
        $this->clean_institution();

        // delete test token
        if ($this->testtoken) {
            delete_records('external_tokens', 'token', $this->testtoken);
        }

        // remove the web service descriptions
        $dbservice = get_record('external_services', 'name', $this->servicename);
        if ($dbservice) {
            $dbregistry = get_record('oauth_server_registry', 'externalserviceid', $dbservice->id);
            if ($dbregistry) {
                delete_records('oauth_server_token', 'osr_id_ref', $dbregistry->id);
            }
            delete_records('oauth_server_registry', 'externalserviceid', $dbservice->id);
            delete_records('external_services_users', 'externalserviceid', $dbservice->id);
            delete_records('external_tokens', 'externalserviceid', $dbservice->id);
            delete_records('external_services_functions', 'externalserviceid', $dbservice->id);
            delete_records('external_services', 'id', $dbservice->id);
        }

        // remove the test user
        $dbuser = get_record('usr', 'username', $this->testuser);
        if ($dbuser){
            $this->created_users[]= $dbuser->id;
        }

        // remove all left over users
        if ($this->created_users) {
            foreach ($this->created_users as $userid) {
                delete_user($userid);
            }
        }

        // remove left over groups
        $dbgroup = get_record('group', 'shortname', 'mytestgroup1', 'institution', 'mahara');
        if ($dbgroup){
            $this->created_groups[]= $dbgroup->id;
        }
        if ($this->created_groups) {
            foreach ($this->created_groups as $groupid) {
                group_delete($groupid);
            }
        }
    }


    protected static function encrypt_password($password, $salt='') {
        if ($salt == '') {
            $salt = substr(md5(rand(1000000, 9999999)), 2, 8);
        }
        return sha1($salt . $password);
    }


    /**
     * Create test users from one place to share between update
     * and favourites
     */
    function create_user1_for_update() {
        //Set test data
        //can run this test only if test usernames don't exist
        foreach (array( 'veryimprobabletestusername1', 'veryimprobabletestusername1_updated') as $username) {
            $existinguser = get_record('usr', 'username', $username);
            if (!empty($existinguser)) {
                delete_user($existinguser->id);
            }
        }

        //a full user: user1
        $user1 = new stdClass();
        $user1->authinstance = $this->authinstance->id;
        $user1->username = 'veryimprobabletestusername1';
        if ($dbuser1 = get_record('usr', 'username', $user1->username)) {
            return $dbuser1;
        }
        $user1->password = 'testpassword1';
        $user1->firstname = 'testfirstname1';
        $user1->lastname = 'testlastname1';
        $user1->email = 'testemail1@hogwarts.school.nz';
        $user1->studentid = 'testidnumber1';
        $user1->preferredname = 'Hello World!';
        $user1->city = 'testcity1';
        $user1->country = 'au';
        $profilefields = new StdClass;
        db_begin();
        $userid = create_user($user1, $profilefields, $this->institution, $this->authinstance);
        db_commit();
        $dbuser1 = get_record('usr', 'username', $user1->username);
        $this->assertTrue($dbuser1);
        $userobj = new User();
        $userobj = $userobj->find_by_id($dbuser1->id);
        $authobj_tmp = AuthFactory::create($dbuser1->authinstance);
        $authobj_tmp->change_password($userobj, $dbuser1->password, false);
        $this->created_users[]= $dbuser1->id;
        $dbuser1 = get_record('usr', 'username', $user1->username);
        return $dbuser1;
    }

    /**
     * Create test users from one place to share between update
     * and favourites
     */
    function create_user2_for_update() {
        //can run this test only if test usernames don't exist
        foreach (array( 'veryimprobabletestusername2', 'veryimprobabletestusername2_updated') as $username) {
            $existinguser = get_record('usr', 'username', $username);
            if (!empty($existinguser)) {
                delete_user($existinguser->id);
            }
        }

        $user2 = new stdClass();
        $user2->authinstance = $this->authinstance->id;
        $user2->username = 'veryimprobabletestusername2';
        if ($dbuser2 = get_record('usr', 'username', $user2->username)) {
            return $dbuser2;
        }
        $user2->password = 'testpassword2';
        $user2->firstname = 'testfirstname2';
        $user2->lastname = 'testlastname2';
        $user2->email = 'testemail1@hogwarts.school.nz';
        $profilefields = new StdClass;
        db_begin();
        $userid = create_user($user2, $profilefields, $this->institution, $this->authinstance);
        db_commit();
        $dbuser2 = get_record('usr', 'username', $user2->username);
        $this->assertTrue($dbuser2);
        $userobj = new User();
        $userobj = $userobj->find_by_id($dbuser2->id);
        $authobj_tmp = AuthFactory::create($dbuser2->authinstance);
        $authobj_tmp->change_password($userobj, $dbuser2->password, false);
        $this->created_users[]= $dbuser2->id;
        $dbuser2 = get_record('usr', 'username', $user2->username);
        return $dbuser2;
    }


    /**
     * get rid of a zero id record that I created and cannot easily delete
     *
     * @param array $favs
     */
    protected static function prune_nasty_zero($favs) {
        $zero = false;
        foreach ($favs as $k => $fav) {
            $fav = (object)$fav;
            if ($fav->id == 0) {
                $zero = $k;
                break;
            }
        }
        if ($zero !== false) {
            unset($favs["$zero"]);
        }
        return $favs;
    }


    /**
     * Find the non-admin userid
     *
     * @param array $favs
     */
    protected static function find_new_fav($favs) {
        foreach ($favs as $k => $fav) {
            if ($fav->id > 1) {
                return $fav->id;
            }
        }
        return false;
    }

}
