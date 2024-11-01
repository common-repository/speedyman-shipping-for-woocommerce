<?php
/**
 * Plugin Name: Speedyman Shipping for WooCommerce
 * Description: Permite el uso de envios mediante plataforma Speedyman
 * Author: Speedyman
 * Author URI: https://speedyman.cl/
 * Version: 1.0
 *
 * Speedyman Shipping for WooCommerce is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.

 * Speedyman Shipping for WooCommerce is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with Speedyman Shipping for WooCommerce. If not, see https://speedyman.cl/terminos-y-condiciones/.
 */

if (!defined('WPINC')) {
    die();
}
$plugin = plugin_basename(__FILE__);

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

    require dirname(__FILE__).'/inc/speedyman.class.php';

    add_action('init', 'speedyman_shipping_method');
    add_filter('woocommerce_shipping_methods', array('Speedyman', 'add_shipping_method'));
    add_filter("plugin_action_links_$plugin", array('Speedyman', 'setConfigLink') );
    add_filter( 'plugin_row_meta', array('Speedyman', 'add_link_to_register'), 10, 2 );

    /**
     * Proper way to enqueue scripts and styles
     */
    function SSFW_enqueue_scripts() {
        wp_enqueue_style( 'SSFW_daterangepicker_style', plugins_url('/speedyman-shipping-for-woocommerce/css/daterangepicker.css'), array() , null, false);
        wp_enqueue_style( 'SSFW_calendar_style', plugins_url('/speedyman-shipping-for-woocommerce/css/calendar.css'), array() , null, false);
        wp_enqueue_style( 'SSFW_bootstrap_style', plugins_url('/speedyman-shipping-for-woocommerce/css/bootstrap.css'), array() , null, false);
        wp_enqueue_style( 'SSFW_pooper_script', plugins_url('/speedyman-shipping-for-woocommerce/js/popper.min.js'), array() , null, false);
        wp_enqueue_style( 'SSFW_bootstrap_script', plugins_url('/speedyman-shipping-for-woocommerce/js/bootstrap.min.js'), array() , null, false);
        wp_enqueue_script( 'SSFW_moment_script', plugins_url('/speedyman-shipping-for-woocommerce/js/moment.min.js'), array() , null, false);
        wp_enqueue_script( 'SSFW_calendar_script', plugins_url('/speedyman-shipping-for-woocommerce/js/calendar.js'), array('jquery') , null, false);
        wp_enqueue_script( 'SSFW_daterangepicker_script', plugins_url('/speedyman-shipping-for-woocommerce/js/daterangepicker.min.js'), array('jquery') , null, false);
    }
    add_action('wp_enqueue_scripts', 'SSFW_enqueue_scripts');

    add_filter('woocommerce_checkout_fields', 'speedyman_shipping_fields');
    function speedyman_shipping_fields($fields) {
        global $woocommerce;

//        $logger = wc_get_logger();
//        $context = array( 'source' => 'speedyman-plugin' );

        $speedyman = new Speedyman();
        $stations = $speedyman->station_options();
        $schedule = $speedyman->schedule();
        $shopInfo = $speedyman->getShopInfo();

        $fields['billing']['billing_estacion'] = array(
            'type' => 'select',
            'options' => $stations,
            'required' => false,
            'label' => __( 'Estación' . ' <span href="#" data-toggle="tooltip" data-placement="top" title="Estación de metro: Elige la estación de metro donde deseas recibir tu pedido. La entrega se hará en el cambio de anden o torniquetes. (Si es posible, por favor elige una estación que este cerca del centro o en la Linea 1, si no te acomoda, no importa, elige la estación que quieras :) )">'
                            . '<span class="glyphicon glyphicon-question-sign"></span>' . '</span>' . '<abbr class="required" title="required">*</abbr>'),
            'priority' => 120,
            'class' => array('speedyman', 'speedyman-stations')
        );
        $fields['billing']['billing_delivery_date'] = array(
            'type' => 'text',
            'required' => true,
            'label' => __( 'Fecha Entrega' . ' <span href="#" data-toggle="tooltip" data-placement="top" title="Fecha de entrega: Elige en que día quieres recibir tu pedido.">' . '<span class="glyphicon glyphicon-question-sign"></span>' . '</span>' ),
            'priority' => 130,
            'class' => array('speedyman'),
            'style' => __('width: 100%;')
        );
        $fields['billing']['billing_delivery_time'] = array(
            'type' => 'time',
            'required' => true,
            'label' => __( 'Rango Horario para entrega' . ' <span href="#" data-toggle="tooltip" data-placement="top" title="Rango horario para entrega: Por favor elige el rango de horas más amplio posible. Tu repartidor te contactará por whatsapp indicando una hora exacta de entrega respetando el rango que selecciones aquí para que no tengas que esperar.">' . '<span class="glyphicon glyphicon-question-sign"></span>' . '</span>' ),
            'priority' => 140,
            'class' => array('speedyman'),
            'style' => __('width: 100%;')
        );

        add_filter( 'woocommerce_cart_needs_shipping_address', '__return_false');
        add_action( 'wp_footer', function() use ($schedule, $shopInfo){
            ?>

            <script>
                jQuery(($) => {

                    $(document).ready(function(){
                        $('[data-toggle="tooltip"]').tooltip();
                    });

                    let schedule = <?php echo json_encode($schedule) ?>;
                    let shopInfo = <?php echo json_encode($shopInfo) ?>;
                    //console.log(schedule);
                    // console.log(shopInfo);
                    let address_valid = false;
                    //rango fechas delivery
                    startDate = moment(schedule.filter(x => (x.fromR != "" && x.toR != "")).shift().dateD, "MM/DD/YYYY");
                    endDate = moment(schedule.filter(x => (x.fromR != "" && x.toR != "")).pop().dateD, "MM/DD/YYYY");
                    //dias no disponibles para envío
                    no_work = schedule.filter(x => (x.fromR == "" && x.toR == "")).map(x => ({'start':moment(x.dateR,"MM/DD/YYYY"), 'end':moment(x.dateR,"MM/DD/YYYY")}));

                    //locale calendario desde el user language del navegador
                    var userLanguage = navigator.language || navigator.userLanguage;

                    //Calendario
                    $('input[name="billing_delivery_date"]').calendario({
                        inline: true,
                        disabledRanges:no_work,
                        singleDate: true,
                        locale: userLanguage,
                        minDate: startDate,
                        maxDate: endDate,
                        showTimePickers: false,
                        format: "x",
                    });

                    //TimePicker
                    // Doc: https://rettica.com/calentim/docs/readme.html

                    let today = moment();
                    let timeSplit = moment(shopInfo['startPickUp'], 'HH:mm').add(90, 'minutes');
                    let timeSplitAM = timeSplit;
                    if(shopInfo['startPickUpAM']) {
                        timeSplitAM = moment(shopInfo['startPickUpAM'], 'HH:mm').add(90, 'minutes');
                    }

                    var timepicker = $('input[name="billing_delivery_time"]').calendario({
                        inline: true,
                        showCalendars: false,
                        minuteSteps: 15,
                        hourFormat: 24,
                        reverseTimepickerArrows: true,
                        limitTimeForDay: function (day) {
                            if(day.isoWeekday() === today.isoWeekday()) {
                                return {
                                    start: { hour: timeSplitAM.diff(today) < 0? timeSplitAM.hours(): timeSplit.hours(),
                                        minute: timeSplitAM.diff(today) < 0? timeSplitAM.minutes(): timeSplit.minutes(),
                                        ampm: null
                                    }, end: { hour: 20, minute: 0, ampm: null } };
                            } else {
                                return {
                                    start: { hour: timeSplitAM.hours() < timeSplit.hours()? timeSplitAM.hours(): timeSplit.hours(),
                                        minute: timeSplitAM.hours() < timeSplit.hours()? timeSplitAM.minutes(): timeSplit.minutes(),
                                        ampm: null
                                    }, end: { hour: 20, minute: 0, ampm: null } };
                            }
                        },
                        format: "HH:mm",
                    });


                    $('#billing_country, #billing_address_1, #billing_city, #billing_state').on('change', () => {
                        address_valid = false;
                        $( 'body' ).trigger( 'update_checkout' );
                    });

                    $('.speedyman').hide();


                    $('body').on( 'updated_checkout', () => {

                        // console.log($("input[name='shipping_method[0]']:checked").val());

                        if($("input[name='shipping_method[0]']:checked").val()==='speedyman-domicilio'){
                            $('.speedyman').show();
                            $('.speedyman').addClass('validate-required');
                            $('.state').addClass('validate-state');
                            $('.postcode').addClass('validate-postcode');
                            $('.speedyman-stations').hide();

                            if(!address_valid && $('#billing_country').val() != '' && $('#billing_address_1').val() != '' && $('#billing_city').val() != '' && $('#billing_state').val() != ''){
                                address = $('#billing_address_1').val()+', '+$('#billing_address_2').val()+', '+$('#billing_city').val()+', '+$('#billing_state').val();
                                var data = {
                                    'address' : address
                                }
                                fetch("<?php echo get_bloginfo('url') ?>/wp-json/speedyman/address", {
                                    method: 'POST',
                                    body: JSON.stringify(data),
                                    headers:{
                                        'Content-Type': 'application/json'
                                    }
                                })
                                    .then(r => r.json())
                                    .then(json => {
                                        console.log(json)
                                        if(typeof json.address !== 'undefined'){
                                            address_valid = true;
                                        }
                                        else {
                                            alert('La dirección indicada no puede usarse para delivery. Intente con otra dirección, ó intente recibir en estación de metro');
                                            $('#billing_country, #billing_address_1, #billing_city, #billing_state').val('');
                                            address_valid = false;
                                        }

                                    })
                            }
                        }
                        else if ($("input[name='shipping_method[0]']:checked").val()==='speedyman-metro') {
                            $('.speedyman').show();
                            $('.speedyman').addClass('validate-required');
                            $('.state').removeClass('validate-state');
                            $('.postcode').removeClass('validate-postcode');
                            $('.speedyman-stations').show();
                        } else {
                            $('.speedyman').hide();
                            $('.speedyman').removeClass('validate-required');
                            $('.state').removeClass('validate-state');
                            $('.postcode').removeClass('validate-postcode');
                            $('.speedyman-stations').hide();
                        }
                    });
                });
            </script>
            <?php
        } );

        return $fields;

    }

    add_action('woocommerce_after_checkout_validation', 'SSFW_validate_checkout', 10, 2);
    function SSFW_validate_checkout($data, $errors) {
        // Do your data processing here and in case of an
        // error add it to the errors array like:

        $logger = wc_get_logger();
        $context = array( 'source' => 'speedyman-plugin' );
        $logger->debug('Time: ', $context);
        $logger->debug($data['billing_delivery_time'], $context);

        list($start, $end) = explode(" - ", $data['billing_delivery_time']);

        $datetime1 = new DateTime('2020-01-01 ' . $start . ':00');
        $datetime2 = new DateTime('2020-01-01 ' . $end . ':00');
        $interval = $datetime1->diff($datetime2);
        $minutes = $interval->h * 60;
        $minutes += $interval->i;

        $logger->debug('Station: ', $context);
        $logger->debug($data['billing_estacion'], $context);

        if(strcmp($data['billing_estacion'], '') === 0) {
            $errors->add( 'validation', __( 'Debes elegir una estación de metro.' ));
        }

        if($start > $end) {
            $errors->add( 'validation', __( 'El rango de tiempo debe ser válido.' ));
        } elseif ($minutes < 15) {
            $errors->add( 'validation', __( 'El rango de tiempo debe ser al menos 15 minutos.' ));
        }
    }


    add_action( 'rest_api_init', 'SSFW_get_address' );
    function SSFW_get_address() {
        register_rest_route( 'speedyman', '/address', array(
            'methods' => 'POST',
            'callback' => 'SSFW_address_callback',
        ));
    }
    function SSFW_address_callback( $request_data ) {
        $parameters = $request_data->get_params();
        $address = $parameters['address'];
        $speedyman = new Speedyman();

        return $speedyman->address($address);
    }

    add_action('woocommerce_checkout_process', 'SSFW_process_shipping');
    function SSFW_process_shipping(){

    }

    add_action( 'woocommerce_checkout_order_processed', 'SSFW_is_speedyman_delivery',  1, 1  );
    function SSFW_is_speedyman_delivery( $order_id ){
        // https://docs.woocommerce.com/document/tutorial-customising-checkout-fields-using-actions-and-filters/

        if(count(WC()->session->get( 'chosen_shipping_methods' )) > 0 && (strcmp(WC()->session->get( 'chosen_shipping_methods' )[0], 'speedyman-metro') === 0 || strcmp(WC()->session->get( 'chosen_shipping_methods' )[0], 'speedyman-domicilio') === 0)) {
            $shipping_method_id = '';
            $shipping_method_instance_id = '';
//            $logger = wc_get_logger();
//            $context = array( 'source' => 'speedyman-plugin' );

            $order = wc_get_order( $order_id );

            $order_email = $order->get_billing_email();
//            $logger->debug('Shipping email', $context);
//            $logger->debug($order_email, $context);

            $order_phone = $order->get_billing_phone();
//            $logger->debug('Shipping phone', $context);
//            $logger->debug($order_phone, $context);

            $order_billing_delivery_date = get_post_meta( $order->get_id(), '_billing_delivery_date', true );
//            $logger->debug('Delivery Date:', $context);
//            $logger->debug($order_billing_delivery_date, $context);

            $order_billing_delivery_time = get_post_meta( $order->get_id(), '_billing_delivery_time', true );
//            $logger->debug('Delivery Time:', $context);
//            $logger->debug($order_billing_delivery_time, $context);
            list($start, $end) = explode(" - ", $order_billing_delivery_time);
//            $date = date_create();
//            date_timestamp_set($date, $start/1000);
//            $start = date_format($date, 'H:i');
//            date_timestamp_set($date, $end/1000);
//            $end = date_format($date, 'H:i');
//            $logger->debug($start . ' - ' . $end, $context);

            $order_shipping_first_name = $order->get_billing_first_name();
//            $logger->debug('Shipping first name', $context);
//            $logger->debug($order_shipping_first_name, $context);

            $order_shipping_last_name = $order->get_billing_last_name();
//            $logger->debug('Shipping last name', $context);
//            $logger->debug($order_shipping_last_name, $context);

            $order_shipping_city = $order->get_billing_city();
//            $logger->debug('Shipping City', $context);
//            $logger->debug($order_shipping_city, $context);

            $order_shipping_country = $order->get_billing_country();
//            $logger->debug('Shipping Country', $context);
//            $logger->debug($order_shipping_country, $context);

            $weight = 0;
            $description = '';
            $long = 0;
            $width = 0;
            $height = 0;

//            $logger->debug(sizeof( $order->get_items() ), $context);
            foreach ( $order->get_items() as $item_id => $item ) {
//                $logger->debug('New item:', $context);
                $quantity = $item->get_quantity();
                $description = $item->get_name();
                $product = $item->get_product();
                $product_weight = $product->get_weight();
                $weight += floatval( $product_weight * $quantity );
                $long = $product->get_length();
                $width = $product->get_width();
                $height = $product->get_height();
//                $logger->debug('Weight: ' . $weight, $context);
//                $logger->debug('Long: ' . $long, $context);
//                $logger->debug('Width: ' . $width, $context);
//                $logger->debug('Height: ' . $height, $context);
            }

            $obj = [
                'email' => $order_email,
                'phone' => $order_phone,
                'deliveryDate' => intval($order_billing_delivery_date),
                'startDelivery' => $start,
                'endDelivery' => $end,
                'name' => $order_shipping_first_name . ' ' . $order_shipping_last_name,
                'city' => $order_shipping_city,
                'country' => $order_shipping_country,
                'value' => floatval($order->get_total()),
                'description' => $description,
                'weight' => floatval($weight),
                'long' => floatval($long),
                'width' => floatval($width),
                'height' => floatval($height),
                'normalPackage' => false,
                'orderId' => $order_id
            ];

            $order_shipping_method = WC()->session->get( 'chosen_shipping_methods' )[0];
//            $logger->debug('Shipping Method:', $context);
//            $logger->debug($order_shipping_method, $context);

            if(strcmp(WC()->session->get( 'chosen_shipping_methods' )[0], 'speedyman-metro') === 0) {

                $obj["type"] = 0;

                $order_billing_estacion = get_post_meta( $order->get_id(), '_billing_estacion', true );
//                $logger->debug('Station:', $context);
//                $logger->debug($order_billing_estacion, $context);

                $obj["station"] = $order_billing_estacion;

            } else {

                $obj["type"] = 1;

                $order_shipping_address_1 = $order->get_billing_address_1();
//                $logger->debug('Shipping Address 1', $context);
//                $logger->debug($order_shipping_address_1, $context);

                $obj["address"] = $order_shipping_address_1;

                $order_shipping_address_2 = $order->get_billing_address_2();
//                $logger->debug('Shipping Address 2', $context);
//                $logger->debug($order_shipping_address_2, $context);

                $obj["address_2"] = $order_shipping_address_2;
            }

            $speedyman = new Speedyman();
//            $logger->debug($obj, $context);
            $speedyman->create_delivery($obj);
        }
    }


    add_action('woocommerce_order_status_changed', 'SSFW_woo_order_status_change_custom', 10, 3);
    function SSFW_woo_order_status_change_custom($order_id, $old_status, $new_status) {
//        $logger = wc_get_logger();
//        $context = array( 'source' => 'speedyman-plugin' );

        $order = new WC_Order( $order_id );

//        $logger->debug("Payment Complete", $context);
//        $logger->debug("Order:", $context);
//        $logger->debug($order_id, $context);
//        $logger->debug("Status:", $context);
//        $logger->debug($order->get_status(), $context);

        $order_status = $order->get_status();
        if(strcmp($order_status, 'processing') === 0 || strcmp($order_status, 'completed') === 0) {
            $obj = [
                'orderAuthorization' => "",
                'deliveryId' => $order_id,
                'plugin' => 'woocommerce'
            ];

            $speedyman = new Speedyman();
            $speedyman->paymentComplete($obj);
        }
    }

    add_action( 'woocommerce_admin_order_data_after_billing_address', 'my_custom_checkout_field_display_admin_order_meta', 10, 1 );
    add_action( 'woocommerce_admin_order_data_after_shipping_address', 'my_custom_checkout_field_display_admin_order_meta', 10, 1 );

    function my_custom_checkout_field_display_admin_order_meta($order){
        $station = get_post_meta( $order->get_id(), 'speedyman_shipping_station', true );
        $address = get_post_meta( $order->get_id(), 'speedyman_shipping_address', true );
        if(strcmp($station, '') !== 0) {
            echo '<p><strong>'. __('Estacion de metro (Speedyman):').':</strong> ' . $station . '</p>';
        } else if(strcmp($address, '') !== 0) {
            echo '<p><strong>'. __('Envío a través de Speedyman').':</strong> ' . '</p>';
        }

    }



    add_action('woocommerce_checkout_create_order', 'before_checkout_create_order', 20, 2);
    function before_checkout_create_order( $order, $data ) {

        $logger = wc_get_logger();
        $context = array('source' => 'speedyman-plugin');

        $logger->debug($order->get_shipping_method(), $context);
        $shipping = $order->get_shipping_method();
        $logger->debug(strcmp($shipping, 'SpeedyMan: Entrega en estación de metro') === 0, $context);
        $logger->debug(strcmp($shipping, 'Speedyman: Despacho a domicilio') === 0, $context);

        if (strcmp($shipping, 'SpeedyMan: Entrega en estación de metro') === 0) {


            $order_billing_estacion = $data['billing_estacion'];
            $logger->debug('Station:', $context);
            $logger->debug($order_billing_estacion, $context);

            $order->update_meta_data( 'speedyman_shipping_station', $data['billing_estacion'] );

        } else if (strcmp($shipping, 'Speedyman: Despacho a domicilio') === 0) {

            $order_shipping_address_1 = $data['billing_address_1'];
//                $logger->debug('Shipping Address 1', $context);
//                $logger->debug($order_shipping_address_1, $context);

            $order_shipping_address_2 = $data['billing_address_2'];
//                $logger->debug('Shipping Address 2', $context);
//                $logger->debug($order_shipping_address_2, $context);

            $order->update_meta_data( 'speedyman_shipping_address', $order_shipping_address_1 . ' ' . $order_shipping_address_2);
        }
    }
}
