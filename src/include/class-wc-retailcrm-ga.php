<?php

if (!class_exists('WC_Retailcrm_Google_Analytics')) {
    /**
     * PHP version 7.0
     *
     * Class WC_Retailcrm_Google_Analytics - Integration with Google Analytics.
     *
     * @category Integration
     * @author   RetailCRM <integration@retailcrm.ru>
     * @license  http://retailcrm.ru Proprietary
     * @link     http://retailcrm.ru
     * @see      http://help.retailcrm.ru
     */
    class WC_Retailcrm_Google_Analytics {
        private static $instance;
        private $options;

        /**
         * @param array $options
         *
         * @return WC_Retailcrm_Google_Analytics
         */
        public static function getInstance($options = array())
        {
            if (self::$instance === null) {
                self::$instance = new self($options);
            }

            return self::$instance;
        }

        /**
         * WC_Retailcrm_Google_Analytics constructor.
         *
         * @param array $options
         */
        private function __construct($options = array())
        {
            $this->options = $options;
        }

        /**
         * @return string
         */
        public function initialize_analytics() {
            return apply_filters('retailcrm_initialize_analytics' ,"
                <script>
                    (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
                        (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
                        m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
                    })(window,document,'script','//www.google-analytics.com/analytics.js','ga');

                    ga('create', '" . esc_js($this->options['ua_code']) . "', 'auto');

                    function getRetailCrmCookie(name) {
                        var matches = document.cookie.match(new RegExp(
                            '(?:^|; )' + name + '=([^;]*)'
                        ));
                        return matches ? decodeURIComponent(matches[1]) : '';
                    }

                    ga('set', 'dimension" . esc_js($this->options['ua_custom']) ."', getRetailCrmCookie('_ga'));
                    ga('send', 'pageview');
                </script>
            ");
        }

        /**
         * @return string
         */
        public function send_analytics() {
            $js = '';

            //Public key in the URL, no need to verify the nonce token.
            // phpcs:ignore WordPress.Security.NonceVerification
            if (!isset($_GET['key'])) {
                return $js;
            }

            // phpcs:ignore WordPress.Security.NonceVerification
            $orderKey = sanitize_text_field(wp_unslash($_GET['key']));
            $order_id = wc_get_order_id_by_order_key($orderKey);
            $order = wc_get_order($order_id);

            if (is_object($order) === false) {
                return $js;
            }

            foreach ($order->get_items() as $item) {
                $uid = ($item['variation_id'] > 0) ? $item['variation_id'] : $item['product_id'];
                $_product = wc_get_product($uid);

                if ($_product) {
                    $order_item = array(
                        'id' => $uid,
                        'name' => $item['name'],
                        'price' => (float)$_product->get_price(),
                        'quantity' => $item['qty'],
                    );

                    $order_items[] = $order_item;
                }
            }

            $url = wp_parse_url(get_site_url());
            $domain = $url['host'];

            $js .= "
                <script type=\"text/javascript\">
                    ga('require', 'ecommerce', 'ecommerce.js');
                    ga('ecommerce:addTransaction', {
                        'id':" . esc_js($order->get_id()) . ",
                        'affiliation':'" . esc_js($domain) . "',
                        'revenue':" . esc_js($order->get_total()) . ",
                        'shipping':" . esc_js($order->get_total_tax()) . ",
                        'tax':" . esc_js($order->get_shipping_total()) . "
                    });
            ";

            foreach ($order_items as $item) {
                $js .= "
                    ga('ecommerce:addItem', {
                        'id':" . esc_js($order->get_id()) . ",
                        'sku':" . esc_js($item['id']) . ",
                        'name': '" . esc_js($item['name']) . "',
                        'price': " . esc_js($item['price']) . ",
                        'quantity': " . esc_js($item['quantity']) . "
                    });
                ";
            }

            $js .= "ga('ecommerce:send');
                </script>";

            return apply_filters('retailcrm_send_analytics', $js);
        }
    }
}
