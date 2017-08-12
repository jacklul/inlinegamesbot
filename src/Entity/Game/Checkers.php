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
 * Class Checkers
 *
 * @TODO direct port from the old version, could be improved, potential bugs may appear
 *
 * @package Bot\Entity\Game
 */
class Checkers extends Game
{
    /**
     * Game unique id (for callback queries)
     *
     * @var string
     */
    private static $code = 'ck';

    /**
     * Game name
     *
     * @var string
     */
    private static $title = 'Checkers';

    /**
     * Game name
     *
     * @var string
     */
    private static $title_extra = '(no flying kings, men cannot capture backwards)';

    /**
     * Game description
     *
     * @var string
     */
    private static $description = 'Checkers is game in which the goal is to capture the other player\'s checkers or make them impossible to move.';

    /**
     * Game image (for inline query result)
     *
     * @var string
     */
    private static $image = 'https://i.imgur.com/mYuCwKA.jpg';

    /**
     * Order on the game list (inline query result)
     *
     * @var int
     */
    private static $order = 5;

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
    public static function getTitleExtra(): string
    {
        return self::$title_extra;
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

        $this->symbols['X'] = Emoji::mediumBlackCircle();
        $this->symbols['XK'] = Emoji::blackMediumSquare();
        $this->symbols['O'] = Emoji::mediumWhiteCircle();
        $this->symbols['OK'] = Emoji::whiteMediumSquare();
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
            $args = explode('-', $callbackquery_data[2]);
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
            $data['move_counter'] = 0;
            $data['current_selection'] = '';

            $data['vote']['host']['draw'] = false;
            $data['vote']['host']['surrender'] = false;
            $data['vote']['guest']['draw'] = false;
            $data['vote']['guest']['surrender'] = false;

            $data['board'] = [
                ['', 'X', '', '', '', 'O', '', 'O'],
                ['X', '', 'X', '', '', '', 'O', ''],
                ['', 'X', '', '', '', 'O', '', 'O'],
                ['X', '', 'X', '', '', '', 'O', ''],
                ['', 'X', '', '', '', 'O', '', 'O'],
                ['X', '', 'X', '', '', '', 'O', ''],
                ['', 'X', '', '', '', 'O', '', 'O'],
                ['X', '', 'X', '', '', '', 'O', ''],
            ];

            Debug::print('Game initialization');
        } elseif (!isset($args) && $command === 'game') {
            Debug::print('No move data received');
        }

        if (empty($data)) {
            return $this->handleEmptyData();
        }

        if (isset($data['current_turn']) && $data['current_turn'] == 'E') {
            return $this->answerCallbackQuery(__("This game has ended!", true));
        }

        $this->max_y = count($data['board']);
        $this->max_x = count($data['board'][0]);

        $killed = false;
        $canChain = true;
        $forcedJump = false;
        $moveLimit = 25;
        $moveLimitReached = false;

