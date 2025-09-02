<?php
if (!defined('ABSPATH')) exit;

class VCNP_Frontend {

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this,'assets']);
        add_shortcode('vc_number_picker', [$this,'shortcode_render']);
        add_filter('woocommerce_get_item_data', [$this, 'display_item_data'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'store_order_item_meta'], 10, 4);
        add_action('woocommerce_cart_item_removed', [$this,'cart_item_removed'], 10, 2);
    }

    public function assets() {
        if (!VCNP_Helpers::is_lottery_product_page()) return;

        // CSS
        wp_enqueue_style('vcnp-style', VCNP_URL . 'assets/css/number-picker.css', [], VCNP_VERSION);

        // JS (footer)
        wp_register_script('vcnp', VCNP_URL . 'assets/js/number-picker.js', ['jquery'], VCNP_VERSION, true);
        wp_enqueue_script('vcnp');
        wp_localize_script('vcnp', 'VCNP', [
            'ajax'  => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vc_np_nonce'),
            'i18n'  => [
                'pick' => __('Please select at least one number.','vcnp'),
                'skill'=> __('Please answer the skill question.','vcnp'),
                'taken'=> __('Sorry, that number just got taken. Please pick another.','vcnp'),
            ]
        ]);
    }

    public function shortcode_render($atts) {
        if (!VCNP_Helpers::is_lottery_product_page()) return '';
        $pid = VCNP_Helpers::current_product_id();
        if (!$pid) return '';

        $max = VCNP_Helpers::grid_max($pid);
        $sold = VCNP_Helpers::get_sold_numbers($pid);
        $skill_on = (get_post_meta($pid, VCNP_Helpers::META_SKILL_ON, true) === 'yes');
        $q  = get_post_meta($pid, VCNP_Helpers::META_SKILL_Q, true);

        ob_start(); ?>
        <div class="vc-np" data-product="<?php echo esc_attr($pid); ?>">
            <?php if ($skill_on): ?>
            <div class="vc-skill-wrap" style="margin:10px 0;">
                <label for="vc_np_skill_answer"><?php echo esc_html($q ?: __('Answer the skill question','vcnp')); ?></label>
                <input type="text" id="vc_np_skill_answer" class="vc-skill-input" placeholder="<?php echo esc_attr($q ? __('Enter answer','vcnp') : __('Enter answer','vcnp')); ?>">
            </div>
            <?php endif; ?>
            <div class="vc-board">
                <?php for ($i=1; $i <= $max; $i++):
                    $is_sold = in_array($i, $sold, true); ?>
                    <button type="button" class="vc-cell <?php echo $is_sold ? 'is-sold' : ''; ?>" data-n="<?php echo $i; ?>" <?php echo $is_sold ? 'disabled' : ''; ?>>
                        <?php echo $i; ?>
                    </button>
                <?php endfor; ?>
            </div>
            <input type="hidden" class="vc-np-numbers" value="">
            <p class="p-mini" style="margin-top:8px;"><?php esc_html_e('No purchase necessary. Free postal entry available – see Terms & Conditions.','vcnp'); ?></p>
        </div>
        <?php
        return ob_get_clean();
    }

    public function display_item_data($item_data,$cart_item){
        if(isset($cart_item['vc_np_number'])) $item_data[]=['name'=>__('Number','vcnp'),'value'=>(int)$cart_item['vc_np_number']];
        if(isset($cart_item['vc_np_skill_answer']) && $cart_item['vc_np_skill_answer']!=='') $item_data[]=['name'=>__('Answer','vcnp'),'value'=>esc_html($cart_item['vc_np_skill_answer'])];
        return $item_data;
    }
    public function store_order_item_meta($item,$key,$values,$order){
        if(!empty($values['vc_np_number'])) $item->add_meta_data('Number', (int)$values['vc_np_number'], true);
        if(isset($values['vc_np_skill_answer'])) $item->add_meta_data('Answer', sanitize_text_field($values['vc_np_skill_answer']), true);
    }

    public function cart_item_removed($cart_item_key,$cart){
        $item = $cart->removed_cart_contents[$cart_item_key] ?? null;
        if ($item && !empty($item['vc_np_number'])) VCNP_Helpers::release_if_mine($item['product_id'], (int)$item['vc_np_number']);
    }
}
