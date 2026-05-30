{*
 * Translation Finder
 *
 * @author    Ecom Experts <ecomyseo@gmail.com>
 * @copyright 2026 Ecom Experts
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 *}
<div id="ps-tf-app" data-ajax="{$ps_tf_ajax_url|escape:'html':'UTF-8'}">
  <div class="ps-tf-layout">

    <aside class="ps-tf-sidebar">
      <h3>{l s='Apartados' d='Modules.Pstranslationfinder.Admin'}</h3>
      <ul class="ps-tf-cats">
        {foreach from=$ps_tf_categories item=cat name=cats}
          <li>
            <a href="#" class="ps-tf-cat{if $smarty.foreach.cats.first} active{/if}" data-cat="{$cat.key|escape:'html':'UTF-8'}">
              {$cat.label|escape:'html':'UTF-8'}
            </a>
          </li>
        {/foreach}
      </ul>
      <div class="ps-tf-help">
        <p>{l s='Busca un texto y te indica donde aparece (front, back, modulos, theme, emails). Edita la traduccion en la columna de la derecha y guarda.' d='Modules.Pstranslationfinder.Admin'}</p>
        <hr>
        <p><strong>{l s='Sintaxis de busqueda (comodin %):' d='Modules.Pstranslationfinder.Admin'}</strong></p>
        <ul class="ps-tf-syntax">
          <li><code>palabra</code> &rarr; {l s='solo esa palabra/frase (exacta)' d='Modules.Pstranslationfinder.Admin'}</li>
          <li><code>%palabra</code> &rarr; {l s='cualquier cosa + palabra + nada (termina en)' d='Modules.Pstranslationfinder.Admin'}</li>
          <li><code>palabra%</code> &rarr; {l s='palabra + cualquier cosa (empieza por)' d='Modules.Pstranslationfinder.Admin'}</li>
          <li><code>%palabra%</code> &rarr; {l s='cualquier cosa + palabra + cualquier cosa (contiene)' d='Modules.Pstranslationfinder.Admin'}</li>
        </ul>
        <p class="text-muted"><small>{l s='Se admiten espacios junto al %: "% palabra %" funciona igual que "%palabra%".' d='Modules.Pstranslationfinder.Admin'}</small></p>
      </div>
    </aside>

    <section class="ps-tf-main">
      <div class="panel ps-tf-searchbar">
        <div class="ps-tf-row">
          <input type="text" id="ps-tf-term" class="form-control" placeholder="{l s='Texto a buscar (min. 3 caracteres)' d='Modules.Pstranslationfinder.Admin'}" />
          <select id="ps-tf-lang" class="form-control">
            {foreach from=$ps_tf_languages item=lang}
              <option value="{$lang.id_lang|intval}"{if $lang.id_lang == $ps_tf_default_lang} selected="selected"{/if}>
                {$lang.name|escape:'html':'UTF-8'} ({$lang.iso_code|escape:'html':'UTF-8'})
              </option>
            {/foreach}
          </select>
          <button type="button" id="ps-tf-search" class="btn btn-primary">
            <i class="icon-search"></i> {l s='Buscar' d='Modules.Pstranslationfinder.Admin'}
          </button>
          <button type="button" id="ps-tf-clearcache" class="btn btn-default">
            <i class="icon-eraser"></i> {l s='Limpiar caché' d='Modules.Pstranslationfinder.Admin'}
          </button>
        </div>
        <div class="ps-tf-row ps-tf-replace-row">
          <input type="text" id="ps-tf-replace" class="form-control" placeholder="{l s='Reemplazar el texto buscado por... (buscar y reemplazar masivo)' d='Modules.Pstranslationfinder.Admin'}" />
          <button type="button" id="ps-tf-replace-all" class="btn btn-warning">
            <i class="icon-refresh"></i> {l s='Reemplazar en todos los resultados' d='Modules.Pstranslationfinder.Admin'}
          </button>
          <label class="ps-tf-backup-lbl">
            <input type="checkbox" id="ps-tf-backup"{if $ps_tf_backup_default} checked="checked"{/if} />
            {l s='Backup (restaurable)' d='Modules.Pstranslationfinder.Admin'}
          </label>
        </div>
        <div id="ps-tf-status" class="ps-tf-status"></div>
      </div>

      <div class="panel">
        <table class="table ps-tf-results">
          <thead>
            <tr>
              <th>{l s='Apartado' d='Modules.Pstranslationfinder.Admin'}</th>
              <th>{l s='Dominio / Origen' d='Modules.Pstranslationfinder.Admin'}</th>
              <th>{l s='Texto original' d='Modules.Pstranslationfinder.Admin'}</th>
              <th>{l s='Traduccion actual' d='Modules.Pstranslationfinder.Admin'}</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="ps-tf-tbody">
            <tr><td colspan="5" class="text-center text-muted">{l s='Introduce un texto y pulsa Buscar.' d='Modules.Pstranslationfinder.Admin'}</td></tr>
          </tbody>
        </table>
      </div>

      <div class="panel ps-tf-history-panel">
        <h3 class="ps-tf-history-toggle">
          <i class="icon-history"></i> {l s='Historial de reemplazos (backup / restaurar)' d='Modules.Pstranslationfinder.Admin'}
        </h3>
        <table class="table ps-tf-history">
          <thead>
            <tr>
              <th>{l s='Fecha' d='Modules.Pstranslationfinder.Admin'}</th>
              <th>{l s='Apartado' d='Modules.Pstranslationfinder.Admin'}</th>
              <th>{l s='Buscado' d='Modules.Pstranslationfinder.Admin'}</th>
              <th>{l s='Reemplazo' d='Modules.Pstranslationfinder.Admin'}</th>
              <th>{l s='Nº' d='Modules.Pstranslationfinder.Admin'}</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="ps-tf-history-tbody">
            <tr><td colspan="6" class="text-center text-muted">{l s='Sin reemplazos registrados.' d='Modules.Pstranslationfinder.Admin'}</td></tr>
          </tbody>
        </table>
      </div>
    </section>

  </div>
</div>
