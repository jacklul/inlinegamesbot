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
        $content = $update->getUpdateContent();

        if ($content && ($from = $content->getFrom())) {
            $lang_code = $from->getLanguageCode();
        }

        if (empty($lang_code)) {
            $lang_code = Language::getDefaultLanguage();
        }

        Language::set($lang_code);

        return parent::processUpdate($update);
    }
}
