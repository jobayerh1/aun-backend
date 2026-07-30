<?php
/**
 * WP File Download
 *
 * @package WP File Download
 * @author  Joomunited
 * @version 1.0
 */

namespace Joomunited\BackgroundTasks;

use Joomunited\Queue\JuMainQueue;

if (!class_exists('\Joomunited\Queue\JuMainQueue')) {
    return;
}

// no direct access
defined('ABSPATH') || die();

/**
 * Class WpfdTasksAbstract
 */
abstract class WpfdTasksAbstract
{
    /**
     * Process queue data
     *
     * @var array|mixed
     */
    public $addToQueueData = array();

    /**
     * WpfdTasksAbstract constructor.
     */
    public function __construct()
    {
        if (!class_exists('JuMainQueue')) {
            return;
        }
    }

    /**
     * Run queue
     *
     * @return void
     */
    public function runQueue()
    {
        if (!class_exists('\Joomunited\Queue\JuMainQueue')) {
            return;
        }

        $wpfdQueue = JuMainQueue::getInstance('wpfd');
        $wpfdQueue->proceedQueueAsync();
        $queueData = $this->addToQueueData;
        do_action('wpfd_action_after_sync_done', $queueData);
    }

    /**
     * Add single task to queue
     *
     * @param array $datas Data queue
     *
     * @return void
     */
    public function doAddToQueue($datas)
    {
        if (!$this->isDatasValid($datas)) {
            return;
        }

        $this->addOnceToQueue($datas);
    }

    /**
     * Validate queue data
     *
     * @param array $datas Queue data
     *
     * @return boolean
     */
    private function isDatasValid($datas)
    {
        if (empty($datas) ||
            !isset($datas['action']) ||
            !isset($datas['id'])
        ) {
            return false;
        }

        return true;
    }

    /**
     * Add new task to queue and made sure just one task is waiting
     *
     * @param array $datas Data queue
     *
     * @return void
     */
    public function addOnceToQueue($datas)
    {
        if (!class_exists('\Joomunited\Queue\JuMainQueue')) {
            return;
        }
        if (!$this->isDatasValid($datas)) {
            return;
        }
        $wpfdQueue = JuMainQueue::getInstance('wpfd');
        $row = $wpfdQueue->checkQueueExist(json_encode($datas));
        if (!$row) {
            $wpfdQueue->addToQueue($datas);
        } else {
            if ((int)$row->status !== 0) {
                $wpfdQueue->addToQueue($datas);
            }
        }
    }

    /**
     * Add new task to queue
     *
     * @param array $datas Data queue
     *
     * @return void
     */
    public function addNewToQueue($datas)
    {
        if (!class_exists('\Joomunited\Queue\JuMainQueue')) {
            return;
        }
        if (!$this->isDatasValid($datas)) {
            return;
        }
        $wpfdQueue = JuMainQueue::getInstance('wpfd');
        $wpfdQueue->addToQueue($datas);
    }

    /**
     * Add new task to queue without duplicate
     *
     * @param array $datas Data queue
     *
     * @return void
     */
    public function addToQueue($datas)
    {
        if (!class_exists('\Joomunited\Queue\JuMainQueue')) {
            return;
        }

        if (!$this->isDatasValid($datas)) {
            return;
        }

        $wpfdQueue = JuMainQueue::getInstance('wpfd');
        $row = $wpfdQueue->checkQueueExist(json_encode($datas));
        $this->addToQueueData = $datas;

        if (!$row) {
            $wpfdQueue->addToQueue($datas);
        }
    }

    /**
     * Update queue response
     *
     * @param integer $queueId   Queue id
     * @param mixed   $responses Response data
     *
     * @return void
     */
    public function updateResponse($queueId, $responses)
    {
        $wpfdQueue = JuMainQueue::getInstance('wpfd');
        $wpfdQueue->updateResponses((int)$queueId, $responses);
    }

    /**
     * Update queue term meta
     *
     * @param integer $term_id  Term id
     * @param integer $queue_id Queue id
     *
     * @return void
     */
    public function updateQueueTermMeta($term_id, $queue_id)
    {
//        $queue_meta = get_term_meta($term_id, 'wpfd_sync_queue', true);
//        if (!empty($queue_meta) && is_array($queue_meta)) {
//            $queue_ids = array_merge($queue_meta, array($queue_id));
//        } else {
            $queue_ids = array((int)$queue_id);
//        }
        update_term_meta($term_id, 'wpfd_sync_queue', array_unique($queue_ids));
    }

