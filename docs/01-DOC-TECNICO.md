# Translation Finder — Documentación Técnica

**Versión:** 1.0.0 · **Autor:** Ecom Experts · **Licencia:** AFL-3.0 · **Compatibilidad:** PrestaShop 1.7 / 8 / 9 (PHP 7.4 y 8.x)

## 1. Propósito
Buscador unificado de traducciones. Localiza cualquier texto en **front/theme, back office, módulos y emails**, indica **dónde aparece** (dominio + archivo) y permite **editarlo** sin overrides ni modificar el core.

## 2. Cómo guarda PrestaShop las traducciones (base del diseño)
- **Sistema moderno (Symfony):** catálogo base en archivos **XLIFF** `.xlf` (`<source>`/`<target>`). Las traducciones editadas en el BO se guardan en la tabla **`ps_translation`** y **tienen prioridad** sobre los `.xlf`.
- **Dominio en BD:** se almacena **sin puntos** (`Admin.Advparameters.Feature` → `AdminAdvparametersFeature`), idéntico al atributo `original`/nombre del fichero XLIFF. El módulo usa exactamente ese valor para que el guardado surta efecto.
- **`key`** = texto fuente (source) · **`translation`** = traducción · **`theme`** = nombre del theme (front) o `NULL` (core/admin/módulos).
- **Legacy:** `modules/<m>/translations/<iso>.php` con array `$_MODULE` (hashes). **Emails:** plantillas `.html`/`.txt` en `mails/<iso>/`.

## 3. Arquitectura
```
ps_translationfinder.php                 Clase Ps_Translationfinder (Module): install/tab, getContent, addnewfeatures
classes/
  TranslationCatalog.php                 Constantes de categoría/origen, clasificación de dominio, normalización de filas
  TranslationScanner.php                 Rastrea XLIFF + legacy + emails + ps_translation; filtra por término/categoría
  TranslationSaver.php                   Persiste: BD (ps_translation) | email (archivo+backup) | legacy (.php)
  TranslationfinderLogger.php              Log opcional a /logs si PS_TFINDER_DEBUG=1
controllers/admin/
  AdminPsTranslationfinderController.php  Pinta el buscador y atiende AJAX (Search/Save/ClearCache)
views/templates/admin/                   search.tpl (UI) · configure.tpl (config)
views/js/ps_translationfinder.js · views/css/ps_translationfinder.css
translations/es-ES/ModulesPstranslationfinderAdmin.es-ES.xlf
logs/ · cache/backups/ · docs/
```
No crea tablas propias: reutiliza `ps_translation`. `index.php` (header + redirect) en cada carpeta.

## 4. Flujo
1. **Buscar** (`ajaxProcessSearch`): `TranslationScanner::search($term, $category, 300)` carga el mapa de `ps_translation`, recorre los XLIFF de la categoría (core `translations/<locale>`, `app/Resources/translations/<locale>` y `default`, theme y módulos), añade legacy y emails, fusiona el valor vigente de BD y devuelve filas normalizadas (con `id`, `origin`, `category`, `domain`, `source`, `translation`, `file`, `theme`).
2. **Guardar** (`ajaxProcessSave`): `TranslationSaver::save($row, $value)` decide por `origin`:
   - `file`/`db` → `INSERT/UPDATE` en `ps_translation` (busca por `id_lang`+`domain`+`key`+`theme`) y limpia caché.
   - `email` → backup en `cache/backups/` y `str_ireplace` del texto en el archivo.
   - `legacy` → reescribe el array `$_MODULE` del `.php`.
3. **Limpiar caché** (`ajaxProcessClearCache`): `Tools::clearAllCache()` + `Tools::clearSf2Cache()` (si existe).

## 5. Rendimiento
- Mínimo 3 caracteres; búsqueda bajo demanda (botón), no en cada tecla.
- Resultados limitados a **300** (`truncated=true` avisa de refinar).
- Corte temprano al alcanzar el límite. Rutas siempre con `_PS_ROOT_DIR_`, `_PS_MODULE_DIR_`, `_PS_MAIL_DIR_`, `_PS_ALL_THEMES_DIR_`.

## 6. Seguridad / estándares
- `if (!defined('_PS_VERSION_')) exit;` en todos los PHP; headers AFL-3.0; sin BOM; sin Composer; carga manual `require_once`.
- `pSQL()` en todo SQL manual; sin `LIMIT 1`; escape Smarty `|escape:'html':'UTF-8'`; tokens de admin en AJAX (sin estado de sesión).
- `isUsingNewTranslationSystem()` devuelve `true`.

## 7. Configuración
- `PS_TFINDER_DEBUG` (0/1): activa logs en `/logs/ps_translationfinder-YYYY-MM-DD.log`.
