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
use Longman\TelegramBot\State;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\DBHelper;
use Longman\TelegramBot\Game;
use Longman\TelegramBot\Entities\InlineKeyboardMarkup;
use Longman\TelegramBot\Entities\InlineKeyboardButton;

class WaitinglistCommand extends UserCommand
{
    protected $name = 'waitinglist';
    protected $enabled = false;

    protected $state;

    private $query_prefix = 'waitinglist';
    
    private $msg_prefix = '🕹 <b>Looking to play</b>' . "\n\n";

    private $strings = [
        'enter_button' => 'Enter️ 🔼',
        'enter_button_txt' => 'Enter',
        'quit_button' => 'Quit 🔽',
        'quit_button_txt' => 'Quit',
        'refresh_button' => 'Refresh 🔄',
        'refresh_button_txt' => 'Refresh',
        'start_button' => 'Start ✳️️',
        'start_button_txt' => 'Start',
        'continue_button' => 'Continue ➡️️',
        'continue_button_txt' => 'Continue️',
        'back_button' => 'Back ⬅️️',
        'back_button_txt' => 'Back',
        'finish_button' => 'Finish ✅️',
        'finish_button_txt' => 'Finish',
        'cancel_button' => 'Cancel 🅾',
        'cancel_button_txt' => 'Cancel',
        'select_none_button' => 'Select NONE️',
        'select_all_button' => 'Select ALL',
        'goback_button' => 'Go back ⬅',
        'goback_button_txt' => 'Go back',
    ];

    private $games = [];

    private $durations = [
        '15' => '15 minutes',
        '30' => '30 minutes',
        '60' => '1 hour',
        '120' => '2 hours',
    ];

