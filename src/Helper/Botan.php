<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2017 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Bot\Helper;

use Bot\Exception\BotException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Longman\TelegramBot\Entities\Update;

/**
 * Class Botan
 *
 * @package Bot\Helper
 */
class Botan
{
    /**
     * Botan.io API URL
     *
     * @var string
     */
    protected static $api_base_uri = 'https://api.botan.io';

    /**
     * Yandex AppMetrica application key
     *
     * @var string
     */
    protected static $token = '';

    /**
     * Guzzle Client object
     *
     * @var \GuzzleHttp\Client
     */
    private static $client;

    /**
     * Track function
     *
     * @param Update $update
     * @param string $event_name
     * @param integer $timeout
     *
     * @return bool|string
     * @throws BotException
     */
    public static function track(Update $update, $event_name = '', $timeout = 5)
    {
        if (empty(self::$token)) {
            self::$token = getenv('BOTAN_TOKEN');
        }

        if (!self::$client instanceof Client) {
            self::$client = new Client(['base_uri' => self::$api_base_uri, 'timeout' => $timeout]);
        }

        if (empty(self::$token) || empty($event_name)) {
            return false;
        }

        if ($update === null) {
            throw new BotException('Update object is empty!');
        }

        $update_data = (array)$update;
        $data = $update_data[$update->getUpdateType()];

        $uid = isset($data['from']['id']) ? $data['from']['id'] : 0;

        try {
            $response = self::$client->post(
                sprintf(
                    '/track?token=%1$s&uid=%2$s&name=%3$s',
                    self::$token,
                    $uid,
                    urlencode($event_name)
                ),
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                    'json'    => $data,
                ]
            );

            $result = (string)$response->getBody();
        } catch (RequestException $e) {
            $result = $e->getMessage();
        }

        $responseData = json_decode($result, true);

        if (!$responseData || $responseData['status'] !== 'accepted') {
            Debug::print('Botan.io stats report failed: ' . $result ?: 'empty response');

            return false;
        }

        return $responseData;
    }
}
