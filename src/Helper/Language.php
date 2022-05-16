<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2016-2022 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bot\Helper;

use DirectoryIterator;
use Gettext\Translations;
use Gettext\Translator;
use RuntimeException;

/**
 * Simple localization class
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
     * Return language list
     *
     * @return array
     */
    public static function list(): array
    {
        $languages = [self::$default_language];

        if (is_dir(ROOT_PATH . '/language')) {
            foreach (new DirectoryIterator(ROOT_PATH . '/language') as $fileInfo) {
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
     * Get default language
     *
     * @return string
     */
    public static function getDefaultLanguage(): string
    {
        if (!empty($default_language = getenv('DEFAULT_LANGUAGE'))) {
            return $default_language;
        }

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
     * Set the language and load translation
     *
     * @param string $language
     */
    public static function set(string $language = ''): void
    {
        $t = new Translator();

        if (file_exists(ROOT_PATH . '/translations/messages.' . $language . '.po')) {
            if (defined('DATA_PATH')) {
                if (!file_exists(ROOT_PATH . '/translations/messages.' . $language . '.cache') || md5_file(ROOT_PATH . '/translations/messages.' . $language . '.po') != file_get_contents(DATA_PATH . '/translations/messages.' . $language . '.cache')) {
                    if (!self::compileToArray($language)) {
                        Utilities::debugPrint('Language compilation to PHP array failed!');
                    }
                }

                if (file_exists(DATA_PATH . '/translations/messages.' . $language . '.php')) {
                    $t->loadTranslations(DATA_PATH . '/translations/messages.' . $language . '.php');
                }
            } else {
                $translations = Translations::fromPoFile(ROOT_PATH . '/translations/messages.' . $language . '.po');
                $t->loadTranslations($translations);
            }

            self::$current_language = $language;
        } else {
            self::$current_language = self::$default_language;
        }

        $t->register();
    }

    /**
     * Compile .po file into .php array file
     *
     * @param string $language
     *
     * @return bool
     */
    private static function compileToArray(string $language): bool
    {
        if (defined('DATA_PATH') && !file_exists(ROOT_PATH . '/translations/messages.' . $language . '.php')) {
            $translation = Translations::fromPoFile(ROOT_PATH . '/translations/messages.' . $language . '.po');

            if (!is_dir(DATA_PATH . '/translations/') && !mkdir($concurrentDirectory = DATA_PATH . '/translations/', 0755, true) && !is_dir($concurrentDirectory)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }

            $translation->toPhpArrayFile(DATA_PATH . '/translations/messages.' . $language . '.php');

            return file_put_contents(DATA_PATH . '/translations/messages.' . $language . '.cache', md5_file(ROOT_PATH . '/translations/messages.' . $language . '.po'));
        }

        return false;
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
