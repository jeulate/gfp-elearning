/**
 * FairPlay LMS — Ponderación de Preguntas
 * Se inyecta en el editor visual frontend de MasterStudy (/edit-quiz/{id}/).
 *
 * Comunicación: REST /wp-json/fplms/v1/quiz/{id}/weights (GET + POST)
 */
(function () {
    'use strict';

    var cfg = window.fplmsWeights;

    // Auto-detect si PHP no inyectó la configuración
    if ( ! cfg ) {
        var _match = window.location.pathname.match( /\/edit-quiz\/(\d+)/ );
        if ( ! _match ) return;
        var _quizId = parseInt( _match[1], 10 );
        if ( ! _quizId ) return;

        // Intentar obtener nonce de distintas fuentes que WordPress/MasterStudy exponen
        var _nonce = '';
        var _root  = '/wp-json';

        if ( window.wpApiSettings ) {
            _nonce = window.wpApiSettings.nonce || '';
            _root  = ( window.wpApiSettings.root || '/wp-json/' ).replace( /\/$/, '' );
        } else if ( window.stmLms && window.stmLms.nonce ) {
            _nonce = window.stmLms.nonce;
        } else if ( window.stmLmsConfig && window.stmLmsConfig.nonce ) {
            _nonce = window.stmLmsConfig.nonce;
        } else {
            // Buscar en cualquier objeto de ventana que tenga 'nonce'
            var _keys = Object.keys( window );
            for ( var _i = 0; _i < _keys.length; _i++ ) {
                try {
                    var _obj = window[ _keys[ _i ] ];
                    if ( _obj && typeof _obj === 'object' && typeof _obj.nonce === 'string' && _obj.nonce.length > 5 ) {
                        _nonce = _obj.nonce;
                        break;
                    }
                } catch ( _e ) { /* ignorar */ }
            }
        }

        cfg = {
            quizId   : _quizId,
            restBase  : _root + '/fplms/v1/quiz/' + _quizId + '/weights',
            nonce     : _nonce
        };
    }

    /* ─── Estado ─────────────────────────────────────────────────────────────── */
    var state = {
        mode           : '',
        weights        : {},
        questions      : [],
        auto_weights   : {},
        global_default : 'auto',
        effective_mode : 'auto'
    };

    var section = null;

    /* ─── REST helpers ───────────────────────────────────────────────────────── */

    function apiFetch( url, opts ) {
        opts = opts || {};
        opts.headers = Object.assign(
            { 'X-WP-Nonce': cfg.nonce, 'Content-Type': 'application/json' },
            opts.headers || {}
        );
        return fetch( url, opts ).then( function ( r ) {
            if ( ! r.ok ) throw new Error( 'HTTP ' + r.status );
            return r.json();
        } );
    }

    function loadData() {
        apiFetch( cfg.restBase )
            .then( function ( d ) {
                state.mode           = d.mode            || '';
                state.weights        = d.weights         || {};
                state.questions      = d.questions       || [];
                state.auto_weights   = d.auto_weights    || {};
                state.global_default = d.global_default  || 'auto';
                state.effective_mode = d.effective_mode  || 'auto';

                if ( state.questions.length === 0 ) {
                    console.warn( '[fplms-weights] ⚠ questions vacío. Meta keys del quiz:', d.debug_meta_keys );
                    console.info( '[fplms-weights] Para diagnóstico corre en consola:\n  fetch("' + cfg.restBase + '", {headers:{"X-WP-Nonce":"' + cfg.nonce + '"}}).then(r=>r.json()).then(d=>console.table(d.debug_meta_keys))' );
                } else {
                    console.log( '[fplms-weights] ✓', state.questions.length, 'preguntas cargadas' );
                }

                renderUI();
            } )
            .catch( function ( e ) {
                console.warn( '[fplms-weights] Error al cargar:', e );
            } );
    }

    function saveData() {
        var mode    = getModeFromUI();
        var weights = getWeightsFromUI();
        return apiFetch( cfg.restBase, {
            method : 'POST',
            body   : JSON.stringify( { mode: mode, weights: weights } )
        } );
    }

    /* ─── Leer valores del UI ────────────────────────────────────────────────── */

    function getModeFromUI() {
        if ( ! section ) return state.mode;
        var checked = section.querySelector( 'input[name="fplms_wt_mode_fe"]:checked' );
        return checked ? checked.value : '';
    }

    function getWeightsFromUI() {
        var out = {};
        if ( ! section ) return out;
        section.querySelectorAll( '.fplms-fe-weight-inp' ).forEach( function ( inp ) {
            var qid = parseInt( inp.dataset.qid, 10 );
            if ( qid ) out[ qid ] = parseFloat( inp.value ) || 0;
        } );
        return out;
    }

    /* ─── Render ─────────────────────────────────────────────────────────────── */

    function renderUI() {
        if ( ! section ) buildSection();
        if ( ! section ) return;

        /* Modo radios */
        section.querySelectorAll( 'input[name="fplms_wt_mode_fe"]' ).forEach( function ( r ) {
            r.checked = ( r.value === state.mode );
        } );

        /* Etiqueta global */
        var globalTag = section.querySelector( '.fplms-fe-global-tag' );
        if ( globalTag ) {
            globalTag.textContent = 'Global: ' + ( 'manual' === state.global_default ? 'Manual' : 'Automática' );
        }

        /* Filas de preguntas */
        var tbody = section.querySelector( '.fplms-fe-tbody' );
        if ( ! tbody ) return;
        tbody.innerHTML = '';

        state.questions.forEach( function ( q, idx ) {
            var savedW = parseFloat( state.weights[ q.id ] ) || 0;
            var autoW  = parseFloat( state.auto_weights[ q.id ] ) || 0;

            var tr = document.createElement( 'tr' );
            tr.innerHTML =
                '<td style="color:#9ca3af;padding:7px 10px;">' + ( idx + 1 ) + '</td>' +
                '<td style="font-weight:500;color:#1f2937;max-width:380px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;padding:7px 10px;" title="' + esc( q.title ) + '">' + esc( q.title ) + '</td>' +
                '<td style="color:#6b7280;font-size:12px;padding:7px 10px;">' + autoW.toFixed(2) + '</td>' +
                '<td style="text-align:right;padding:7px 10px;">' +
                    '<input type="number" class="fplms-fe-weight-inp"' +
                    ' data-qid="' + q.id + '"' +
                    ' value="' + ( savedW > 0 ? savedW.toFixed(2) : '' ) + '"' +
                    ' min="0" max="100" step="0.01" placeholder="0.00"' +
                    ' style="width:86px;padding:5px 8px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;text-align:right;">' +
                '</td>';

            tbody.appendChild( tr );
            tr.querySelector( '.fplms-fe-weight-inp' ).addEventListener( 'input', updateTotal );
        } );

        updateSections();
        updateTotal();
    }

    function esc( str ) {
        return String( str )
            .replace( /&/g, '&amp;' )
            .replace( /</g, '&lt;' )
            .replace( />/g, '&gt;' )
            .replace( /"/g, '&quot;' );
    }

    function updateSections() {
        if ( ! section ) return;
        var mode      = getModeFromUI();
        var effective = mode === '' ? state.global_default : mode;
        var autoDiv   = section.querySelector( '.fplms-fe-auto-sec' );
        var manualDiv = section.querySelector( '.fplms-fe-manual-sec' );
        if ( autoDiv )   autoDiv.style.display   = effective !== 'manual' ? 'block' : 'none';
        if ( manualDiv ) manualDiv.style.display = effective === 'manual'  ? 'block' : 'none';
        if ( effective === 'manual' ) updateTotal();
    }

    function updateTotal() {
        if ( ! section ) return;
        var inputs = section.querySelectorAll( '.fplms-fe-weight-inp' );
        var total  = 0;
        inputs.forEach( function ( inp ) { total += parseFloat( inp.value ) || 0; } );
        total = Math.round( total * 100 ) / 100;

        var el  = section.querySelector( '.fplms-fe-total-num' );
        var msg = section.querySelector( '.fplms-fe-total-msg' );
        if ( ! el ) return;

        el.textContent = total.toFixed(2);
        var ok = Math.abs( total - 100 ) < 0.011;
        el.style.color = ok ? '#16a34a' : ( total > 100 ? '#dc2626' : '#d97706' );

        if ( msg ) {
            if ( ok ) {
                msg.textContent = '✓ Suma correcta';
                msg.style.color = '#16a34a';
            } else if ( total > 100 ) {
                msg.textContent = '⚠ Supera 100 en ' + ( total - 100 ).toFixed(2);
                msg.style.color = '#dc2626';
            } else {
                msg.textContent = '⚠ Faltan ' + ( 100 - total ).toFixed(2) + ' puntos';
                msg.style.color = '#d97706';
            }
        }
    }

    /* ─── Construir sección ──────────────────────────────────────────────────── */

    function buildSection() {
        section    = document.createElement( 'div' );
        section.id = 'fplms-weights-section';
        section.setAttribute( 'style', [
            'padding:24px',
            'border-top:1px solid #e5e7eb',
            'background:#fff',
            'font-family:inherit',
            'font-size:14px',
            'color:#1f2937'
        ].join( ';' ) );

        section.innerHTML = [
            /* ─ estilos inline scope ─ */
            '<style>',
            '#fplms-weights-section .fw-card{background:#f8f9fc;border:1px solid #e5e7eb;border-radius:8px;padding:14px 16px;margin-bottom:16px;}',
            '#fplms-weights-section .fw-label{display:inline-flex;align-items:center;gap:8px;cursor:pointer;font-weight:500;color:#374151;margin-right:20px;margin-bottom:4px;}',
            '#fplms-weights-section table{width:100%;border-collapse:collapse;font-size:13px;}',
            '#fplms-weights-section thead th{text-align:left;padding:7px 10px;background:#f3f4f6;color:#6b7280;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.3px;border-bottom:1px solid #e5e7eb;}',
            '#fplms-weights-section tbody tr{border-bottom:1px solid #f3f4f6;}',
            '#fplms-weights-section tbody tr:hover{background:#f9fafb;}',
            '#fplms-weights-section .fw-save-btn{padding:9px 22px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;transition:opacity .2s;}',
            '#fplms-weights-section .fw-save-btn:hover{opacity:.85;}',
            '#fplms-weights-section .fw-save-btn:disabled{opacity:.55;cursor:not-allowed;}',
            '#fplms-weights-section .fw-dist-btn{padding:6px 14px;background:#667eea;color:#fff;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;margin-left:auto;}',
            '#fplms-weights-section .fw-dist-btn:hover{opacity:.85;}',
            '#fplms-weights-section .fw-notice{display:none;font-size:12px;padding:7px 12px;border-radius:6px;border-width:1px;border-style:solid;}',
            '</style>',

            /* ─ título ─ */
            '<div style="display:flex;align-items:center;gap:10px;margin-bottom:18px;">',
                '<svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">',
                    '<circle cx="11" cy="11" r="10" stroke="#667eea" stroke-width="1.5"/>',
                    '<text x="11" y="15" text-anchor="middle" font-size="12" fill="#667eea" font-weight="700">%</text>',
                '</svg>',
                '<h3 style="margin:0;font-size:16px;font-weight:700;color:#1f2937;">Ponderación de Preguntas</h3>',
            '</div>',

            /* ─ modo ─ */
            '<div class="fw-card">',
                '<p style="margin:0 0 10px;font-weight:600;color:#374151;font-size:13px;">Modo de ponderación</p>',
                '<div>',
                    '<label class="fw-label">',
                        '<input type="radio" name="fplms_wt_mode_fe" value="">',
                        'Heredar global',
                        '<span class="fplms-fe-global-tag" style="display:inline-block;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600;background:#e0f2fe;color:#0369a1;margin-left:4px;">Global: Automática</span>',
                    '</label>',
                    '<label class="fw-label">',
                        '<input type="radio" name="fplms_wt_mode_fe" value="auto">',
                        'Distribución automática',
                    '</label>',
                    '<label class="fw-label">',
                        '<input type="radio" name="fplms_wt_mode_fe" value="manual">',
                        'Ponderación manual',
                    '</label>',
                '</div>',
            '</div>',

            /* ─ sección automática ─ */
            '<div class="fplms-fe-auto-sec">',
                '<div style="padding:14px 16px;border-radius:8px;background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;font-size:13px;margin-bottom:16px;">',
                    '<strong>Distribución automática:</strong> los 100 puntos se reparten equitativamente entre todas las preguntas del quiz.',
                '</div>',
            '</div>',

            /* ─ sección manual ─ */
            '<div class="fplms-fe-manual-sec" style="display:none;">',
                '<div style="overflow-x:auto;margin-bottom:0;">',
                '<table>',
                    '<thead>',
                        '<tr>',
                            '<th style="width:40px;">#</th>',
                            '<th>Pregunta</th>',
                            '<th style="width:110px;">Pts. auto</th>',
                            '<th style="width:130px;text-align:right;">Peso (pts)</th>',
                        '</tr>',
                    '</thead>',
                    '<tbody class="fplms-fe-tbody"></tbody>',
                '</table>',
                '</div>',
                '<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:12px 14px;margin-top:12px;background:#f8f9fc;border:1px solid #e5e7eb;border-radius:8px;">',
                    '<span style="color:#6b7280;font-size:13px;">Total acumulado:</span>',
                    '<span class="fplms-fe-total-num" style="font-size:20px;font-weight:700;color:#dc2626;">0.00</span>',
                    '<span style="color:#6b7280;font-size:13px;">/ 100</span>',
                    '<span class="fplms-fe-total-msg" style="font-size:12px;"></span>',
                    '<button type="button" class="fw-dist-btn">Distribuir equitativamente</button>',
                '</div>',
            '</div>',

            /* ─ botón guardar ─ */
            '<div style="margin-top:20px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;">',
                '<button type="button" class="fw-save-btn">Guardar ponderación</button>',
                '<span class="fw-notice"></span>',
            '</div>',
        ].join( '' );

        /* ─ eventos modo ─ */
        section.querySelectorAll( 'input[name="fplms_wt_mode_fe"]' ).forEach( function ( r ) {
            r.addEventListener( 'change', updateSections );
        } );

        /* ─ distribuir ─ */
        var distBtn = section.querySelector( '.fw-dist-btn' );
        if ( distBtn ) {
            distBtn.addEventListener( 'click', function () {
                var inputs = section.querySelectorAll( '.fplms-fe-weight-inp' );
                var n = inputs.length;
                if ( ! n ) return;
                var base = Math.floor( ( 100 / n ) * 100 ) / 100;
                var rem  = Math.round( ( 100 - base * n ) * 100 ) / 100;
                inputs.forEach( function ( inp, i ) {
                    inp.value = ( i === n - 1 )
                        ? ( Math.round( ( base + rem ) * 100 ) / 100 ).toFixed(2)
                        : base.toFixed(2);
                } );
                updateTotal();
            } );
        }

        /* ─ guardar ─ */
        var saveBtn = section.querySelector( '.fw-save-btn' );
        if ( saveBtn ) {
            saveBtn.addEventListener( 'click', function () {
                saveBtn.disabled    = true;
                saveBtn.textContent = 'Guardando…';
                saveData()
                    .then( function ( res ) {
                        if ( res && res.success ) {
                            showNotice( '✓ Ponderación guardada correctamente', '#f0fdf4', '#166534', '#bbf7d0' );
                            state.mode    = getModeFromUI();
                            state.weights = getWeightsFromUI();
                        } else {
                            showNotice( '✗ Error al guardar. Inténtalo de nuevo.', '#fef2f2', '#dc2626', '#fecaca' );
                        }
                    } )
                    .catch( function () {
                        showNotice( '✗ Error de conexión.', '#fef2f2', '#dc2626', '#fecaca' );
                    } )
                    .finally( function () {
                        saveBtn.disabled    = false;
                        saveBtn.textContent = 'Guardar ponderación';
                    } );
            } );
        }
    }

    function showNotice( msg, bg, color, borderColor ) {
        if ( ! section ) return;
        var n = section.querySelector( '.fw-notice' );
        if ( ! n ) return;
        n.textContent        = msg;
        n.style.display      = 'inline-block';
        n.style.background   = bg;
        n.style.color        = color;
        n.style.borderColor  = borderColor || color;
        clearTimeout( n._timer );
        n._timer = setTimeout( function () { n.style.display = 'none'; }, 5000 );
    }

    /* ─── Inyectar en la página ──────────────────────────────────────────────── */

    function injectSection() {
        /* Verificar que la sección no esté ya en el DOM */
        if ( section && section.parentNode ) return;

        /* Buscar el <footer> que contiene el botón "Guardar" del editor */
        var footers = document.querySelectorAll( 'footer' );
        var targetFooter = null;
        footers.forEach( function ( f ) {
            var btn = f.querySelector( 'button[type="submit"]' );
            if ( btn && btn.textContent.trim() === 'Guardar' ) {
                targetFooter = f;
            }
        } );

        if ( ! targetFooter ) return;

        if ( ! section ) buildSection();
        targetFooter.parentNode.insertBefore( section, targetFooter );
    }

    /* ─── Arranque con MutationObserver ─────────────────────────────────────── */

    var dataLoaded = false;

    function tryInit() {
        /* Verificar si el footer del editor ya existe */
        var footers = document.querySelectorAll( 'footer' );
        var found   = false;
        footers.forEach( function ( f ) {
            var btn = f.querySelector( 'button[type="submit"]' );
            if ( btn && btn.textContent.trim() === 'Guardar' ) found = true;
        } );

        if ( ! found ) return;

        injectSection();

        if ( ! dataLoaded && section && section.parentNode ) {
            dataLoaded = true;
            loadData();
        }
    }

    /* Observar cambios en el DOM para detectar cuándo el editor React termina de renderizarse */
    var observer = new MutationObserver( function () {
        tryInit();
        /* Si la sección fue eliminada por React, re-inyectar */
        if ( section && ! section.parentNode && dataLoaded ) {
            injectSection();
            renderUI();
        }
    } );

    observer.observe( document.body, { childList: true, subtree: true } );

    /* Intentar de inmediato por si el DOM ya está listo */
    if ( document.readyState === 'complete' || document.readyState === 'interactive' ) {
        tryInit();
    } else {
        document.addEventListener( 'DOMContentLoaded', tryInit );
    }

} )();
