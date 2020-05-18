<?php
/**
 * Plugin Name: Down Payment Calculator
 * Plugin URI: https://wahyuwibowo.com/projects/down-payment-calculator/
 * Description: Calculate an estimated down payment.
 * Author: Wahyu Wibowo
 * Author URI: https://wahyuwibowo.com
 * Version: 1.0
 * Text Domain: down-payment-calculator
 * Domain Path: languages
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) {
    exit;
}

class Down_Payment_Calculator {
    
    private static $_instance = NULL;
    
    /**
     * Initialize all variables, filters and actions
     */
    public function __construct() {
        add_action( 'init',                         array( $this, 'load_plugin_textdomain' ), 0 );
        add_action( 'wp_enqueue_scripts',           array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_ajax_dpc_calculate',        array( $this, 'calculate' ) );
        add_action( 'wp_ajax_nopriv_dpc_calculate', array( $this, 'calculate' ) );
        add_filter( 'http_request_args',            array( $this, 'dont_update_plugin' ), 5, 2 );
        
        add_shortcode( 'down_payment_calculator', array( $this, 'add_shortcode' ) );
    }
    
    /**
     * retrieve singleton class instance
     * @return instance reference to plugin
     */
    public static function instance() {
        if ( NULL === self::$_instance ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    
    public function load_plugin_textdomain() {
        $locale = is_admin() && function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();
        $locale = apply_filters( 'plugin_locale', $locale, 'down-payment-calculator' );
        
        unload_textdomain( 'down-payment-calculator' );
        load_textdomain( 'down-payment-calculator', WP_LANG_DIR . '/down-payment-calculator/down-payment-calculator-' . $locale . '.mo' );
        load_plugin_textdomain( 'down-payment-calculator', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
    }
    
    public function dont_update_plugin( $r, $url ) {
        if ( 0 !== strpos( $url, 'https://api.wordpress.org/plugins/update-check/1.1/' ) ) {
            return $r; // Not a plugin update request. Bail immediately.
        }
        
        $plugins = json_decode( $r['body']['plugins'], true );
        unset( $plugins['plugins'][plugin_basename( __FILE__ )] );
        $r['body']['plugins'] = json_encode( $plugins );
        
        return $r;
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script( 'dpc-frontend', plugin_dir_url( __FILE__ ) . 'assets/js/frontend.js', array( 'jquery' ) );
        wp_enqueue_style( 'dpc-frontend', plugin_dir_url( __FILE__ ) . 'assets/css/frontend.css' );
        
        wp_localize_script( 'dpc-frontend', 'Down_Payment_Calculator', array(
            'ajaxurl'     => admin_url( 'admin-ajax.php' ),
            'nonce'       => wp_create_nonce( 'down_payment_calculator' ),
            'calculating' => __( 'Calculating...', 'down-payment-calculator' )
        ) );
    }
    
    public function add_shortcode() {
        $output = '<div class="dpc-container">';
        
        $output .= '<div class="dpc-form-input">';
        $output .= '<table>';
        $output .= sprintf( '<tr><td>%s</td><td><input type="number" min="0" id="dpc-home-price" value="200000" step="1000"></td></tr>', __( 'Home Price ($)', 'down-payment-calculator' ) );
        $output .= sprintf( '<tr><td>%s</td><td><input type="number" min="0" max="100" id="dpc-down-payment" value="20" step="0.01"></td></tr>', __( 'Down Payment (%)', 'down-payment-calculator' ) );
        $output .= sprintf( '<tr><td>%s</td><td><input type="number" min="0" max="100" id="dpc-closing-costs" value="3" step="0.01"></td></tr>', __( 'Closing Costs (%)', 'down-payment-calculator' ) );
        $output .= sprintf( '<tr><td>%s</td><td><input type="number" min="0" max="100" id="dpc-interest-rate" value="3.52" step="0.01"></td></tr>', __( 'Interest Rate (%)', 'down-payment-calculator' ) );
        $output .= sprintf( '<tr><td>%s</td><td><input type="number" min="0" id="dpc-loan-term" value="30"></td></tr>', __( 'Loan Term (years)', 'down-payment-calculator' ) );        
        $output .= '</table>';
        $output .= sprintf( '<div class="dpc-calculate"><button id="dpc-calculate-button">%s</button></div>', __( 'Calculate', 'down-payment-calculator' ) );
        $output .= '</div>';
        
        $output .= '<div class="dpc-result">';
        $output .= sprintf( '<div class="dpc-result-title">%s</div>', __( 'Result', 'down-payment-calculator' ) );
        $output .= '<table>';
        $output .= sprintf( '<tr><td>%s</td><td><span id="dpc-down-payment-result">$40.000</span></td></tr>', __( 'Down Payment', 'down-payment-calculator' ) );
        $output .= sprintf( '<tr><td>%s</td><td><span id="dpc-closing-costs-result">$6.000</span></td></tr>', __( 'Closing Costs', 'down-payment-calculator' ) );
        $output .= sprintf( '<tr><td>%s</td><td><span id="dpc-upfront-costs-result">$46.000</span></td></tr>', __( 'Upfront Costs = Down Payment + Closing Costs', 'down-payment-calculator' ) );
        $output .= sprintf( '<tr><td>%s</td><td><span id="dpc-loan-amount-result">$160.000</span></td></tr>', __( 'Loan Amount', 'down-payment-calculator' ) );
        $output .= sprintf( '<tr><td>%s</td><td><span id="dpc-monthly-payment-result">$720</span></td></tr>', __( 'Monthly Payment', 'down-payment-calculator' ) );        
        $output .= '</table>';
        $output .= '</div>';
        
        $output .= '</div>';
        
        return $output;
    }
    
    public function calculate() {
        check_ajax_referer( 'down_payment_calculator', 'nonce' );
        
        $input_home_price    = floatval( $_POST['home_price'] );
        $input_down_payment  = floatval( $_POST['down_payment'] );
        $input_closing_costs = floatval( $_POST['closing_costs'] );
        $input_interest_rate = floatval( $_POST['interest_rate'] );
        $input_loan_term     = floatval( $_POST['loan_term'] );
        
        $down_payment = $input_down_payment / 100 * $input_home_price;
        $closing_costs = $input_closing_costs / 100 * $input_home_price;
        $upfront_costs = $down_payment + $closing_costs;
        $loan_amount = $input_home_price - $down_payment;
        
        $value1 = ( $input_interest_rate / 100 ) * pow( ( 1 + ( $input_interest_rate / 100 ) ), $input_loan_term );
        $value2 = pow( ( 1 + ( $input_interest_rate / 100 ) ), $input_loan_term ) - 1;
        $monthly_payment = $loan_amount * ( $value1 / $value2 ) / 12;
        
        $down_payment    = '$' . number_format( $down_payment );
        $closing_costs   = '$' . number_format( $closing_costs );
        $upfront_costs   = '$' . number_format( $upfront_costs );
        $loan_amount     = '$' . number_format( $loan_amount );
        $monthly_payment = '$' . number_format( $monthly_payment );
        
        wp_send_json_success( array( 
            'down_payment'    => $down_payment,
            'closing_costs'   => $closing_costs,
            'upfront_costs'   => $upfront_costs,
            'loan_amount'     => $loan_amount,
            'monthly_payment' => $monthly_payment
        ) );
    }

}

Down_Payment_Calculator::instance();