    /**
     * Get Google sync status
     *
     * @param boolean $return Return value of count
     *
     * @return integer
     */
    public function getGoogleSyncStatus($return = false)
    {
        $remainTaskCount = $this->getRemainTaskCount('google');
        if ($return) {
            return $remainTaskCount;
        }
        header('Content-Type: application/json');
        echo json_encode(array('success' => true, 'total' => $this->getRemainTaskCount('google')));
        die;
    }

    /**
     * Get google team drive sync status
     *
     * @param boolean $return Return value of count
     *
     * @return integer
     */
    public function getGoogleTeamDriveSyncStatus($return = false)
    {
        $remainTaskCount = $this->getRemainTaskCount('google_team_drive');
        if ($return) {
            return $remainTaskCount;
        }
        header('Content-Type: application/json');
        echo json_encode(array('success' => true, 'total' => $this->getRemainTaskCount('google_team_drive')));
        die;
    }

    /**
     * Get Onedrive sync status
     *
     * @param boolean $return Return value of count
     *
     * @return integer
     */
    public function getOnedriveSyncStatus($return = false)
    {
        $remainTaskCount = $this->getRemainTaskCount('onedrive');
        if ($return) {
            return $remainTaskCount;
        }

        header('Content-Type: application/json');
        echo json_encode(array('success' => true, 'total' => $remainTaskCount));
        die;
    }

    /**
     * Get Onedrive Business sync status
     *
     * @param boolean $return Return value of count
     *
     * @return integer
     */
    public function getOnedriveBusinessSyncStatus($return = false)
    {
        $remainTaskCount = $this->getRemainTaskCount('onedrive_business');
        if ($return) {
            return $remainTaskCount;
        }

        header('Content-Type: application/json');
        echo json_encode(array('success' => true, 'total' => $remainTaskCount));
        die;
    }

    /**
     * Get dropbox sync status
     *
     * @param boolean $return Return value of count
     *
     * @return integer
     */
    public function getDropboxSyncStatus($return = false)
    {
        $remainTaskCount = $this->getRemainTaskCount('dropbox');
        if ($return) {
            return $remainTaskCount;
        }

        header('Content-Type: application/json');
        echo json_encode(array('success' => true, 'total' => $remainTaskCount));
        die;
    }

    /**
     * Get AWS sync status
     *
     * @param boolean $return Return value of count
     *
     * @return integer
     */
    public function getAwsSyncStatus($return = false)
    {
        $remainTaskCount = $this->getRemainTaskCount('aws');
        if ($return) {
            return $remainTaskCount;
        }

        header('Content-Type: application/json');
        echo json_encode(array('success' => true, 'total' => $remainTaskCount));
        die;
    }

    /**
     * Get Nextcloud sync status
     *
     * @param boolean $return Return value of count
     *
     * @return integer
     */
    public function getNextcloudSyncStatus($return = false)
    {
        $remainTaskCount = $this->getRemainTaskCount('nextcloud');
        if ($return) {
            return $remainTaskCount;
        }

        header('Content-Type: application/json');
        echo json_encode(array('success' => true, 'total' => $remainTaskCount));
        die;
    }

    /**
     * Get remain task count
     *
     * @param string $provide Cloud provider
     *
     * @return integer
     */
    public function getRemainTaskCount($provide = '')
    {
        if (!class_exists('\Joomunited\Queue\JuMainQueue')) {
            return 0;
        }
        $actions = array();
        switch ($provide) {
            case 'google':
                $actions = array('wpfd_sync_google_drive', 'wpfd_google_drive_remove');
                break;
            case 'google_team_drive':
                $actions = array('wpfd_sync_google_team_drive', 'wpfd_google_team_drive_remove');
                break;
            case 'onedrive':
                $actions = array('wpfd_sync_onedrive', 'wpfd_onedrive_remove');
                break;
            case 'onedrive_business':
                $actions = array('wpfd_sync_onedrive_business', 'wpfd_onedrive_business_remove');
                break;
            case 'dropbox':
                $actions = array('wpfd_sync_dropbox', 'wpfd_dropbox_remove');
                break;
            case 'aws':
                $actions = array('wpfd_sync_aws', 'wpfd_aws_remove');
                break;
            case 'nextcloud':
                $actions = array('wpfd_sync_nextcloud', 'wpfd_nextcloud_remove');
                break;
            default:
                break;
        }

        if (count($actions) === 0) {
            return -1;
        }

        $wpfdQueue = JuMainQueue::getInstance('wpfd');

        return $wpfdQueue->getRemainCountByActions($actions);
    }
}
