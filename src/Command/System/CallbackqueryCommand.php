<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2016-2019 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Longman\TelegramBot\Commands\SystemCommands;

use jacklul\inlinegamesbot\GameCore;
use jacklul\inlinegamesbot\Helper\Utilities;
use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Request;

/**
 * Handle button presses
 *
 * @noinspection PhpUndefinedClassInspection
 */
class CallbackqueryCommand extends SystemCommand
{
    /**
     * Callback data before first ';' symbol -> command bind
     *
     * @var array
     */
    private $aliases = [
        'stats' => 'stats',
    ];

    /**
     * @return bool|\Longman\TelegramBot\Entities\ServerResponse|mixed
     *
     * @throws \Longman\TelegramBot\Exception\TelegramException
     * @throws \jacklul\inlinegamesbot\Exception\BotException
     * @throws \jacklul\inlinegamesbot\Exception\StorageException
     * @throws \jacklul\inlinegamesbot\Exception\TelegramApiException
     * @throws \Throwable
     */
    public function execute()
    {
        $callback_query = $this->getUpdate()->getCallbackQuery();
        $data = $callback_query->getData();

        Utilities::debugPrint('Data: ' . $data);

        $command = explode(';', $data)[0];

        if (isset($this->aliases[$command]) && $this->getTelegram()->getCommandObject($this->aliases[$command])) {
            return $this->getTelegram()->executeCommand($this->aliases[$command]);
        }

        if ($this->isDataValid($data)) {
            $game = new GameCore($callback_query->getInlineMessageId(), explode(';', $data)[0], $this);

            if ($game->canRun()) {
                return $game->run();
            }
        }

        return Request::answerCallbackQuery(
            [
                'callback_query_id' => $callback_query->getId(),
                'text'              => __("Bad request!"),
                'show_alert'        => true,
            ]
        );
    }

    /**
     * Validate callback data
     *
     * @param string $data
     *
     * @return bool
     */
    private function isDataValid(string $data): bool
    {
        /** @noinspection CallableParameterUseCaseInTypeContextInspection */
        $data = explode(';', $data);

        if (count($data) >= 2) {
            return true;
        }

        return false;
    }
}
