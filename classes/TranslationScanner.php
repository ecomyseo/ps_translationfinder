<?php
/**
 * Translation Finder
 *
 * @author    Ecom Experts <ecomyseo@gmail.com>
 * @copyright 2026 Ecom Experts
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Rastrea todas las fuentes de traduccion de PrestaShop (XLIFF del core/theme/modulos,
 * ficheros legacy .php, plantillas de email y tabla ps_translation) y devuelve los
 * resultados que coinciden con un termino, clasificados por apartado.
 *
 * Las traducciones editadas se almacenan en ps_translation con el dominio SIN puntos
 * (formato real de PrestaShop), que coincide con el atributo "original" del XLIFF.
 */
class TranslationScanner
{
    /** @var int */
    protected $id_lang;
    /** @var string Locale tipo es-ES. */
    protected $locale;
    /** @var string Codigo ISO tipo es. */
    protected $iso;
    /** @var string Nombre del theme activo. */
    protected $theme;
    /** @var array Mapa de traducciones en BD: clave "domain\0key\0theme" => translation. */
    protected $db_map = [];
    /** @var array Lista plana de filas de BD para busqueda directa. */
    protected $db_rows = [];
    /** @var string Regex anclada (coincidencia de campo completo). */
    protected $re_field = '';
    /** @var string Regex sin anclar (coincidencia de subcadena, para emails). */
    protected $re_contains = '';
    /** @var array|null Cache de tablas traducibles descubiertas. */
    protected $lang_tables = null;

    /**
     * @param int    $id_lang
     * @param string $theme   Nombre del theme activo (opcional)
     */
    public function __construct($id_lang, $theme = '')
    {
        $this->id_lang = (int) $id_lang;

        $lang = new Language($this->id_lang);
        $this->iso = $lang->iso_code ? $lang->iso_code : 'en';
        $this->locale = $lang->locale ? $lang->locale : $this->iso . '-' . Tools::strtoupper($this->iso);

        if ($theme) {
            $this->theme = $theme;
        } else {
            $context = \Context::getContext();
            $this->theme = ($context && $context->shop && $context->shop->theme_name)
                ? $context->shop->theme_name
                : (defined('_THEME_NAME_') ? _THEME_NAME_ : 'classic');
        }

        $this->loadDbMap();
    }

    /**
     * Carga las traducciones existentes en ps_translation para el idioma.
     */
    protected function loadDbMap()
    {
        try {
            $rows = Db::getInstance()->executeS(
                'SELECT `key`, `translation`, `domain`, `theme` FROM `' . _DB_PREFIX_ . 'translation` WHERE `id_lang` = ' . (int) $this->id_lang
            );
        } catch (Exception $e) {
            TranslationfinderLogger::log('loadDbMap error: ' . $e->getMessage(), 'scanner');
            $rows = [];
        }

        if (!is_array($rows)) {
            return;
        }
        foreach ($rows as $r) {
            $theme = isset($r['theme']) ? (string) $r['theme'] : '';
            $mapKey = $r['domain'] . "\0" . $r['key'] . "\0" . $theme;
            $this->db_map[$mapKey] = (string) $r['translation'];
            $this->db_rows[] = $r;
        }
    }

    /**
     * Devuelve la traduccion vigente en BD para un dominio/clave/theme dados, o null.
     *
     * @param string $domain
     * @param string $key
     * @param string $theme
     *
     * @return string|null
     */
    protected function dbValue($domain, $key, $theme)
    {
        $k = $domain . "\0" . $key . "\0" . $theme;
        if (array_key_exists($k, $this->db_map)) {
            return $this->db_map[$k];
        }
        // Reintento sin theme (las traducciones globales se guardan con theme vacio).
        $k2 = $domain . "\0" . $key . "\0" . '';
        if ($theme !== '' && array_key_exists($k2, $this->db_map)) {
            return $this->db_map[$k2];
        }

        return null;
    }

