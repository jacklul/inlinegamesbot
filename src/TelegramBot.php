<?php

/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2016-2022 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bot;

use Bot\Helper\Language;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Entities\Update;
use Longman\TelegramBot\Telegram;

class TelegramBot extends Telegram
{
    /**
     * @param Update $update
     *
     * @return ServerResponse
     */
    public function processUpdate(Update $update): ServerResponse
    {
        $lang_code = null;

        // Try to detect language from different update types
        if ($message = $update->getMessage()) {
            $lang_code = $message->getFrom()->getLanguageCode();
        } elseif ($callback = $update->getCallbackQuery()) {
            $lang_code = $callback->getFrom()->getLanguageCode();
        } elseif ($inline = $update->getInlineQuery()) {
            $lang_code = $inline->getFrom()->getLanguageCode();
        } elseif ($chosen = $update->getChosenInlineResult()) {
            $lang_code = $chosen->getFrom()->getLanguageCode();
        } elseif ($edited = $update->getEditedMessage()) {
            $lang_code = $edited->getFrom()->getLanguageCode();
        }

        // If no language detected, fallback to default
        if (empty($lang_code)) {
            $lang_code = Language::getDefaultLanguage();
        }

        Language::set($lang_code);

        return parent::processUpdate($update);
    }
}
