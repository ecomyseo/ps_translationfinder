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
 * Modelo de catalogo: clasifica dominios de traduccion en apartados
 * (front/theme, back office, modulos, emails, legacy) y normaliza resultados.
 */
class TranslationCatalog
{
    const CAT_ALL = 'all';
    const CAT_FRONT = 'front';
    const CAT_BACK = 'back';
    const CAT_MODULES = 'modules';
    const CAT_EMAILS = 'emails';
    const CAT_OTHER = 'other';
    const CAT_CONTENT = 'content';

    const ORIGIN_FILE = 'file';
    const ORIGIN_LEGACY = 'legacy';
    const ORIGIN_EMAIL = 'email';
    const ORIGIN_DB = 'db';
    const ORIGIN_CONTENT = 'content';

    /**
     * Devuelve la lista de apartados para la interfaz (clave => etiqueta).
     *
     * @return array
     */
    public static function getCategories()
    {
        return [
            self::CAT_ALL => 'Todos',
            self::CAT_FRONT => 'Front / Theme',
            self::CAT_BACK => 'Back office',
            self::CAT_MODULES => 'Modulos',
            self::CAT_EMAILS => 'Emails',
            self::CAT_OTHER => 'Legacy / Otros',
        ];
    }

    /**
     * Clasifica un dominio de traduccion en una de las categorias.
     *
     * @param string $domain
     *
     * @return string
     */
    public static function classifyDomain($domain)
    {
        $domain = (string) $domain;

        if ($domain === '') {
            return self::CAT_OTHER;
        }
        if (stripos($domain, 'Emails') === 0 || stripos($domain, 'Mail') === 0) {
            return self::CAT_EMAILS;
        }
        if (stripos($domain, 'Admin') === 0) {
            return self::CAT_BACK;
        }
        if (stripos($domain, 'Modules') === 0) {
            return self::CAT_MODULES;
        }
        if (stripos($domain, 'Shop') === 0) {
            return self::CAT_FRONT;
        }

        return self::CAT_OTHER;
    }

    /**
     * Construye un identificador unico de fila para el front (base64 de los datos clave).
     *
     * @param array $row
     *
     * @return string
     */
    public static function rowId(array $row)
    {
        $raw = ($row['origin'] ?? '') . '|' . ($row['domain'] ?? '') . '|'
            . ($row['source'] ?? '') . '|' . ($row['file'] ?? '') . '|' . ($row['theme'] ?? '');

        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /**
     * Normaliza una fila de resultado a la estructura comun.
     *
     * @param array $data
     *
     * @return array
     */
    public static function makeRow(array $data)
    {
        $domain = isset($data['domain']) ? (string) $data['domain'] : '';
        $row = [
            'origin' => isset($data['origin']) ? (string) $data['origin'] : self::ORIGIN_FILE,
            'category' => isset($data['category']) ? (string) $data['category'] : self::classifyDomain($domain),
            'domain' => $domain,
            'source' => isset($data['source']) ? (string) $data['source'] : '',
            'translation' => isset($data['translation']) ? (string) $data['translation'] : '',
            'file' => isset($data['file']) ? (string) $data['file'] : '',
            'theme' => isset($data['theme']) ? (string) $data['theme'] : '',
            'editable' => isset($data['editable']) ? (bool) $data['editable'] : true,
        ];
        $row['id'] = self::rowId($row);

        return $row;
    }
}
