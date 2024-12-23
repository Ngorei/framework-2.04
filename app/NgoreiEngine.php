<?php
namespace app;
use Exception;
use app\tatiye;
use PDO; // Tambahkan ini untuk menggunakan PDO
class NgoreiEngine {
  private $_tpldata = [];
  private $_section = [];
  private $files = [];
   
  private $showLanguageIndex = false;
  private $root;
  private $languageData;
  private $specialVariables = [];
  private $includeTemplates = [];
  private $templateRoutingTemplates = [];
  private $filters = [];
    
  // Tambahkan di bagian atas class setelah property
  private const DEVELOPMENT_MODE = true; // Sesuaikan nilainya
    
  // Tambahkan property untuk performance monitoring
  private static $perfStats = [];

  // Tambahkan method untuk logging performa
  private function logPerf(string $operation, float $start): void {
      self::$perfStats[$operation] = microtime(true) - $start;
  }

  // Method untuk mendapatkan statistik performa
  public function getPerfStats(): array {
      return self::$perfStats;
  }

  /**
   * Konstruktor kelas Ngorei
   * @param string $root Direktori root
   * @param array $languageData Data bahasa
   */
  public function __construct(string $root = "./", array $languageData = []) {
    $this->setRootDir($root);
    $this->languageData = $languageData;
  }
  
  /**
   * Mengatur direktori root
   * @param string $root Direktori root
   */
  private function setRootDir(string $root): void
  {
    $this->root = str_replace('\\', '/', rtrim($root, '/')) . '/';
  }
  
  /**
   * Destruktor kelas Ngorei
   */
  public function __destruct()
  {
    $this->destroy();
  }
  
  /**
   * Mereset daftar file
   */
  public function resetFiles()
  {
    $this->files = [];
  }
  
  /**
   * Menghancurkan objek template
   */
  public function destroy()
  {
    unset($this->_tpldata);
    unset($this->files);
    unset($this->_section);
    unset($this->root, $this->showLanguageIndex, $this->languageData);
  }

  /**
   * Menetapkan atau menambahkan nilai variabel
   * @param string $varname Nama variabel
   * @param mixed $varval Nilai variabel
   * @param bool $append Apakah menambahkan nilai
   * @return bool Berhasil atau tidak
   */
  public function val(string $varname, $varval, bool $append = false): bool
  {
    if ($append && isset($this->_tpldata['.'][$varname])) {
      $this->_tpldata['.'][$varname] .= $varval;
      return true;
    }
    
    $this->_tpldata['.'][$varname] = $varval;
    return true;
  }

  /**
   * Menambahkan array ke blok variabel
   * @param string $varblock Nama blok variabel
   * @param array $vararray Array yang akan ditambahkan
   * @return bool Berhasil atau tidak
   */
  public function TDSnet(string $varblock, array $vararray): bool
  {
    if (!isset($this->_tpldata['.'][$varblock]) || !is_array($this->_tpldata['.'][$varblock])) {
      $this->_tpldata['.'][$varblock] = [];
    }
    $this->_tpldata['.'][$varblock][] = $vararray;
    return true;
  }

  /**
   * Menambahkan file template sementara
   * @param string $varfile Nama file
   * @return bool Berhasil atau tidak
   */
  private function addTempfile(string $varfile): bool
  {
    if (!file_exists($varfile)) {
      die("Parser->add_file(): Couldn't load template file $this->root$varfile");
    }
    $this->files[$varfile] = $varfile;
    return true;
  }
  
  /**
   * Mengubah karakter khusus HTML
   * @param string &$content Konten yang akan diubah
   */
  public function htmlStandard(string &$content): void
  {
    if (!empty($content)) {
      $content = str_replace(array('& ', ' & '), array('&amp; ', ' &amp; '), $content);
    }
  }

  /**
   * Menambahkan variabel khusus baru
   * @param string $key Kunci variabel khusus
   * @param string $value Nilai variabel khusus
   */
  public function addSpecialVariable(string $key, string $value): void
  {
    $this->specialVariables[$key] = rtrim($value, '/');
  }



  /**
   * Mengurai variabel khusus dalam konten
   * @param string &$content Konten yang akan diurai
   */
  private function parseSpecialVariables(string &$content): void
  {
    foreach ($this->specialVariables as $key => $value) {
      $pattern = '/\{' . preg_quote($key, '/') . '\:(.*?)\}/';
      $content = preg_replace_callback($pattern, function($matches) use ($value) {
        return $value . '/' . $matches[1];
      }, $content);
    }
  }

  /**
   * Menambahkan template untuk diinclude
   * @param string $key Kunci template
   * @param string $value Path template
   */
  public function includeTemplate(string $key, string $value): void {
    $cleanPath = str_replace('\\', '/', rtrim($value, '/'));
    $this->includeTemplates[$key] = $cleanPath;
  }

  /**
   * Mengurai dan memproses file template yang diinclude
   * @param string &$content Konten yang akan diurai
   */
  private function parseIncludeTemplates(string &$content): void
  {
    foreach ($this->includeTemplates as $key => $value) {
      $pattern = '/\{' . preg_quote($key, '/') . '\:(.*?)\}/';
      $content = preg_replace_callback($pattern, function($matches) use ($value) {
        $fullPath = $value . '/' . $matches[1];
        if (file_exists($fullPath)) {
          $fileContent = file_get_contents($fullPath);
          // Parse konten file untuk memproses variabel template di dalamnya
          return $this->DomHTML($fileContent, true, true, false);
        }
        return ''; // Return kosong jika file tidak ditemukan
      }, $content);
    }
  }

  /**
   * Mengurai konten template
   * @param string &$content Konten yang akan diurai
   * @param bool $removeVars Apakah menghapus variabel yang tidak digunakan
   * @param bool $return Apakah mengembalikan hasil
   * @param bool $compress Apakah mengompres hasil
   * @return string Hasil penguraian
   */
  public function DomHTML(string &$content, bool $removeVars = true, bool $return = true, bool $compress = false): string
  {
    try {
        // Cek apakah ada request URL di $_SERVER dan set path
        if (isset($_SERVER['REQUEST_URI'])) {
            $this->setPath($_SERVER['REQUEST_URI']);
        }

        // Proses utility classes
        $content = $this->domUtilityClasses($content);
        
        // Proses script imports
        $this->domScriptImports($content);
        
        // Proses assets (termasuk font links)
        $this->domAssets($content);
        
        // Tambahkan parseWidthClasses sebelum parsing lainnya
        $content = $this->parseWidthClasses($content);
        
        // Proses special variables
        $this->parseSpecialVariables($content);
        
        // Proses include templates
        $this->parseIncludeTemplates($content);
        
        // Tambahkan parsing routing templates
        $this->domRoutingTemplates($content);
        
        // Tambahkan pemanggilan domImageSrc
        $this->domImageSrc($content);
        $this->domUrlNavigasi($content);
        
        // Tambahkan pemanggilan domViewTemplate
        $this->domViewTemplate($content);

        // Tambahkan pemanggilan domDirTemplate
        $this->domDirTemplate($content);
        
        // Tambahkan pemanggilan domRoutingDiv
        $this->domRoutingDiv($content);
        
        // Tambahkan pemanggilan domNavigation
        $this->domNavigation($content);
        
        // Tambahkan pemanggilan domQueryData
        $this->domQueryData($content);
        
        // Proses kondisi if-elseif-else
        $this->processAdvancedConditions($content);
        
        // Update regex untuk menangkap filter
        $content = preg_replace_callback(
            "#\{([a-z0-9_.|()]*)\}#i",
            function($matches) use ($removeVars) {
                // Split variable dan filter
                $parts = explode('|', $matches[1]);
                $varName = trim($parts[0]);
                
                // Ambil nilai dasar
                $value = $this->_tpldata['.'][$varName] ?? (!$removeVars ? $matches[0] : '');
                
                // Terapkan filter jika ada
                for ($i = 1; $i < count($parts); $i++) {
                    $filterStr = trim($parts[$i]);
                    // Parse filter dan argumennya
                    if (preg_match('/^([a-z_]+)(?:\((.*?)\))?$/i', $filterStr, $filterMatches)) {
                        $filterName = $filterMatches[1];
                        $arguments = isset($filterMatches[2]) ? 
                            array_map('trim', explode(',', $filterMatches[2])) : 
                            [];
                        $value = $this->applyFilter($value, $filterName, $arguments);
                    }
                }
                
                return $value;
            },
            $content
        );

        // Proses language tags
        $content = preg_replace_callback(
            "#_lang\{(.*)\}#i",
            function($matches) {
                return $this->showLanguageIndex ? 
                    ($this->languageData[$matches[1]] ?? $matches[1]) : 
                    ($this->languageData[$matches[1]] ?? '');
            },
            $content
        );

        if ($removeVars) {
            $content = preg_replace("#\{([a-z0-9_]*)\}#i", '', $content);
        }

        $this->htmlStandard($content);
        $content = trim($content);

        if (!$return) {
            echo $content;
            return '';
        }

        return $content;
        
    } catch (Exception $e) {
        throw new Exception("Error parsing template: " . $e->getMessage());
    }
  }

  /**
   * Mengurai blok dalam template
   * @param string &$content Konten yang akan diurai
   * @param string $blockname Nama blok
   * @param bool $removeVars Apakah menghapus variabel yang tidak digunakan
   * @param bool $return Apakah mengembalikan hasil
   * @return string Hasil penguraian blok
   */
  private function parseBlock(string &$content, string $blockname, bool $removeVars = true, bool $return = true): string
  {
    $matchArray = [];
    preg_match_all("#\{$blockname\.([a-z0-9_]*)\}#i", $content, $matchArray, PREG_SET_ORDER);
    
    $blockLength = count($this->_tpldata['.'][$blockname]);
      
    $res = '';
    for ($i = 0; $i < $blockLength; $i++) {
      $temp = $content;
      foreach ($matchArray as $val) {
        if ($this->_tpldata['.'][$blockname][$i][$val[1]] === true) {
          $this->_tpldata['.'][$blockname][$i][$val[1]] = 'start_loop_section_' . $blockname . '_' . $i;
          eval('global $start_loop_section_' . $blockname . '_' . $i . ';');
          eval('$start_loop_section_' . $blockname . '_' . $i . ' = true;');
        }
        $temp = str_replace(
          $val[0], 
          isset($this->_tpldata['.'][$blockname][$i][$val[1]]) ? 
          trim($this->_tpldata['.'][$blockname][$i][$val[1]]) : '', 
          $temp 
        );
      }
      $res .= $temp;
    }
    $content = $res;
    if ($i > 0) {
      global $$blockname;
      $$blockname = true;
    }
    if (!$return) {
      echo $content;
      return '';
    }

    return $content;
  }

