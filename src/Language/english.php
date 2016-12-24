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

$translation = [
    // This Language
    'language' => 'English',

    // Game stuff
    'game_versus_abbreviation' => 'vs.',
    'game_current_turn' => 'Current turn:',

    // Buttons
    'button_play' => 'Play',
    'button_join' => 'Join',
    'button_quit' => 'Quit',
    'button_kick' => 'Kick',
    'button_lobby' => 'Lobby',
    'button_play_again' => 'Play again',
    'button_create' => 'Create new session',
    'button_language' => 'Language',
    'button_rules' => 'Game rules',

    // States
    'state_waiting_for_opponent' => '{STR} is waiting for opponent to join...' . "\n" . 'Press <b>\'Join\'</b> button to join.',
    'state_waiting_for_host' => 'Waiting for {STR} to start...' . "\n" . 'Press <b>\'Play\'</b> button to start.',
    'state_session_is_empty' => 'This game session is empty.',
    'state_session_not_available' => 'This game session is no longer available.',

    // Results
    'result_won' => '{STR} has won!',
    'result_draw' => 'Game ended with a draw!',

    // Notifications
    'notify_invalid_move' => 'Invalid move!',
    'notify_not_your_turn' => 'It\'s not your turn!',
    'notify_not_a_host' => 'You\'re not the host!',
    'notify_not_in_this_game' => 'You\'re not in this game!',
    'notify_game_is_full' => 'This game is full!',
    'notify_cant_play_with_yourself' => 'You can\'t play with yourself!',
    'notify_go_to_lobby' => 'Are you sure you want to go back to the lobby?' . "\n" . 'Click this button again within 5 seconds to confirm.',
    'notify_process_is_busy' => 'Process for this game is busy!' . "\n" . 'Try again in a few seconds.',

];
