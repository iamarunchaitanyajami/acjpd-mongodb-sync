<?php
/**
 * Register Custom Term sync Cron.
 *
 * @package acj-mongodb-sync
 * @sub-package WordPress
 *
 * @since 1.0.6
 */

namespace Acj\Mongodb\Cron;

use Acj\Mongodb\UserSync;

/**
 * Class User Cron Jobs.
 */
class User extends Base {

	/**
	 * Hook Name.
	 *
	 * @var string
	 */
	public string $hook_name = 'user';

	/**
	 * Initiate all the cron's.
	 *
	 * @return void
	 */
	public function init(): void {
		parent::init();

		/**
		 * Add Hooks.
		 */
		$export_post_hook = sprintf( '%s%s', esc_attr( ACJ_MONGODB_PREFIX ), esc_attr( $this->hook_name ) );
		if ( ! $this->is_cron_enabled() ) {
			wp_clear_scheduled_hook( $export_post_hook );
			$crons = _get_cron_array();
			foreach ( $crons as $timestamp => $cron ) {
				if ( isset( $cron[ $export_post_hook ] ) ) {
					foreach ( $cron[ $export_post_hook ] as $hash => $data ) {
						wp_unschedule_event( $timestamp, $export_post_hook, $data['args'] );
					}
				}
			}

			return;
		}

		/**
		 * Actions.
		 */
		add_action( $export_post_hook, array( $this, 'export_users' ) );

		/**
		 * Schedules
		 */
		$this->schedule_cron( $export_post_hook, ACJ_MONGODB_PREFIX . 'import_every_fifteen_minutes' );
	}

	/**
	 * Export users.
	 *
	 * @return void
	 */
	public function export_users(): void {

		/**
		 * Get all the list user that were not synced.
		 */
		$args = array(
			'meta_query'     => array( //phpcs:ignore
				'relation' => 'OR',
				array(
					'key'     => 'acj_mongodb_sync_inserted_id',
					'compare' => 'NOT EXISTS',
					'value'   => '',
				),
				array(
					'key'     => 'acj_mongodb_sync_inserted_id',
					'value'   => '',
					'compare' => '=',
				),
			),
		);

		$user_query = new \WP_User_Query( $args );
		$authors    = $user_query->get_results();
		if ( empty( $authors ) ) {
			return;
		}

		update_option( 'sync_untracked_users', count( $authors ) );

		$synced_users   = 0;
		$user_type_sync = new UserSync();
		foreach ( $authors as $author ) {
			if ( ! $author instanceof \WP_User ) {
				continue;
			}

			$user_type_sync->sync_user( $author->ID, $author->data );

			++$synced_users;
		}

		$users_ids = wp_list_pluck( $authors, 'ID' );
		if ( ! empty( $users_ids ) ) {
			foreach ( $users_ids as $user_id ) {
				if ( empty( $user_id ) ) {
					continue;
				}

				$users_meta_list = get_user_meta( $user_id, '', true );
				if ( empty( $users_meta_list ) ) {
					continue;
				}

				foreach ( $users_meta_list as $user_meta_key => $user_meta_value ) {
					$user_type_sync->sync_user_meta( 0, $user_id, $user_meta_key, $user_meta_value );
				}
			}
		}

		add_action( 'shutdown', array( $user_type_sync, 'process_sync' ) );

		update_option( 'sync_untracked_users', ( count( $authors ) - $synced_users ) );
	}
}
