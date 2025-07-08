<?php
/**
 * controllers/front/cron.php
 *
 * Cron endpoint: submits queued URLs to IndexNow for the current shop domain.
 */

class TbIndexNowCronModuleFrontController extends ModuleFrontController
{
    public $display_header = false;
    public $display_footer = false;

    public function initContent()
    {
        parent::initContent();

        // Authenticate request
        $key       = Tools::getValue('key') ?: Tools::getValue('INDEXNOW_API_KEY');
        $configKey = Configuration::get('INDEXNOW_API_KEY');
        if (!$key || $key !== $configKey) {
            http_response_code(401);
            exit('Unauthorized');
        }

        $db      = Db::getInstance();
        $currentHost = parse_url(Tools::getShopDomainSsl(true), PHP_URL_HOST);

        // Fetch only URLs queued for this shop's domain
        $allQueue = $db->executeS('SELECT id_queue, url FROM `' . _DB_PREFIX_ . TbIndexNow::QUEUE_TABLE . '`');
        $filtered = array_filter($allQueue, function($row) use ($currentHost) {
            return parse_url($row['url'], PHP_URL_HOST) === $currentHost;
        });
        if (empty($filtered)) {
            http_response_code(204);
            exit('No content');
        }

        // Extract URLs and IDs
        $ids  = array_column($filtered, 'id_queue');
        $urls = array_column($filtered, 'url');

        // Submit in chunks
        $ch = curl_init('https://api.indexnow.org/indexnow');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json; charset=utf-8',
            'Accept: application/json',
        ]);

        $statusFinal = 0;
        $now         = date('Y-m-d H:i:s');

        foreach (array_chunk($urls, 10000) as $chunk) {
            $payload = json_encode([
                'host'        => $currentHost,
                'key'         => $key,
                'keyLocation' => Tools::getShopDomainSsl(true) . '/' . $key . '.txt',
                'urlList'     => array_values($chunk),
            ], JSON_UNESCAPED_SLASHES);

            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            $resp = curl_exec($ch);
            if ($resp === false) {
                $status   = 0;
                $response = curl_error($ch);
            } else {
                $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $response = $resp;
            }
            $statusFinal = $status;

            // Log history for each URL in this chunk
            foreach ($chunk as $url) {
                Db::getInstance()->insert(
                    TbIndexNow::HISTORY_TABLE,
                    [
                        'url'         => pSQL($url),
                        'status_code' => (int)$status,
                        'response'    => pSQL($response),
                        'date_add'    => $now,
                    ]
                );
            }
        }

        curl_close($ch);

        // Remove only processed entries from queue
        Db::getInstance()->delete(
            _DB_PREFIX_ . TbIndexNow::QUEUE_TABLE,
            'id_queue IN (' . implode(', ', array_map('intval', $ids)) . ')'
        );

        // Output summary
        http_response_code($statusFinal);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => $statusFinal,
            'count'  => count($urls),
        ]);
        exit;
    }
}
