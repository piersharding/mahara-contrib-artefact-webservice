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

// must be run from the command line
if (isset($_SERVER['REMOTE_ADDR']) || isset($_SERVER['GATEWAY_INTERFACE'])){
    die('Direct access to this script is forbidden.');
}

// disable the WSDL cache
ini_set("soap.wsdl_cache_enabled", "0");

//define('NO_DEBUG_DISPLAY', true);
define('INTERNAL', 1);
define('PUBLIC', 1);

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
class webservice_test extends UnitTestCase {

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

    function setUp() {
        // default current user to admin
        global $USER;
        $USER->id = 1;

        //token to test
        $this->servicename = 'test webservices';
        $this->testuser = 'wstestuser';

        // clean out first
        $this->tearDown();

        // create the new test user
        if (!$dbuser = get_record('usr', 'username', $this->testuser)) {
            db_begin();
            if (!$authinstance = get_record('auth_instance', 'institution', 'mahara', 'authname', 'webservice')) {
                throw new Exception('missing authentication type: mahara/webservce - configure the mahara institution');
            }
            $institution = new Institution($authinstance->institution);

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
            $userid = create_user($new_user, $profilefields, $institution, $authinstance);
            db_commit();
            $dbuser = get_record('usr', 'username', $this->testuser);
            // Add salt and encrypt the pw, if the auth instance allows for it
            $userobj = new User();
            $userobj = $userobj->find_by_id($dbuser->id);
            $authobj_tmp = AuthFactory::create($dbuser->authinstance);
            $authobj_tmp->change_password($userobj, $dbuser->password, false);
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

        //protocols to test
        $this->testrest = true;
        $this->testxmlrpc = true;
        $this->testsoap = true;

        ////// READ-ONLY DB tests ////
        $this->readonlytests = array(
            'mahara_user_get_users_by_id' => true,
            'mahara_user_get_users' => true,
        );

        ////// WRITE DB tests ////
        $this->writetests = array(
            'mahara_user_create_users' => true,
            'mahara_user_update_users' => true,
            'mahara_user_delete_users' => true,
        );

        //performance testing: number of time the web service are run
        $this->iteration = 1;

        // keep track of users created and deleted
        $this->created_users = array();

        //DO NOT CHANGE
        //reset the timers
        $this->timerrest = 0;
        $this->timerxmlrpc = 0;
        $this->timersoap = 0;
    }


    function tearDown() {

        // delete test token
        if ($this->testtoken) {
            delete_records('external_tokens', 'token', $this->testtoken);
        }

        // remove the web service descriptions
        $dbservice = get_record('external_services', 'name', $this->servicename);
        if ($dbservice) {
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
    }


    function testRun() {
        global $CFG;

        if (!$this->testrest and !$this->testxmlrpc and !$this->testsoap) {
            print_r("Web service unit tests are not run as not setup.
                (see /artefact/webservice/simpletest/testwebservice.php)");
        }

        // need a token to test
        if (!empty($this->testtoken)) {

            // test the REST interface
            if ($this->testrest) {
                $this->timerrest = time();
                require_once(get_config('docroot') . "/artefact/webservice/rest/lib.php");
                // iterate the token and user auth types
                foreach (array('server', 'simpleserver') as $type) {
                    $restclient = new webservice_rest_client(get_config('wwwroot')
                                    . '/artefact/webservice/rest/'.$type.'.php',
                                     ($type == 'server' ? array('wstoken' => $this->testtoken) :
                                                          array('wsusername' => $this->testuser, 'wspassword' => $this->testuser)));
                    for ($i = 1; $i <= $this->iteration; $i = $i + 1) {
                        foreach ($this->readonlytests as $functionname => $run) {
                            if ($run) {
                                $this->{$functionname}($restclient);
                            }
                        }
                        foreach ($this->writetests as $functionname => $run) {
                            if ($run) {
                                $this->{$functionname}($restclient);
                            }
                        }
                    }
                }

                $this->timerrest = time() - $this->timerrest;
                //here you could call a log function to display the timer
                //example:
//                error_log('REST time: ');
//                error_log(print_r($this->timerrest));
            }

            // test the XML-RPC interface
            if ($this->testxmlrpc) {
                $this->timerxmlrpc = time();
                require_once(get_config('docroot') . "/artefact/webservice/xmlrpc/lib.php");
                // iterate the token and user auth types
                foreach (array('server', 'simpleserver') as $type) {
                    $xmlrpcclient = new webservice_xmlrpc_client(get_config('wwwroot')
                                    . '/artefact/webservice/xmlrpc/'.$type.'.php',
                                     ($type == 'server' ? array('wstoken' => $this->testtoken) :
                                                          array('wsusername' => $this->testuser, 'wspassword' => $this->testuser)));

                    for ($i = 1; $i <= $this->iteration; $i = $i + 1) {
                        foreach ($this->readonlytests as $functionname => $run) {
                            if ($run) {
                                $this->{$functionname}($xmlrpcclient);
                            }
                        }
                        foreach ($this->writetests as $functionname => $run) {
                            if ($run) {
                                $this->{$functionname}($xmlrpcclient);
                            }
                        }
                    }
                }

                $this->timerxmlrpc = time() - $this->timerxmlrpc;
                //here you could call a log function to display the timer
                //example:
//                error_log('XML-RPC time: ');
//                error_log(print_r($this->timerxmlrpc));
            }

            // test the SOAP interface
            if ($this->testsoap) {
                $this->timersoap = time();
                require_once(get_config('docroot') . "/artefact/webservice/soap/lib.php");

                // iterate the token and user auth types
                foreach (array('server' => array('wstoken' => $this->testtoken),
                               'simpleserver' => array('wsusername' => $this->testuser, 'wspassword' => $this->testuser),
                               'simpleserver' => array('wsse' => 1)) as $type => $parms) {
                    if (isset($parms['wsse'])) {
                        $soapclient = new webservice_soap_client(get_config('wwwroot')
                                        . '/artefact/webservice/soap/'.$type.'.php', array('wsservice' => $this->servicename),
                                            array("features" => SOAP_WAIT_ONE_WAY_CALLS)); //force SOAP synchronous mode
                                                                                           //when function return null
                        $wsseSoapClient = new webservice_soap_client_wsse(array($soapclient, '_doRequest'), $soapclient->wsdl, $soapclient->getOptions());
                        $wsseSoapClient->__setUsernameToken($this->testuser, $this->testuser);
                        $soapclient->setSoapClient($wsseSoapClient);
                    }
                    else {
                        $soapclient = new webservice_soap_client(get_config('wwwroot')
                                        . '/artefact/webservice/soap/'.$type.'.php', $parms,
                                            array("features" => SOAP_WAIT_ONE_WAY_CALLS)); //force SOAP synchronous mode
                                                                                           //when function return null
                    }
                    $soapclient->setWsdlCache(false);
                    for ($i = 1; $i <= $this->iteration; $i = $i + 1) {
                        foreach ($this->readonlytests as $functionname => $run) {
                            if ($run) {
                                $this->{$functionname}($soapclient);
                            }
                        }
                        foreach ($this->writetests as $functionname => $run) {
                            if ($run) {
                                $this->{$functionname}($soapclient);
                            }
                        }
                    }
                }

                $this->timersoap = time() - $this->timersoap;
                //here you could call a log function to display the timer
                //example:
//                error_log('SOAP time: ');
//                error_log(print_r($this->timersoap));
            }
        }
    }

    ///// WEB SERVICE TEST FUNCTIONS

    // simple get users by ID
    function mahara_user_get_users_by_id($client) {
        $dbusers = get_records_sql_array('SELECT u.id AS id FROM {usr} u INNER JOIN {auth_instance} ai ON u.authinstance = ai.id WHERE u.deleted = 0 AND ai.institution = \'mahara\'', null);
        $userids = array();
        foreach ($dbusers as $dbuser) {
            if ($dbuser->id == 0) continue;
            $userids[] = $dbuser->id;
        }
        $function = 'mahara_user_get_users_by_id';

        $params = array('userids' => $userids);
        $users = $client->call($function, $params);

        $this->assertEqual(count($users), count($userids));
    }


    // simple get all users
    function mahara_user_get_users($client) {
        $function = 'mahara_user_get_users';

        $dbusers = get_records_sql_array('SELECT u.id AS id FROM {usr} u INNER JOIN {auth_instance} ai ON u.authinstance = ai.id WHERE u.deleted = 0 AND ai.institution = \'mahara\'', null);
        $userids = array();
        foreach ($dbusers as $dbuser) {
            if ($dbuser->id == 0) continue;
            $userids[] = $dbuser->id;
        }
        $params = array();
        $users = $client->call($function, $params);

        $this->assertEqual(count($users), count($userids));
    }

    // create user test
    function mahara_user_create_users($client) {
        //Test data
        //a full user: user1
        $user1 = new stdClass();
        $user1->username = 'testusername1';
        $user1->password = 'testpassword1';
        $user1->firstname = 'testfirstname1';
        $user1->lastname = 'testlastname1';
        $user1->email = 'testemail1@hogwarts.school.nz';
        $user1->auth = 'internal';
        $user1->institution = 'mahara';
        $user1->studentid = 'testidnumber1';
        $user1->preferredname = 'Hello World!';
        $user1->city = 'testcity1';
        $user1->country = 'au';
        //a small user: user2
        $user2 = new stdClass();
        $user2->username = 'testusername2';
        $user2->password = 'testpassword2';
        $user2->firstname = 'testfirstname2';
        $user2->lastname = 'testlastname2';
        $user2->email = 'testemail1@hogwarts.school.nz';
        $user2->auth = 'webservice';
        $user2->institution = 'mahara';
        $users = array($user1, $user2);

        //do not run the test if user1 or user2 already exists
        foreach (array($user1->username, $user2->username) as $username) {
            $existinguser = get_record('usr', 'username', $username);
            if (!empty($existinguser)) {
                delete_user($existinguser->id);
            }
        }

        $function = 'mahara_user_create_users';
        $params = array('users' => $users);
        $resultusers = $client->call($function, $params);

        // store users for deletion at the end
        foreach ($resultusers as $u) {
            $this->created_users[]= $u['id'];
        }
        $this->assertEqual(count($users), count($resultusers));

        //retrieve user1 from the DB and check values
        $dbuser1 = get_record('usr', 'username', $user1->username);
        $this->assertEqual($dbuser1->firstname, $user1->firstname);
        $this->assertEqual($dbuser1->password,
                self::encrypt_password($user1->password, $dbuser1->salt));
        $this->assertEqual($dbuser1->lastname, $user1->lastname);
        $this->assertEqual($dbuser1->email, $user1->email);
        $this->assertEqual($dbuser1->studentid, $user1->studentid);
        $this->assertEqual($dbuser1->preferredname, $user1->preferredname);
        foreach (array('city', 'country') as $field) {
            $artefact = get_profile_field($dbuser1->id, $field);
            $this->assertEqual($artefact, $user1->{$field});
        }

        //retrieve user2 from the DB and check values
        $dbuser2 = get_record('usr', 'username', $user2->username);
        $this->assertEqual($dbuser2->firstname, $user2->firstname);
        $this->assertEqual($dbuser2->password,
                self::encrypt_password($user2->password, $dbuser2->salt));
        $this->assertEqual($dbuser2->lastname, $user2->lastname);
        $this->assertEqual($dbuser2->email, $user2->email);
    }

    private static function encrypt_password($password, $salt='') {
        if ($salt == '') {
            $salt = substr(md5(rand(1000000, 9999999)), 2, 8);
        }
        return sha1($salt . $password);
    }

    // delete user test
    function mahara_user_delete_users($client) {
        global $DB, $CFG;

        //Set test data
        //a full user: user1
        if (!$authinstance = get_record('auth_instance', 'institution', 'mahara', 'authname', 'webservice')) {
            throw new invalid_parameter_exception('Invalid authentication type: mahara/webservce');
        }
        $institution = new Institution($authinstance->institution);

        //can run this test only if test usernames don't exist
        foreach (array( 'deletetestusername1', 'deletetestusername2') as $username) {
            $existinguser = get_record('usr', 'username', $username);
            if (!empty($existinguser)) {
                delete_user($existinguser->id);
            }
        }
        db_begin();
        $new_user = new StdClass;
        $new_user->authinstance = $authinstance->id;
        $new_user->username     = 'deletetestusername1';
        $new_user->firstname    = $new_user->username;
        $new_user->lastname     = $new_user->username;
        $new_user->password     = $new_user->username;
        $new_user->email        = $new_user->username.'@hogwarts.school.nz';
        $new_user->passwordchange = 0;
        $new_user->admin        = 0;
        $profilefields = new StdClass;
        $userid = create_user($new_user, $profilefields, $institution, $authinstance);
        db_commit();
        $dbuser1 = get_record('usr', 'username', $new_user->username);
        $this->assertTrue($dbuser1);
        $this->created_users[]= $dbuser1->id;

        db_begin();
        $new_user = new StdClass;
        $new_user->authinstance = $authinstance->id;
        $new_user->username     = 'deletetestusername2';
        $new_user->firstname    = $new_user->username;
        $new_user->lastname     = $new_user->username;
        $new_user->password     = $new_user->username;
        $new_user->email        = $new_user->username.'@hogwarts.school.nz';
        $new_user->passwordchange = 0;
        $new_user->admin        = 0;
        $profilefields = new StdClass;
        $userid = create_user($new_user, $profilefields, $institution, $authinstance);
        db_commit();
        $dbuser2 = get_record('usr', 'username', $new_user->username);
        $this->assertTrue($dbuser2);
        $this->created_users[]= $dbuser2->id;

        //delete the users by webservice
        $function = 'mahara_user_delete_users';
        $params = array('userids' => array($dbuser1->id, $dbuser2->id));
        $client->call($function, $params);

        //search for them => TESTS they don't exists
        foreach (array($dbuser1, $dbuser2) as $user) {
            $user = get_record('usr', 'id', $user->id, 'deleted', 0);
            $this->assertTrue(empty($user));
        }
    }

    // update user test
    function mahara_user_update_users($client) {
        global $DB, $CFG;

        //Set test data
        if (!$authinstance = get_record('auth_instance', 'institution', 'mahara', 'authname', 'internal')) {
            throw new invalid_parameter_exception('Invalid authentication type: mahara/webservce');
        }
        $institution = new Institution($authinstance->institution);

        //can run this test only if test usernames don't exist
        foreach (array( 'veryimprobabletestusername1', 'veryimprobabletestusername2', 'veryimprobabletestusername1_updated', 'veryimprobabletestusername2_updated') as $username) {
            $existinguser = get_record('usr', 'username', $username);
            if (!empty($existinguser)) {
                delete_user($existinguser->id);
            }
        }

        //a full user: user1
        db_begin();
        $user1 = new stdClass();
        $user1->authinstance = $authinstance->id;
        $user1->username = 'veryimprobabletestusername1';
        $user1->password = 'testpassword1';
        $user1->firstname = 'testfirstname1';
        $user1->lastname = 'testlastname1';
        $user1->email = 'testemail1@hogwarts.school.nz';
        $user1->studentid = 'testidnumber1';
        $user1->preferredname = 'Hello World!';
        $user1->city = 'testcity1';
        $user1->country = 'au';
        $profilefields = new StdClass;
        $userid = create_user($user1, $profilefields, $institution, $authinstance);
        db_commit();
        $dbuser1 = get_record('usr', 'username', $user1->username);
        $this->assertTrue($dbuser1);
        $userobj = new User();
        $userobj = $userobj->find_by_id($dbuser1->id);
        $authobj_tmp = AuthFactory::create($dbuser1->authinstance);
        $authobj_tmp->change_password($userobj, $dbuser1->password, false);
        $this->created_users[]= $dbuser1->id;
        $dbuser1 = get_record('usr', 'username', $user1->username);

        db_begin();
        $user2 = new stdClass();
        $user2->authinstance = $authinstance->id;
        $user2->username = 'veryimprobabletestusername2';
        $user2->password = 'testpassword2';
        $user2->firstname = 'testfirstname2';
        $user2->lastname = 'testlastname2';
        $user2->email = 'testemail1@hogwarts.school.nz';
        $profilefields = new StdClass;
        $userid = create_user($user2, $profilefields, $institution, $authinstance);
        db_commit();
        $dbuser2 = get_record('usr', 'username', $user2->username);
        $this->assertTrue($dbuser2);
        $userobj = new User();
        $userobj = $userobj->find_by_id($dbuser2->id);
        $authobj_tmp = AuthFactory::create($dbuser2->authinstance);
        $authobj_tmp->change_password($userobj, $dbuser2->password, false);
        $this->created_users[]= $dbuser2->id;
        $dbuser2 = get_record('usr', 'username', $user2->username);


        //update the test data
        $user1 = new stdClass();
        $user1->id = $dbuser1->id;
        $user1->username = 'veryimprobabletestusername1_updated';
        $user1->password = 'testpassword1_updated';
        $user1->salt = $dbuser1->salt;
        $user1->firstname = 'testfirstname1_updated';
        $user1->lastname = 'testlastname1_updated';
        $user1->email = 'testemail1_updated@hogwarts.school.nz';
        $user1->studentid = 'testidnumber1_updated';
        $user1->preferredname = 'Hello World!_updated';
        $user1->city = 'testcity1_updated';
        $user1->country = 'au';
        $user2 = new stdClass();
        $user2->id = $dbuser2->id;
        $user2->username = 'veryimprobabletestusername2_updated';
        $user2->password = 'testpassword2_updated';
        $user2->salt = $dbuser2->salt;
        $user2->firstname = 'testfirstname2_updated';
        $user2->lastname = 'testlastname2_updated';
        $user2->email = 'testemail1_updated@hogwarts.school.nz';
        $users = array($user1, $user2);

        //update the users by web service
        $function = 'mahara_user_update_users';
        $params = array('users' => $users);
        $client->call($function, $params);

        //compare DB user with the test data
        $dbuser1 = get_record('usr', 'username', $user1->username);
        $this->assertEqual($dbuser1->firstname, $user1->firstname);
        $this->assertEqual($dbuser1->password,
                self::encrypt_password($user1->password, $dbuser1->salt));
        $this->assertEqual($dbuser1->lastname, $user1->lastname);
        $this->assertEqual($dbuser1->email, $user1->email);
        $this->assertEqual($dbuser1->studentid, $user1->studentid);
        $this->assertEqual($dbuser1->preferredname, $user1->preferredname);
        foreach (array('city', 'country') as $field) {
            $artefact = get_profile_field($dbuser1->id, $field);
            $this->assertEqual($artefact, $user1->{$field});
        }

        $dbuser2 = get_record('usr', 'username', $user2->username);
        $this->assertEqual($dbuser2->firstname, $user2->firstname);
        $this->assertEqual($dbuser2->password,
                self::encrypt_password($user2->password, $dbuser2->salt));
        $this->assertEqual($dbuser2->lastname, $user2->lastname);
        $this->assertEqual($dbuser2->email, $user2->email);
    }
}
