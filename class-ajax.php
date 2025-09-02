<?php
if (!defined('ABSPATH')) exit;

class VCNP_Ajax {
    public function __construct(){
        add_action('wp_ajax_vc_np_state',          [$this,'state']);
        add_action('wp_ajax_nopriv_vc_np_state',   [$this,'state']);
        add_action('wp_ajax_vc_np_reserve',        [$this,'reserve']);
        add_action('wp_ajax_nopriv_vc_np_reserve', [$this,'reserve']);
        add_action('wp_ajax_vc_np_release',        [$this,'release']);
        add_action('wp_ajax_nopriv_vc_np_release', [$this,'release']);
        add_action('wp_ajax_vc_np_add_to_cart',        [$this,'add_to_cart']);
        add_action('wp_ajax_nopriv_vc_np_add_to_cart', [$this,'add_to_cart']);
    }

    public function state(){
        check_ajax_referer('vc_np_nonce','nonce');
        $pid = absint($_POST['pid'] ?? 0);
        if(!VCNP_Helpers::is_lottery_product_id($pid)) wp_send_json_error(['msg'=>'bad pid']);
        $sold     = VCNP_Helpers::get_sold_numbers($pid);
        $reserved = VCNP_Helpers::reserved_numbers($pid);
        $mine = [];
        foreach ($reserved as $n) {
            $data = get_transient(VCNP_Helpers::hold_key($pid,$n));
            if ($data && ($data['sid'] ?? '') === VCNP_Helpers::session_id()) $mine[] = (int)$n;
        }
        wp_send_json_success(['sold'=>$sold,'reserved'=>$reserved,'mine'=>$mine,'ttl'=>VCNP_Helpers::hold_ttl($pid)]);
    }

    public function reserve(){
        check_ajax_referer('vc_np_nonce','nonce');
        $pid = absint($_POST['pid'] ?? 0);
        $num = absint($_POST['num'] ?? 0);
        if(!VCNP_Helpers::is_lottery_product_id($pid) || !$num) wp_send_json_error(['msg'=>'bad params']);
        $max = VCNP_Helpers::grid_max($pid);
        if ($num < 1 || $num > $max) wp_send_json_error(['msg'=>'out of range']);
        if (in_array($num, VCNP_Helpers::get_sold_numbers($pid), true)) wp_send_json_error(['msg'=>'sold']);
        if (VCNP_Helpers::is_reserved_by_other($pid,$num)) wp_send_json_error(['msg'=>'reserved']);
        if (VCNP_Helpers::reserve($pid,$num)) wp_send_json_success();
        wp_send_json_error(['msg'=>'fail']);
    }

    public function release(){
        check_ajax_referer('vc_np_nonce','nonce');
        $pid = absint($_POST['pid'] ?? 0);
        $num = absint($_POST['num'] ?? 0);
        if(!VCNP_Helpers::is_lottery_product_id($pid) || !$num) wp_send_json_error(['msg'=>'bad params']);
        VCNP_Helpers::release_if_mine($pid,$num);
        wp_send_json_success();
    }

    public function add_to_cart(){
        check_ajax_referer('vc_np_nonce','nonce');
        $pid  = absint($_POST['pid'] ?? 0);
        $nums = isset($_POST['nums']) ? (array) $_POST['nums'] : [];
        $nums = array_values(array_unique(array_map('intval',$nums)));
        $skill = sanitize_text_field($_POST['skill'] ?? '');

        if(!VCNP_Helpers::is_lottery_product_id($pid) || empty($nums)) wp_send_json_error(['msg'=>'Missing numbers']);

        // Skill check
        $skill_on = (get_post_meta($pid, VCNP_Helpers::META_SKILL_ON, true) === 'yes');
        if ($skill_on){
            $correct = (string) get_post_meta($pid, VCNP_Helpers::META_SKILL_A, true);
            if ($correct !== '' && mb_strtolower(trim($skill)) !== mb_strtolower(trim($correct))){
                wp_send_json_error(['msg'=>__('Incorrect answer to skill question.','vcnp')]);
            }
        }

        // Ensure Woo cart in AJAX
        if (function_exists('WC') && is_null(WC()->cart) && function_exists('wc_load_cart')) wc_load_cart();
        if (!function_exists('WC') || is_null(WC()->cart)) wp_send_json_error(['msg'=>'Cart unavailable.']);

        $max = VCNP_Helpers::grid_max($pid);
        foreach($nums as $n){
            if ($n < 1 || $n > $max) continue;
            if (in_array($n, VCNP_Helpers::get_sold_numbers($pid), true)) continue;
            if (VCNP_Helpers::is_reserved_by_other($pid,$n)) continue;
            VCNP_Helpers::reserve($pid,$n);
            WC()->cart->add_to_cart($pid, 1, 0, [], [
                'vc_np_number' => $n,
                'vc_np_skill_answer' => $skill_on ? $skill : '',
            ]);
        }

        $cart_url = function_exists('wc_get_cart_url') ? wc_get_cart_url() : '/cart';
        wp_send_json_success(['cart'=>$cart_url]);
    }
}
