# Translation Finder (ps_translationfinder)

Buscador y editor unificado de traducciones para **PrestaShop 1.7 / 8 / 9**. Localiza cualquier
texto traducible —**front/theme, back office, módulos y emails**— desde un único buscador, te dice
**dónde aparece** (dominio + archivo) y te permite **editarlo sin overrides** ni tocar el core.

- **Autor:** Ecom Experts
- **Licencia:** [AFL-3.0](https://opensource.org/licenses/AFL-3.0)
- **Compatibilidad:** PrestaShop 1.7, 8 y 9 · PHP 7.4 y 8.x

## Características

- **Búsqueda global** de traducciones en un solo sitio: front/theme, back office, módulos y emails.
- **Indica el origen** de cada cadena: dominio de traducción y archivo donde aparece.
- **Edición directa** persistida en la tabla `ps_translation` (con prioridad sobre los `.xlf`),
  en archivos legacy `translations/<iso>.php` y en plantillas de email.
- **Sin overrides** ni modificaciones del core de PrestaShop.
- **Copias de seguridad** automáticas al editar plantillas de email.
- Compatible con el sistema de traducción moderno (XLIFF/Symfony) y el legacy (`$_MODULE`).

## Instalación

1. Descarga el contenido de este repositorio.
2. Comprime la carpeta en un ZIP llamado `ps_translationfinder.zip` (la carpeta raíz dentro del
   ZIP debe llamarse `ps_translationfinder`).
3. En tu back office: **Módulos → Subir un módulo** y selecciona el ZIP.
4. Instala y abre la configuración del módulo para empezar a buscar y editar traducciones.

Alternativamente, copia la carpeta `ps_translationfinder/` dentro de `modules/` de tu PrestaShop e
instálala desde **Módulos**.

## Uso

Abre la configuración del módulo, escribe el texto que quieras localizar y el buscador mostrará
todas las coincidencias con su origen. Edita el valor y guarda; el cambio se persiste en el lugar
correcto según el tipo de cadena (BD, legacy o email).

## Documentación

En la carpeta [`docs/`](docs/) encontrarás:

- [`01-DOC-TECNICO.md`](docs/01-DOC-TECNICO.md) — Documentación técnica y arquitectura.
- [`02-DOC-DESARROLLO.md`](docs/02-DOC-DESARROLLO.md) — Guía para desarrolladores.
- [`03-GUIA-USUARIO.md`](docs/03-GUIA-USUARIO.md) — Guía de usuario.

## Licencia

Distribuido bajo la licencia **Academic Free License 3.0 (AFL-3.0)**.
