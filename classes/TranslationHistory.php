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
 * Historial de reemplazos masivos para poder restaurarlos. Cada entrada guarda el
 * termino buscado, el reemplazo, la fecha y los items afectados (con su valor anterior),
 * lo que permite deshacer el cambio. Se almacena en cache/replace_history.json del modulo.
 */
class TranslationHistory
{
    /** @var int Numero maximo de entradas que se conservan. */
    const MAX_ENTRIES = 50;

    /**
     * Ruta del fichero de historial.
     *
     * @return string
     */
    protected static function file()
    {
        $dir = _PS_MODULE_DIR_ . 'ps_translationfinder/cache/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return $dir . 'replace_history.json';
    }

    /**
     * Carga todas las entradas (mas reciente primero).
     *
     * @return array
     */
    public static function all()
    {
        $file = self::file();
        if (!is_file($file)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? $data : [];
    }

    /**
     * Anade una entrada de reemplazo al historial.
     *
     * @param array $entry ['id_lang','category','term','replace','items'=>[['row'=>..,'old'=>..,'new'=>..]]]
     *
     * @return string id de la entrada
     */
    public static function add(array $entry)
    {
        $entries = self::all();
        $id = 'h' . str_replace([' ', ':', '-', '.'], '', date('YmdHis')) . count($entries) . substr(md5(json_encode($entry['term'] ?? '') . microtime(true)), 0, 6);
        $entry['id'] = $id;
        $entry['date'] = date('Y-m-d H:i:s');
        $entry['count'] = isset($entry['items']) ? count($entry['items']) : 0;
        array_unshift($entries, $entry);
        if (count($entries) > self::MAX_ENTRIES) {
            $entries = array_slice($entries, 0, self::MAX_ENTRIES);
        }
        self::write($entries);

        return $id;
    }

    /**
     * Devuelve una entrada por id, o null.
     *
     * @param string $id
     *
     * @return array|null
     */
    public static function get($id)
    {
        foreach (self::all() as $e) {
            if (isset($e['id']) && $e['id'] === $id) {
                return $e;
            }
        }

        return null;
    }

    /**
     * Elimina una entrada por id.
     *
     * @param string $id
     *
     * @return void
     */
    public static function remove($id)
    {
        $entries = [];
        foreach (self::all() as $e) {
            if (!isset($e['id']) || $e['id'] !== $id) {
                $entries[] = $e;
            }
        }
        self::write($entries);
    }

    /**
     * Marca una entrada como restaurada (sin borrarla del listado).
     *
     * @param string $id
     *
     * @return void
     */
    public static function markRestored($id)
    {
        $entries = self::all();
        foreach ($entries as &$e) {
            if (isset($e['id']) && $e['id'] === $id) {
                $e['restored'] = true;
                $e['restored_date'] = date('Y-m-d H:i:s');
            }
        }
        unset($e);
        self::write($entries);
    }

    /**
     * Devuelve un listado ligero (sin los items) para mostrar en la interfaz.
     *
     * @return array
     */
    public static function summary()
    {
        $out = [];
        foreach (self::all() as $e) {
            $out[] = [
                'id' => isset($e['id']) ? $e['id'] : '',
                'date' => isset($e['date']) ? $e['date'] : '',
                'term' => isset($e['term']) ? $e['term'] : '',
                'replace' => isset($e['replace']) ? $e['replace'] : '',
                'category' => isset($e['category']) ? $e['category'] : '',
                'count' => isset($e['count']) ? (int) $e['count'] : 0,
                'restored' => !empty($e['restored']),
                'restored_date' => isset($e['restored_date']) ? $e['restored_date'] : '',
            ];
        }

        return $out;
    }

    /**
     * Escribe el historial en disco.
     *
     * @param array $entries
     *
     * @return void
     */
    protected static function write(array $entries)
    {
        @file_put_contents(self::file(), json_encode($entries), LOCK_EX);
    }
}
