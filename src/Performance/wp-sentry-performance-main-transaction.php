<?php

use Sentry\Tracing\Transaction;

final class WP_Sentry_Php_Performance_Main_Transaction
{
    private static $init = false;

    public function __construct($tracing_rate)
    {
        if (!self::$init) {
            $this->start_main_transaction($tracing_rate);
            add_action('shutdown', ['this', 'shutdown'], 99);
            self::$init = true;
        }
        return $this;
    }

    private function get_cli_arguments(): string
    {
        $commands = $_SERVER['argv'] ?? ['', 'SENTRY_PARSE_ERROR_NO_COMMAND'];
        array_shift($commands); // the first one is the wp binary.
        return implode(' ', $commands);
    }

    /**
     * Generate transaction name
     * Generates the URL of the request
     * Generates transaction type
     * @return string[]
     */
    private function generate_transaction_properties(): array
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
                $transaction_type = 'admin-ajax';

            } else {
                $name = $_SERVER['HTTP_HOST'] . parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
                $transaction_type = 'www-request';
            }
        } else {
            if (defined('DOING_CRON')) {
                $name = 'cron';
                $transaction_type = 'cron';
            } else {
                $name = 'wp ' . $this->get_cli_arguments();
                $transaction_type = 'wp-cli';
            }
        }
        \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($transaction_type): void {
            $scope->setTag('transaction.type', $transaction_type);
        });
        return array($url, $name, $transaction_type);
    }

    private function start_main_transaction($sentryTracingRate): Transaction
    {
        wp_sentry_safe(function (\Sentry\State\HubInterface $sentryHub) use ($sentryTracingRate) {
            $sentryHub->getClient()->getOptions()->setTracesSampleRate($sentryTracingRate);
            \Sentry\SentrySdk::setCurrentHub($sentryHub);
        });
        # 2 set up transaction context

        $transactionContext = new \Sentry\Tracing\TransactionContext();
        list($url, $name, $transaction_type) = $this->generate_transaction_properties();

        # 3 set up transaction context
        $transactionContext->setName($name);
        $transactionContext->setOp($transaction_type);
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

    public function shutdown()
    {
        # 7 finish span
        $sentrySpan = \Sentry\SentrySdk::getCurrentHub()->getSpan();
        if ($sentrySpan) {
            $sentrySpan->finish();
        }
        $sentryTransaction = \Sentry\SentrySdk::getCurrentHub()->getTransaction();

        # 8 finish transaction
        if ($sentryTransaction) {
            $data = $sentryTransaction->getData();
            $memoryBytes = memory_get_peak_usage(true);
            if (is_numeric($memoryBytes) && $memoryBytes > 0) {
                $data['memory_get_peak_usage'] = $memoryBytes / 1024 / 1024;
                $data['php_memory_limit_SHUTDOWN'] = ini_get('memory_limit');
                $sentryTransaction->setData($data);
            }
            \Sentry\SentrySdk::getCurrentHub()->setSpan($sentryTransaction);
            $transaction = \Sentry\SentrySdk::getCurrentHub()->getSpan();
            $transaction->finish();
        }
    }
}

