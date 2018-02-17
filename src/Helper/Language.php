<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace jacklul\inlinegamesbot\Helper;

use Gettext\Translations;
use Gettext\Translator;

/**
 * Class Language
 *
 * Simple localization class
 *
 * @package jacklul\inlinegamesbot\Helper
 */
class Language
{
    /**
     * Default strings language
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
    public static function set(string $language = ''): void
    {
        $t = new Translator();

        if (file_exists(APP_PATH . '/language/messages.' . $language . '.po')) {
            if (!file_exists(DATA_PATH . '/language/messages.' . $language . '.cache') || md5_file(APP_PATH . '/language/messages.' . $language . '.po') != file_get_contents(DATA_PATH . '/language/messages.' . $language . '.cache')) {
                self::compileToArray($language);
            }

            $t->loadTranslations(DATA_PATH . '/language/messages.' . $language . '.php');

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
     * @param string $language
     */
    private static function compileToArray(string $language): void
    {
        if (!file_exists(DATA_PATH . '/language/messages.' . $language . '/.php')) {
            $translation = Translations::fromPoFile(APP_PATH . '/language/messages.' . $language . '.po');

            if (!is_dir(DATA_PATH . '/language/')) {
                mkdir(DATA_PATH . '/language/', 0755, true);
            }

            $translation->toPhpArrayFile(DATA_PATH . '/language/messages.' . $language . '.php');
            file_put_contents(DATA_PATH . '/language/messages.' . $language . '.cache', md5_file(APP_PATH . '/language/messages.' . $language . '.po'));
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
    public static function setDefaultLanguage(string $default_language): void
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
