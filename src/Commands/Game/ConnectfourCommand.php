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

class ConnectfourCommand extends UserCommand
{
    protected $name = 'connectfour';

    private $query_prefix = 'g2';
    private $strings = [
        'game_empty_field' => ' ',
    ];

    private $rules_url = 'https://en.wikipedia.org/wiki/Connect_Four';

    private $empty_board = [
        ['', '', '', '', '', '', ''],
        ['', '', '', '', '', '', ''],
        ['', '', '', '', '', '', ''],
        ['', '', '', '', '', '', ''],
        ['', '', '', '', '', '', ''],
        ['', '', '', '', '', '', '']
    ];

    private $max_x = 6;
    private $max_y = 7;

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
                                $host_fullname . ' (' . $this->strings['X'] . ')' . ' ' . $this->strings['game_versus_abbreviation'] . ' ' . $guest_fullname . ' (' . $this->strings['O'] . ')' . "\n\n" . $this->strings['game_current_turn'] . ' ' . $move,
                                $this->createInlineKeyboard('game', $gameId, $this->empty_board)
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
                }

                // Handle the game
                if ($user_id == $host_id || $user_id == $guest_id) {
                    if ($data['game_current_turn'] == 'E') {
                        return $this->answerCallback();
                    }

                    $notYourTurn = false;
                    $isValidMove = false;
                    $gameEnded = false;
                    $gameResult = '';
                    $currentMove = '';

                    if (($user_id == $host_id && $data['game_current_turn'] == 'X') || ($user_id == $guest_id && $data['game_current_turn'] == 'O')) {
                        if ($command >= 0 && $command < $this->max_y) {
                            for ($x = 0; $x < ($this->max_x); $x++) {
                                if ($data['board'][$x][$command] == '') {
                                    $isValidMove = true;
                                    $moveData = $x;
                                }
                            }
                        }

                        if ($isValidMove) {
                            if ($user_id == $host_id && $data['game_current_turn'] == 'X') {
                                $data['board'][$moveData][$command] = 'X';
                                $data['game_current_turn'] = 'O';
                            } elseif ($user_id == $guest_id && $data['game_current_turn'] == 'O') {
                                $data['board'][$moveData][$command] = 'O';
                                $data['game_current_turn'] = 'X';
                            }
                        } else {
                            return $this->answerCallback($this->strings['notify_invalid_move'], true);
                        }
                    } else {
                        $notYourTurn = true;
                    }

                    $isOver = $this->isGameOver($data['board']);

                    if (in_array($isOver, ['X', 'O', 'T'])) {
                        if ($isOver == 'X') {
                            $gameResult = "\n\n" . str_replace('{STR}', $host_fullname . '<b>', $this->strings['result_won']) . '</b>';
                            $winner = 'X';
                        } elseif ($isOver == 'O') {
                            $gameResult = "\n\n" . str_replace('{STR}', $guest_fullname . '<b>', $this->strings['result_won']) . '</b>';
                            $winner = 'O';
                        } else {
                            $gameResult = "\n\n" . '<b>' . $this->strings['result_draw'] . '</b>';
                        }

                        $data['game_current_turn'] = 'E';
                        $gameEnded = true;
                    }

                    if (!$gameEnded) {
                        $move = $data['game_current_turn'];
                        $move = str_replace('X', $this->strings['X'], $move);
                        $move = str_replace('O', $this->strings['O'], $move);

                        $currentMove = "\n\n" . $this->strings['game_current_turn'] . ' ' . $move;
                    }

                    $result = Game::updateGame($gameId, json_encode($data));

                    if ($result) {
                        $this->editMessage(
                            $host_fullname . ' (' . $this->strings['X'] . ')' . ' ' . $this->strings['game_versus_abbreviation'] . ' ' . $guest_fullname . ' (' . $this->strings['O'] . ')' . $gameResult . $currentMove,
                            $this->createInlineKeyboard('game', $gameId, $data['board'], $gameEnded, $winner)
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

        $data['board'] = $this->empty_board;
        $data['start_turn'] = $move;
        $data['game_current_turn'] = $move;

        $result = Game::updateGame($id, json_encode($data));

        if ($result) {
            return $move;
        }

        return false;
    }

    private function createInlineKeyboard($menu = '', $id = null, $board = null, $gameIsOver = false, $winner = null)
    {
        $inline_keyboard = [];

        if ($menu == 'game' && is_array($board)) {
            for ($x = 0; $x < $this->max_x; $x++) {
                $tmp_array = [];

                for ($y = 0; $y < $this->max_y; $y++) {
                    if ($board[$x][$y] == 'X' || $board[$x][$y] == 'O' || strpos($board[$x][$y], 'win')) {
                        if ($winner == 'X' && $board[$x][$y] == 'O') {
                            $field = $this->strings['lost'];
                        } elseif ($winner == 'O' && $board[$x][$y] == 'X') {
                            $field = $this->strings['lost'];
                        } else {
                            $field = $this->strings[$board[$x][$y]];
                        }
                    } else {
                        $field = $this->strings['game_empty_field'];
                    }

                    array_push(
                        $tmp_array,
                        new InlineKeyboardButton(
                            [
                                'text' => $field,
                                'callback_data' => $this->query_prefix . '_'.$y.'_' . $id
                            ]
                        )
                    );
                }

                $inline_keyboard[] = $tmp_array;
            }
            if ($gameIsOver) {
                $inline_keyboard[] = [
                    new InlineKeyboardButton(
                        [
                            'text' => $this->strings['button_play_again'],
                            'callback_data' => $this->query_prefix . "_start_" . $id,
                        ]
                    )
                ];
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

    private function isGameOver(&$board)
    {
        $empty = 0;
        for ($x = 0; $x < $this->max_x; $x++) {
            for ($y = 0; $y < $this->max_y; $y++) {
                if ($board[$x][$y] == '') {
                    $empty++;
                }

                if ($board[$x][$y] != '' && $board[$x][$y] == $board[$x][$y + 1] && $board[$x][$y] == $board[$x][$y + 2] && $board[$x][$y] == $board[$x][$y + 3]) {
                    $winner = $board[$x][$y];
                    $board[$x][$y + 1] = $board[$x][$y].'_win';
                    $board[$x][$y + 2] = $board[$x][$y].'_win';
                    $board[$x][$y + 3] = $board[$x][$y].'_win';
                    $board[$x][$y] = $board[$x][$y].'_win';
                    
                    return $winner;
                }

                if ($board[$x][$y] != '' && $board[$x][$y] == $board[$x + 1][$y] && $board[$x][$y] == $board[$x + 2][$y] && $board[$x][$y] == $board[$x + 3][$y]) {
                    $winner = $board[$x][$y];
                    $board[$x + 1][$y] = $board[$x][$y].'_win';
                    $board[$x + 2][$y] = $board[$x][$y].'_win';
                    $board[$x + 3][$y] = $board[$x][$y].'_win';
                    $board[$x][$y] = $board[$x][$y].'_win';
                    
                    return $winner;
                }

                if ($board[$x][$y] != '' && $board[$x][$y] == $board[$x + 1][$y + 1] && $board[$x][$y] == $board[$x + 2][$y + 2] && $board[$x][$y] == $board[$x + 3][$y + 3]) {
                    $winner = $board[$x][$y];
                    $board[$x + 1][$y + 1] = $board[$x][$y].'_win';
                    $board[$x + 2][$y + 2] = $board[$x][$y].'_win';
                    $board[$x + 3][$y + 3] = $board[$x][$y].'_win';
                    $board[$x][$y] = $board[$x][$y].'_win';
                    
                    return $winner;
                }

                if ($board[$x][$y] != '' && $board[$x][$y] == $board[$x - 1][$y + 1] && $board[$x][$y] == $board[$x - 2][$y + 2] && $board[$x][$y] == $board[$x - 3][$y + 3]) {
                    $winner = $board[$x][$y];
                    $board[$x - 1][$y + 1] = $board[$x][$y].'_win';
                    $board[$x - 2][$y + 2] = $board[$x][$y].'_win';
                    $board[$x - 3][$y + 3] = $board[$x][$y].'_win';
                    $board[$x][$y] = $board[$x][$y].'_win';
                    
                    return $winner;
                }
            }
        }

        if ($empty == 0) {
            return 'T';
        }
    }
}
