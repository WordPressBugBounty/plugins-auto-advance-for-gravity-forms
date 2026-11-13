<?php

/*
Plugin Name: Auto Advance for GravityForms
Description:  The Auto Advance plugin for Gravity Forms makes the form filling process quicker and more user friendly for visitors. The plugin gives an easy way to choose which field(s) trigger an auto advance to the next step of your form.
Version: 5.0.6
Author: Frog Eat Fly
Tested up to: 7.1
Author URI: https://www.multipagepro.com/
*/
define( 'AAFG_PRO_PLAN_NAME', 'autoadvanceforgravityformspro' );
define( 'AAFG_PLUS_PLAN_NAME', 'autoadvanceforgravityformsplus' );
define( 'ZZD_AAGF_DIR', plugin_dir_path( __FILE__ ) );
define( 'ZZD_AAGF_URL', plugin_dir_url( __FILE__ ) );
define( 'AUTO_ADVANCED_ZZD', '5.0.6' );
define( 'AUTO_ADVANCED_ASSETS', '5.0.6' );
if ( !function_exists( 'aafgf_fs' ) ) {
    // Create a helper function for easy SDK access.
    function aafgf_fs() {
        global $aafgf_fs;
        if ( !isset( $aafgf_fs ) ) {
            // Include Freemius SDK.
            require_once __DIR__ . '/freemius/start.php';
            $aafgf_fs = fs_dynamic_init( array(
                'id'             => '6159',
                'slug'           => 'auto-advance-for-gravity-forms',
                'type'           => 'plugin',
                'public_key'     => 'pk_03c636a8e7786094d99a1bf5e2e43',
                'is_premium'     => false,
                'has_addons'     => false,
                'has_paid_plans' => true,
                'menu'           => array(
                    'first-path' => 'plugins.php',
                    'support'    => false,
                ),
                'is_live'        => true,
            ) );
        }
        return $aafgf_fs;
    }

    // Init Freemius.
    aafgf_fs();
    // Signal that SDK was initiated.
    do_action( 'aafgf_fs_loaded' );
    aafgf_fs()->add_filter( 'pricing_url', 'aafgf_upgrade_url' );
    function aafgf_upgrade_url(  $url  ) {
        $modified_url = "https://www.multipagepro.com/";
        return $modified_url;
    }

}
add_action( 'gform_loaded', array('GF_Auto_Advanced_AddOn', 'load'), 5 );
class GF_Auto_Advanced_AddOn {
    public static function load() {
        if ( !method_exists( 'GFForms', 'include_addon_framework' ) ) {
            return;
        }
        require_once ZZD_AAGF_DIR . 'php/class-gfautoadvancedaddon.php';
        GFAddOn::register( 'GFAutoAdvancedAddOn' );
    }

}

add_action( 'plugins_loaded', 'load_aagf_languages', 0 );
function load_aagf_languages() {
    load_plugin_textdomain( 'gf-autoadvanced', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}

function aafg_simple_addon() {
    return GFAutoAdvancedAddOn::get_instance();
}

function aagf_fn_validate_required_plugins() {
    if ( !method_exists( 'GFForms', 'include_payment_addon_framework' ) ) {
        return false;
    }
    return true;
}

require_once ZZD_AAGF_DIR . 'php/admin-notices.php';
