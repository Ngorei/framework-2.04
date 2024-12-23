<?php
namespace app;
use Exception;
use Psr\Log\LoggerInterface;

/**
 * Class NgoreiDetect
 * Kelas untuk mendeteksi perangkat, browser, dan informasi client lainnya
 * @package app
 */
class NgoreiDetect {
    // Menambahkan konstanta baru
    private const STATUS_MOBILE = 'mobile';
    private const STATUS_TABLET = 'tablet';
    private const STATUS_THEME = 'desktop';
    private const OS_UNKNOWN = 'unknown';
    
    private const CACHE_DURATION = 3600;
    private static $cache = [];
    private static $logger;

    /**
     * Set logger untuk debugging
     * @param LoggerInterface $logger
     */
    public static function setLogger(LoggerInterface $logger) {
        self::$logger = $logger;
    }

    /**
     * Deteksi tipe perangkat dan return status yang sesuai
     * @return string
     */
    public static function deviceType(): string {
        try {
            $cacheKey = $_SERVER['HTTP_USER_AGENT'] ?? 'default';
            
            // Cek cache
            if (isset(self::$cache[$cacheKey]) && (time() - self::$cache[$cacheKey]['time'] < self::CACHE_DURATION)) {
                return self::$cache[$cacheKey]['status'];
            }

            $userAgent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
            $userBrowser = $_SERVER['HTTP_ACCEPT'] ?? '';
            
            // Deteksi perangkat
            if(self::isTabletDevice($userAgent)) {
                $status = self::STATUS_TABLET;
            } elseif(self::isMobileDevice($userAgent, $userBrowser)) {
                $status = self::STATUS_MOBILE;
            } else {
                $status = self::STATUS_THEME;
            }

            // Simpan ke cache
            self::$cache[$cacheKey] = [
                'status' => $status,
                'time' => time()
            ];

            self::log("Device detected: {$status} for UA: {$userAgent}");
            return $status;

        } catch (Exception $e) {
            self::log("Error in deviceType: " . $e->getMessage(), 'error');
            return self::STATUS_THEME; // default fallback
        }
    }

    /**
     * Deteksi browser pengguna
     * @return string
     */
    public static function detectBrowser(): string {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $browsers = [
            'Chrome' => '/chrome/i',
            'Firefox' => '/firefox/i',
            'Safari' => '/safari/i',
            'Edge' => '/edge/i',
            'Opera' => '/opera|OPR/i',
            'IE' => '/MSIE|Trident/i'
        ];

        foreach ($browsers as $browser => $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return $browser;
            }
        }

