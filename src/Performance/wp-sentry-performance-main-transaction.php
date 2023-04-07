<?php

use Sentry\Tracing\Transaction;

final class WP_Sentry_Php_Performance_Main_Transaction {

    public function __construct(){

    }
    private function get_cli_arguments() : string {
        $commands = $_SERVER['argv'] ?? ['','SENTRY_PARSE_ERROR_NO_COMMAND'];
        array_shift($commands); // the first one is the wp binary.
        return implode(' ', $commands);
    }

    /**
     * @return string[]
     */
    protected function generate_transaction_properties(): array
    {
        $url = null;
        $transaction = null;
        if ($_SERVER['REQUEST_URI'] ?? null) {
            $url = "$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
            if (strpos($_SERVER['REQUEST_URI'], 'wp-admin/admin-ajax.php') !== false) {
                $actionName = $_GET['action'] ?? $_POST['action'] ?? 'SENTRY_PARSE_ERROR_NO_ACTION';
                /**
                 * If its admin ajax, the transaction name includes the name of the action instead of the URL.
                 */
                $name = "admin-ajax: " . $actionName;
                $operationType = 'admin-ajax';

            } else {
                $name = $_SERVER['HTTP_HOST'] . parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
                $operationType = 'www-request';
            }
        } else {
            if (defined('DOING_CRON')) {
                $name = 'cron';
                $operationType = 'cron';
            } else {
                $command = $this->get_cli_arguments();
                $name = 'wp ' . $command;
                $operationType = 'wp-cli';
            }
        }
        \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($operationType): void {
            $scope->setTag('transaction.type', $operationType);
        });
        return array($url, $name, $operationType);
    }
    function start_main_transaction( $sentryTracingRate): Transaction
    {
        wp_sentry_safe(function (\Sentry\State\HubInterface $sentryHub) use ($sentryTracingRate) {
            $sentryHub->getClient()->getOptions()->setTracesSampleRate($sentryTracingRate);
            \Sentry\SentrySdk::setCurrentHub($sentryHub);
        });
        # 2 set up transaction context

        $transactionContext = new \Sentry\Tracing\TransactionContext();
        list($url, $name, $operationType) = $this->generate_transaction_properties();

        # 3 set up transaction context
        $transactionContext->setName($name);
        $transactionContext->setOp($operationType);
        $transactionContext->setData([
            'php_memory_limit_INITIAL' => ini_get('memory_limit'),
            'url' => $url,
            'method' => strtoupper($_SERVER['REQUEST_METHOD'] ?? '')
        ]);
        $transactionContext->setStartTimestamp($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));

        # 4 start transaction
        $transaction = \Sentry\SentrySdk::getCurrentHub()->startTransaction($transactionContext);
        \Sentry\SentrySdk::getCurrentHub()->setSpan($transaction);
        return $transaction;
    }
}