    /**
     * Construye una expresion regular a partir del termino con comodines '%'.
     * Reglas: sin '%' = coincidencia exacta; '%' equivale a "cualquier cosa".
     *   palabra    -> ^palabra$        (solo esa palabra/frase, exacta)
     *   %palabra   -> ^.*palabra$       (cualquier cosa + palabra + nada)
     *   palabra%   -> ^palabra.*$       (palabra + cualquier cosa)
     *   %palabra%  -> ^.*palabra.*$     (cualquier cosa + palabra + cualquier cosa)
     *
     * @param string $term
     * @param bool   $anchored true para coincidir con el valor completo del campo
     *
     * @return string
     */
    protected function termToRegex($term, $anchored)
    {
        $parts = explode('%', $term);
        foreach ($parts as &$p) {
            // Se admiten espacios pegados al comodin: "% palabra %" == "%palabra%".
            // Se recortan los espacios de los extremos de cada tramo (los espacios
            // internos de una frase, p.ej. "productos destacados", se conservan).
            $p = preg_quote(trim($p), '/');
        }
        unset($p);
        $pattern = implode('.*', $parts);
        if ($anchored) {
            $pattern = '^' . $pattern . '$';
        }

        // Modificadores: i=insensible may/min, u=unicode, s=DOTALL (el . cruza saltos de
        // linea). 's' es CLAVE para encontrar texto dentro de contenido HTML multilinea
        // (bloques de texto, descripciones, etc.).
        return '/' . $pattern . '/ius';
    }

    /**
     * Comprueba si el valor de un campo coincide con el termino (modo anclado/exacto).
     *
     * @param string $value
     * @param string $regex
     *
     * @return bool
     */
    protected function matchField($value, $regex)
    {
        return (bool) @preg_match($regex, (string) $value);
    }

    /**
     * Busca un termino en las fuentes de la categoria indicada.
     *
     * @param string $term
     * @param string $category Una de TranslationCatalog::CAT_*
     * @param int    $limit
     *
     * @return array ['rows' => array, 'truncated' => bool, 'total' => int]
     */
    public function search($term, $category, $limit = 300, $includeContent = false)
    {
        $term = trim((string) $term);
        $rows = [];
        $truncated = false;

        if ($term === '') {
            return ['rows' => [], 'truncated' => false, 'total' => 0];
        }

        // Prepara las dos regex (campo completo y subcadena) segun los comodines '%'.
        $this->re_field = $this->termToRegex($term, true);
        $this->re_contains = $this->termToRegex($term, false);

        $cats = ($category === TranslationCatalog::CAT_ALL || $category === '')
            ? [TranslationCatalog::CAT_FRONT, TranslationCatalog::CAT_BACK, TranslationCatalog::CAT_MODULES, TranslationCatalog::CAT_EMAILS, TranslationCatalog::CAT_OTHER]
            : [$category];

        $seen = [];

        // El contenido de BD (tablas *_lang) es una categoria APARTE: solo se rastrea
        // cuando se selecciona explicitamente, nunca dentro de "Todos" (es mas pesado).
        if ($category === TranslationCatalog::CAT_CONTENT) {
            $this->scanContent($term, $rows, $seen, $limit, $truncated);

            return ['rows' => $rows, 'truncated' => $truncated, 'total' => count($rows)];
        }

        foreach ($cats as $cat) {
            if ($cat === TranslationCatalog::CAT_EMAILS) {
                $this->scanEmails($term, $rows, $seen, $limit, $truncated);
            } else {
                $this->scanXliff($term, $cat, $rows, $seen, $limit, $truncated);
                $this->scanLegacy($term, $cat, $rows, $seen, $limit, $truncated);
            }
            if ($truncated) {
                break;
            }
        }

        // Filas que solo existen en BD (p.ej. dominios Emails* o textos personalizados).
        $this->scanDbOnly($term, $cats, $rows, $seen, $limit, $truncated);

        // En "Todos", si se solicita, incluir tambien el contenido de las tablas *_lang.
        if (!$truncated && $includeContent
            && ($category === TranslationCatalog::CAT_ALL || $category === '')) {
            $this->scanContent($term, $rows, $seen, $limit, $truncated);
        }

        return ['rows' => $rows, 'truncated' => $truncated, 'total' => count($rows)];
    }

