<?php
if (!defined('ABSPATH')) exit;

class VCNP_Helpers {
    const META_SOLD      = '_vc_np_sold';
    const META_MAX       = '_vc_np_max';
    const META_SKILL_ON  = '_vc_np_skill_enabled';
    const META_SKILL_Q   = '_vc_np_skill_q';
    const META_SKILL_A   = '_vc_np_skill_a';
    const META_FORCE     = '_vc_np_force'; // compat: force enable picker
    const TRANSIENT_SOLD_PREFIX = 'vc_np_sold_';
    const HOLD_PREFIX    = 'vc_np_hold_';
    const HOLD_TTL       = 600; // 10 minutes (default)

    /** Current single-product page product ID (robust) */
    public static function current_product_id(): int {
        if (!function_exists('is_product') || !is_product()) return 0;
        $pid = absint(get_queried_object_id());
        if ($pid) return $pid;
        global $post; if ($post && !empty($post->ID)) return absint($post->ID);
        global $product; if (is_object($product) && method_exists($product,'get_id')) return absint($product->get_id());
        return 0;
    }

    /** True if the given product is a Woo Lottery product or forced on */
    public static function is_lottery_product_id(int $pid): bool {
        if (!$pid) return false;
        $enabled = false;
        if (get_post_meta($pid, self::META_FORCE, true) === 'yes') $enabled = true;
        elseif (taxonomy_exists('product_type')) {
            try { $enabled = has_term('lottery', 'product_type', $pid); }
            catch (\Throwable $e) { $enabled = false; }
        }
        /** Allow hotfixes to force-enable/disable */
        return (bool) apply_filters('vcnp_is_product_enabled', $enabled, $pid);
    }

    public static function is_lottery_product_page(): bool {
        $pid = self::current_product_id();
        return self::is_lottery_product_id($pid);
    }

    /** SOLD cache helpers */
    public static function transient_key(int $pid): string { return self::TRANSIENT_SOLD_PREFIX . $pid; }
    public static function get_sold_numbers(int $pid): array {
        $key = self::transient_key($pid);
        $cached = get_transient($key);
        if ($cached !== false) return (array) $cached;
        $arr = get_post_meta($pid, self::META_SOLD, true);
        $arr = is_array($arr) ? array_map('intval', $arr) : [];
        sort($arr);
        set_transient($key, $arr, HOUR_IN_SECONDS);
        return $arr;
    }
    public static function add_sold_numbers(int $pid, array $nums): void {
        $existing = self::get_sold_numbers($pid);
        $merged = array_unique(array_merge($existing, array_map('intval',$nums)));
        sort($merged);
        update_post_meta($pid, self::META_SOLD, $merged);
        delete_transient(self::transient_key($pid));
    }

    /** Grid size (enforced) */
    public static function grid_max(int $pid): int {
        $max = (int) get_post_meta($pid, self::META_MAX, true);
        if ($max < 1)  $max = 100;
        if ($max > 1000) $max = 1000;
        /** Allow hotfixes to change grid size on the fly */
        return (int) apply_filters('vcnp_grid_max', $max, $pid);
    }

    /** Hold TTL with filter */
    public static function hold_ttl(int $pid): int {
        $ttl = (int) self::HOLD_TTL;
        return (int) apply_filters('vcnp_hold_ttl', $ttl, $pid);
    }

    /** Session id for holds (stable per user/session) */
    public static function session_id(): string {
        if (function_exists('WC') && WC()->session) {
            $sid = WC()->session->get_customer_id();
            if ($sid) return 'sid_' . md5($sid);
        }
        $sid = is_user_logged_in() ? (string) get_current_user_id() : (string) wp_get_session_token();
        return 'sid_' . md5($sid ?: 'guest');
    }

    /** Hold keys */
    public static function hold_key(int $pid,int $num): string { return self::HOLD_PREFIX.$pid.'_'.$num; }
    public static function idx_key(int $pid): string { return self::HOLD_PREFIX.'idx_'.$pid; }

    /** Reserve a number (idempotent for same session) */
    public static function reserve(int $pid,int $num): bool {
        $key = self::hold_key($pid,$num);
        $now = time();
        $data = get_transient($key);
        if (is_array($data) && !empty($data['exp']) && $data['exp'] < $now) { delete_transient($key); $data = false; }
        $sid = self::session_id();
        if ($data && ($data['sid'] ?? '') !== $sid) return false;
        $exp = $now + self::hold_ttl($pid);
        set_transient($key, ['sid'=>$sid,'exp'=>$exp], self::hold_ttl($pid));
        $idx = get_transient(self::idx_key($pid)) ?: [];
        $idx[(int)$num] = (int)$exp;
        set_transient(self::idx_key($pid), $idx, self::hold_ttl($pid));
        return true;
    }
    public static function force_release(int $pid,int $num): void {
        delete_transient(self::hold_key($pid,$num));
        $idx = get_transient(self::idx_key($pid)) ?: [];
        unset($idx[(int)$num]);
        set_transient(self::idx_key($pid), $idx, self::hold_ttl($pid));
    }
    public static function release_if_mine(int $pid,int $num): void {
        $key  = self::hold_key($pid,$num);
        $data = get_transient($key);
        if ($data && ($data['sid'] ?? '') === self::session_id()) self::force_release($pid,$num);
    }
    public static function is_reserved_by_other(int $pid,int $num): bool {
        $key = self::hold_key($pid,$num);
        $now = time();
        $data = get_transient($key);
        if (!$data) return false;
        if (!empty($data['exp']) && $data['exp'] < $now) { delete_transient($key); return false; }
        return ($data['sid'] ?? '') !== self::session_id();
    }
    public static function reserved_numbers(int $pid): array {
        $idx = get_transient(self::idx_key($pid)) ?: [];
        $now = time(); $out = []; $dirty = false;
        foreach ($idx as $n=>$exp) {
            $data = get_transient(self::hold_key($pid,(int)$n));
            if ($data && ($data['exp'] ?? 0) > $now) $out[] = (int)$n;
            else { $dirty = true; unset($idx[$n]); }
        }
        if ($dirty) set_transient(self::idx_key($pid), $idx, self::hold_ttl($pid));
        sort($out); return $out;
    }
}
