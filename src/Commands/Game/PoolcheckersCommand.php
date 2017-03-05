<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * Copyright (c) 2016 Jack'lul <https://jacklul.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Longman\TelegramBot\Commands\UserCommands;

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Game;
use Longman\TelegramBot\Strings;
use Longman\TelegramBot\Entities\InlineKeyboardMarkup;
use Longman\TelegramBot\Entities\InlineKeyboardButton;

class PoolcheckersCommand extends UserCommand
{
    protected $name = 'poolcheckers';

    private $query_prefix = 'g5';
    private $strings = [
        'game_empty_field' => ' ',
    ];

    private $rules_url = 'https://en.wikipedia.org/wiki/Pool_checkers';

    private $starting_board = [
        ['', 'X', '', '', '', 'O', '', 'O'],
        ['X', '', 'X', '', '', '', 'O', ''],
        ['', 'X', '', '', '', 'O', '', 'O'],
        ['X', '', 'X', '', '', '', 'O', ''],
        ['', 'X', '', '', '', 'O', '', 'O'],
        ['X', '', 'X', '', '', '', 'O', ''],
        ['', 'X', '', '', '', 'O', '', 'O'],
        ['X', '', 'X', '', '', '', 'O', ''],
    ];

    private $max_x = 8;
    private $max_y = 8;

    private $selection = null;

    private $query_id;
    private $message_id;

