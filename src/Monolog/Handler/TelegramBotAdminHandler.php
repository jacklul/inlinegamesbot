<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2017 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Bot\Monolog\Handler;

use Bot\Helper\Debug;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;

/**
 * Class TelegramBotAdminHandler
 *
 * @package Bot\Monolog\Handler
 */
class TelegramBotAdminHandler extends AbstractProcessingHandler
{
    /**
     * Telegram object
     *
     * @var Telegram
     */
    private $telegram;

    /**
     * Bot ID
     *
     * @var int
     */
    private $bot_id;

    /**
     * File with last reports (up to 100 entries)
     *
     * @var null|string
     */
    private $reports_file = null;

    /**
     * Last error time
     *
     * @var int
     */
    private $last_timestamp = 0;

    /**
     * Last error message
     *
     * @var string
     */
    private $last_message = '';

    /**
     * TelegramBotAdminHandler constructor
     *
     * @param Telegram $telegram
     * @param bool|int $level
     * @param bool     $bubble
     */
    public function __construct(Telegram $telegram, $level = Logger::ERROR, $bubble = true)
    {
        $this->telegram = $telegram;
        $this->bot_id = $telegram->getBotId();

        $this->reports_file = sys_get_temp_dir() . '/' . $this->bot_id . '_reports.json';

        if (!is_writable(dirname($this->reports_file))) {
            if (!is_dir(DATA_PATH . '/tmp')) {
                mkdir(DATA_PATH . '/tmp', 0755, true);
            }

            $this->reports_file = DATA_PATH . '/tmp/' . $this->bot_id . '_reports.json';

            if (!is_writable(dirname($this->reports_file))) {
                $this->reports_file = null;
            }
        }

        parent::__construct($level, $bubble);
    }

    /**
     * Send log message to bot admins
     *
     * @param  array $record
     * @return bool
     */
    protected function write(array $record): bool
    {
        if (time() == $this->last_timestamp) {
            sleep(1);       // prevent hitting Telegram's flood block
        }

        $reports = $this->getReports();
        $message = preg_split("/\\r\\n|\\r|\\n/", $record['message'])[0];   // get only the actual message without stacktrace

        if ($message === $this->last_message) {
            Debug::print('Log report prevented - message is a duplicate (session)');

            return false;
        }

        // Do not send already reported error messages for 1 day
        if (isset($reports) && is_array($reports) && count($reports) > 0) {
            foreach ($reports as $report) {
                if ($report['message'] === $message && $report['time'] + 86400 > $record['datetime']->format('U')) {
                    Debug::print('Log report prevented - message is a duplicate (last 24 hours)');

                    return false;
                }
            }
        }

        $success = false;
        foreach ($this->telegram->getAdminList() as $admin) {
            if ($admin != $this->bot_id) {
                try {
                    $result = Request::sendMessage(
                        [
                        'chat_id'    => $admin,
                        'text'       => '<b>' . $record['level_name'] . ' (' . $record['datetime']->format('H:i:s d-m-Y') . ')</b>' . PHP_EOL . '<code>' . htmlentities($record['message']) . '</code>',
                        'parse_mode' => 'HTML',
                        ]
                    );
                } catch (TelegramException $e) {
                    print($e->getMessage());
                }

                if (isset($result) && $result->isOk()) {
                    $success = true;
                }
            }
        }

        if (!$success) {
            print($record['message']);  // log error to console instead
        }

        $this->addReport(['message' => $message, 'time' => $record['datetime']->format('U')]);

        $this->last_timestamp = time();
        $this->last_message = $message;

        return true;
    }

    /**
     * Get reports array
     *
     * @return array
     */
    private function getReports(): array
    {
        if (!is_null($this->reports_file) && file_exists($this->reports_file)) {
            $reports = json_decode(file_get_contents($this->reports_file), true);
        }

        if (!isset($reports) || !is_array($reports)) {
            $reports = [];
        }

        return $reports;
    }

    /**
     * Add current report to reports file
     *
     * @param array $report
     *
     * @return bool
     */
    private function addReport($report = []): bool
    {
        if (empty($report['message']) || empty($report['time']) || is_null($this->reports_file)) {
            return false;
        }

        $reports = $this->getReports();

        array_push($reports, $report);

        while (count($reports) > 100) {
            array_shift($reports);
        }

        return file_put_contents($this->reports_file, json_encode($reports));
    }
}
