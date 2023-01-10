<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2016-2020 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bot\Entity\Game;

/**
 * Elephant XO
 */
class Elephantxo extends Tictactoe
{
    /**
     * Game unique ID
     *
     * @var string
     */
    protected static $code = 'exo';

    /**
     * Game name / title
     *
     * @var string
     */
    protected static $title = 'Elephant XO';

    /**
     * Game description
     *
     * @var string
     */
    protected static $description = 'Elephant XO is a game for two players, X and O, who take turns marking the spaces in a 8×8 grid.';

    /**
     * Game thumbnail image
     *
     * @var string
     */
    protected static $image = 'https://i.imgur.com/tv2dmtq.png';

    /**
     * Order on the games list
     *
     * @var int
     */
    protected static $order = 3;

    /**
     * Base starting board
     *
     * @var array
     */
    protected static $board = [
        ['', '', '', '', '', '', '', ''],
        ['', '', '', '', '', '', '', ''],
        ['', '', '', '', '', '', '', ''],
        ['', '', '', '', '', '', '', ''],
        ['', '', '', '', '', '', '', ''],
        ['', '', '', '', '', '', '', ''],
        ['', '', '', '', '', '', '', ''],
        ['', '', '', '', '', '', '', ''],
    ];

    /**
     * Check whenever game is over
     *
     * @param array $board
     *
     * @return string
     */
    protected function isGameOver(array &$board): ?string
    {
        $empty = 0;
        for ($x = 0; $x <= $this->max_x; $x++) {
            for ($y = 0; $y <= $this->max_y; $y++) {
                if (isset($board[$x][$y]) && $board[$x][$y] == '') {
                    $empty++;
                }

                if (isset($board[$x][$y]) && isset($board[$x][$y + 1]) && isset($board[$x][$y + 2]) && isset($board[$x][$y + 3]) && isset($board[$x][$y + 4])) {
                    if ($board[$x][$y] != '' && $board[$x][$y] == $board[$x][$y + 1] && $board[$x][$y] == $board[$x][$y + 2] && $board[$x][$y] == $board[$x][$y + 3] && $board[$x][$y] == $board[$x][$y + 4]) {
                        $winner = $board[$x][$y];
                        $board[$x][$y + 1] = $board[$x][$y] . '_won';
                        $board[$x][$y + 2] = $board[$x][$y] . '_won';
                        $board[$x][$y + 3] = $board[$x][$y] . '_won';
                        $board[$x][$y + 4] = $board[$x][$y] . '_won';
                        $board[$x][$y] = $board[$x][$y] . '_won';

                        return $winner;
                    }
                }

                if (isset($board[$x][$y]) && isset($board[$x + 1][$y]) && isset($board[$x + 2][$y]) && isset($board[$x + 3][$y]) && isset($board[$x + 4][$y])) {
                    if ($board[$x][$y] != '' && $board[$x][$y] == $board[$x + 1][$y] && $board[$x][$y] == $board[$x + 2][$y] && $board[$x][$y] == $board[$x + 3][$y] && $board[$x][$y] == $board[$x + 4][$y]) {
                        $winner = $board[$x][$y];
                        $board[$x + 1][$y] = $board[$x][$y] . '_won';
                        $board[$x + 2][$y] = $board[$x][$y] . '_won';
                        $board[$x + 3][$y] = $board[$x][$y] . '_won';
                        $board[$x + 4][$y] = $board[$x][$y] . '_won';
                        $board[$x][$y] = $board[$x][$y] . '_won';

                        return $winner;
                    }
                }

                if (isset($board[$x][$y]) && isset($board[$x + 1][$y + 1]) && isset($board[$x + 2][$y + 2]) && isset($board[$x + 3][$y + 3]) && isset($board[$x + 4][$y + 4])) {
                    if ($board[$x][$y] != '' && $board[$x][$y] == $board[$x + 1][$y + 1] && $board[$x][$y] == $board[$x + 2][$y + 2] && $board[$x][$y] == $board[$x + 3][$y + 3] && $board[$x][$y] == $board[$x + 4][$y + 4]) {
                        $winner = $board[$x][$y];
                        $board[$x + 1][$y + 1] = $board[$x][$y] . '_won';
                        $board[$x + 2][$y + 2] = $board[$x][$y] . '_won';
                        $board[$x + 3][$y + 3] = $board[$x][$y] . '_won';
                        $board[$x + 4][$y + 4] = $board[$x][$y] . '_won';
                        $board[$x][$y] = $board[$x][$y] . '_won';

                        return $winner;
                    }
                }

                if (isset($board[$x][$y]) && isset($board[$x - 1][$y + 1]) && isset($board[$x - 2][$y + 2]) && isset($board[$x - 3][$y + 3]) && isset($board[$x - 4][$y + 4])) {
                    if ($board[$x][$y] != '' && $board[$x][$y] == $board[$x - 1][$y + 1] && $board[$x][$y] == $board[$x - 2][$y + 2] && $board[$x][$y] == $board[$x - 3][$y + 3] && $board[$x][$y] == $board[$x - 4][$y + 4]) {
                        $winner = $board[$x][$y];
                        $board[$x - 1][$y + 1] = $board[$x][$y] . '_won';
                        $board[$x - 2][$y + 2] = $board[$x][$y] . '_won';
                        $board[$x - 3][$y + 3] = $board[$x][$y] . '_won';
                        $board[$x - 4][$y + 4] = $board[$x][$y] . '_won';
                        $board[$x][$y] = $board[$x][$y] . '_won';

                        return $winner;
                    }
                }
            }
        }

        if ($empty == 0) {
            return 'T';
        }

        return null;
    }
}
