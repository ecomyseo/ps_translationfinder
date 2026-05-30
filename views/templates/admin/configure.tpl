{*
 * Translation Finder
 *
 * @author    Ecom Experts <ecomyseo@gmail.com>
 * @copyright 2026 Ecom Experts
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 *}
<div class="panel">
  <h3><i class="icon-globe"></i> {l s='Translation Finder' d='Modules.Pstranslationfinder.Admin'}</h3>
  <p>{l s='Buscador unificado de traducciones de PrestaShop. Encuentra cualquier texto (front, back office, modulos, theme y emails) y cambialo desde un solo sitio, sin overrides.' d='Modules.Pstranslationfinder.Admin'}</p>
  <a href="{$ps_tf_admin_link|escape:'html':'UTF-8'}" class="btn btn-primary btn-lg">
    <i class="icon-search"></i> {l s='Abrir el buscador de traducciones' d='Modules.Pstranslationfinder.Admin'}
  </a>
</div>

<form method="post" action="" class="panel">
  <h3><i class="icon-cogs"></i> {l s='Configuracion' d='Modules.Pstranslationfinder.Admin'}</h3>
  <div class="form-group">
    <label>{l s='Modo depuracion (logs en /logs del modulo)' d='Modules.Pstranslationfinder.Admin'}</label>
    <span class="switch prestashop-switch fixed-width-lg">
      <input type="radio" name="PS_TFINDER_DEBUG" id="dbg_on" value="1"{if $ps_tf_debug} checked="checked"{/if} />
      <label for="dbg_on">{l s='Si' d='Modules.Pstranslationfinder.Admin'}</label>
      <input type="radio" name="PS_TFINDER_DEBUG" id="dbg_off" value="0"{if !$ps_tf_debug} checked="checked"{/if} />
      <label for="dbg_off">{l s='No' d='Modules.Pstranslationfinder.Admin'}</label>
      <a class="slide-button btn"></a>
    </span>
  </div>
  <div class="form-group">
    <label>{l s='Buscar tambien en CONTENIDO de BD (tablas *_lang: productos, categorias, CMS, bloques de texto...)' d='Modules.Pstranslationfinder.Admin'}</label>
    <span class="switch prestashop-switch fixed-width-lg">
      <input type="radio" name="PS_TFINDER_CONTENT" id="cnt_on" value="1"{if $ps_tf_content} checked="checked"{/if} />
      <label for="cnt_on">{l s='Si' d='Modules.Pstranslationfinder.Admin'}</label>
      <input type="radio" name="PS_TFINDER_CONTENT" id="cnt_off" value="0"{if !$ps_tf_content} checked="checked"{/if} />
      <label for="cnt_off">{l s='No' d='Modules.Pstranslationfinder.Admin'}</label>
      <a class="slide-button btn"></a>
    </span>
    <p class="help-block">{l s='Activa el apartado "Contenido (BD)". Edita contenido real de la tienda; usalo con cuidado.' d='Modules.Pstranslationfinder.Admin'}</p>
  </div>
  <div class="form-group">
    <label>{l s='Backup de reemplazos (permite restaurar)' d='Modules.Pstranslationfinder.Admin'}</label>
    <span class="switch prestashop-switch fixed-width-lg">
      <input type="radio" name="PS_TFINDER_BACKUP" id="bk_on" value="1"{if $ps_tf_backup} checked="checked"{/if} />
      <label for="bk_on">{l s='Si' d='Modules.Pstranslationfinder.Admin'}</label>
      <input type="radio" name="PS_TFINDER_BACKUP" id="bk_off" value="0"{if !$ps_tf_backup} checked="checked"{/if} />
      <label for="bk_off">{l s='No' d='Modules.Pstranslationfinder.Admin'}</label>
      <a class="slide-button btn"></a>
    </span>
    <p class="help-block">{l s='Si esta activo, cada reemplazo masivo guarda los valores anteriores para poder deshacerlo desde el historial.' d='Modules.Pstranslationfinder.Admin'}</p>
  </div>
  <div class="panel-footer">
    <button type="submit" name="submitPsTranslationfinder" class="btn btn-default pull-right">
      <i class="process-icon-save"></i> {l s='Guardar' d='Modules.Pstranslationfinder.Admin'}
    </button>
  </div>
</form>
