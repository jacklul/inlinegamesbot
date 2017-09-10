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
use Bot\Helper\Debug;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Spatie\Emoji\Emoji;

/**
 * Class Rockpaperscissors
 *
 * @package Bot\Entity\Game
 */
class Rockpaperscissors extends Game
{
    /**
     * Game unique id (for callback queries)
     *
     * @var string
     */
    private static $code = 'rps';

    /**
     * Game name
     *
     * @var string
     */
    private static $title = 'Rock-Paper-Scissors';

    /**
     * Game description
     *
     * @var string
     */
    private static $description = 'Rock-paper-scissors is game in which each player simultaneously forms one of three shapes with an outstretched hand.';

    /**
     * Game image (for inline query result)
     *
     * @var string
     */
    private static $image = 'https://i.imgur.com/1H8HI7n.png';

    /**
     * Order on the game list (inline query result)
     *
     * @var int
     */
    private static $order = 3;

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
    protected $symbols = [];

    /**
     * Define game symbols (emojis)
     */
    private function defineSymbols()
    {
        $this->symbols['R'] = 'ROCK';
        $this->symbols['R_short'] = Emoji::raisedFist();
        $this->symbols['P'] = 'PAPER';
        $this->symbols['P_short'] = Emoji::raisedHand();
        $this->symbols['S'] = 'SCISSORS';
        $this->symbols['S_short'] = Emoji::victoryHand();
    }

