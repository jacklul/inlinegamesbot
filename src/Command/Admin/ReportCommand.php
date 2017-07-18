<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2017 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Longman\TelegramBot\Commands\AdminCommands;

use Bot\Helper\Debug;
use Bot\Exception\BotException;
use Longman\TelegramBot\Commands\AdminCommand;
use Longman\TelegramBot\Request;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

/**
 * Class ReportCommand
 *
 * @package Longman\TelegramBot\Commands\AdminCommands
 */
class ReportCommand extends AdminCommand
{
    protected $name = 'report';
    protected $description = 'Send error reports and logs to admins';
    protected $usage = '/report';

    /**
     * @return \Longman\TelegramBot\Entities\ServerResponse
     * @throws BotException
     */
    public function execute()
    {
        $message = $this->getMessage();
        $edited_message = $this->getUpdate()->getEditedMessage();

        if ($edited_message) {
            $message = $edited_message;
        }

        $chat_id = $message->getFrom()->getId();
        $dirsToSend = $this->getConfig('dirs_to_report');

        if (empty(getenv('BOT_ADMIN'))) {
            Debug::log('No admin is set, aborting this command.');
            return Request::emptyResponse();
        }

        if (empty($dirsToSend) || !is_array($dirsToSend)) {
            throw new BotException('Config variable \'dirs_to_report\' must be an array!');
        }

        Request::sendChatAction(['chat_id' => $chat_id, 'action' => 'typing']);

        $filesToSend = [];

        foreach ($dirsToSend as $dirToSend) {
            if (file_exists($thisZip = VAR_PATH . '/' . basename($dirToSend) . '.zip')) {
                $newZip = VAR_PATH . '/' . basename($dirToSend) . '_previous.zip';
                rename($thisZip, $newZip);
            }

            if (file_exists($thisZip = VAR_PATH . '/' . basename($dirToSend) . '_previous.zip')) {
                $filesToSend[] = realpath($thisZip);
            }

            $thisZip = $this->zipDir($dirToSend);

            if ($thisZip) {
                $filesToSend[] = realpath($thisZip);
                $this->deleteDir($dirToSend);
            }
        }

        $alreadySent = [];
        $sendFailed = false;
        $bot_id = $this->getTelegram()->getBotId();

        if (!empty($filesToSend)) {
            foreach ($this->getTelegram()->getAdminList() as $admin) {
                if ($admin != $bot_id) {
                    foreach ($filesToSend as $file) {
                        Debug::log('Sending to ' . $admin);

                        if (!empty($alreadySent[$file])) {
                            $data_admin = [
                                'chat_id' => $admin,
                                'document' => $alreadySent[$file]
                            ];

                            $log['result'] = Request::sendDocument($data_admin);
                        } else {
                            if (filesize($file) < 50000000) {
                                $data_admin = [
                                    'chat_id' => $admin,
                                    'document' => Request::encodeFile($file)
                                ];

                                $result = Request::sendDocument($data_admin);

                                if ($result->isOk() && $result->getResult()->getDocument()) {
                                    $alreadySent[$file] = $result->getResult()->getDocument()->getFileId();
                                    unlink($file);
                                }
                            } else {
                                $sendFailed = true;
                                $data_admin = [
                                    'chat_id' => $admin,
                                    'text' => 'File \'' . $file . '\' cannot be sent because it exceeds 50MB size limit!'
                                ];

                                Request::sendMessage($data_admin);
                            }
                        }
                    }
                }
            }
        }

        if ($message) {
            $data = [];
            $data['chat_id'] = $chat_id;

            if (!empty($alreadySent)) {
                if (!$sendFailed) {
                    $data['text'] = 'Report successful!';
                } else {
                    $data['text'] = 'Report partially successful!';
                }
            } elseif ($sendFailed) {
                $data['text'] = 'Report failed!';
            } else {
                $data['text'] = 'Nothing to report!';
            }

            return Request::sendMessage($data);
        }

        return Request::emptyResponse();
    }

    /**
     * Check if directory is empty
     *
     * @param $dir
     *
     * @return bool|null
     */
    private function isDirEmpty($dir)
    {
        if (!is_readable($dir)) {
            return null;
        }

        return (count(scandir($dir)) == 2);
    }

    /**
     * Delete directory recursively
     *
     * @param $dirPath
     *
     * @throws BotException
     */
    private function deleteDir($dirPath)
    {
        if (!is_dir($dirPath)) {
            throw new BotException("$dirPath must be a directory");
        }

        if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
            $dirPath .= '/';
        }

        $files = glob($dirPath . '*', GLOB_MARK);

        foreach ($files as $file) {
            Debug::log('Removing \'' . $file . '\'');

            if (is_dir($file)) {
                self::deleteDir($file);
            } else {
                @unlink($file);
            }
        }

        @rmdir($dirPath);
    }

    /**
     * Create a zip from a directory
     *
     * @param $dir
     *
     * @return bool|string
     */
    private function zipDir($dir)
    {
        if (is_dir($pathToZip = $dir) && !$this->isDirEmpty($dir)) {
            if (!is_dir(VAR_PATH . '/')) {
                mkdir(VAR_PATH . '/', 0755, true);
            }

            Debug::log('Zipping \'' . $dir . '\'...');

            $zipFile = VAR_PATH . '/' . basename($dir) . '.zip';

            $zip = new ZipArchive();
            $zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($pathToZip),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $name => $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($pathToZip) + 1);

                    $zip->addFile($filePath, $relativePath);
                }
            }

            if ($zip->close()) {
                Debug::log('Saved as \'' . $zipFile . '\'');

                return $zipFile;
            }
        }

        return false;
    }
}
