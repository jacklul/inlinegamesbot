<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2017 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Bot\Helper;

use Gettext\Translations;
use Gettext\Translator;

/**
 * Class Language
 *
 * @package Bot\Helper
 */
class Language
{
    /**
     * Default language
     *
     * @var string
     */
    private static $default_language = 'en';

    /**
     * Current language
     *
     * @var string
     */
    private static $current_language = '';

    /**
     * Set the language and load translation
     *
     * @param string $language
     */
    public static function set($language = ''): void
    {
        $t = new Translator();

        if (file_exists(APP_PATH . '/language/messages.' . $language . '.po')) {
            if (!file_exists(VAR_PATH . '/language/messages.' . $language . '.cache') || md5_file(APP_PATH . '/language/messages.' . $language . '.po') != file_get_contents(VAR_PATH . '/language/messages.' . $language . '.cache')) {
                self::compileToArray($language);
            }

            $t->loadTranslations(VAR_PATH . '/language/messages.' . $language . '.php');

            self::$current_language = $language;
        } else {
            self::$current_language = self::$default_language;
        }

        $t->register();
    }

    /**
     * Return language list
     *
     * @return array
     */
    public static function list(): array
    {
        $languages = [self::$default_language];

        if (is_dir(APP_PATH . '/language')) {
            foreach (new \DirectoryIterator(APP_PATH . '/language') as $fileInfo) {
                if (!$fileInfo->isDir() && !$fileInfo->isDot()) {
                    $language = explode('.', $fileInfo->getFilename());

                    if ($language[1] != self::getDefaultlanguage() && isset($language[2]) && $language[2] == 'po') {
                        $languages[] = $language[1];
                    }
                }
            }
        }

        return $languages;
    }

    /**
     * Compile .po file into .php array file
     *
     * @param $language
     */
    private static function compileToArray($language)
    {
        if (!file_exists(VAR_PATH . '/language/messages.' . $language . '/.php')) {
            $translation = Translations::fromPoFile(APP_PATH . '/language/messages.' . $language . '.po');

            if (!is_dir(VAR_PATH . '/language/')) {
                mkdir(VAR_PATH . '/language/', 0755, true);
            }

            $translation->toPhpArrayFile(VAR_PATH . '/language/messages.' . $language . '.php');
            file_put_contents(VAR_PATH . '/language/messages.' . $language . '.cache', md5_file(APP_PATH . '/language/messages.' . $language . '.po'));
        }
    }

    /**
     * Get default language
     *
     * @return string
     */
    public static function getDefaultLanguage(): string
    {
        return self::$default_language;
    }

    /**
     * Set default language
     *
     * @param string $default_language
     */
    public static function setDefaultLanguage(string $default_language)
    {
        self::$default_language = $default_language;
        self::set($default_language);
    }

    /**
     * Get current language
     *
     * @return string
     */
    public static function getCurrentLanguage(): string
    {
        return self::$current_language ?: self::$default_language;
    }
}