  /**
   * Mengurai file PHP dan mengembalikan hasilnya
   * @param string $varfile Nama file PHP
   * @param bool $removeVars Apakah menghapus variabel yang tidak digunakan
   * @param bool $return Apakah mengembalikan hasil
   * @param bool $compress Apakah mengompres hasil
   * @return string Hasil penguraian
   */
  public function Worker(string $varfile, bool $removeVars = false, bool $return = true, bool $compress = false): string
  {
        $headerContent = '';
        ob_start();
          $assetHeader = tatiye::assets('header');
          $scriptsHeader = '';
         foreach ($assetHeader as $path) {
             // Cek apakah path adalah URL eksternal
             if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
                 // Cek apakah file adalah CSS
                 if (str_ends_with($path, '.css')) {
                     $scriptsHeader .= sprintf('<link rel="stylesheet" href="%s">' . PHP_EOL, $path);
                 } else {
                     $scriptsHeader .= sprintf('<script src="%s"></script>' . PHP_EOL, $path);
                 }
             } else if (strpos($path, 'module|') !== false) {
                 // Handle module script
                 $cleanPath = str_replace('module|', '', $path);
                 $scriptsHeader .= sprintf('<script type="module" src="'.HOST.'/%s"></script>' . PHP_EOL, $cleanPath);
             } else {
                 // Cek apakah file lokal adalah CSS
                 if (str_ends_with($path, '.css')) {
                     $scriptsHeader .= sprintf('<link rel="stylesheet" href="'.HOST.'/%s">' . PHP_EOL, $path);
                 } else {
                     $scriptsHeader .= sprintf('<script src="'.HOST.'/%s"></script>' . PHP_EOL, $path);
                 }
             }
         }
        $headerContent = ob_get_clean();
        // Parse header content
        $headerContent = $this->DomHTML($scriptsHeader, $removeVars, true, $compress).PHP_EOL;

    
    // Load dan render konten utama
    ob_start();
    // Di method yang melakukan require_once, tambahkan pengecekan file
    require_once($varfile);
    $content = ob_get_contents();
    ob_end_clean();
    
    $tempfile = tempnam(sys_get_temp_dir(), $varfile);
    $temphandle = fopen($tempfile, "w");
    fwrite($temphandle, $content);
    fclose($temphandle);
    
    $this->addTempfile($tempfile);
    $content = $this->parseFile($tempfile, $removeVars, true, $compress);
    unlink($tempfile);
    
    // Parse konten utama
    $mainContent = $this->DomHTML($content, $removeVars, true, $compress).PHP_EOL;
    
