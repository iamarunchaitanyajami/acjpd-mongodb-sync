<?php
/**
 * Register Custom Term sync Cron.
 *
 * @package acjpd-mongodb-sync
 * @sub-package WordPress
 */

namespace Acjpd\Mongodb\Cron;

use Acjpd\Mongodb\TermSync;

/**
 * Class Term Cron Jobs.
 */
class Term extends Base {

	/**
	 * Hook Name.
	 *
	 * @var string
	 */
	public string $hook_name = 'term';

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
		add_action( $export_post_hook, array( $this, 'export_terms' ) );

		/**
		 * Schedules
		 */
		$this->schedule_cron( $export_post_hook, ACJPD_MONGODB_PREFIX . 'import_every_fifteen_minutes' );
	}

	/**
	 * Get terms with a specific non-empty meta key
	 *
	 * @param string $taxonomy The taxonomy slug or `null` to query all public taxonomies.
	 *
	 * @return array
	 */
	private function get_terms_with_meta( string $taxonomy ): array {
		/* Do Query */
		$term_args = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'fields'     => 'all',
			'count'      => true,
			'meta_query' => array( //phpcs:ignore
				'relation' => 'OR',
				array(
					'key'     => 'acjpd_mongodb_sync_inserted_id',
					'compare' => 'NOT EXISTS',
					'value'   => '',
				),
				array(
					'key'     => 'acjpd_mongodb_sync_inserted_id',
					'value'   => '',
					'compare' => '=',
				),
			),
		);

		$term_query = new \WP_Term_Query( $term_args );

		/* Do we have any terms? */
		if ( empty( $term_query->terms ) ) {
			return array();
		}

		/* Get the terms */
		return $term_query->terms;
	}

	/**
	 * Export posts.
	 *
	 * @return void
	 */
	public function export_terms(): void {
		$exclude_taxonomies = $this->get_setting_options( 'acjpd-sync-taxonomy-types', array() );
		if ( empty( $exclude_taxonomies ) ) {
			return;
		}

		/**
		 * Get all the list of terms that were not synced.
		 */
		$terms_data = array();
		foreach ( $exclude_taxonomies as $exclude_taxonomy ) {
			$terms      = $this->get_terms_with_meta( $exclude_taxonomy );
			$terms_data = array_merge( $terms_data, $terms );
		}

		if ( empty( $terms_data ) ) {
			return;
		}

		$total_terms = count( $terms_data );
		update_option( 'sync_untracked_terms', $total_terms );

		$synced_terms   = 0;
		$term_type_sync = new TermSync();
		foreach ( $terms_data as $term_data ) {
			if ( ! $term_data instanceof \WP_Term ) {
				continue;
			}

			$term_type_sync->sync_taxonomy( $term_data->term_id, 0, $term_data->taxonomy );
			++$synced_terms;
		}

		$terms_ids = wp_list_pluck( $terms_data, 'term_id' );
		if ( ! empty( $terms_ids ) ) {
			foreach ( $terms_ids as $terms_id ) {
				$terms_meta_list = get_term_meta( $terms_id, '', true );
				if ( empty( $terms_meta_list ) ) {
					continue;
				}

				foreach ( $terms_meta_list as $term_meta_key => $term_meta_value ) {
					$term_type_sync->sync_term_meta( 0, $terms_id, $term_meta_key, $term_meta_value );
				}
			}
		}

		add_action( 'shutdown', array( $term_type_sync, 'process_sync' ) );

		update_option( 'sync_untracked_terms', ( $total_terms - $synced_terms ) );
	}
}
