<?php
if (!defined('ABSPATH')) exit;

class VCNP_Orders {
    public function __construct(){
        add_action('woocommerce_order_status_processing', [$this,'mark_sold_from_order']);
        add_action('woocommerce_order_status_completed',  [$this,'mark_sold_from_order']);
    }

    public function mark_sold_from_order($order_id){
        $order = wc_get_order($order_id); if(!$order) return;
        $per = [];
        foreach($order->get_items() as $item){
            $pid = $item->get_product_id();
            $num = (int) $item->get_meta('Number');
            if($pid && $num) $per[(int)$pid][] = $num;
        }
        foreach($per as $pid=>$nums){
            VCNP_Helpers::add_sold_numbers((int)$pid, $nums);
            foreach($nums as $n){ VCNP_Helpers::force_release((int)$pid,(int)$n); }
        }
    }
}
