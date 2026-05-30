/**
 * Translation Finder
 *
 * @author    Ecom Experts <ecomyseo@gmail.com>
 * @copyright 2026 Ecom Experts
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
(function ($) {
  'use strict';

  $(function () {
    var $app = $('#ps-tf-app');
    if (!$app.length) {
      return;
    }

    var ajaxUrl = $app.data('ajax');
    var currentCat = 'all';
    var lastRows = [];

    var CAT_LABELS = {
      all: 'Todos',
      front: 'Front / Theme',
      back: 'Back office',
      modules: 'Modulos',
      emails: 'Emails',
      other: 'Legacy / Otros',
      content: 'Contenido (BD)'
    };

    function esc(str) {
      return $('<div/>').text(str === null || str === undefined ? '' : String(str)).html();
    }

    function setStatus(msg, type) {
      var cls = type === 'error' ? 'text-danger' : (type === 'ok' ? 'text-success' : 'text-muted');
      $('#ps-tf-status').html('<span class="' + cls + '">' + esc(msg) + '</span>');
    }

    function rowsHtml(rows) {
      if (!rows.length) {
        return '<tr><td colspan="5" class="text-center text-muted">Sin resultados.</td></tr>';
      }
      var html = '';
      rows.forEach(function (r, idx) {
        var origin = r.origin === 'db' ? 'BD' : (r.origin === 'email' ? 'Email' : (r.origin === 'legacy' ? 'Legacy' : 'Archivo'));
        var src = r.source;
        if (r.origin === 'email' && r.snippet) {
          src = r.snippet;
        }
        html += '<tr data-idx="' + idx + '">';
        html += '<td><span class="label label-info">' + esc(CAT_LABELS[r.category] || r.category) + '</span></td>';
        html += '<td><code>' + esc(r.domain) + '</code><br><small class="text-muted">' + esc(origin) + ' &middot; ' + esc(r.file) + '</small></td>';
        html += '<td class="ps-tf-source">' + esc(src) + '</td>';
        html += '<td><textarea id="ps-tf-ta-' + idx + '" class="form-control ps-tf-input" rows="5">' + esc(r.translation) + '</textarea></td>';
        html += '<td>'
          + '<button type="button" class="btn btn-default btn-sm ps-tf-htmlbtn">Editor HTML</button>'
          + ' <button type="button" class="btn btn-primary btn-sm ps-tf-save">Guardar</button>'
          + '</td>';
        html += '</tr>';
      });
      return html;
    }

    function doSearch() {
      var term = $('#ps-tf-term').val();
      var idLang = $('#ps-tf-lang').val();
      if (!term || term.trim().length < 3) {
        setStatus('Escribe al menos 3 caracteres.', 'error');
        return;
      }
      setStatus('Buscando...');
      $.ajax({
        url: ajaxUrl,
        type: 'POST',
        dataType: 'json',
        data: { ajax: 1, action: 'Search', term: term, category: currentCat, id_lang: idLang }
      }).done(function (resp) {
        if (!resp || !resp.success) {
          setStatus(resp && resp.message ? resp.message : 'Error en la busqueda.', 'error');
          $('#ps-tf-tbody').html(rowsHtml([]));
          return;
        }
        lastRows = resp.rows || [];
        $('#ps-tf-tbody').html(rowsHtml(lastRows));
        var catLabel = CAT_LABELS[currentCat] || currentCat;
        var msg = resp.total + ' resultado(s) en "' + catLabel + '".';
        if (resp.total === 0 && currentCat !== 'all') {
          msg += ' Prueba en "Todos" o en otro apartado (p.ej. el texto puede estar en Front/Theme o Modulos).';
        }
        if (resp.total === 0) {
          msg += ' Recuerda: sin % busca EXACTO; usa %' + term.trim().replace(/%/g, '') + '% para "contiene".';
        }
        if (resp.truncated) {
          msg += ' Resultados limitados a 300; afina la busqueda.';
        }
        setStatus(msg, resp.total === 0 ? 'error' : 'ok');
      }).fail(function (xhr) {
        setStatus('Error de conexion con el servidor (' + (xhr && xhr.status ? xhr.status : '?') + ').', 'error');
      });
    }

    // Devuelve el valor actual de la fila leyendo del editor activo (TinyMCE,
    // vista WYSIWYG contenteditable, o el textarea de codigo).
    function getRowValue($tr) {
      var $ta = $tr.find('.ps-tf-input');
      var id = $ta.attr('id');
      if (window.tinymce && id && tinymce.get(id)) {
        return tinymce.get(id).getContent();
      }
      var $rte = $tr.find('.ps-tf-rte');
      if ($rte.length) {
        return $rte.html();
      }
      return $ta.val();
    }

    function toggleHtmlEditor($tr, $btn) {
      var $ta = $tr.find('.ps-tf-input');
      var id = $ta.attr('id');
      if (window.tinymce) {
        if (tinymce.get(id)) {
          $ta.val(tinymce.get(id).getContent());
          tinymce.get(id).remove();
          $btn.text('Editor HTML');
        } else {
          tinymce.init({
            selector: '#' + id,
            menubar: false,
            branding: false,
            height: 220,
            plugins: 'code link lists table',
            toolbar: 'undo redo | bold italic underline | bullist numlist | link | code',
            convert_urls: false
          });
          $btn.text('Ver codigo');
        }
        return;
      }
      // Fallback sin TinyMCE: vista WYSIWYG con contenteditable.
      var $rte = $tr.find('.ps-tf-rte');
      if ($rte.length) {
        $ta.val($rte.html()).show();
        $rte.remove();
        $btn.text('Editor HTML');
      } else {
        $rte = $('<div class="form-control ps-tf-rte" contenteditable="true"></div>').html($ta.val());
        $ta.hide().after($rte);
        $btn.text('Ver codigo');
      }
    }

    function doSave($tr) {
      var row = lastRows[$tr.data('idx')];
      if (!row) { return; }
      var value = getRowValue($tr);
      var idLang = $('#ps-tf-lang').val();
      var $btn = $tr.find('.ps-tf-save');
      $btn.prop('disabled', true).text('Guardando...');
      $.ajax({
        url: ajaxUrl,
        type: 'POST',
        dataType: 'json',
        data: { ajax: 1, action: 'Save', id_lang: idLang, value: value, row: JSON.stringify(row) }
      }).done(function (resp) {
        $btn.prop('disabled', false).text('Guardar');
        if (resp && resp.success) {
          $btn.removeClass('btn-primary').addClass('btn-success').text('Guardado');
          setTimeout(function () { $btn.removeClass('btn-success').addClass('btn-primary').text('Guardar'); }, 2000);
          setStatus(resp.message, 'ok');
        } else {
          setStatus(resp && resp.message ? resp.message : 'No se pudo guardar.', 'error');
        }
      }).fail(function () {
        $btn.prop('disabled', false).text('Guardar');
        setStatus('Error de conexion al guardar.', 'error');
      });
    }

    // Delegacion desde el contenedor: funciona aunque el contenido se repinte.
    $app.on('click', '#ps-tf-search', doSearch);

    $app.on('keypress', '#ps-tf-term', function (e) {
      if (e.which === 13) { doSearch(); }
    });

    $app.on('click', '.ps-tf-cat', function (e) {
      e.preventDefault();
      $app.find('.ps-tf-cat').removeClass('active');
      $(this).addClass('active');
      currentCat = $(this).data('cat');
      if ($('#ps-tf-term').val().trim().length >= 3) {
        doSearch();
      }
    });

    $app.on('click', '.ps-tf-save', function () {
      doSave($(this).closest('tr'));
    });

    $app.on('click', '.ps-tf-htmlbtn', function () {
      toggleHtmlEditor($(this).closest('tr'), $(this));
    });

    $app.on('click', '#ps-tf-replace-all', function () {
      var term = $('#ps-tf-term').val();
      var replace = $('#ps-tf-replace').val();
      var idLang = $('#ps-tf-lang').val();
      if (!term || term.trim().length < 3) {
        setStatus('Escribe al menos 3 caracteres en el texto a buscar.', 'error');
        return;
      }
      if (!replace || replace === term) {
        setStatus('Escribe un texto de reemplazo distinto.', 'error');
        return;
      }
      if (!window.confirm('Se reemplazara "' + term + '" por "' + replace + '" en TODAS las coincidencias del apartado seleccionado y se guardaran los cambios.\n\nEn contenido HTML solo se cambia el texto visible (no se tocan etiquetas ni atributos como class/href). Hay backup para restaurar.\n\nContinuar?')) {
        return;
      }
      var $btn = $(this);
      var backup = $('#ps-tf-backup').is(':checked') ? 1 : 0;
      $btn.prop('disabled', true);
      setStatus('Reemplazando en todos los resultados...');
      $.ajax({
        url: ajaxUrl,
        type: 'POST',
        dataType: 'json',
        data: { ajax: 1, action: 'Replace', term: term, replace: replace, category: currentCat, id_lang: idLang, backup: backup }
      }).done(function (resp) {
        $btn.prop('disabled', false);
        setStatus(resp && resp.message ? resp.message : 'Reemplazo terminado.', resp && resp.success ? 'ok' : 'error');
        if (resp && resp.success) {
          doSearch();
          loadHistory();
        }
      }).fail(function () {
        $btn.prop('disabled', false);
        setStatus('Error de conexion en el reemplazo.', 'error');
      });
    });

    function renderHistory(list) {
      if (!list || !list.length) {
        return '<tr><td colspan="6" class="text-center text-muted">Sin reemplazos registrados.</td></tr>';
      }
      var html = '';
      list.forEach(function (h) {
        var restored = h.restored
          ? '<span class="label label-default">Restaurado ' + esc(h.restored_date) + '</span>'
          : '<button type="button" class="btn btn-success btn-sm ps-tf-restore" data-id="' + esc(h.id) + '">Restaurar</button>';
        html += '<tr>';
        html += '<td><small>' + esc(h.date) + '</small></td>';
        html += '<td><span class="label label-info">' + esc(CAT_LABELS[h.category] || h.category) + '</span></td>';
        html += '<td>' + esc(h.term) + '</td>';
        html += '<td>' + esc(h.replace) + '</td>';
        html += '<td>' + esc(h.count) + '</td>';
        html += '<td>' + restored + ' <button type="button" class="btn btn-default btn-sm ps-tf-delhist" data-id="' + esc(h.id) + '" title="Eliminar del historial"><i class="icon-trash"></i></button></td>';
        html += '</tr>';
      });
      return html;
    }

    function loadHistory() {
      $.ajax({
        url: ajaxUrl, type: 'POST', dataType: 'json',
        data: { ajax: 1, action: 'History' }
      }).done(function (resp) {
        if (resp && resp.success) {
          $('#ps-tf-history-tbody').html(renderHistory(resp.history));
        }
      });
    }

    $app.on('click', '.ps-tf-restore', function () {
      var id = $(this).data('id');
      if (!window.confirm('Restaurar este reemplazo? Se devolveran los textos a su valor anterior.')) { return; }
      var $b = $(this).prop('disabled', true).text('Restaurando...');
      $.ajax({
        url: ajaxUrl, type: 'POST', dataType: 'json',
        data: { ajax: 1, action: 'Restore', history_id: id }
      }).done(function (resp) {
        setStatus(resp && resp.message ? resp.message : 'Restaurado.', resp && resp.success ? 'ok' : 'error');
        if (resp && resp.history) { $('#ps-tf-history-tbody').html(renderHistory(resp.history)); }
        if (resp && resp.success) { doSearch(); }
      }).fail(function () {
        $b.prop('disabled', false).text('Restaurar');
        setStatus('Error de conexion al restaurar.', 'error');
      });
    });

    $app.on('click', '.ps-tf-delhist', function () {
      var id = $(this).data('id');
      if (!window.confirm('Eliminar esta entrada del historial? (no revierte los cambios)')) { return; }
      $.ajax({
        url: ajaxUrl, type: 'POST', dataType: 'json',
        data: { ajax: 1, action: 'DeleteHistory', history_id: id }
      }).done(function (resp) {
        if (resp && resp.history) { $('#ps-tf-history-tbody').html(renderHistory(resp.history)); }
      });
    });

    loadHistory();

    $app.on('click', '#ps-tf-clearcache', function () {
      setStatus('Limpiando cache...');
      $.ajax({
        url: ajaxUrl,
        type: 'POST',
        dataType: 'json',
        data: { ajax: 1, action: 'ClearCache' }
      }).done(function (resp) {
        setStatus(resp && resp.message ? resp.message : 'Cache limpiada.', 'ok');
      }).fail(function () {
        setStatus('Error al limpiar la cache.', 'error');
      });
    });
  });
})(typeof jQuery !== 'undefined' ? jQuery : window.$);
