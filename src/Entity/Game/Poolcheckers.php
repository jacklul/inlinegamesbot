<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2016-2022 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bot\Entity\Game;

use Bot\Exception\BotException;
use Bot\Exception\StorageException;
use Bot\Helper\Utilities;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Spatie\Emoji\Emoji;

/**
 * Pool Checkers
 */
class Poolcheckers extends Checkers
{
    /**
     * Game unique ID
     *
     * @var string
     */
    protected static $code = 'pck';

    /**
     * Game name / title
     *
     * @var string
     */
    protected static $title = 'Pool Checkers';

    /**
     * Game name
     *
     * @var string
     */
    protected static $title_extra = '(flying kings, men can capture backwards)';

    /**
     * Order on the games list
     *
     * @var int
     */
    protected static $order = 41;

    /**
     * Game related variables
     */
    protected $selection;

    /**
     * @return string
     */
    public static function getTitleExtra(): string
    {
        return self::$title_extra;
    }

    /**
     * Handle votes for draw
     *
     * @return bool|ServerResponse|mixed
     *
     * @throws BotException
     * @throws TelegramException
     * @throws StorageException
     */
    protected function drawAction()
    {
        if ($this->getCurrentUserId() !== $this->getUserId('host') && $this->getCurrentUserId() !== $this->getUserId('guest')) {
            return $this->answerCallbackQuery(__("You're not in this game!"), true);
        }

        $data = &$this->data['game_data'];

        if ((isset($data['current_turn']) && $data['current_turn'] == 'E') || $data['board'] === null) {
            return $this->answerCallbackQuery(__("This game has ended!", true));
        }

        $this->defineSymbols();

        $this->max_y = count($data['board']);
        $this->max_x = count($data['board'][0]);

        if ($this->getUser('host') && $this->getCurrentUserId() === $this->getUserId('host') && !$data['vote']['host']['draw']) {
            $data['vote']['host']['draw'] = true;

            if ($this->saveData($this->data)) {
                Utilities::isDebugPrintEnabled() && Utilities::debugPrint($this->getCurrentUserMention() . ' voted to draw');

                return $this->gameAction();
            }
        }

        if ($this->getUser('guest') && $this->getCurrentUserId() === $this->getUserId('guest') && !$data['vote']['guest']['draw']) {
            $data['vote']['guest']['draw'] = true;

            if ($this->saveData($this->data)) {
                Utilities::isDebugPrintEnabled() && Utilities::debugPrint($this->getCurrentUserMention() . ' voted to draw');

                return $this->gameAction();
            }
        }

        if ((!$this->getUser('host') || $this->getCurrentUserId() !== $this->getUserId('host')) && (!$this->getUser('guest') || $this->getCurrentUserId() !== $this->getUserId('guest'))) {
            return $this->answerCallbackQuery();
        }

        return $this->answerCallbackQuery(__("You already voted!"), true);
    }

    /**
     * Game handler
     *
     * @return ServerResponse
     *
     * @throws BotException
     * @throws TelegramException
     * @throws StorageException
     */
    protected function gameAction(): ServerResponse
    {
        if ($this->getCurrentUserId() !== $this->getUserId('host') && $this->getCurrentUserId() !== $this->getUserId('guest')) {
            return $this->answerCallbackQuery(__("You're not in this game!"), true);
        }

        $data = &$this->data['game_data'];

        $this->defineSymbols();

        $callbackquery_data = $this->manager->getUpdate()->getCallbackQuery()->getData();
        $callbackquery_data = explode(';', $callbackquery_data);

        $command = $callbackquery_data[1];

        $args = null;
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
            $data['current_selection_lock'] = false;

            $data['vote']['host']['draw'] = false;
            $data['vote']['host']['surrender'] = false;
            $data['vote']['guest']['draw'] = false;
            $data['vote']['guest']['surrender'] = false;

            $data['board'] = static::$board;

            Utilities::debugPrint('Game initialization');
        } elseif ($args === null && $command === 'game') {
            Utilities::debugPrint('No move data received');
        }

        if (isset($data['current_turn']) && $data['current_turn'] == 'E') {
            return $this->answerCallbackQuery(__("This game has ended!", true));
        }

        $this->max_y = count($data['board']);
        $this->max_x = count($data['board'][0]);

        $killed = false;
        $forcedJump = false;
        $moveLimit = 25;
        $moveLimitReached = false;
        $piecesLeft = null;

