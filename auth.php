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
 * Authentication Plugin: Shared URL JWT Authentication
 * Authenticates against using a shared secret generated by the website
 *
 * @package auth_jwt_sso
 * @author Lupiya Mujala <lupiya@ecreators.com.au>
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/authlib.php');
require_once($CFG->libdir . '/moodlelib.php');
require_once($CFG->dirroot . '/auth/jwt_sso/lib/jwt/vendor/autoload.php');
require_once($CFG->dirroot . '/user/lib.php');

use MiladRahimi\Jwt\Cryptography\Algorithms\Hmac\HS256;
use MiladRahimi\Jwt\JwtParser;


/**
 * Shared Cookie authentication plugin.
 */

class auth_plugin_jwt_sso extends auth_plugin_base {

    /**
     * Constructor.
     */
    public function __construct() {
        $this->authtype = 'jwt_sso';
    }

    /**
     * jwt_cookie uses loginpage_hook and pre_loginpage_hook to authenicate users.
     * This function is never called, see:
     * https://docs.moodle.org/dev/Authentication_plugins#Overview_of_Moodle_authentication_process
     *
     * @param string $username The username
     * @param string $password The password
     *
     * @return bool false always for this function as it's never called.
     */
    public function user_login($username, $password) {
        return false;
    }

    /**
     * When the login page for moodle has been requested, run this function.
     * We attempt to auto log the user in, or redirect to the alternative login page.
     */
    public function loginpage_hook() {
        global $CFG;

        $signed_jwt = optional_param('sjwt', 0, PARAM_TEXT);
        $redirect = optional_param('redirect', 1, PARAM_INT);

        if (!function_exists('mcrypt_module_open')) {
            debugging(get_string('mcrypt_not_installed', 'auth_jwt_cookie'), DEBUG_ALL);
            return;
        }

        // Let's verify the JWT.
        $valid_jwt = $this->verify_jwt($signed_jwt);
        if(!$valid_jwt){
            return;
        }

        $user = $this->verify_user($valid_jwt);

        //Complete the login here.
        if ($user !== false && $user != null) {
            try {
                complete_user_login($user);
                redirect($CFG->wwwroot .'/my');
            } catch (Exception $e) {
                //do nothing
            }

        }else{
            return;
        }
    }

    /**
     * This attempts to parse the JWT from the URL
     */
    protected function verify_jwt($signed_jwt){
        global $CFG;

        $valid = false;

        // Check if the site actually has the secret set.
        if(!$CFG->jwtssosecret){
            return $valid;
        }

        // Check if the signed JWT exists.
        if(empty($signed_jwt)){
            return $valid;
        }else{
            $valid = $this->decrypt_jwt($signed_jwt);
            return $valid;
        }
    }

    /**
     * This function will decrypt the JWT
     */
    protected function decrypt_jwt($signed_jwt){
        global $CFG;

        $secretKey = ($CFG->jwtssosecret);

        try{
            $signer = new HS256($secretKey);
            $parser = new JwtParser($signer);
            $claims = $parser->parse($signed_jwt);
        }catch(Exception $e){
            return false;
        }

        return $claims;
    }

    /**
     * This function will verify if the user account exists and complete the login.
     */
    protected function verify_user($userdata)
    {
        global $DB;

        if($userdata){
            $user = $DB->get_record('user', array('username' => $userdata['username']));

            $newuser = false;
            if(!$user){
                $user = create_user_record($userdata['username'], '', $this->authtype);
                $newuser = true;
            }

            $this->update_user_profile_fields($user, $userdata, $newuser);

            try {
                $user = get_complete_user_data('id', $user->id);
            } catch (dml_multiple_records_exception $e) {
                // Multiple users are configured with this id.
                debugging('Multiple users have the same idnumber configured for this authtype. idnumber: '.$user->id, DEBUG_ALL);
                return false;
            } catch (dml_missing_record_exception $e) {
                // No users are configured with this id.
                debugging('No users configured with idnumber for this authtype. idnumber: ' . $user->id, DEBUG_ALL);
                return false;
            }

            return $user;
        }else{
            return false;
        }
    }

    public function update_user_profile_fields(&$user, $userdata, $newuser = false) {
        global $CFG;

        $update = false;
        // Update the user fields.
        $allowed_fields = array('firstname', 'lastname', 'description', 'email');
        foreach ($allowed_fields as $field){
            if(isset($userdata[$field])) {
                $user->{$field} = $userdata[$field];
                $update = true;
            }
        }

        // Update the profile.
        if($update){
            require_once($CFG->dirroot . '/user/lib.php');
            require_once($CFG->dirroot . '/user/profile/lib.php');
            user_update_user($user, false, false);
            // Save custom profile fields.
            profile_save_data($user);
        }
    }

}
