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
use Bot\Helper\DebugLog;
use Spatie\Emoji\Emoji;

/**
 * Class Tictactoe
 *
 * @package Bot\Entity\Game
 */
class Tictactoe extends Game
{
    /**
     * Game unique id (for callback queries)
     *
     * @var string
     */
    private static $code = 'xo';

    /**
     * Game name
     *
     * @var string
     */
    private static $title = 'Tic-Tac-Toe';

    /**
     * Game description
     *
     * @var string
     */
    private static $description = 'Tic-tac-toe is a game for two players, X and O, who take turns marking the spaces in a 3×3 grid.';

    /**
     * Game image (for inline query result)
     *
     * @var string
     */
    private static $image = 'http://i.imgur.com/yU2uexr.png';

    /**
     * Order on the game list (inline query result)
     *
     * @var int
     */
    private static $order = 1;

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
    protected $max_x;
    protected $max_y;
    protected $symbols = [];

    /**
     * Define game symbols (emojis)
     */
    private function defineSymbols()
    {
        $this->symbols['empty'] = '.';

        $this->symbols['X'] = Emoji::crossMark();
        $this->symbols['O'] = Emoji::heavyLargeCircle();

        $this->symbols['X_won'] = $this->symbols['X'];
        $this->symbols['O_won'] = $this->symbols['O'];

        $this->symbols['X_lost'] = Emoji::heavyMultiplicationX();
        $this->symbols['O_lost'] = Emoji::radioButton();
    }

    /**
     * Game handler
     *
     * @return \Longman\TelegramBot\Entities\ServerResponse|mixed
     */
    protected function gameAction()
    {
        $data = &$this->data['data'];

        $this->defineSymbols();

        $callbackquery_data = $this->manager->getUpdate()->getCallbackQuery()->getData();
        $callbackquery_data = explode(';', $callbackquery_data);

        $command = $callbackquery_data[1];

        if (isset($callbackquery_data[2])) {
            $args = explode('-', $callbackquery_data[2]);
        }

        if ($command == 'start') {
            if (isset($data['settings']) && $data['settings']['X'] == 'host') {
                $data['settings']['X'] = 'guest';
                $data['settings']['O'] = 'host';
            } else {
                $data['settings']['X'] = 'host';
                $data['settings']['O'] = 'guest';
            }

            $data['current_turn'] = 'X';
            $data['board'] = [
                ['', '', ''],
                ['', '', ''],
                ['', '', '']
            ];

            DebugLog::log('Game initialization');
        } elseif (!isset($args)) {
            DebugLog::log('No move data received!');
        }

        if (empty($data)) {
            return false;
        }

        if (isset($data['current_turn']) && $data['current_turn'] == 'E') {
            return $this->answerCallbackQuery(__("This game has ended!"), true);
        }

        if ($this->getCurrentUserId()!== $this->getUserId($data['settings'][$data['current_turn']])) {
            return $this->answerCallbackQuery(__("It's not your turn!"), true);
        }

        if (isset($args) && isset($data['board'][$args[0]][$args[1]]) && $data['board'][$args[0]][$args[1]] !== '') {
            return $this->answerCallbackQuery(__("Invalid move!"), true);
        }

        $this->max_y = count($data['board']);
        $this->max_x = count($data['board'][0]);

        if (isset($args)) {
            if ($data['current_turn'] == 'X') {
                $data['board'][$args[0]][$args[1]] = 'X';
                $data['current_turn'] = 'O';
            } elseif ($data['current_turn'] == 'O') {
                $data['board'][$args[0]][$args[1]] = 'O';
                $data['current_turn'] = 'X';
            } else {
                DebugLog::log('Invalid move data: ' . ($args[0]) . ' - ' . ($args[1]));
                return $this->answerCallbackQuery(__("Invalid move!"), true);
            }

            DebugLog::log($data['current_turn'] . ' placed at ' . ($args[1]) . ' - ' . ($args[0]));
        }

        $isOver = $this->isGameOver($data['board']);
        $gameOutput = '';

        if (!empty($isOver) && in_array($isOver, ['X', 'O'])) {
            $gameOutput = '<b>' . __("{PLAYER} won!", ['{PLAYER}' => '</b>' . $this->getUserMention($data['settings'][$isOver]) . '<b>']) . '</b>';
        } elseif ($isOver == 'T') {
            $gameOutput = '<b>' . __("Game ended with a draw!") . '</b>';
        }

        if (!empty($isOver) && in_array($isOver, ['X', 'O', 'T'])) {
            $data['current_turn'] = 'E';
        } else {
            $gameOutput = __("Current turn:") . ' ' . $this->symbols[$data['current_turn']];
        }

        if ($this->manager->setData($this->data)) {
            return $this->editMessage(
                $this->getUserMention('host') . ' (' . (($data['settings']['X'] == 'host') ? $this->symbols['X'] : $this->symbols['O']) . ')' . ' ' . __("vs.") . ' ' . $this->getUserMention('guest') . ' (' . (($data['settings']['O'] == 'guest') ? $this->symbols['O'] : $this->symbols['X']) . ')' . PHP_EOL . PHP_EOL . $gameOutput,
                $this->gameKeyboard($data['board'], $isOver)
            );
        }
    }

