<?php

namespace fmb_engine;

if ( ! defined( 'ABSPATH' ) ) {
    exit; 
}

abstract class Settings {

	

    private $slug;

    

    protected $values = array();

    

    private $option_key = '';

    

    private $defaults = array();

    

    private $options = array();

    private static ?bool $table_exists = null;

    public static function storage_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'orderflow_options';
    }

	

	private static function migrate_option_name_prefix(): void {
		global $wpdb;
		$table = self::storage_table();
		$like  = $wpdb->esc_like( 'orderflow_' ) . '%';
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE `{$table}` SET option_name = REPLACE(option_name, 'orderflow_', 'orderflow_') WHERE option_name LIKE %s",
				$like
			)
		);
	}

    private static function table_exists(): bool {
        if (self::$table_exists !== null) {
            return self::$table_exists;
        }

        $cached = wp_cache_get( 'orderflow_options_table_exists', 'fmb-engine' );
        if ($cached !== false) {
            return self::$table_exists = (bool) $cached;
        }

        global $wpdb;
        $table      = self::storage_table();
        $old_table  = $wpdb->prefix . 'orderflow_options';
        $exists     = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        $old_exists = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old_table ) );

        if ( ! $exists && $old_exists ) {
			 
			$wpdb->query( "RENAME TABLE `{$old_table}` TO `{$table}`" );
			$exists = true;
		}

        if (!$exists) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE IF NOT EXISTS $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            option_name VARCHAR(191) NOT NULL,
            option_value LONGTEXT NOT NULL,
            migrated TINYINT(1) NOT NULL DEFAULT 1,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY option_name (option_name)
        ) $charset_collate;";
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);
            $exists = true;
        }

		if ( $exists ) {
			self::migrate_option_name_prefix();
		}

        wp_cache_set( 'orderflow_options_table_exists', $exists, 'fmb-engine', 12 * HOUR_IN_SECONDS );
        return self::$table_exists = $exists;
    }
    

    public function __construct( $slug ) {
        $this->slug = $slug;
        $this->option_key = 'orderflow_' . $slug;
    }

    public function getSlug() {
        return $this->slug;
    }

	

	public function locateOptionsFromArrays( array $fieldTypes, array $defaults ) {
		$this->options              = $fieldTypes;
		$this->defaults             = $defaults;
		self::table_exists();
	}

    

    private function orderflow_get_from_storage( $option_key ) {
        global $wpdb;
        if (!self::$table_exists) {
            return null;
        }

        $table = self::storage_table();

        
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT option_value FROM $table WHERE option_name = %s LIMIT 1",
                $option_key
            ),
            ARRAY_A
        );

        if ( ! $row ) {
            return null;
        }

        $val = maybe_unserialize( $row['option_value'] );
        return is_array( $val ) ? $val : [];
    }
    

    private function orderflow_set_in_storage( $option_key, $value, $migrated = 1 ) {
        global $wpdb;
        if (!self::$table_exists) {
            return null;
        }
        $table = self::storage_table();
        $data  = [
            'option_value' => maybe_serialize( $value ),
            'migrated'     => (int) $migrated,
        ];
        $format = [ '%s', '%d' ];
        
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE option_name = %s",
                $option_key
            )
        );

        if ( $exists ) {
            
            $result = $wpdb->update(
                $table,
                $data,
                [ 'option_name' => $option_key ],
                $format,
                [ '%s' ]
            );
        } else {
            
            $result = $wpdb->insert(
                $table,
                array_merge( [ 'option_name' => $option_key ], $data ),
                array_merge( [ '%s' ], $format )
            );
        }

        return $result !== false;
    }
	

	public function addOption( $key, $field_type, $default ) {
		$this->options[ $key ] = $field_type;
		$this->defaults[ $key ] = $default;
	}

	

    public function getOption( $key, $fallback = null ) {

        $this->maybeLoad();

        
        if ( ! isset( $this->values[ $key ] ) ) {
            $this->values[ $key ] = isset( $this->defaults[ $key ] )
                ? $this->defaults[ $key ] : null;
        }

        
        if ( null === $this->values[ $key ] && ! is_null( $fallback ) ) {
            $this->values[ $key ] = $fallback;
        }

        return $this->values[ $key ];

    }
    public function setOption($key, $value){
        $this->maybeLoad();
        if (isset($value) ) {
            $this->values[ $key ] = $value;
        }
    }

	

	protected function normalizeLoadedValues() {
	}

	

	private function maybeLoad( $force = false ) {

        if ( ! $force && ! empty( $this->values ) ) {
            return; 
        }

        
        $stored = $this->orderflow_get_from_storage( $this->option_key );
        if ( is_array( $stored ) ) {
            $this->values = wp_parse_args( $stored, $this->defaults );
            $this->normalizeLoadedValues();
            return;
        }

        
        $old_data = get_option( $this->option_key, null );
        if ( ! is_array( $old_data ) ) {
            $old_data = get_option( 'orderflow_' . $this->slug, null );
        }
        if ( is_array( $old_data ) ) {
            
            $this->orderflow_set_in_storage( $this->option_key, $old_data, 1 );
            $this->values = wp_parse_args( $old_data, $this->defaults );
            $this->normalizeLoadedValues();
            return;
        }

        
        $this->values = $this->defaults;
        $this->normalizeLoadedValues();

	}

    public function reloadOptions() {
        $this->maybeLoad( true );
    }

	

    public function updateOptions( $values = null ) {

        $this->maybeLoad();

        if ( is_array( $values ) ) {
            $form_data = $values;
        } else {
            if ( isset( $_POST['fmb-engine'][ $this->slug ] ) && is_array( $_POST['fmb-engine'][ $this->slug ] ) ) {
                $form_data = $_POST['fmb-engine'][ $this->slug ];
            } else {
                $form_data = [];
            }
        }

        
        foreach ( $form_data as $key => $value ) {
            if ( isset( $this->options[ $key ] ) ) {
                $this->values[ $key ] = $this->sanitize_form_field( $key, $value );
            }
        }

        
        $written = $this->orderflow_set_in_storage( $this->option_key, $this->values, 1 );

        if ( ! $written ) {
            
            update_option( $this->option_key, $this->values, false );
        }

    }
	
	

	private function sanitize_form_field( $key, $value ) {

	    $type = $this->options[ $key ];

		 
		$filter_name = "{$this->option_key}_settings_sanitize_{$key}_field";
		if ( has_filter( $filter_name ) ) {
			return apply_filters( $filter_name, $value );
		}

		 
		if ( is_callable( array( $this, 'sanitize_' . $type . '_field' ) ) ) {
			return $this->{'sanitize_' . $type . '_field'}( $value );
		}

		 
		return $this->sanitize_text_field( $value );

	}

	

	public function render_text_input( $key, $placeholder = '', $disabled = false, $hidden = false, $empty = false, $type = 'standard' ) {

		$attr_name = "orderflow[$this->slug][$key]";
		$attr_id = 'orderflow_' . $this->slug . '_' . $key;
		$attr_value = $empty == false ? $this->getOption( $key ) : "";

		$classes = array(
			"input-$type"
		);

		if ( $hidden ) {
			$classes[] = 'form-control-hidden';
		}

		$classes = implode( ' ', $classes );
		?>

        <input <?php disabled( $disabled ); ?>
                type="text"
                name="<?php echo esc_attr( $attr_name ); ?>"
                id="<?php echo esc_attr( $attr_id ); ?>"
                value="<?php echo esc_attr( $attr_value ); ?>"
                placeholder="<?php echo esc_attr( $placeholder ); ?>"
                class="<?php echo esc_attr( $classes ); ?>">
		<?php
	}

    

    public function render_password_input( $key, $placeholder = '', $disabled = false, $hidden = false, $empty = false, $type = 'standard' ) {

        $attr_name = "orderflow[$this->slug][$key]";
        $attr_id = 'orderflow_' . $this->slug . '_' . $key;
        $attr_value = $empty == false ? $this->getOption( $key ) : "";

        $classes = array(
            "input-$type",
            "passwordInput"
        );

        if ( $hidden ) {
            $classes[] = 'form-control-hidden';
        }

        $classes = implode( ' ', $classes );
        ?>
        <div class="password-block">
            <input <?php disabled( $disabled ); ?>
                    type="text"
                    name="<?php echo esc_attr( $attr_name ); ?>"
                    id="<?php echo esc_attr( $attr_id ); ?>"
                    value="<?php echo esc_attr( $attr_value ); ?>"
                    placeholder="<?php echo esc_attr( $placeholder ); ?>"
                    class="<?php echo esc_attr( $classes ); ?>"
                    style="display:none;"
            >

            <input type="text" class="maskedInput input-<?php echo esc_attr( $type ); ?>" data-value="<?php echo esc_attr( $attr_value ); ?>">
        </div>
        <?php
    }
	

	public function render_pixel_id( $key, $placeholder = '', $index = 0 ) {
        
        $attr_name = "orderflow[$this->slug][$key][]";
        $attr_id = 'orderflow_' . $this->slug . '_' . $key . '_' . $index;
		
		$values = (array) $this->getOption( $key );
		$attr_value = isset( $values[ $index ] ) ? $values[ $index ] : null;
  
		?>
        
        <input type="text" name="<?php echo esc_attr( $attr_name ); ?>"
               id="<?php echo esc_attr( $attr_id ); ?>"
               value="<?php echo esc_attr( $attr_value ); ?>"
               placeholder="<?php echo esc_attr( $placeholder ); ?>"
               class="input-standard">
		
		<?php
		
	}

    

    public function render_text_area_array_item( $key, $placeholder = '', $index = 0, $enabled = true ) {

        $attr_name = "orderflow[$this->slug][$key][]";
        $attr_id = 'orderflow_' . $this->slug . '_' . $key . '_' . $index;

        $values = (array) $this->getOption( $key );
        $attr_value = isset( $values[ $index ] ) ? $values[ $index ] : null;

        ?>

        <textarea type="text" name="<?php echo esc_attr( $attr_name ); ?>"
                  id="<?php echo esc_attr( $attr_id ); ?>"
                  placeholder="<?php echo esc_attr( $placeholder ); ?>"
                  class="textarea-standard" <?= !$enabled ? 'disabled' : ''; ?>><?php echo esc_attr( $attr_value ); ?></textarea>

        <?php
    }

    

    public function render_text_input_array_item( $key, $placeholder = '', $index = 0,$hidden = false ) {

        $attr_name = "orderflow[$this->slug][$key][]";
        $attr_id = 'orderflow_' . $this->slug . '_' . $key . '_' . $index;

        $values = (array) $this->getOption( $key );
        $attr_value = isset( $values[ $index ] ) ? $values[ $index ] : null;

        ?>

        <input type=<?=$hidden? "hidden": "text"?> name="<?php echo esc_attr( $attr_name ); ?>"
               id="<?php echo esc_attr( $attr_id ); ?>"
               value="<?php echo esc_attr( $attr_value ); ?>"
               placeholder="<?php echo esc_attr( $placeholder ); ?>"
               class="input-standard">
        <?php
    }
	
	

	public function render_text_area_input( $key, $placeholder = '', $disabled = false, $hidden = false ) {

		$attr_name = "orderflow[$this->slug][$key]";
		$attr_id = 'orderflow_' . $this->slug . '_' . $key;
		$attr_value = $this->getOption( $key );

		$classes = array( 'form-control' );

		if( $hidden ) {
			$classes[] = 'form-control-hidden';
		}

		$classes = implode( ' ', $classes );

		?>

        <textarea <?php disabled( $disabled ); ?> name="<?php echo esc_attr( $attr_name ); ?>"
              id="<?php echo esc_attr( $attr_id ); ?>" rows="5"
              placeholder="<?php echo esc_attr( $placeholder ); ?>"
              class="<?php echo esc_attr( $classes ); ?>"><?php esc_html_e( $attr_value ); ?></textarea>

		<?php

	}
	
	

    public function render_switcher_input( $key, $collapse = false, $disabled = false, $default = false, $type = 'secondary') {
        $attr_name = "orderflow[$this->slug][$key]";
        $attr_id = 'orderflow_' . $this->slug . '_' . $key;
        $attr_value = $this->getOption( $key );
        $input_class = $label_class = '';

		$classes = array( "$type-switch" );

		if ( $collapse ) {
			$classes[] = 'collapse-control';
		}

        if ( $disabled ) {
            $classes[] = 'disabled';
            $attr_name = "";
            $attr_value = $default;
        }

		if ( $type === 'primary' ) {
			$input_class = 'primary-switch-input';
			$label_class = 'primary-switch-btn';
		} elseif ( $type === 'secondary' ) {
			$input_class = 'custom-switch-input';
			$label_class = 'custom-switch-btn';
		}

		$classes = implode( ' ', $classes );

		?>

        <div class="<?php echo esc_attr( $classes ); ?>">

			<?php if ( !$disabled ) : ?>
                <input type="hidden" name="<?php echo esc_attr( $attr_name ); ?>" value="0">
			<?php endif; ?>

			<?php if ( $collapse ) : ?>
                <input type="checkbox" name="<?php echo esc_attr( $attr_name ); ?>"
                       value="1" <?php disabled( $disabled, true ); ?> <?php checked( $attr_value, true ); ?>
                       id="<?php echo esc_attr( $attr_id ); ?>"
                       class="<?php echo esc_attr( $input_class ); ?>"
                       data-target="orderflow_<?php echo esc_attr( $this->slug ); ?>_<?php echo esc_attr( $key ); ?>_panel">
			<?php else : ?>
                <input type="checkbox" name="<?php echo esc_attr( $attr_name ); ?>"
                       value="1" <?php disabled( $disabled, true ); ?> <?php checked( $attr_value, true ); ?>
                       id="<?php echo esc_attr( $attr_id ); ?>"
                       class="<?php echo esc_attr( $input_class ); ?>">
			<?php endif; ?>

            <label class="<?php echo esc_attr( $label_class ); ?>" for="<?php echo esc_attr( $attr_id ); ?>">
				<?php if ( $type === 'primary' ) : ?>
                    <span class="<?php echo esc_attr( $label_class ); ?>-slider"></span>
				<?php endif; ?>
            </label>
        </div>

		<?php
	}

	public function render_switcher_input_array( $key, $index = 0 ) {

		$attr_name = "orderflow[$this->slug][$key][]";
		$attr_id = 'orderflow_' . $this->slug . '_' . $key . "_" . $index;
		$attr_values = (array) $this->getOption( $key );
		$value = "index_" . $index;
		$valueIndex = array_search( $value, $attr_values );

		$classes = array( 'secondary-switch' );
		$classes = implode( ' ', $classes );

		?>

        <div class="<?php echo esc_attr( $classes ); ?>">
            <input type="checkbox"
                   name="<?php echo esc_attr( $attr_name ); ?>"
                   value="<?php echo esc_attr( $value ); ?>"
				<?php echo $valueIndex !== false ? "checked" : "" ?>
                   id="<?php echo esc_attr( $attr_id ); ?>"
                   class="custom-switch-input <?php echo esc_attr( $key ); ?>">

            <label class="custom-switch-btn" for="<?php echo esc_attr( $attr_id ); ?>"></label>
        </div>

		<?php

	}
	
	

	public function render_checkbox_input( $key, $label, $disabled = false ) {

		$attr_name = "orderflow[$this->slug][$key]";
		$attr_value = $this->getOption( $key );
		$id = $key . "_" . random_int( 1, 1000000 );

		?>

        <div class="small-checkbox">
            <input type="hidden" name="<?php echo esc_attr( $attr_name ); ?>" value="0">
            <input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $attr_name ); ?>"
                   value="1"
                   class="small-control-input" <?php disabled( $disabled, true ); ?> <?php checked( $attr_value, true ); ?>>
            <label class="small-control small-checkbox-label" for="<?php echo esc_attr( $id ); ?>">
                <span class="small-control-indicator"><i class="icon-check"></i></span>
                <span class="small-control-description"><?php echo wp_kses_post( $label ); ?></span>
            </label>
        </div>

		<?php
	}

	

	public function render_checkbox_input_revert_array( $key, $label, $value, $disabled = false ) {

		$attr_name = "orderflow[$this->slug][$key][]";
		$attr_values = (array) $this->getOption( $key );

		$isChecked = !in_array( $value, $attr_values );
		$id = $key . "_" . random_int( 1, 1000000 );
		?>
        <div class="small-checkbox">
            <input type="hidden" name="<?php echo esc_attr( $attr_name ); ?>" value="<?= $value ?>">
            <input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $attr_name ); ?>"
                   value="<?= "revert_" . $value ?>"
                   class="small-control-input" <?php disabled( $disabled, true ); ?>
				<?php echo $isChecked ? "checked" : "" ?>>
            <label class="small-control small-checkbox-label" for="<?php echo esc_attr( $id ); ?>">
                <span class="small-control-indicator"><i class="icon-check"></i></span>
                <span class="small-control-description"><?php echo wp_kses_post( $label ); ?></span>
            </label>
        </div>

		<?php
	}


    

    public function render_checkbox_blacklist_input_array($key, $label, $value, $disabled = false ) {

		$attr_name = "orderflow[$this->slug][$key][]";
		$attr_values = (array) $this->getOption( $key );
        $id = $key . "_" . random_int( 1, 1000000 );
		$isChecked = in_array($value, $attr_values, true);
		?>
        <div class="small-checkbox">
            <input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $attr_name ); ?>"
                   value="<?= $value ?>"
                   class="small-control-input" <?php disabled( $disabled, true ); ?> <?php echo $isChecked ? "checked" : "" ?>>
            <label class="small-control small-checkbox-label" for="<?php echo esc_attr( $id ); ?>">
                <span class="small-control-indicator"><i class="icon-check"></i></span>
                <span class="small-control-description"><?php echo wp_kses_post( $label ); ?></span>
            </label>
        </div>

		<?php
		
	}
	
	

	public function render_radio_input( $key, $value, $label, $disabled = false, $with_pro_badge = false ) {

		$id = $key . "_" . rand( 1, 1000000 );
		$attr_name = "orderflow[$this->slug][$key]";
		?>

        <div class="radio-standard">
            <input type="radio"
                   name="<?php echo esc_attr( $attr_name ); ?>"
				<?php disabled( $disabled, true ); ?>
                   class="custom-control-input"
                   id="<?php echo esc_attr( $id ); ?>"
				<?php checked( $this->getOption( $key ), $value ); ?>
                   value="<?php echo esc_attr( $value ); ?>">
            <label class="standard-control radio-checkbox-label" for="<?php echo esc_attr( $id ); ?>">
                <span class="standard-control-indicator"></span>
                <span class="standard-control-description"><?php echo wp_kses_post( $label ); ?></span>
				<?php if ( $with_pro_badge ) {
					renderCogBadge();
				} ?>
            </label>
        </div>

		<?php
		
	}
	
	

	public function render_number_input( $key, $placeholder = '', $disabled = false, $max = null, $min = 0, $step = 'any', $suffix = '' ) {

		$attr_name = "orderflow[$this->slug][$key]";
		$attr_id = 'orderflow_' . $this->slug . '_' . $key;
		$attr_value = $this->getOption( $key );

		?>
        <div class="input-number-wrapper">
            <input <?php disabled( $disabled ); ?> type="number" name="<?php echo esc_attr( $attr_name ); ?>"
                                                   id="<?php echo esc_attr( $attr_id ); ?>"
                                                   value="<?php echo esc_attr( $attr_value ); ?>"
                                                   placeholder="<?php echo esc_attr( $placeholder ); ?>"
                                                   min="<?= $min ?>"
				<?php if ( $max != null ) : ?> max="<?= $max ?>" <?php endif; ?>
                                                   step="<?= $step ?>"
            >
        </div>

		<?php
		
	}


    

    public function render_number_input_percent( $key, $placeholder = '', $disabled = false, $max = null, $min = 0, $step = 'any' ) {

        $attr_name = "orderflow[$this->slug][$key]";
        $attr_id = 'orderflow_' . $this->slug . '_' . $key;
        $attr_value = $this->getOption( $key );

        ?>
        <div class="input-number-wrapper input-number-wrapper-percent">
            <input <?php disabled( $disabled ); ?> type="number" name="<?php echo esc_attr( $attr_name ); ?>"
                                                   id="<?php echo esc_attr( $attr_id ); ?>"
                                                   value="<?php echo esc_attr( $attr_value ); ?>"
                                                   placeholder="<?php echo esc_attr( $placeholder ); ?>"
                                                   min="<?= $min ?>"
                <?php if ( $max != null ) : ?> max="<?= $max ?>" <?php endif; ?>
                                                   step="<?= $step ?>"
            >
        </div>

        <?php

    }
	

	public function render_select_input( $key, $options, $disabled = false, $visibility_target = null, $visibility_value = null, $searchable = false ) {

		$attr_name = "orderflow[$this->slug][$key]";
		$attr_id = 'orderflow_' . $this->slug . '_' . $key;

		$classes = array( 'select-standard' );

		if ( $visibility_target ) {
			$classes[] = 'controls-visibility';
		}
        if($searchable){
            $classes[] = 'fmb-engine-select2';
        }

		$classes = implode( ' ', $classes );

		?>
            <div class="select-standard-wrap">
                <select class="<?php echo esc_attr( $classes ); ?>" id="<?php echo esc_attr( $attr_id ); ?>"
                        name="<?php echo esc_attr( $attr_name ); ?>" <?php disabled( $disabled ); ?>
                        data-target="<?php echo esc_attr( $visibility_target ); ?>"
                        data-value="<?php echo esc_attr( $visibility_value ); ?>" autocomplete="off">

                    <option value="" disabled selected>Please, select...</option>

                    <?php foreach ( $options as $option_key => $option_value ) : ?>
                        <option value="<?php echo esc_attr( $option_key ); ?>" <?php selected( $option_key, esc_attr( $this->getOption( $key ) ) ); ?> <?php disabled( $option_key, 'disabled' ); ?>><?php echo esc_attr( $option_value ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
		<?php
	}

	

    public function render_multi_select_input( $key, $values, $disabled = false, $placeholder = "" ) {

        $attr_name = "orderflow[$this->slug][$key][]";
        $attr_id = 'orderflow_' . $this->slug . '_' . $key;

        $selected = $this->getOption( $key ) ? $this->getOption( $key ) : array();
        ?>

        <input type="hidden" name="<?php echo esc_attr( $attr_name ); ?>" value="">
        <select class="fmb-engine-select2"
                data-placeholder="<?= $placeholder ?>"
                name="<?php echo esc_attr( $attr_name ); ?>"
                id="<?php echo esc_attr( $attr_id ); ?>" <?php disabled( $disabled ); ?> style="width: 100%;"
                multiple>
            <?php foreach ( $values as $option_key => $option_value ) : ?>
                <option value="<?php echo esc_attr( $option_key ); ?>"
                    <?php selected( in_array( $option_key, $selected ) ); ?>
                    <?php disabled( $option_key, 'disabled' ); ?>
                >
                    <?php echo esc_attr( $option_value ); ?>
                </option>
            <?php endforeach; ?>

        </select>

        <?php
    }
	
	

    public function render_tags_select_input( $key, $disabled = false ,$default = []) {

        $attr_name = "orderflow[$this->slug][$key][]";
        $attr_id = 'orderflow_' . $this->slug . '_' . $key;

        $tags = $this->getOption( $key );
        $tags = is_array( $tags ) ? array_filter( $tags ) : array();
        $tags = array_diff($tags,$default);
        ?>

        <input type="hidden" name="<?php echo esc_attr( $attr_name ); ?>" value="">
        <select class="fmb-engine-tags-select2" name="<?php echo esc_attr( $attr_name ); ?>"
                id="<?php echo esc_attr( $attr_id ); ?>" <?php disabled( $disabled ); ?> style="width: 100%;"
                multiple>

            <?php foreach ( $default as $tag ) : ?>
                <option  value="<?php echo esc_attr( $tag ); ?>" selected locked="locked">
                    <?php echo esc_attr( $tag ); ?>
                </option>
            <?php endforeach; ?>

            <?php foreach ( $tags as $tag ) : ?>
                <option value="<?php echo esc_attr( $tag ); ?>" selected>
                    <?php echo esc_attr( $tag ); ?>
                </option>
            <?php endforeach; ?>

        </select>

        <?php
    }


	

	public function sanitize_text_field( $value ) {

		$value = is_null( $value ) ? '' : $value;

		return wp_kses_post( trim( stripslashes( $value ) ) );

	}
	
	

	public function sanitize_textarea_field( $value ){

		$value = is_null( $value ) ? '' : $value;

		return trim( stripslashes( $value ) );

	}
	
	

	public function sanitize_number_field( $value ) {
		return (int) $value;
	}
	
	

	public function sanitize_checkbox_field( $value ) {

		if ( is_bool( $value ) || is_numeric( $value ) ) {
			return (bool) $value;
		} else {
			return false;
		}

	}
	
	

	public function sanitize_radio_field( $value ) {
		return ! is_null( $value ) ? trim( stripslashes( $value ) ) : null;
	}
	
	

	public function sanitize_select_field( $value ) {
		
		$value = is_null( $value ) ? '' : $value;
		
		return \fmb_engine\Facebook\deepSanitizeTextField( stripslashes( $value ) );
		
	}
	
	

	public function sanitize_multi_select_field( $value ) {
		return is_array( $value ) ? array_map( '\fmb_engine\Facebook\deepSanitizeTextField', $value ) : array();
	}
	
	

	public function sanitize_tag_select_field( $value ) {
		return is_array( $value ) ? array_map( '\fmb_engine\Facebook\deepSanitizeTextField', $value ) : array();
    }
	
	

	public function sanitize_array_field( $values ) {
		
		$values = is_array( $values ) ? $values : array();
		$sanitized = array();
		
		foreach ( $values as $key => $value ) {
			
			$new_value = $this->sanitize_text_field( $value );
			
			if ( ! empty( $new_value ) && ! in_array( $new_value, $sanitized ) ) {
				$sanitized[ $key ] = $new_value;
			}
			
		}
		
		return $sanitized;
		
	}



    public function sanitize_array_textarea_field( $values ) {

        $values = is_array( $values ) ? $values : array();
        $sanitized = array();

        foreach ( $values as $key => $value ) {

            $new_value = $this->sanitize_textarea_field( $value );

            if ( ! empty( $new_value ) && ! in_array( $new_value, $sanitized ) ) {
                $sanitized[ $key ] = $new_value;
            }

        }

        return $sanitized;
    }
	

	public function sanitize_array_v_field( $values ) {

		$values = is_array( $values ) ? $values : array();
		$sanitized = array();

		foreach ( $values as $key => $value ) {

			$new_value = $this->sanitize_text_field( $value );

			if ( ! empty( $new_value ) ) {
				$sanitized[ $key ] = $new_value;
			}

		}

		return $sanitized;

	}
    public function render_checkbox_input_array( $key, $label, $index = 0, $disabled = false ) {

        $attr_name  = "orderflow[$this->slug][$key][]";
        $attr_values = (array)$this->getOption( $key );
        $value = "index_".$index;
        $valueIndex = array_search($value,$attr_values);

        ?>

        <label class="custom-control custom-checkbox">
            <input type="checkbox" name="<?php echo esc_attr( $attr_name ); ?>" value="<?=$value?>"
                   class="custom-control-input" <?php disabled( $disabled, true ); ?>
                <?=$valueIndex !== false ? "checked" : "" ?>>
            <span class="custom-control-indicator"></span>
            <span class="custom-control-description"><?php echo wp_kses_post( $label ); ?></span>
        </label>

        <?php

    }
    function renderDummyTextInput( $placeholder = '' ) {
        ?>

        <input type="text" disabled="disabled" placeholder="<?php esc_html_e( $placeholder ); ?>" class="form-control">

        <?php
    }
    function renderDummyNumberInput($default = 0) {
        ?>

        <input type="number" disabled="disabled" min="0" max="100" class="form-control" value="<?=$default?>">

        <?php
    }

    function renderDummySwitcher($isEnable = false) {
        $attr = $isEnable ? " checked='checked'" : "";
        ?>

        <div class="custom-switch disabled">
            <input type="checkbox" value="1" <?=$attr?> disabled="disabled" class="custom-switch-input">
            <label class="custom-switch-btn"></label>
        </div>

        <?php
    }

    function renderDummyCheckbox( $label, $with_pro_badge = false ) {
        ?>

        <label class="custom-control custom-checkbox <?php echo $with_pro_badge ? 'custom-checkbox-badge' : ''; ?>">
            <input type="checkbox" value="1"
                   class="custom-control-input" disabled="disabled">
            <span class="custom-control-indicator"></span>
            <span class="custom-control-description">
            <?php echo wp_kses_post( $label ); ?>
        </span>
        </label>

        <?php
    }

    function renderDummyRadioInput( $label, $checked = false ) {
        ?>

        <label class="custom-control custom-radio">
            <input type="radio" disabled="disabled"
                   class="custom-control-input" <?php checked( $checked ); ?>>
            <span class="custom-control-indicator"></span>
            <span class="custom-control-description"><?php echo wp_kses_post( $label ); ?></span>
        </label>

        <?php
    }

    function renderDummyTagsFields( $tags = array() ) {
        ?>

        <select class="form-control fmb-engine-tags-select2" disabled="disabled" style="width: 100%;" multiple>

            <?php if(!empty($tags)){
                foreach ( $tags as $tag ) : ?>
                    <option value="<?php echo esc_attr( $tag ); ?>" selected>
                        <?php echo esc_attr( $tag ); ?>
                    </option>
                <?php endforeach;
                }
            ?>

        </select>

        <?php
    }

    function renderDummySelectInput( $value, $full_width = false ) {

        $attr_width = $full_width ? 'width: 100%;' : '';

        ?>

        <select class="form-control form-control-sm" disabled="disabled" autocomplete="off" style="<?php echo esc_attr( $attr_width ); ?>">
            <option value="" disabled selected><?php esc_html_e( $value ); ?></option>
        </select>

        <?php
    }


    public function convertTimeToSeconds($timeValue = 24, $type = 'hours')
    {
        switch ($type){
            case 'hours':
                $time = $timeValue * 60 * 60;
                break;
            case 'minute':
                $time = $timeValue * 60;
                break;
            case 'seconds':
                $time = $timeValue;
                break;
        }
        return $time;
    }


    public function renderValueOptionsBlock($context, $useEnable = true, $usePopover = true, $useBorder = true, $title = '', $disabled = false) {
        if ( empty( $context ) ) {
            return;
        }

        if ( empty( $title ) ) {
            $title = 'Value parameter settings:';
        }

        $prefixes = [
            'purchase',
            'initiate_checkout',
        ];
        if ( count( array_filter( $prefixes, function ( $prefix ) use ( $context ) {
                return strpos( $context, $prefix ) !== false;
            } ) ) > 0 ) {
            $priceText = 'Order\'s total';
            $percentText = 'Percent of the order\'s total';
        } else {
            $priceText = 'Product price';
            $percentText = 'Percent of the product price';
        }
        ?>
        <div class="gap-24">
            <div class="d-flex align-items-center">
                <?php if ( !is_null( $this->getOption( $context . '_value_enabled' ) ) || $useEnable ) : ?>
                    <div class="mr-16">
                        <?php !$disabled ? $this->render_switcher_input( $context . '_value_enabled', true ) : renderDummySwitcher(); ?>
                    </div>
                <?php endif; ?>
                <h4 class="secondary_heading"><?php echo esc_html( $title ); ?></h4>
                <?php
                if ( $usePopover ) {
                    renderPopoverButton( $context . '_event_value' );
                } ?>
            </div>

            <div <?php if ( !is_null( $this->getOption( $context . '_value_enabled' ) ) && !$disabled) {
                renderCollapseTargetAttributes( $context . '_value_enabled', \fmb_engine\Facebook\FacebookRuntime() );
            } ?>>
                <div class="radio-inputs-wrap-big woo-settings-block">
                    <?php !$disabled ? $this->render_radio_input( $context . '_value_option', 'price', $priceText ) : renderDummyRadioInput($priceText); ?>

                    <?php if ( strpos( $context, 'edd_' ) !== 0 ) { ?>
                        <?php if ( !isPixelCogActive() ) { ?>
                            <?php !$disabled ? $this->render_radio_input( $context . '_value_option', 'cog', 'Price minus Cost of Goods.', true, true ): renderDummyRadioInput('Price minus Cost of Goods'); ?>
                        <?php } else { ?>
                            <?php !$disabled ? $this->render_radio_input( $context . '_value_option', 'cog', 'Price minus Cost of Goods', false ): renderDummyRadioInput('Price minus Cost of Goods'); ?>
                        <?php } ?>
                    <?php } ?>

                    <div class="grid-table">
                        <?php renderDummyRadioInput( $percentText ); ?>
                        <?php renderDummyNumberInput( 0 ); ?>

                        <?php !$disabled ? $this->render_radio_input( $context . '_value_option', 'global', 'Use global value' ) : renderDummyRadioInput('Use global value') ; ?>
                        <?php !$disabled ? $this->render_number_input( $context . '_value_global' ) : renderDummyNumberInput(0); ?>
                    </div>
                </div>

                <?php if ( $useBorder ) : ?>
                    <div class="line mt-24"></div>
                <?php endif; ?>
            </div>
        </div>

        <?php
    }
}