    public function execute()
    {
        $update = $this->getUpdate();
        $callback_query = $update->getCallbackQuery();
        $chosen_inline_result = $update->getChosenInlineResult();

        $this->strings = Strings::load($this->name);

        if ($callback_query) {
            $user_id = $callback_query->getFrom()->getId();
            $this->query_id = $callback_query->getId();
            $this->message_id = $callback_query->getInlineMessageId();

            $text = $callback_query->getData();
            $text = explode('_', $text);

            if ($text[0] != $this->query_prefix) {
                return $this->answerCallback();
            }

            $command = $text[1];
            $gameId = $text[2];

            $this_user_fullname = $callback_query->getFrom()->getFirstName();
            if (!empty($callback_query->getFrom()->getLastName())) {
                $this_user_fullname .= ' ' . $callback_query->getFrom()->getLastName();
            }

            if (is_numeric($gameId)) {
                $file = $this->getTelegram()->getDownloadPath() . '/' . $gameId . '.lock_game';
                $fh = fopen($file, 'w');
                if (!flock($fh, LOCK_EX | LOCK_NB) && time() <= filemtime($file) + 60) {
                    return $this->answerCallback($this->strings['notify_process_is_busy'], true);
                }

                $query_raw = Game::getGame($gameId);
            }

            if (!empty($gameId) && count($query_raw) == 1) {
                $query = $query_raw[0];
                $data = json_decode($query['data'], true);

                $host_id = $query['host_id'];
                $guest_id = $query['guest_id'];

                $host_fullname = '';
                $guest_fullname = '';

                if ($host_id) {
                    $host_user = Game::getUser($host_id);

                    $host_fullname = $host_user['fullname'];
                    $host_mention = $host_user['mention'];
                }

                if ($guest_id) {
                    $guest_user = Game::getUser($guest_id);

                    $guest_fullname = $guest_user['fullname'];
                    $guest_mention = $guest_user['mention'];
                }
				
                $this_user_fullname = htmlentities($this_user_fullname);
                $host_fullname = htmlentities($host_fullname);
                $guest_fullname = htmlentities($guest_fullname);

                if (isset($data['language'])) {
                    $this->strings = Strings::load($this->name, $data['language']);
                } else {
                    $this->strings = Strings::load($this->name);
                }

                if ($command == 'join') {
                    if ($user_id == $host_id && !$this->getTelegram()->isAdmin()) {
                        return $this->answerCallback($this->strings['notify_cant_play_with_yourself'], true);
                    }

                    if (empty($host_id)) {
                        $result = Game::setHost($gameId, $user_id);

                        if ($result) {
                            $this->editMessage(
                                str_replace('{STR}', $this_user_fullname, $this->strings['state_waiting_for_opponent']),
                                $this->createInlineKeyboard('lobby', $gameId)
                            );
                        }
                    } elseif (empty($guest_id)) {
                        $result = Game::setGuest($gameId, $user_id);

                        if ($result) {
                            $this->editMessage(
                                str_replace('{STR}', $host_fullname, $this->strings['state_waiting_for_host']),
                                $this->createInlineKeyboard('pregame', $gameId)
                            );
                        }
                    } else {
                        return $this->answerCallback($this->strings['notify_game_is_full'], true);
                    }

                    return $this->answerCallback();
                } elseif ($command == 'quit') {
                    if ($user_id == $guest_id) {
                        $result = Game::setGuest($gameId, null);

                        if ($result) {
                            $this->editMessage(
                                str_replace('{STR}', $host_fullname, $this->strings['state_waiting_for_opponent']),
                                $this->createInlineKeyboard('lobby', $gameId)
                            );

                            return $this->answerCallback();
                        }
                    } elseif ($user_id == $host_id) {
                        if (empty($guest_id)) {
                            $result = Game::setHost($gameId, null);

                            if ($result) {
                                $this->editMessage(
                                    '<i>' . $this->strings['state_session_is_empty'] . '</i>',
                                    $this->createInlineKeyboard('empty', $gameId)
                                );
                            }
                        } else {
                            $result = Game::migrateHost($gameId, $guest_id);

                            if ($result) {
                                $this->editMessage(
                                    str_replace('{STR}', $guest_fullname, $this->strings['state_waiting_for_opponent']),
                                    $this->createInlineKeyboard('lobby', $gameId)
                                );
                            }
                        }
                    } else {
                        return $this->answerCallback($this->strings['notify_not_in_this_game'], true);
                    }

                    return $this->answerCallback();
                } elseif ($command == 'kick') {
                    if ($user_id == $host_id) {
                        $result = Game::setGuest($gameId, null);

                        if ($result) {
                            $this->editMessage(
                                str_replace('{STR}', $host_fullname, $this->strings['state_waiting_for_opponent']),
                                $this->createInlineKeyboard('lobby', $gameId)
                            );

                            return $this->answerCallback();
                        }
                    } elseif ($user_id == $guest_id) {
                        return $this->answerCallback($this->strings['notify_not_a_host'], true);
                    } else {
                        return $this->answerCallback($this->strings['notify_not_in_this_game'], true);
                    }

                    return $this->answerCallback();
                } elseif ($command == 'start') {
                    if ($user_id == $host_id) {
                        $move = $this->initalizeGame($gameId, $data);

                        if ($move) {
                            $move = str_replace('X', $this->strings['X'], $move);
                            $move = str_replace('O', $this->strings['O'], $move);

                            $this->editMessage(
                                $host_fullname . ' (' . $this->strings['X'] . ')' . ' ' . $this->strings['game_versus_abbreviation'] . ' ' . $guest_fullname . ' (' . $this->strings['O'] . ')' . "\n\n" . $this->strings['game_current_turn'] . ' ' . $move . "\n" . $this->strings['game_select_pawn'],
                                $this->createInlineKeyboard('game', $gameId, $this->starting_board)
                            );
                        }
                    } elseif ($user_id == $guest_id) {
                        return $this->answerCallback($this->strings['notify_not_a_host'], true);
                    } else {
                        return $this->answerCallback($this->strings['notify_not_in_this_game'], true);
                    }

                    return $this->answerCallback();
                } elseif ($command == 'lobby') {
                    if ($user_id == $host_id) {
                        $gobacktolobby = Game::getCallbackQueryCount($user_id, $this->query_prefix.'_lobby_'.$gameId, date('Y-m-d H:i:s', strtotime("-5 second")));

                        if (!isset($gobacktolobby) || (isset($gobacktolobby[0]['i']) && $gobacktolobby[0]['i'] > 1)) {
                            $this->editMessage(
                                str_replace('{STR}', $host_fullname, $this->strings['state_waiting_for_host']),
                                $this->createInlineKeyboard('pregame', $gameId)
                            );
                        } else {
                            return $this->answerCallback($this->strings['notify_go_to_lobby'], true);
                        }
                    } elseif ($user_id == $guest_id) {
                        return $this->answerCallback($this->strings['notify_not_a_host'], true);
                    } else {
                        return $this->answerCallback($this->strings['notify_not_in_this_game'], true);
                    }

                    return $this->answerCallback();
                } elseif ($command == 'language') {
                    if ($user_id == $host_id) {
                        $language = Strings::rotate($data['language'], $this->getTelegram()->isAdmin());

                        if (!empty($language)) {
                            $this->strings = Strings::load($this->name, $language);
                            $data['language'] = $language;
                            $result = Game::updateGame($gameId, json_encode($data));

                            if ($result) {
                                if ($guest_id) {
                                    $this->editMessage(
                                        str_replace('{STR}', $host_fullname, $this->strings['state_waiting_for_host']),
                                        $this->createInlineKeyboard('pregame', $gameId)
                                    );
                                } else {
                                    $this->editMessage(
                                        str_replace('{STR}', $host_fullname, $this->strings['state_waiting_for_opponent']),
                                        $this->createInlineKeyboard('lobby', $gameId)
                                    );
                                }
                            }
                        }
                    } elseif ($user_id == $guest_id) {
                        return $this->answerCallback($this->strings['notify_not_a_host'], true);
                    } else {
                        return $this->answerCallback($this->strings['notify_not_in_this_game'], true);
                    }

                    return $this->answerCallback();
                } elseif ($command == 'forfeit') {
                    if ($data['game_current_turn'] != 'E') {
                        if ($user_id == $guest_id) {
                            $isOver = 'X';
                        } elseif ($user_id == $host_id) {
                            $isOver = 'O';
                        } else {
                            return $this->answerCallback($this->strings['notify_not_in_this_game'], true);
                        }

                        if ($isOver == 'X') {
                            $gameResult = "\n\n" . str_replace('{STR}', $host_fullname . '<b>', $this->strings['result_won']) . '</b>'.' '.str_replace('{STR}', $guest_fullname, $this->strings['result_surrender']);
                        } elseif ($isOver == 'O') {
                            $gameResult = "\n\n" . str_replace('{STR}', $guest_fullname . '<b>', $this->strings['result_won']) . '</b>'.' '.str_replace('{STR}', $host_fullname, $this->strings['result_surrender']);
                        }

                        $data['game_current_turn'] = 'E';
                        $data['current_selection'] = '';

                        $result = Game::updateGame($gameId, json_encode($data));

                        if ($result) {
                            $this->editMessage(
                                $host_fullname . ' (' . $this->strings['X'] . ')' . ' ' . $this->strings['game_versus_abbreviation'] . ' ' . $guest_fullname . ' (' . $this->strings['O'] . ')' . $gameResult,
                                $this->createInlineKeyboard('game', $gameId, $data['board'], true)
                            );
                        }
                    }

                    return $this->answerCallback();
                } elseif ($command == 'draw') {
                    if ($data['game_current_turn'] != 'E') {
                        if ($user_id == $guest_id && $data['guest_notify_voted_to_draw'] !== true) {
                            $data['guest_notify_voted_to_draw'] = true;
                        } elseif ($user_id == $host_id && $data['host_notify_voted_to_draw'] !== true) {
                            $data['host_notify_voted_to_draw'] = true;
                        } elseif ($user_id == $guest_id || $user_id == $host_id) {
                            return $this->answerCallback($this->strings['notify_already_voted_to_draw']);
                        } else {
                            return $this->answerCallback($this->strings['notify_not_in_this_game'], true);
                        }

                        $move = $data['game_current_turn'];
                        $move = str_replace('X', $this->strings['X'], $move);
                        $move = str_replace('O', $this->strings['O'], $move);

                        $result = Game::updateGame($gameId, json_encode($data));

                        if ($result) {
                            if ($data['host_notify_voted_to_draw'] == true && $data['guest_notify_voted_to_draw'] == true) {
                                $data['game_current_turn'] = 'E';
                                $data['current_selection'] = '';

                                $this->editMessage(
                                    $host_fullname . ' (' . $this->strings['X'] . ')' . ' ' . $this->strings['game_versus_abbreviation'] . ' ' . $guest_fullname . ' (' . $this->strings['O'] . ')' . "\n\n" . '<b>' . $this->strings['result_draw'] . '</b>',
                                    $this->createInlineKeyboard('game', $gameId, $data['board'], true, $data['move_counter'])
                                );
                            } else {
                                if ($data['host_notify_voted_to_draw']) {
                                    $whovoted = "\n" . str_replace('{STR}', $host_fullname, $this->strings['game_voted_to_draw']);
                                } elseif ($data['guest_notify_voted_to_draw']) {
                                    $whovoted = "\n" . str_replace('{STR}', $guest_fullname, $this->strings['game_voted_to_draw']);
                                }

                                $this->editMessage(
                                    $host_fullname . ' (' . $this->strings['X'] . ')' . ' ' . $this->strings['game_versus_abbreviation'] . ' ' . $guest_fullname . ' (' . $this->strings['O'] . ')' . "\n\n" . $this->strings['game_current_turn'] . ' ' . $move . "\n" . $this->strings['game_select_pawn'] . $whovoted,
                                    $this->createInlineKeyboard('game', $gameId, $data['board'], false, $data['move_counter'])
                                );

                                return $this->answerCallback($this->strings['notify_voted_to_draw']);
                            }
                        }
                    }

                    return $this->answerCallback();
                }

                // Handle the game
                if ($user_id == $host_id || $user_id == $guest_id) {
                    if ($data['game_current_turn'] == 'E') {
                        return $this->answerCallback();
                    }

                    $notYourTurn = false;
                    $gameEnded = false;
                    $gameResult = '';
                    $currentMove = '';
                    $forcedJump = false;
                    $killed = false;
                    $moveLimit = 25;
                    $moveLimitReached = false;

                    if (($user_id == $host_id && $data['game_current_turn'] == 'X') || ($user_id == $guest_id && $data['game_current_turn'] == 'O')) {
                        if ($data['current_selection'] != '') {
                            if ($data['current_selection'] == $command[0].$command[1]) {
                                if ($data['current_selection_lock'] == false) {
                                    return $this->answerCallback();
                                } else {
                                    return $this->answerCallback($this->strings['notify_mandatory_jump'], true);
                                }
                            } else {
                                if ($user_id == $host_id && $data['game_current_turn'] == 'X') {
                                    $possibleMoves = $this->possibleMoves($data['board'], $data['current_selection']);
                                } elseif ($user_id == $guest_id && $data['game_current_turn'] == 'O') {
                                    $possibleMoves = $this->possibleMoves($data['board'], $data['current_selection']);
                                }

                                for ($x = 0; $x < $this->max_x; $x++) {
                                    for ($y = 0; $y < $this->max_y; $y++) {
                                        if (strpos($data['board'][$x][$y], $data['game_current_turn']) !== false && $this->possibleMoves($data['board'], $x.$y, true)) {
                                            $forcedJump = true;
                                            $availableMoves = $this->possibleMoves($data['board'], $x.$y);

                                            if (isset($availableMoves['kills'][$command[0].$command[1]]) && $data['current_selection'] == $x.$y) {
                                                $forcedJump = false;
                                                break 2;
                                            }
                                        }
                                    }
                                }

                                if (in_array($command[0].$command[1], $possibleMoves['valid_moves']) && $data['board'][$command[0]][$command[1]] == '') {
                                    if ($forcedJump) {
                                        return $this->answerCallback($this->strings['notify_mandatory_jump'], true);
                                    }

                                    $data['board'][$command[0]][$command[1]] = $data['board'][$data['current_selection'][0]][$data['current_selection'][1]];
                                    $data['board'][$data['current_selection'][0]][$data['current_selection'][1]] = '';

                                    if ($possibleMoves['kills'][$command[0].$command[1]] != '') {
                                        $data['board'][$possibleMoves['kills'][$command[0].$command[1]][0]][$possibleMoves['kills'][$command[0].$command[1]][1]] = '';
                                        $killed = true;
                                    }

                                    if ($killed == true && $this->possibleMoves($data['board'], $command[0].$command[1], true, null, $data['current_selection'][0].$data['current_selection'][1])) {
                                        $data['current_selection_lock'] = true;
                                        $data['current_selection'] = $command[0].$command[1];
                                    } else {
                                        if (strpos($data['board'][$command[0]][$command[1]], 'K') === false && (($data['game_current_turn'] == 'X' && $command[1] == 7) || ($data['game_current_turn'] == 'O' && $command[1] == 0))) {
                                            $data['board'][$command[0]][$command[1]] .= 'K';
                                        }

                                        if ($data['game_current_turn'] == 'X') {
                                            $data['game_current_turn'] = 'O';
                                        } else {
                                            $data['game_current_turn'] = 'X';
                                        }

                                        $data['current_selection'] = '';
                                        $data['current_selection_lock'] = false;
                                    }

                                    $piecesLeft = $this->piecesLeft($data['board']);

                                    if (($piecesLeft['X'] == 1 || $piecesLeft['O'] == 1 ) && $data['current_selection_lock'] == false) {
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
                                        return $this->answerCallback($this->strings['notify_mandatory_jump'], true);
                                    } elseif ($user_id == $host_id && $data['game_current_turn'] == 'X' && strpos($data['board'][$command[0]][$command[1]], 'X') !== false) {
                                        $data['current_selection'] = $command[0].$command[1];
                                    } elseif ($user_id == $guest_id && $data['game_current_turn'] == 'O' && strpos($data['board'][$command[0]][$command[1]], 'O') !== false) {
                                        $data['current_selection'] = $command[0].$command[1];
                                    } else {
                                        return $this->answerCallback($this->strings['notify_invalid_move'], true);
                                    }
                                }
                            }
                        } else {
                            if ($user_id == $host_id && $data['game_current_turn'] == 'X' && strpos($data['board'][$command[0]][$command[1]], 'X') !== false) {
                                $data['current_selection'] = $command[0].$command[1];
                            } elseif ($user_id == $guest_id && $data['game_current_turn'] == 'O' && strpos($data['board'][$command[0]][$command[1]], 'O') !== false) {
                                $data['current_selection'] = $command[0].$command[1];
                            } else {
                                return $this->answerCallback($this->strings['notify_wrong_selection'], true);
                            }
                            $this->answerCallback();
                        }
                    } else {
                        $notYourTurn = true;
                    }

                    $isOver = $this->isGameOver($data['board']);

                    if (in_array($isOver, ['X', 'O', 'T']) || $moveLimitReached) {
                        if ($isOver == 'X' || $piecesLeft['X'] > $piecesLeft['O']) {
                            $gameResult = "\n\n" . str_replace('{STR}', $host_fullname . '<b>', $this->strings['result_won']) . '</b>';
                        } elseif ($isOver == 'O' || $piecesLeft['O'] > $piecesLeft['X']) {
                            $gameResult = "\n\n" . str_replace('{STR}', $guest_fullname . '<b>', $this->strings['result_won']) . '</b>';
                        } else {
                            $gameResult = "\n\n" . '<b>' . $this->strings['result_draw'] . '</b>';
                        }

                        $data['game_current_turn'] = 'E';
                        $data['current_selection'] = '';
                        $gameEnded = true;
                    }

                    if (!$gameEnded) {
                        $this->selection = $data['current_selection'];

                        $move = $data['game_current_turn'];
                        $move = str_replace('X', $this->strings['X'], $move);
                        $move = str_replace('O', $this->strings['O'], $move);

                        $currentMove = "\n\n" . $this->strings['game_current_turn'] . ' ' . $move;
                        if ($data['current_selection'] == '') {
                            $currentMove .= "\n" . $this->strings['game_select_pawn'];
                        } else {
                            $currentMove .= ' ' . str_replace('{STR}', ($data['current_selection'][0] + 1) . '-' . ($data['current_selection'][1] + 1), $this->strings['game_selected_pawn']);

                            if ($data['current_selection_lock'] == false) {
                                $currentMove .= "\n" . $this->strings['game_select_info'];
                            } else {
                                $currentMove .= "\n" . $this->strings['game_chain_move'];
                            }
                        }

                        if ($data['host_notify_voted_to_draw']) {
                            $currentMove .= "\n" . str_replace('{STR}', $host_fullname, $this->strings['game_voted_to_draw']);
                        } elseif ($data['guest_notify_voted_to_draw']) {
                            $currentMove .= "\n" . str_replace('{STR}', $guest_fullname, $this->strings['game_voted_to_draw']);
                        }
                    }

                    $result = Game::updateGame($gameId, json_encode($data));

                    if ($result) {
                        $this->editMessage(
                            $host_fullname . ' (' . $this->strings['X'] . ')' . ' ' . $this->strings['game_versus_abbreviation'] . ' ' . $guest_fullname . ' (' . $this->strings['O'] . ')' . $gameResult . $currentMove,
                            $this->createInlineKeyboard('game', $gameId, $data['board'], $gameEnded, $data['move_counter'])
                        );
                    }

                    if ($notYourTurn) {
                        return $this->answerCallback($this->strings['notify_not_your_turn'], true);
                    }
                } else {
                    return $this->answerCallback($this->strings['notify_not_in_this_game'], true);
                }

                return $this->answerCallback();
            } else {
                if ($command == 'new') {
                    $gameId = Game::newGame($user_id, $this->name, $this->message_id);

                    if ($gameId) {
                        $this->editMessage(
                            str_replace('{STR}', $this_user_fullname, $this->strings['state_waiting_for_opponent']),
                            $this->createInlineKeyboard('lobby', $gameId)
                        );
                    }
                } else {
                    $this->editMessage(
                        '<i>' . $this->strings['state_session_not_available'] . '</i>',
                        $this->createInlineKeyboard('invalid', $gameId)
                    );
                }

                return $this->answerCallback();
            }
        } elseif ($chosen_inline_result) {
            $resultId = $chosen_inline_result->getResultId();
            $user_id = $chosen_inline_result->getFrom()->getId();
            $this->message_id = $chosen_inline_result->getInlineMessageId();

            if ($resultId == $this->query_prefix) {
                $this_user_fullname = $chosen_inline_result->getFrom()->getFirstName();
                if (!empty($chosen_inline_result->getFrom()->getLastName())) {
                    $this_user_fullname .= ' ' . $chosen_inline_result->getFrom()->getLastName();
                }

                $gameId = Game::newGame($user_id, $this->name, $this->message_id);

                if ($gameId) {
                    $this->editMessage(
                        str_replace('{STR}', $this_user_fullname, $this->strings['state_waiting_for_opponent']),
                        $this->createInlineKeyboard('lobby', $gameId)
                    );
                }
            }
        }

        return Request::emptyResponse();
    }