        if ($command === 'game') {
            if ($this->getCurrentUserId() === $this->getUserId($data['settings'][$data['current_turn']])) {
                if ($data['current_selection'] != '') {
                    Debug::print('Current selection: ' . $data['current_selection']);

                    if ($data['current_selection'] == $args[0] . $args[1]) {
                        if ($data['current_selection_lock'] == false) {
                            return $this->answerCallbackQuery();
                        } else {
                            return $this->answerCallbackQuery(__("You must make a jump when possible!"), true);
                        }
                    } else {
                        Debug::print('Listing possible moves');

                        $possibleMoves = $this->possibleMoves($data['board'], $data['current_selection']);

                        for ($x = 0; $x < $this->max_x; $x++) {
                            for ($y = 0; $y < $this->max_y; $y++) {
                                if (strpos($data['board'][$x][$y], $data['current_turn']) !== false && $this->possibleMoves($data['board'], $x . $y, true)) {
                                    $forcedJump = true;
                                    $availableMoves = $this->possibleMoves($data['board'], $x . $y);

                                    if (isset($availableMoves['kills'][$args[0] . $args[1]]) && $data['current_selection'] == $x . $y) {
                                        $forcedJump = false;
                                        break 2;
                                    }
                                }
                            }
                        }

                        if (in_array($args[0] . $args[1], $possibleMoves['valid_moves']) && $data['board'][$args[0]][$args[1]] == '') {
                            if ($forcedJump) {
                                return $this->answerCallbackQuery(__("You must make a jump when possible!"), true);
                            }

                            $data['board'][$args[0]][$args[1]] = $data['board'][$data['current_selection'][0]][$data['current_selection'][1]];
                            $data['board'][$data['current_selection'][0]][$data['current_selection'][1]] = '';

                            $data['vote'][$data['settings'][$data['current_turn']]]['surrender'] = false;

                            if (strpos($data['board'][$args[0]][$args[1]], 'K') === false && (($data['current_turn'] == 'X' && $args[1] == 7) || ($data['current_turn'] == 'O' && $args[1] == 0))) {
                                $data['board'][$args[0]][$args[1]] .= 'K';
                                $canChain = false;
                            }

                            if ($possibleMoves['kills'][$args[0] . $args[1]] != '') {
                                $data['board'][$possibleMoves['kills'][$args[0] . $args[1]][0]][$possibleMoves['kills'][$args[0] . $args[1]][1]] = '';
                                $killed = true;
                            }

                            if ($killed && $canChain && $this->possibleMoves($data['board'], $args[0] . $args[1], true)) {
                                $data['current_selection_lock'] = true;
                                $data['current_selection'] = $args[0] . $args[1];
                            } else {
                                if ($data['current_turn'] == 'X') {
                                    $data['current_turn'] = 'O';
                                } else {
                                    $data['current_turn'] = 'X';
                                }

                                $data['current_selection'] = '';
                                $data['current_selection_lock'] = false;
                            }

                            $piecesLeft = $this->piecesLeft($data['board']);

                            if (($piecesLeft['X'] == 1 || $piecesLeft['O'] == 1) && $data['current_selection_lock'] == false) {
                                if (!isset($data['move_counter'])) {
                                    $data['move_counter'] = 1;
                                } else {
                                    $data['move_counter'] = $data['move_counter'] + 1;
                                }

                                if ($data['move_counter'] >= $moveLimit) {
                                    $moveLimitReached = true;
                                }
                            }
                        } else {
                            if ($data['current_selection_lock'] == true) {
                                return $this->answerCallbackQuery(__("You must make a jump when possible!"), true);
                            } elseif ($this->getCurrentUserId() == $this->getUserId('host') && $data['current_turn'] == 'X' && strpos($data['board'][$args[0]][$args[1]], 'X') !== false) {
                                $data['current_selection'] = $args[0] . $args[1];
                            } elseif ($this->getCurrentUserId() == $this->getUserId('guest') && $data['current_turn'] == 'O' && strpos($data['board'][$args[0]][$args[1]], 'O') !== false) {
                                $data['current_selection'] = $args[0] . $args[1];
                            } else {
                                return $this->answerCallbackQuery(__("Invalid move!"), true);
                            }
                        }
                    }
                } elseif ($args[0] !== '' && $args[1] !== '') {
                    if ($this->getCurrentUserId() == $this->getUserId('host') && $data['current_turn'] == 'X' && strpos($data['board'][$args[0]][$args[1]], 'X') !== false) {
                        $data['current_selection'] = $args[0] . $args[1];
                    } elseif ($this->getCurrentUserId() == $this->getUserId('guest') && $data['current_turn'] == 'O' && strpos($data['board'][$args[0]][$args[1]], 'O') !== false) {
                        $data['current_selection'] = $args[0] . $args[1];
                    } elseif ($command === 'game') {
                        return $this->answerCallbackQuery(__("Invalid selection!"), true);
                    } else {
                        return $this->answerCallbackQuery(__("Invalid move!"), true);
                    }
                }
            } else {
                return $this->answerCallbackQuery(__("It's not your turn!"), true);
            }
        }

        Debug::print('Checking if game is over');

        $isOver = $this->isGameOver($data['board']);

        if ($data['vote']['host']['draw'] && $data['vote']['guest']['draw']) {
            $isOver = 'T';
        }

