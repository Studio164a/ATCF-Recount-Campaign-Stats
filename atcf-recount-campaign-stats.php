<?php
/**
 * Plugin Name:			Recount Campaign Stats for Appthemer Crowdfunding
 * Plugin URI:			https://github.com/Studio164a/atcf-recount-backers/
 * Description:			A tool to let you force a recount of the backers for one of your campaigns.
 * Author:				Studio 164a
 * Author URI:			https://164a.com
 * Version:     		1.0.1
 * Text Domain: 		atcf-recount-backers
 * GitHub Plugin URI: 	https://github.com/Studio164a/atcf-recount-backers
 * GitHub Branch:    	master
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit; 


class ATCF_Recount_Backers {

	public function __construct() {
		add_action( 'atcf_metabox_campaign_stats_after', array( $this, 'add_recount_backers_button' ) );		
		add_action( 'atcf_metabox_campaign_stats_after', array( $this, 'add_recount_earnings_button' ) );
		add_action( 'edd_recount_backers', array( $this, 'recount_backers' ) );		
	}

	/**
	 * Add a recount button to the campaign stats meta box. 
	 *
	 * @param 	ATCF_Campaign 		$campaign
	 * @return 	void
	 * @since 	1.0.0
	 */
	public function add_recount_backers_button( $campaign ) {

		$args = array(
			'post'       	=> $campaign->ID,
			'action'     	=> 'edit',
			'edd_action' 	=> 'recount_backers',
		);
		
		$base_url = admin_url( 'post.php' );
		?>
		<p>
			<a class="button" href="<?php echo add_query_arg( $args, $base_url ) ?>"><?php _e( 'Recount Backers', 'atcf-recount-campaign-stats' ) ?></a>
		</p>
		<?php 
	}

	/**
	 * Add a recount earnings button to the campaign stats meta box. 
	 *
	 * @return 	void
	 * @access  public
	 * @since 	1.0.0
	 */
	public function add_recount_earnings_button( $campaign ) {

		if ( ! class_exists( 'EDD_Recount_Earnings' ) ) {	
			return;
		}	

		$args = array(
			'post'       	=> $campaign->ID,
			'action'     	=> 'edit',
			'edd_action' 	=> 'recount_earnings',
		);
		
		$base_url = admin_url( 'post.php' );
		?>
		<p>
			<a class="button" href="<?php echo add_query_arg( $args, $base_url ) ?>"><?php _e( 'Recount Earnings', 'atcf-recount-campaign-stats' ) ?></a>
		</p>
		<?php 
	}

	/**
	 * Recount backers.  
	 *
	 * @return 	void
	 * @access  public
	 * @since 	1.0.0
	 */
	public function recount_backers() {
		global $edd_logs, $wpdb;

		if ( empty( $_GET['post'] ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_products' ) ) {
			wp_die( 'Cheating' );
		}

		$campaign_id = absint( $_GET['post'] );

		if ( ! get_post( $campaign_id ) ) {
			return;
		}

		// Reset prices' bought count to 0 first
		$prices = edd_get_variable_prices( $campaign_id );

		foreach ( $prices as $key => $value ) {
			$prices[$key]['bought'] = 0;
		}

		$args = array(
			'post_parent' => $campaign_id,
			'log_type'    => 'sale',
			'nopaging'    => true,
			'fields'      => 'ids',
		);

		$log_ids     = $edd_logs->get_connected_logs( $args, 'sale' );
		
		if ( $log_ids ) {
			$log_ids     = implode( ',', $log_ids );
			$payment_ids = $wpdb->get_col( "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key='_edd_log_payment_id' AND post_id IN ($log_ids)" );
			unset( $log_ids );

			$payment_ids = implode( ',', $payment_ids );
			$payments = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE ID IN (" . $payment_ids . ") AND post_status IN ('publish','preapproval')" );
				
			foreach ( $payments as $payment_id ) {
				$payment_data = edd_get_payment_meta( $payment_id );
				$downloads    = maybe_unserialize( $payment_data[ 'downloads' ] );

				if ( ! is_array( $downloads ) ) {
					return;
				}	

				foreach ( $downloads as $download ) {

					if ( $download['id'] != $campaign_id ) {
						continue;
					}
					
					foreach ( $prices as $key => $value ) {
						$what = isset( $download[ 'options' ][ 'price_id' ] ) ? $download[ 'options' ][ 'price_id' ] : 0;

						if ( ! isset ( $prices[ $what ][ 'bought' ] ) ) {
							$prices[ $what ][ 'bought' ] = 0;
						}

						$current = $prices[ $what ][ 'bought' ];						

						if ( $key == $what ) {
							$prices[ $what ][ 'bought' ] = $current + $download[ 'quantity' ];							
						}
					}					
				}
			}	

			update_post_meta( $campaign_id, 'edd_variable_prices', $prices );		
		}
	}
}

new ATCF_Recount_Backers();