    private function editMessage($text, $reply_markup)
    {
        $data = [];
        $data['inline_message_id'] = $this->message_id;
        $data['text'] = '<b>' . $this->strings['message_prefix'] . '</b>' . "\n\n" . $text;
        $data['parse_mode'] = 'HTML';
        $data['disable_web_page_preview'] = true;
        $data['reply_markup'] = $reply_markup;

        return Request::editMessageText($data);
    }

    private function answerCallback($text = '', $notify = false)
    {
        $data = [];
        $data['callback_query_id'] = $this->query_id;

        if (!empty($text)) {
            $data['text'] = $text;
        }

        if ($notify) {
            $data['show_alert'] = true;
        }

        return Request::answerCallbackQuery($data);
    }

    private function initalizeGame($id, $data)
    {
        if (!empty($data['start_turn']) && $data['start_turn'] == 'X') {
            $move = 'O';
        } else {
            $move = 'X';
        }

        $data['board'] = $this->starting_board;
        $data['start_turn'] = $move;
        $data['game_current_turn'] = $move;
        $data['current_selection'] = '';
        $data['move_counter'] = 0;
        $data['host_notify_voted_to_draw'] = false;
        $data['guest_notify_voted_to_draw'] = false;

        $result = Game::updateGame($id, json_encode($data));

        if ($result) {
            return $move;
        }

        return false;
    }

