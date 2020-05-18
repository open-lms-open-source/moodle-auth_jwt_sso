<?php

defined('MOODLE_INTERNAL') || die;
if ($ADMIN->fulltree) {

    $settings->add(new admin_setting_configtext('auth_jwt_sso/secret', get_string('secret', 'auth_jwt_sso'),
        get_string('secret_description', 'auth_jwt_sso'), ''));
    $settings->add(new admin_setting_configcheckbox('auth_jwt_sso/secret_encoded', get_string('secret_encoded', 'auth_jwt_sso'),
        get_string('secret_encoded_description', 'auth_jwt_sso'), ''));
    $settings->add(new admin_setting_configcheckbox('auth_jwt_sso/use_cookie', get_string('use_cookie', 'auth_jwt_sso'),
        get_string('use_cookie_description', 'auth_jwt_sso'), 1));
    $settings->add(new admin_setting_configtext('auth_jwt_sso/shared_login_url', get_string('shared_login_url', 'auth_jwt_sso'),
        get_string('shared_login_url_description', 'auth_jwt_sso'), ''));
    $settings->add(new admin_setting_configtext('auth_jwt_sso/no_account_url', get_string('no_account_url', 'auth_jwt_sso'),
        get_string('no_account_url_description', 'auth_jwt_sso'), ''));
    $settings->add(new admin_setting_configtext('auth_jwt_sso/logout_url', get_string('logout_url', 'auth_jwt_sso'),
        get_string('logout_url_description', 'auth_jwt_sso'), ''));
    $settings->add(new admin_setting_configtext('auth_jwt_sso/change_password_url', get_string('change_password_url', 'auth_jwt_sso'),
        get_string('change_password_url_description', 'auth_jwt_sso'), ''));
    $settings->add(new admin_setting_configtext('auth_jwt_sso/cookie_name', get_string('cookie_name', 'auth_jwt_sso'),
        get_string('cookie_name_description', 'auth_jwt_sso'), ''));
    $settings->add(new admin_setting_configtext('auth_jwt_sso/jwt_name', get_string('jwt_name', 'auth_jwt_sso'),
        get_string('jwt_name_description', 'auth_jwt_sso'), ''));
    $settings->add(new admin_setting_configtext('auth_jwt_sso/shared_cookie_domain', get_string('shared_cookie_domain', 'auth_jwt_sso'),
        get_string('shared_cookie_domain_description', 'auth_jwt_sso'), ''));

}