    // Load dan render footer
    $footerContent = '';
    $asset = tatiye::assets('footer');
    $scripts = '';
    ob_start();
     $footerContent = "\n" . PHP_EOL;
    foreach ($asset as $path) {
        // Cek apakah path adalah URL eksternal
        if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
            $scripts .= sprintf('<script src="%s"></script>' . PHP_EOL, $path);
        } else if (strpos($path, 'module|') !== false) {
            // Handle module script
            $cleanPath = str_replace('module|', '', $path);
            $scripts .= sprintf('<script type="module" src="'.HOST.'/%s"></script>' . PHP_EOL, $cleanPath);
        } else {
            // Handle regular script
            $scripts .= sprintf('<script src="'.HOST.'/%s"></script>' . PHP_EOL, $path);
        }
    }
    $footerContent = ob_get_clean();
    // Parse footer content
    $footerContent = $this->DomHTML($scripts, $removeVars, true, $compress).PHP_EOL;
    // Gabungkan header, konten utama, dan footer
    return $headerContent . $mainContent . $footerContent;
  }

  /**
   * Mengurai file PHP dan mengembalikan hasilnya
   * @param string $varfile Nama file PHP
   * @param bool $removeVars Apakah menghapus variabel yang tidak digunakan
   * @param bool $return Apakah mengembalikan hasil
   * @param bool $compress Apakah mengompres hasil
   * @return string Hasil penguraian
   */
  public function SDK(string $varfile, bool $removeVars = false, bool $return = true, bool $compress = false): string {
    // Cache meta content untuk menghindari load berulang
    static $cachedMetaContent = null;
    
    // Load dan render header terlebih dahulu
    if ($cachedMetaContent === null) {
        $headerFile = ROOT_PUBLIC.'/meta.html';
        if (file_exists($headerFile)) {
            ob_start();
            require_once($headerFile);
            $metaContent = ob_get_contents();
            ob_end_clean();
            // Cache hasil parse
            $cachedMetaContent = $this->DomHTML($metaContent, $removeVars, true, $compress).PHP_EOL;
        } else {
            $cachedMetaContent = '';
        }
    }
    $metaContent = $cachedMetaContent;

    // Load dan render konten utama dengan buffer yang lebih besar
    $bufferSize = 1024 * 512; // 512KB buffer
    ob_start(null, $bufferSize);
    require_once($varfile);
    $content = ob_get_contents();
    ob_end_clean();
    
    // Gunakan memory temp file untuk file besar
    if (strlen($content) > 5242880) { // 5MB threshold
        $tempfile = tempnam(sys_get_temp_dir(), 'sdk_' . md5($varfile));
    } else {
        $tempfile = tempnam(sys_get_temp_dir(), 'sdk_');
    }
    
    $temphandle = fopen($tempfile, "wb"); // Gunakan mode binary
    fwrite($temphandle, $content);
    fclose($temphandle);
    
    $this->addTempfile($tempfile);
    $content = $this->parseFile($tempfile, $removeVars, true, $compress);
    unlink($tempfile);
    
    // Parse konten utama dengan buffer yang dioptimalkan
    $mainContent = $this->DomHTML($content, $removeVars, true, $compress).PHP_EOL;
    
    // Optimasi footer scripts dengan string builder pattern
    $footerContent = '';
    $asset = tatiye::assets('footer');
    $scripts = [];
    
    // Pre-alokasi array untuk scripts
    $scripts = array_pad([], count($asset), '');
    $i = 0;
    
    foreach ($asset as $path) {
        if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
            $scripts[$i] = sprintf('<script src="%s"></script>', $path);
        } else if (strpos($path, 'module|') !== false) {
            $cleanPath = str_replace('module|', '', $path);
            $scripts[$i] = sprintf('<script type="module" src="'.HOST.'/%s"></script>', $cleanPath);
        } else {
            $scripts[$i] = sprintf('<script src="'.HOST.'/%s"></script>', $path);
        }
        $i++;
    }
    
    // Gabungkan scripts dengan implode untuk performa lebih baik
    $scriptContent = implode(PHP_EOL, array_filter($scripts)) . PHP_EOL;
    
    // Parse footer content
    $footerContent = $this->DomHTML($scriptContent, $removeVars, true, $compress).PHP_EOL;
    
    // Gunakan string builder pattern untuk menggabungkan output
    $output = $metaContent;
    $output .= $mainContent;
    $output .= $footerContent;
    
    return $output;
}

  /**
   * Mengurai file template
   * @param string $file Nama file template
   * @param bool $removeVars Apakah menghapus variabel yang tidak digunakan
   * @param bool $return Apakah mengembalikan hasil
   * @param bool $compress Apakah mengompres hasil
   * @return string Hasil penguraian file
   */
  public function parseFile(string $file, bool $removeVars = false, bool $return = true, bool $compress = false): string
  {
    try {
      if (!isset($this->files[$file]) || !file_exists($this->files[$file])) {
        throw new Exception("Template file not found: {$this->files[$file]}");
      }

      $fileContent = file_get_contents($this->files[$file]);

      // Proses blok dan bagian template
      $fileContent = $this->processBlocks($fileContent);
      $fileContent = $this->processSections($fileContent);
      //$fileContent = $this->processBrief($fileContent);

      // Ganti variabel template
      if (isset($this->_tpldata['.'][$file])) {
        foreach ($this->_tpldata['.'][$file] as $varName => $varVal) {
          $fileContent = str_replace('{' . $varName . '}', $varVal, $fileContent);
        }
      }

      return $this->DomHTML($fileContent, $removeVars, $return, $compress);
    } catch (Exception $e) {
      // Log error atau tangani sesuai kebutuhan
      throw new Exception("Error parsing file: " . $e->getMessage());
    }
  }

  /**
   * Memproses blok dalam konten
   * @param string $content Konten yang akan diproses
   * @return string Hasil pemrosesan blok
   */
  private function processBlocks(string $content): string
  {
    $pattern = "#<!-- App ([a-z0-9_]*) -->([\S\W]*)<!-- END_App \\1 -->#i";
    return preg_replace_callback($pattern, [$this, 'processBlockCallback'], $content);
  }

  /**
   * Callback untuk memproses blok
   * @param array $matches Hasil pencocokan regex
   * @return string Hasil pemrosesan blok
   */
  private function processBlockCallback(array $matches): string
  {
    $blockName = $matches[1];
    $blockContent = $matches[2];

    if (isset($this->_tpldata['.'][$blockName]) && is_array($this->_tpldata['.'][$blockName])) {
      return $this->parseBlock($blockContent, $blockName);
    }

    return $blockContent;
  }

  /**
   * Memproses bagian dalam konten
   * @param string $content Konten yang akan diproses
   * @return string Hasil pemrosesan bagian
   */
  private function processSections(string $content): string
  {
    $pattern = "#<!-- START_SECTION ([a-z0-9_]*) -->([\S\W]*)<!-- STOP_SECTION \\1 -->#i";
    return preg_replace_callback($pattern, [$this, 'processSectionCallback'], $content);
  }

  /**
   * Callback untuk memproses bagian
   * @param array $matches Hasil pencocokan regex
   * @return string Hasil pemrosesan bagian
   */
  private function processSectionCallback(array $matches): string
  {
    $sectionName = $matches[1];
    $sectionContent = $matches[2];

    if ($sectionName === 'donot_compress') {
      return $matches[0];
    }

    if (!empty($this->_section[$sectionName])) {
      return $this->DomHTML($sectionContent);
    }

    return '';
  }



  /**
   * Memproses parameter if dalam Brief
   * @param array $data Data yang akan diproses
   * @param array $params Parameter Brief
   * @return array Data yang telah difilter
   */
  private function processIfParameter(array $data, array $params): array {
      if (empty($params['if'])) return $data;
      
      list($field, $value) = explode(':', $params['if']);
      return array_filter($data, function($item) use ($field, $value) {
          return $item[$field] == $value;
      });
  }

  /**
   * Memproses kondisi dalam konten
   * @param string $content Konten template
   * @param array $data Data untuk evaluasi
   * @return string Hasil proses
   */
  private function processIfConditions(string $content, array $data): string 
  {
      $pattern = '/\{if:(.*?)\}(.*?)(?:\{elseif:(.*?)\}(.*?))*(?:\{else\}(.*?))?\{endif\}/s';
      
      return preg_replace_callback($pattern, function($matches) use ($data) {
          $condition = trim($matches[1]);
          $ifContent = $matches[2];
          $elseContent = $matches[3] ?? '';
          
          // Evaluasi kondisi
          $result = $this->evaluateSimpleCondition($condition, $data);
          
          return $result ? $ifContent : $elseContent;
      }, $content);
  }

  /**
   * Evaluasi kondisi sederhana
   * @param string $condition Kondisi yang akan dievaluasi
   * @param array $data Data untuk evaluasi
   * @return bool Hasil evaluasi
   */
  private function evaluateSimpleCondition(string $condition, array $data): bool 
  {
      // Cek operator
      $operators = [
          ':' => '==',    // field:value
          '=' => '==',    // field=value
          '!=' => '!=',   // field!=value
          '>' => '>',     // field>value
          '<' => '<',     // field<value
          '>=' => '>=',   // field>=value
          '<=' => '<=',   // field<=value
      ];
      
      foreach ($operators as $symbol => $operator) {
          if (strpos($condition, $symbol) !== false) {
              list($field, $value) = array_map('trim', explode($symbol, $condition, 2));
              return $this->compareValue($data[$field] ?? null, $value, $operator);
          }
      }
      
      // Jika tidak ada operator, cek keberadaan field
      return !empty($data[$condition]);
  }

  /**
   * Membandingkan nilai
   * @param mixed $fieldValue Nilai field
   * @param mixed $compareValue Nilai pembanding
   * @param string $operator Operator perbandingan
   * @return bool Hasil perbandingan
   */
  private function compareValue($fieldValue, $compareValue, string $operator): bool 
  {
      // Hapus tanda kutip jika ada
      $compareValue = trim($compareValue, '"\'');
      
      switch ($operator) {
          case '==':
              return $fieldValue == $compareValue;
          case '!=':
              return $fieldValue != $compareValue;
          case '>':
              return $fieldValue > $compareValue;
          case '<':
              return $fieldValue < $compareValue;
          case '>=':
              return $fieldValue >= $compareValue;
          case '<=':
              return $fieldValue <= $compareValue;
          default:
              return false;
      }
  }

  /**
   * Mendaftarkan filter baru
   * @param string $name Nama filter
   * @param callable $callback Function filter
   */
  public function addFilter(string $name, callable $callback): void 
  {
    $this->filters[$name] = $callback;
  }
  
  /**
   * Menerapkan filter pada nilai
   * @param mixed $value Nilai yang akan difilter
   * @param string $filter Nama filter
   * @param array $arguments Argument tambahan
   * @return mixed Nilai hasil filter
   */
  private function applyFilter($value, string $filter, array $arguments = []) 
  {
    // Cek apakah filter ada
    if (isset($this->filters[$filter])) {
      return call_user_func_array($this->filters[$filter], [$value, ...$arguments]);
    }
    
    // Filter bawaan
    switch($filter) {
        case 'number_format':
            // Pastikan nilai adalah numerik sebelum diformat
            if (is_numeric($value)) {
                $decimals = $arguments[0] ?? 0;
                $decPoint = $arguments[1] ?? ',';
                $thousandsSep = $arguments[2] ?? '.';
                return number_format((float)$value, $decimals, $decPoint, $thousandsSep);
            }
            return $value;
            
        case 'upper':
            return strtoupper($value);
            
        case 'lower':
            return strtolower($value);
            
        case 'readmore':
            return $this->truncateText($value, ...$arguments);
            
        case 'date':
            // Format tanggal dengan format custom
            // Penggunaan: {tanggal|date(Y-m-d)}
            $format = $arguments[0] ?? 'Y-m-d H:i:s';
            return date($format, strtotime($value));
            
        case 'currency':
            // Format mata uang
            // Penggunaan: {harga|currency(IDR)}
            if (!is_numeric($value)) return $value;
            $currency = $arguments[0] ?? 'IDR';
            $decimals = $arguments[1] ?? 0;
            return $currency . ' ' . number_format((float)$value, $decimals, ',', '.');
            
        case 'nl2br':
            // Konversi newline ke <br>
            // Penggunaan: {deskripsi|nl2br}
            return nl2br($value);
            
        case 'limit_words':
            // Batasi jumlah kata
            // Penggunaan: {content|limit_words(20,...)}
            $limit = (int)($arguments[0] ?? 20);
            $end = $arguments[1] ?? '...';
            $words = str_word_count($value, 2);
            if (count($words) > $limit) {
                return implode(' ', array_slice($words, 0, $limit)) . $end;
            }
            return $value;
            
        case 'strip_tags':
            // Hapus HTML tags
            // Penggunaan: {content|strip_tags}
            return strip_tags($value);
            
        case 'escape':
            // Escape HTML entities
            // Penggunaan: {content|escape}
            return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            
        case 'trim':
            // Trim whitespace
            // Penggunaan: {text|trim}
            return trim($value);
            
        case 'md5':
            // Generate MD5 hash
            // Penggunaan: {text|md5}
            return md5($value);
            
        case 'json':
            // Encode/decode JSON
            // Penggunaan: {data|json}
            return is_string($value) ? json_decode($value, true) : json_encode($value);
            
        case 'ucfirst':
            // Kapital huruf pertama
            // Penggunaan: {nama|ucfirst}
            return ucfirst(strtolower($value));
            
        case 'ucwords':
            // Kapital setiap kata
            // Penggunaan: {judul|ucwords} 
            return ucwords(strtolower($value));
            
        case 'slug':
            // Generate URL slug
            // Penggunaan: {judul|slug}
            $text = strtolower($value);
            $text = preg_replace('/[^a-z0-9\-]/', '-', $text);
            return trim(preg_replace('/-+/', '-', $text), '-');
            
        case 'phone':
            // Format nomor telepon
            // Penggunaan: {telepon|phone}
            $number = preg_replace('/[^0-9]/', '', $value);
            if (strlen($number) > 10) {
                return substr($number, 0, 4) . '-' . substr($number, 4, 4) . '-' . substr($number, 8);
            }
            return $value;
            
        default:
            return $value;
    }
  }
  
  /**
   * Memotong teks dengan panjang tertentu
   */
  private function truncateText(string $text, int $length = 100, string $append = '...'): string 
  {
    if (mb_strlen($text) <= $length) {
      return $text;
    }
    return rtrim(mb_substr($text, 0, $length)) . $append;
  }

  /**
   * Validasi dan proses grouping
   */
  private function shouldProcessGrouping(array $params): bool 
  {
    return !empty($params['group']) || !empty($params['aggregate']);
  }

  /**
   * Memproses grouping dan agregasi data
   */
  private function processGrouping(array $data, array $params): array 
  {
    $result = [];
    
    // Proses group by
    if (!empty($params['group'])) {
        $groupFields = explode(',', $params['group']);
        
        foreach ($data as $item) {
            $groupKey = [];
            foreach ($groupFields as $field) {
                $groupKey[] = $item[trim($field)] ?? '';
            }
            $key = implode('|', $groupKey);
            $result[$key][] = $item;
        }
        
        // Proses agregasi jika ada
        if (!empty($params['aggregate'])) {
            foreach ($result as &$group) {
                $group = $this->calculateAggregates($group, $params['aggregate']);
            }
        }
        
        // Format hasil akhir
        return array_values($result);
    }
    
    return $data;
  }

  /**
   * Menghitung nilai agregasi
   */
  private function calculateAggregates(array $group, string $aggregateParams): array 
  {
    $aggregates = explode(',', $aggregateParams);
    $result = ['items' => $group];
    
    foreach ($aggregates as $agg) {
        list($function, $field) = explode(':', trim($agg));
        
        switch (strtolower($function)) {
            case 'sum':
                $result[$function . '_' . $field] = array_sum(array_column($group, $field));
                break;
            case 'avg':
                $values = array_column($group, $field);
                $result[$function . '_' . $field] = count($values) ? array_sum($values) / count($values) : 0;
                break;
            case 'count':
                $result[$function . '_' . $field] = count($group);
                break;
            case 'min':
                $result[$function . '_' . $field] = min(array_column($group, $field));
                break;
            case 'max':
                $result[$function . '_' . $field] = max(array_column($group, $field));
                break;
        }
    }
    
    return $result;
  }

  /**
   * Validasi dan proses export
   */
  private function shouldProcessExport(array $params): bool 
  {
    return !empty($params['export']);
  }

  /**
   * Memproses export data ke berbagai format
   */
  private function processExport(array $data, array $params): string 
  {
    if (empty($data)) {
        header('Content-Type: application/json');
        return json_encode(['error' => 'Data kosong']);
    }

    $format = strtolower($params['export']);
    
    switch($format) {
        case 'json':
            header('Content-Type: application/json');
            return json_encode([
                'status' => 'success',
                'data' => $data,
                'total' => count($data)
            ]);

        case 'csv':
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="export_'.date('Y-m-d').'.csv"');
            return $this->exportToCsv($data);

        case 'excel':
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment; filename="export_'.date('Y-m-d').'.xls"');
            return $this->exportToExcel($data);

        case 'xml':
            header('Content-Type: application/xml; charset=utf-8');
            header('Content-Disposition: attachment; filename="export_'.date('Y-m-d').'.xml"');
            return $this->exportToXml($data);

        default:
            throw new Exception("Format export tidak didukung: $format");
    }
  }

  /**
   * Export data ke format CSV
   */
  private function exportToCsv(array $data): string 
  {
    if (empty($data)) return '';
    
    $output = fopen('php://temp', 'r+');
    
    // Tulis header
    fputcsv($output, array_keys(reset($data)));
    
    // Tulis data
    foreach ($data as $row) {
        fputcsv($output, array_values($row));
    }
    
    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);
    
    return $csv;
  }

  /**
   * Export data ke format XML
   */
  private function exportToXml(array $data): string 
  {
    $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><data></data>');
    
    foreach ($data as $item) {
        $record = $xml->addChild('record');
        foreach ($item as $key => $value) {
            $record->addChild($key, htmlspecialchars((string)$value));
        }
    }
    
    return $xml->asXML();
  }

  /**
   * Export data ke format Excel
   */
  private function exportToExcel(array $data): string 
  {
    $output = "<table border='1'>\n";
    
    // Header
    $output .= "<tr>";
    foreach (array_keys(reset($data)) as $header) {
        $output .= "<th>" . htmlspecialchars($header) . "</th>";
    }
    $output .= "</tr>\n";
    
    // Data
    foreach ($data as $row) {
        $output .= "<tr>";
        foreach ($row as $value) {
            $output .= "<td>" . htmlspecialchars((string)$value) . "</td>";
        }
        $output .= "</tr>\n";
    }
    
    $output .= "</table>";
    
    return $output;
  }

  /**
   * Cache system
   */
  private function processCaching(string $endpoint, array $params): ?array 
  {
    if (empty($params['cache'])) {
        return null;
    }

    $cacheKey = $this->generateCacheKey($endpoint, $params);
    $cacheDuration = (int)$params['cache']; // Dalam detik

    // Cek cache
    $cachedData = $this->getCache($cacheKey);
    if ($cachedData !== null) {
        return $cachedData;
    }

    // Fetch data baru
    $data = $this->fetchFromApi($endpoint);
    $this->setCache($cacheKey, $data, $cacheDuration);
    
    return $data;
  }

  /**
   * Generate cache key
   */
  private function generateCacheKey(string $endpoint, array $params): string 
  {
    return md5($endpoint . serialize($params));
  }

  /**
   * Get data dari cache
   */
  private function getCache(string $key): ?array 
  {
    try {
        $cacheDir = dirname(__DIR__) . '/cache';
        $cacheFile = $cacheDir . '/ngorei_cache_' . $key;
        
        error_log("Mencoba membaca cache dari: " . $cacheFile);
        
        if (file_exists($cacheFile)) {
            $content = file_get_contents($cacheFile);
            if ($content === false) {
                error_log("Gagal membaca file cache");
                return null;
            }
            
            $cache = unserialize($content);
            if ($cache === false) {
                error_log("Gagal unserialize cache");
                return null;
            }
            
            if ($cache['expires'] > time()) {
                error_log("Cache masih valid, mengembalikan data cache");
                return $cache['data'];
            }
            
            error_log("Cache sudah expired, menghapus file cache");
            unlink($cacheFile);
        }
        
        error_log("Cache tidak ditemukan");
        return null;
        
    } catch (Exception $e) {
        error_log("Error saat membaca cache: " . $e->getMessage());
        return null;
    }
  }

  /**
   * Set data ke cache
   */
  private function setCache(string $key, array $data, int $duration): void 
  {
    try {
        $cacheDir = dirname(__DIR__) . '/cache';
        
        error_log("Mencoba menyimpan cache di: " . $cacheDir);
        
        // Buat direktori cache jika belum ada
        if (!is_dir($cacheDir)) {
            error_log("Membuat direktori cache...");
            if (!mkdir($cacheDir, 0777, true)) {
                throw new Exception("Gagal membuat direktori cache");
            }
            chmod($cacheDir, 0777);
        }
        
        $cacheFile = $cacheDir . '/ngorei_cache_' . $key;
        $cache = [
            'expires' => time() + $duration,
            'data' => $data
        ];
        
        error_log("Menulis cache ke: " . $cacheFile);
        if (file_put_contents($cacheFile, serialize($cache)) === false) {
            throw new Exception("Gagal menulis file cache");
        }
        
        error_log("Cache berhasil disimpan");
        
    } catch (Exception $e) {
        error_log("Error saat menyimpan cache: " . $e->getMessage());
    }
  }

  /**
   * Memproses kondisi if-elseif-else dalam template
   * @param string $content Konten template
   * @return string Hasil proses
   */
  private function processAdvancedConditions(string &$content): void
  {
      $pattern = '/\{if:(.*?)\}(.*?)(?:\{elseif:(.*?)\}(.*?))*(?:\{else\}(.*?))?\{endif\}/s';
      
      $content = preg_replace_callback($pattern, function($matches) {
          // Ekstrak kondisi if utama dan kontennya
          $mainCondition = $this->parseCondition(trim($matches[1]));
          $ifContent = $matches[2];
          
          // Evaluasi kondisi if utama
          if ($this->evaluateCondition($mainCondition)) {
              return $this->parseTemplateVariables($ifContent);
          }
          
          // Cari dan evaluasi semua elseif
          $fullContent = $matches[0];
          if (preg_match_all('/\{elseif:(.*?)\}(.*?)(?=\{elseif|{else|{endif})/s', $fullContent, $elseifMatches)) {
              for ($i = 0; $i < count($elseifMatches[1]); $i++) {
                  $elseifCondition = $this->parseCondition(trim($elseifMatches[1][$i]));
                  if ($this->evaluateCondition($elseifCondition)) {
                      return $this->parseTemplateVariables($elseifMatches[2][$i]);
                  }
              }
          }
          
          // Jika ada else, ambil kontennya
          if (preg_match('/\{else\}(.*?)\{endif\}/s', $fullContent, $elseMatch)) {
              return $this->parseTemplateVariables($elseMatch[1]);
          }
          
          return '';
      }, $content);
  }

  /**
   * Parse kondisi dari template
   * @param string $condition Kondisi yang akan diparsing
   * @return array Hasil parsing kondisi
   */
  private function parseCondition(string $condition): array
  {
      $result = [
          'left' => '',
          'operator' => '',
          'right' => ''
      ];
      
      // Pisahkan kondisi berdasarkan operator
      if (strpos($condition, '==') !== false) {
          list($left, $right) = array_map('trim', explode('==', $condition));
          $result['operator'] = '==';
      } elseif (strpos($condition, '!=') !== false) {
          list($left, $right) = array_map('trim', explode('!=', $condition));
          $result['operator'] = '!=';
      } else {
          return $result;
      }

      // Parse nilai kiri
      if (preg_match('/^"([^"]*)"$/', $left, $matches)) {
          // Jika string dengan kutip
          $result['left'] = $matches[1];
      } elseif (preg_match('/^\{([a-z0-9_]+)\}$/i', $left, $matches)) {
          // Jika variabel dalam kurung kurawal
          $result['left'] = $this->_tpldata['.'][$matches[1]] ?? '';
      } else {
          // Jika variabel biasa
          $result['left'] = $this->_tpldata['.'][$left] ?? $left;
      }

      // Parse nilai kanan
      if (preg_match('/^"([^"]*)"$/', $right, $matches)) {
          // Jika string dengan kutip
          $result['right'] = $matches[1];
      } elseif (preg_match('/^\{([a-z0-9_]+)\}$/i', $right, $matches)) {
          // Jika variabel dalam kurung kurawal
          $result['right'] = $this->_tpldata['.'][$matches[1]] ?? '';
      } else {
          // Jika variabel biasa
          $result['right'] = $this->_tpldata['.'][$right] ?? $right;
      }

      return $result;
  }

  /**
   * Evaluasi kondisi yang sudah diparsing
   * @param array $condition Kondisi yang akan dievaluasi
   * @return bool Hasil evaluasi
   */
  private function evaluateCondition(array $condition): bool
  {
      if (empty($condition['operator'])) {
          return false;
      }
      
      $left = (string)$condition['left'];
      $right = (string)$condition['right'];

      switch ($condition['operator']) {
          case '==':
              return $left === $right;
          case '!=':
              return $left !== $right;
          default:
              return false;
      }
  }

  /**
   * Parse variabel template dalam konten
   * @param string $content Konten yang akan diparsing
   * @return string Hasil parsing
   */
  private function parseTemplateVariables(string $content): string
  {
      // Parse class w- pattern terlebih dahulu
      $content = $this->parseWidthClasses($content);
      
      // Lanjutkan dengan parsing variabel template normal
      return preg_replace_callback(
          '/{([a-z0-9_]+)(?:\|([^}]+))?}/i',
          function($matches) {
              $varName = $matches[1];
              $value = $this->_tpldata['.'][$varName] ?? '';
              
              if (isset($matches[2])) {
                  $filters = explode('|', $matches[2]);
                  foreach ($filters as $filter) {
                      $value = $this->applyFilter($value, $filter);
                  }
              }
              
              return $value;
          },
          $content
      );
  }

  /**
   * Parse class width pattern dan konversi ke inline style
   * @param string $content Konten yang akan diparsing
   * @return string Hasil parsing
   */
  private function parseWidthClasses(string $content): string
  {
      return preg_replace_callback(
          '/class=(["\'])(.*?\bw-(\d+(?:\.\d+)?)(px|em|rem|%|vh|vw)?\b.*?)\1/i',
          function($matches) {
              $fullClass = $matches[2];
              $value = $matches[3];
              $unit = $matches[4] ?? 'px'; // Default ke px jika unit tidak disebutkan
              
              $width = $value . $unit;
              
              // Pisahkan class w- dari class lainnya
              $classes = explode(' ', $fullClass);
              $otherClasses = array_filter($classes, function($class) {
                  return !preg_match('/^w-/', $class);
              });
              
              // Gabungkan kembali class lainnya
              $newClasses = implode(' ', $otherClasses);
              
              // Cek apakah sudah ada style attribute
              if (strpos($matches[0], 'style=') !== false) {
                  // Jika sudah ada style, tambahkan width ke dalamnya
                  return preg_replace_callback(
                      '/style=(["\'])(.*?)\1/',
                      function($styleMatches) use ($width) {
                          $existingStyle = $styleMatches[2];
                          $newStyle = rtrim($existingStyle, ';') . ';width:' . $width . ';';
                          return 'style="' . $newStyle . '"';
                      },
                      'class="' . $newClasses . '" ' . $matches[0]
                  );
              } else {
                  // Jika belum ada style, buat baru
                  return 'class="' . $newClasses . '" style="width:' . $width . ';"';
              }
          },
          $content
      );
  }

  /**
   * Property untuk menyimpan layout dan blocks
   */
  private $layouts = [];
  private $blocks = [];
  private $currentBlock = null;

  /**
   * Mendaftarkan layout baru
   * @param string $name Nama layout
   * @param string $template Konten template layout
   */
  public function setLayout(string $name, string $template): void 
  {
    $this->layouts[$name] = $template;
  }

  /**
   * Menggunakan layout yang sudah didefinisikan
   * @param string $name Nama layout
   * @param array $data Data untuk layout
   * @return string Hasil render layout
   */
  public function extendLayout(string $name, array $data = []): string 
  {
    if (!isset($this->layouts[$name])) {
        throw new NgoreiException("Layout '$name' tidak ditemukan");
    }
    
    // Set data untuk layout
    foreach ($data as $key => $value) {
        $this->val($key, $value);
    }
    
    $content = $this->layouts[$name];
    
    // Parse blocks dalam layout
    $content = $this->parseBlocks($content);
    
    // Parse konten normal
    return $this->DomHTML($content);
  }

  /**
   * Mendefinisikan block dalam template
   * @param string $name Nama block
   * @param string $content Konten block
   */
  public function setBlock(string $name, string $content): void 
  {
    $this->blocks[$name] = $content;
  }

  /**
   * Memulai pendefinisian block
   * @param string $name Nama block
   */
  public function startBlock(string $name): void 
  {
    $this->currentBlock = $name;
    ob_start();
  }

  /**
   * Mengakhiri pendefinisian block
   */
  public function endBlock(): void 
  {
    if ($this->currentBlock === null) {
        throw new NgoreiException("Tidak ada block yang sedang aktif");
    }
    
    $content = ob_get_clean();
    $this->blocks[$this->currentBlock] = $content;
    $this->currentBlock = null;
  }

  /**
   * Mengambil konten block
   * @param string $name Nama block
   * @param string $default Konten default jika block tidak ada
   * @return string Konten block
   */
  public function getBlock(string $name, string $default = ''): string 
  {
    return $this->blocks[$name] ?? $default;
  }

  /**
   * Parse blocks dalam template
   * @param string $content Konten template
   * @return string Hasil parsing
   */
  private function parseBlocks(string $content): string 
  {
    // Parse @block directives
    $content = preg_replace_callback('/@block\(([^)]+)\)/', function($matches) {
        $blockName = trim($matches[1], '"\'');
        return $this->getBlock($blockName);
    }, $content);
    
    // Parse @hasBlock directives
    $content = preg_replace_callback('/@hasBlock\(([^)]+)\)(.*?)@endHasBlock/s', function($matches) {
        $blockName = trim($matches[1], '"\'');
        return isset($this->blocks[$blockName]) ? $matches[2] : '';
    }, $content);
    
    return $content;
  }

  // Tambahkan property untuk assets
  private $assets = [
      'header' => [],
      'footer' => []
  ];

  private $assetHost =HOST; // Tambah property untuk host

  /**
   * Set host domain untuk assets
   * @param string $host Host domain (contoh: https://example.com)
   */
  public function setAssetHost(string $host): void
  {
      $this->assetHost = rtrim($host, '/');
  }

  /**
   * Menambahkan assets (CSS/JS) ke template dengan posisi yang tepat
   * @param string|array $files Path file atau array path files
   * @param string $position Posisi asset (header/footer)
   * @param array $attributes Atribut tambahan untuk tag
   */
  public function setAssets($files, string $position = 'header', array $attributes = []): void
  {
      // Validasi input
      if (!is_string($files) && !is_array($files)) {
          throw new Exception("Assets must be string or array");
      }
      
      if (!isset($this->assets[$position])) {
          $this->assets[$position] = [];
      }

      // Normalize input
      $files = (array)$files;

      // Process files
      foreach ($files as $file) {
          if (!is_string($file) || empty(trim($file))) {
              continue;
          }

          try {
              // CSS selalu di header
              $actualPosition = (pathinfo($file, PATHINFO_EXTENSION) === 'css') ? 'header' : $position;
              
              if (strpos($file, 'module|') === 0) {
                  $this->processModuleAsset($file, $actualPosition, $attributes);
              } else {
                  $this->processRegularAsset($file, $actualPosition, $attributes);
              }
          } catch (Exception $e) {
              error_log("Asset processing error: " . $e->getMessage());
          }
      }

      // Update template
      if (isset($this->assets['header'])) {
          $this->val("assets.header", implode("\n", $this->assets['header']));
      }
      if (isset($this->assets['footer'])) {
          $this->val("assets.footer", implode("\n", $this->assets['footer']));
      }
  }

  /**
   * Menentukan posisi yang tepat untuk asset
   * @param string $file Path file
   * @param string $defaultPosition Posisi default
   * @return string Posisi yang ditentukan
   */

  /**
   * Proses module asset
   */
  private function processModuleAsset(string $file, string $position, array $attributes): void
  {
      $actualFile = substr($file, 7); // Hapus 'module|'
      if (empty(trim($actualFile))) {
          return;
      }

      $url = $this->buildAssetUrl($actualFile);
      // Hapus indentasi tambahan untuk script module
      $this->assets[$position][] = sprintf('<script type="module" src="%s"></script>',
          htmlspecialchars($url, ENT_QUOTES, 'UTF-8')
      );
  }

  /**
   * Proses regular asset
   */
  private function processRegularAsset(string $file, string $position, array $attributes): void
  {
      $url = $this->buildAssetUrl($file);
      $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

      if (empty($ext)) {
          throw new Exception("File extension required: $file");
      }
      
      switch ($ext) {
          case 'css':
              $attrs = $this->buildAttributes(array_merge($attributes, ['rel' => 'stylesheet']));
              $this->assets[$position][] = sprintf('<link%s href="%s">',
                  $attrs,
                  htmlspecialchars($url, ENT_QUOTES, 'UTF-8')
              );
              break;

          case 'js':
              $attrs = $this->buildAttributes($attributes);
              // Pastikan semua script (termasuk module) menggunakan format yang sama
              $this->assets[$position][] = sprintf('<script%s src="%s"></script>',
                  $attrs,
                  htmlspecialchars($url, ENT_QUOTES, 'UTF-8')
              );
              break;

          default:
              throw new Exception("Unsupported asset type: $ext");
      }
  }

  /**
   * Build asset URL
   */
  private function buildAssetUrl(string $path): string
  {
      $path = $this->sanitizePath($path);
      
      if (preg_match('/^(https?:)?\/\//i', $path)) {
          return $path;
      }

      return rtrim($this->assetHost, '/') . '/' . ltrim($path, '/');
  }

  /**
   * Sanitize file path
   */
  private function sanitizePath(string $path): string
  {
      return str_replace(['../', './'], '', trim($path));
  }

  /**
   * Build HTML attributes string
   */
  private function buildAttributes(array $attributes): string
  {
      $attrs = [];
      foreach ($attributes as $key => $value) {
          if ($value === true) {
              $attrs[] = htmlspecialchars($key);
          } elseif ($value !== false && $value !== null) {
              $attrs[] = sprintf(
                  '%s="%s"',
                  htmlspecialchars($key),
                  htmlspecialchars($value)
              );
          }
      }
      return empty($attrs) ? '' : ' ' . implode(' ', $attrs);
  }

  /**
   * Mendapatkan assets untuk posisi tertentu
   * @param string $position Posisi asset (header/footer)
   * @return string HTML assets
   */
  public function getAssets(string $position): string
  {
      return implode("\n", $this->assets[$position] ?? []);
  }

  /**
   * Membersihkan assets untuk posisi tertentu
   * @param string $position Posisi asset (header/footer)
   * @return void
   */
  public function clearAssets(string $position): void
  {
      if (isset($this->assets[$position])) {
          $this->assets[$position] = [];
          $this->val("assets.{$position}", '');
      }
  }

  private function domAssets(string &$content): void 
  {
    // Parse header assets dengan indentasi yang tepat
    $content = preg_replace_callback(
        '/<!-- assets\.header -->/',
        function() {
            $assets = $this->getAssets('header');
            // Tambahkan 4 spasi di awal setiap baris
            $lines = explode("\n", $assets);
            $indentedLines = array_map(function($line) {
                return "    " . $line;
            }, $lines);
            return implode("\n", $indentedLines);
        },
        $content
    );

    // Parse footer assets dengan indentasi yang tepat
    $content = preg_replace_callback(
        '/<!-- assets\.footer -->/',
        function() {
            $assets = $this->getAssets('footer');
            // Tambahkan 2 spasi di awal setiap baris
            $lines = explode("\n", $assets);
            $indentedLines = array_map(function($line) {
                return "  " . $line;
            }, $lines);
            return implode("\n", $indentedLines);
        },
        $content
    );
  }

  /**
   * Memproses script import dalam konten
   * @param string &$content Konten yang akan diproses
   */
  private function domScriptImports(string &$content): void {
    // Parse script imports dengan penanganan path nested
    $scriptPattern = '/<script\s+import=["\'](.*?)["\'](?:\s+key=["\'](.*?)["\'])?(.*?)><\/script>/i';
    $content = preg_replace_callback($scriptPattern, function($matches) {
        $importPath = $matches[1];
        $key = isset($matches[2]) ? $matches[2] : '';
        $otherAttributes = $matches[3] ?? '';
        
        // Normalisasi path dengan menghapus '..' dan './'
        $importPath = $this->normalizeImportPath($importPath);
        
        // Cek apakah path adalah URL absolut
        if (!preg_match('/^(https?:)?\/\//i', $importPath)) {
            // Gabungkan dengan host domain untuk path relatif
            $importPath = rtrim($this->assetHost, '/') . '/' . ltrim($importPath, '/');
        }
        
        $keyAttr = $key ? sprintf(' data-key="%s"', htmlspecialchars($key, ENT_QUOTES, 'UTF-8')) : '';
        
        return sprintf('<script type="module" src="%s"%s%s></script>', 
            htmlspecialchars($importPath, ENT_QUOTES, 'UTF-8'),
            $keyAttr,
            $otherAttributes
        );
    }, $content);

    // Parse style imports dengan penanganan path nested
    $stylePattern = '/<style\s+import=["\'](.*?)["\'](?:\s+key=["\'](.*?)["\'])?(.*?)><\/style>/i';
    $content = preg_replace_callback($stylePattern, function($matches) {
        $importPath = $matches[1];
        $key = isset($matches[2]) ? $matches[2] : '';
        
        // Normalisasi path dengan menghapus '..' dan './'
        $importPath = $this->normalizeImportPath($importPath);
        
        // Cek apakah path adalah URL absolut
        if (!preg_match('/^(https?:)?\/\//i', $importPath)) {
            // Gabungkan dengan host domain untuk path relatif
            $importPath = rtrim($this->assetHost, '/') . '/' . ltrim($importPath, '/');
        }
        
        return sprintf('<link rel="stylesheet" href="%s">', 
            htmlspecialchars($importPath, ENT_QUOTES, 'UTF-8')
        );
    }, $content);
  }

  /**
   * Normalisasi path import
   * @param string $path Path yang akan dinormalisasi
   * @return string Path yang telah dinormalisasi
   */
  private function normalizeImportPath(string $path): string {
    // Hapus '..' dan './' dari path
    $path = preg_replace('/\.\.[\/\\\\]|\.[\/\\\\]/', '', $path);
    
    // Ganti multiple slashes dengan single slash
    $path = preg_replace('/[\/\\\\]+/', '/', $path);
    
    // Hapus slash di awal dan akhir
    $path = trim($path, '/');
    
    // Validasi karakter yang diizinkan dalam path
    if (!preg_match('/^[a-zA-Z0-9\/_.-]+$/', $path)) {
        throw new Exception("Path tidak valid: " . $path);
    }
    
    return $path;
  }

  /**
   * Parse class utility patterns dan konversi ke inline style
   * @param string $content Konten yang akan diparsing
   * @return string Hasil parsing
   */
  private function domUtilityClasses(string $content): string
  {
      // Definisi pola utilitas
      $utilities = [
          'tx' => 'text-align',    
          // Utility yang sudah ada
          'position' => 'position',
          'pos' => 'position',
          'top' => 'top',
          'bottom' => 'bottom',
          'left' => 'left',
          'right' => 'right',


          // Utility yang sudah ada
          'w' => 'width',
          'h' => 'height',
          'min-w' => 'min-width',
          'min-h' => 'min-height',
          'max-w' => 'max-width',
          'max-h' => 'max-height',
          
          // Margin
          'm' => 'margin',
          'mt' => 'margin-top',
          'mb' => 'margin-bottom',
          'ml' => 'margin-left',
          'mr' => 'margin-right',
          'mx' => 'margin-left margin-right',
          'my' => 'margin-top margin-bottom',
          
          // Padding
          'p' => 'padding',
          'pt' => 'padding-top',
          'pb' => 'padding-bottom',
          'pl' => 'padding-left',
          'pr' => 'padding-right',
          'px' => 'padding-left padding-right',
          'py' => 'padding-top padding-bottom',
          
          // Position
          'top' => 'top',
          'bottom' => 'bottom',
          'left' => 'left',
          'right' => 'right',
          
          // Font
          'fs' => 'font-size',
          'fw' => 'font-weight',
          'lh' => 'line-height',
          
          // Border
          'br' => 'border-radius',
          'bw' => 'border-width',
          
          // Opacity & Z-index
          'op' => 'opacity',
          'z' => 'z-index',
          

          
          // Tambahkan utilitas warna
          'bg' => 'background-color',
          'text' => 'color',
      ];
      
      // Pattern untuk mencocokkan seluruh tag HTML dengan atribut class
      $pattern = '/<([a-zA-Z0-9]+)([^>]*?class=(["\'])(.*?)\3[^>]*?)>/i';
      
      return preg_replace_callback($pattern, function($elementMatches) use ($utilities) {
          $tag = $elementMatches[1];
          $attributes = $elementMatches[2];
          $quote = $elementMatches[3];
          $classes = $elementMatches[4];
          
          // Jika tidak ada utility class, kembalikan tag asli
          $hasUtilityClass = false;
          foreach ($utilities as $prefix => $properties) {
              if (preg_match('/\b' . preg_quote($prefix, '/') . '-[a-zA-Z0-9\#]+/', $classes)) {
                  $hasUtilityClass = true;
                  break;
              }
          }
          
          if (!$hasUtilityClass) {
              return $elementMatches[0];
          }
          
          // Proses setiap utility class
          $styles = [];
          $remainingClasses = [];
          
          foreach (explode(' ', $classes) as $class) {
              $matched = false;
              foreach ($utilities as $prefix => $properties) {
                  if (preg_match('/^' . preg_quote($prefix, '/') . '-([a-zA-Z0-9\#\.]+)(?:-(.*?))?$/', $class, $matches)) {
                      $matched = true;
                      $value = $matches[1];
                      
                      // Khusus untuk text-align, tidak perlu unit
                      if ($properties === 'text-align') {
                          // Mapping nilai text-align
                          $alignValues = [
                              'center' => 'center',
                              'left' => 'left',
                              'right' => 'right',
                              // Bisa tambahkan nilai lain seperti justify jika diperlukan
                          ];
                          $value = $alignValues[$value] ?? $value;
                      } else {
                          $unit = $matches[2] ?? $this->getDefaultUnit($properties);
                          if (is_numeric($value)) {
                              $value .= $unit;
                          }
                      }
                      
                      // Tambahkan setiap properti ke array styles
                      foreach (explode(' ', $properties) as $prop) {
                          $styles[$prop] = $value;
                      }
                      break;
                  }
              }
              if (!$matched) {
                  $remainingClasses[] = $class;
              }
          }
          
          // Gabungkan style yang ada dengan style baru
          $styleAttr = '';
          if (!empty($styles)) {
              $styleString = '';
              foreach ($styles as $prop => $value) {
                  $styleString .= "$prop:$value;";
              }
              
              // Cek apakah sudah ada atribut style
              if (preg_match('/style=(["\']).*?\1/', $attributes, $styleMatches)) {
                  // $existingStyle = trim($styleMatches[2], '; ');
                  // $newStyle = $existingStyle . ($existingStyle ? ';' : '') . $styleString;
                  // $attributes = preg_replace('/style=(["\']).*?\1/', 'style="' . $newStyle . '"', $attributes);
              } else {
                  $attributes .= ' style="' . $styleString . '"';
          }
          }
          
          // Update atribut class dengan class yang tersisa
          if (!empty($remainingClasses)) {
              $attributes = preg_replace('/class=(["\']).*?\1/', 'class="' . implode(' ', $remainingClasses) . '"', $attributes);
          } else {
              $attributes = preg_replace('/\s*class=(["\']).*?\1/', '', $attributes);
          }
          
          return "<$tag$attributes>";
      }, $content);
  }

  /**
   * Memproses nilai warna dan mengembalikan nilai CSS yang sesuai
   * @param string $value Nilai warna original
   * @return string Nilai warna yang diproses
   */
  private function processColorValue(string $value): string
  {
      // Daftar warna kustom
      $customColors = [
          'primary' => '#007bff',
          'secondary' => '#6c757d',
          'success' => '#28a745',
          'danger' => '#dc3545',
          'warning' => '#ffc107',
          'info' => '#17a2b8',
          'light' => '#f8f9fa',
          'dark' => '#343a40',
          'white' => '#ffffff',
          'black' => '#000000',
          // Tambahkan warna kustom lainnya sesuai kebutuhan
      ];
      
      // Cek apakah nilai adalah warna kustom
      if (isset($customColors[strtolower($value)])) {
          return $customColors[strtolower($value)];
      }
      
      // Jika nilai sudah dalam format yang valid (hex, rgb, dll)
      return $value;
  }

  /**
   * Mendapatkan unit default berdasarkan properti
   * @param string $property Properti CSS
   * @return string Unit default
   */
  private function getDefaultUnit(string $property): string
  {
      // Unit default berdasarkan properti
      $timeProperties = ['transition2', 'animation', 'animation-duration', 'transition-duration'];
      $unitlessProperties = ['opacity', 'z-index', 'font-weight', 'flex', 'order', 'scale'];
      
      if (in_array($property, $timeProperties)) {
          return 'ms';
      }
      if (in_array($property, $unitlessProperties)) {
          return '';
      }
      return 'px';
  }

  /**
   * Parse font classes dan tambahkan Google Font link
   * @param string $content Konten yang akan diparsing
   * @return string Hasil parsing
   */
  private function domFontClasses(string &$content): void
  {
      $content = preg_replace_callback(
          '/class=(["\'])(.*?\bfont-([A-Za-z0-9_-]+).*?)\1/i',
          function($matches) {
              $fullClass = $matches[2];
              $fontFamily = $matches[3];
              
              // Konversi nama font ke format yang benar
              $fontName = str_replace('-', ' ', $fontFamily);
              
              // Cek apakah font sudah di-load
              if (!isset($this->loadedFonts[$fontName])) {
                  // Format URL Google Font
                  $fontUrl = 'https://fonts.googleapis.com/css2?family=' . 
                            str_replace(' ', '+', $fontName) . 
                            ':wght@400;500;600;700&display=swap';
                  
                  // Tambahkan link font ke header
                  $fontLink = sprintf(
                      '<link href="%s" rel="stylesheet">',
                      htmlspecialchars($fontUrl, ENT_QUOTES, 'UTF-8')
                  );
                  
                  // Tambahkan ke assets header
                  if (!isset($this->assets['header'])) {
                      $this->assets['header'] = [];
                  }
                  $this->assets['header'][] = $fontLink;
                  
                  $this->loadedFonts[$fontName] = true;
              }
              
              // Pisahkan class font- dari class lainnya
              $classes = explode(' ', $fullClass);
              $otherClasses = array_filter($classes, function($class) {
                  return !preg_match('/^font-/', $class);
              });
              
              // Gabungkan kembali class lainnya
              $newClasses = trim(implode(' ', $otherClasses));
              
              // Tambahkan font-family ke style elemen
              $styleAttr = sprintf('style="font-family: \'%s\', sans-serif;"', $fontName);
              
              // Jika masih ada class lain, tambahkan kembali
              $classAttr = $newClasses ? sprintf('class="%s"', $newClasses) : '';
              
              // Gabungkan atribut
              $attributes = trim($classAttr . ' ' . $styleAttr);
              
              // Return tag dengan atribut yang diperbarui
              return str_replace($matches[0], $attributes, $matches[0]);
          },
          $content
      );
  }

  // Tambahkan property untuk routing templates
  private $routingTemplates = [];

  /**
   * Menambahkan routing template
   * @param string $key Kunci routing
   * @param string $basePath Path dasar template
   */
  public function templateRouting(string $key, string $basePath): void 
  {
    // Normalisasi path
    $cleanPath = str_replace('\\', '/', rtrim($basePath, '/'));
    
    // Konversi relative path ke absolute path jika perlu
    if (!preg_match('/^[\/\\\\]|[a-zA-Z]:[\/\\\\]/', $cleanPath)) {
        $cleanPath = dirname(__DIR__) . '/' . $cleanPath;
    }
    
    error_log("Adding template routing - Key: $key, Path: $cleanPath");
    $this->routingTemplates[$key] = $cleanPath;
  }

  /**
   * Parse variabel template dalam defaultFile
   */
  private function domRoutingTemplates(string &$content): void 
  {
    // Proses variabel template terlebih dahulu
    $content = preg_replace_callback(
        '/\{([a-z0-9_]+)\}/i',
        function($matches) {
            $varName = $matches[1];
            return isset($this->_tpldata['.'][$varName]) ? $this->_tpldata['.'][$varName] : $matches[0];
        },
        $content
    );

    // Kemudian proses routing template
    foreach ($this->routingTemplates as $key => $basePath) {
        $pattern = '/\{' . preg_quote($key, '/') . '\:([^|}]+)(?:\|([^}]+))?\}/';
        
        $content = preg_replace_callback($pattern, function($matches) use ($basePath) {
            try {
                $requestedPath = trim($matches[1]);
                $defaultFile = isset($matches[2]) ? trim($matches[2]) : null;
                
                error_log("Processing route - Requested: $requestedPath, Default: $defaultFile");

                // Normalisasi paths
                $basePath = rtrim($basePath, '/');
                
                // Split path menjadi array untuk menangani subfolder
                $pathParts = array_filter(explode('/', trim($requestedPath, '/')));
                $originalPath = implode('/', $pathParts); // Simpan path asli
                $lastPart = end($pathParts);
                
                // Jika ada variabel page, ganti bagian terakhir path
                if (isset($this->_tpldata['.']['page'])) {
                    $lastIndex = count($pathParts) - 1;
                    $pathParts[$lastIndex] = $this->_tpldata['.']['page'];
                    $requestedPath = implode('/', $pathParts);
                }
                
                // Bangun full path
                $fullPath = $basePath;
                foreach ($pathParts as $part) {
                    $fullPath .= '/' . $part;
                }
                
                // Dapatkan directory dari full path
                $directory = dirname($fullPath);
                
                // Daftar ekstensi yang didukung
                $extensions = ['.html', '.php', '.tpl'];
                
                // 1. Coba cari file yang diminta langsung
                foreach ($extensions as $ext) {
                    $filePath = $fullPath . $ext;
                    error_log("Checking direct file: $filePath");
                    if (file_exists($filePath)) {
                        error_log("Found direct file: $filePath");
                        return $this->loadAndDomTemplate($filePath);
                    }
                }
                
                // 2. Coba cari file di directory yang sama dengan nama dari page variable
                if (isset($this->_tpldata['.']['page'])) {
                    $pageName = $this->_tpldata['.']['page'];
                    foreach ($extensions as $ext) {
                        $pageFilePath = $directory . '/' . $pageName . $ext;
                        error_log("Checking page file: $pageFilePath");
                        if (file_exists($pageFilePath)) {
                            error_log("Found page file: $pageFilePath");
                            return $this->loadAndDomTemplate($pageFilePath);
                        }
                    }
                }
                
                // 3. Coba load default file jika ada
                if ($defaultFile) {
                    // Hapus ekstensi dari default file jika ada
                    $defaultFile = pathinfo($defaultFile, PATHINFO_FILENAME);
                    foreach ($extensions as $ext) {
                        $defaultPath = $directory . '/' . $defaultFile . $ext;
                        error_log("Checking default file: $defaultPath");
                        if (file_exists($defaultPath)) {
                            error_log("Found default file: $defaultPath");
                            return $this->loadAndDomTemplate($defaultPath);
                        }
                    }
                }
                
                // 4. Coba kembali ke file intro.html di directory yang sama
                foreach ($extensions as $ext) {
                    $introPath = $directory . '/intro' . $ext;
                    error_log("Checking intro file: $introPath");
                    if (file_exists($introPath)) {
                        error_log("Falling back to intro file: $introPath");
                        return $this->loadAndDomTemplate($introPath);
                    }
                }
                
                // 5. Jika semua gagal, coba cari file index di directory tersebut
                foreach ($extensions as $ext) {
                    $indexPath = $directory . '/index' . $ext;
                    error_log("Checking index file: $indexPath");
                    if (file_exists($indexPath)) {
                        error_log("Found index file: $indexPath");
                        return $this->loadAndDomTemplate($indexPath);
                    }
                }
                
                error_log("No template file found for path: $requestedPath");
                return $this->handleTemplateNotFound($requestedPath);
                
            } catch (Exception $e) {
                error_log("Error in domRoutingTemplates: " . $e->getMessage());
                return "<!-- Template Error: " . htmlspecialchars($e->getMessage()) . " -->";
            }
        }, $content);
    }
  }

  /**
   * Load dan parse template file
   * @param string $path Path ke file template
   * @return string Hasil parsing template
   */
  private function loadAndDomTemplate(string $path): string 
  {
    try {
        error_log("Loading template from: " . $path);
        
        if (!file_exists($path)) {
            error_log("Template file not found: " . $path);
            return "<!-- Template not found: " . htmlspecialchars($path) . " -->";
        }

        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $content = '';
        
        // Load konten berdasarkan ekstensi file
        if ($ext === 'html') {
            ob_start();
            include $path;
            $content = ob_get_clean();
        } else {
            $content = file_get_contents($path);
        }

        if ($content === false || empty($content)) {
            error_log("Failed to read template or empty content: " . $path);
            return "<!-- Failed to read template: " . htmlspecialchars($path) . " -->";
        }

        error_log("Successfully loaded template content, length: " . strlen($content));
        
        try {
            // Proses blok dan bagian template
            $content = $this->processBlocks($content);
            $content = $this->processSections($content);
            
            // Proses utility classes
            $content = $this->domUtilityClasses($content);
            
            // Proses script imports
            $this->domScriptImports($content);
            
            // Proses assets
            $this->domAssets($content);
            
            // Tambahkan parseWidthClasses sebelum parsing lainnya
            $content = $this->parseWidthClasses($content);
            
            // Proses special variables
            $this->parseSpecialVariables($content);
            
            // Proses include templates
            $this->parseIncludeTemplates($content);
            
            // Proses kondisi if-elseif-else
            $this->processAdvancedConditions($content);
            
            // Update regex untuk menangkap filter
            $content = preg_replace_callback(
                "#\{([a-z0-9_.|()]*)\}#i",
                function($matches) {
                    // Split variable dan filter
                    $parts = explode('|', $matches[1]);
                    $varName = trim($parts[0]);
                    
                    // Ambil nilai dasar
                    $value = $this->_tpldata['.'][$varName] ?? $matches[0];
                    
                    // Terapkan filter jika ada
                    for ($i = 1; $i < count($parts); $i++) {
                        $filterStr = trim($parts[$i]);
                        // Parse filter dan argumennya
                        if (preg_match('/^([a-z_]+)(?:\((.*?)\))?$/i', $filterStr, $filterMatches)) {
                            $filterName = $filterMatches[1];
                            $arguments = isset($filterMatches[2]) ? 
                                array_map('trim', explode(',', $filterMatches[2])) : 
                                [];
                            $value = $this->applyFilter($value, $filterName, $arguments);
                        }
                    }
                    
                    return $value;
                },
                $content
            );

            // Proses language tags
            $content = preg_replace_callback(
                "#_lang\{(.*)\}#i",
                function($matches) {
                    return $this->showLanguageIndex ? 
                        ($this->languageData[$matches[1]] ?? $matches[1]) : 
                        ($this->languageData[$matches[1]] ?? '');
                },
                $content
            );

            $this->htmlStandard($content);
            $content = trim($content);

            return $content;

        } catch (Exception $e) {
            error_log("Error parsing template content: " . $e->getMessage());
            throw new Exception("Error parsing template: " . $e->getMessage());
        }
        
    } catch (Exception $e) {
        error_log("Error loading template " . $path . ": " . $e->getMessage());
        return "<!-- Template Error: " . htmlspecialchars($e->getMessage()) . " -->";
    }
  }

  /**
   * Handle template tidak ditemukan
   * @param string $path Path template yang dicari
   * @return string Pesan error atau template default
   */
  private function handleTemplateNotFound(string $path): string 
  {
    if (self::DEVELOPMENT_MODE) {
        $message = "Template not found: $path";
        error_log($message);
        return "<!-- $message -->";
    }
    // Dalam mode production, coba load template 404 default
    try {
        $default404 = dirname(__DIR__) . '/public/404.html';
        if (file_exists($default404)) {
            $content = file_get_contents($default404);
            if ($content !== false) {
                return $this->DomHTML($content, true, true, false);
            }
        }
    } catch (Exception $e) {
        error_log("Error loading 404 template: " . $e->getMessage());
    }
    
    return '';
  }

  // Konstanta untuk URL processing
  private const JAVASCRIPT_VOID = 'javascript:void(0);';
  private const EXTERNAL_URL_PATTERN = '/^(https?:\/\/|javascript:)/i';

  /**
   * Parse URL navigasi dalam template
   * @param string &$content Konten yang akan diparse
   */
  private function domUrlNavigasi(string &$content): void {
    $linkProcessor = new NgoreiLink();
    $linkProcessor->processLinks($content);
  }

  /**
   * Membangun URL internal lengkap
   */
  private function buildInternalUrl(string $url): string 
  {
    return rtrim(HOST, '/') . '/' . ltrim($url, '/');
  }

  private function domImageSrc(string &$content): void 
  {
    // Parse semua tag <img> dengan src
    $content = preg_replace_callback(
        '/<img\s+([^>]*?)src=(["\'])((?!(?:https?:)?\/\/|\{)[^"\']*)\2([^>]*?)>/i',
        function($matches) {
            $attributes = $matches[1];      // Atribut sebelum src
            $quote = $matches[2];           // Tipe kutip (' atau ")
            $imagePath = $matches[3];       // Path gambar dalam src
            $endAttributes = $matches[4];    // Atribut setelah src
            
            // Skip URL eksternal
            if (preg_match('/^https?:\/\//i', $imagePath)) {
                return "<img {$attributes}src={$quote}{$imagePath}{$quote}{$endAttributes}>";
            }
            
            // Skip data URL (base64)
            if (strpos($imagePath, 'data:image/') === 0) {
                return "<img {$attributes}src={$quote}{$imagePath}{$quote}{$endAttributes}>";
            }
            
            // Normalisasi path gambar
            $imagePath = ltrim($imagePath, '/');
            
            // Tambahkan HOST ke path gambar internal
            $fullImagePath = rtrim(HOST, '/') . '/' . $imagePath;
            
            // Cek apakah file gambar ada (opsional)
            $localPath = $_SERVER['DOCUMENT_ROOT'] . '/' . $imagePath;
            if (!file_exists($localPath)) {
                error_log("Warning: Image not found: {$localPath}");
                // Opsional: Gunakan gambar placeholder jika file tidak ditemukan
                // $fullImagePath = rtrim(HOST, '/') . '/assets/img/placeholder.png';
            }
            
            return "<img {$attributes}src={$quote}{$fullImagePath}{$quote}{$endAttributes}>";
        },
        $content
    );
  }

  /**
   * Mengubah elemen View menjadi script template
   * @param string &$content Konten yang akan diproses
   */
  private function domViewTemplate(string &$content): void 
  {
      // Pattern untuk mencocokkan elemen View dengan id
      $pattern = '/<View\s+([^>]*?)id=(["\'])(.*?)\2([^>]*?)>(.*?)<\/View>/is';
      
      $content = preg_replace_callback($pattern, function($matches) {
          $attributes = $matches[1];      // Atribut sebelum id
          $quote = $matches[2];           // Tipe kutip (' atau ")
          $id = $matches[3];             // Nilai id
          $endAttributes = $matches[4];   // Atribut setelah id
          $innerContent = $matches[5];    // Konten dalam View
          
          // Bersihkan whitespace berlebih tapi pertahankan indentasi
          $innerContent = preg_replace('/>\s+</', '><', $innerContent);
          $innerContent = trim($innerContent);
          
          // Format output script template
          return sprintf(
              '<script type="text/template" id=%s%s%s>%s</script>',
              $quote,
              htmlspecialchars($id, ENT_QUOTES),
              $quote,
              $innerContent
          );
      }, $content);
  }

  /**
   * Mengubah elemen div dengan atribut require menjadi format template
   * @param string &$content Konten yang akan diproses
   */
  private function domDirTemplate(string &$content): void 
  {
      // Pattern untuk mencocokkan div dengan atribut require
      $pattern = '/<div\s+([^>]*?)require=(["\'])(.*?)\2([^>]*?)>\s*<\/div>/is';
      
      $content = preg_replace_callback($pattern, function($matches) {
          $attributes = $matches[1];      // Atribut sebelum require
          $quote = $matches[2];           // Tipe kutip (' atau ")
          $requirePath = $matches[3];     // Nilai path dari require
          $endAttributes = $matches[4];   // Atribut setelah require
          
          // Normalisasi path
          $requirePath = trim($requirePath, '/');
          
          // Format output sesuai template yang diinginkan
          return sprintf('{require:%s}', $requirePath);
          
      }, $content);
  }

  /**
   * Mengubah elemen div dengan atribut routing menjadi format template
   * @param string &$content Konten yang akan diproses
   */
  private function domRoutingDiv(string &$content): void 
  {
    $pattern = '/<Routing\s+path=(["\'])(.*?)\1[^>]*><\/Routing>/is';
    
    $content = preg_replace_callback($pattern, function($matches) {
        $routingPath = trim($matches[2]);
        
        try {
            // 1. Split path dan proses
            $parts = explode('|', $routingPath);
            $defaultPath = trim($parts[0]);
            $requestedPath = isset($parts[1]) ? trim($parts[1]) : '';
            
            // 2. Proses path variable jika ada
            if (!empty($requestedPath) && strpos($requestedPath, '{path}') !== false) {
                // Ambil nilai path dari template data
                $pathValue = $this->_tpldata['.']['path'] ?? '';
                
                // Ganti {path} dengan nilai aktual
                $requestedPath = str_replace('{path}', $pathValue, $requestedPath);
            }
            
            // 3. Proses variabel template lainnya jika ada
            if (!empty($requestedPath)) {
                $requestedPath = preg_replace_callback(
                    '/\{([a-z0-9_]+)\}|(?<![a-z0-9_])([a-z0-9_]+)(?![a-z0-9_])/i',
                    function($varMatches) {
                        $varName = !empty($varMatches[1]) ? $varMatches[1] : $varMatches[2];
                        return isset($this->_tpldata['.'][$varName]) ? 
                               $this->_tpldata['.'][$varName] : 
                               $varMatches[0];
                    },
                    $requestedPath
                );
            }
            
            // 4. Validasi path yang akan digunakan
            $pathToUse = !empty($requestedPath) ? $requestedPath : $defaultPath;
            
            // 5. Cari file menggunakan getDirList
            $dirInfo = tatiye::getDirList($pathToUse, $defaultPath);
            
            // 6. Baca dan proses konten file
            if (!file_exists($dirInfo['dir'])) {
                throw new Exception("File tidak ditemukan: " . $dirInfo['dir']);
            }
            
            $fileContent = file_get_contents($dirInfo['dir']);
            if ($fileContent === false) {
                throw new Exception("Gagal membaca file: " . $dirInfo['dir']);
            }
            
            // 7. Return konten yang sudah diproses
            return $this->DomHTML($fileContent, true, true, false);
            
        } catch (\Exception $e) {
            error_log("Error in domRoutingDiv: " . $e->getMessage());
            // Jika terjadi error, coba gunakan default path
            try {
                $dirInfo = tatiye::getDirList($defaultPath, $defaultPath);
                $fileContent = file_get_contents($dirInfo['dir']);
                return $this->DomHTML($fileContent, true, true, false);
            } catch (\Exception $fallbackError) {
                error_log("Fallback error in domRoutingDiv: " . $fallbackError->getMessage());
                return "<!-- Error: " . htmlspecialchars($e->getMessage()) . " -->";
            }
        }
    }, $content);
  }

  /**
   * Memproses query dan menampilkan data dalam template
   * @param string &$content Konten yang akan diproses
   */
  private function domQueryData(string &$content): void 
  {
    $queryData = new NgoreiQuery();
    $queryData->processQueryData($content);
  }

  /**
   * Menangani request ke URL eksternal
   * @param string $url URL eksternal yang akan diproses
   * @return string HTML yang telah diproses
   */
  public function handleExternalUrl($url) {
      // Ambil konten dari URL eksternal
      $content = @file_get_contents($url);
      if ($content === false) {
          throw new \Exception('Gagal mengambil konten dari URL: ' . $url);
      }
      
      // Proses konten HTML
      $processedContent = $this->processExternalContent($content, $url);
      
      // Terapkan template dan assets
      return $this->applyTemplate($processedContent);
  }

  /**
   * Memproses konten dari URL eksternal
   * @param string $content Konten HTML mentah
   * @param string $baseUrl URL asal untuk memperbaiki path relatif
   * @return string Konten yang telah diproses
   */
  protected function processExternalContent($content, $baseUrl) {
      // Parse base URL
      $urlParts = parse_url($baseUrl);
      $basePath = $urlParts['scheme'] . '://' . $urlParts['host'];
      if (isset($urlParts['port'])) {
          $basePath .= ':' . $urlParts['port'];
      }
      
      // Perbaiki path relatif menjadi absolut
      $content = preg_replace(
          [
              '/(src|href)=[\'"](?!\s*(?:https?:)?\/\/)(\/?)([^\'"\s>]+?)[\'"]/i',
              '/(url\([\'"]?)(?!\s*(?:https?:)?\/\/)(\/?)([^\'"\s>)]+?)([\'"]?\))/i'
          ],
          [
              '$1="' . $basePath . '/$3"',
              '$1' . $basePath . '/$3$4'
          ],
          $content
      );
      
      // Tambahkan class atau atribut khusus jika diperlukan
      $content = str_replace('<body', '<body class="external-content"', $content);
      
      return $content;
  }

  /**
   * Menerapkan template ke konten
   * @param string $content Konten yang akan dibungkus template
   * @return string HTML final
   */
  protected function applyTemplate($content) {
      // Ambil konten antara <body> dan </body>
      preg_match('/<body.*?>(.*?)<\/body>/is', $content, $matches);
      $bodyContent = $matches[1] ?? $content;
      
      // Terapkan template
      $template = $this->getTemplateContent();
      return str_replace('{{content}}', $bodyContent, $template);
  }

  // Tambahkan method baru setelah method handleExternalUrl

  /**
   * Mengekstrak path setelah tanda "-" dari URL
   * @param string $url URL yang akan diproses
   * @return string Path yang diekstrak
   */
  public function extractPathAfterDash(string $url): string 
  {
      // Hapus trailing slash
      $url = rtrim($url, '/');
      
      // Cari posisi tanda "-"
      $position = strpos($url, '-');
      
      if ($position === false) {
          return ''; // Return string kosong jika tidak ada tanda "-"
      }
      
      // Ambil bagian setelah tanda "-"
      $path = substr($url, $position + 1);
      
      // Bersihkan path dari karakter yang tidak diinginkan
      $path = trim($path, '/');
      
      return $path;
  }

  /**
   * Menangani URL dengan format khusus menggunakan tanda "-"
   * @param string $url URL lengkap yang akan diproses
   * @return string|null Path yang valid atau null jika format tidak sesuai
   */
  public function handleDashUrl(string $url): ?string 
  {
      try {
          // Parse URL untuk mendapatkan path
          $parsedUrl = parse_url($url);
          $fullPath = $parsedUrl['path'] ?? '';
          
          // Jika tidak ada tanda "-", return null
          if (strpos($fullPath, '-') === false) {
              return null;
          }
          
          // Ekstrak path setelah tanda "-"
          $extractedPath = $this->extractPathAfterDash($fullPath);
          
          if (empty($extractedPath)) {
              return null;
          }
          
          // Validasi path
          if (!preg_match('/^[a-zA-Z0-9\/_-]+$/', $extractedPath)) {
              throw new Exception("Path tidak valid: " . $extractedPath);
          }
          
          return $extractedPath;
          
      } catch (Exception $e) {
          error_log("Error handling dash URL: " . $e->getMessage());
          return null;
      }
  }

  // Tambahkan method baru untuk menangani {path}

  /**
   * Menangani template variable {path}
   * @param string $url URL yang akan diproses
   * @return void
   */
  public function setPath(string $url): void
  {
      try {
          // Ekstrak path dari URL menggunakan handleDashUrl
          $path = $this->handleDashUrl($url);
          
          // Set path ke template variable
          if ($path !== null) {
              $this->val('path', $path);
          } else {
              // Jika path tidak valid, set nilai default atau kosong
              $this->val('path', '');
          }
          
      } catch (Exception $e) {
          error_log("Error setting path: " . $e->getMessage());
          $this->val('path', ''); // Set nilai default jika terjadi error
      }
  }

  /**
   * Mengubah elemen Navigation menjadi format template
   * @param string &$content Konten yang akan diproses
   */
  private function domNavigation(string &$content): void 
  {
    // Perbaiki pattern untuk menangani whitespace dan newline
    $pattern = '/<Navigation\s+page\s*=\s*(["\'])(.*?)\1\s*(?:default\s*=\s*(["\'])(.*?)\3)?\s*>\s*<\/Navigation>/is';
    
    $content = preg_replace_callback($pattern, function($matches) {
        $pagePath = trim($matches[2]);
        $defaultPath = isset($matches[4]) ? trim($matches[4]) : '';
        
        try {
            // 1. Proses path variable jika ada
            if (strpos($pagePath, '{path}') !== false) {
                $pathValue = $this->_tpldata['.']['path'] ?? '';
                $pagePath = str_replace('{path}', $pathValue, $pagePath);
            }
            
            // 2. Proses variabel template lainnya jika ada
            $pagePath = preg_replace_callback(
                '/\{([a-z0-9_]+)\}|(?<![a-z0-9_])([a-z0-9_]+)(?![a-z0-9_])/i',
                function($varMatches) {
                    $varName = !empty($varMatches[1]) ? $varMatches[1] : $varMatches[2];
                    return isset($this->_tpldata['.'][$varName]) ? 
                           $this->_tpldata['.'][$varName] : 
                           $varMatches[0];
                },
                $pagePath
            );
            
            // 3. Validasi path yang akan digunakan
            $pathToUse = !empty($pagePath) ? $pagePath : $defaultPath;
            
            // Debug log
            error_log("Navigation - Path to use: " . $pathToUse);
            
            // 4. Cari file menggunakan getDirList dengan ekstensi yang didukung
            $extensions = ['', '.html', '.php', '.tpl']; // Tambahkan '' untuk mencoba tanpa ekstensi
            $fileFound = false;
            $fileContent = '';
            
            foreach ($extensions as $ext) {
                $testPath = $pathToUse . $ext;
                error_log("Navigation - Testing path: " . $testPath);
                
                try {
                    // Tambahkan parameter kedua untuk getDirList
                    $dirInfo = tatiye::getDirList($testPath, $testPath);
                    error_log("Navigation - Dir info: " . print_r($dirInfo, true));
                    
                    if (!empty($dirInfo['dir']) && file_exists($dirInfo['dir'])) {
                        $fileContent = file_get_contents($dirInfo['dir']);
                        if ($fileContent !== false) {
                            $fileFound = true;
                            error_log("Navigation - File found at: " . $dirInfo['dir']);
                            // Proses konten file dengan DomHTML
                            return $this->DomHTML($fileContent, true, true, false);
                        }
                    }
                } catch (\Exception $e) {
                    error_log("Navigation - Error testing path $testPath: " . $e->getMessage());
                    continue;
                }
            }
            
            // Jika file tidak ditemukan dan ada default path, coba default path
            if (!$fileFound && !empty($defaultPath)) {
                error_log("Navigation - Trying default path: " . $defaultPath);
                foreach ($extensions as $ext) {
                    $testPath = $defaultPath . $ext;
                    try {
                        // Tambahkan parameter kedua untuk getDirList
                        $dirInfo = tatiye::getDirList($testPath, $testPath);
                        if (!empty($dirInfo['dir']) && file_exists($dirInfo['dir'])) {
                            $fileContent = file_get_contents($dirInfo['dir']);
                            if ($fileContent !== false) {
                                // Proses konten file dengan DomHTML
                                return $this->DomHTML($fileContent, true, true, false);
                            }
                        }
                    } catch (\Exception $e) {
                        error_log("Navigation - Error testing default path $testPath: " . $e->getMessage());
                        continue;
                    }
                }
            }
            
            throw new Exception("File tidak ditemukan untuk path: $pathToUse dan default: $defaultPath");
            
        } catch (\Exception $e) {
            error_log("Error in domNavigation: " . $e->getMessage());
            return sprintf(
                "<!-- Navigation Error: %s -->",
                htmlspecialchars($e->getMessage())
            );
        }
    }, $content);
  }

  protected function handleCssJsRequest($path) {
    // Ubah dari 'doc/' menjadi 'public/'
    if (strpos($path, 'public/') === 0) {
        $filePath = dirname(__DIR__) . '/' . $path;
        
        if (file_exists($filePath)) {
            $ext = pathinfo($filePath, PATHINFO_EXTENSION);
            
            // Set content type yang sesuai
            switch ($ext) {
                case 'css':
                    header('Content-Type: text/css');
                    break;
                case 'js':
                    header('Content-Type: application/javascript');
                    break;
                default:
                    return false;
            }
            
            // Output file content
            readfile($filePath);
            return true;
        }
    }
    return false;
  }
}

?>

 