    private function createInlineKeyboard($menu = '', $id = null, $board = null, $gameIsOver = false, $moveCounter = 0)
    {
        $inline_keyboard = [];

        if ($menu == 'game' && is_array($board)) {
            for ($x = 0; $x < $this->max_x; $x++) {
                $tmp_array = [];

                for ($y = 0; $y < $this->max_y; $y++) {
                    if ($board[$x][$y] == 'X' || $board[$x][$y] == 'O' || $board[$x][$y] == 'XK' || $board[$x][$y] == 'OK') {
                        $field = $this->strings[$board[$x][$y]];
                    } else {
                        $field = $this->strings['game_empty_field'];
                    }

                    //if ($this->selection == $x.$y) {
                    //    $field = '[' . $field . ']';
                    //}

                    array_push(
                        $tmp_array,
                        new InlineKeyboardButton(
                            [
                            'text' => ' '.$field,
                            'callback_data' => $this->query_prefix . '_'.$x.$y.'_' . $id
                            ]
                        )
                    );
                }

                $inline_keyboard[] = $tmp_array;
            }

            $inline_keyboard = $this->transpose($inline_keyboard);

            $piecesLeft = $this->piecesLeft($board);

            if ($gameIsOver) {
                $inline_keyboard[] = [
                    new InlineKeyboardButton(
                        [
                            'text' => $this->strings['button_play_again'],
                            'callback_data' => $this->query_prefix . "_start_" . $id,
                        ]
                    )
                ];
            } else {
                if ($moveCounter == 0 || ($moveCounter > 0 && $piecesLeft['X'] != $piecesLeft['O'])) {
                    $inline_keyboard[] = [
                        new InlineKeyboardButton(
                            [
                                'text' => $this->strings['button_forfeit'],
                                'callback_data' => $this->query_prefix . "_forfeit_" . $id
                            ]
                        )
                    ];
                } else {
                    $inline_keyboard[] = [
                        new InlineKeyboardButton(
                            [
                                'text' => $this->strings['button_draw'],
                                'callback_data' => $this->query_prefix . "_draw_" . $id
                            ]
                        )
                    ];
                }
            }
            $inline_keyboard[] = [
                new InlineKeyboardButton(
                    [
                        'text' => $this->strings['button_quit'],
                        'callback_data' => $this->query_prefix . "_quit_" . $id
                    ]
                ),
                new InlineKeyboardButton(
                    [
                        'text' => $this->strings['button_lobby'],
                        'callback_data' => $this->query_prefix . "_lobby_" . $id,
                    ]
                )
            ];
        } elseif ($menu == 'lobby') {
            if (Strings::getLanguagesCount($this->getTelegram()->isAdmin()) > 1) {
                $inline_keyboard[] = [
                    new InlineKeyboardButton(
                        [
                            'text' => $this->strings['button_language'] . ' - ' . $this->strings['language'],
                            'callback_data' => $this->query_prefix . "_language_" . $id
                        ]
                    )
                ];
            }
            $inline_keyboard[] = [
                new InlineKeyboardButton(
                    [
                        'text' => $this->strings['button_rules'],
                        'url' => $this->rules_url
                    ]
                )
            ];
            $inline_keyboard[] = [
                new InlineKeyboardButton(
                    [
                        'text' => $this->strings['button_quit'],
                        'callback_data' => $this->query_prefix . "_quit_" . $id
                    ]
                ),
                new InlineKeyboardButton(
                    [
                        'text' => $this->strings['button_join'],
                        'callback_data' => $this->query_prefix . "_join_" . $id
                    ]
                )
            ];
        } elseif ($menu == 'pregame') {
            $inline_keyboard[] = [
                new InlineKeyboardButton(
                    [
                        'text' => $this->strings['button_play'],
                        'callback_data' => $this->query_prefix . "_start_" . $id
                    ]
                )
            ];
            if (Strings::getLanguagesCount($this->getTelegram()->isAdmin()) > 1) {
                $inline_keyboard[] = [
                    new InlineKeyboardButton(
                        [
                            'text' => $this->strings['button_language'] . ' - ' . $this->strings['language'],
                            'callback_data' => $this->query_prefix . "_language_" . $id
                        ]
                    )
                ];
            }
            $inline_keyboard[] = [
                new InlineKeyboardButton(
                    [
                        'text' => $this->strings['button_rules'],
                        'url' => $this->rules_url
                    ]
                )
            ];
            $inline_keyboard[] = [
                new InlineKeyboardButton(
                    [
                        'text' => $this->strings['button_quit'],
                        'callback_data' => $this->query_prefix . "_quit_" . $id
                    ]
                ),
                new InlineKeyboardButton(
                    [
                        'text' => $this->strings['button_kick'],
                        'callback_data' => $this->query_prefix . "_kick_" . $id
                    ]
                )
            ];
        } elseif ($menu == 'empty') {
            $inline_keyboard[] = [
                new InlineKeyboardButton(
                    [
                        'text' => $this->strings['button_join'],
                        'callback_data' => $this->query_prefix . "_join_" . $id
                    ]
                )
            ];
        } elseif ($menu == 'invalid') {
            $inline_keyboard[] = [
                new InlineKeyboardButton(
                    [
                        'text' => $this->strings['button_create'],
                        'callback_data' => $this->query_prefix . "_new"
                    ]
                )
            ];
        }

        $inline_keyboard_markup = new InlineKeyboardMarkup(['inline_keyboard' => $inline_keyboard]);

        return $inline_keyboard_markup;
    }