    /**
     * Game handler
     *
     * @return \Longman\TelegramBot\Entities\ServerResponse|mixed
     */
    protected function gameAction()
    {
        if ($this->getCurrentUserId() !== $this->getUserId('host') && $this->getCurrentUserId() !== $this->getUserId('guest')) {
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

        if ($command === 'start') {
            $data['host_pick'] = '';
            $data['guest_pick'] = '';
            $data['host_wins'] = 0;
            $data['guest_wins'] = 0;
            $data['round'] = 1;
            $data['current_turn'] = '';

            Debug::print('Game initialization');
        } elseif (!isset($arg)) {
            Debug::print('No move data received');
        }

        if (empty($data)) {
            return $this->handleEmptyData();
        }

        if (isset($data['current_turn']) && $data['current_turn'] == 'E') {
            return $this->answerCallbackQuery(__("This game has ended!"), true);
        }

        Debug::print('Argument: ' . $arg);

        if (isset($arg)) {
            if (in_array($arg, ['R', 'P', 'S'])) {
                if ($this->getCurrentUserId() == $this->getUserId('host') && $data['host_pick'] == '') {
                    $data['host_pick'] = $arg;
                } elseif ($this->getCurrentUserId() == $this->getUserId('guest') && $data['guest_pick'] == '') {
                    $data['guest_pick'] = $arg;
                }

                if ($this->saveData($this->data)) {
                    Debug::print($this->getCurrentUserMention() . ' picked ' . $arg);

                    if ($data['host_pick'] == '' || $data['guest_pick'] == '') {
                        return $this->answerCallbackQuery();
                    }
                } else {
                    return $this->returnStorageFailure();
                }
            } else {
                Debug::print('Invalid move data: ' . $arg);

                return $this->answerCallbackQuery(__("Invalid move!"), true);
            }
        }

        $isOver = false;
        $gameOutput = '';
        $hostPick = '';
        $guestPick = '';

        if ($data['host_pick'] != '' && $data['guest_pick'] != '') {
            $isOver = $this->isGameOver($data['host_pick'], $data['guest_pick']);

            if (in_array($isOver, ['X', 'O', 'T'])) {
                $data['round'] += 1;

                if ($isOver == 'X') {
                    $data['host_wins'] = $data['host_wins'] + 1;

                    $gameOutput = '<b>' . __("{PLAYER} won this round!", ['{PLAYER}' => '</b>' . $this->getUserMention('host') . '<b>']) . '</b>' . PHP_EOL;
                } elseif ($isOver == 'O') {
                    $data['guest_wins'] = $data['guest_wins'] + 1;

                    $gameOutput = '<b>' . __("{PLAYER} won this round!", ['{PLAYER}' => '</b>' . $this->getUserMention('guest') . '<b>']) . '</b>' . PHP_EOL;
                } else {
                    $data['host_wins'] += 1;
                    $data['guest_wins'] += 1;

                    $gameOutput = '<b>' . __("This round ended with a draw!") . '</b>' . PHP_EOL;
                }
            }

            $hostPick = ' (' . $this->symbols[$data['host_pick'] . '_short'] . ')';
            $guestPick = ' (' . $this->symbols[$data['guest_pick'] . '_short'] . ')';
        }

        if ($data['host_wins'] >= $data['guest_wins'] + 3 || ($data['round'] > 5 && $data['host_wins'] > $data['guest_wins'])) {
            $gameOutput = '<b>' . __("{PLAYER} won the game!", ['{PLAYER}' => '</b>' . $this->getUserMention('host') . '<b>']) . '</b>';

            $data['current_turn'] = 'E';
        } elseif ($data['guest_wins'] >= $data['host_wins'] + 3 || ($data['round'] > 5 && $data['guest_wins'] > $data['host_wins'])) {
            $gameOutput = '<b>' . __("{PLAYER} won the game!", ['{PLAYER}' => '</b>' . $this->getUserMention('guest') . '<b>']) . '</b>';

            $data['current_turn'] = 'E';
        } else {
            $gameOutput .= '<b>' . __("Round {ROUND} - make your picks!", ['{ROUND}' => $data['round']]) . '</b>';

            $data['host_pick'] = '';
            $data['guest_pick'] = '';
            $isOver = false;
        }

        if ($this->saveData($this->data)) {
            return $this->editMessage(
                $this->getUserMention('host') . $hostPick . ' (' . $data['host_wins'] . ')' . ' ' . __("vs.") . ' ' . $this->getUserMention('guest') . $guestPick . ' (' . $data['guest_wins'] . ')' . PHP_EOL . PHP_EOL . $gameOutput,
                $this->gameKeyboard($isOver)
            );
        } else {
            return $this->returnStorageFailure();
        }
    }

    /**
     * Keyboard for game in progress
     *
     * @param string $isOver
     * @param string $winner
     *
     * @return InlineKeyboard
     */
    protected function gameKeyboard($isOver = false, $winner = '')
    {
        if (!$isOver) {
            $inline_keyboard[] = [
                new InlineKeyboardButton(
                    [
                        'text'          => $this->symbols['R'] . ' ' . $this->symbols['R_short'],
                        'callback_data' => $this->manager->getGame()::getCode() . ';game;R',
                    ]
                ),
                new InlineKeyboardButton(
                    [
                        'text'          => $this->symbols['P'] . ' ' . $this->symbols['P_short'],
                        'callback_data' => $this->manager->getGame()::getCode() . ';game;P',
                    ]
                ),
                new InlineKeyboardButton(
                    [
                        'text'          => $this->symbols['S'] . ' ' . $this->symbols['S_short'],
                        'callback_data' => $this->manager->getGame()::getCode() . ';game;S',
                    ]
                ),
            ];
        } else {
            $inline_keyboard[] = [
                new InlineKeyboardButton(
                    [
                        'text'          => __('Play again!'),
                        'callback_data' => $this->manager->getGame()::getCode() . ';start',
                    ]
                ),
            ];
        }

        if (getenv('DEBUG')) {
            $inline_keyboard[] = [
                new InlineKeyboardButton(
                    [
                        'text'          => 'DEBUG: ' . 'Restart',
                        'callback_data' => $this->manager->getGame()::getCode() . ';start',
                    ]
                ),
            ];
        }

        $inline_keyboard[] = [
            new InlineKeyboardButton(
                [
                    'text'          => __('Quit'),
                    'callback_data' => $this->manager->getGame()::getCode() . ';quit',
                ]
            ),
            new InlineKeyboardButton(
                [
                    'text'          => __('Kick'),
                    'callback_data' => $this->manager->getGame()::getCode() . ';kick',
                ]
            ),
        ];

        $inline_keyboard_markup = new InlineKeyboard(...$inline_keyboard);

        return $inline_keyboard_markup;
    }

    /**
     * Check whenever game is over
     *
     * @param $x
     * @param $y
     *
     * @return string
     */
    private function isGameOver($x, $y)
    {
        if ($x == 'P' && $y == 'R') {
            return 'X';
        }

        if ($y == 'P' && $x == 'R') {
            return 'O';
        }

        if ($x == 'R' && $y == 'S') {
            return 'X';
        }

        if ($y == 'R' && $x == 'S') {
            return 'O';
        }

        if ($x == 'S' && $y == 'P') {
            return 'X';
        }

        if ($y == 'S' && $x == 'P') {
            return 'O';
        }

        if ($y == $x) {
            return 'T';
        }
    }
}
