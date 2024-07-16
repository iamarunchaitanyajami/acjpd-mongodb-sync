<?php
/**
 * Register Custom Cron.
 *
 * @package acjpd-mongodb-sync
 * @sub-package WordPress
 */

namespace Acjpd\Mongodb\Cron;

use Acjpd\Mongodb\PostTypeSync;

/**
 * Class Post Cron Jobs.
 */
class Post extends Base {

	/**
	 * Hook Name.
	 *
	 * @var string
	 */
	public string $hook_name = 'post';

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
		$export_post_hook = sprintf( '%s%s', esc_attr( ACJPD_MONGODB_PREFIX ), esc_attr( $this->hook_name ) );
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
		add_action( $export_post_hook, array( $this, 'export_posts' ) );

		/**
		 * Schedules
		 */
		$this->schedule_cron( $export_post_hook, ACJPD_MONGODB_PREFIX . 'import_every_fifteen_minutes' );
	}

	/**
	 * Export posts.
	 *
	 * @return void
	 */
	public function export_posts(): void {
		$exclude_post_type = $this->get_setting_options( 'acjpd-sync-object-types', array() );
		if ( empty( $exclude_post_type ) ) {
			return;
		}

		$exclude_post_status = $this->get_setting_options( 'acjpd-sync-object-status', array() );
		if ( empty( $exclude_post_status ) ) {
			return;
		}

		/**
		 * Get all the list po posts that were not synced.
		 */
		$args = array(
			'post_type'      => $exclude_post_type,
			'post_status'    => $exclude_post_status,
			'posts_per_page' => 100,
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

		$data        = new \WP_Query( $args );
		$posts_found = $data->get_posts();
		if ( empty( $posts_found ) ) {
			return;
		}

		update_option( 'sync_untracked_posts', $data->found_posts );

		$synced_posts   = 0;
		$post_type_sync = new PostTypeSync();
		foreach ( $posts_found as $post_found ) {
			if ( ! $post_found instanceof \WP_Post ) {
				continue;
			}

			$post_type_sync->sync_post( $post_found->ID, $post_found );

			++$synced_posts;
		}

		$posts_ids = wp_list_pluck( $posts_found, 'ID' );
		if ( ! empty( $posts_ids ) ) {
			foreach ( $posts_ids as $post_id ) {
				if ( empty( $post_id ) ) {
					continue;
				}

				$posts_meta_list = get_post_meta( $post_id, '', true );
				if ( empty( $posts_meta_list ) ) {
					continue;
				}

				foreach ( $posts_meta_list as $post_meta_key => $post_meta_value ) {
					$post_type_sync->sync_post_meta( 0, $post_id, $post_meta_key, $post_meta_value );
				}
			}
		}

		add_action( 'shutdown', array( $post_type_sync, 'process_sync' ) );

		update_option( 'sync_untracked_posts', ( $data->found_posts - $synced_posts ) );
	}
}