        return 'Unknown Browser';
    }

    /**
     * Cek apakah perangkat mobile
     */
    private static function isMobileDevice(string $userAgent, string $userBrowser): bool {
        $mobileIdentifiers = [
            'ipod', 'iphone', 'android', 'iemobile', 
            'blackberry', 'webos', 'opera mini'
        ];
        
        foreach($mobileIdentifiers as $identifier) {
            if(stripos($userAgent, $identifier) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Cek apakah perangkat tablet
     */
    private static function isTabletDevice(string $userAgent): bool {
        $tabletIdentifiers = [
            'ipad', 'android(?!.*mobile)', 'tablet'
        ];
        
        foreach($tabletIdentifiers as $identifier) {
            if(preg_match('/' . $identifier . '/i', $userAgent)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Log message untuk debugging
     */
    private static function log(string $message, string $level = 'info'): void {
        if (self::$logger) {
            self::$logger->$level($message);
        }
    }

    /**
     * Deteksi sistem operasi pengguna
     * @return string
     */
    public static function detectOS(): string {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $os_array = [
            'Windows' => '/windows|win32|win64/i',
            'Mac OS X' => '/macintosh|mac os x/i',
            'Linux' => '/linux/i',
            'Ubuntu' => '/ubuntu/i',
            'iPhone' => '/iphone/i',
            'iPad' => '/ipad/i',
            'Android' => '/android/i'
        ];
        
        foreach($os_array as $os => $pattern) {
            if(preg_match($pattern, $userAgent)) {
                return $os;
            }
        }
        
        return self::OS_UNKNOWN;
    }

    /**
     * Deteksi apakah koneksi menggunakan HTTPS
     * @return bool
     */
    public static function isSecureConnection(): bool {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    }

    /**
     * Dapatkan informasi bahasa browser pengguna
     * @return array
     */
    public static function getBrowserLanguages(): array {
        $languages = [];
        
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $langs = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
            
            foreach ($langs as $lang) {
                $lang = substr($lang, 0, 2);
                if (!in_array($lang, $languages)) {
                    $languages[] = $lang;
                }
            }
        }
        
        return $languages;
    }

    /**
     * Deteksi apakah request dari bot/crawler
     * @return bool
     */
    public static function isBot(): bool {
        $userAgent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
        
        $bots = [
            'googlebot', 'bingbot', 'slurp', 'duckduckbot', 'baiduspider',
            'yandexbot', 'facebookexternalhit', 'twitterbot', 'rogerbot',
            'linkedinbot', 'embedly', 'quora link preview', 'showyoubot',
            'outbrain', 'pinterest', 'slackbot', 'vkShare', 'W3C_Validator'
        ];
        
        foreach ($bots as $bot) {
            if (stripos($userAgent, $bot) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Dapatkan informasi lengkap tentang client
     * @return array
     */
    public static function getClientInfo(): array {
        $baseInfo = [
            'device_type' => self::deviceType(),
            'browser' => self::detectBrowser(),
            'operating_system' => self::detectOS(),
            'is_secure' => self::isSecureConnection(),
            'languages' => self::getBrowserLanguages(),
            'is_bot' => self::isBot(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'connection_speed' => self::getConnectionSpeed(),
            'color_scheme' => self::getColorSchemePreference(),
            'screen_resolution' => self::getScreenResolution(),
            'data_saver' => self::isDataSaverEnabled(),
            'supports_pwa' => self::supportsPWA(),
            'has_javascript' => self::hasJavaScript(),
            'geolocation' => self::getGeoLocation(),
            'is_touch_device' => self::isTouchDevice(),
            'headers' => self::getAllHeaders()
        ];

        return array_filter($baseInfo, function($value) {
            return $value !== null && $value !== '';
        });
    }

    /**
     * Cek apakah perangkat mendukung touch events
     * @return bool
     */
    public static function isTouchDevice(): bool {
        $userAgent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
        return (
            stripos($userAgent, 'touch') !== false ||
            self::deviceType() === self::STATUS_MOBILE ||
            self::deviceType() === self::STATUS_TABLET
        );
    }

    /**
     * Deteksi kecepatan koneksi pengguna
     * @return string
     */
    public static function getConnectionSpeed(): string {
        $connection = $_SERVER['HTTP_CLIENT_HINTS'] ?? '';
        
        if (strpos($connection, '4g') !== false) {
            return '4G';
        } elseif (strpos($connection, '3g') !== false) {
            return '3G';
        } elseif (strpos($connection, '2g') !== false) {
            return '2G';
        } elseif (strpos($connection, 'slow-2g') !== false) {
            return 'Slow-2G';
        }
        
        return 'Unknown';
    }

    /**
     * Deteksi preferensi tema gelap/terang
     * @return string
     */
    public static function getColorSchemePreference(): string {
        if (isset($_SERVER['HTTP_SEC_CH_PREFERS_COLOR_SCHEME'])) {
            return $_SERVER['HTTP_SEC_CH_PREFERS_COLOR_SCHEME'];
        }
        return 'light'; // default
    }

    /**
     * Deteksi resolusi layar (jika tersedia)
     * @return array
     */
    public static function getScreenResolution(): array {
        $width = $_SERVER['HTTP_SEC_CH_WIDTH'] ?? 0;
        $height = $_SERVER['HTTP_SEC_CH_HEIGHT'] ?? 0;
        
        return [
            'width' => (int) $width,
            'height' => (int) $height
        ];
    }

    /**
     * Deteksi apakah pengguna menggunakan mode data saver
     * @return bool
     */
    public static function isDataSaverEnabled(): bool {
        return (isset($_SERVER['HTTP_SAVE_DATA']) && 
                strtolower($_SERVER['HTTP_SAVE_DATA']) === 'on');
    }

    /**
     * Deteksi apakah JavaScript diaktifkan
     * @return bool
     */
    public static function hasJavaScript(): bool {
        // Ini perlu implementasi di sisi client dengan cookie atau session
        return isset($_COOKIE['js_enabled']) || isset($_SESSION['js_enabled']);
    }

    /**
     * Deteksi apakah perangkat mendukung PWA
     * @return bool
     */
    public static function supportsPWA(): bool {
        $userAgent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
        $browser = self::detectBrowser();
        
        return (
            $browser === 'Chrome' || 
            $browser === 'Firefox' || 
            $browser === 'Safari' ||
            stripos($userAgent, 'mobile') !== false
        );
    }

    /**
     * Dapatkan informasi geolokasi berdasarkan IP
     * @return array
     */
    public static function getGeoLocation(): array {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if (function_exists('geoip_record_by_name')) {
            $geo = @geoip_record_by_name($ip);
            if ($geo) {
                return [
                    'country' => $geo['country_name'] ?? 'Unknown',
                    'city' => $geo['city'] ?? 'Unknown',
                    'latitude' => $geo['latitude'] ?? 0,
                    'longitude' => $geo['longitude'] ?? 0
                ];
            }
        }
        return ['country' => 'Unknown', 'city' => 'Unknown', 'latitude' => 0, 'longitude' => 0];
    }

    /**
     * Dapatkan semua header yang dikirim oleh client
     * @return array
     */
    public static function getAllHeaders(): array {
        return getallheaders() ?: [];
    }

    /**
     * Mengumpulkan semua informasi device dalam bentuk array terstruktur
     * @return array
     */
    public static function getAllDeviceInfo(): array {
        return [
            'device' => [
                'type' => self::deviceType(),
                'os' => self::detectOS(),
                'is_touch' => self::isTouchDevice(),
                'screen' => self::getScreenResolution(),
                'connection' => [
                    'type' => self::getConnectionSpeed(),
                    'is_secure' => self::isSecureConnection(),
                    'data_saver' => self::isDataSaverEnabled()
                ]
            ],
            'browser' => [
                'name' => self::detectBrowser(),
                'languages' => self::getBrowserLanguages(),
                'javascript_enabled' => self::hasJavaScript(),
                'color_scheme' => self::getColorSchemePreference(),
                'supports_pwa' => self::supportsPWA()
            ],
            'client' => [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'is_bot' => self::isBot(),
                'headers' => self::getAllHeaders()
            ],
            'location' => self::getGeoLocation(),
            'timestamps' => [
                'detected_at' => date('Y-m-d H:i:s'),
                'timezone' => date_default_timezone_get()
            ],
            'meta' => [
                'cache_duration' => self::CACHE_DURATION,
                'detection_version' => '1.0'
            ]
        ];
    }

    /**
     * Mengekspor data device dalam format JSON
     * @return string
     */
    public static function exportToJson(): string {
        return json_encode(self::getAllDeviceInfo(), JSON_PRETTY_PRINT);
    }

    /**
     * Menyimpan informasi device ke file
     * @param string $filepath
     * @return bool
     */
    public static function saveToFile(string $filepath): bool {
        try {
            $data = self::getAllDeviceInfo();
            $json = json_encode($data, JSON_PRETTY_PRINT);
            return file_put_contents($filepath, $json) !== false;
        } catch (Exception $e) {
            self::log("Error saving device info: " . $e->getMessage(), 'error');
            return false;
        }
    }
}

?>

 