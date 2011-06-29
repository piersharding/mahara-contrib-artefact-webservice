<?php
/**
 * Mahara: Electronic portfolio, weblog, resume builder and social networking
 * Copyright (C) 2006-2011 Catalyst IT Ltd and others; see:
 *                         http://wiki.mahara.org/Contributors
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
 * @package    mahara
 * @subpackage lang
 * @author     Catalyst IT Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006-2011 Catalyst IT Ltd http://catalyst.net.nz
 *
 */

defined('INTERNAL') || die();

$string['control_webservices'] = 'Switch ALL WebServices on or off: ';
$string['enabled'] = 'enabled';
$string['disabled'] = 'disabled';
$string['masterswitch'] = 'WebServices master switch';


$string['pluginname'] = 'WebServices XXX';

$string['protocolswitches'] = 'Switch On/Off Protocols';
$string['manage_protocols'] = '<b>Enable or Disable protocols that are to be supported by this installation:</b>';
$string['protocol'] = 'Protocol';
$string['rest'] = 'REST';
$string['soap'] = 'SOAP';
$string['xmlrpc'] = 'XML-RPC';

$string['servicefunctiongroups'] = 'Manage Service Groups';
$string['sfgdescription'] = 'Build lists of functions into service groups, that can be allocated to users authorised for execution';
$string['name'] = 'Name';
$string['component'] = 'Component';
$string['functions'] = 'Functions';
$string['enableservice'] = 'Enable/disable Service';
$string['existingserviceusers'] = 'Cannot switch to token only users, as service users are linked to this service';
$string['existingtokens'] = 'Cannot switch to authorisaed service users as token users exist for this service';
$string['usersonly'] = 'currently Users only';
$string['tokensonly'] = 'currently Tokens only';
$string['switchtousers'] = 'Switch to Users';
$string['switchtotokens'] = 'Switch to Tokens';

$string['invalidservice'] = 'Invalid Service selected';
$string['invalidfunction'] = 'Invalid Function selected';

$string['servicetokens'] = 'Manage Service Access Tokens';
$string['stdescription'] = 'Generate access tokens, and allocate users to Service Groups';
$string['username'] = 'User';
$string['servicename'] = 'Service';
$string['generate'] = 'Generate token';
$string['invalidtoken'] = 'Invalid token selected';
$string['token'] = 'Token';
$string['invaliduserselected'] = 'Invalid user selected';
$string['invaliduserselectedinstitution'] = 'Invalid user for token institution selected from user search';
$string['noservices'] = 'No services configured';

$string['manageserviceusers'] = 'Manage Service Users';
$string['sudescription'] = 'Allocate users to Service Groups and Institutions.  User must only be configured once.  All users must have the "webservice" authentication method.  The instance of the "webservice" authentication method of the user must be from an institution that they are a member of.';
$string['serviceuser'] = 'Service user';
$string['invalidserviceuser'] = 'Invalid Service User selected';
$string['nouser'] = 'Please select a user';
$string['duplicateuser'] = 'User account is already configured for Web Services';

$string['servicefunctionlist'] = 'Functions allocated against the service';
$string['sfldescription'] = 'Build the list of functions that are available to this service';
$string['functionname'] = 'Function name';
$string['classname'] = 'Class name';
$string['methodname'] = 'Method name';
$string['invalidinput'] = 'Invalid input';
$string['configsaved'] = 'Configuration saved';

$string['webservice'] = 'WebService';
$string['webservices'] = 'WebServices';

$string['headingusersearchtoken'] = 'WebServices: Token user search';
$string['headingusersearchuser'] = 'WebServices: Service User search';
$string['usersearchinstructions'] = 'Select a user to associate with a webservice by clicking on the avatar.  You can search for users by clicking on the initials of their first and last names, or by entering a name in the search box. You can also enter an email address in the search box if you would like to search email addresses.';

// wsdoc
$string['function'] = 'Function';
$string['description'] = 'Description';
$string['component'] = 'Component';
$string['method'] = 'Method';
$string['class'] = 'Class';
$string['arguments'] = 'Arguments';
$string['invalidparameter'] = 'Invalid parameter value detected, execution can not continue.';

// testclient
$string['testclient'] = 'Web Services test client';
$string['tokenauth'] = 'Token';
$string['userauth'] = 'User';
$string['authtype'] = 'Authentication Type';
$string['enterparameters'] = 'Enter Function Parameters';
$string['testclientinstructions'] = 'This is the interactive test client facility for Web Services.  This enables you to select a function and then execute it live against the current system.  Please be aware that ANY function you eecute will run for real.';
$string['executed'] = 'Function call executed';
$string['invaliduser'] = 'Invalid wsusername/wspassword supplied';
$string['invalidtoken'] = 'Invalid wstoken supplied';
$string['topinstructions'] = 'Go <a href="%s">here</a> to access the Web Services interactive test client.';