    public function execute()
    {
        $message = $this->getMessage();
        $callback_query = $this->getUpdate()->getCallbackQuery();

        if ($message) {
            $chat_id = $message->getChat()->getId();
            $user_id = $message->getFrom()->getId();

            $user_username = $message->getFrom()->getUserName();

            $gettext = trim($message->getText(true));
            $gettext_check = $this->query_prefix . '_start';
        } elseif ($callback_query) {
            $chat_id = $callback_query->getMessage()->getChat()->getId();
            $user_id = $callback_query->getFrom()->getId();

            $user_username = $callback_query->getFrom()->getUserName();

            $gettext = $callback_query->getData();
            $gettext_check = $gettext;
            $gettext = explode('_', $gettext);
            $gettext = $gettext[1];
            
            $data_query = [];
            $data_query['callback_query_id'] = $callback_query->getId();
        }

        $temp_path = $this->getTelegram()->getDownloadPath();

        if (!$this->getTelegram()->isAdmin() && ($gettext_check == $this->query_prefix . '_' || $gettext_check == $this->query_prefix . '_start')) {
            if (file_exists($temp_path . '/' . $chat_id . '.lock_waitinglist') && time() < (filemtime($temp_path . '/' . $chat_id . '.lock_waitinglist') + 15)) {
                if ($callback_query) {
                    $data_query['text'] = 'Please wait at least 15 seconds between refreshes!'."\n".'Spamming will result in a temporary block.';
                    $data_query['show_alert'] = true;
                    return Request::answerCallbackQuery($data_query);
                } else {
                    return Request::emptyResponse();
                }
            }

            touch($temp_path . '/' . $chat_id . '.lock_waitinglist');
        }

        // DISABLED
        $disabled_text = "Sorry, but this feature has been disabled!\n\nWhy?\nBecause people kept reporting others for spam.";

        if ($message) {
            $data = [];
            $data['chat_id'] = $chat_id;
            $data['text'] = $disabled_text;
            return Request::sendMessage($data);
        } elseif ($callback_query) {
            $data_query['text'] = $disabled_text;
            $data_query['show_alert'] = true;
            return Request::answerCallbackQuery($data_query);
        }
        // DISABLED

        $this->games = Game::idToName();
        $this->state = new State($user_id);

        if ($gettext == 'quit') {
            $result = DBHelper::query('DELETE FROM `waiting_list` WHERE `user_id` = ' . $user_id);

            if ($result) {
                $data_query['text'] = "You have been removed from the list!";
            } else {
                $data_query['text'] = "There was an error while removing you from the list!";
                $data_query['show_alert'] = true;
                return Request::answerCallbackQuery($data_query);
            }
        } elseif ($gettext == 'cancel') {
            $this->state->cancel();

            $data_query['text'] = "You decided to cancel!";
            Request::answerCallbackQuery($data_query);
        } elseif ($gettext == 'start') {
            if ($this->state) {
                $this->state->cancel();
            }
        }

        if ((($this->state->exists() && $this->state->getCommand() == $this->getName()) || $gettext == 'enter')) {
            if ($gettext == 'cancel') {
                $text = "<b>You decided to cancel.</b>\n\nClick <b>'" . $this->strings['goback_button_txt'] . "'</b> button to return to the list.";
                $this->state->cancel();

                $inline_keyboard = [
                    [
                        new InlineKeyboardButton(
                            [
                                'text' => $this->strings['goback_button'],
                                'callback_data' => $this->query_prefix . '_back'
                            ]
                        )
                    ]
                ];
            } else {
                $query = DBHelper::query('SELECT * FROM `waiting_list` WHERE `user_id` = ' . $user_id);

                if (count($query) == 1) {
                    $data_query['text'] = "You are already on the list!";
                    $data_query['show_alert'] = true;
                    return Request::answerCallbackQuery($data_query);
                }

                $this->state = new State($user_id, $this->getName());

                if (!isset($this->state->notes['no_input'])) {
                    $this->state->notes['no_input'] = true;
                    $this->state->update();
                }

                if (!isset($this->state->notes['state'])) {
                    $state = 0;
                } else {
                    $state = $this->state->notes['state'];
                }

                if ($gettext == 'back') {
                    $state = $state - 1;

                    if ($state < 0) {
                        $state = 0;
                    }

                    $this->state->notes['state'] = $state;
                    $this->state->update();
                }

                switch ($state) {
                    case 0:
                        $inline_keyboard = [
                            [
                                new InlineKeyboardButton(
                                    [
                                        'text' => $this->strings['cancel_button'],
                                        'callback_data' => $this->query_prefix . '_cancel'
                                    ]
                                ),
                                new InlineKeyboardButton([
                                    'text' => $this->strings['start_button'],
                                    'callback_data' => $this->query_prefix . '_continue'
                                ])
                            ]
                        ];

                        $text = "<b>If you're not fine with revealing your name, photo and username to a person you don't know — cancel immediately!</b>\n\n<b>Make sure you have username set or you won't be able to continue!</b> (<a href=\"https://telegram.org/faq#q-what-are-usernames-how-do-i-get-one\">How do I do that?</a>)\n\nClick <b>'" . $this->strings['start_button_txt'] . "'</b> button when ready.\nYou can cancel anytime by clicking <b>'" . $this->strings['cancel_button_txt'] . "'</b> button.";

                        if (empty($user_username)) {
                            $data_query['text'] = "You don't seem to have @username set!";
                            $data_query['show_alert'] = true;
                            break;
                        }

                        if ($gettext != 'continue') {
                            break;
                        }

                        $this->state->notes['state'] = 1;
                        $this->state->update();

                        $gettext = '';
                    // no break
                    case 1:
                        if (!isset($this->state->notes['games']) || $gettext == 'all' || $gettext == 'none') {
                            $this->state->notes['games'] = '';
                        }

                        $inline_keyboard = [];
                        $tmp_array = [];

                        $counter = 0;
                        $selected = 0;
                        foreach ($this->games as $gameid => $gamename) {
                            $counter++;

                            if ($gettext == $gameid || $gettext == 'all' || $gettext == 'none') {
                                if ((strpos($this->state->notes['games'], $gameid) !== false && $gettext != 'all') || $gettext == 'none') {
                                    $this->state->notes['games'] = str_replace($gameid, '', $this->state->notes['games']);
                                } else {
                                    $this->state->notes['games'] .= ' ' . $gameid;
                                }
                                $this->state->notes['games'] = trim($this->state->notes['games']);
                                $this->state->update();
                            }

                            if (strpos($this->state->notes['games'], $gameid) !== false) {
                                $isSelected = true;
                                $selected++;
                            } else {
                                $isSelected = false;
                            }

                            array_push(
                                $tmp_array,
                                new InlineKeyboardButton(
                                    [
                                        'text' => (($isSelected) ? '✔ ' : '✖️ ') . $gamename,
                                        'callback_data' => $this->query_prefix . '_' . $gameid
                                    ]
                                )
                            );

                            if ($counter % 2 == 0) {
                                $inline_keyboard[] = $tmp_array;
                                $tmp_array = [];
                            }
                        }

                        if (count($tmp_array) > 0) {
                            $inline_keyboard[] = $tmp_array;
                        }

                        $inline_keyboard[] = [
                            new InlineKeyboardButton(
                                [
                                    'text' => $this->strings['select_none_button'],
                                    'callback_data' => $this->query_prefix . '_none'
                                ]
                            ),
                            new InlineKeyboardButton(
                                [
                                    'text' => $this->strings['select_all_button'],
                                    'callback_data' => $this->query_prefix . '_all'
                                ]
                            )
                        ];
                        $inline_keyboard[] = [
                            new InlineKeyboardButton(
                                [
                                    'text' => $this->strings['back_button'],
                                    'callback_data' => $this->query_prefix . '_back'
                                ]
                            ),
                            new InlineKeyboardButton(
                                [
                                    'text' => $this->strings['cancel_button'],
                                    'callback_data' => $this->query_prefix . '_cancel'
                                ]
                            ),
                            new InlineKeyboardButton(
                                [
                                    'text' => $this->strings['continue_button'],
                                    'callback_data' => $this->query_prefix . '_continue'
                                ]
                            )
                        ];

                        $games = explode(' ', $this->state->notes['games']);

                        $gameslist = '';
                        foreach ($games as $game) {
                            if (!empty($this->games[$game])) {
                                if (!empty($gameslist)) {
                                    $gameslist .= ', ';
                                }
                                $gameslist .= $this->games[$game];
                            }
                        }

                        if (!empty($gameslist)) {
                            $gamesorder = "<b>Order:</b> ".$gameslist."\n\n";
                        } else {
                            $gamesorder = '';
                        }

                        $text = "Select the game(s) you want to play.\n\n".$gamesorder."Click <b>'" . $this->strings['continue_button_txt'] . "'</b> button to go to the next step.";

                        if ($gettext != 'continue') {
                            break;
                        }

                        if ($gettext == 'continue' && $selected == 0) {
                            $data_query['text'] = "You must select at least one game!";
                            $data_query['show_alert'] = true;
                            break;
                        }

                        $this->state->notes['state'] = 2;
                        $this->state->update();

                        $gettext = '';
                    // no break
                    case 2:
                        if (!isset($this->state->notes['duration'])) {
                            $this->state->notes['duration'] = 60;
                        }

                        foreach ($this->durations as $time => $timename) {
                            if ($gettext == $time) {
                                $this->state->notes['duration'] = $time;
                                $this->state->update();
                            }
                        }

                        $inline_keyboard = [];
                        $tmp_array = [];
                        $counter = 0;
                        foreach ($this->durations as $time => $timename) {
                            $counter++;

                            if ($this->state->notes['duration'] == $time) {
                                $isSelected = true;
                            } else {
                                $isSelected = false;
                            }

                            array_push(
                                $tmp_array,
                                new InlineKeyboardButton(
                                    [
                                        'text' => (($isSelected) ? '✔ ' : '✖️ ') . $timename,
                                        'callback_data' => $this->query_prefix . '_' . $time
                                    ]
                                )
                            );

                            if ($counter % 2 == 0) {
                                $inline_keyboard[] = $tmp_array;
                                $tmp_array = [];
                            }
                        }

                        if (count($tmp_array) > 0) {
                            $inline_keyboard[] = $tmp_array;
                        }

                        $inline_keyboard[] = [
                            new InlineKeyboardButton(
                                [
                                    'text' => $this->strings['back_button'],
                                    'callback_data' => $this->query_prefix . '_back'
                                ]
                            ),
                            new InlineKeyboardButton(
                                [
                                    'text' => $this->strings['cancel_button'],
                                    'callback_data' => $this->query_prefix . '_cancel'
                                ]
                            ),
                            new InlineKeyboardButton(
                                [
                                    'text' => $this->strings['continue_button'],
                                    'callback_data' => $this->query_prefix . '_continue'
                                 ]
                            )
                        ];

                        $text = "Select for how long you wish to stay on the list.\n\nClick <b>'" . $this->strings['continue_button_txt'] . "'</b> button to go to the next step.";

                        if ($gettext != 'continue') {
                            break;
                        }

                        $this->state->notes['state'] = 3;
                        $this->state->update();

                        $gettext = '';
                    // no break
                    case 3:
                        $games = explode(' ', $this->state->notes['games']);

                        $gameslist = '';
                        foreach ($games as $game) {
                            if (!empty($this->games[$game])) {
                                if (!empty($gameslist)) {
                                    $gameslist .= ', ';
                                }
                                $gameslist .= $this->games[$game];
                            }
                        }

                        $inline_keyboard = [
                            [
                                new InlineKeyboardButton(
                                    [
                                        'text' => $this->strings['back_button'],
                                        'callback_data' => $this->query_prefix . '_back'
                                    ]
                                ),
                                new InlineKeyboardButton(
                                    [
                                        'text' => $this->strings['cancel_button'],
                                        'callback_data' => $this->query_prefix . '_cancel'
                                    ]
                                ),
                                new InlineKeyboardButton(
                                    [
                                        'text' => $this->strings['finish_button'],
                                        'callback_data' => $this->query_prefix . '_continue'
                                    ]
                                )
                            ]
                        ];

                        $text = "<b>Your selections:</b>\n <b>Games order:</b> ".$gameslist."\n <b>Remove after:</b> ".$this->durations[$this->state->notes['duration']]."\n\nClick <b>'" . $this->strings['finish_button_txt'] . "'</b> button to enter the list or <b>'" . $this->strings['back_button_txt'] . "'</b> button if you want to change anything.";

                        if ($gettext != 'continue') {
                            break;
                        }

                        $this->state->notes['state'] = 4;
                        $this->state->update();

                        $gettext = '';
                    // no break
                    case 4:
                        $gameslist = explode(' ', $this->state->notes['games']);

                        $where = '';
                        foreach ($gameslist as $game) {
                            if ($game != '') {
                                if (!empty($where)) {
                                    $where .= ' OR ';
                                }

                                $where .= '`games` LIKE \'%' . $game . '%\'';
                            }
                        }
                        $where = '(' . $where . ')';

                        $query = DBHelper::query('SELECT * FROM `waiting_list` WHERE ' . $where . ' AND `user_id` != ' . $user_id . ' ORDER BY `waiting_list`.`created_at` ASC');

                        if (count($query) > 0) {
                            $thisplayer = DBHelper::query('SELECT * FROM `user` WHERE `id` = '. $query[0]['user_id']);

                            $commongames = '';
                            foreach ($gameslist as $game) {
                                if (!empty($query[0]['games']) && !empty($game) && strpos($query[0]['games'], $game) !== false) {
                                    if (!empty($this->games[$game])) {
                                        if (!empty($commongames)) {
                                            $commongames .= ', ';
                                        }

                                        $commongames .= $this->games[$game];
                                    }
                                }
                            }

                            $text = "@".$thisplayer[0]['username']."<b> also wants to play:</b>\n ".$commongames;

                            if (count($query) > 1) {
                                if (count($gameslist) == 1) {
                                    $games_str = 'game';
                                } else {
                                    $games_str = 'games';
                                }


                                $text .= "\n\n" . 'There are also <b>' . (count($query) - 1) . '</b> more users on the list looking to play the same ' . $games_str . ' you selected.' . "\n";
                            }

                            $text .= "\n<b>Are you sure you want to enter the list, wouldn't it be better to message people who are already on the list instead and play?</b>\n\nClick <b>'" . $this->strings['finish_button_txt'] . "'</b> button to enter the list anyway or <b>'" . $this->strings['cancel_button_txt'] . "'</b> button to cancel.";

                            $inline_keyboard = [
                                [
                                    new InlineKeyboardButton(
                                        [
                                            'text' => $this->strings['cancel_button'],
                                            'callback_data' => $this->query_prefix . '_cancel'
                                        ]
                                    ),
                                    new InlineKeyboardButton(
                                        [
                                            'text' => $this->strings['finish_button'],
                                            'callback_data' => $this->query_prefix . '_continue'
                                        ]
                                    )
                                ]
                            ];

                            if ($gettext != 'continue') {
                                break;
                            }
                        }

                        $this->state->notes['state'] = 5;
                        $this->state->update();

                        $gettext = '';
                    // no break
                    case 5:
                        $result = DBHelper::query('INSERT INTO `waiting_list` (`user_id`, `games`, `delete_at`, `created_at`) VALUES (' . $user_id . ', \''. $this->state->notes['games'] .'\', \'' . date('Y-m-d H:i:s', strtotime("+ ".$this->state->notes['duration']." minutes")) . '\', \'' . date('Y-m-d H:i:s', time()) . '\') ON DUPLICATE KEY UPDATE `created_at` = \'' . date('Y-m-d H:i:s', time()) . '\'');

                        if ($result) {
                            $text = "<b>You're now on the list!</b>\nYou will be removed automatically after <b>".$this->durations[$this->state->notes['duration']]."</b>.\n\nClick <b>'" . $this->strings['goback_button_txt'] . "'</b> button to return to the list.";

                            $inline_keyboard = [
                                [
                                    new InlineKeyboardButton(
                                        [
                                            'text' => $this->strings['goback_button'],
                                            'callback_data' => $this->query_prefix . '_'
                                        ]
                                    )
                                ]
                            ];

                            $this->state->stop();
                        } else {
                            $data_query['text'] = "There was an error while putting you on the list!";
                            $data_query['show_alert'] = true;
                            return Request::answerCallbackQuery($data_query);
                        }

                        break;
                }
            }
        } else {
            $result = DBHelper::query('SELECT * FROM `waiting_list` AS t1 INNER JOIN `user` AS t2 ON t1.user_id = t2.id ORDER BY t1.`created_at` ASC');
            $mm_users = count($result);

            if ($result && $mm_users > 0) {
                if ($mm_users == 1) {
                    $str = 'user';
                    $str2 = 'is';
                } else {
                    $str = 'users';
                    $str2 = 'are';
                }

                $text = 'Currently, there '. $str2 . ' <b>' . $mm_users . '</b> ' . $str . ' on the list:'. "\n";

                $counter = 1;
                if (count($result) > 0) {
                    $isOnTheList = false;
                    $playerlist = '';

                    foreach ($result as $player) {
                        if (!empty($playerlist)) {
                            $playerlist .= "\n";
                        }

                        if ($player['user_id'] == $user_id) {
                            $isOnTheList = true;
                        }

                        $playergames = explode(' ', $player['games']);

                        $games = '';
                        foreach ($playergames as $game) {
                            if (!empty($this->games[$game])) {
                                if (!empty($games)) {
                                    $games .= ", ";
                                }

                                $games .= "<b>".$this->games[$game]."</b>";
                            }
                        }

                        $playerlist .= " ".$counter.". @".$player['username'] . " - ".$games."";
                        $counter++;
                    }

                    $text .= $playerlist ."\n\n";
                }

                if ($isOnTheList) {
                    $inline_keyboard = [
                        [
                            new InlineKeyboardButton(
                                [
                                    'text' => $this->strings['refresh_button'],
                                    'callback_data' => $this->query_prefix . '_'
                                ]
                            ),
                            new InlineKeyboardButton(
                                [
                                    'text' => $this->strings['quit_button'],
                                    'callback_data' => $this->query_prefix . '_quit'
                                ]
                            )
                        ]
                    ];

                    $text .= "Message someone from the list!\n";
                    $text .= "Click <b>'" . $this->strings['quit_button_txt'] . "'</b> button to quit the list.";
                } else {
                    $inline_keyboard = [
                        [
                            new InlineKeyboardButton(
                                [
                                    'text' => $this->strings['refresh_button'],
                                    'callback_data' => $this->query_prefix . '_'
                                ]
                            ),
                            new InlineKeyboardButton(
                                [
                                    'text' => $this->strings['enter_button'],
                                    'callback_data' => $this->query_prefix . '_enter'
                                ]
                            )
                        ]
                    ];

                    $text .= "Message someone from the list or click <b>'" . $this->strings['enter_button_txt'] . "'</b> button to enter it if there is noone looking to play the game you want to play.";
                }
            } else {
                $inline_keyboard = [
                    [
                        new InlineKeyboardButton(
                            [
                                'text' => $this->strings['refresh_button'],
                                'callback_data' => $this->query_prefix . '_'
                            ]
                        ),
                        new InlineKeyboardButton(
                            [
                                'text' => $this->strings['enter_button'],
                                'callback_data' => $this->query_prefix . '_enter'
                            ]
                        )
                    ]
                ];

                $text = '<b>Currently, the list is empty!</b>'. "\n\nClick <b>'" . $this->strings['enter_button_txt'] . "'</b> button to enter the list.";
            }
        }

        $data = [];
        $data['chat_id'] = $chat_id;
        $data['parse_mode'] = 'HTML';
        $data['disable_web_page_preview'] = true;

        if ($text != '') {
            $data['text'] = $this->msg_prefix . $text;
        }

        if ($inline_keyboard) {
            $data['reply_markup'] = new InlineKeyboardMarkup(['inline_keyboard' => $inline_keyboard]);
        }

        if ($message || $gettext == 'start') {
            if ($gettext == 'start') {
                Request::answerCallbackQuery($data_query);
            }

            return Request::sendMessage($data);
        } elseif ($callback_query) {
            Request::answerCallbackQuery($data_query);
            $data['message_id'] = $callback_query->getMessage()->getMessageId();
            return Request::editMessageText($data);
        }
    }
}