    /**
     * Devuelve los patrones glob de ficheros XLIFF a rastrear segun la categoria.
     *
     * @param string $category
     *
     * @return array Lista de ['glob' => string, 'theme' => string]
     */
    protected function getXliffJobs($category)
    {
        $jobs = [];
        $root = rtrim(_PS_ROOT_DIR_, '/\\') . '/';
        $localeDirs = [
            $root . 'translations/' . $this->locale . '/',
            $root . 'app/Resources/translations/' . $this->locale . '/',
            $root . 'app/Resources/translations/default/',
        ];

        if ($category === TranslationCatalog::CAT_FRONT) {
            foreach ($localeDirs as $d) {
                $jobs[] = ['glob' => $d . 'Shop*.xlf', 'theme' => ''];
            }
            $themeDir = rtrim(_PS_ALL_THEMES_DIR_, '/\\') . '/' . $this->theme . '/translations/' . $this->locale . '/';
            $jobs[] = ['glob' => $themeDir . '*.xlf', 'theme' => $this->theme];
        } elseif ($category === TranslationCatalog::CAT_BACK) {
            foreach ($localeDirs as $d) {
                $jobs[] = ['glob' => $d . 'Admin*.xlf', 'theme' => ''];
            }
        } elseif ($category === TranslationCatalog::CAT_MODULES) {
            $jobs[] = ['glob' => rtrim(_PS_MODULE_DIR_, '/\\') . '/*/translations/' . $this->locale . '/*.xlf', 'theme' => ''];
        } elseif ($category === TranslationCatalog::CAT_OTHER) {
            foreach ($localeDirs as $d) {
                $jobs[] = ['glob' => $d . '*.xlf', 'theme' => ''];
            }
        }

        return $jobs;
    }

    /**
     * Rastrea ficheros XLIFF.
     */
    protected function scanXliff($term, $category, array &$rows, array &$seen, $limit, &$truncated)
    {
        foreach ($this->getXliffJobs($category) as $job) {
            $files = glob($job['glob']);
            if (!is_array($files)) {
                continue;
            }
            foreach ($files as $file) {
                $this->parseXliffFile($file, $term, $category, $job['theme'], $rows, $seen, $limit, $truncated);
                if ($truncated) {
                    return;
                }
            }
        }
    }