        $gameOutput = '';
        if (in_array($isOver, ['X', 'O', 'T']) || $moveLimitReached) {
            if ($isOver == 'X' || $piecesLeft['X'] > $piecesLeft['O']) {
                $gameOutput .= '<b>' . __("{PLAYER} won!", ['{PLAYER}' => '</b>' . $this->getUserMention('host') . '<b>']) . '</b>';
            } elseif ($isOver == 'O' || $piecesLeft['O'] > $piecesLeft['X']) {
                $gameOutput .= '<b>' . __("{PLAYER} won!", ['{PLAYER}' => '</b>' . $this->getUserMention('guest') . '<b>']) . '</b>';
            } else {
                $gameOutput .= '<b>' . __("Game ended with a draw!") . '</b>';
            }

            $data['current_turn'] = 'E';
            $data['current_selection'] = '';

            Debug::print('Game ended');
        } else {
            $this->selection = $data['current_selection'];

            if ($data['vote']['host']['draw']) {
                $gameOutput .= '<b>' . __("{PLAYER} voted to draw!", ['{PLAYER}' => '</b>' . $this->getUserMention('host') . '<b>']) . '</b>' . PHP_EOL . PHP_EOL;
            } elseif ($data['vote']['guest']['draw']) {
                $gameOutput .= '<b>' . __("{PLAYER} voted to draw!", ['{PLAYER}' => '</b>' . $this->getUserMention('guest') . '<b>']) . '</b>' . PHP_EOL . PHP_EOL;
            }

            $gameOutput .= __("Current turn:") . ' ' . $this->symbols[$data['current_turn']];

            if ($data['current_selection'] == '') {
                $gameOutput .= "\n" . __("(Select the piece you want to move)");
            } else {
                $gameOutput .= "\n" . __("(Selected: {COORDINATES})", ['{COORDINATES}' => ($data['current_selection'][0] + 1) . '-' . ($data['current_selection'][1] + 1)]);

                if ($data['current_selection_lock'] == false) {
                    $gameOutput .= "\n" . __("(Make your move or select different piece)");
                } else {
                    $gameOutput .= "\n" . __("(Your move must continue)");
                }
            }

            Debug::print('Game is still in progress');
        }

