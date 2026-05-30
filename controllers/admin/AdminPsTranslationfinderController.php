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
 * Controlador del back office: pinta el buscador y atiende los endpoints AJAX
 * (buscar, guardar, limpiar cache).
 */
class AdminPsTranslationfinderController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->display = 'view';
        parent::__construct();
    }

    /**
     * Pinta la pantalla del buscador.
     */
    public function initContent()
    {
        parent::initContent();

        $languages = [];
        foreach (Language::getLanguages(false) as $lang) {
            $languages[] = [
                'id_lang' => (int) $lang['id_lang'],
                'name' => $lang['name'],
                'iso_code' => $lang['iso_code'],
            ];
        }

        $contentEnabled = (bool) Configuration::get('PS_TFINDER_CONTENT');
        $backupDefault = (Configuration::get('PS_TFINDER_BACKUP') === false)
            ? true
            : (bool) Configuration::get('PS_TFINDER_BACKUP');

        $categories = [];
        foreach (TranslationCatalog::getCategories() as $key => $label) {
            $categories[] = ['key' => $key, 'label' => $label];
        }
        if ($contentEnabled) {
            $categories[] = ['key' => TranslationCatalog::CAT_CONTENT, 'label' => 'Contenido (BD)'];
        }

        $this->context->smarty->assign([
            'ps_tf_ajax_url' => $this->context->link->getAdminLink('AdminPsTranslationfinder'),
            'ps_tf_token' => $this->token,
            'ps_tf_languages' => $languages,
            'ps_tf_default_lang' => (int) $this->context->language->id,
            'ps_tf_categories' => $categories,
            'ps_tf_content_enabled' => $contentEnabled ? 1 : 0,
            'ps_tf_backup_default' => $backupDefault ? 1 : 0,
            'ps_tf_module_dir' => $this->module->getPathUri(),
        ]);

        $this->setTemplate('search.tpl');
    }

    /**
     * Carga CSS/JS de la interfaz.
     */
    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);
        // TinyMCE del back office (para el editor HTML). La comprobacion de existencia
        // usa la ruta de DISCO (_PS_ROOT_DIR_), y addJS la URL (_PS_JS_DIR_), porque
        // _PS_JS_DIR_ es una URL (/js/) y file_exists sobre ella rompe por open_basedir.
        // Si no existe, el JS cae a un editor WYSIWYG (contenteditable), asi que es opcional.
        if (defined('_PS_ROOT_DIR_') && defined('_PS_JS_DIR_')
            && @file_exists(rtrim(_PS_ROOT_DIR_, '/\\') . '/js/tiny_mce/tinymce.min.js')) {
            $this->addJS(_PS_JS_DIR_ . 'tiny_mce/tinymce.min.js');
        }
        $this->addCSS($this->module->getPathUri() . 'views/css/ps_translationfinder.css');
        $this->addJS($this->module->getPathUri() . 'views/js/ps_translationfinder.js');
    }

    /**
     * AJAX: busca un termino en las fuentes de traduccion.
     */
    public function ajaxProcessSearch()
    {
        $term = Tools::getValue('term');
        $category = $this->sanitizeCategory(Tools::getValue('category', TranslationCatalog::CAT_ALL));
        $id_lang = $this->resolveLang(Tools::getValue('id_lang'));

        if ($id_lang === 0) {
            $this->jsonDie(['success' => false, 'message' => 'Idioma no valido.', 'rows' => []]);
        }
        if (Tools::strlen(trim((string) $term)) < 3) {
            $this->jsonDie(['success' => false, 'message' => 'Escribe al menos 3 caracteres.', 'rows' => []]);
        }

        $scanner = new TranslationScanner($id_lang);
        $result = $scanner->search($term, $category, 300, $this->contentEnabled());

        $this->jsonDie([
            'success' => true,
            'truncated' => $result['truncated'],
            'total' => $result['total'],
            'rows' => $result['rows'],
        ]);
    }

    /**
     * AJAX: guarda una traduccion.
     */
    public function ajaxProcessSave()
    {
        if (!$this->access('edit')) {
            $this->jsonDie(['success' => false, 'message' => 'No tienes permiso para editar traducciones.']);
        }
        $id_lang = $this->resolveLang(Tools::getValue('id_lang'));
        if ($id_lang === 0) {
            $this->jsonDie(['success' => false, 'message' => 'Idioma no valido.']);
        }
        $value = (string) Tools::getValue('value');
        $row = json_decode((string) Tools::getValue('row'), true);

        if (!is_array($row) || !$this->isValidRow($row)) {
            $this->jsonDie(['success' => false, 'message' => 'Datos de fila invalidos.']);
        }

        $saver = new TranslationSaver($id_lang);
        $result = $saver->save($row, $value);

        $this->jsonDie($result);
    }

    /**
     * AJAX: buscar y reemplazar masivo. Sustituye el termino por otro en la traduccion
     * de todas las coincidencias del apartado y guarda los cambios.
     */
    public function ajaxProcessReplace()
    {
        if (!$this->access('edit')) {
            $this->jsonDie(['success' => false, 'message' => 'No tienes permiso para editar traducciones.']);
        }
        $term = Tools::getValue('term');
        $replace = (string) Tools::getValue('replace');
        $category = $this->sanitizeCategory(Tools::getValue('category', TranslationCatalog::CAT_ALL));
        $id_lang = $this->resolveLang(Tools::getValue('id_lang'));

        if ($id_lang === 0) {
            $this->jsonDie(['success' => false, 'message' => 'Idioma no valido.']);
        }
        if (Tools::strlen(trim((string) $term)) < 3) {
            $this->jsonDie(['success' => false, 'message' => 'El texto a buscar debe tener al menos 3 caracteres.']);
        }
        if ($replace === '' || $replace === $term) {
            $this->jsonDie(['success' => false, 'message' => 'Indica un texto de reemplazo distinto.']);
        }

        // La palabra/frase literal a sustituir (sin los comodines '%').
        $needle = trim(str_replace('%', '', $term));
        if ($needle === '') {
            $this->jsonDie(['success' => false, 'message' => 'El texto a reemplazar no puede ser solo comodines.']);
        }

        $backup = (int) Tools::getValue('backup', 1) ? true : false;

        $scanner = new TranslationScanner($id_lang);
        $result = $scanner->search($term, $category, 300, $this->contentEnabled());
        $saver = new TranslationSaver($id_lang);

        $changed = 0;
        $skipped = 0;
        $errors = 0;
        $items = [];
        foreach ($result['rows'] as $row) {
            $current = isset($row['translation']) ? (string) $row['translation'] : '';
            // En emails el reemplazo se hace sobre el contenido del fichero (source = termino).
            if ($row['origin'] === TranslationCatalog::ORIGIN_EMAIL) {
                // El source del email es el termino; la sustitucion real (respetando HTML)
                // la hace saveEmail sobre el fichero.
                $new = str_ireplace($needle, $replace, $row['source']);
            } else {
                if (stripos($current, $needle) === false) {
                    ++$skipped;
                    continue;
                }
                // Reemplazo seguro: si el valor es HTML, solo cambia el texto, no las etiquetas.
                $new = TranslationSaver::replaceText($current, $needle, $replace);
            }
            if ($new === $current && $row['origin'] !== TranslationCatalog::ORIGIN_EMAIL) {
                ++$skipped;
                continue;
            }
            $res = $saver->save($row, $new, false);
            if (!empty($res['success'])) {
                ++$changed;
                if ($backup) {
                    $items[] = ['row' => $row, 'old' => $current, 'new' => $new];
                }
            } else {
                ++$errors;
            }
        }

        $saver->clearCache();

        $historyId = '';
        if ($backup && !empty($items)) {
            $historyId = TranslationHistory::add([
                'id_lang' => $id_lang,
                'category' => $category,
                'term' => $term,
                'replace' => $replace,
                'items' => $items,
            ]);
        }

        $this->jsonDie([
            'success' => true,
            'message' => 'Reemplazo completado: ' . $changed . ' cambiada(s), ' . $skipped . ' omitida(s), ' . $errors . ' con error.'
                . ($historyId ? ' Backup guardado (restaurable).' : '') . ' Caché limpiada.',
            'changed' => $changed,
            'skipped' => $skipped,
            'errors' => $errors,
            'history_id' => $historyId,
        ]);
    }

    /**
     * AJAX: devuelve el historial de reemplazos (resumen) para el listado.
     */
    public function ajaxProcessHistory()
    {
        $this->jsonDie(['success' => true, 'history' => TranslationHistory::summary()]);
    }

    /**
     * AJAX: restaura un reemplazo del historial (revierte cada item a su valor anterior).
     */
    public function ajaxProcessRestore()
    {
        if (!$this->access('edit')) {
            $this->jsonDie(['success' => false, 'message' => 'No tienes permiso para restaurar.']);
        }
        $id = (string) Tools::getValue('history_id');
        $entry = TranslationHistory::get($id);
        if (!$entry || empty($entry['items'])) {
            $this->jsonDie(['success' => false, 'message' => 'Entrada de historial no encontrada.']);
        }

        $id_lang = isset($entry['id_lang']) ? (int) $entry['id_lang'] : (int) $this->context->language->id;
        $saver = new TranslationSaver($id_lang);
        $restored = 0;
        $errors = 0;
        foreach ($entry['items'] as $item) {
            if (!isset($item['row']) || !is_array($item['row'])) {
                continue;
            }
            $row = $item['row'];
            $old = isset($item['old']) ? (string) $item['old'] : '';
            // En emails, para restaurar hay que reemplazar el texto NUEVO por el ANTERIOR.
            if (isset($row['origin']) && $row['origin'] === TranslationCatalog::ORIGIN_EMAIL) {
                $row['source'] = isset($item['new']) ? (string) $item['new'] : $row['source'];
            }
            $res = $saver->save($row, $old, false);
            if (!empty($res['success'])) {
                ++$restored;
            } else {
                ++$errors;
            }
        }
        $saver->clearCache();
        TranslationHistory::markRestored($id);

        $this->jsonDie([
            'success' => true,
            'message' => 'Restauradas ' . $restored . ' traduccion(es)' . ($errors ? ', ' . $errors . ' con error' : '') . '. Caché limpiada.',
            'history' => TranslationHistory::summary(),
        ]);
    }

    /**
     * AJAX: elimina una entrada del historial.
     */
    public function ajaxProcessDeleteHistory()
    {
        if (!$this->access('edit')) {
            $this->jsonDie(['success' => false, 'message' => 'Sin permiso.']);
        }
        TranslationHistory::remove((string) Tools::getValue('history_id'));
        $this->jsonDie(['success' => true, 'history' => TranslationHistory::summary()]);
    }

    /**
     * AJAX: limpia la cache manualmente.
     */
    public function ajaxProcessClearCache()
    {
        $saver = new TranslationSaver((int) $this->context->language->id);
        $saver->clearCache();
        $this->jsonDie(['success' => true, 'message' => 'Caché limpiada.']);
    }

    /**
     * Devuelve la respuesta JSON con cabecera y flags de seguridad, y termina.
     *
     * @param array $data
     */
    protected function jsonDie(array $data)
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
        $this->ajaxDie(json_encode($data, $flags));
    }

    /**
     * Valida que el id_lang corresponde a un idioma instalado. Devuelve el id o 0.
     *
     * @param mixed $id_lang
     *
     * @return int
     */
    protected function resolveLang($id_lang)
    {
        $id_lang = (int) $id_lang;
        if ($id_lang <= 0) {
            return (int) $this->context->language->id;
        }

        return Language::getLanguage($id_lang) ? $id_lang : 0;
    }

    /**
     * Normaliza la categoria a una de las permitidas.
     *
     * @param mixed $category
     *
     * @return string
     */
    protected function sanitizeCategory($category)
    {
        $category = (string) $category;

        if ($category === TranslationCatalog::CAT_CONTENT) {
            return $this->contentEnabled() ? TranslationCatalog::CAT_CONTENT : TranslationCatalog::CAT_ALL;
        }

        return array_key_exists($category, TranslationCatalog::getCategories())
            ? $category
            : TranslationCatalog::CAT_ALL;
    }

    /**
     * Indica si la busqueda/edicion de contenido de BD esta habilitada en configuracion.
     *
     * @return bool
     */
    protected function contentEnabled()
    {
        return (bool) Configuration::get('PS_TFINDER_CONTENT');
    }

    /**
     * Comprueba que la fila recibida del cliente tiene un origen valido y campos de tipo correcto.
     *
     * @param array $row
     *
     * @return bool
     */
    protected function isValidRow(array $row)
    {
        $allowedOrigins = [
            TranslationCatalog::ORIGIN_FILE,
            TranslationCatalog::ORIGIN_DB,
            TranslationCatalog::ORIGIN_EMAIL,
            TranslationCatalog::ORIGIN_LEGACY,
        ];
        if ($this->contentEnabled()) {
            $allowedOrigins[] = TranslationCatalog::ORIGIN_CONTENT;
        }
        if (!isset($row['origin']) || !in_array($row['origin'], $allowedOrigins, true)) {
            return false;
        }
        foreach (['domain', 'source', 'file', 'theme', 'table', 'column'] as $field) {
            if (isset($row[$field]) && !is_string($row[$field])) {
                return false;
            }
        }
        if ($row['origin'] === TranslationCatalog::ORIGIN_CONTENT) {
            if (empty($row['table']) || empty($row['column']) || !isset($row['pk']) || !is_array($row['pk'])) {
                return false;
            }
        }

        return true;
    }
}
