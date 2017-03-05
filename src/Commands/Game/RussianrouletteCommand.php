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

class RussianrouletteCommand extends UserCommand
{
    protected $name = 'russianroulette';

    private $query_prefix = 'g6';
    private $strings = [
        'game_empty_field' => ' ',
    ];

    private $rules_url = 'https://en.wikipedia.org/wiki/Russian_roulette';

    private $cylinder = ['', '', '', '', '', ''];

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
                            if ($data['game_current_turn'] == 'E') {
                                $move = $this->initalizeGame($gameId, $data);

                                if ($move) {
                                    if ($move == 'X') {
                                        $move = $host_fullname;
                                    } else {
                                        $move = $this_user_fullname;
                                    }

                                    $this->editMessage(
                                        $host_fullname . ' ' . $this->strings['game_versus_abbreviation'] . ' ' . $this_user_fullname . "\n\n" . $this->strings['game_current_turn'] . ' ' . $move,
                                        $this->createInlineKeyboard('game', $gameId)
                                    );
                                }
                            } else {
                                $this->editMessage(
                                    str_replace('{STR}', $host_fullname, $this->strings['state_waiting_for_host']),
                                    $this->createInlineKeyboard('pregame', $gameId)
                                );
                            }
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
                            if ($move == 'X') {
                                $move = $host_fullname;
                            } else {
                                $move = $guest_fullname;
                            }

