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
 * Persiste los cambios de traduccion sin tocar el core:
 *  - Dominios modernos (Admin/Shop/Modules/Emails): tabla ps_translation (metodo oficial).
 *  - Emails en plantilla: edita el .html/.txt con copia de seguridad previa.
 *  - Legacy .php: actualiza el valor del array del modulo.
 */
class TranslationSaver
{
    /** @var int */
    protected $id_lang;
    /** @var string */
    protected $iso;

    /**
     * @param int $id_lang
     */
    public function __construct($id_lang)
    {
        $this->id_lang = (int) $id_lang;
        $lang = new Language($this->id_lang);
        $this->iso = $lang->iso_code ? $lang->iso_code : 'en';
    }

    /**
     * Guarda un cambio segun su origen.
     *
     * @param array  $row    Fila original (origin, domain, source, file, theme...)
     * @param string $value  Nueva traduccion
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public function save(array $row, $value, $clearCache = true)
    {
        $origin = isset($row['origin']) ? $row['origin'] : TranslationCatalog::ORIGIN_FILE;

        try {
            switch ($origin) {
                case TranslationCatalog::ORIGIN_EMAIL:
                    return $this->saveEmail($row, $value);
                case TranslationCatalog::ORIGIN_LEGACY:
                    return $this->saveLegacy($row, $value);
                case TranslationCatalog::ORIGIN_CONTENT:
                    return $this->saveContent($row, $value);
                case TranslationCatalog::ORIGIN_FILE:
                case TranslationCatalog::ORIGIN_DB:
                default:
                    return $this->saveDb($row, $value, $clearCache);
            }
        } catch (Exception $e) {
            TranslationfinderLogger::log('save error: ' . $e->getMessage(), 'saver');

            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Inserta o actualiza la traduccion en ps_translation (prioritaria sobre los XLIFF).
     */
    protected function saveDb(array $row, $value, $clearCache = true)
    {
        $domain = isset($row['domain']) ? (string) $row['domain'] : '';
        $key = isset($row['source']) ? (string) $row['source'] : '';
        $theme = isset($row['theme']) ? (string) $row['theme'] : '';

        if ($domain === '' || $key === '') {
            return ['success' => false, 'message' => 'Faltan dominio o clave para guardar.'];
        }

        $db = Db::getInstance();
        $themeCond = ($theme === '') ? '`theme` IS NULL' : "`theme` = '" . pSQL($theme) . "'";
        $id = (int) $db->getValue(
            'SELECT `id_translation` FROM `' . _DB_PREFIX_ . 'translation`'
            . ' WHERE `id_lang` = ' . (int) $this->id_lang
            . " AND `domain` = '" . pSQL($domain) . "'"
            . " AND `key` = '" . pSQL($key, true) . "'"
            . ' AND ' . $themeCond
        );

        if ($id) {
            $ok = $db->execute(
                'UPDATE `' . _DB_PREFIX_ . 'translation` SET `translation` = \'' . pSQL($value, true) . '\''
                . ' WHERE `id_translation` = ' . (int) $id
            );
        } else {
            $themeVal = ($theme === '') ? 'NULL' : "'" . pSQL($theme) . "'";
            $ok = $db->execute(
                'INSERT INTO `' . _DB_PREFIX_ . 'translation` (`id_lang`, `key`, `translation`, `domain`, `theme`) VALUES ('
                . (int) $this->id_lang . ', '
                . "'" . pSQL($key, true) . "', "
                . "'" . pSQL($value, true) . "', "
                . "'" . pSQL($domain) . "', "
                . $themeVal . ')'
            );
        }

        if (!$ok) {
            return ['success' => false, 'message' => 'No se pudo escribir en ps_translation.'];
        }

        if ($clearCache) {
            $this->clearCache();

            return ['success' => true, 'message' => 'Traduccion guardada en base de datos. Caché limpiada.'];
        }

        return ['success' => true, 'message' => 'Traduccion guardada en base de datos.'];
    }