    private function possibleMoves($board, $selection, $onlykill = false, $char = '', $backmultijumpblock = null)
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
                if ($board[($sel_x - 1)][($sel_y + 1)] == '') {
                    $valid_moves[] = ($sel_x - 1) . ($sel_y + 1); //right up
                }

                if ($board[($sel_x + 1)][($sel_y + 1)] == '') {
                    $valid_moves[] = ($sel_x + 1).($sel_y + 1); //right down
                }

                if (strpos($board[($sel_x - 1)][($sel_y + 1)], 'O') !== false) {
                    $valid_moves[] = ($sel_x - 2).($sel_y + 2); //right up
                    $kill[($sel_x - 2).($sel_y + 2)] = ($sel_x - 1).($sel_y + 1);
                }

                if (strpos($board[($sel_x + 1)][($sel_y + 1)], 'O') !== false) {
                    $valid_moves[] = ($sel_x + 2).($sel_y + 2); //right down
                    $kill[($sel_x + 2).($sel_y + 2)] = ($sel_x + 1).($sel_y + 1);
                }

                if (strpos($board[($sel_x + 1)][($sel_y - 1)], 'O') !== false) {
                    $valid_moves[] = ($sel_x + 2).($sel_y - 2); //left up
                    $kill[($sel_x + 2).($sel_y - 2)] = ($sel_x + 1).($sel_y - 1);
                }