    /**
     * Parsea un fichero XLIFF y anade las coincidencias.
     */
    protected function parseXliffFile($file, $term, $category, $theme, array &$rows, array &$seen, $limit, &$truncated)
    {
        $xml = @simplexml_load_file($file);
        if ($xml === false) {
            return;
        }

        // El DOMINIO es el nombre del fichero sin locale ni extension (p.ej. ShopThemeCatalog).
        // OJO: el atributo "original" del XLIFF es la RUTA del fichero fuente, NO el dominio.
        $domain = preg_replace('/(\.[A-Za-z]{2,3}-[A-Za-z]{2,4})?\.xlf$/', '', basename($file));
        $category_real = TranslationCatalog::classifyDomain($domain);

        // Si el filtro es "otros", saltar lo que ya cubren las otras categorias.
        if ($category === TranslationCatalog::CAT_OTHER
            && in_array($category_real, [TranslationCatalog::CAT_FRONT, TranslationCatalog::CAT_BACK, TranslationCatalog::CAT_MODULES, TranslationCatalog::CAT_EMAILS], true)) {
            return;
        }

        // Las traducciones de tema (dominios Shop*) se guardan/leen con el theme activo;
        // el resto (Admin*, Modules*, Emails*) con theme NULL. Asi lo hace PrestaShop.
        $rowTheme = (stripos($domain, 'Shop') === 0) ? $this->theme : '';

        foreach ($xml->file as $fileNode) {
            $body = isset($fileNode->body) ? $fileNode->body : $fileNode;
            foreach ($body->{'trans-unit'} as $unit) {
                $source = (string) $unit->source;
                $target = (string) $unit->target;
                if ($source === '' && $target === '') {
                    continue;
                }
                if (!$this->matchField($source, $this->re_field) && !$this->matchField($target, $this->re_field)) {
                    continue;
                }

                $dbVal = $this->dbValue($domain, $source, $rowTheme);
                $current = ($dbVal !== null) ? $dbVal : $target;

                $row = TranslationCatalog::makeRow([
                    'origin' => TranslationCatalog::ORIGIN_FILE,
                    'category' => $category_real,
                    'domain' => $domain,
                    'source' => $source,
                    'translation' => $current,
                    'file' => $this->relPath($file),
                    'theme' => $rowTheme,
                    'editable' => true,
                ]);
                if (isset($seen[$row['id']])) {
                    continue;
                }
                $seen[$row['id']] = true;
                $rows[] = $row;
                if (count($rows) >= $limit) {
                    $truncated = true;

                    return;
                }
            }
        }
    }

    /**
     * Rastrea ficheros legacy de traduccion (.php con hashes) de modulos.
     */
    protected function scanLegacy($term, $category, array &$rows, array &$seen, $limit, &$truncated)
    {
        if ($category !== TranslationCatalog::CAT_MODULES) {
            return;
        }
        $files = glob(rtrim(_PS_MODULE_DIR_, '/\\') . '/*/translations/' . $this->iso . '.php');
        if (!is_array($files)) {
            return;
        }
        foreach ($files as $file) {
            $module = basename(dirname(dirname($file)));
            $_MODULE = [];
            // El fichero legacy define $_MODULE; lo aislamos en una funcion.
            $data = $this->includeLegacy($file);
            if (!is_array($data)) {
                continue;
            }
            foreach ($data as $hash => $value) {
                if (!$this->matchField($value, $this->re_field)) {
                    continue;
                }
                $row = TranslationCatalog::makeRow([
                    'origin' => TranslationCatalog::ORIGIN_LEGACY,
                    'category' => TranslationCatalog::CAT_MODULES,
                    'domain' => 'legacy:' . $module,
                    'source' => $hash,
                    'translation' => (string) $value,
                    'file' => $this->relPath($file),
                    'theme' => '',
                    'editable' => true,
                ]);
                if (isset($seen[$row['id']])) {
                    continue;
                }
                $seen[$row['id']] = true;
                $rows[] = $row;
                if (count($rows) >= $limit) {
                    $truncated = true;

                    return;
                }
            }
        }
    }

    /**
     * Incluye un fichero legacy de forma aislada y devuelve el array $_MODULE.
     *
     * @param string $file
     *
     * @return array|null
     */
    protected function includeLegacy($file)
    {
        // Los ficheros legacy suelen declarar `global $_MODULE`, por lo que hay que
        // capturar la variable global tras el include (no la local).
        global $_MODULE;
        $_MODULE = [];
        try {
            include $file;
        } catch (Exception $e) {
            return null;
        }

        return isset($_MODULE) && is_array($_MODULE) ? $_MODULE : null;
    }

