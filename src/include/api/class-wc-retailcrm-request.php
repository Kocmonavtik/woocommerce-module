<?php

if (!class_exists('WC_Retailcrm_Exception_Curl')) {
    include_once(WC_Integration_Retailcrm::checkCustomFile('include/api/class-wc-retailcrm-exception-curl.php'));
}

if (!class_exists('WC_Retailcrm_Response')) {
    include_once(WC_Integration_Retailcrm::checkCustomFile('include/api/class-wc-retailcrm-response.php'));
}

/**
 * PHP version 7.0
 *
 * Class WC_Retailcrm_Request - Request class.
 *
 * @category Integration
 * @package  WC_Retailcrm_Request
 * @author   RetailCRM <dev@retailcrm.ru>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     http://retailcrm.ru/docs/Developers/ApiVersion5
 */
class WC_Retailcrm_Request
{
    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';

    protected $url;
    protected $defaultParameters;

    /**
     * Client constructor.
     *
     * @param string $url               api url
     * @param array  $defaultParameters array of parameters
     *
     */
    public function __construct($url, array $defaultParameters = [])
    {
        $this->url = $url;
        $this->defaultParameters = $defaultParameters;
    }

    /**
     * Make HTTP request
     *
     * @param string $path       request url
     * @param string $method     (default: 'GET')
     * @param array  $parameters (default: array())
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     *
     * @throws \InvalidArgumentException
     * @throws WC_Retailcrm_Exception_Curl
     *
     * @return WC_Retailcrm_Response
     */
    public function makeRequest(
        $path,
        $method,
        array $parameters = []
    ) {
        $allowedMethods = [self::METHOD_GET, self::METHOD_POST];

        if (!in_array($method, $allowedMethods, false)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Method "%1$s" is not valid. Allowed methods are %2$s',
                    esc_attr($method),
                    esc_attr(implode(', ', $allowedMethods))
                )
            );
        }

        $parameters = self::METHOD_GET === $method
            ? array_merge($this->defaultParameters, $parameters, [
                'cms_source' => 'WordPress',
                'cms_version' => function_exists('get_bloginfo') ? get_bloginfo('version') : '',
                'woo_version' => WC()->version ?? '',
                'php_version' => function_exists('phpversion') ? phpversion() : '',
                'module_version' => WC_Integration_Retailcrm::MODULE_VERSION,
                'ga_option_is_active' => getOptionByCode('ua') === WC_Retailcrm_Abstracts_Settings::YES,
            ])
            : array_merge($this->defaultParameters, $parameters);

        $url = $this->url . $path;

        if (self::METHOD_GET === $method && count($parameters)) {
            $url .= '?' . http_build_query($parameters, '', '&');
        }

        $args = [
            'timeout' => 30,
            'sslverify' => false
        ];

        if (self::METHOD_POST === $method) {
            $args['method'] = 'POST';
            $args['body'] = $parameters;
        } else {
            $args['method'] = 'GET';
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $error = $response->get_error_message();
            throw new WC_Retailcrm_Exception_Curl(esc_html($error));
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $responseBody = wp_remote_retrieve_body($response);

        return new WC_Retailcrm_Response($statusCode, $responseBody);
    }
}
