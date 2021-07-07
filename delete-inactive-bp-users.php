<?php
/**
 * Plugin Name:  BP Inactive Subscriber User Cleaner
 * Description:  Removes subscribes after 30 days if they  never logged to the site after activating their account.
 * Author:       BuddyDev
 * Version:      1.0.0
 * License:      GPLv2 or later (license.txt)
 */

// Do not allow direct access over web.
defined( 'ABSPATH' ) || exit;

/**
 * Cleans inactive subscriber users periodically.
 */
class Tosin_BP_Inactive_User_Cleaner {

	/**
	 * Singleton.
	 *
	 * @var Tosin_BP_Inactive_User_Cleaner
	 */
	private static $instance = null;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->setup();
	}

	/**
	 * Boots the class.
	 *
	 * @return Tosin_BP_Inactive_User_Cleaner
	 */
	public static function boot() {

		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Sets up hooks.
	 */
	private function setup() {
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		// hook runs daily.
		add_action( 'tosin_bp_clean_inactive_user', array( $this, 'auto_delete_users' ) );
	}

	/**
	 * Schedule cron job.
	 */
	public function activate() {

		if ( ! wp_next_scheduled( 'tosin_bp_clean_inactive_user' ) ) {
			wp_schedule_event( time(), 'daily', 'tosin_bp_clean_inactive_user' );
		}
	}

	/**
	 * Disable cron job.
	 */
	public function deactivate() {
		wp_unschedule_event( wp_next_scheduled( 'tosin_bp_clean_inactive_user' ), 'tosin_bp_clean_inactive_user', );
	}

	/**
	 * Deletes user periodically.
	 */
	public function auto_delete_users() {

		if ( ! function_exists( 'buddypress' ) ) {
			return;
		}

		// users with this role will be removed.
		$role = 'subscriber';
		// Number of days they have been registered but never logged in.
		$days_since_registered = 10;

		global $wpdb;
		$activity_table = buddypress()->members->table_name_last_activity;

		// find all inactive users.
		// These are the users who have registered but do not have an entry in activity table(for las_activity).
		$date_sql = $wpdb->prepare( "DATE_ADD(user_registered, INTERVAL +%d DAY ) <= UTC_TIMESTAMP() ", $days_since_registered );

		$inactive_user_ids = $wpdb->get_col( "SELECT ID from {$wpdb->users} WHERE ($date_sql) AND ID NOT IN ( SELECT DISTINCT( user_id ) FROM {$activity_table})" );

		if ( empty( $inactive_user_ids ) ) {
			return;
		}
		// query the WP for all users with subscriber role wwithin the inactive list.
		$query = new WP_User_Query(
			array(
				'role'    => $role,
				'number'  => - 1,
				'include' => $inactive_user_ids,
				'fields'  => 'ID',
			)
		);
		$subscribers = $query->get_results();

		if ( empty( $subscribers ) ) {
			return;
		}

		foreach ( $subscribers as $subscriber ) {
			wp_delete_user( $subscriber );
		}
	}
}

// init.
Tosin_BP_Inactive_User_Cleaner::boot();
