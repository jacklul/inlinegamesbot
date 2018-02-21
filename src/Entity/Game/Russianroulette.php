<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2016-2018 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace jacklul\inlinegamesbot\Entity\Game;

use jacklul\inlinegamesbot\Entity\Game;
use jacklul\inlinegamesbot\Helper\Utilities;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Spatie\Emoji\Emoji;

/**
 * Class Russianroulette
 *
 * @package jacklul\inlinegamesbot\Entity\Game
 */
class Russianroulette extends Game
{
    /**
     * Game unique ID
     *
     * @var string
     */
    protected static $code = 'rr';

    /**
     * Game name / title
     *
     * @var string
     */
    protected static $title = 'Russian Roulette';

    /**
     * Game description
     *
     * @var string
     */
    protected static $description = 'Russian roulette is a game of chance in which a player places a single round in a revolver, spins the cylinder, places the muzzle against their head, and pulls the trigger.';

    /**
     * Game thumbnail image
     *
     * @var string
     */
    protected static $image = 'https://i.imgur.com/LffxQLK.jpg';

    /**
     * Order on the games list
     *
     * @var int
     */
    protected static $order = 4;

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
     *
     * @throws \jacklul\inlinegamesbot\Exception\BotException
     * @throws \Longman\TelegramBot\Exception\TelegramException
     * @throws \jacklul\inlinegamesbot\Exception\StorageException
     */
    protected function gameAction()
    {
        if ($this->getCurrentUserId() !== $this->getUserId('host') && $this->getCurrentUserId() !== $this->getUserId('guest')) {
            return $this->answerCallbackQuery(__("You're not in this game!"), true);
        }

        $data = &$this->data['game_data'];

        $this->defineSymbols();

        $callbackquery_data = $this->manager->getUpdate()->getCallbackQuery()->getData();
        $callbackquery_data = explode(';', $callbackquery_data);

        $command = $callbackquery_data[1];

        $arg = null;
        if (isset($callbackquery_data[2])) {
            $arg = $callbackquery_data[2];
        }

        if ($command === 'start') {
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

            Utilities::isDebugPrintEnabled() && Utilities::debugPrint('Game initialization');
        } elseif ($arg === null) {
            Utilities::isDebugPrintEnabled() && Utilities::debugPrint('No move data received');
        }

        if (empty($data)) {
            return $this->handleEmptyData();
        }

        if (isset($data['current_turn']) && $data['current_turn'] == 'E') {
            return $this->answerCallbackQuery(__("This game has ended!"), true);
        }

        if ($this->getCurrentUserId() !== $this->getUserId($data['settings'][$data['current_turn']]) && $command !== 'start') {
            return $this->answerCallbackQuery(__("It's not your turn!"), true);
        }

        $hit = '';
        $gameOutput = '';

        if (isset($arg)) {
            if ($arg === 'null') {
                return $this->answerCallbackQuery();
            }

            if (!isset($data['cylinder'][$arg - 1])) {
                Utilities::isDebugPrintEnabled() && Utilities::debugPrint('Bad move data received: ' . $arg);

                return $this->answerCallbackQuery(__("Invalid move!"), true);
            }

            Utilities::isDebugPrintEnabled() && Utilities::debugPrint('Chamber selected: ' . $arg);

            if ($data['cylinder'][$arg - 1] === 'X') {
                Utilities::isDebugPrintEnabled() && Utilities::debugPrint('Chamber contains bullet, player is dead');

                if ($data['current_turn'] == 'X') {
                    $gameOutput = '<b>' . __("{PLAYER} won!", ['{PLAYER}' => '</b>' . $this->getUserMention($data['settings']['O']) . '<b>']) . '</b>' . PHP_EOL;
                    $gameOutput .= '<b>' . __("{PLAYER} died! (kicked from the game)", ['{PLAYER}' => '</b>' . $this->getUserMention($data['settings']['X']) . '<b>']) . '</b>';
                    if ($data['settings']['X'] === 'host') {
                        $this->data['players']['host'] = $this->data['players']['guest'];
                        $this->data['players']['guest'] = null;
                    } else {
                        $this->data['players']['guest'] = null;
                    }

                    $data['current_turn'] = 'E';
                } elseif ($data['current_turn'] == 'O') {
                    $gameOutput = '<b>' . __("{PLAYER} won!", ['{PLAYER}' => '</b>' . $this->getUserMention($data['settings']['X']) . '<b>']) . '</b>' . PHP_EOL;
                    $gameOutput .= '<b>' . __("{PLAYER} died! (kicked from the game)", ['{PLAYER}' => '</b>' . $this->getUserMention($data['settings']['O']) . '<b>']) . '</b>';

                    if ($data['settings']['X'] === 'host') {
                        $this->data['players']['host'] = $this->data['players']['guest'];
                        $this->data['players']['guest'] = null;
                    } else {
                        $this->data['players']['guest'] = null;
                    }

                    $data['current_turn'] = 'E';
                }

                $hit = $arg;

                if ($this->saveData($this->data)) {
                    return $this->editMessage($gameOutput . PHP_EOL . PHP_EOL . __('{PLAYER_HOST} is waiting for opponent to join...', ['{PLAYER_HOST}' => $this->getUserMention('host')]) . PHP_EOL . __('Press {BUTTON} button to join.', ['{BUTTON}' => '<b>\'' . __('Join') . '\'</b>']), $this->customGameKeyboard($hit));
                } else {
                    return $this->returnStorageFailure();
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

        Utilities::isDebugPrintEnabled() && Utilities::debugPrint('Cylinder: |' . implode('|', $data['cylinder']) . '|');

        if ($this->saveData($this->data)) {
            return $this->editMessage(
                $this->getUserMention('host') . ' ' . __("vs.") . ' ' . $this->getUserMention('guest') . PHP_EOL . PHP_EOL . $gameOutput,
                $this->customGameKeyboard($hit)
            );
        } else {
            return $this->returnStorageFailure();
        }
    }

    /**
     * Keyboard for game in progress
     *
     * @param string $hit
     *
     * @return InlineKeyboard
     */
    protected function customGameKeyboard(string $hit = null)
    {
        $inline_keyboard[] = [
            new InlineKeyboardButton(
                [
                    'text'          => $this->symbols['empty'],
                    'callback_data' => self::getCode() . ';game;null',
                ]
            ),
            new InlineKeyboardButton(
                [
                    'text'          => ($hit == 1) ? $this->symbols['chamber_hit'] : $this->symbols['chamber'],
                    'callback_data' => self::getCode() . ';game;1',
                ]
            ),
            new InlineKeyboardButton(
                [
                    'text'          => ($hit == 2) ? $this->symbols['chamber_hit'] : $this->symbols['chamber'],
                    'callback_data' => self::getCode() . ';game;2',
                ]
            ),
            new InlineKeyboardButton(
                [
                    'text'          => $this->symbols['empty'],
                    'callback_data' => self::getCode() . ';game;null',
                ]
            ),
        ];

        $inline_keyboard[] = [
            new InlineKeyboardButton(
                [
                    'text'          => ($hit == 6) ? $this->symbols['chamber_hit'] : $this->symbols['chamber'],
                    'callback_data' => self::getCode() . ';game;6',
                ]
            ),
            new InlineKeyboardButton(
                [
                    'text'          => $this->symbols['empty'],
                    'callback_data' => self::getCode() . ';game;null',
                ]
            ),
            new InlineKeyboardButton(
                [
                    'text'          => ($hit == 3) ? $this->symbols['chamber_hit'] : $this->symbols['chamber'],
                    'callback_data' => self::getCode() . ';game;3',
                ]
            ),
        ];

        $inline_keyboard[] = [
            new InlineKeyboardButton(
                [
                    'text'          => $this->symbols['empty'],
                    'callback_data' => self::getCode() . ';game;null',
                ]
            ),
            new InlineKeyboardButton(
                [
                    'text'          => ($hit == 5) ? $this->symbols['chamber_hit'] : $this->symbols['chamber'],
                    'callback_data' => self::getCode() . ';game;5',
                ]
            ),
            new InlineKeyboardButton(
                [
                    'text'          => ($hit == 4) ? $this->symbols['chamber_hit'] : $this->symbols['chamber'],
                    'callback_data' => self::getCode() . ';game;4',
                ]
            ),
            new InlineKeyboardButton(
                [
                    'text'          => $this->symbols['empty'],
                    'callback_data' => self::getCode() . ';game;null',
                ]
            ),
        ];

        if (!is_numeric($hit)) {
            $inline_keyboard[] = [
                new InlineKeyboardButton(
                    [
                        'text'          => __('Quit'),
                        'callback_data' => self::getCode() . ';quit',
                    ]
                ),
                new InlineKeyboardButton(
                    [
                        'text'          => __('Kick'),
                        'callback_data' => self::getCode() . ';kick',
                    ]
                ),
            ];
        } else {
            $inline_keyboard[] = [
                new InlineKeyboardButton(
                    [
                        'text'          => __('Quit'),
                        'callback_data' => self::getCode() . ';quit',
                    ]
                ),
                new InlineKeyboardButton(
                    [
                        'text'          => __('Join'),
                        'callback_data' => self::getCode() . ';join',
                    ]
                ),
            ];
        }

        if (getenv('DEBUG')) {
            $inline_keyboard[] = [
                new InlineKeyboardButton(
                    [
                        'text'          => 'DEBUG: ' . 'Restart',
                        'callback_data' => self::getCode() . ';start',
                    ]
                ),
            ];
        }

        $inline_keyboard_markup = new InlineKeyboard(...$inline_keyboard);

        return $inline_keyboard_markup;
    }
}
