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
    // Game title
    'message_prefix' => 'Checkers',

    // Game symbols
    'X' => '⚫️️',
    'XK' => '◾️️',
    'O' => '⚪️',
    'OK' => '◽️️',
    'game_empty_field' => '.',

    // Game text
    'game_selected_pawn' => '(Selected: {STR})',
    'game_select_pawn' => '(Select the piece you want to move)',
    'game_select_info' => '(Make your move or select different piece)',
    'game_chain_move' => '(Your move must continue)',
    'game_voted_to_draw' => '({STR} offered a draw)',

    // Buttons
    'button_forfeit' => 'Surrender',
    'button_draw' => 'Vote to draw',

    // Results
    'result_surrender' => '({STR} surrendered)',

    // Notifications
    'notify_voted_to_draw' => 'You voted to draw this game!',
    'notify_already_voted_to_draw' => 'You already voted to draw this game!',
    'notify_wrong_selection' => 'Invalid selection!',
    'notify_mandatory_jump' => 'You must make a jump when possible!',
];
