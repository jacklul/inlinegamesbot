<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2017 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Bot\Entity\Game;

use Bot\Entity\Game;
use Bot\Helper\DebugLog;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Spatie\Emoji\Emoji;

/**
 * Class Russianroulette
 *
 * @package Bot\Entity\Game
 */
class Russianroulette extends Game
{
    /**
     * Game unique id (for callback queries)
     *
     * @var string
     */
    private static $code = 'rr';

    /**
     * Game name
     *
     * @var string
     */
    private static $title = 'Russian Roulette';

    /**
     * Game description
     *
     * @var string
     */
    private static $description = 'Russian roulette is a game of chance in which a player places a single round in a revolver, spins the cylinder, places the muzzle against their head, and pulls the trigger.';

    /**
     * Game image (for inline query result)
     *
     * @var string
     */
    private static $image = 'https://i.imgur.com/LffxQLK.jpg';

    /**
     * Order on the game list (inline query result)
     *
     * @var int
     */
    private static $order = 4;

    /**
     * @return string
     */
    public static function getCode(): string
    {
        return self::$code;
    }

    /**
     * @return string
     */
    public static function getTitle(): string
    {
        return self::$title;
    }

    /**
     * @return string
     */
    public static function getDescription(): string
    {
        return self::$description;
    }

    /**
     * @return string
     */
    public static function getImage(): string
    {
        return self::$image;
    }

    /**
     * @return string
     */
    public static function getOrder(): string
    {
        return self::$order;
    }

    /**
     * Game related variables
     */
    protected $max_x;
    protected $max_y;
    protected $symbols = [];

    /**
     * Define game symbols (emojis)
     */
    private function defineSymbols()
    {
        $this->symbols['empty'] = '.';

        $this->symbols['chamber'] = Emoji::radioButton();
        $this->symbols['chamber_hit'] = Emoji::largeRedCircle();
    }

    /**
     * Game handler
     *
     * @return \Longman\TelegramBot\Entities\ServerResponse|mixed
     */
    protected function gameAction()
    {
        if ($this->getCurrentUserId() != $this->getUserId('host') && $this->getCurrentUserId() != $this->getUserId('guest')) {
            return $this->answerCallbackQuery(__("You're not in this game!"), true);
        }

        $data = &$this->data['data'];

        $this->defineSymbols();

        $callbackquery_data = $this->manager->getUpdate()->getCallbackQuery()->getData();
        $callbackquery_data = explode(';', $callbackquery_data);

        $command = $callbackquery_data[1];

        if (isset($callbackquery_data[2])) {
            $arg = $callbackquery_data[2];
        }

        if ($command == 'start') {
            if (isset($data['settings']) && $data['settings']['X'] == 'host') {
                $data['settings']['X'] = 'guest';
                $data['settings']['O'] = 'host';
            } else {
                $data['settings']['X'] = 'host';
                $data['settings']['O'] = 'guest';
            }

            $data['current_turn'] = 'X';
            $data['cylinder'] = ['', '', '', '', '', ''];
            $data['cylinder'][mt_rand(0, 5)] = 'X';

            DebugLog::log('Game initialization');
        } elseif (!isset($arg)) {
            DebugLog::log('No move data received!');
        }

        if (empty($data)) {
            return false;
        }

        if (isset($data['current_turn']) && $data['current_turn'] == 'E') {
            return $this->answerCallbackQuery(__("This game has ended!"), true);
        }

        if ($this->getCurrentUserId()!== $this->getUserId($data['settings'][$data['current_turn']])) {
            return $this->answerCallbackQuery(__("It's not your turn!"), true);
        }

        $hit = '';
        $gameOutput = '';

        if (isset($arg)) {
            if ($arg === 'null') {
                return $this->answerCallbackQuery();
            }

            if (!isset($data['cylinder'][$arg - 1])) {
                DebugLog::log('Bad move data received: ' . $arg);
                return $this->answerCallbackQuery(__("Invalid move!"), true);
            }

            DebugLog::log('Chamber selected: ' . $arg);

            if ($data['cylinder'][$arg - 1] === 'X') {
                DebugLog::log('Chamber contains bullet, player is dead');

                if ($data['current_turn'] == 'X') {
                    $gameOutput = '<b>' . __("{PLAYER} won!", ['{PLAYER}' => '</b>' . $this->getUserMention($data['settings']['O']) . '<b>']) . '</b>' . PHP_EOL;
                    $gameOutput .= '<b>' . __("{PLAYER} died! (kicked from the game)", ['{PLAYER}' => '</b>' . $this->getUserMention($data['settings']['X']) . '<b>']) . '</b>';
                    if ($data['settings']['X'] === 'host') {
                        $this->data['players']['host'] = $this->data['players']['guest'];
                        $this->data['players']['guest'] = null;
                    } else {
                        $this->data['players']['guest'] = null;
                    }
                } elseif ($data['current_turn'] == 'O') {
                    $gameOutput = '<b>' . __("{PLAYER} won!", ['{PLAYER}' => '</b>' . $this->getUserMention($data['settings']['X']) . '<b>']) . '</b>' . PHP_EOL;
                    $gameOutput .= '<b>' . __("{PLAYER} died! (kicked from the game)", ['{PLAYER}' => '</b>' . $this->getUserMention($data['settings']['O']) . '<b>']) . '</b>';

                    if ($data['settings']['X'] === 'host') {
                        $this->data['players']['host'] = $this->data['players']['guest'];
                        $this->data['players']['guest'] = null;
                    } else {
                        $this->data['players']['guest'] = null;
                    }
                }

                $hit = $arg;

                if ($this->manager->setData($this->data)) {
                    return $this->editMessage($gameOutput . PHP_EOL . PHP_EOL . __('{PLAYER} is waiting for opponent to join...', ['{PLAYER}' => $this->getUserMention('host')]) . PHP_EOL . __('Press {BUTTON} button to join.', ['{BUTTON}' => '<b>\'' . __('Join') . '\'</b>']), $this->gameKeyboard($hit));
                }
            } else {
                $gameOutput = '<b>' . __("{PLAYER} survived!", ['{PLAYER}' => '</b>' . $this->getCurrentUserMention() . '<b>']) . '</b>' . PHP_EOL;

                if ($data['current_turn'] == 'X') {
                    $data['current_turn'] = 'O';
                } elseif ($data['current_turn'] == 'O') {
                    $data['current_turn'] = 'X';
                }

                $data['cylinder'] = ['', '', '', '', '', ''];
                $data['cylinder'][mt_rand(0, 5)] = 'X';
            }
        }

        $gameOutput .= __("Current turn:") . ' ' . $this->getUserMention($data['settings'][$data['current_turn']]);

        DebugLog::log('Cylinder: |' . implode('|', $data['cylinder']) . '|');

        if ($this->manager->setData($this->data)) {
            return $this->editMessage(
                $this->getUserMention('host') . ' ' . __("vs.") . ' ' . $this->getUserMention('guest') . PHP_EOL . PHP_EOL . $gameOutput,
                $this->gameKeyboard($hit)
            );
        }
    }

