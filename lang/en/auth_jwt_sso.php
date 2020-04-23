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

/**
 * Strings for component 'auth_jwt_sso', language 'en'.
 *
 * @package   auth_jwt_sso
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['auth_jwt_cookiedescription'] = 'This method checks a shared domain cookie server to see if the user has already authenticated with the website. '.
    'You may specify login/index.php?redirect=0 to disable shared cookie authentication on the login page.';
$string['mcrypt_not_installed'] = 'Mcrypt is not installed, JWT Cookie auth will not work without mcrypt';
$string['secret'] = 'Secret';
$string['secret_description'] = 'The JWT Cookie encryption secret';
$string['shared_login_url'] = 'Login / Refresh URL';
$string['shared_login_url_description'] = 'URL to forward to refresh shared cookie (login url) - can use alt url';
$string['no_account_url'] = 'URL when no LMS account';
$string['no_account_url_description'] = 'URL to forward a user to when they have a valid cookie, but do not have a valid LMS account';
$string['logout_url'] = 'Logout URL';
$string['logout_url_description'] = 'URL to single logout / return to after logout';
$string['change_password_url'] = 'Password-change URL';
$string['change_password_url_description'] = 'URL to forward to change user password';
$string['cookie_name'] = 'Cookie Name';
$string['cookie_name_description'] = 'Name of the shared cookie to decrypt for login/auth';
$string['shared_cookie_domain'] = 'Shared Cookie domain';
$string['shared_cookie_domain_description'] = 'The domain name the shared cookie is placed at';
$string['dosinglelogout'] = 'Single Logout?';
$string['dosinglelogout_description'] = 'Logout all systems, i.e. website, when logging out of Moodle';
$string['iterations'] = 'Encryption Iterations';
$string['iterations_description'] = 'Iterations to run encryption method';
$string['timeout'] = 'Shared Cookie Timeout';
$string['timeout_description'] = 'How long before cookie is considered expired';
$string['paramparser'] = 'Parameter Parser';
$string['paramparser_description'] = 'The parameter parser helps to identify to jwt on the cookie';
$string['userdatamapper'] = 'Userdata Mapper field';
$string['userdatamapper_description'] = 'The userdata mapper field used to identify where can we get the data from array on jwt cookie token';
$string['useruniquemoodleid'] = 'Mapping Moodle';
$string['useruniquemoodleid_description'] = 'Which Moodle user field should the data attribute be matched to?';
$string['useruniqueid'] = 'Mapping Unique ID';
$string['useruniqueid_description'] = 'Which Moodle user field should the Unique ID data attribute be matched to from cookie?';
$string['username'] = 'Username';
$string['email'] = 'Email';
$string['idnumber'] = 'ID Number';
$string['pluginname'] = 'JWT SSO';
$string['use_cookie'] = 'Use cookie auth';
$string['use_cookie_description'] = 'Use cookie to authenticate JWT';
$string['jwt_name'] = 'Parameter Name';
$string['jwt_name_description'] = 'Name of the shared jwt to decrypt for login/auth';
$string['encryption_method'] = 'Encryption method';
$string['encryption_method_description'] = 'Encryption method used for payload';
$string['HS256'] = 'HS256';