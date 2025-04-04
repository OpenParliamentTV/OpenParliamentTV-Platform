<?php

require_once(__DIR__."/../../i18n.class.php");
require_once(__DIR__."/../../config.php");

class LanguageManager {
    private static $instance = null;
    private $i18n = null;
    private $initialized = false;
    private $currentLang = null;
    private $langJSONString = null;
    private $projectRoot = null;

    private function __construct() {
        // Get the project root directory (2 levels up from this file)
        $this->projectRoot = dirname(dirname(__DIR__));
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init($customConfig = null) {
        if ($this->initialized) {
            return;
        }

        global $config, $acceptLang;
        
        // Handle language switching via request
        if (isset($_REQUEST["lang"]) && isset($acceptLang) && array_key_exists($_REQUEST["lang"], $acceptLang)) {
            $_SESSION["lang"] = $_REQUEST["lang"];
            // Force the language to be the one requested
            $this->currentLang = $_REQUEST["lang"];
        }
        
        // Default configuration using absolute paths
        $defaultConfig = [
            'filePath' => $this->projectRoot . '/lang/lang_{LANGUAGE}.json',
            'cachePath' => $this->projectRoot . '/langcache/',
            'fallbackLang' => 'de'
        ];

        // Merge with provided config
        $i18nConfig = $customConfig ? array_merge($defaultConfig, $customConfig) : $defaultConfig;

        // Initialize i18n
        $this->i18n = new i18n(
            $i18nConfig['filePath'],
            $i18nConfig['cachePath'],
            $i18nConfig['fallbackLang']
        );
        $this->i18n->init();

        // Determine current language if not already set
        if ($this->currentLang === null) {
            $userLang = $this->i18n->getUserLangs();
            $langIntersection = array_values(array_intersect($userLang, array_keys($acceptLang ?? ['de' => true])));
            $this->currentLang = (count($langIntersection) > 0) ? $langIntersection[0] : 'de';
        }

        // Load language JSON for JavaScript using absolute path
        $langFile = $this->projectRoot . '/lang/lang_' . $this->currentLang . '.json';
        if (file_exists($langFile)) {
            $this->langJSONString = file_get_contents($langFile);
        } else {
            $this->langJSONString = '{}';
        }

        $this->initialized = true;
    }

    public function getCurrentLang() {
        return $this->currentLang;
    }

    public function getUserLangs() {
        return $this->i18n ? $this->i18n->getUserLangs() : [];
    }

    public function getLangJSONString() {
        return $this->langJSONString;
    }

    public function switchLanguage($lang) {
        global $acceptLang;
        
        if (isset($acceptLang) && array_key_exists($lang, $acceptLang)) {
            $_SESSION["lang"] = $lang;
            $this->currentLang = $lang;
            return true;
        }
        return false;
    }
}

// Initialize language if L function doesn't exist
if (!function_exists("L")) {
    LanguageManager::getInstance()->init();
} 