    /**
     * Reemplazo seguro para contenido HTML: si el texto parece HTML, sustituye SOLO en
     * los nodos de texto (fuera de las etiquetas <...>), de modo que no se toquen
     * atributos (class, href, src...) ni se rompa el marcado. Si no es HTML, reemplazo normal.
     * Insensible a mayusculas/minusculas.
     *
     * @param string $content
     * @param string $search
     * @param string $replace
     *
     * @return string
     */
    public static function replaceText($content, $search, $replace)
    {
        $content = (string) $content;
        if ($search === '') {
            return $content;
        }
        if (strpos($content, '<') !== false && strpos($content, '>') !== false) {
            $parts = preg_split('/(<[^>]*>)/s', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
            if (is_array($parts)) {
                foreach ($parts as $i => $seg) {
                    // Los indices pares son texto (fuera de etiqueta); los impares son etiquetas.
                    if ($i % 2 === 0 && $seg !== '') {
                        $parts[$i] = str_ireplace($search, $replace, $seg);
                    }
                }

                return implode('', $parts);
            }
        }

        return str_ireplace($search, $replace, $content);
    }

    /**
     * Resuelve una ruta relativa recibida del cliente confinandola a un directorio base
     * permitido y a una whitelist de extensiones. Evita path traversal / escritura
     * (o inclusion) de ficheros arbitrarios. Devuelve la ruta absoluta segura o null.
     *
     * @param string $rel          Ruta relativa a la raiz de PrestaShop
     * @param array  $allowedBases Directorios base permitidos (absolutos)
     * @param array  $allowedExt   Extensiones permitidas en minusculas (sin punto)
     *
     * @return string|null
     */
    protected function resolveSafePath($rel, array $allowedBases, array $allowedExt)
    {
        $rel = (string) $rel;
        // Rechazo temprano: vacio, byte nulo, traversal o ruta absoluta/UNC/unidad.
        if ($rel === ''
            || strpos($rel, "\0") !== false
            || strpos($rel, '..') !== false
            || preg_match('#^([/\\\\]|[A-Za-z]:)#', $rel)) {
            return null;
        }

        $candidate = rtrim(_PS_ROOT_DIR_, '/\\') . '/' . ltrim($rel, '/');
        $real = realpath($candidate);
        if ($real === false || !is_file($real)) {
            return null;
        }
        $real = str_replace('\\', '/', $real);

        $ext = Tools::strtolower(pathinfo($real, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            return null;
        }

        foreach ($allowedBases as $base) {
            $baseReal = realpath($base);
            if ($baseReal === false) {
                continue;
            }
            $baseReal = rtrim(str_replace('\\', '/', $baseReal), '/') . '/';
            if (strncmp($real, $baseReal, strlen($baseReal)) === 0) {
                return $real;
            }
        }

        return null;
    }

    /**
     * Reemplaza el texto buscado dentro de la plantilla de email, con backup previo.
     */
    protected function saveEmail(array $row, $value)
    {
        $rel = isset($row['file']) ? (string) $row['file'] : '';
        $source = isset($row['source']) ? (string) $row['source'] : '';

        // Confinar a mails/ del core, del theme o de modulos, solo .html/.txt.
        $bases = [
            rtrim(_PS_MAIL_DIR_, '/\\'),
            rtrim(_PS_ALL_THEMES_DIR_, '/\\'),
            rtrim(_PS_MODULE_DIR_, '/\\'),
        ];
        $file = $this->resolveSafePath($rel, $bases, ['html', 'txt']);
        if ($file === null || strpos($file, '/mails/') === false) {
            TranslationfinderLogger::log('saveEmail ruta rechazada: ' . $rel, 'saver');

            return ['success' => false, 'message' => 'Ruta de email no permitida.'];
        }

        if (!is_writable($file)) {
            return ['success' => false, 'message' => 'El fichero de email no tiene permisos de escritura: ' . $rel];
        }
        if ($source === '' || $source === $value) {
            return ['success' => false, 'message' => 'No hay cambios que aplicar al email.'];
        }

        $content = (string) file_get_contents($file);
        if (stripos($content, $source) === false) {
            return ['success' => false, 'message' => 'El texto original ya no aparece en el email.'];
        }

        $this->backupFile($file);
        // Reemplazo que respeta el HTML del email (no toca atributos ni etiquetas).
        $new = self::replaceText($content, $source, $value);
        if (file_put_contents($file, $new) === false) {
            return ['success' => false, 'message' => 'No se pudo escribir el fichero de email.'];
        }

        return ['success' => true, 'message' => 'Email actualizado (backup creado en /cache del modulo).'];
    }

    /**
     * Actualiza un valor en un fichero legacy .php de modulo.
     */
    protected function saveLegacy(array $row, $value)
    {
        $rel = isset($row['file']) ? (string) $row['file'] : '';
        $hash = isset($row['source']) ? (string) $row['source'] : '';

        // Confinar a modules/<modulo>/translations/<iso>.php (solo .php) ANTES de incluir.
        $file = $this->resolveSafePath($rel, [rtrim(_PS_MODULE_DIR_, '/\\')], ['php']);
        if ($file === null || !preg_match('#/modules/[^/]+/translations/[a-z]{2}\.php$#', $file)) {
            TranslationfinderLogger::log('saveLegacy ruta rechazada: ' . $rel, 'saver');

            return ['success' => false, 'message' => 'Ruta de fichero legacy no permitida.'];
        }
        if (!is_writable($file)) {
            return ['success' => false, 'message' => 'El fichero legacy no tiene permisos de escritura: ' . $rel];
        }

        global $_MODULE;
        $_MODULE = [];
        include $file;
        if (!isset($_MODULE) || !is_array($_MODULE) || !array_key_exists($hash, $_MODULE)) {
            return ['success' => false, 'message' => 'No se encontro la clave en el fichero legacy.'];
        }

        $this->backupFile($file);
        $_MODULE[$hash] = $value;

        $out = "<?php\n\nglobal \$_MODULE;\n\$_MODULE = array();\n";
        foreach ($_MODULE as $k => $v) {
            $out .= "\$_MODULE['" . str_replace("'", "\\'", $k) . "'] = '" . str_replace("'", "\\'", $v) . "';\n";
        }

        if (file_put_contents($file, $out) === false) {
            return ['success' => false, 'message' => 'No se pudo escribir el fichero legacy.'];
        }

        return ['success' => true, 'message' => 'Traduccion legacy actualizada (backup creado).'];
    }

    /**
     * Actualiza un texto de contenido en una tabla *_lang. Valida tabla, columna y
     * claves contra el esquema REAL (SHOW COLUMNS) para impedir inyeccion por identificador.
     */
    protected function saveContent(array $row, $value)
    {
        $table = isset($row['table']) ? (string) $row['table'] : '';
        $column = isset($row['column']) ? (string) $row['column'] : '';
        $pk = (isset($row['pk']) && is_array($row['pk'])) ? $row['pk'] : [];

        if ($table === '' || $column === '' || empty($pk)) {
            return ['success' => false, 'message' => 'Datos de contenido incompletos.'];
        }
        // Solo tablas del prefijo de la tienda.
        if (_DB_PREFIX_ !== '' && strpos($table, _DB_PREFIX_) !== 0) {
            return ['success' => false, 'message' => 'Tabla de contenido no permitida.'];
        }

        try {
            $cols = Db::getInstance()->executeS('SHOW COLUMNS FROM `' . bqSQL($table) . '`');
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'La tabla no existe.'];
        }
        if (!is_array($cols) || !$cols) {
            return ['success' => false, 'message' => 'La tabla no existe.'];
        }

        $colNames = [];
        $textCols = [];
        $hasLang = false;
        foreach ($cols as $c) {
            $colNames[] = $c['Field'];
            if ($c['Field'] === 'id_lang') {
                $hasLang = true;
            }
            $type = Tools::strtolower($c['Type']);
            if (strpos($type, 'char') !== false || strpos($type, 'text') !== false) {
                $textCols[] = $c['Field'];
            }
        }
        // Solo se permite editar contenido multiidioma (la tabla debe tener id_lang).
        if (!$hasLang) {
            return ['success' => false, 'message' => 'La tabla no es de contenido multiidioma.'];
        }
        if (!in_array($column, $textCols, true)) {
            return ['success' => false, 'message' => 'Columna de contenido no editable.'];
        }

        $where = [];
        foreach ($pk as $k => $v) {
            if (!in_array($k, $colNames, true)) {
                return ['success' => false, 'message' => 'Clave de contenido no valida.'];
            }
            $where[] = '`' . bqSQL($k) . '` = ' . (is_numeric($v) ? (int) $v : "'" . pSQL((string) $v) . "'");
        }
        if (empty($where)) {
            return ['success' => false, 'message' => 'Sin clave para localizar el contenido.'];
        }

        $ok = Db::getInstance()->execute(
            'UPDATE `' . bqSQL($table) . '` SET `' . bqSQL($column) . "` = '" . pSQL((string) $value, true) . "'"
            . ' WHERE ' . implode(' AND ', $where)
        );
        if (!$ok) {
            return ['success' => false, 'message' => 'No se pudo actualizar el contenido.'];
        }

        return ['success' => true, 'message' => 'Contenido actualizado en ' . $table . '.' . $column . '.'];
    }

    /**
     * Crea una copia de seguridad del fichero dentro de /cache del modulo.
     *
     * @param string $file
     */
    protected function backupFile($file)
    {
        try {
            $dir = _PS_MODULE_DIR_ . 'ps_translationfinder/cache/backups/';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $name = date('Ymd-His') . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file));
            @copy($file, $dir . $name);
        } catch (Exception $e) {
            TranslationfinderLogger::log('backup error: ' . $e->getMessage(), 'saver');
        }
    }

    /**
     * Limpia la cache (Smarty + Symfony) para que las traducciones surtan efecto.
     */
    public function clearCache()
    {
        try {
            if (method_exists('Tools', 'clearAllCache')) {
                Tools::clearAllCache();
            } else {
                Tools::clearSmartyCache();
            }
            if (method_exists('Tools', 'clearSf2Cache')) {
                Tools::clearSf2Cache();
            }

            // CLAVE: el front cachea el catalogo de traducciones en
            // _PS_CACHE_DIR_/translations. Si no se borra, la traduccion guardada
            // en ps_translation no se refleja en la tienda. PrestaShop lo regenera
            // (leyendo BD + ficheros) en la siguiente peticion.
            if (defined('_PS_CACHE_DIR_')) {
                $tdir = rtrim(_PS_CACHE_DIR_, '/\\') . '/translations';
                if (is_dir($tdir)) {
                    if (method_exists('Tools', 'deleteDirectory')) {
                        Tools::deleteDirectory($tdir, false);
                    } else {
                        $this->deleteDirContents($tdir);
                    }
                }
            }
        } catch (Exception $e) {
            TranslationfinderLogger::log('clearCache error: ' . $e->getMessage(), 'saver');
        }
    }

    /**
     * Borra el contenido de un directorio de forma recursiva (fallback).
     *
     * @param string $dir
     */
    protected function deleteDirContents($dir)
    {
        $items = @scandir($dir);
        if (!is_array($items)) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->deleteDirContents($path);
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }
    }
}