        if ($this->saveData($this->data)) {
            return $this->editMessage(
                $this->getUserMention('host') . ' (' . (($data['settings']['X'] == 'host') ? $this->symbols['X'] : $this->symbols['O']) . ')' . ' ' . __("vs.") . ' ' . $this->getUserMention('guest') . ' (' . (($data['settings']['O'] == 'guest') ? $this->symbols['O'] : $this->symbols['X']) . ')' . PHP_EOL . PHP_EOL . $gameOutput,
                $this->gameKeyboard($data['board'], $isOver, $data['move_counter'])
            );
        } else {
            return $this->returnStorageFailure();
        }
    }

    /**
     * Keyboard for game in progress
     *
     * @param array  $board
     * @param string $winner
     * @param int    $moveCounter
     *
     * @return InlineKeyboard
     */
    protected function gameKeyboard($board, $winner = '', $moveCounter = 0)
    {
        for ($x = 0; $x <= $this->max_x; $x++) {
            $tmp_array = [];

            for ($y = 0; $y <= $this->max_y; $y++) {
                if (isset($board[$x][$y])) {
                    if ($board[$x][$y] == 'X' || $board[$x][$y] == 'O' || $board[$x][$y] == 'XK' || $board[$x][$y] == 'OK') {
                        $field = $this->symbols[$board[$x][$y]];
                    } else {
                        $field = '';
                    }

                    if (!isset($field) || $field === '') {
                        $field = ($this->symbols['empty']) ?: ' ';
                    }

                    /*if (isset($this->selection) && $this->selection == $x.$y) {
                        $field = '[' . $field . ']';
                    }*/

                    array_push(
                        $tmp_array,
                        new InlineKeyboardButton(
                            [
                                'text'          => $field,
                                'callback_data' => $this->manager->getGame()::getCode() . ';game;' . $x . '-' . $y,
                            ]
                        )
                    );
                }
            }

            if (!empty($tmp_array)) {
                $inline_keyboard[] = $tmp_array;
            }
        }

        $inline_keyboard = $this->transpose($inline_keyboard);

        $piecesLeft = $this->piecesLeft($board);

        if (!empty($winner)) {
            $inline_keyboard[] = [
                new InlineKeyboardButton(
                    [
                        'text'          => __('Play again!'),
                        'callback_data' => $this->manager->getGame()::getCode() . ';start',
                    ]
                ),
            ];
        } else {
            if ($moveCounter == 0 || ($moveCounter > 0 && $piecesLeft['X'] != $piecesLeft['O'])) {
                $inline_keyboard[] = [
                    new InlineKeyboardButton(
                        [
                            'text'          => __('Surrender'),
                            'callback_data' => $this->manager->getGame()::getCode() . ';forfeit',
                        ]
                    ),
                ];
            } else {
                $inline_keyboard[] = [
                    new InlineKeyboardButton(
                        [
                            'text'          => __('Vote to draw'),
                            'callback_data' => $this->manager->getGame()::getCode() . ';draw',
                        ]
                    ),
                ];
            };
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

        if (getenv('DEBUG')) {
            $this->boardPrint($board);

            $inline_keyboard[] = [
                new InlineKeyboardButton(
                    [
                        'text'          => 'DEBUG: ' . 'Restart',
                        'callback_data' => $this->manager->getGame()::getCode() . ';start',
                    ]
                ),
                new InlineKeyboardButton(
                    [
                        'text'          => 'DEBUG: ' . 'Surrender',
                        'callback_data' => $this->manager->getGame()::getCode() . ';fortfeit',
                    ]
                ),
                new InlineKeyboardButton(
                    [
                        'text'          => 'DEBUG: ' . 'Draw',
                        'callback_data' => $this->manager->getGame()::getCode() . ';draw',
                    ]
                ),
            ];
        }

        $inline_keyboard_markup = new InlineKeyboard(...$inline_keyboard);

        return $inline_keyboard_markup;
    }

    /**
     * Generate list of possible moves
     *
     * @param $board
     * @param $selection
     * @param bool      $onlykill
     * @param string    $char
     *
     * @return array|bool
     */
    private function possibleMoves($board, $selection, $onlykill = false, $char = '')
    {
        $valid_moves = [];
        $kill = [];

        $sel_x = $selection[0];
        $sel_y = $selection[1];

        if ($char == '') {
            $char = $board[$sel_x][$sel_y];
        }

        if (strpos($char, 'X') !== false) {
            if ($board[$sel_x][$sel_y] == 'X') {
                if ($board[($sel_x - 1)][($sel_y + 1)] == '') {
                    $valid_moves[] = ($sel_x - 1) . ($sel_y + 1); //right up
                }

                if ($board[($sel_x + 1)][($sel_y + 1)] == '') {
                    $valid_moves[] = ($sel_x + 1) . ($sel_y + 1); //right down
                }

                if (strpos($board[($sel_x - 1)][($sel_y + 1)], 'O') !== false) {
                    $valid_moves[] = ($sel_x - 2) . ($sel_y + 2); //right up
                    $kill[($sel_x - 2) . ($sel_y + 2)] = ($sel_x - 1) . ($sel_y + 1);
                }

                if (strpos($board[($sel_x + 1)][($sel_y + 1)], 'O') !== false) {
                    $valid_moves[] = ($sel_x + 2) . ($sel_y + 2); //right down
                    $kill[($sel_x + 2) . ($sel_y + 2)] = ($sel_x + 1) . ($sel_y + 1);
                }
            } elseif ($board[$sel_x][$sel_y] == 'XK') {
                if ($board[($sel_x - 1)][($sel_y + 1)] == '') {
                    $valid_moves[] = ($sel_x - 1) . ($sel_y + 1); //right up
                }

                if ($board[($sel_x + 1)][($sel_y + 1)] == '') {
                    $valid_moves[] = ($sel_x + 1) . ($sel_y + 1); //right down
                }

                if ($board[($sel_x + 1)][($sel_y - 1)] == '') {
                    $valid_moves[] = ($sel_x + 1) . ($sel_y - 1); //left up
                }

                if ($board[($sel_x - 1)][($sel_y - 1)] == '') {
                    $valid_moves[] = ($sel_x - 1) . ($sel_y - 1); //left down
                }

                if (strpos($board[($sel_x - 1)][($sel_y + 1)], 'O') !== false) {
                    $valid_moves[] = ($sel_x - 2) . ($sel_y + 2); //right up
                    $kill[($sel_x - 2) . ($sel_y + 2)] = ($sel_x - 1) . ($sel_y + 1);
                }

                if (strpos($board[($sel_x + 1)][($sel_y + 1)], 'O') !== false) {
                    $valid_moves[] = ($sel_x + 2) . ($sel_y + 2); //right down
                    $kill[($sel_x + 2) . ($sel_y + 2)] = ($sel_x + 1) . ($sel_y + 1);
                }

                if (strpos($board[($sel_x + 1)][($sel_y - 1)], 'O') !== false) {
                    $valid_moves[] = ($sel_x + 2) . ($sel_y - 2); //left up
                    $kill[($sel_x + 2) . ($sel_y - 2)] = ($sel_x + 1) . ($sel_y - 1);
                }

                if (strpos($board[($sel_x - 1)][($sel_y - 1)], 'O') !== false) {
                    $valid_moves[] = ($sel_x - 2) . ($sel_y - 2); //left down
                    $kill[($sel_x - 2) . ($sel_y - 2)] = ($sel_x - 1) . ($sel_y - 1);
                }
            }
        } elseif (strpos($char, 'O') !== false) {
            if ($board[$sel_x][$sel_y] == 'O') {
                if ($board[($sel_x + 1)][($sel_y - 1)] == '') {
                    $valid_moves[] = ($sel_x + 1) . ($sel_y - 1); //left up
                }

                if ($board[($sel_x - 1)][($sel_y - 1)] == '') {
                    $valid_moves[] = ($sel_x - 1) . ($sel_y - 1); //left down
                }

                if (strpos($board[($sel_x + 1)][($sel_y - 1)], 'X') !== false) {
                    $valid_moves[] = ($sel_x + 2) . ($sel_y - 2); //left up
                    $kill[($sel_x + 2) . ($sel_y - 2)] = ($sel_x + 1) . ($sel_y - 1);
                }

                if (strpos($board[($sel_x - 1)][($sel_y - 1)], 'X') !== false) {
                    $valid_moves[] = ($sel_x - 2) . ($sel_y - 2); //left down
                    $kill[($sel_x - 2) . ($sel_y - 2)] = ($sel_x - 1) . ($sel_y - 1);
                }
            } elseif ($board[$sel_x][$sel_y] == 'OK') {
                if ($board[($sel_x - 1)][($sel_y + 1)] == '') {
                    $valid_moves[] = ($sel_x - 1) . ($sel_y + 1); //right up
                }

                if ($board[($sel_x + 1)][($sel_y + 1)] == '') {
                    $valid_moves[] = ($sel_x + 1) . ($sel_y + 1); //right down
                }

                if ($board[($sel_x + 1)][($sel_y - 1)] == '') {
                    $valid_moves[] = ($sel_x + 1) . ($sel_y - 1); //left up
                }

                if ($board[($sel_x - 1)][($sel_y - 1)] == '') {
                    $valid_moves[] = ($sel_x - 1) . ($sel_y - 1); //left down
                }

                if (strpos($board[($sel_x - 1)][($sel_y + 1)], 'X') !== false) {
                    $valid_moves[] = ($sel_x - 2) . ($sel_y + 2); //right up
                    $kill[($sel_x - 2) . ($sel_y + 2)] = ($sel_x - 1) . ($sel_y + 1);
                }

                if (strpos($board[($sel_x + 1)][($sel_y + 1)], 'X') !== false) {
                    $valid_moves[] = ($sel_x + 2) . ($sel_y + 2); //right down
                    $kill[($sel_x + 2) . ($sel_y + 2)] = ($sel_x + 1) . ($sel_y + 1);
                }

                if (strpos($board[($sel_x + 1)][($sel_y - 1)], 'X') !== false) {
                    $valid_moves[] = ($sel_x + 2) . ($sel_y - 2); //left up
                    $kill[($sel_x + 2) . ($sel_y - 2)] = ($sel_x + 1) . ($sel_y - 1);
                }

                if (strpos($board[($sel_x - 1)][($sel_y - 1)], 'X') !== false) {
                    $valid_moves[] = ($sel_x - 2) . ($sel_y - 2); //left down
                    $kill[($sel_x - 2) . ($sel_y - 2)] = ($sel_x - 1) . ($sel_y - 1);
                }
            }
        }

        if ($onlykill) {
            foreach ($kill as $thismove => $thiskill) {
                $thismove = (string)$thismove;

                if (preg_match('/\-/', $thismove) || $thismove[0] >= $this->max_x || $thismove[1] >= $this->max_y || $thismove[0] < 0 || $thismove[1] < 0) {
                    continue;
                }

                if ($board[$thismove[0]][$thismove[1]] == '') {
                    return true;
                }
            }

            return false;
        }

        foreach ($valid_moves as $key => $value) {
            $x = substr($value, 0, 1);
            $y = substr($value, 1, 1);

            if (strlen($value) > 2) {
                unset($valid_moves[$key]);
            } elseif ($x < 0 || $y < 0 || $x >= $this->max_x || $y >= $this->max_y || preg_match('/\-/', $value)) {
                unset($valid_moves[$key]);
                unset($kill[$value]);
            } elseif ($board[$x][$y] != '') {
                unset($valid_moves[$key]);
                unset($kill[$x . $y]);
            }
        }

        $c = function ($v) {
            if (is_array($v)) {
                return array_filter($v) != [];
            }
        };

        array_filter($valid_moves, $c);
        array_filter($kill, $c);

        return [
            'valid_moves' => $valid_moves,
            'kills'       => $kill,
        ];
    }

    /**
     * Check how many pieces is left
     *
     * @param $board
     *
     * @return array
     */
    private function piecesLeft($board)
    {
        $xs = 0;
        $ys = 0;

        for ($x = 0; $x < $this->max_x; $x++) {
            for ($y = 0; $y < $this->max_y; $y++) {
                if (strpos($board[$x][$y], 'X') !== false) {
                    $xs++;
                } elseif (strpos($board[$x][$y], 'O') !== false) {
                    $ys++;
                }
            }
        }

        return [
            'X' => $xs,
            'O' => $ys,
        ];
    }

    /**
     * Check whenever game is over
     *
     * @param $board
     *
     * @return string
     */
    protected function isGameOver($board)
    {
        $array = $this->piecesLeft($board);

        if ($array['O'] == 0) {
            return 'X';
        } elseif ($array['X'] == 0) {
            return 'O';
        }

        $availableMoves_X = [];
        $availableMoves_O = [];

        for ($x = 0; $x < $this->max_x; $x++) {
            for ($y = 0; $y < $this->max_y; $y++) {
                if (strpos($board[$x][$y], 'X') !== false) {
                    array_push($availableMoves_X, $this->possibleMoves($board, $x . $y, false, 'X"'));
                } elseif (strpos($board[$x][$y], 'O') !== false) {
                    array_push($availableMoves_O, $this->possibleMoves($board, $x . $y, false, 'O'));
                }
            }
        }

        $noMovesX = true;
        foreach ($availableMoves_X as $move) {
            if (count($move['valid_moves']) > 0) {
                $noMovesX = false;
                break;
            }
        }

        $noMovesO = true;
        foreach ($availableMoves_O as $move) {
            if (count($move['valid_moves']) > 0) {
                $noMovesO = false;
                break;
            }
        }

        if ($noMovesO) {
            return 'X';
        } elseif ($noMovesX) {
            return 'O';
        }
    }

    /**
     * Invert the array table for display
     *
     * @param $array
     *
     * @return mixed
     */
    private function transpose($array)
    {
        array_unshift($array, null);

        return call_user_func_array('array_map', $array);
    }

    /**
     * Handle user surrender
     *
     * @return bool|\Longman\TelegramBot\Entities\ServerResponse|mixed
     */
    protected function forfeitAction()
    {
        if ($this->getCurrentUserId() !== $this->getUserId('host') && $this->getCurrentUserId() !== $this->getUserId('guest')) {
            return $this->answerCallbackQuery(__("You're not in this game!"), true);
        }

        $this->defineSymbols();

        $this->max_y = count($this->data['data']['board']);
        $this->max_x = count($this->data['data']['board'][0]);

        if ($this->getUser('host') && $this->getCurrentUserId() == $this->getUserId('host')) {
            if ($this->data['data']['vote']['host']['surrender']) {
                Debug::print($this->getCurrentUserMention() . ' surrendered');

                $gameOutput = '<b>' . __("{PLAYER} won!", ['{PLAYER}' => '</b>' . $this->getUserMention('guest') . '<b>']) . '</b>' . PHP_EOL;
                $gameOutput .= '<b>' . __("{PLAYER} surrendered!", ['{PLAYER}' => '</b>' . $this->getUserMention('host') . '<b>']) . '</b>' . PHP_EOL;

                $this->data['data']['current_turn'] = 'E';

                if ($this->saveData($this->data)) {
                    return $this->editMessage(
                        $this->getUserMention('host') . ' (' . (($this->data['data']['settings']['X'] == 'host') ? $this->symbols['X'] : $this->symbols['O']) . ')' . ' ' . __("vs.") . ' ' . $this->getUserMention('guest') . ' (' . (($this->data['data']['settings']['O'] == 'guest') ? $this->symbols['O'] : $this->symbols['X']) . ')' . PHP_EOL . PHP_EOL . $gameOutput,
                        $this->gameKeyboard($this->data['data']['board'], 'surrender')
                    );
                } else {
                    return $this->returnStorageFailure();
                }
            }

            Debug::print($this->getCurrentUserMention() . ' voted to surrender');
            $this->data['data']['vote']['host']['surrender'] = true;

            if ($this->saveData($this->data)) {
                return $this->answerCallbackQuery(__("Press the button again to surrender!"), true);
            } else {
                return $this->returnStorageFailure();
            }
        } elseif ($this->getUser('guest') && $this->getCurrentUserId() == $this->getUserId('guest')) {
            if ($this->data['data']['vote']['guest']['surrender']) {
                Debug::print($this->getCurrentUserMention() . ' surrendered');

                $gameOutput = '<b>' . __("{PLAYER} won!", ['{PLAYER}' => '</b>' . $this->getUserMention('host') . '<b>']) . '</b>' . PHP_EOL;
                $gameOutput .= '<b>' . __("{PLAYER} surrendered!", ['{PLAYER}' => '</b>' . $this->getUserMention('guest') . '<b>']) . '</b>' . PHP_EOL;

                $this->data['data']['current_turn'] = 'E';

                if ($this->saveData($this->data)) {
                    return $this->editMessage(
                        $this->getUserMention('host') . ' (' . (($this->data['data']['settings']['X'] == 'host') ? $this->symbols['X'] : $this->symbols['O']) . ')' . ' ' . __("vs.") . ' ' . $this->getUserMention('guest') . ' (' . (($this->data['data']['settings']['O'] == 'guest') ? $this->symbols['O'] : $this->symbols['X']) . ')' . PHP_EOL . PHP_EOL . $gameOutput,
                        $this->gameKeyboard($this->data['data']['board'], 'surrender')
                    );
                } else {
                    return $this->returnStorageFailure();
                }
            }

            Debug::print($this->getCurrentUserMention() . ' voted to surrender');
            $this->data['data']['vote']['guest']['surrender'] = true;

            if ($this->saveData($this->data)) {
                return $this->answerCallbackQuery(__("Press the button again to surrender!"), true);
            } else {
                return $this->returnStorageFailure();
            }
        } else {
            Debug::print('Someone else executed forfeit action');

            return $this->answerCallbackQuery();
        }
    }

    /**
     * Handle votes for draw
     *
     * @return bool|\Longman\TelegramBot\Entities\ServerResponse|mixed
     */
    protected function drawAction()
    {
        if ($this->getCurrentUserId() !== $this->getUserId('host') && $this->getCurrentUserId() !== $this->getUserId('guest')) {
            return $this->answerCallbackQuery(__("You're not in this game!"), true);
        }

        $this->defineSymbols();

        $this->max_y = count($this->data['data']['board']);
        $this->max_x = count($this->data['data']['board'][0]);

        if ($this->getUser('host') && $this->getCurrentUserId() == $this->getUserId('host') && !$this->data['data']['vote']['host']['draw']) {
            $this->data['data']['vote']['host']['draw'] = true;

            if ($this->saveData($this->data)) {
                Debug::print($this->getCurrentUserMention() . ' voted to draw');

                return $this->gameAction();
            } else {
                return $this->returnStorageFailure();
            }
        } elseif ($this->getUser('guest') && $this->getCurrentUserId() == $this->getUserId('guest') && !$this->data['data']['vote']['guest']['draw']) {
            $this->data['data']['vote']['guest']['draw'] = true;

            if ($this->saveData($this->data)) {
                Debug::print($this->getCurrentUserMention() . ' voted to draw');

                return $this->gameAction();
            } else {
                return $this->returnStorageFailure();
            }
        } else {
            return $this->answerCallbackQuery(__("You already voted!"), true);
        }
    }
}
