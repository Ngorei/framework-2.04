<?php
namespace app;

class NgoreiLink {
    // Konstanta untuk URL processing
    private const JAVASCRIPT_VOID = 'javascript:void(0);';
    private const EXTERNAL_URL_PATTERN = '/^(https?:\/\/|javascript:)/i';

    /**
     * Parse URL navigasi dalam template
     * @param string &$content Konten yang akan diparse
     */
    public function processLinks(string &$content): void {
        // Pattern untuk atribut khusus (onPress/onRoute/onModal) dengan encode opsional
        $specialAttrPattern = '/<a([^>]*?)(onPress|onRoute|onPage|singlePage|onModal)=(["\'])(.*?)\3([^>]*?)>/is';
        
        $content = preg_replace_callback($specialAttrPattern, function($matches) {
            [, $beforeAttr, $funcName, $quote, $params, $afterAttr] = $matches;
            
            // Cek apakah ada atribut encode
            $hasEncode = preg_match('/\bencode=["\']true["\']/', $beforeAttr . $afterAttr);
            
            // Bersihkan dan gabungkan atribut
            $attrs = $this->cleanAndMergeAttributes($beforeAttr, $afterAttr, $funcName);
            
            // Hapus atribut encode jika ada
            $attrs = preg_replace('/\s*encode=["\']true["\']/', '', $attrs);
            
            // Tambahkan href javascript:void(0) jika belum ada
            $attrs = $this->ensureJavascriptVoid($attrs);
            
            // Format parameter sesuai tipenya dan encode jika diperlukan
            $formattedParams = $this->formatParameters($params, $hasEncode);
            
            // Tambahkan onclick handler
            $attrs = $this->addOnClickHandler($attrs, $funcName, $formattedParams);
            
            return "<a{$attrs}>";
        }, $content);
        
        // Proses href normal
        $normalHrefPattern = '/<a\s+([^>]*?)href=(["\'])((?!javascript:void\(0\))[^"\']*)\2([^>]*?)>/i';
        $content = preg_replace_callback($normalHrefPattern, function($matches) {
            [, $attributes, $quote, $url, $endAttributes] = $matches;
            
            // Skip URL eksternal atau javascript
            if (preg_match(self::EXTERNAL_URL_PATTERN, $url)) {
                return "<a {$attributes}href={$quote}{$url}{$quote}{$endAttributes}>";
            }
            
            // Normalisasi dan lengkapi URL internal
            $fullUrl = $this->buildInternalUrl($url);
            
            return "<a {$attributes}href={$quote}{$fullUrl}{$quote}{$endAttributes}>";
        }, $content);
    }

    /**
     * Membersihkan dan menggabungkan atribut
     */
    private function cleanAndMergeAttributes(string $before, string $after, string $funcName): string {
        $attrs = $before . $after;
        // Hapus atribut duplikat jika ada
        return preg_replace('/' . preg_quote($funcName, '/') . '=["\'][^"\']*["\']/', '', $attrs);
    }

    /**
     * Memastikan href javascript:void(0) ada
     */
    private function ensureJavascriptVoid(string $attrs): string {
        if (!strpos($attrs, 'href="' . self::JAVASCRIPT_VOID . '"')) {
            $attrs .= ' href="' . self::JAVASCRIPT_VOID . '"';
        }
        return $attrs;
    }

    /**
     * Format parameter sesuai tipenya
     * @param string $params Parameter yang akan diformat
     * @param bool $encode Apakah perlu diencode ke base64
     * @return string Parameter yang telah diformat
     */
    private function formatParameters(string $params, bool $encode = false): string {
        // Deteksi dan format JSON
        if (preg_match('/^\s*{.*}\s*$/s', $params)) {
            if ($encode) {
                // Encode JSON ke base64
                return "'" . base64_encode(trim($params)) . "'";
            }
            return trim($params);
        }
        
        // Format string biasa
        if ($encode) {
            return "'" . base64_encode($params) . "'";
        }
        return '"' . addslashes($params) . '"';
    }

    /**
     * Menambahkan onclick handler
     */
    private function addOnClickHandler(string $attrs, string $funcName, string $params): string {
        if (!strpos($attrs, 'onClick="')) {
            $attrs .= sprintf(' onClick="%s(%s);"', $funcName, $params);
        }
        return $attrs;
    }

    /**
     * Membangun URL internal lengkap
     */
    private function buildInternalUrl(string $url): string {
        // Pisahkan URL dan fragment (bagian setelah #)
        $parts = explode('#', $url);
        $baseUrl = $parts[0];
        $fragment = isset($parts[1]) ? '#' . $parts[1] : '';
        
        // Jika URL kosong dan hanya ada fragment, gunakan URL saat ini
        if (empty($baseUrl) && !empty($fragment)) {
            // Ambil URL saat ini dari $_GET['url'] atau path lain
            $currentUrl = isset($_GET['url']) ? $_GET['url'] : '';
            return rtrim(HOST, '/') . '/' . ltrim($currentUrl, '/') . $fragment;
        }
        // Gabungkan base URL dengan fragment
        return rtrim(HOST, '/') . '/' . ltrim($baseUrl, '/') . $fragment;
    }
}
?> 