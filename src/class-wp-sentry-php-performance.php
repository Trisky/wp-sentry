<?php
final class WP_Sentry_Php_Performance
{
    public function __construct(){
        if(!self::is_performance_enabled()) return;

        if(defined('WP_SENTRY_MU_LOADED') ? WP_SENTRY_MU_LOADED : false){
            new WP_Sentry_Php_Performance_Main_Transaction(self::get_tracing_rate());
            $this->enable_performance_hooks();
        }
    }
    private static function get_tracing_rate(){
         return defined('WP_SENTRY_TRACES_SAMPLE_RATE') ? WP_SENTRY_TRACES_SAMPLE_RATE : 0;
    }
    public static function is_performance_enabled(){
        $sentry_tracing_rate = self::get_tracing_rate();
        return !is_numeric($sentry_tracing_rate) || $sentry_tracing_rate < 0;
    }

    private function enable_performance_hooks(){

    }
}