    /**
     * Check whenever game is over
     *
     * @param $board
     *
     * @return string
     */
    private function isGameOver(&$board)
    {
        $empty = 0;
        for ($x = 0; $x < $this->max_x; $x++) {
            for ($y = 0; $y < $this->max_y; $y++) {
                if ($board[$x][$y] == '') {
                    $empty++;
                }

                if (isset($board[$x][$y]) && isset($board[$x][$y + 1]) && isset($board[$x][$y + 2])) {
                    if ($board[$x][$y] != '' && $board[$x][$y] == $board[$x][$y + 1] && $board[$x][$y] == $board[$x][$y + 2]) {
                        $winner = $board[$x][$y];
                        $board[$x][$y + 1] = $board[$x][$y] . '_won';
                        $board[$x][$y + 2] = $board[$x][$y] . '_won';
                        $board[$x][$y] = $board[$x][$y] . '_won';

                        return $winner;
                    }
                }

                if (isset($board[$x][$y]) && isset($board[$x + 1][$y]) && isset($board[$x + 2][$y])) {
                    if ($board[$x][$y] != '' && $board[$x][$y] == $board[$x + 1][$y] && $board[$x][$y] == $board[$x + 2][$y]) {
                        $winner = $board[$x][$y];

                        $board[$x + 1][$y] = $board[$x][$y] . '_won';
                        $board[$x + 2][$y] = $board[$x][$y] . '_won';
                        $board[$x][$y] = $board[$x][$y] . '_won';

                        return $winner;
                    }
                }

                if (isset($board[$x][$y]) && isset($board[$x + 1][$y + 1]) && isset($board[$x + 2][$y + 2])) {
                    if ($board[$x][$y] != '' && $board[$x][$y] == $board[$x + 1][$y + 1] && $board[$x][$y] == $board[$x + 2][$y + 2]) {
                        $winner = $board[$x][$y];

                        $board[$x + 1][$y + 1] = $board[$x][$y] . '_won';
                        $board[$x + 2][$y + 2] = $board[$x][$y] . '_won';
                        $board[$x][$y] = $board[$x][$y] . '_won';

                        return $winner;
                    }
                }

                if (isset($board[$x][$y]) && isset($board[$x - 1][$y + 1]) && isset($board[$x - 2][$y + 2])) {
                    if ($board[$x][$y] != '' && $board[$x][$y] == $board[$x - 1][$y + 1] && $board[$x][$y] == $board[$x - 2][$y + 2]) {
                        $winner = $board[$x][$y];
                        $board[$x - 1][$y + 1] = $board[$x][$y] . '_won';
                        $board[$x - 2][$y + 2] = $board[$x][$y] . '_won';
                        $board[$x][$y] = $board[$x][$y] . '_won';

                        return $winner;
                    }
                }
            }
        }

        if ($empty == 0) {
            return 'T';
        }
    }
}
