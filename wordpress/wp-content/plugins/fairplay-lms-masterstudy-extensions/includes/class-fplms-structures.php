<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FairPlay_LMS_Structures_Controller {

    /**
     * Registra las taxonomías internas para estructuras.
     */
    public function register_taxonomies(): void {

        $common_args = [
            'public'       => false,
            'show_ui'      => false,
            'show_in_menu' => false,
            'hierarchical' => false,
        ];

        register_taxonomy(
            FairPlay_LMS_Config::TAX_CITY,
            'post',
            array_merge( $common_args, [ 'label' => 'Ciudades' ] )
        );

        register_taxonomy(
            FairPlay_LMS_Config::TAX_CHANNEL,
            'post',
            array_merge( $common_args, [ 'label' => 'Canales / Franquicias' ] )
        );

        register_taxonomy(
            FairPlay_LMS_Config::TAX_BRANCH,
            'post',
            array_merge( $common_args, [ 'label' => 'Sucursales' ] )
        );

        register_taxonomy(
            FairPlay_LMS_Config::TAX_ROLE,
            'post',
            array_merge( $common_args, [ 'label' => 'Cargos' ] )
        );
    }

    /**
     * Manejo del formulario de estructuras (crear / activar / desactivar).
     */
    public function handle_form(): void {

        if ( ! isset( $_POST['fplms_structures_action'] ) ) {
            return;
        }

        if ( ! current_user_can( FairPlay_LMS_Config::CAP_MANAGE_STRUCTURES ) ) {
            return;
        }

        if (
            ! isset( $_POST['fplms_structures_nonce'] ) ||
            ! wp_verify_nonce( $_POST['fplms_structures_nonce'], 'fplms_structures_save' )
        ) {
            return;
        }

        $action   = sanitize_text_field( wp_unslash( $_POST['fplms_structures_action'] ) );
        $taxonomy = sanitize_text_field( wp_unslash( $_POST['fplms_taxonomy'] ?? '' ) );

        $allowed_taxonomies = [
            FairPlay_LMS_Config::TAX_CITY,
            FairPlay_LMS_Config::TAX_CHANNEL,
            FairPlay_LMS_Config::TAX_BRANCH,
            FairPlay_LMS_Config::TAX_ROLE,
        ];

        if ( ! in_array( $taxonomy, $allowed_taxonomies, true ) ) {
            return;
        }

        if ( 'create' === $action ) {

            $name   = sanitize_text_field( wp_unslash( $_POST['fplms_name'] ?? '' ) );
            $active = ! empty( $_POST['fplms_active'] ) ? '1' : '0';

            if ( $name ) {
                $term = wp_insert_term( $name, $taxonomy );
                if ( ! is_wp_error( $term ) ) {
                    update_term_meta( $term['term_id'], FairPlay_LMS_Config::META_ACTIVE, $active );

                    // Guardar múltiples ciudades si viene en el formulario (nuevo sistema)
                    if ( FairPlay_LMS_Config::TAX_CITY !== $taxonomy && ! empty( $_POST['fplms_cities'] ) ) {
                        $city_ids = array_map( 'absint', (array) $_POST['fplms_cities'] );
                        $city_ids = array_filter( $city_ids );

                        if ( ! empty( $city_ids ) ) {
                            $this->save_multiple_cities( $term['term_id'], $city_ids );
                        }
                    }
                }
            }
        }

        if ( 'toggle_active' === $action ) {

            $term_id = isset( $_POST['fplms_term_id'] ) ? absint( $_POST['fplms_term_id'] ) : 0;
            if ( $term_id ) {
                $current = get_term_meta( $term_id, FairPlay_LMS_Config::META_ACTIVE, true );
                $new     = ( '1' === $current ) ? '0' : '1';
                update_term_meta( $term_id, FairPlay_LMS_Config::META_ACTIVE, $new );
            }
        }

        if ( 'edit' === $action ) {

            $term_id = isset( $_POST['fplms_term_id'] ) ? absint( $_POST['fplms_term_id'] ) : 0;
            $name    = sanitize_text_field( wp_unslash( $_POST['fplms_name'] ?? '' ) );

            if ( $term_id && $name ) {
                // Actualizar nombre del término
                wp_update_term( $term_id, $taxonomy, [ 'name' => $name ] );

                // Actualizar múltiples ciudades si viene en el formulario (nuevo sistema)
                if ( FairPlay_LMS_Config::TAX_CITY !== $taxonomy && ! empty( $_POST['fplms_cities'] ) ) {
                    $city_ids = array_map( 'absint', (array) $_POST['fplms_cities'] );
                    $city_ids = array_filter( $city_ids );

                    if ( ! empty( $city_ids ) ) {
                        $this->save_multiple_cities( $term_id, $city_ids );
                    }
                }
            }
        }

        $tab = isset( $_POST['fplms_tab'] ) ? sanitize_text_field( wp_unslash( $_POST['fplms_tab'] ) ) : 'city';
        wp_safe_redirect(
            add_query_arg(
                [
                    'page' => 'fplms-structures',
                    'tab'  => $tab,
                ],
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    /**
     * Página de estructuras (admin).
     */
    public function render_page(): void {

        if ( ! current_user_can( FairPlay_LMS_Config::CAP_MANAGE_STRUCTURES ) ) {
            wp_die( 'No tienes permisos para acceder a esta sección.' );
        }

        $tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'city';

        $tabs = [
            'city'    => [
                'label'    => 'Ciudades',
                'taxonomy' => FairPlay_LMS_Config::TAX_CITY,
            ],
            'channel' => [
                'label'    => 'Canales / Franquicias',
                'taxonomy' => FairPlay_LMS_Config::TAX_CHANNEL,
            ],
            'branch'  => [
                'label'    => 'Sucursales',
                'taxonomy' => FairPlay_LMS_Config::TAX_BRANCH,
            ],
            'role'    => [
                'label'    => 'Cargos',
                'taxonomy' => FairPlay_LMS_Config::TAX_ROLE,
            ],
        ];

        if ( ! isset( $tabs[ $tab ] ) ) {
            $tab = 'city';
        }

        $current = $tabs[ $tab ];

        $terms = get_terms(
            [
                'taxonomy'   => $current['taxonomy'],
                'hide_empty' => false,
            ]
        );
        ?>
        <div class="wrap">
            <h1>Estructuras</h1>

            <h2 class="nav-tab-wrapper">
                <?php foreach ( $tabs as $key => $info ) : ?>
                    <?php
                    $class = ( $key === $tab ) ? 'nav-tab nav-tab-active' : 'nav-tab';
                    $url   = add_query_arg(
                        [
                            'page' => 'fplms-structures',
                            'tab'  => $key,
                        ],
                        admin_url( 'admin.php' )
                    );
                    ?>
                    <a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $class ); ?>">
                        <?php echo esc_html( $info['label'] ); ?>
                    </a>
                <?php endforeach; ?>
            </h2>

            <h2><?php echo esc_html( $current['label'] ); ?></h2>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <?php if ( 'city' !== $tab ) : ?>
                            <th>Ciudad</th>
                        <?php endif; ?>
                        <th>Activo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) : ?>
                    <?php foreach ( $terms as $term ) : ?>
                        <?php
                        $active = get_term_meta( $term->term_id, FairPlay_LMS_Config::META_ACTIVE, true );
                        $active = ( '1' === $active );
                        
                        // Obtener ciudades relacionadas si no es tab ciudad
                        $city_ids = [];
                        $city_names = [];
                        if ( 'city' !== $tab ) {
                            $city_ids = $this->get_term_cities( $term->term_id );
                            foreach ( $city_ids as $city_id ) {
                                $city_name = $this->get_term_name_by_id( $city_id );
                                if ( $city_name ) {
                                    $city_names[] = $city_name;
                                }
                            }
                        }
                        ?>
                        <tr>
                            <td><?php echo esc_html( $term->name ); ?></td>
                            <?php if ( 'city' !== $tab ) : ?>
                                <td>
                                    <?php 
                                    if ( ! empty( $city_names ) ) {
                                        echo esc_html( implode( ', ', $city_names ) );
                                    } else {
                                        echo '<em>Sin asignar</em>';
                                    }
                                    ?>
                                </td>
                            <?php endif; ?>
                            <td><?php echo $active ? 'Sí' : 'No'; ?></td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field( 'fplms_structures_save', 'fplms_structures_nonce' ); ?>
                                    <input type="hidden" name="fplms_structures_action" value="toggle_active">
                                    <input type="hidden" name="fplms_taxonomy" value="<?php echo esc_attr( $current['taxonomy'] ); ?>">
                                    <input type="hidden" name="fplms_term_id" value="<?php echo esc_attr( $term->term_id ); ?>">
                                    <input type="hidden" name="fplms_tab" value="<?php echo esc_attr( $tab ); ?>">
                                    <button type="submit" class="button">
                                        <?php echo $active ? 'Desactivar' : 'Activar'; ?>
                                    </button>
                                </form>
                                
                                <!-- Botón Editar -->
                                <button type="button" class="button" 
                                    onclick="fplmsEditStructure(<?php echo esc_attr( $term->term_id ); ?>, '<?php echo esc_attr( $term->name ); ?>', <?php echo esc_attr( wp_json_encode( $city_ids ) ); ?>, '<?php echo esc_attr( $current['taxonomy'] ); ?>')">
                                    Editar
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="<?php echo 'city' === $tab ? '3' : '4'; ?>">No hay registros todavía.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>

            <h3 style="margin-top:2em;">Nuevo registro</h3>
            <form method="post">
                <?php wp_nonce_field( 'fplms_structures_save', 'fplms_structures_nonce' ); ?>
                <input type="hidden" name="fplms_structures_action" value="create">
                <input type="hidden" name="fplms_taxonomy" value="<?php echo esc_attr( $current['taxonomy'] ); ?>">
                <input type="hidden" name="fplms_tab" value="<?php echo esc_attr( $tab ); ?>">

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="fplms_name">Nombre</label></th>
                        <td>
                            <input name="fplms_name" id="fplms_name" type="text" class="regular-text" required>
                        </td>
                    </tr>

                    <?php if ( 'city' !== $tab ) : ?>
                        <tr>
                            <th scope="row"><label for="fplms_cities">Ciudades Relacionadas</label></th>
                            <td>
                                <div class="fplms-multiselect-wrapper">
                                    <select name="fplms_cities[]" id="fplms_cities" class="fplms-multiselect" multiple required>
                                        <?php
                                        $cities = $this->get_active_terms_for_select( FairPlay_LMS_Config::TAX_CITY );
                                        foreach ( $cities as $city_id => $city_name ) :
                                            ?>
                                            <option value="<?php echo esc_attr( $city_id ); ?>">
                                                <?php echo esc_html( $city_name ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="fplms-multiselect-display"></div>
                                </div>
                                <p class="description">Selecciona una o múltiples ciudades. Este <?php echo esc_html( strtolower( $current['label'] ) ); ?> estará disponible en todas las ciudades seleccionadas.</p>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <tr>
                        <th scope="row">Activo</th>
                        <td>
                            <label>
                                <input name="fplms_active" type="checkbox" value="1" checked>
                                Marcar como activo
                            </label>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">Guardar</button>
                </p>
            </form>

            <!-- Modal de Edición -->
            <div id="fplms-edit-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999;">
                <div style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); background:white; padding:30px; border-radius:5px; width:90%; max-width:500px; box-shadow:0 0 20px rgba(0,0,0,0.3);">
                    <h3>Editar Estructura</h3>
                    <form method="post" id="fplms-edit-form">
                        <?php wp_nonce_field( 'fplms_structures_save', 'fplms_structures_nonce' ); ?>
                        <input type="hidden" name="fplms_structures_action" value="edit">
                        <input type="hidden" name="fplms_tab" value="<?php echo esc_attr( $tab ); ?>">
                        <input type="hidden" name="fplms_term_id" id="fplms_edit_term_id" value="">
                        <input type="hidden" name="fplms_taxonomy" id="fplms_edit_taxonomy" value="">

                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="fplms_edit_name">Nombre</label></th>
                                <td>
                                    <input name="fplms_name" id="fplms_edit_name" type="text" class="regular-text" required>
                                </td>
                            </tr>
                            <tr id="fplms_edit_city_row" style="display:none;">
                                <th scope="row"><label for="fplms_edit_cities">Ciudades</label></th>
                                <td>
                                    <div class="fplms-multiselect-wrapper">
                                        <select name="fplms_cities[]" id="fplms_edit_cities" class="fplms-multiselect" multiple required>
                                            <?php 
                                            $cities = $this->get_active_terms_for_select( FairPlay_LMS_Config::TAX_CITY );
                                            foreach ( $cities as $city_id => $city_name ) : 
                                            ?>
                                                <option value="<?php echo esc_attr( $city_id ); ?>">
                                                    <?php echo esc_html( $city_name ); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="fplms-multiselect-display"></div>
                                    </div>
                                    <p class="description">Selecciona una o múltiples ciudades.</p>
                                </td>
                            </tr>
                        </table>

                        <p class="submit" style="margin-top:20px; text-align:right;">
                            <button type="button" class="button" onclick="fplmsCloseEditModal()">Cancelar</button>
                            <button type="submit" class="button button-primary">Guardar Cambios</button>
                        </p>
                    </form>
                </div>
            </div>

            <style>
            .fplms-multiselect-wrapper {
                position: relative;
                width: 100%;
            }

            .fplms-multiselect {
                display: none;
            }

            .fplms-multiselect-display {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                padding: 10px 12px;
                border: 1px solid #8c8f94;
                border-radius: 4px;
                background-color: #fff;
                min-height: 40px;
                cursor: pointer;
                font-size: 14px;
                transition: all 0.2s ease;
                align-items: center;
                position: relative;
            }

            .fplms-multiselect-display:hover {
                border-color: #0073aa;
                box-shadow: 0 0 0 1px #0073aa;
            }

            .fplms-multiselect-display:focus-within {
                border-color: #0073aa;
                box-shadow: 0 0 0 1px #0073aa;
                outline: 2px solid transparent;
            }

            .fplms-multiselect-display::after {
                content: '';
                position: absolute;
                right: 10px;
                top: 50%;
                transform: translateY(-50%);
                width: 6px;
                height: 6px;
                border-right: 2px solid #555;
                border-bottom: 2px solid #555;
                transform: translateY(-65%) rotate(45deg);
                pointer-events: none;
            }

            .fplms-multiselect-tag {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 4px 8px;
                background-color: #0073aa;
                color: #fff;
                border-radius: 3px;
                font-size: 13px;
                font-weight: 500;
                white-space: nowrap;
                animation: slideIn 0.2s ease;
            }

            @keyframes slideIn {
                from {
                    opacity: 0;
                    transform: scale(0.9);
                }
                to {
                    opacity: 1;
                    transform: scale(1);
                }
            }

            .fplms-multiselect-tag.removing {
                animation: slideOut 0.2s ease;
            }

            @keyframes slideOut {
                from {
                    opacity: 1;
                    transform: scale(1);
                }
                to {
                    opacity: 0;
                    transform: scale(0.9);
                }
            }

            .fplms-multiselect-tag-remove {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 16px;
                height: 16px;
                cursor: pointer;
                border-radius: 2px;
                transition: background-color 0.15s ease;
                font-weight: bold;
                line-height: 1;
            }

            .fplms-multiselect-tag-remove:hover {
                background-color: rgba(255, 255, 255, 0.3);
            }

            .fplms-multiselect-dropdown {
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background-color: #fff;
                border: 1px solid #8c8f94;
                border-top: none;
                border-radius: 0 0 4px 4px;
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                max-height: 200px;
                overflow-y: auto;
                z-index: 1000;
                display: none;
                margin-top: -1px;
            }

            .fplms-multiselect-dropdown.open {
                display: block;
            }

            .fplms-multiselect-option {
                padding: 10px 12px;
                cursor: pointer;
                transition: background-color 0.15s ease;
                user-select: none;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .fplms-multiselect-option:hover {
                background-color: #f0f0f1;
            }

            .fplms-multiselect-option.selected {
                background-color: #e7f3ff;
                color: #0073aa;
            }

            .fplms-multiselect-option input[type="checkbox"] {
                margin: 0;
                cursor: pointer;
            }

            .fplms-multiselect-placeholder {
                color: #999;
                font-style: italic;
                padding: 10px 12px;
            }

            .fplms-multiselect-display.empty {
                color: #999;
            }

            .fplms-multiselect-display.empty::before {
                content: 'Selecciona una o múltiples ciudades';
                font-style: italic;
            }
            </style>

            <script>
            class FairPlayMultiSelect {
                constructor(selectElement) {
                    this.select = selectElement;
                    this.wrapper = selectElement.closest('.fplms-multiselect-wrapper');
                    this.display = this.wrapper.querySelector('.fplms-multiselect-display');
                    this.dropdown = null;
                    this.isOpen = false;

                    this.init();
                }

                init() {
                    this.createDropdown();
                    this.bindEvents();
                    this.updateDisplay();
                }

                createDropdown() {
                    this.dropdown = document.createElement('div');
                    this.dropdown.className = 'fplms-multiselect-dropdown';

                    const options = this.select.querySelectorAll('option');
                    
                    if (options.length === 0) {
                        const placeholder = document.createElement('div');
                        placeholder.className = 'fplms-multiselect-placeholder';
                        placeholder.textContent = 'No hay opciones disponibles';
                        this.dropdown.appendChild(placeholder);
                    } else {
                        options.forEach(option => {
                            const optionDiv = document.createElement('div');
                            optionDiv.className = 'fplms-multiselect-option';
                            if (option.selected) {
                                optionDiv.classList.add('selected');
                            }

                            const checkbox = document.createElement('input');
                            checkbox.type = 'checkbox';
                            checkbox.checked = option.selected;
                            checkbox.value = option.value;

                            const label = document.createElement('label');
                            label.style.margin = '0';
                            label.style.cursor = 'pointer';
                            label.style.flex = '1';
                            label.textContent = option.textContent;

                            optionDiv.appendChild(checkbox);
                            optionDiv.appendChild(label);

                            optionDiv.addEventListener('click', (e) => {
                                e.stopPropagation();
                                checkbox.checked = !checkbox.checked;
                                this.updateSelectFromCheckbox(option, checkbox.checked);
                                this.updateDisplay();
                                optionDiv.classList.toggle('selected', checkbox.checked);
                            });

                            this.dropdown.appendChild(optionDiv);
                        });
                    }

                    this.display.parentNode.insertBefore(this.dropdown, this.display.nextSibling);
                }

                bindEvents() {
                    this.display.addEventListener('click', () => this.toggleDropdown());
                    
                    document.addEventListener('click', (e) => {
                        if (!this.wrapper.contains(e.target)) {
                            this.closeDropdown();
                        }
                    });

                    this.select.addEventListener('change', () => this.updateDisplay());
                }

                toggleDropdown() {
                    if (this.isOpen) {
                        this.closeDropdown();
                    } else {
                        this.openDropdown();
                    }
                }

                openDropdown() {
                    this.dropdown.classList.add('open');
                    this.isOpen = true;
                    this.display.focus();
                }

                closeDropdown() {
                    this.dropdown.classList.remove('open');
                    this.isOpen = false;
                }

                updateSelectFromCheckbox(option, checked) {
                    option.selected = checked;
                    this.select.dispatchEvent(new Event('change', { bubbles: true }));
                }

                updateDisplay() {
                    const selected = Array.from(this.select.querySelectorAll('option:checked'));

                    if (selected.length === 0) {
                        this.display.innerHTML = '';
                        this.display.classList.add('empty');
                    } else {
                        this.display.classList.remove('empty');
                        this.display.innerHTML = selected.map(option => {
                            return `
                                <div class="fplms-multiselect-tag" data-value="${option.value}">
                                    <span>${option.textContent}</span>
                                    <span class="fplms-multiselect-tag-remove" onclick="event.stopPropagation(); this.closest('.fplms-multiselect-wrapper').fpMultiSelect.removeTag('${option.value}')">×</span>
                                </div>
                            `;
                        }).join('');
                    }
                }

                removeTag(value) {
                    const option = this.select.querySelector(`option[value="${value}"]`);
                    if (option) {
                        const tag = this.display.querySelector(`[data-value="${value}"]`);
                        tag.classList.add('removing');
                        setTimeout(() => {
                            option.selected = false;
                            this.select.dispatchEvent(new Event('change', { bubbles: true }));
                            this.updateDisplay();
                            this.updateDropdownOptions();
                        }, 200);
                    }
                }

                updateDropdownOptions() {
                    const options = this.dropdown.querySelectorAll('.fplms-multiselect-option');
                    options.forEach(optionDiv => {
                        const checkbox = optionDiv.querySelector('input[type="checkbox"]');
                        optionDiv.classList.toggle('selected', checkbox.checked);
                    });
                }
            }

            // Inicializar todos los multiselects cuando el DOM esté listo
            document.addEventListener('DOMContentLoaded', function() {
                const selects = document.querySelectorAll('.fplms-multiselect');
                selects.forEach(select => {
                    const wrapper = select.closest('.fplms-multiselect-wrapper');
                    wrapper.fpMultiSelect = new FairPlayMultiSelect(select);
                });
            });

            // Re-inicializar si el DOM cambia (útil después de cargar modal)
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.addedNodes.length) {
                        const selects = document.querySelectorAll('.fplms-multiselect:not([data-initialized])');
                        selects.forEach(select => {
                            select.setAttribute('data-initialized', 'true');
                            const wrapper = select.closest('.fplms-multiselect-wrapper');
                            if (wrapper && !wrapper.fpMultiSelect) {
                                wrapper.fpMultiSelect = new FairPlayMultiSelect(select);
                            }
                        });
                    }
                });
            });

            observer.observe(document.body, { childList: true, subtree: true });

            function fplmsEditStructure(termId, termName, cityIds, taxonomy) {
                document.getElementById('fplms_edit_term_id').value = termId;
                document.getElementById('fplms_edit_name').value = termName;
                document.getElementById('fplms_edit_taxonomy').value = taxonomy;
                
                // Mostrar campo de ciudades solo si no es la pestaña de ciudades
                const cityRow = document.getElementById('fplms_edit_city_row');
                const citySelect = document.getElementById('fplms_edit_cities');
                
                if (taxonomy !== 'fplms_city') {
                    cityRow.style.display = 'table-row';
                    
                    // Limpiar selección anterior
                    Array.from(citySelect.options).forEach(opt => opt.selected = false);
                    
                    // Seleccionar ciudades del término (puede ser un array)
                    if (cityIds && Array.isArray(cityIds) && cityIds.length > 0) {
                        cityIds.forEach(cityId => {
                            const option = citySelect.querySelector(`option[value="${cityId}"]`);
                            if (option) option.selected = true;
                        });
                    }
                    
                    // Actualizar display del multiselect
                    const wrapper = citySelect.closest('.fplms-multiselect-wrapper');
                    if (wrapper && wrapper.fpMultiSelect) {
                        wrapper.fpMultiSelect.updateDisplay();
                        wrapper.fpMultiSelect.updateDropdownOptions();
                    }
                } else {
                    cityRow.style.display = 'none';
                }
                
                document.getElementById('fplms-edit-modal').style.display = 'block';
            }

            function fplmsCloseEditModal() {
                document.getElementById('fplms-edit-modal').style.display = 'none';
            }

            // Cerrar modal al hacer clic fuera
            document.addEventListener('click', function(event) {
                const modal = document.getElementById('fplms-edit-modal');
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
            </script>
        </div>
        <?php
    }

    /**
     * Devuelve términos activos para un select.
     */
    public function get_active_terms_for_select( string $taxonomy ): array {

        $terms = get_terms(
            [
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
            ]
        );

        $result = [];

        if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                $active = get_term_meta( $term->term_id, FairPlay_LMS_Config::META_ACTIVE, true );
                if ( '1' === $active || '' === $active ) {
                    $result[ $term->term_id ] = $term->name;
                }
            }
        }

        return $result;
    }

    /**
     * Nombre de término por ID (o string vacío).
     */
    public function get_term_name_by_id( $term_id ): string {
        $term_id = absint( $term_id );
        if ( ! $term_id ) {
            return '';
        }
        $term = get_term( $term_id );
        if ( $term && ! is_wp_error( $term ) ) {
            return $term->name;
        }
        return '';
    }

    /**
     * Guarda la relación jerárquica entre estructuras.
     * Ejemplo: Asignar un Canal a una Ciudad
     */
    public function save_hierarchy_relation( int $term_id, string $relation_type, int $parent_term_id ): bool {

        if ( ! $term_id || ! $parent_term_id ) {
            return false;
        }

        $meta_key = '';

        // Validar que el tipo de relación sea válido
        if ( 'city' === $relation_type ) {
            $meta_key = FairPlay_LMS_Config::META_TERM_PARENT_CITY;
        } elseif ( 'channel' === $relation_type ) {
            $meta_key = FairPlay_LMS_Config::META_TERM_PARENT_CHANNEL;
        } elseif ( 'branch' === $relation_type ) {
            $meta_key = FairPlay_LMS_Config::META_TERM_PARENT_BRANCH;
        }

        if ( ! $meta_key ) {
            return false;
        }

        update_term_meta( $term_id, $meta_key, $parent_term_id );
        return true;
    }

    /**
     * Obtiene términos filtrados por su padre en la jerarquía.
     * Ejemplo: Obtener todos los Canales de una Ciudad
     */
    public function get_terms_by_parent( string $taxonomy, string $parent_type, int $parent_term_id ): array {

        if ( ! $parent_term_id ) {
            return [];
        }

        // Determinar la meta key según el tipo de padre
        $meta_key = '';
        if ( 'city' === $parent_type ) {
            $meta_key = FairPlay_LMS_Config::META_TERM_PARENT_CITY;
        } elseif ( 'channel' === $parent_type ) {
            $meta_key = FairPlay_LMS_Config::META_TERM_PARENT_CHANNEL;
        } elseif ( 'branch' === $parent_type ) {
            $meta_key = FairPlay_LMS_Config::META_TERM_PARENT_BRANCH;
        }

        if ( ! $meta_key ) {
            return [];
        }

        $terms = get_terms(
            [
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
                'meta_key'   => $meta_key,
                'meta_value' => $parent_term_id,
            ]
        );

        if ( is_wp_error( $terms ) ) {
            return [];
        }

        return $terms ? $terms : [];
    }

    /**
     * Obtiene el padre (ciudad) de un término.
     * Devuelve el ID del padre o 0 si no tiene.
     */
    public function get_parent_term( int $term_id, string $parent_type ): int {

        if ( ! $term_id ) {
            return 0;
        }

        $meta_key = '';

        if ( 'city' === $parent_type ) {
            $meta_key = FairPlay_LMS_Config::META_TERM_PARENT_CITY;
        } elseif ( 'channel' === $parent_type ) {
            $meta_key = FairPlay_LMS_Config::META_TERM_PARENT_CHANNEL;
        } elseif ( 'branch' === $parent_type ) {
            $meta_key = FairPlay_LMS_Config::META_TERM_PARENT_BRANCH;
        }

        if ( ! $meta_key ) {
            return 0;
        }

        $parent_id = get_term_meta( $term_id, $meta_key, true );
        return $parent_id ? absint( $parent_id ) : 0;
    }

    /**
     * Obtiene los términos activos para un select filtrados por ciudad.
     * Útil para mostrar dinámicamente las opciones en el frontend.
     */
    public function get_active_terms_by_city( string $taxonomy, int $city_term_id ): array {

        $result = [];

        if ( ! $city_term_id ) {
            return $result;
        }

        // Determinar el tipo de relación según la taxonomía
        $relation_type = '';
        if ( FairPlay_LMS_Config::TAX_CHANNEL === $taxonomy ) {
            $relation_type = 'city';
        } elseif ( FairPlay_LMS_Config::TAX_BRANCH === $taxonomy ) {
            $relation_type = 'city'; // Las sucursales pueden depender también de la ciudad
        } elseif ( FairPlay_LMS_Config::TAX_ROLE === $taxonomy ) {
            $relation_type = 'city'; // Los cargos también por ciudad
        }

        if ( ! $relation_type ) {
            return $result;
        }

        $terms = $this->get_terms_by_parent( $taxonomy, $relation_type, $city_term_id );

        if ( ! empty( $terms ) ) {
            foreach ( $terms as $term ) {
                $active = get_term_meta( $term->term_id, FairPlay_LMS_Config::META_ACTIVE, true );
                if ( '1' === $active || '' === $active ) {
                    $result[ $term->term_id ] = $term->name;
                }
            }
        }

        return $result;
    }

    /**
     * Verifica si un término tiene relación con una ciudad.
     * Útil para validar que el usuario pueda ver un curso.
     */
    public function is_term_related_to_city( int $term_id, int $city_term_id ): bool {

        if ( ! $term_id || ! $city_term_id ) {
            return false;
        }

        // Obtener la ciudad padre del término
        $parent_city = $this->get_parent_term( $term_id, 'city' );

        return $parent_city === $city_term_id;
    }

    /**
     * AJAX: Carga dinámicamente las opciones de una taxonomía filtradas por ciudad.
     * Llamada desde JavaScript cuando el usuario selecciona una ciudad.
     */
    public function ajax_get_terms_by_city(): void {

        if ( ! isset( $_POST['city_id'] ) || ! isset( $_POST['taxonomy'] ) ) {
            wp_send_json_error( 'Missing parameters' );
        }

        $city_id  = absint( $_POST['city_id'] );
        $taxonomy = sanitize_text_field( wp_unslash( $_POST['taxonomy'] ) );

        $allowed_taxonomies = [
            FairPlay_LMS_Config::TAX_CHANNEL,
            FairPlay_LMS_Config::TAX_BRANCH,
            FairPlay_LMS_Config::TAX_ROLE,
        ];

        if ( ! in_array( $taxonomy, $allowed_taxonomies, true ) ) {
            wp_send_json_error( 'Invalid taxonomy' );
        }

        $terms = $this->get_active_terms_by_city( $taxonomy, $city_id );

        $options = [ '' => '-- Seleccionar --' ];
        foreach ( $terms as $term_id => $term_name ) {
            $options[ $term_id ] = $term_name;
        }

        wp_send_json_success( $options );
    }

    /**
     * Obtiene todos los términos de una taxonomía que pueden estar en múltiples ciudades.
     * Útil para identificar canales/sucursales/cargos duplicados en diferentes ciudades.
     * 
     * @param string $taxonomy Taxonomía a consultar.
     * @return array Array con estructura: [term_id => ['name' => 'xxx', 'cities' => [1,2,3]]]
     */
    public function get_terms_with_cities( string $taxonomy ): array {

        $allowed_taxonomies = [
            FairPlay_LMS_Config::TAX_CHANNEL,
            FairPlay_LMS_Config::TAX_BRANCH,
            FairPlay_LMS_Config::TAX_ROLE,
        ];

        if ( ! in_array( $taxonomy, $allowed_taxonomies, true ) ) {
            return [];
        }

        $terms = get_terms( [
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
        ] );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return [];
        }

        $result = [];

        foreach ( $terms as $term ) {
            $city_id = $this->get_parent_term( $term->term_id, 'city' );
            $result[ $term->term_id ] = [
                'name'   => $term->name,
                'city'   => $city_id,
                'active' => get_term_meta( $term->term_id, FairPlay_LMS_Config::META_ACTIVE, true ),
            ];
        }

        return $result;
    }

    /**
     * Guarda múltiples ciudades para un término (cargo/canal/sucursal).
     * Reemplaza a save_hierarchy_relation() para ciudades en sistema multi-ciudad.
     *
     * @param int   $term_id ID del término
     * @param array $city_ids Array de IDs de ciudades
     * @return bool true si se guardó correctamente
     */
    public function save_multiple_cities( int $term_id, array $city_ids ): bool {

        if ( ! $term_id || empty( $city_ids ) ) {
            return false;
        }

        // Sanitizar y validar IDs
        $city_ids = array_map( 'absint', $city_ids );
        $city_ids = array_filter( $city_ids );

        if ( empty( $city_ids ) ) {
            return false;
        }

        // Eliminar duplicados
        $city_ids = array_unique( $city_ids );

        // Guardar como JSON serializado
        $serialized = wp_json_encode( $city_ids );
        update_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_CITIES, $serialized );

        return true;
    }

    /**
     * Obtiene todas las ciudades asignadas a un término.
     * Soporta compatibilidad retroactiva con sistema antiguo (single city).
     *
     * @param int $term_id ID del término
     * @return array Array de IDs de ciudades
     */
    public function get_term_cities( int $term_id ): array {

        if ( ! $term_id ) {
            return [];
        }

        // Intentar obtener ciudades en nuevo formato (JSON)
        $serialized = get_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_CITIES, true );

        if ( $serialized ) {
            $city_ids = json_decode( $serialized, true );
            return is_array( $city_ids ) ? $city_ids : [];
        }

        // Fallback a sistema antiguo (compatibilidad retroactiva)
        $old_city = $this->get_parent_term( $term_id, 'city' );
        return $old_city ? [ $old_city ] : [];
    }

    /**
     * Obtiene términos que están asignados a una o varias ciudades.
     * Filtra por si el término está en alguna de las ciudades solicitadas.
     *
     * @param string $taxonomy Taxonomía a consultar
     * @param array  $city_ids Array de IDs de ciudades
     * @return array Array de términos que están en esas ciudades
     */
    public function get_terms_by_cities( string $taxonomy, array $city_ids ): array {

        if ( empty( $city_ids ) ) {
            return [];
        }

        $city_ids = array_map( 'absint', array_filter( $city_ids ) );
        if ( empty( $city_ids ) ) {
            return [];
        }

        $result    = [];
        $all_terms = get_terms(
            [
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
            ]
        );

        if ( is_wp_error( $all_terms ) || empty( $all_terms ) ) {
            return [];
        }

        foreach ( $all_terms as $term ) {
            $term_cities = $this->get_term_cities( $term->term_id );

            // Si el término está en cualquiera de las ciudades solicitadas
            if ( array_intersect( $term_cities, $city_ids ) ) {
                $result[] = $term;
            }
        }

        return $result;
    }

    /**
     * Obtiene todos los términos de una taxonomía con todas sus ciudades asignadas.
     * Útil para mostrar en tabla cuáles ciudades tiene cada término.
     *
     * @param string $taxonomy Taxonomía a consultar
     * @return array Array con estructura: [term_id => ['name' => 'xxx', 'cities' => [1,2,3], 'active' => '1']]
     */
    public function get_terms_all_cities( string $taxonomy ): array {

        $allowed_taxonomies = [
            FairPlay_LMS_Config::TAX_CHANNEL,
            FairPlay_LMS_Config::TAX_BRANCH,
            FairPlay_LMS_Config::TAX_ROLE,
        ];

        if ( ! in_array( $taxonomy, $allowed_taxonomies, true ) ) {
            return [];
        }

        $terms = get_terms(
            [
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
            ]
        );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return [];
        }

        $result = [];

        foreach ( $terms as $term ) {
            $cities = $this->get_term_cities( $term->term_id );
            $result[ $term->term_id ] = [
                'name'   => $term->name,
                'cities' => $cities,
                'active' => get_term_meta( $term->term_id, FairPlay_LMS_Config::META_ACTIVE, true ),
            ];
        }

        return $result;
    }
}