                if (strpos($board[($sel_x - 1)][($sel_y - 1)], 'O') !== false) {
                    $valid_moves[] = ($sel_x - 2).($sel_y - 2); //left down
                    $kill[($sel_x - 2).($sel_y - 2)] = ($sel_x - 1).($sel_y - 1);
                }
            } elseif ($board[$sel_x][$sel_y] == 'XK') {
                for ($move = 1; $move <= 8; $move++) {
                    if ($board[($sel_x - $move)][($sel_y + $move)] == '') {
                        $valid_moves[] = ($sel_x - $move) . ($sel_y + $move); //right up
                    } else {
                        break;
                    }
                }

                for ($move = 1; $move <= 8; $move++) {
                    if ($board[($sel_x + $move)][($sel_y + $move)] == '') {
                        $valid_moves[] = ($sel_x + $move).($sel_y + $move); //right down
                    } else {
                        break;
                    }
                }

                for ($move = 1; $move <= 8; $move++) {
                    if ($board[($sel_x + $move)][($sel_y - $move)] == '') {
                        $valid_moves[] = ($sel_x + $move).($sel_y - $move); //left up
                    } else {
                        break;
                    }
                }

                for ($move = 1; $move <= 8; $move++) {
                    if ($board[($sel_x - $move)][($sel_y - $move)] == '') {
                        $valid_moves[] = ($sel_x - $move).($sel_y - $move); //left down
                    } else {
                        break;
                    }
                }

                for ($move = 1; $move <= 8; $move++) {
                    if (strpos($board[($sel_x - $move)][($sel_y + $move)], 'O') !== false) {
                        for ($move2 = 1; $move2 < $move; $move2++) {
                            if ($board[($sel_x - $move2)][($sel_y + $move2)] != '') {
                                break 2;
                            }
                        }

                        $valid_moves[] = ($sel_x - ($move + 1)).($sel_y + ($move + 1)); //right up

                        $kill[($sel_x - ($move + 1)).($sel_y + ($move + 1))] = ($sel_x - $move).($sel_y + $move);
                        break;
                    }
                }

                for ($move = 1; $move <= 8; $move++) {
                    if (strpos($board[($sel_x + $move)][($sel_y + $move)], 'O') !== false) {
                        for ($move2 = 1; $move2 < $move; $move2++) {
                            if ($board[($sel_x + $move2)][($sel_y + $move2)] != '') {
                                break 2;
                            }
                        }

                        $valid_moves[] = ($sel_x + ($move + 1)) . ($sel_y + ($move + 1)); //right down
                        $kill[($sel_x + ($move + 1)) . ($sel_y + ($move + 1))] = ($sel_x + $move) . ($sel_y + $move);
                        break;
                    }
                }

                for ($move = 1; $move <= 8; $move++) {
                    if (strpos($board[($sel_x + $move)][($sel_y - $move)], 'O') !== false) {
                        for ($move2 = 1; $move2 < $move; $move2++) {
                            if ($board[($sel_x + $move2)][($sel_y - $move2)] != '') {
                                break 2;
                            }
                        }

                        $valid_moves[] = ($sel_x + ($move + 1)).($sel_y - ($move + 1)); //left up
                        $kill[($sel_x + ($move + 1)).($sel_y - ($move + 1))] = ($sel_x + $move).($sel_y - $move);
                        break;
                    }
                }

                for ($move = 1; $move <= 8; $move++) {
                    if (strpos($board[($sel_x - $move)][($sel_y - $move)], 'O') !== false) {
                        for ($move2 = 1; $move2 < $move; $move2++) {
                            if ($board[($sel_x - $move2)][($sel_y - $move2)] != '') {
                                break 2;
                            }
                        }

                        $valid_moves[] = ($sel_x - ($move + 1)).($sel_y - ($move + 1)); //left down
                        $kill[($sel_x - ($move + 1)).($sel_y - ($move + 1))] = ($sel_x - $move).($sel_y - $move);
                        break;
                    }
                }
            }
        } elseif (strpos($char, 'O') !== false) {
            if ($board[$sel_x][$sel_y] == 'O') {
                if ($board[($sel_x + 1)][($sel_y - 1)] == '') {
                    $valid_moves[] = ($sel_x + 1).($sel_y - 1); //left up
                }

                if ($board[($sel_x - 1)][($sel_y - 1)] == '') {
                    $valid_moves[] = ($sel_x - 1).($sel_y - 1); //left down
                }

                if (strpos($board[($sel_x - 1)][($sel_y + 1)], 'X') !== false) {
                    $valid_moves[] = ($sel_x - 2).($sel_y + 2); //right up
                    $kill[($sel_x - 2).($sel_y + 2)] = ($sel_x - 1).($sel_y + 1);
                }

                if (strpos($board[($sel_x + 1)][($sel_y + 1)], 'X') !== false) {
                    $valid_moves[] = ($sel_x + 2).($sel_y + 2); //right down
                    $kill[($sel_x + 2).($sel_y + 2)] = ($sel_x + 1).($sel_y + 1);
                }

                if (strpos($board[($sel_x + 1)][($sel_y - 1)], 'X') !== false) {
                    $valid_moves[] = ($sel_x + 2).($sel_y - 2); //left up
                    $kill[($sel_x + 2).($sel_y - 2)] = ($sel_x + 1).($sel_y - 1);
                }

                if (strpos($board[($sel_x - 1)][($sel_y - 1)], 'X') !== false) {
                    $valid_moves[] = ($sel_x - 2).($sel_y - 2); //left down
                    $kill[($sel_x - 2).($sel_y - 2)] = ($sel_x - 1).($sel_y - 1);
                }
            } elseif ($board[$sel_x][$sel_y] == 'OK') {
                for ($move = 1; $move <= 8; $move++) {
                    if ($board[($sel_x - $move)][($sel_y + $move)] == '') {
                        $valid_moves[] = ($sel_x - $move) . ($sel_y + $move); //right up
                    } else {
                        break;
                    }
                }

                for ($move = 1; $move <= 8; $move++) {
                    if ($board[($sel_x + $move)][($sel_y + $move)] == '') {
                        $valid_moves[] = ($sel_x + $move).($sel_y + $move); //right down
                    } else {
                        break;
                    }
                }

                for ($move = 1; $move <= 8; $move++) {
                    if ($board[($sel_x + $move)][($sel_y - $move)] == '') {
                        $valid_moves[] = ($sel_x + $move).($sel_y - $move); //left up
                    } else {
                        break;
                    }
                }

                for ($move = 1; $move <= 8; $move++) {
                    if ($board[($sel_x - $move)][($sel_y - $move)] == '') {
                        $valid_moves[] = ($sel_x - $move).($sel_y - $move); //left down
                    } else {
                        break;
                    }
                }

                for ($move = 1; $move <= 8; $move++) {
                    if (strpos($board[($sel_x - $move)][($sel_y + $move)], 'X') !== false) {
                        for ($move2 = 1; $move2 < $move; $move2++) {
                            if ($board[($sel_x - $move2)][($sel_y + $move2)] != '') {
                                break 2;
                            }
                        }

                        $valid_moves[] = ($sel_x - ($move + 1)).($sel_y + ($move + 1)); //right up

                        $kill[($sel_x - ($move + 1)).($sel_y + ($move + 1))] = ($sel_x - $move).($sel_y + $move);
                        break;
                    }
                }

                for ($move = 1; $move <= 8; $move++) {
                    if (strpos($board[($sel_x + $move)][($sel_y + $move)], 'X') !== false) {
                        for ($move2 = 1; $move2 < $move; $move2++) {
                            if ($board[($sel_x + $move2)][($sel_y + $move2)] != '') {
                                break 2;
                            }
                        }

                        $valid_moves[] = ($sel_x + ($move + 1)) . ($sel_y + ($move + 1)); //right down
                        $kill[($sel_x + ($move + 1)) . ($sel_y + ($move + 1))] = ($sel_x + $move) . ($sel_y + $move);
                        break;
                    }
                }

                for ($move = 1; $move <= 8; $move++) {
                    if (strpos($board[($sel_x + $move)][($sel_y - $move)], 'X') !== false) {
                        for ($move2 = 1; $move2 < $move; $move2++) {
                            if ($board[($sel_x + $move2)][($sel_y - $move2)] != '') {
                                break 2;
                            }
                        }

                        $valid_moves[] = ($sel_x + ($move + 1)).($sel_y - ($move + 1)); //left up
                        $kill[($sel_x + ($move + 1)).($sel_y - ($move + 1))] = ($sel_x + $move).($sel_y - $move);
                        break;
                    }
                }

                for ($move = 1; $move <= 8; $move++) {
                    if (strpos($board[($sel_x - $move)][($sel_y - $move)], 'X') !== false) {
                        for ($move2 = 1; $move2 < $move; $move2++) {
                            if ($board[($sel_x - $move2)][($sel_y - $move2)] != '') {
                                break 2;
                            }
                        }

                        $valid_moves[] = ($sel_x - ($move + 1)).($sel_y - ($move + 1)); //left down
                        $kill[($sel_x - ($move + 1)).($sel_y - ($move + 1))] = ($sel_x - $move).($sel_y - $move);
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
                $thismove = (string) $thismove;

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
                unset($kill[$x.$y]);
            }
        }

        $c = function ($v) {
            if (is_array($v)) {
                return array_filter($v) != array();
            }
        };

        array_filter($valid_moves, $c);
        array_filter($kill, $c);

        return [
            'valid_moves' => $valid_moves,
            'kills' => $kill
        ];
    }

    private function piecesLeft($board)
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
            'X' => $xs,
            'O' => $ys,
            'XK' => $xks,
            'OK' => $yks,
        ];
    }

    private function isGameOver($board)
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
                    array_push($availableMoves_X, $this->possibleMoves($board, $x.$y, false, 'X"'));
                } elseif (strpos($board[$x][$y], 'O') !== false) {
                    array_push($availableMoves_O, $this->possibleMoves($board, $x.$y, false, 'O'));
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

    private function transpose($array)
    {
        array_unshift($array, null);
        return call_user_func_array('array_map', $array);
    }
}
