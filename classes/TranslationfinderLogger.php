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
 * Logger ligero. Solo escribe si la opcion de depuracion esta activa en configuracion.
 * Los ficheros se guardan dentro del propio modulo en /logs.
 */
class TranslationfinderLogger
{
    /**
     * Escribe una linea de log si el modo depuracion esta activado.
     *
     * @param string $message
     * @param string $context
     *
     * @return void
     */
    public static function log($message, $context = 'general')
    {
        if (!(int) Configuration::get('PS_TFINDER_DEBUG')) {
            return;
        }

        try {
            $dir = _PS_MODULE_DIR_ . 'ps_translationfinder/logs/';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $file = $dir . 'ps_translationfinder-' . date('Y-m-d') . '.log';
            $line = '[' . date('Y-m-d H:i:s') . '][' . $context . '] ' . $message . PHP_EOL;
            @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
        } catch (Exception $e) {
            // El log nunca debe romper el flujo principal.
        }
    }
}
