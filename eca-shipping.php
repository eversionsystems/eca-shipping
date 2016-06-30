<?php
/*
Plugin Name: ECA Shipping Method
Plugin URI: http://eversionsystems.com
Description: Custom shipping based on cart total
Version: 1.0
Author: Andrew Schultz
Author URI: http://eversionsystems.com
License: GPL2
*/

/**
 * Check if WooCommerce is active
 */
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

	function eca_shipping_init() {
		if ( ! class_exists( 'WC_ECA_Shipping' ) ) {
			class WC_ECA_Shipping extends WC_Shipping_Method {
				
				private $shipping_fields_num = 7;
				
				/**
				 * Constructor for your shipping class
				 *
				 * @access public
				 * @return void
				 */
				public function __construct() {
					$this->id                 = 'eca_shipping'; // Id for your shipping method. Should be uunique.
					$this->method_title       = __( 'ECA Shipping' );  // Title shown in admin
					$this->method_description = __( 'Flat rate shipping based on cart price' ); // Description shown in admin
					//$this->enabled            = "yes"; // This can be added as an setting but for this example its forced enabled
					//$this->title              = "ECA Flat Rate"; // This can be added as an setting but for this example its forced.

					$this->init();
				}

				/**
				 * Init your settings
				 *
				 * @access public
				 * @return void
				 */
				function init() {
					// Load the settings API
					$this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings
					$this->init_settings(); // This is part of the settings API. Loads settings you previously init.

					// Define user set variables
					$this->title = $this->get_option( 'title' );
					//$this->shipping_costs = isset( $this->settings['shipping_costs'] ) ? $this->settings['shipping_costs'] : array();
					
					// Save settings in admin if you have any defined
					add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
				}
				
				/**
				 * init_form_fields function.
				 *
				 * @access public
				 * @return void
				 */
				public function init_form_fields() {
					global $woocommerce;
					$shipping_desc = __( 'Format in low|high|cost', 'wc_eca_shipping' ).'<br/>'.__( 'eg 0|20|7.5', 'wc_eca_shipping' );

					$settings  = array(
						'enabled'          => array(
							'title'           => __( 'Enable/Disable', 'wc_eca_shipping' ),
							'type'            => 'checkbox',
							'label'           => __( 'Enable this shipping method', 'wc_eca_shipping' ),
							'default'         => 'no'
						),
						'title'            => array(
							'title'           => __( 'Method Title', 'wc_eca_shipping' ),
							'type'            => 'text',
							'description'     => __( 'This controls the title which the user sees during checkout.', 'wc_eca_shipping' ),
							'default'         => __( 'Flat Rate', 'wc_eca_shipping' )
						),
						'australian_costs' 			=> array(
							'title'			=> __( 'Australian Shipping Costs', 'wc_eca_shipping' ),
							'type'			=> 'title',
							'description'   => __( 'Add Australian shipping charges here') )
					);
						
					for ($x = 1; $x <= $this->shipping_fields_num; $x++) {
						$settings[ 'australian_shipping_cost_' . $x ] = array(
							'title'       => sprintf( __( 'Shipping Cost %s', 'woocommerce' ), $x ),
							'type'        => 'text',
							'placeholder' => __( 'N/A', 'wc_eca_shipping' ),
							'description' => $shipping_desc,
							'default'     => '',
							'css'      => 'width:150px;',
							'desc_tip'    => true
						);
					}
					
					$settings['australian_express_costs'] = array(
							'title'				=> __( 'Australian Express Shipping Costs', 'wc_eca_shipping' ),
							'type'				=> 'title',
							'description'   	=> __( 'Add Express shipping charges here') 
					);
					
					for ($x = 1; $x <= $this->shipping_fields_num; $x++) {
						$settings[ 'australian_express_shipping_cost_' . $x ] = array(
							'title'       => sprintf( __( 'Shipping Cost %s', 'wc_eca_shipping' ), $x ),
							'type'        => 'text',
							'placeholder' => __( 'N/A', 'wc_eca_shipping' ),
							'description' => $shipping_desc,
							'default'     => '',
							'css'      => 'width:150px;',
							'desc_tip'    => true
						);
					}
					
					$settings['international_costs'] = array(
							'title'				=> __( 'International Shipping Costs', 'wc_eca_shipping' ),
							'type'				=> 'title',
							'description'   	=> __( 'Add International shipping charges here') 
					);
					
					for ($x = 1; $x <= $this->shipping_fields_num; $x++) {
						$settings[ 'international_shipping_cost_' . $x ] = array(
							'title'       => sprintf( __( 'Shipping Cost %s', 'wc_eca_shipping' ), $x ),
							'type'        => 'text',
							'placeholder' => __( 'N/A', 'wc_eca_shipping' ),
							'description' => $shipping_desc,
							'default'     => '',
							'css'      => 'width:150px;',
							'desc_tip'    => true
						);
					}
					
					$this->form_fields = $settings;
				}

				/**
				 * calculate_shipping function.
				 *
				 * @access public
				 * @param mixed $package
				 * @return void
				 */
				public function calculate_shipping( $package ) {
					global $woocommerce;
					
					//Australisian country codes
					$local_country_codes = array('AU');
					
					//Get customer's billing country 2 digit code
					$billing_country_code =	$woocommerce->customer->get_country();
					
					//Get total cart value minus subscriptions and memberships (virtual products/no shipping)
					$total_cost = $this->get_package_item_total_cost($package);
					
					//Simplify postage cost to just the entire cart contents total
					//$total_cost = $woocommerce->cart->subtotal;
					
					//Loop over Australian shipping costs in form "lower|upper|cost"
					for ($x = 1; $x <= $this->shipping_fields_num; $x++) {
						if(in_array($billing_country_code, $local_country_codes))
							$shipping_cost_string = $this->get_option( 'australian_shipping_cost_' . $x );
						else
							$shipping_cost_string = $this->get_option( 'international_shipping_cost_' . $x );
						
						$shipping_params_array = explode('|', $shipping_cost_string);
						$lower = $shipping_params_array[0];
						$upper = $shipping_params_array[1];
						$shipping_cost = $shipping_params_array[2];
						
						//Round price to whole number
						//$total_cost = round($total_cost);
						
						if($total_cost >= $lower && $total_cost <= $upper) {
							//Shipping cost found return value $shipping_cost
							break;
						}
					}
					
					$rate = array(
						'id' => $this->id,
						'label' => $this->title,
						'cost' => $shipping_cost,
						'calc_tax' => 'per_item'
					);

					// Register the rate
					$this->add_rate( $rate );
					
					//Register Express Shipping Rate
					for ($x = 1; $x <= $this->shipping_fields_num; $x++) {
						if(in_array($billing_country_code, $local_country_codes))
							$shipping_cost_string = $this->get_option( 'australian_express_shipping_cost_' . $x );
						
						$shipping_params_array = explode('|', $shipping_cost_string);
						$lower = $shipping_params_array[0];
						$upper = $shipping_params_array[1];
						$shipping_cost = $shipping_params_array[2];
						
						if($total_cost >= $lower && $total_cost <= $upper) {
							//Shipping cost found return value $shipping_cost
							break;
						}
					}
					
					if(in_array($billing_country_code, $local_country_codes)) {
						$rate_express = array(
							'id' => 'eca_shipping_express',
							'label' => 'Express',
							'cost' => $shipping_cost,
							'calc_tax' => 'per_item'
						);

						$this->add_rate( $rate_express ); // Register the express rate
					}
					else
						unset($rates['eca_shipping_express'] );
				}
				
				/**
				 * Get items requiring postage total cost
				 * @param  array $package
				 * @return int
				 */
				public function get_package_item_total_cost( $package ) {
					$total_cost = 0;
					foreach ( $package['contents'] as $item_id => $values ) {
						if ( $values['quantity'] > 0 && $values['data']->needs_shipping() ) {
							$total_cost += $values['data']->get_price() * $values['quantity'];
						}
					}
					return $total_cost;
				}
			}
		}
	}

	add_action( 'woocommerce_shipping_init', 'eca_shipping_init' );

	function add_eca_shipping( $methods ) {
		$methods[] = 'WC_ECA_Shipping';
		return $methods;
	}

	add_filter( 'woocommerce_shipping_methods', 'add_eca_shipping' );
	
	function myplugin_admin_messages() {
		add_settings_error( 'wc_eca_shipping', 'wc_eca_shipping_shipping_rate_updated_fail', __('There was a problem updating your shipping rate, please try again.', 'wc_eca_shipping'), 'error' );
		settings_errors( 'wc_eca_shipping_notices' );
		write_log('ECA Shipping Admin save fired');
	}

	//add_action('admin_notices', 'myplugin_admin_messages');
}

?>