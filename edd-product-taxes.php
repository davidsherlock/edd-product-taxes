<?php
/**
 * Plugin Name:     Easy Digital Downloads - Product Taxes
 * Plugin URI:      https://sellcomet.com/downloads/product-taxes/
 * Description:     Apply different tax rates to physical goods and services.
 * Version:         1.0.0
 * Author:          Sell Comet
 * Author URI:      https://sellcomet.com
 * Text Domain:     edd-product-taxes
 *
 * @package         EDD\ProductTaxes
 * @author          Sell Comet
 * @copyright       Copyright (c) 2017, Sell Comet
 */


// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) exit;

if( !class_exists( 'EDD_Product_Taxes' ) ) {

    /**
     * Main EDD_Product_Taxes class
     *
     * @since       1.0.0
     */
    class EDD_Product_Taxes {

        /**
         * @var         EDD_Product_Taxes $instance The one true EDD_Product_Taxes
         * @since       1.0.0
         */
        private static $instance;

        /**
         * Get active instance
         *
         * @access      public
         * @since       1.0.0
         * @return      object self::$instance The one true EDD_Product_Taxes
         */
        public static function instance() {
            if( !self::$instance ) {
                self::$instance = new EDD_Product_Taxes();
                self::$instance->setup_constants();
                self::$instance->includes();
                self::$instance->load_textdomain();
                self::$instance->hooks();
            }

            return self::$instance;
        }


        /**
         * Setup plugin constants
         *
         * @access      private
         * @since       1.0.0
         * @return      void
         */
        private function setup_constants() {
            // Plugin version
            define( 'EDD_PRODUCT_TAXES_VER', '1.0.0' );

            // Plugin path
            define( 'EDD_PRODUCT_TAXES_DIR', plugin_dir_path( __FILE__ ) );

            // Plugin URL
            define( 'EDD_PRODUCT_TAXES_URL', plugin_dir_url( __FILE__ ) );
        }


        /**
         * Include necessary files
         *
         * @access      private
         * @since       1.0.0
         * @return      void
         */
        private function includes() {

            // Include miscellaneous functions
            require_once EDD_PRODUCT_TAXES_DIR . 'includes/functions/misc-functions.php';

            // Include tax functions
            require_once EDD_PRODUCT_TAXES_DIR . 'includes/functions/tax-functions.php';

            if ( is_admin() ) {
              require_once EDD_PRODUCT_TAXES_DIR . 'includes/admin/settings.php';
            }

        }


        /**
         * Run action and filter hooks
         *
         * @access      private
         * @since       1.0.0
         * @return      void
         */
        private function hooks() {

            if ( edd_use_product_taxes() ) {
              add_filter( 'edd_get_cart_item_tax', array( $this, 'get_cart_item_product_tax' ), 10, 4 );
              add_filter( 'edd_cart_item_tax_description', array( $this, 'get_cart_item_product_tax_description' ), 10, 3 );
              add_filter( 'edd_tax_rate', array( $this, 'get_product_tax_rate' ), 10, 4 );

              // Should we apply the tax rates to the fees?
              if ( edd_apply_fee_tax_rates() ) {
                add_filter( 'edd_get_cart_fee_tax', array( $this, 'get_cart_fee_product_tax' ), 10, 1 );
              }

              // Is commissions active and enabled?
              if ( edd_product_taxes_commissions_active() && edd_use_mitigate_tax_liability() ) {
                add_filter ('eddc_calc_commission_amount_args', array ( $this, 'adjust_commission_amount_args' ), 10, 1);
              }
            }

            // Handle licensing
            if( class_exists( 'EDD_License' ) ) {
                $license = new EDD_License( __FILE__, 'Product Taxes', EDD_PRODUCT_TAXES_VER, 'Sell Comet', null, 'https://sellcomet.com/', 248 );
            }
        }


        /**
         * Calculate the tax for an item in the cart.
         *
         * @since 1.0.0
         * @access public
         *
         * @param float $Taxes       Tax amount
         * @param array $download_id Download ID
         * @param array $options     Cart item options
         * @param float $subtotal    Cart item subtotal
         * @return float Tax amount
         */
        public function get_cart_item_product_tax( $tax, $download_id, $options, $subtotal ) {
            $tax = 0;

            if ( ! edd_download_is_tax_exclusive( $download_id ) ) {
              $country = ! empty( $_POST['billing_country'] ) ? $_POST['billing_country'] : false;
              $state   = ! empty( $_POST['card_state'] )      ? $_POST['card_state']      : false;

              if ( true === $this->download_has_product_taxes( $download_id ) ) {
                  $tax = edd_calculate_product_tax( $subtotal, $country, $state );
              } else {
                  $tax = edd_calculate_tax( $subtotal, $country, $state );
              }
            }

        		$tax = max( $tax, 0 );

        		return apply_filters( 'get_cart_item_product_tax', $tax, $download_id, $options, $subtotal );
        }


        /**
         * Cart Item Checkout Product Tax Description
         *
         * @since 1.0.0
         * @access public
         *
         * @param string $label Original cart item tax description
         * @param int    $item_id Download (cart item) ID number
         * @param array  $options Optional parameters, used for defining variable prices
         * @return string Fully formatted price
         */
        public function get_cart_item_product_tax_description( $label, $item_id, $options ) {

          if ( true == $this->download_has_product_taxes( $item_id ) ) {
            $label = '&nbsp;&ndash;&nbsp;';

            if ( edd_prices_show_tax_on_checkout() ) {
              $label .= sprintf( __( 'includes %s tax', 'easy-digital-downloads' ), edd_get_formatted_product_tax_rate() );
    				} else {
    					$label .= sprintf( __( 'excludes %s tax', 'easy-digital-downloads' ), edd_get_formatted_product_tax_rate() );
            }

          }

          return apply_filters( 'get_cart_item_product_tax_description', $label, $item_id, $options );
        }


        /**
         * Get tax applicable for fees.
         *
         * @since 1.0.0
         * @param  float $tax The total amount of tax
         * @access public
         * @return float Total taxable amount for fees
         */
        public function get_cart_fee_product_tax( $tax ) {
          $tax  = 0;
      		$fees = edd_get_cart_fees();

      		if ( $fees ) {
      			foreach ( $fees as $fee_id => $fee ) {
      				if ( ! empty( $fee['no_tax'] ) || $fee['amount'] < 0 ) {
      					continue;
      				}

              add_filter( 'edd_prices_include_tax', '__return_false' );
              // $tax += edd_calculate_product_tax( $fee['amount'] );
              remove_filter( 'edd_prices_include_tax', '__return_false' );
            }
          }

      		return apply_filters( 'get_cart_fee_product_tax', $tax );
        }


        /**
         * Get taxation rate - workaround to show correct tax rates on frontend
         *
         * @since 1.0.0
         * @param float $rate
         * @param bool  $country
         * @param bool  $state
         * @return mixed|void
         */
        public function get_product_tax_rate( $rate, $country, $state ) {
          global $post;

          $post_id = is_object( $post ) ? $post->ID : 0;

          // TODO: Remove need for global $post by passing in download_id

          if ( true === $this->download_has_product_taxes( $post_id ) ) {
            $rate = edd_get_unformatted_product_tax_rate( $country, $state );
          }

          return apply_filters( 'get_product_tax_rate', $rate, $country, $state );
        }


        /**
         * Should product taxes be applied to our download?
         *
         * @param  int  $download_id ID of download
         * @return boolean true if has product taxes, false otherwise
         */
        public function download_has_product_taxes( $download_id = 0 ) {

          // Does the download have "Product Taxes" enabled?
          $download_custom_tax_rates = edd_download_has_product_taxes( $download_id );

          if ( false === edd_product_taxes_integrations_disabled() ) {
            // Does the download have "Download As Services" checked?
            if ( class_exists( 'EDD_Downloads_As_Services' ) ) {
              $download_is_services = edd_download_is_service( $download_id );
            }

            // Does the download have "Simple Shipping" checked?
            if ( class_exists( 'EDD_Simple_Shipping' ) ) {
              $download_has_simple_shipping = edd_download_has_shipping_enabled( $download_id );
            }
          }

          $download_is_services         = isset( $download_is_services ) ? $download_is_services : false;
          $download_has_simple_shipping = isset( $download_has_simple_shipping ) ? $download_has_simple_shipping : false;

        	// Check if any conditions above are true
        	if ( $download_custom_tax_rates || $download_is_services || $download_has_simple_shipping ) {
        		return true;
        	}

        	return false;
        }


        /**
         * Add the item cart tax to the commission amount if "Mitigate Tax Liability" is enabled
         *
         * @since       1.0.0
         * @param       array $args The args passed to the eddc_calc_commission_amount function
         * @return      array $args The updated args
         */
        public function adjust_commission_amount_args( $args ) {

          if ( $this->download_has_product_taxes( $args['download_id'] ) ) {
            $args['price'] = $args['cart_item']['price'] + $args['cart_item']['tax'];
          }

          return $args;
        }


        /**
         * Internationalization
         *
         * @access      public
         * @since       1.0.0
         * @return      void
         */
        public function load_textdomain() {
            // Set filter for language directory
            $lang_dir = EDD_PRODUCT_TAXES_DIR . '/languages/';
            $lang_dir = apply_filters( 'edd_product_taxes_languages_directory', $lang_dir );

            // Traditional WordPress plugin locale filter
            $locale = apply_filters( 'plugin_locale', get_locale(), 'edd-product-taxes' );
            $mofile = sprintf( '%1$s-%2$s.mo', 'edd-product-taxes', $locale );

            // Setup paths to current locale file
            $mofile_local   = $lang_dir . $mofile;
            $mofile_global  = WP_LANG_DIR . '/edd-product-taxes/' . $mofile;

            if( file_exists( $mofile_global ) ) {
                // Look in global /wp-content/languages/edd-plugin-name/ folder
                load_textdomain( 'edd-product-taxes', $mofile_global );
            } elseif( file_exists( $mofile_local ) ) {
                // Look in local /wp-content/plugins/edd-plugin-name/languages/ folder
                load_textdomain( 'edd-product-taxes', $mofile_local );
            } else {
                // Load the default language files
                load_plugin_textdomain( 'edd-product-taxes', false, $lang_dir );
            }
        }

    }
} // End if class_exists check


/**
 * The main function responsible for returning the one true EDD_Product_Taxes
 * instance to functions everywhere
 *
 * @since       1.0.0
 * @return      \EDD_Product_Taxes The one true EDD_Product_Taxes
 *
 * @todo        Inclusion of the activation code below isn't mandatory, but
 *              can prevent any number of errors, including fatal errors, in
 *              situations where your extension is activated but EDD is not
 *              present.
 */
function EDD_Product_Taxes_load() {
    if( ! class_exists( 'Easy_Digital_Downloads' ) ) {
        if( ! class_exists( 'EDD_Extension_Activation' ) ) {
            require_once 'includes/classes/class.extension-activation.php';
        }

        $activation = new EDD_Extension_Activation( plugin_dir_path( __FILE__ ), basename( __FILE__ ) );
        $activation = $activation->run();
    } else {
        return EDD_Product_Taxes::instance();
    }
}
add_action( 'plugins_loaded', 'EDD_Product_Taxes_load' );