                            $this->editMessage(
                                $host_fullname . ' ' . $this->strings['game_versus_abbreviation'] . ' ' . $guest_fullname . "\n\n" . $this->strings['game_current_turn'] . ' ' . $move,
                                $this->createInlineKeyboard('game', $gameId)
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
                    if ($data['game_current_turn'] == 'E' || $command == 'null') {
                        return $this->answerCallback();
                    }

                    $notYourTurn = false;
                    $gameEnded = false;
                    $gameResult = '';
                    $currentMove = '';
                    $command = $command - 1;

                    if (($user_id == $host_id && $data['game_current_turn'] == 'X') || ($user_id == $guest_id && $data['game_current_turn'] == 'O')) {
                        if ($data['cylinder'][$command] == 'X') {
                            if ($data['game_current_turn'] == 'X') {
                                $gameResult = "\n\n" . str_replace('{STR}', $host_fullname . '<b>', $this->strings['result_died']) . '</b>';
                                $gameResult .= "\n" . str_replace('{STR}', $guest_fullname . '<b>', $this->strings['result_won']) . '</b>';
                                $gameResult .= "\n\n" . str_replace('{STR}', $guest_fullname, $this->strings['state_waiting_for_opponent']);

                                $result = Game::migrateHost($gameId, $guest_id);
                            } elseif ($data['game_current_turn'] == 'O') {
                                $gameResult = "\n\n" . str_replace('{STR}', $guest_fullname . '<b>', $this->strings['result_died']) . '</b>';
                                $gameResult .= "\n" . str_replace('{STR}', $host_fullname . '<b>', $this->strings['result_won']) . '</b>';
                                $gameResult .= "\n\n" . str_replace('{STR}', $host_fullname, $this->strings['state_waiting_for_opponent']);

                                $result = Game::setGuest($gameId, null);
                            }

                            if (!$result) {
                                return $this->answerCallback();
                            }

                            $gameEnded = true;
                            $hit = $command + 1;
                            $data['game_current_turn'] = 'E';
                        } else {
                            if ($data['game_current_turn'] == 'X') {
                                $gameResult = "\n\n" . str_replace('{STR}', $host_fullname . '<b>', $this->strings['result_survived']) . '</b>';
                                $data['game_current_turn'] = 'O';
                            } elseif ($data['game_current_turn'] == 'O') {
                                $gameResult = "\n\n" . str_replace('{STR}', $guest_fullname . '<b>', $this->strings['result_survived']) . '</b>';
                                $data['game_current_turn'] = 'X';
                            }

                            $this->cylinder[mt_rand(0, 5)] = 'X';
                            $data['cylinder'] = $this->cylinder;

                            $hit = false;
                        }
                    } else {
                        $notYourTurn = true;
                    }

                    if (!$gameEnded) {
                        if (empty($gameResult)) {
                            $gameResult = "\n";
                        }

                        $move = $data['game_current_turn'];

                        if ($move == 'X') {
                            $move = $host_fullname;
                        } else {
                            $move = $guest_fullname;
                        }

                        $currentMove = "\n" . $this->strings['game_current_turn'] . ' ' . $move;
                    }

                    $result = Game::updateGame($gameId, json_encode($data));

                    if ($result) {
                        $this->editMessage(
                            $host_fullname . ' ' . $this->strings['game_versus_abbreviation'] . ' ' . $guest_fullname . $gameResult . $currentMove,
                            $this->createInlineKeyboard('game', $gameId, $hit, $gameEnded)
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

        $this->cylinder[mt_rand(0, 5)] = 'X';

        $data['cylinder'] = $this->cylinder;
        $data['start_turn'] = $move;
        $data['game_current_turn'] = $move;

        $result = Game::updateGame($id, json_encode($data));

        if ($result) {
            return $move;
        }

        return false;
    }

    private function createInlineKeyboard($menu = '', $id = null, $hit = false, $gameEnded = false)
    {
        $inline_keyboard = [];

        if ($menu == 'game') {
            $inline_keyboard[] = [
                new InlineKeyboardButton(
                    [
                        'text' => $this->strings['game_empty_field'],
                        'callback_data' => $this->query_prefix . "_null_" . $id
                    ]
                ),
                new InlineKeyboardButton(
                    [
                        'text' => ($hit == 1) ? $this->strings['game_chamber_hit'] : $this->strings['game_chamber'],
                        'callback_data' => $this->query_prefix . "_1_" . $id
                    ]
                ),
                new InlineKeyboardButton(
                    [
                        'text' => ($hit == 2) ? $this->strings['game_chamber_hit'] : $this->strings['game_chamber'],
                        'callback_data' => $this->query_prefix . "_2_" . $id
                    ]
                ),
                new InlineKeyboardButton(
                    [
                        'text' => $this->strings['game_empty_field'],
                        'callback_data' => $this->query_prefix . "_null_" . $id
                    ]
                )
            ];

            $inline_keyboard[] = [
                new InlineKeyboardButton(
                    [
                        'text' => ($hit == 6) ? $this->strings['game_chamber_hit'] : $this->strings['game_chamber'],
                        'callback_data' => $this->query_prefix . "_6_" . $id
                    ]
                ),
                new InlineKeyboardButton(
                    [
                        'text' => $this->strings['game_empty_field'],
                        'callback_data' => $this->query_prefix . "_null_" . $id
                    ]
                ),
                new InlineKeyboardButton(
                    [
                        'text' => ($hit == 3) ? $this->strings['game_chamber_hit'] : $this->strings['game_chamber'],
                        'callback_data' => $this->query_prefix . "_3_" . $id
                    ]
                )
            ];

            $inline_keyboard[] = [
                new InlineKeyboardButton(
                    [
                        'text' => $this->strings['game_empty_field'],
                        'callback_data' => $this->query_prefix . "_null_" . $id
                    ]
                ),
                new InlineKeyboardButton(
                    [
                        'text' => ($hit == 5) ? $this->strings['game_chamber_hit'] : $this->strings['game_chamber'],
                        'callback_data' => $this->query_prefix . "_5_" . $id
                    ]
                ),
                new InlineKeyboardButton(
                    [
                        'text' => ($hit == 4) ? $this->strings['game_chamber_hit'] : $this->strings['game_chamber'],
                        'callback_data' => $this->query_prefix . "_4_" . $id
                    ]
                ),
                new InlineKeyboardButton(
                    [
                        'text' => $this->strings['game_empty_field'],
                        'callback_data' => $this->query_prefix . "_null_" . $id
                    ]
                )
            ];
            if (!$gameEnded) {
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
            } else {
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
            }
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

    private function isGameOver($board)
    {
        $empty = 0;
        for ($x = 0; $x < $this->max_x; $x++) {
            for ($y = 0; $y < $this->max_y; $y++) {
                if ($board[$x][$y] == '') {
                    $empty++;
                }


                if ($board[$x][$y] != '' && $board[$x][$y] == $board[$x][$y + 1] && $board[$x][$y] == $board[$x][$y + 2]) {
                    return $board[$x][$y];
                }

                if ($board[$x][$y] != '' && $board[$x][$y] == $board[$x + 1][$y] && $board[$x][$y] == $board[$x + 2][$y]) {
                    return $board[$x][$y];
                }


                if ($board[$x][$y] != '' && $board[$x][$y] == $board[$x + 1][$y + 1] && $board[$x][$y] == $board[$x + 2][$y + 2]) {
                    return $board[$x][$y];
                }

                if ($board[$x][$y] != '' && $board[$x][$y] == $board[$x - 1][$y + 1] && $board[$x][$y] == $board[$x - 2][$y + 2]) {
                    return $board[$x][$y];
                }
            }
        }

        if ($empty == 0) {
            return 'T';
        }
    }
}
