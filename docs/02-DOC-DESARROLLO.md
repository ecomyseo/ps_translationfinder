# Translation Finder — Guía de Desarrollo

**Versión:** 1.0.0 · **Autor:** Ecom Experts · **Licencia:** AFL-3.0

## 1. Puntos de extensión

### Añadir/ajustar la clasificación de apartados
`classes/TranslationCatalog.php` → `classifyDomain($domain)`. El orden importa: `Emails`/`Mail` → `Admin` → `Modules` → `Shop` → otros. Las categorías de la UI se definen en `getCategories()`.

### Añadir nuevas raíces de búsqueda
`classes/TranslationScanner.php` → `getXliffJobs($category)` devuelve `['glob' => patrón, 'theme' => nombre]`. Añade ahí nuevas rutas (p.ej. otra carpeta de catálogos). Para emails edita `scanEmails()` (lista `$dirs`).

### Cambiar el límite de resultados
Controlador `ajaxProcessSearch()` → tercer argumento de `search()` (por defecto 300).

## 2. Contrato de una "fila" (row)
`TranslationCatalog::makeRow()` normaliza a:
```
id          string  hash único (base64 de origin|domain|source|file|theme)
origin      string  file | db | email | legacy
category    string  all|front|back|modules|emails|other
domain      string  dominio SIN puntos (o "mail:..." / "legacy:...")
source      string  texto fuente (o hash legacy, o término en emails)
translation string  traducción vigente (BD si existe, si no el target del XLIFF)
file        string  ruta relativa a la raíz de PrestaShop
theme       string  nombre del theme o '' (global)
editable    bool
```
El front guarda el array `lastRows` y referencia por índice (`data-idx`) — no se incrusta JSON en el DOM.

## 3. Persistencia (TranslationSaver)
- **BD:** se localiza la fila con `id_lang + domain + key + theme` (theme `IS NULL` o `= valor`); si existe `UPDATE`, si no `INSERT`. `key`/`translation` con `pSQL($v, true)` (permite HTML). Tras escribir → `clearCache()`.
- **Email:** `backupFile()` copia el original a `cache/backups/` con timestamp; luego `str_ireplace(source → value)`. Requiere permisos de escritura en `mails/`.
- **Legacy:** captura `global $_MODULE`, modifica la clave y reescribe el `.php` completo.

## 4. Reglas PrestaShop respetadas
- Sin overrides (clases propias en `/classes`, controlador en `/controllers/admin`).
- Sin namespace en la clase principal; nombre `Ps_Translationfinder` = carpeta.
- Sin dependencias Composer; funciones nativas (`SimpleXML`, `glob`).
- Tab instalada en `install()` vía objeto `Tab` (no `installOverrides`).
- `addnewfeatures()` con `try{}` reinstala la tab si falta, al entrar en configuración.
- Compatibilidad PHP 7.4/8: sin `::class`, `\Context::getContext()`, comprobaciones defensivas.

## 5. Depuración
Activa **Modo depuración** en la configuración (`PS_TFINDER_DEBUG`). `TranslationfinderLogger::log($msg, $context)` escribe en `/logs`. Errores de scanner/saver/backup se registran sin romper el flujo.

## 6. Cómo añadir un endpoint AJAX
En `AdminPsTranslationfinderController` crea `ajaxProcessMiAccion()` y llama desde JS con `data: { ajax:1, action:'MiAccion', ... }`. Responde con `$this->ajaxDie(json_encode([...]))`.

## 7. Pendientes / mejoras futuras
- Índice cacheado en `/cache` para acelerar búsquedas repetidas (estructura ya prevista).
- Soporte legacy de theme (`themes/<t>/lang/<iso>.php`).
- Exportar/importar cambios; historial de ediciones.