    /**
     * Keyboard for game in progress
     *
     * @param string $hit
     * @param string $winner
     *
     * @return InlineKeyboard
     */
    protected function gameKeyboard($hit = '', $winner = '')
    {
        $inline_keyboard[] = [
            new InlineKeyboardButton(
                [
                    'text' => $this->symbols['empty'],
                    'callback_data' => $this->manager->getGame()::getCode() . ';game;null'
                ]
            ),
            new InlineKeyboardButton(
                [
                    'text' => ($hit == 1) ? $this->symbols['chamber_hit'] : $this->symbols['chamber'],
                    'callback_data' => $this->manager->getGame()::getCode() . ';game;1'
                ]
            ),
            new InlineKeyboardButton(
                [
                    'text' => ($hit == 2) ? $this->symbols['chamber_hit'] : $this->symbols['chamber'],
                    'callback_data' => $this->manager->getGame()::getCode() . ';game;2'
                ]
            ),
            new InlineKeyboardButton(
                [
                    'text' => $this->symbols['empty'],
                    'callback_data' => $this->manager->getGame()::getCode() . ';game;null'
                ]
            )
        ];

        $inline_keyboard[] = [
            new InlineKeyboardButton(
                [
                    'text' => ($hit == 6) ? $this->symbols['chamber_hit'] : $this->symbols['chamber'],
                    'callback_data' => $this->manager->getGame()::getCode() . ';game;6'
                ]
            ),
            new InlineKeyboardButton(
                [
                    'text' => $this->symbols['empty'],
                    'callback_data' => $this->manager->getGame()::getCode() . ';game;null'
                ]
            ),
            new InlineKeyboardButton(
                [
                    'text' => ($hit == 3) ? $this->symbols['chamber_hit'] : $this->symbols['chamber'],
                    'callback_data' => $this->manager->getGame()::getCode() . ';game;3'
                ]
            )
        ];

        $inline_keyboard[] = [
            new InlineKeyboardButton(
                [
                    'text' => $this->symbols['empty'],
                    'callback_data' => $this->manager->getGame()::getCode() . ';game;null'
                ]
            ),
            new InlineKeyboardButton(
                [
                    'text' => ($hit == 5) ? $this->symbols['chamber_hit'] : $this->symbols['chamber'],
                    'callback_data' => $this->manager->getGame()::getCode() . ';game;5'
                ]
            ),
            new InlineKeyboardButton(
                [
                    'text' => ($hit == 4) ? $this->symbols['chamber_hit'] : $this->symbols['chamber'],
                    'callback_data' => $this->manager->getGame()::getCode() . ';game;4'
                ]
            ),
            new InlineKeyboardButton(
                [
                    'text' => $this->symbols['empty'],
                    'callback_data' => $this->manager->getGame()::getCode() . ';game;null'
                ]
            )
        ];

        if (getenv('DEBUG')) {
            $inline_keyboard[] = [
                new InlineKeyboardButton(
                    [
                        'text' => 'DEBUG: ' . 'Restart',
                        'callback_data' => $this->manager->getGame()::getCode() . ';start'
                    ]
                )
            ];
        }

        if (!is_numeric($hit)) {
            $inline_keyboard[] = [
                new InlineKeyboardButton(
                    [
                        'text'          => __('Quit'),
                        'callback_data' => $this->manager->getGame()::getCode() . ';quit'
                    ]
                ),
                new InlineKeyboardButton(
                    [
                        'text'          => __('Kick'),
                        'callback_data' => $this->manager->getGame()::getCode() . ';kick'
                    ]
                )
            ];
        } else {
            $inline_keyboard[] = [
                new InlineKeyboardButton(
                    [
                        'text'          => __('Quit'),
                        'callback_data' => $this->manager->getGame()::getCode() . ';quit'
                    ]
                ),
                new InlineKeyboardButton(
                    [
                        'text'          => __('Join'),
                        'callback_data' => $this->manager->getGame()::getCode() . ';join'
                    ]
                )
            ];
        }

        $inline_keyboard_markup = new InlineKeyboard(...$inline_keyboard);

        return $inline_keyboard_markup;
    }
}
