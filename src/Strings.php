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

namespace Longman\TelegramBot;

class Strings
{
    public static $multi_language_enabled = true;
    public static $numPublicLanguages;
    public static $defaultstrings = [];

    public static function load($game, $language = null)
    {
        $strings = [];
        $translation = [];

        if (empty(self::$defaultstrings)) {
            if (file_exists(__DIR__ . '/Language/english.php')) {
                include(__DIR__ . '/Language/english.php');

                foreach ($translation as $string_name => $string_text) {
                    $strings[$string_name] = $string_text;
                }
            }
            self::$defaultstrings = $strings;
        } else {
            $strings = self::$defaultstrings;
        }

        if (file_exists(__DIR__ . '/Language/english_' . strtolower($game) . '.php')) {
            include(__DIR__ . '/Language/english_' . strtolower($game) . '.php');

            foreach($translation as $string_name => $string_text) {
                $strings[$string_name] = $string_text;
            }
        }

        if (!empty($language) && $language != 'english' && file_exists(__DIR__ . '/Language/' . strtolower($language) . '.php')) {
            include(__DIR__ . '/Language/' . strtolower($language) . '.php');

            foreach ($translation as $string_name => $string_text) {
                $strings[$string_name] = $string_text;
            }

            if (!empty($game) && file_exists(__DIR__ . '/Language/' . strtolower($language) . '_' . strtolower($game) . '.php')) {
                include(__DIR__ . '/Language/' . strtolower($language) . '_' . strtolower($game) . '.php');

                foreach($translation as $string_name => $string_text) {
                    $strings[$string_name] = $string_text;
                }
            }
        }

        if (!empty($language) && $language != 'english' && file_exists(__DIR__ . '/Language_Test/' . strtolower($language) . '.php')) {
            include(__DIR__ . '/Language_Test/' . strtolower($language) . '.php');

            foreach ($translation as $string_name => $string_text) {
                $strings[$string_name] = $string_text;
            }

            if (!empty($game) && file_exists(__DIR__ . '/Language_Test/' . strtolower($language) . '_' . strtolower($game) . '.php')) {
                include(__DIR__ . '/Language_Test/' . strtolower($language) . '_' . strtolower($game) . '.php');

                foreach($translation as $string_name => $string_text) {
                    $strings[$string_name] = $string_text;
                }
            }
        }

        return $strings;
    }

    public static function rotate($savedlanguage, $isAdmin = null)
    {
        if (empty($savedlanguage)) {
            $savedlanguage = 'english';
        }

        $files = array();
        if (is_dir(__DIR__ . '/Language/') && $handle = opendir(__DIR__ . '/Language/')) {
            while (false !== ($file = readdir($handle))) {
                if ($file != "." && $file != ".." && strpos($file, '.php') && !strpos($file, '_')) {
                    array_push($files, $file);
                }
            }
            closedir($handle);

            if ($isAdmin) {
                if (is_dir(__DIR__ . '/Language_Test/') && $handle = opendir(__DIR__ . '/Language_Test/')) {
                    while (false !== ($file = readdir($handle))) {
                        if ($file != "." && $file != ".." && strpos($file, '.php') && !strpos($file, '_')) {
                            array_push($files, $file);
                        }
                    }
                    closedir($handle);
                }
            }

            sort($files);

            $picknext = false;
            for ($i = 0; $i < count($files); $i++) {
                $file = explode('.', $files[$i]);
                $language = $file[0];

                if (!$picknext && $language == $savedlanguage) {
                    $picknext = $language;

                    if ($i >= count($files) - 1) {
                        $i = -1;
                    }
                } elseif ($picknext && $picknext != $language) {
                    break;
                }
            }
        }

        return $language;
    }

    public static function getLanguagesCount($isAdmin = null)
    {
        if (!self::$multi_language_enabled) {
            return 1;
        }

        if (!self::$numPublicLanguages) {
            $languages = 0;
            if (is_dir(__DIR__ . '/Language/') && $handle = opendir(__DIR__ . '/Language/')) {
                while (false !== ($file = readdir($handle))) {
                    if ($file != "." && $file != ".." && strpos($file, '.php') && !strpos($file, '_')) {
                        $languages++;
                    }
                }
                closedir($handle);

                if ($isAdmin) {
                    if (is_dir(__DIR__ . '/Language_Test/') && $handle = opendir(__DIR__ . '/Language_Test/')) {
                        while (false !== ($file = readdir($handle))) {
                            if ($file != "." && $file != ".." && strpos($file, '.php') && !strpos($file, '_')) {
                                $languages++;
                            }
                        }
                        closedir($handle);
                    }
                }

                self::$numPublicLanguages = $languages;
            }
        }

        return self::$numPublicLanguages;
    }
}
