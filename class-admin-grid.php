<?php
if (!defined('ABSPATH')) exit;

class VCNP_Admin_Grid_Size {
    public function __construct() {
        add_action('add_meta_boxes', [$this,'add_box']);
        add_action('save_post_product', [$this,'save'], 10, 2);
    }

    public function add_box() {
        add_meta_box('vcnp_grid_size','VC Number Picker – Grid size', [$this,'render'], 'product','side','default');
    }

    public function render($post) {
        wp_nonce_field('vcnp_grid_size','vcnp_grid_size_nonce');
        $val = (int) get_post_meta($post->ID, VCNP_Helpers::META_MAX, true);
        if ($val < 1) { $val = 100; }
        $force = (get_post_meta($post->ID, VCNP_Helpers::META_FORCE, true) === 'yes');
        $skill_on = (get_post_meta($post->ID, VCNP_Helpers::META_SKILL_ON, true) === 'yes');
        $q  = get_post_meta($post->ID, VCNP_Helpers::META_SKILL_Q, true);
        $a  = get_post_meta($post->ID, VCNP_Helpers::META_SKILL_A, true);
        ?>
        <style>.vcnp-grid-size label{display:block;margin-bottom:6px}.vcnp-grid-size input[type=number]{width:100%}</style>
        <div class="vcnp-grid-size">
            <p><strong><?php esc_html_e('Choose board size','vcnp'); ?></strong></p>
            <label><input type="radio" name="vcnp_max_choice" value="50"  <?php checked($val===50);?>> 50</label>
            <label><input type="radio" name="vcnp_max_choice" value="59"  <?php checked($val===59);?>> 59</label>
            <label><input type="radio" name="vcnp_max_choice" value="100" <?php checked($val===100);?>> 100</label>
            <label><input type="radio" name="vcnp_max_choice" value="custom" <?php checked(!in_array($val,[50,59,100],true));?>> <?php esc_html_e('Custom','vcnp'); ?>:</label>
            <input type="number" min="1" max="1000" step="1" name="vcnp_max_custom" value="<?php echo esc_attr($val);?>">
            <p class="description"><code>_vc_np_max</code> <?php esc_html_e('caps the board and AJAX.','vcnp'); ?></p>

            <hr>
            <p><label><input type="checkbox" name="vcnp_force" value="yes" <?php checked($force);?>> <?php esc_html_e('Force enable on this product (compatibility mode)','vcnp'); ?></label></p>

            <hr>
            <p><label><input type="checkbox" name="vcnp_skill_on" value="yes" <?php checked($skill_on);?>> <?php esc_html_e('Enable skill question','vcnp'); ?></label></p>
            <p><label><?php esc_html_e('Question','vcnp'); ?></label><br><input type="text" name="vcnp_skill_q" value="<?php echo esc_attr($q);?>" style="width:100%"></p>
            <p><label><?php esc_html_e('Answer (exact match)','vcnp'); ?></label><br><input type="text" name="vcnp_skill_a" value="<?php echo esc_attr($a);?>" style="width:100%"></p>
        </div>
        <?php
    }

    public function save($post_id, $post) {
        if (!isset($_POST['vcnp_grid_size_nonce']) || !wp_verify_nonce($_POST['vcnp_grid_size_nonce'],'vcnp_grid_size')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if ($post->post_type !== 'product') return;
        if (!current_user_can('edit_post', $post_id)) return;

        $choice = isset($_POST['vcnp_max_choice']) ? sanitize_text_field($_POST['vcnp_max_choice']) : '';
        $custom = isset($_POST['vcnp_max_custom']) ? (int) $_POST['vcnp_max_custom'] : 0;
        $val = 100;
        if     ($choice === '50')  $val = 50;
        elseif ($choice === '59')  $val = 59;
        elseif ($choice === '100') $val = 100;
        else { $custom = max(1, min(1000, $custom)); $val = $custom; }
        update_post_meta($post_id, VCNP_Helpers::META_MAX, $val);

        $force = (isset($_POST['vcnp_force']) && $_POST['vcnp_force']==='yes') ? 'yes' : '';
        update_post_meta($post_id, VCNP_Helpers::META_FORCE, $force);

        $skill_on = (isset($_POST['vcnp_skill_on']) && $_POST['vcnp_skill_on']==='yes') ? 'yes' : '';
        update_post_meta($post_id, VCNP_Helpers::META_SKILL_ON, $skill_on);
        update_post_meta($post_id, VCNP_Helpers::META_SKILL_Q, sanitize_text_field($_POST['vcnp_skill_q'] ?? ''));
        update_post_meta($post_id, VCNP_Helpers::META_SKILL_A, sanitize_text_field($_POST['vcnp_skill_a'] ?? ''));
    }
}
