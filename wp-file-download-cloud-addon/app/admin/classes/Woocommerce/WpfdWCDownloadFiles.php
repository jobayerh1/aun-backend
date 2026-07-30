<?php
/**
 * WP File Download
 *
 * @package WP File Download
 * @author  Joomunited
 * @version 1.0
 */

// no direct access
defined('ABSPATH') || die();

/**
 * Class WpfdWCDownloadFiles
 */
class WpfdWCDownloadFiles
{
    /**
     * Woocommerce order object
     *
     * @var boolean|WC_Order|WC_Order_Refund
     */
    protected $order;

    /**
     * Order id
     *
     * @var integer|string
     */
    protected $orderId;

    /**
     * Download array
     *
     * @var array
     */
    protected $downloads = array();

    /**
     * WpfdWCDownloadFiles constructor.
     *
     * @param string|integer $orderId Order id
     *
     * @return void
     */
    public function __construct($orderId)
    {
        $this->orderId = $orderId;

        $this->order = wc_get_order($orderId);

        $orderDownloads = get_post_meta($this->getOrderId(), '_wpfd_order_data', true);

        if (!empty($orderDownloads) && is_countable($orderDownloads) && count($orderDownloads)) {
            foreach ($orderDownloads as $download) {
                $downloadAr = $this->createDownloadArray($download);
                if (!$downloadAr) {
                    continue;
                }
                $this->downloads[$download['download_id']] = $downloadAr;
            }
        }
    }

    /**
     * Get order id
     *
     * @return mixed
     */
    public function getOrderId()
    {
        return $this->orderId;
    }

    /**
     * Get order
     *
     * @return boolean|WC_Order|WC_Order_Refund
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * Get downloads
     *
     * @return array
     */
    public function getDownloads()
    {
        return $this->downloads;
    }

    /**
     * Create download array
     *
     * @param array $download Download array
     *
     * @return array
     */
    private function createDownloadArray($download)
    {
        try {
            $product = new WC_Product($download['product_id']);
        } catch (Exception $e) {
            return false;
        }

        return array(
            'id'                  => $download['download_id'],
            'name'                => $download['file_data']['name'],
            'file'                => '',
            'downloads_remaining' => isset($download['downloads_remaining']) ? $download['downloads_remaining'] : '',
            'access_expires'      => (isset($download['access_expires']) && $download['access_expires'] !== '' && intval($download['access_expires']) !== 0 ) ? date('Y-m-d', $download['access_expires']) : '',
            'product_id'          => $download['product_id'],
            'product_name'        => $product->get_name(),
            'product_url'         => $product->is_visible() ? $product->get_permalink() : '',
            'download_url'        => $this->generateDownloadUrl($download),
            'download_id'         => $download['download_id'],
            'download_name'       => $download['file_data']['name'],
            'order_id'            => $this->getOrderId(),
            'order_key'           => $download['order_key'],

        );
    }

    /**
     * Generate download URL
     *
     * @param array $download Download information
     *
     * @return string
     */
    private function generateDownloadUrl($download)
    {
        $email_hash = function_exists('hash') ? hash('sha256', $this->order->get_billing_email()) : sha1($this->order->get_billing_email());

        return add_query_arg(
            array(
                'wpfd_download_file' => $download['product_id'],
                'order'         => $this->order->get_order_key(),
                'uid'           => $email_hash,
                'key'           => $download['download_id'],
            ),
            trailingslashit(home_url())
        );
    }
}