        if ($command === 'game') {
            if ($this->getCurrentUserId() === $this->getUserId($data['settings'][$data['current_turn']])) {
                if ($data['current_selection'] != '') {
                    Utilities::debugPrint('Current selection: ' . $data['current_selection']);

                    if ($data['current_selection'] == $args[0] . $args[1]) {
                        if ($data['current_selection_lock'] == false) {
                            return $this->answerCallbackQuery();
                        } else {
                            return $this->answerCallbackQuery(__("You must make a jump when possible!"), true);
                        }
                    } else {
                        Utilities::debugPrint('Listing possible moves');

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

                        if (in_array($args[0] . $args[1], $possibleMoves['valid_moves']) && isset($data['board'][$args[0]][$args[1]]) && $data['board'][$args[0]][$args[1]] == '') {
                            if ($forcedJump) {
                                return $this->answerCallbackQuery(__("You must make a jump when possible!"), true);
                            }

                            $data['board'][$args[0]][$args[1]] = $data['board'][$data['current_selection'][0]][$data['current_selection'][1]];
                            $data['board'][$data['current_selection'][0]][$data['current_selection'][1]] = '';

                            $data['vote'][$data['settings'][$data['current_turn']]]['surrender'] = false;
                            $data['vote'][$data['settings'][$data['current_turn']]]['draw'] = false;

                            if (strpos($data['board'][$args[0]][$args[1]], 'K') === false && (($data['current_turn'] == 'X' && $args[1] == 7) || ($data['current_turn'] == 'O' && $args[1] == 0))) {
                                $data['board'][$args[0]][$args[1]] .= 'K';
                            }

                            if (isset($possibleMoves['kills'][$args[0] . $args[1]]) && $possibleMoves['kills'][$args[0] . $args[1]] != '') {
                                $data['board'][$possibleMoves['kills'][$args[0] . $args[1]][0]][$possibleMoves['kills'][$args[0] . $args[1]][1]] = '';
                                $killed = true;
                            }

                            if ($killed == true && $this->possibleMoves($data['board'], $args[0] . $args[1], true, null, $data['current_selection'][0] . $data['current_selection'][1])) {
                                $data['current_selection_lock'] = true;
                                $data['current_selection'] = $args[0] . $args[1];
                            } else {
                                if (strpos($data['board'][$args[0]][$args[1]], 'K') === false && (($data['current_turn'] == 'X' && $args[1] == 7) || ($data['current_turn'] == 'O' && $args[1] == 0))) {
                                    $data['board'][$args[0]][$args[1]] .= 'K';
                                }

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
                            } elseif ($this->getCurrentUserId() === $this->getUserId($data['settings'][$data['current_turn']]) && strpos($data['board'][$args[0]][$args[1]], $data['current_turn']) !== false) {
                                $data['current_selection'] = $args[0] . $args[1];
                            } else {
                                return $this->answerCallbackQuery(__("Invalid move!"), true);
                            }
                        }
                    }
                } elseif ($args[0] !== '' && $args[1] !== '') {
                    if ($this->getCurrentUserId() == $this->getUserId($data['settings'][$data['current_turn']]) && strpos($data['board'][$args[0]][$args[1]], $data['current_turn']) !== false) {
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

        Utilities::debugPrint('Checking if game is over');

        $isOver = $this->isGameOver($data['board']);

        if ($data['vote']['host']['draw'] && $data['vote']['guest']['draw']) {
            $isOver = 'T';
        }

        $gameOutput = '';
        if (in_array($isOver, ['X', 'O', 'T']) || $moveLimitReached) {
            if ($isOver == 'X' || $piecesLeft['X'] > $piecesLeft['O']) {
                $gameOutput .= Emoji::trophy() . ' <b>' . __("{PLAYER} won!", ['{PLAYER}' => '</b>' . $this->getUserMention('host') . '<b>']) . '</b>';
            } elseif ($isOver == 'O' || $piecesLeft['O'] > $piecesLeft['X']) {
                $gameOutput .= Emoji::trophy() . ' <b>' . __("{PLAYER} won!", ['{PLAYER}' => '</b>' . $this->getUserMention('guest') . '<b>']) . '</b>';
            } else {
                $gameOutput .= Emoji::chequeredFlag() . ' <b>' . __("Game ended with a draw!") . '</b>';
            }

            $data['current_turn'] = 'E';
            $data['current_selection'] = '';

            Utilities::debugPrint('Game ended');
        } else {
            $this->selection = $data['current_selection'];

            if ($data['vote']['host']['draw']) {
                $gameOutput .= '<b>' . __("{PLAYER} voted to draw!", ['{PLAYER}' => '</b>' . $this->getUserMention('host') . '<b>']) . '</b>' . PHP_EOL . PHP_EOL;
            } elseif ($data['vote']['guest']['draw']) {
                $gameOutput .= '<b>' . __("{PLAYER} voted to draw!", ['{PLAYER}' => '</b>' . $this->getUserMention('guest') . '<b>']) . '</b>' . PHP_EOL . PHP_EOL;
            }

            $gameOutput .= Emoji::playButton() . ' ' . $this->getUserMention($data['settings'][$data['current_turn']]) . ' (' . $this->symbols[$data['current_turn']] . ')';

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

            Utilities::debugPrint('Game is still in progress');
        }

        if ($this->saveData($this->data)) {
            return $this->editMessage(
                $this->getUserMention('host') . ' (' . (($data['settings']['X'] == 'host') ? $this->symbols['X'] : $this->symbols['O']) . ')' . ' vs. ' . $this->getUserMention('guest') . ' (' . (($data['settings']['O'] == 'guest') ? $this->symbols['O'] : $this->symbols['X']) . ')' . PHP_EOL . PHP_EOL . $gameOutput,
                $this->gameKeyboard($data['board'], $isOver, $data['move_counter'])
            );
        }

        return parent::gameAction();
    }

    /**
     * Generate list of possible moves
     *
     * @param array  $board
     * @param string $selection
     * @param bool   $onlykill
     * @param string $char
     * @param string $backmultijumpblock
     *
     * @return array|bool
     */
    protected function possibleMoves(array $board, string $selection, bool $onlykill = false, string $char = null, string $backmultijumpblock = null)
    {
        $valid_moves = [];
        $kill = [];

        $sel_x = $selection[0];
        $sel_y = $selection[1];

        if ($char == '') {
            $char = $board[$sel_x][$sel_y];
        }

        if ($backmultijumpblock) {
            $board[$backmultijumpblock[0]][$backmultijumpblock[1]] = 'TMP';
        }

        if (strpos($char, 'X') !== false) {
            if ($board[$sel_x][$sel_y] == 'X') {
                if (isset($board[($sel_x - 1)][($sel_y + 1)]) && $board[($sel_x - 1)][($sel_y + 1)] == '') {
                    $valid_moves[] = ($sel_x - 1) . ($sel_y + 1); //right up
                }

                if (isset($board[($sel_x + 1)][($sel_y + 1)]) && $board[($sel_x + 1)][($sel_y + 1)] == '') {
                    $valid_moves[] = ($sel_x + 1) . ($sel_y + 1); //right down
                }

                if (isset($board[($sel_x - 1)][($sel_y + 1)]) && strpos($board[($sel_x - 1)][($sel_y + 1)], 'O') !== false) {
                    $valid_moves[] = ($sel_x - 2) . ($sel_y + 2); //right up
                    $kill[($sel_x - 2) . ($sel_y + 2)] = ($sel_x - 1) . ($sel_y + 1);
                }

                if (isset($board[($sel_x + 1)][($sel_y + 1)]) && strpos($board[($sel_x + 1)][($sel_y + 1)], 'O') !== false) {
                    $valid_moves[] = ($sel_x + 2) . ($sel_y + 2); //right down
                    $kill[($sel_x + 2) . ($sel_y + 2)] = ($sel_x + 1) . ($sel_y + 1);
                }

                if (isset($board[($sel_x + 1)][($sel_y - 1)]) && strpos($board[($sel_x + 1)][($sel_y - 1)], 'O') !== false) {
                    $valid_moves[] = ($sel_x + 2) . ($sel_y - 2); //left up
                    $kill[($sel_x + 2) . ($sel_y - 2)] = ($sel_x + 1) . ($sel_y - 1);
                }

                if (isset($board[($sel_x - 1)][($sel_y - 1)]) && strpos($board[($sel_x - 1)][($sel_y - 1)], 'O') !== false) {
                    $valid_moves[] = ($sel_x - 2) . ($sel_y - 2); //left down
                    $kill[($sel_x - 2) . ($sel_y - 2)] = ($sel_x - 1) . ($sel_y - 1);
                }
            } elseif ($board[$sel_x][$sel_y] == 'XK') {
                for ($move = 1; $move <= 8; $move++) {
                    if (isset($board[($sel_x - $move)][($sel_y + $move)]) && $board[($sel_x - $move)][($sel_y + $move)] == '') {
                        $valid_moves[] = ($sel_x - $move) . ($sel_y + $move); //right up
                    } else {
                        break;
                    }
                }

                for ($move = 1; $move <= 8; $move++) {
                    if (isset($board[($sel_x + $move)][($sel_y + $move)]) && $board[($sel_x + $move)][($sel_y + $move)] == '') {
                        $valid_moves[] = ($sel_x + $move) . ($sel_y + $move); //right down
                    } else {
                        break;
                    }
                }

                for ($move = 1; $move <= 8; $move++) {
                    if (isset($board[($sel_x + $move)][($sel_y - $move)]) && $board[($sel_x + $move)][($sel_y - $move)] == '') {
                        $valid_moves[] = ($sel_x + $move) . ($sel_y - $move); //left up
                    } else {
                        break;
                    }
                }

                for ($move = 1; $move <= 8; $move++) {
                    if (isset($board[($sel_x - $move)][($sel_y - $move)]) && $board[($sel_x - $move)][($sel_y - $move)] == '') {
                        $valid_moves[] = ($sel_x - $move) . ($sel_y - $move); //left down
                    } else {
                        break;
                    }
                }

                for ($move = 1; $move <= 8; $move++) {
                    if (isset($board[($sel_x - $move)][($sel_y + $move)]) && strpos($board[($sel_x - $move)][($sel_y + $move)], 'O') !== false) {
                        for ($move2 = 1; $move2 < $move; $move2++) {
                            if (isset($board[($sel_x - $move2)][($sel_y + $move2)]) && $board[($sel_x - $move2)][($sel_y + $move2)] != '') {
                                break 2;
                            }
                        }

                        $valid_moves[] = ($sel_x - ($move + 1)) . ($sel_y + ($move + 1)); //right up

                        $kill[($sel_x - ($move + 1)) . ($sel_y + ($move + 1))] = ($sel_x - $move) . ($sel_y + $move);
                        break;
                    }
                }

                for ($move = 1; $move <= 8; $move++) {
                    if (isset($board[($sel_x + $move)][($sel_y + $move)]) && strpos($board[($sel_x + $move)][($sel_y + $move)], 'O') !== false) {
                        for ($move2 = 1; $move2 < $move; $move2++) {
                            if (isset($board[($sel_x + $move2)][($sel_y + $move2)]) && $board[($sel_x + $move2)][($sel_y + $move2)] != '') {
                                break 2;
                            }
                        }

                        $valid_moves[] = ($sel_x + ($move + 1)) . ($sel_y + ($move + 1)); //right down
                        $kill[($sel_x + ($move + 1)) . ($sel_y + ($move + 1))] = ($sel_x + $move) . ($sel_y + $move);
                        break;
                    }
                }

                for ($move = 1; $move <= 8; $move++) {
                    if (isset($board[($sel_x + $move)][($sel_y - $move)]) && strpos($board[($sel_x + $move)][($sel_y - $move)], 'O') !== false) {
                        for ($move2 = 1; $move2 < $move; $move2++) {
                            if (isset($board[($sel_x + $move2)][($sel_y - $move2)]) && $board[($sel_x + $move2)][($sel_y - $move2)] != '') {
                                break 2;
                            }
                        }

                        $valid_moves[] = ($sel_x + ($move + 1)) . ($sel_y - ($move + 1)); //left up
                        $kill[($sel_x + ($move + 1)) . ($sel_y - ($move + 1))] = ($sel_x + $move) . ($sel_y - $move);
                        break;
                    }
                }

                for ($move = 1; $move <= 8; $move++) {
                    if (isset($board[($sel_x - $move)][($sel_y - $move)]) && strpos($board[($sel_x - $move)][($sel_y - $move)], 'O') !== false) {
                        for ($move2 = 1; $move2 < $move; $move2++) {
                            if (isset($board[($sel_x - $move2)][($sel_y - $move2)]) && $board[($sel_x - $move2)][($sel_y - $move2)] != '') {
                                break 2;
                            }
                        }

                        $valid_moves[] = ($sel_x - ($move + 1)) . ($sel_y - ($move + 1)); //left down
                        $kill[($sel_x - ($move + 1)) . ($sel_y - ($move + 1))] = ($sel_x - $move) . ($sel_y - $move);
                        break;
                    }
                }
            }
        } elseif (strpos($char, 'O') !== false) {
            if (isset($board[$sel_x][$sel_y]) && $board[$sel_x][$sel_y] == 'O') {
                if (isset($board[($sel_x + 1)][($sel_y - 1)]) && $board[($sel_x + 1)][($sel_y - 1)] == '') {
                    $valid_moves[] = ($sel_x + 1) . ($sel_y - 1); //left up
                }

                if (isset($board[($sel_x - 1)][($sel_y - 1)]) && $board[($sel_x - 1)][($sel_y - 1)] == '') {
                    $valid_moves[] = ($sel_x - 1) . ($sel_y - 1); //left down
                }

                if (isset($board[($sel_x - 1)][($sel_y + 1)]) && strpos($board[($sel_x - 1)][($sel_y + 1)], 'X') !== false) {
                    $valid_moves[] = ($sel_x - 2) . ($sel_y + 2); //right up
                    $kill[($sel_x - 2) . ($sel_y + 2)] = ($sel_x - 1) . ($sel_y + 1);
                }

                if (isset($board[($sel_x + 1)][($sel_y + 1)]) && strpos($board[($sel_x + 1)][($sel_y + 1)], 'X') !== false) {
                    $valid_moves[] = ($sel_x + 2) . ($sel_y + 2); //right down
                    $kill[($sel_x + 2) . ($sel_y + 2)] = ($sel_x + 1) . ($sel_y + 1);
                }

                if (isset($board[($sel_x + 1)][($sel_y - 1)]) && strpos($board[($sel_x + 1)][($sel_y - 1)], 'X') !== false) {
                    $valid_moves[] = ($sel_x + 2) . ($sel_y - 2); //left up
                    $kill[($sel_x + 2) . ($sel_y - 2)] = ($sel_x + 1) . ($sel_y - 1);
                }

                if (isset($board[($sel_x - 1)][($sel_y - 1)]) && strpos($board[($sel_x - 1)][($sel_y - 1)], 'X') !== false) {
                    $valid_moves[] = ($sel_x - 2) . ($sel_y - 2); //left down
                    $kill[($sel_x - 2) . ($sel_y - 2)] = ($sel_x - 1) . ($sel_y - 1);
                }
            } elseif (isset($board[$sel_x][$sel_y]) && $board[$sel_x][$sel_y] == 'OK') {
                for ($move = 1; $move <= 8; $move++) {
                    if (isset($board[($sel_x - $move)][($sel_y + $move)]) && $board[($sel_x - $move)][($sel_y + $move)] == '') {
                        $valid_moves[] = ($sel_x - $move) . ($sel_y + $move); //right up
                    } else {
                        break;
                    }
                }

                for ($move = 1; $move <= 8; $move++) {
                    if (isset($board[($sel_x + $move)][($sel_y + $move)]) && $board[($sel_x + $move)][($sel_y + $move)] == '') {
                        $valid_moves[] = ($sel_x + $move) . ($sel_y + $move); //right down
                    } else {
                        break;
                    }
                }

                for ($move = 1; $move <= 8; $move++) {
                    if (isset($board[($sel_x + $move)][($sel_y - $move)]) && $board[($sel_x + $move)][($sel_y - $move)] == '') {
                        $valid_moves[] = ($sel_x + $move) . ($sel_y - $move); //left up
                    } else {
                        break;
                    }
                }

                for ($move = 1; $move <= 8; $move++) {
                    if (isset($board[($sel_x - $move)][($sel_y - $move)]) && $board[($sel_x - $move)][($sel_y - $move)] == '') {
                        $valid_moves[] = ($sel_x - $move) . ($sel_y - $move); //left down
                    } else {
                        break;
                    }
                }

                for ($move = 1; $move <= 8; $move++) {
                    if (isset($board[($sel_x - $move)][($sel_y + $move)]) && strpos($board[($sel_x - $move)][($sel_y + $move)], 'X') !== false) {
                        for ($move2 = 1; $move2 < $move; $move2++) {
                            if (isset($board[($sel_x - $move2)][($sel_y + $move2)]) && $board[($sel_x - $move2)][($sel_y + $move2)] != '') {
                                break 2;
                            }
                        }

                        $valid_moves[] = ($sel_x - ($move + 1)) . ($sel_y + ($move + 1)); //right up

                        $kill[($sel_x - ($move + 1)) . ($sel_y + ($move + 1))] = ($sel_x - $move) . ($sel_y + $move);
                        break;
                    }
                }

                for ($move = 1; $move <= 8; $move++) {
                    if (isset($board[($sel_x + $move)][($sel_y + $move)]) && strpos($board[($sel_x + $move)][($sel_y + $move)], 'X') !== false) {
                        for ($move2 = 1; $move2 < $move; $move2++) {
                            if (isset($board[($sel_x + $move2)][($sel_y + $move2)]) && $board[($sel_x + $move2)][($sel_y + $move2)] != '') {
                                break 2;
                            }
                        }

                        $valid_moves[] = ($sel_x + ($move + 1)) . ($sel_y + ($move + 1)); //right down
                        $kill[($sel_x + ($move + 1)) . ($sel_y + ($move + 1))] = ($sel_x + $move) . ($sel_y + $move);
                        break;
                    }
                }

                for ($move = 1; $move <= 8; $move++) {
                    if (isset($board[($sel_x + $move)][($sel_y - $move)]) && strpos($board[($sel_x + $move)][($sel_y - $move)], 'X') !== false) {
                        for ($move2 = 1; $move2 < $move; $move2++) {
                            if (isset($board[($sel_x + $move2)][($sel_y - $move2)]) && $board[($sel_x + $move2)][($sel_y - $move2)] != '') {
                                break 2;
                            }
                        }

                        $valid_moves[] = ($sel_x + ($move + 1)) . ($sel_y - ($move + 1)); //left up
                        $kill[($sel_x + ($move + 1)) . ($sel_y - ($move + 1))] = ($sel_x + $move) . ($sel_y - $move);
                        break;
                    }
                }

                for ($move = 1; $move <= 8; $move++) {
                    if (isset($board[($sel_x - $move)][($sel_y - $move)]) && strpos($board[($sel_x - $move)][($sel_y - $move)], 'X') !== false) {
                        for ($move2 = 1; $move2 < $move; $move2++) {
                            if (isset($board[($sel_x - $move2)][($sel_y - $move2)]) && $board[($sel_x - $move2)][($sel_y - $move2)] != '') {
                                break 2;
                            }
                        }

                        $valid_moves[] = ($sel_x - ($move + 1)) . ($sel_y - ($move + 1)); //left down
                        $kill[($sel_x - ($move + 1)) . ($sel_y - ($move + 1))] = ($sel_x - $move) . ($sel_y - $move);
                        break;
                    }
                }
            }
        }

        if ($backmultijumpblock) {
            $board[$backmultijumpblock[0]][$backmultijumpblock[1]] = '';
        }

        if ($onlykill) {
            foreach ($kill as $thismove => $thiskill) {
                $thismove = (string)$thismove;

                if (false !== strpos($thismove, "-") || $thismove[0] >= $this->max_x || $thismove[1] >= $this->max_y || $thismove[0] < 0 || $thismove[1] < 0) {
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
            } elseif ($x < 0 || $y < 0 || $x >= $this->max_x || $y >= $this->max_y || false !== strpos($value, "-")) {
                unset($valid_moves[$key]);
                unset($kill[$value]);
            } elseif ($board[$x][$y] != '') {
                unset($valid_moves[$key]);
                unset($kill[$x . $y]);
            }
        }

        $c = static function ($v) {
            if (is_array($v)) {
                return array_filter($v) != [];
            }

            return false;
        };

        array_filter($valid_moves, $c);
        array_filter($kill, $c);

        return [
            'valid_moves' => $valid_moves,
            'kills'       => $kill,
        ];
    }

    /**
     * Check whenever game is over
     *
     * @param array $board
     *
     * @return string
     */
    protected function isGameOver(array $board): ?string
    {
        $array = $this->piecesLeft($board);

        if ($array['O'] == 0) {
            return 'X';
        } elseif ($array['X'] == 0) {
            return 'O';
        } elseif ($array['X'] == 1 && $array['O'] == 1 && $array['XK'] == 1 && $array['OK'] == 1) {
            return 'T';
        }

        $availableMoves_X = [];
        $availableMoves_O = [];

        for ($x = 0; $x < $this->max_x; $x++) {
            for ($y = 0; $y < $this->max_y; $y++) {
                if (strpos($board[$x][$y], 'X') !== false) {
                    $availableMoves_X[] = $this->possibleMoves($board, $x . $y, false, 'X"');
                } elseif (strpos($board[$x][$y], 'O') !== false) {
                    $availableMoves_O[] = $this->possibleMoves($board, $x . $y, false, 'O');
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

        if ($noMovesO || $noMovesX) {
            if ($array['X'] > $array['O']) {
                return 'X';
            } elseif ($array['O'] > $array['X']) {
                return 'O';
            }

            if ($array['XK'] > $array['OK']) {
                return 'X';
            } elseif ($array['OK'] > $array['XK']) {
                return 'O';
            }
        }

        return null;
    }

    /**
     * Keyboard for game in progress
     *
     * @param array  $board
     * @param string $winner
     * @param int    $moveCounter
     *
     * @return InlineKeyboard
     * @throws BotException
     */
    protected function gameKeyboard(array $board, string $winner = null, int $moveCounter = 0): InlineKeyboard
    {
        $inline_keyboard = [];

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

                    if (isset($this->selection) && $this->selection == $x . $y) {
                        $field = '[' . $field . ']';
                    }

                    $tmp_array[] = new InlineKeyboardButton(
                        [
                            'text'          => $field,
                            'callback_data' => self::getCode() . ';game;' . $x . '-' . $y,
                        ]
                    );
                }
            }

            if (!empty($tmp_array)) {
                $inline_keyboard[] = $tmp_array;
            }
        }

        $inline_keyboard = $this->invertBoard($inline_keyboard);

        $piecesLeft = $this->piecesLeft($board);

        if (!empty($winner)) {
            $inline_keyboard[] = [
                new InlineKeyboardButton(
                    [
                        'text'          => __('Play again!'),
                        'callback_data' => self::getCode() . ';start',
                    ]
                ),
            ];
        } else {
            if ($moveCounter == 0 || ($moveCounter > 0 && $piecesLeft['X'] != $piecesLeft['O'])) {
                $inline_keyboard[] = [
                    new InlineKeyboardButton(
                        [
                            'text'          => __('Surrender'),
                            'callback_data' => self::getCode() . ';forfeit',
                        ]
                    ),
                ];
            } else {
                $inline_keyboard[] = [
                    new InlineKeyboardButton(
                        [
                            'text'          => __('Vote to draw'),
                            'callback_data' => self::getCode() . ';draw',
                        ]
                    ),
                ];
            }
        }

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

        if (getenv('DEBUG') && $this->getCurrentUserId() == getenv('BOT_ADMIN')) {
            $this->boardPrint($board);

            $inline_keyboard[] = [
                new InlineKeyboardButton(
                    [
                        'text'          => 'DEBUG: ' . 'Restart',
                        'callback_data' => self::getCode() . ';start',
                    ]
                ),
                new InlineKeyboardButton(
                    [
                        'text'          => 'DEBUG: ' . 'Surrender',
                        'callback_data' => self::getCode() . ';forfeit',
                    ]
                ),
                new InlineKeyboardButton(
                    [
                        'text'          => 'DEBUG: ' . 'Draw',
                        'callback_data' => self::getCode() . ';draw',
                    ]
                ),
            ];
        }

        return new InlineKeyboard(...$inline_keyboard);
    }

    /**
     * Check how many pieces is left
     *
     * @param array $board
     *
     * @return array
     */
    protected function piecesLeft(array $board): array
    {
        $xs = 0;
        $ys = 0;
        $xks = 0;
        $yks = 0;

        for ($x = 0; $x < $this->max_x; $x++) {
            for ($y = 0; $y < $this->max_y; $y++) {
                if (strpos($board[$x][$y], 'X') !== false) {
                    $xs++;
                } elseif (strpos($board[$x][$y], 'O') !== false) {
                    $ys++;
                }

                if (strpos($board[$x][$y], 'XK') !== false) {
                    $xks++;
                } elseif (strpos($board[$x][$y], 'OK') !== false) {
                    $yks++;
                }
            }
        }

        return [
            'X'  => $xs,
            'O'  => $ys,
            'XK' => $xks,
            'OK' => $yks,
        ];
    }
}
