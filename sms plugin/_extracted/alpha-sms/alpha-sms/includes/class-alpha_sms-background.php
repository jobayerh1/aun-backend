<?php
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Handle background SMS sending jobs.
 *
 * This class is responsible for dispatching background jobs using
 * wp_schedule_single_event() and processing the payload for each
 * scheduled job.
 *
 * @package    Alpha_sms
 * @subpackage Alpha_sms/includes
 * @since      1.0.12 Introduced to queue individual SMS jobs for campaign sending.
 */
class Alpha_SMS_Background
{
    /**
     * Action hook used to process a single SMS job.
     */
    public const ACTION_HOOK = 'alpha_sms_send_single_sms';

    /**
     * Plugin slug/text domain.
     *
     * @var string
     */
    protected $plugin_name;

    /**
     * Constructor.
     *
     * @param string $plugin_name Plugin slug/text domain.
     */
    public function __construct($plugin_name)
    {
        $this->plugin_name = $plugin_name;
    }

    /**
     * Queue a single SMS job.
     *
     * @since 1.0.12
     *
     * @param string $number    Recipient phone number.
     * @param string $body      Message body.
     * @param string $sender_id Sender ID (optional).
     * @param string $api_key   API key used to authenticate requests.
     *
     * @return bool Whether the job was queued successfully.
     */
    public function dispatch($number, $body, $sender_id, $api_key)
    {
        if (!function_exists('wp_schedule_single_event')) {
            return false;
        }

        $number = $this->normalize_number($number);
        $body = is_string($body) ? trim($body) : '';
        $sender_id = is_string($sender_id) ? trim($sender_id) : '';
        $api_key = is_string($api_key) ? trim($api_key) : '';

        if ('' === $number || '' === $body || '' === $api_key) {
            return false;
        }

        $payload = [
            'job_id'      => uniqid('alpha_sms_job_', true),
            'plugin_name' => $this->plugin_name,
            'number'      => $number,
            'body'        => $body,
            'sender_id'   => $sender_id,
            'api_key'     => $api_key,
        ];

        $timestamp = time() + 1;

        return false !== wp_schedule_single_event($timestamp, self::ACTION_HOOK, [$payload]);
    }

    /**
     * Process a queued SMS job.
     *
     * @since 1.0.12
     *
     * @param array $payload Job payload data.
     *
     * @return void
     */
    public function alpha_sms_send_single_sms($payload)
    {
        if (!is_array($payload)) {
            return;
        }

        $payload = wp_parse_args($payload, [
            'number'    => '',
            'body'      => '',
            'sender_id' => '',
            'api_key'   => '',
        ]);

        $number = $this->normalize_number($payload['number']);
        $body = (string)$payload['body'];
        $sender_id = (string)$payload['sender_id'];
        $api_key = (string)$payload['api_key'];

        if ('' === $number || '' === $body || '' === $api_key) {
            return;
        }

        if (!class_exists('Alpha_SMS_Class')) {
            require_once plugin_dir_path(__FILE__) . 'sms.class.php';
        }

        $sms = new Alpha_SMS_Class($api_key);
        $sms->numbers = $number;
        $sms->body = $body;

        if ('' !== $sender_id) {
            $sms->sender_id = $sender_id;
        }

        $response = $sms->Send();

        $this->record_result($payload, $response);
    }

    /**
     * Store job results for later display in the admin area.
     *
     * @since 1.0.12
     *
     * @param array $payload  Job payload data.
     * @param mixed $response Response from the API.
     *
     * @return void
     */
    protected function record_result($payload, $response)
    {
        $option_key = $this->plugin_name . '_job_results';
        $lock_key = $option_key . '_lock';

        if (!add_transient($lock_key, true, 10)) {
            return;
        }

        try {
            $results = get_option($option_key, []);
            if (!is_array($results)) {
                $results = [];
            }

            $defaults = [
                'success'    => 0,
                'failed'     => 0,
                'last_error' => '',
                'failures'   => [],
            ];

            $results = wp_parse_args($results, $defaults);

            if ($this->is_successful_response($response)) {
                $results['success']++;
            } else {
                $results['failed']++;

                $message = $this->extract_error_message($response);
                $sanitized_message = sanitize_text_field($message);
                $results['last_error'] = $sanitized_message;

                if (!is_array($results['failures'])) {
                    $results['failures'] = [];
                }

                if (count($results['failures']) < 5) {
                    $results['failures'][] = [
                        'number'  => sanitize_text_field($this->normalize_number(isset($payload['number']) ? $payload['number'] : '')),
                        'message' => $sanitized_message,
                    ];
                }
            }

            update_option($option_key, $results);
        } finally {
            delete_transient($lock_key);
        }
    }

    /**
     * Determine if the API response indicates a successful send.
     *
     * @since 1.0.12
     *
     * @param mixed $response Response data.
     *
     * @return bool
     */
    protected function is_successful_response($response)
    {
        if (is_wp_error($response)) {
            return false;
        }

        if (is_object($response) && isset($response->error)) {
            return (int)$response->error === 0;
        }

        if (is_array($response) && isset($response['error'])) {
            return (int)$response['error'] === 0;
        }

        return (bool)$response;
    }

    /**
     * Extract an error message from the response.
     *
     * @since 1.0.12
     *
     * @param mixed $response Response data.
     *
     * @return string
     */
    protected function extract_error_message($response)
    {
        if (is_wp_error($response)) {
            $message = $response->get_error_message();
        } elseif (is_object($response) && isset($response->msg)) {
            $message = (string)$response->msg;
        } elseif (is_array($response) && isset($response['msg'])) {
            $message = (string)$response['msg'];
        } else {
            /* translators: Error message shown when SMS sending fails for unknown reason. */
            $message = __('Unknown error while sending SMS.', 'alpha-sms');
        }

        return wp_strip_all_tags($message);
    }

    /**
     * Normalize a phone number string.
     *
     * @since 1.0.12
     *
     * @param string $number Raw phone number input.
     *
     * @return string
     */
    protected function normalize_number($number)
    {
        $number = trim((string)$number);

        return preg_replace('/\s+/', '', $number);
    }
}