    /**
     * Rastrea plantillas de email (core, theme y modulos) buscando el termino en su contenido.
     */
    protected function scanEmails($term, array &$rows, array &$seen, $limit, &$truncated)
    {
        $dirs = [
            rtrim(_PS_MAIL_DIR_, '/\\') . '/' . $this->iso . '/',
            rtrim(_PS_ALL_THEMES_DIR_, '/\\') . '/' . $this->theme . '/mails/' . $this->iso . '/',
        ];
        $moduleMails = glob(rtrim(_PS_MODULE_DIR_, '/\\') . '/*/mails/' . $this->iso . '/', GLOB_ONLYDIR);
        if (is_array($moduleMails)) {
            $dirs = array_merge($dirs, $moduleMails);
        }

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $files = array_merge(
                (array) glob($dir . '*.html'),
                (array) glob($dir . '*.txt')
            );
            foreach ($files as $file) {
                $content = (string) @file_get_contents($file);
                if ($content === '' || !$this->matchField($content, $this->re_contains)) {
                    continue;
                }
                $row = TranslationCatalog::makeRow([
                    'origin' => TranslationCatalog::ORIGIN_EMAIL,
                    'category' => TranslationCatalog::CAT_EMAILS,
                    'domain' => 'mail:' . basename($file),
                    'source' => $term,
                    'translation' => $term,
                    'file' => $this->relPath($file),
                    'theme' => '',
                    'editable' => true,
                ]);
                $row['snippet'] = $this->snippet($content, $term);
                if (isset($seen[$row['id']])) {
                    continue;
                }
                $seen[$row['id']] = true;
                $rows[] = $row;
                if (count($rows) >= $limit) {
                    $truncated = true;

                    return;
                }
            }
        }
    }

    /**
     * Anade filas que solo existen en ps_translation (no estaban en ficheros).
     */
    protected function scanDbOnly($term, array $cats, array &$rows, array &$seen, $limit, &$truncated)
    {
        if ($truncated) {
            return;
        }
        foreach ($this->db_rows as $r) {
            $domain = (string) $r['domain'];
            $cat = TranslationCatalog::classifyDomain($domain);
            if (!in_array($cat, $cats, true)) {
                continue;
            }
            if (!$this->matchField((string) $r['key'], $this->re_field) && !$this->matchField((string) $r['translation'], $this->re_field)) {
                continue;
            }
            $theme = isset($r['theme']) ? (string) $r['theme'] : '';
            $row = TranslationCatalog::makeRow([
                'origin' => TranslationCatalog::ORIGIN_DB,
                'category' => $cat,
                'domain' => $domain,
                'source' => (string) $r['key'],
                'translation' => (string) $r['translation'],
                'file' => 'ps_translation (BD)',
                'theme' => $theme,
                'editable' => true,
            ]);
            if (isset($seen[$row['id']])) {
                continue;
            }
            $seen[$row['id']] = true;
            $rows[] = $row;
            if (count($rows) >= $limit) {
                $truncated = true;

                return;
            }
        }
    }

    /**
     * Extrae un fragmento de contexto alrededor de la primera coincidencia.
     *
     * @param string $content
     * @param string $term
     *
     * @return string
     */
    protected function snippet($content, $term)
    {
        $pos = stripos($content, $term);
        if ($pos === false) {
            return '';
        }
        $start = max(0, $pos - 60);
        $frag = Tools::substr($content, $start, Tools::strlen($term) + 120);

        return trim(preg_replace('/\s+/', ' ', strip_tags($frag)));
    }

    /**
     * Convierte una ruta absoluta en relativa a la raiz de PrestaShop.
     *
     * @param string $file
     *
     * @return string
     */
    protected function relPath($file)
    {
        $root = rtrim(_PS_ROOT_DIR_, '/\\');
        $file = str_replace('\\', '/', $file);
        $root = str_replace('\\', '/', $root);

        return ltrim(str_replace($root, '', $file), '/');
    }

    /**
     * Descubre las tablas *_lang del prefijo y, por cada una, sus columnas de texto,
     * su clave primaria y si tiene id_lang. La estructura se obtiene del esquema real
     * (SHOW), nunca del cliente, para usarla como whitelist segura.
     *
     * @return array tabla => ['text' => [], 'pk' => [], 'hasLang' => bool]
     */
    protected function getLangTables()
    {
        if ($this->lang_tables !== null) {
            return $this->lang_tables;
        }
        $out = $this->getLangTablesFromSchema();
        if (empty($out)) {
            // Respaldo si information_schema esta restringido: solo tablas *_lang por nombre.
            $out = $this->getLangTablesFromShow();
        }
        $this->lang_tables = $out;

        return $out;
    }

    /**
     * Descubre, con UNA consulta a information_schema, TODAS las tablas del prefijo que
     * tienen una columna id_lang (incluye contenido de modulos cuyo nombre NO acaba en _lang).
     *
     * @return array tabla => ['text' => [], 'pk' => [], 'hasLang' => true]
     */
    protected function getLangTablesFromSchema()
    {
        $out = [];
        try {
            $rows = Db::getInstance()->executeS(
                'SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, COLUMN_KEY'
                . ' FROM information_schema.COLUMNS'
                . ' WHERE TABLE_SCHEMA = DATABASE()'
            );
        } catch (Exception $e) {
            TranslationfinderLogger::log('getLangTablesFromSchema error: ' . $e->getMessage(), 'scanner');

            return $out;
        }
        if (!is_array($rows) || !$rows) {
            return $out;
        }

        $prefix = _DB_PREFIX_;
        $textTypes = ['varchar', 'char', 'text', 'tinytext', 'mediumtext', 'longtext'];
        $byTable = [];
        foreach ($rows as $r) {
            $t = $r['TABLE_NAME'];
            if ($prefix !== '' && strpos($t, $prefix) !== 0) {
                continue;
            }
            $byTable[$t][] = $r;
        }

        foreach ($byTable as $t => $cols) {
            $text = [];
            $pk = [];
            $hasLang = false;
            foreach ($cols as $c) {
                $field = $c['COLUMN_NAME'];
                $dt = Tools::strtolower($c['DATA_TYPE']);
                if ($field === 'id_lang') {
                    $hasLang = true;
                }
                if (isset($c['COLUMN_KEY']) && $c['COLUMN_KEY'] === 'PRI') {
                    $pk[] = $field;
                }
                if ($field !== 'id_lang' && in_array($dt, $textTypes, true)) {
                    $text[] = $field;
                }
            }
            if ($hasLang && !empty($text) && !empty($pk)) {
                $out[$t] = ['text' => $text, 'pk' => $pk, 'hasLang' => true];
            }
        }

        return $out;
    }

    /**
     * Respaldo: descubre solo tablas cuyo nombre termina en _lang (sin information_schema).
     *
     * @return array
     */
    protected function getLangTablesFromShow()
    {
        $out = [];
        try {
            $tables = Db::getInstance()->executeS('SHOW TABLES');
        } catch (Exception $e) {
            return $out;
        }
        if (!is_array($tables)) {
            return $out;
        }
        $prefix = _DB_PREFIX_;
        foreach ($tables as $row) {
            $name = (string) reset($row);
            if (substr($name, -5) !== '_lang') {
                continue;
            }
            if ($prefix !== '' && strpos($name, $prefix) !== 0) {
                continue;
            }
            try {
                $cols = Db::getInstance()->executeS('SHOW COLUMNS FROM `' . bqSQL($name) . '`');
            } catch (Exception $e) {
                continue;
            }
            if (!is_array($cols)) {
                continue;
            }
            $text = [];
            $pk = [];
            $hasLang = false;
            foreach ($cols as $c) {
                $field = $c['Field'];
                $type = Tools::strtolower($c['Type']);
                if ($field === 'id_lang') {
                    $hasLang = true;
                }
                if (isset($c['Key']) && $c['Key'] === 'PRI') {
                    $pk[] = $field;
                }
                if ($field !== 'id_lang' && (strpos($type, 'char') !== false || strpos($type, 'text') !== false)) {
                    $text[] = $field;
                }
            }
            if ($hasLang && !empty($text) && !empty($pk)) {
                $out[$name] = ['text' => $text, 'pk' => $pk, 'hasLang' => true];
            }
        }

        return $out;
    }

    /**
     * Rastrea el CONTENIDO de la tienda en las tablas *_lang (productos, categorias,
     * CMS, bloques de texto, etc.) para el idioma seleccionado.
     */
    protected function scanContent($term, array &$rows, array &$seen, $limit, &$truncated)
    {
        $needle = trim(str_replace('%', '', $term));
        if ($needle === '') {
            return;
        }

        $tables = $this->getLangTables();
        $before = count($rows);
        TranslationfinderLogger::log(
            'scanContent inicio: term="' . $term . '" needle="' . $needle . '" id_lang=' . (int) $this->id_lang
            . ' tablas_descubiertas=' . count($tables) . ' [' . implode(', ', array_slice(array_keys($tables), 0, 40)) . ']',
            'content'
        );

        foreach ($tables as $table => $info) {
            if (!$info['hasLang'] || empty($info['text']) || empty($info['pk'])) {
                continue;
            }
            $selCols = array_values(array_unique(array_merge($info['pk'], $info['text'])));
            $colSql = [];
            foreach ($selCols as $c) {
                $colSql[] = '`' . bqSQL($c) . '`';
            }
            $likes = [];
            foreach ($info['text'] as $tc) {
                $likes[] = '`' . bqSQL($tc) . "` LIKE '%" . pSQL($needle) . "%'";
            }
            $sql = 'SELECT ' . implode(', ', $colSql) . ' FROM `' . bqSQL($table) . '`'
                . ' WHERE `id_lang` = ' . (int) $this->id_lang
                . ' AND (' . implode(' OR ', $likes) . ')'
                . ' LIMIT ' . (int) ($limit + 50);
            try {
                $res = Db::getInstance()->executeS($sql);
            } catch (Exception $e) {
                continue;
            }
            if (!is_array($res)) {
                continue;
            }
            foreach ($res as $r) {
                foreach ($info['text'] as $tc) {
                    $val = isset($r[$tc]) ? (string) $r[$tc] : '';
                    if ($val === '' || !$this->matchField($val, $this->re_field)) {
                        continue;
                    }
                    $pk = [];
                    foreach ($info['pk'] as $pc) {
                        $pk[$pc] = isset($r[$pc]) ? $r[$pc] : null;
                    }
                    $pkLabel = [];
                    foreach ($pk as $k => $v) {
                        $pkLabel[] = $k . '=' . $v;
                    }
                    $row = [
                        'origin' => TranslationCatalog::ORIGIN_CONTENT,
                        'category' => TranslationCatalog::CAT_CONTENT,
                        'domain' => $table,
                        'source' => $tc . ' (' . implode(', ', $pkLabel) . ')',
                        'translation' => $val,
                        'file' => 'BD: ' . $table . '.' . $tc,
                        'theme' => '',
                        'editable' => true,
                        'table' => $table,
                        'column' => $tc,
                        'pk' => $pk,
                    ];
                    $row['id'] = TranslationCatalog::rowId([
                        'origin' => $row['origin'],
                        'domain' => $table,
                        'source' => $row['source'],
                        'file' => $row['file'],
                        'theme' => '',
                    ]);
                    if (isset($seen[$row['id']])) {
                        continue;
                    }
                    $seen[$row['id']] = true;
                    $rows[] = $row;
                    if (count($rows) >= $limit) {
                        $truncated = true;
                        TranslationfinderLogger::log('scanContent fin (limite): coincidencias_contenido=' . (count($rows) - $before), 'content');

                        return;
                    }
                }
            }
        }

        TranslationfinderLogger::log('scanContent fin: coincidencias_contenido=' . (count($rows) - $before), 'content');
    }
}