// running webservices messages
$string['accessexception'] = 'Access control exception';
$string['accessnotallowed'] = 'Access to web service not allowed';
$string['actwebserviceshhdr'] = 'Active web service protocols';
$string['addaservice'] = 'Add service';
$string['addcapabilitytousers'] = 'Check users capability';
//$string['addcapabilitytousersdescription'] = 'Users should have two capabilities - webservice:createtoken and a capability matching the protocols used, for example webservice/rest:use, webservice/soap:use. To achieve this, create a web services role with the appropriate capabilities allowed and assign it to the web services user as a system role.';
$string['addfunction'] = 'Add function';
$string['addfunctionhelp'] = 'Select the function to add to the service.';
$string['addfunctions'] = 'Add functions';
$string['addfunctionsdescription'] = 'Select required functions for the newly created service.';
//$string['addrequiredcapability'] = 'Assign/unassign the required capability';
$string['addservice'] = 'Add a new service: {$a->name} (id: {$a->id})';
$string['addservicefunction'] = 'Add functions to the service "%s"';
$string['allusers'] = 'All users';
$string['amftestclient'] = 'AMF test client';
$string['apiexplorer'] = 'API explorer';
$string['apiexplorernotavalaible'] = 'API explorer not available yet.';
$string['arguments'] = 'Arguments';
$string['authmethod'] = 'Authentication method';
$string['configwebserviceplugins'] = 'For security reasons, only protocols that are in use should be enabled.';
$string['context'] = 'Context';
$string['createservicedescription'] = 'A service is a set of web service functions. You will allow the user to access to a new service. On the <strong>Add service</strong> page check \'Enable\' and \'Authorised users\' options. Select \'No required capability\'.';
$string['createserviceforusersdescription'] = 'A service is a set of web service functions. You will allow users to access to a new service. On the <strong>Add service</strong> page check \'Enable\' and uncheck \'Authorised users\' options. Select \'No required capability\'.';
$string['createtoken'] = 'Create token';
$string['createtokenforuser'] = 'Create a token for a user';
$string['createtokenforuserdescription'] = 'Create a token for the web services user.';
$string['createuser'] = 'Create a specific user';
$string['createuserdescription'] = 'A web services user is required to represent the system controlling Mahara.';
$string['default'] = 'Default to "%s"';
$string['deleteaservice'] = 'Delete service';
$string['deleteservice'] = 'Delete the service: {$a->name} (id: {$a->id})';
$string['deleteserviceconfirm'] = 'Deleting a service will also delete the tokens related to this service. Do you really want to delete external service "%s"?';
$string['deletetokenconfirm'] = 'Do you really want to delete this web service token for <strong>{$a->user}</strong> on the service <strong>{$a->service}</strong>?';
$string['disabledwarning'] = 'All web service protocols are disabled.  The "Enable web services" setting can be found in Advanced features.';
$string['doc'] = 'Documentation';
$string['docaccessrefused'] = 'You are not allowed to see the documentation for this token';
$string['documentation'] = 'web service documentation';
$string['editaservice'] = 'Edit service';
$string['editservice'] = 'Edit the service: {$a->name} (id: {$a->id})';
//$string['enabled'] = 'Enabled';
$string['enabledocumentation'] = 'Enable developer documentation';
$string['enabledocumentationdescription'] = 'Detailed web services documentation is available for enabled protocols.';
$string['enableprotocols'] = 'Enable protocols';
$string['enableprotocolsdescription'] = 'At least one protocol should be enabled. For security reasons, only protocols that are to be used should be enabled.';
$string['enablews'] = 'Enable web services';
$string['enablewsdescription'] = 'Web services must be enabled in Advanced features.';
$string['entertoken'] = 'Enter a security key/token:';
$string['error'] = 'Error: %s';
$string['errorcatcontextnotvalid'] = 'You cannot execute functions in the category context (category id:{$a->catid}). The context error message was: {$a->message}';
$string['errorcodes'] = 'Error message';
$string['errorcoursecontextnotvalid'] = 'You cannot execute functions in the course context (course id:{$a->courseid}). The context error message was: {$a->message}';
$string['errorinvalidparam'] = 'The param "%s" is invalid.';
$string['errorinvalidparamsapi'] = 'Invalid external api parameter';
$string['errorinvalidparamsdesc'] = 'Invalid external api description';
$string['errorinvalidresponseapi'] = 'Invalid external api response';
$string['errorinvalidresponsedesc'] = 'Invalid external api response description';
$string['errormissingkey'] = 'Missing required key in single structure: %s';
$string['errornotemptydefaultparamarray'] = 'The web service description parameter named \'%s\' is an single or multiple structure. The default can only be empty array. Check web service description.';
$string['erroronlyarray'] = 'Only arrays accepted.';
$string['erroroptionalparamarray'] = 'The web service description parameter named \'%s\' is an single or multiple structure. It can not be set as VALUE_OPTIONAL. Check web service description.';
$string['errorresponsemissingkey'] = 'Error in response - Missing following required key in a single structure: %s';
$string['errorscalartype'] = 'Scalar type expected, array or object received.';
$string['errorunexpectedkey'] = 'Unexpected keys (%s) detected in parameter array.';
$string['execute'] = 'Execute';
$string['executewarnign'] = 'WARNING: If you press execute your database will be modified and changes can not be reverted automatically!';
$string['externalservice'] = 'External service';
$string['externalservicefunctions'] = 'External service functions';
$string['externalservices'] = 'External services';
$string['externalserviceusers'] = 'External service users';
$string['failedtolog'] = 'Failed to login';
$string['function'] = 'Function';
//$string['functions'] = 'Functions';
$string['generalstructure'] = 'General structure';
//$string['checkusercapability'] = 'Check user capability';
//$string['checkusercapabilitydescription'] = 'The user should have appropriate capabilities according to the protocols used, for example webservice/rest:use, webservice/soap:use. To achieve this, create a web services role with protocol capabilities allowed and assign it to the web services user as a system role.';
$string['information'] = 'Information';
$string['invalidaccount'] = 'Invalid web services account - check service user configuration';
$string['invalidextparam'] = 'Invalid external api parameter: %s';
$string['invalidextresponse'] = 'Invalid external api response: %s';
$string['invalidiptoken'] = 'Invalid token - your IP is not supported';
$string['invalidtimedtoken'] = 'Invalid token - token expired';
$string['invalidtoken'] = 'Invalid token - token not found';
$string['invalidtokensession'] = 'Invalid session based token - session not found or expired';
$string['iprestriction'] = 'IP restriction';
$string['iprestriction_help'] = 'The user will need to call web service from the listed IPs.';
$string['key'] = 'Key';
$string['keyshelp'] = 'The keys are used to access your Mahara account from external applications.';
$string['manageprotocols'] = 'Manage protocols';
$string['managetokens'] = 'Manage tokens';
//$string['missingcaps'] = 'Missing capabilities';
//$string['missingcaps_help'] = 'List of required capabilities for the service which the selected user does not have. Missing capabilities must be added to the user\'s role in order to use the service.';
$string['missingpassword'] = 'Missing password';
$string['missingusername'] = 'Missing username';
$string['nofunctions'] = 'This service has no functions.';
//$string['norequiredcapability'] = 'No required capability';
$string['notoken'] = 'The token list is empty.';
$string['onesystemcontrolling'] = 'One system controlling Mahara with a token';
$string['onesystemcontrollingdescription'] = 'The following steps help you to set up the Mahara web service for a system to control Mahara. These steps also help to set up the recommended token (security keys) authentication method.';
$string['operation'] = 'Operation';
$string['optional'] = 'Optional';
$string['phpparam'] = 'XML-RPC (PHP structure)';
$string['phpresponse'] = 'XML-RPC (PHP structure)';
$string['postrestparam'] = 'PHP code for REST (POST request)';
$string['potusers'] = 'Not authorised users';
$string['potusersmatching'] = 'Not authorised users matching';
$string['print'] = 'Print all';
$string['protocol'] = 'Protocol';
$string['removefunction'] = 'Remove';
$string['removefunctionconfirm'] = 'Do you really want to remove function "{$a->function}" from service "{$a->service}"?';
$string['requireauthentication'] = 'This method requires authentication with xxx permission.';
$string['required'] = 'Required';
//$string['requiredcapability'] = 'Required capability';
//$string['requiredcapability_help'] = 'If set, only users with the required capability can access the service.';
//$string['requiredcaps'] = 'Required capabilities';
$string['resettokenconfirm'] = 'Do you really want to reset this web service key for <strong>{$a->user}</strong> on the service <strong>{$a->service}</strong>?';
$string['resettokenconfirmsimple'] = 'Do you really want to reset this key? Any saved links containing the old key will not work anymore.';
$string['response'] = 'Response';
$string['restcode'] = 'REST';
$string['restexception'] = 'REST';
$string['restparam'] = 'REST (POST parameters)';
$string['restrictedusers'] = 'Authorised users only';
$string['restrictedusers_help'] = 'This setting determines whether all users with the permission to create a web services token can generate a token for this service via their security keys page or whether only authorised users can do so.';
$string['securitykey'] = 'Security key (token)';
$string['securitykeys'] = 'Security keys';
$string['selectauthorisedusers'] = 'Select authorised users';
$string['selectedcapability'] = 'Selected';
$string['selectedcapabilitydoesntexit'] = 'The currently set required capability (%s) doesn\'t exist any more. Please change it and save the changes.';
$string['selectservice'] = 'Select a service';
$string['selectspecificuser'] = 'Select a specific user';
$string['selectspecificuserdescription'] = 'Add the web services user as an authorised user.';
$string['service'] = 'Service';
$string['servicehelpexplanation'] = 'A service is a set of functions. A service can be accessed by all users or just specified users.';
//$string['servicename'] = 'Service name';
$string['servicesbuiltin'] = 'Built-in services';
$string['servicescustom'] = 'Custom services';
$string['serviceusers'] = 'Authorised users';
$string['serviceusersettings'] = 'User settings';
$string['serviceusersmatching'] = 'Authorised users matching';
$string['serviceuserssettings'] = 'Change settings for the authorised users';
$string['simpleauthlog'] = 'Simple authentication login';
$string['step'] = 'Step';
$string['testauserwithtestclientdescription'] = 'Simulate external access to the service using the web service test client. Before doing so, login as a user with the Mahara/webservice:createtoken capability and obtain the security key (token) via My profile settings. You will use this token in the test client. In the test client, also choose an enabled protocol with the token authentication. <strong>WARNING: The functions that you test WILL BE EXECUTED for this user, so be careful what you choose to test!</strong>';
$string['testclient'] = 'Web service test client';
$string['testclientdescription'] = '* The web service test client <strong>executes</strong> the functions for <strong>REAL</strong>. Do not test functions that you don\'t know. <br/>* All existing web service functions are not yet implemented into the test client. <br/>* In order to check that a user cannot access some functions, you can test some functions that you didn\'t allow.<br/>* To see clearer error messages set the debugging to <strong>{$a->mode}</strong> into {$a->atag}<br/>* Access the {$a->amfatag}.';
$string['testwithtestclient'] = 'Test the service';
$string['testwithtestclientdescription'] = 'Simulate external access to the service using the web service test client. Use an enabled protocol with token authentication. <strong>WARNING: The functions that you test WILL BE EXECUTED, so be careful what you choose to test!</strong>';
//$string['token'] = 'Token';
$string['tokenauthlog'] = 'Token authentication';
$string['tokencreatedbyadmin'] = 'Can only be reset by administrator (*)';
$string['tokencreator'] = 'Creator';
$string['updateusersettings'] = 'Update';
$string['userasclients'] = 'Users as clients with token';
$string['userasclientsdescription'] = 'The following steps help you to set up the Mahara web service for users as clients. These steps also help to set up the recommended token (security keys) authentication method. In this use case, the user will generate his token from the security keys page via My profile settings.';
//$string['usermissingcaps'] = 'Missing capabilities: %s';
$string['usernotallowed'] = 'The user is not allowed for this service. First you need to allow this user on the %s\'s allowed users administration page.';
$string['usersettingssaved'] = 'User settings saved';
$string['validuntil'] = 'Valid until';
$string['validuntil_help'] = 'If set, the service will be inactivated after this date for this user.';
//$string['webservice'] = 'Web service';
//$string['webservices'] = 'Web services';
$string['webservicesoverview'] = 'Overview';
$string['webservicetokens'] = 'Web service tokens';
$string['wrongusernamepassword'] = 'Wrong username or password';
$string['wsauthmissing'] = 'The web service authentication plugin is missing.';
$string['wsauthnotenabled'] = 'The web service authentication plugin is disabled.';
$string['wsclientdoc'] = 'Mahara web service client documentation';
$string['wsdocumentation'] = 'Web service documentation';
$string['wsdocumentationdisable'] = 'Web service documentation is disabled.';
$string['wsdocumentationintro'] = 'To create a client we advise you to read the {$a->doclink}';
$string['wsdocumentationlogin'] = 'or enter your web service username and password:';
$string['wspassword'] = 'Web service password';
$string['wsusername'] = 'Web service username';

