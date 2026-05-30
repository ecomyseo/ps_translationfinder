# Translation Finder — Guía de Usuario

## ¿Qué hace?
Te permite **buscar cualquier texto** de tu tienda y saber **dónde aparece** (front, back office, módulos, theme o emails), y **cambiarlo** desde un único buscador, sin tener que recorrer todos los paneles de traducción de PrestaShop.

## Instalación
1. Copia la carpeta `ps_translationfinder` en `/modules`.
2. En el back office: **Módulos → Gestor de módulos**, busca "Translation Finder" e **instala**.
3. Se crea un acceso en el menú **Localización → Buscar traducción** (y un botón desde la configuración del módulo).

## Uso del buscador
1. Abre **Localización → Buscar traducción**.
2. Escribe el texto que buscas (mínimo **3 caracteres**) y elige el **idioma**.
3. Pulsa **Buscar**.
4. A la **izquierda** filtra por apartado:
   - **Todos**
   - **Front / Theme** (lo que ve el cliente)
   - **Back office** (administración)
   - **Módulos**
   - **Emails**
   - **Legacy / Otros**
5. En la tabla de resultados verás, por cada coincidencia: el **apartado**, el **dominio y archivo** donde está, el **texto original** y la **traducción actual** (editable).

## Cambiar una traducción
1. Edita el texto en la columna **Traducción actual**.
2. Pulsa **Guardar** en esa fila.
3. El cambio se guarda y se limpia la caché automáticamente. Recarga la página de la tienda para verlo.

> **Front, back, módulos y theme:** el cambio se guarda en la base de datos (método oficial de PrestaShop), **sin tocar archivos del core**. Es seguro y sobrevive a actualizaciones del catálogo.
>
> **Emails:** al ser plantillas, el texto se cambia directamente en el archivo del email. El módulo crea automáticamente una **copia de seguridad** antes de modificarlo.

## Sintaxis de búsqueda (comodín `%`)
- `palabra` → busca **solo esa palabra/frase exacta**.
- `%palabra` → cualquier cosa + palabra + nada (**termina en**).
- `palabra%` → palabra + cualquier cosa (**empieza por**).
- `%palabra%` → cualquier cosa + palabra + cualquier cosa (**contiene**).

## Buscar y reemplazar (masivo)
1. Busca el texto (puedes usar comodines).
2. En el campo **"Reemplazar el texto buscado por..."** escribe el texto nuevo.
3. Pulsa **Reemplazar en todos los resultados** y confirma.
4. Sustituye la palabra/frase en la traducción de **todas** las coincidencias del apartado seleccionado y guarda los cambios (la caché se limpia al terminar). El reemplazo usa la palabra literal, ignorando los `%`.

## Contenido de la tienda (tablas `*_lang`) — opcional
Por defecto el buscador trabaja con traducciones de **interfaz**. Si quieres buscar/editar también **contenido** (nombres de productos, categorías, páginas CMS, bloques de texto…):
1. Ve a la **configuración** del módulo y activa **"Buscar también en CONTENIDO de BD"**.
2. Aparecerá el apartado **"Contenido (BD)"** en la columna izquierda.
3. Selecciónalo y busca: encontrará el texto en cualquier tabla `*_lang` y podrás editarlo.
> Edita contenido real de la tienda directamente en BD; úsalo con cuidado. No se incluye en "Todos" por rendimiento: hay que elegir el apartado expresamente.

## Backup e historial de reemplazos (restaurar)
- Junto al botón de reemplazo hay una casilla **"Backup (restaurable)"** (activada por defecto, configurable).
- Con el backup activo, cada **reemplazo masivo** guarda los valores anteriores.
- Debajo de los resultados verás el **Historial de reemplazos** con: fecha, apartado, texto buscado, reemplazo, nº de cambios y un botón **Restaurar** para deshacer ese reemplazo (devuelve los textos a su valor anterior). También puedes eliminar una entrada del historial (no revierte, solo la quita de la lista).

## Botones útiles
- **Limpiar caché:** fuerza el refresco si un cambio no se ve reflejado.
- El buscador limita a **300 resultados**; si te avisa de que hay más, afina el texto.

## Preguntas frecuentes
- **No encuentro un texto.** Prueba con menos palabras, revisa el idioma seleccionado y el apartado "Todos".
- **Cambié un email y no se aplica.** Comprueba que la carpeta `mails/` tiene permisos de escritura.
- **El cambio no aparece en la tienda.** Pulsa **Limpiar caché** y recarga.

## Modo depuración
En la configuración del módulo puedes activar **Modo depuración**: genera registros en la carpeta `/logs` del módulo, útiles para soporte técnico.
