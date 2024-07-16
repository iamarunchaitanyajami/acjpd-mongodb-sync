<?php
/**
 * Register Custom Cron.
 *
 * @package     acjpd-mongodb-sync
 * @sub-package WordPress
 */

namespace Acjpd\Mongodb\Cron;

/**
 * Class Cron Jobs.
 */
class Base {

	/**
	 * Initiate all the cron's.
	 *
	 * @return void
	 */
	public function init(): void {

		/**
		 * Add cron interval
		 */
		add_filter( 'cron_schedules', array( $this, 'add_cron_interval' ) );
	}

	/**
	 * Schedule cron.
	 *
	 * @param string $hook_name  Hook name.
	 * @param string $recurrence Time period.
	 * @param array  $args       Arguments.
	 *
	 * @return void
	 */
	public function schedule_cron( string $hook_name, string $recurrence, array $args = array() ): void {
		if ( ! wp_next_scheduled( $hook_name ) ) {
			wp_schedule_event( time(), $recurrence, $hook_name, $args );
		}
	}

	/**
	 * Add required cron intervals.
	 *
	 * @param array $schedules Wp Cron Schedules.
	 *
	 * @return array
	 */
	public function add_cron_interval( array $schedules ): array {
		$schedules[ ACJPD_MONGODB_PREFIX . 'import_every_fifteen_minutes' ] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => esc_html__( 'ACJ MongoDB SYNC Cron Import: Every Fifteen Minutes', 'acjpd-mongodb-sync' ),
		);

		return apply_filters( ACJPD_MONGODB_PREFIX . 'import_cron_schedules', $schedules );
	}

	/**
	 * Is Cron enabled.
	 *
	 * @return bool
	 */
	protected function is_cron_enabled(): bool {
		$enable = $this->get_setting_options( 'acjpd-cron-sync-enable', array() );

		return ! empty( $enable ) && isset( $enable[0] ) ? $enable[0] : ACJPD_MONGODB_ENABLE_CRON;
	}

	/**
	 * Get Mongodb settings.
	 *
	 * @param string $key          Options Key.
	 * @param mixed  $default_args Default value.
	 *
	 * @since v1.2.0
	 *
	 * @return mixed
	 */
	public function get_setting_options( string $key, mixed $default_args ): mixed {
		$option_key = sprintf( '_alchemy_options_%s', esc_html( $key ) );

		return is_multisite() ? alch_admin_get_saved_network_option( $option_key, $default_args ) : alch_get_option( $key, $default_args );
	}
}
