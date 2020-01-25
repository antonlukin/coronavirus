<?php
/**
 * Parse wiki page and create html
 */

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Check if telegram token exists
$dotenv->required(['TELEGRAM_TOKEN', 'TELEGRAM_CHAT']);

// Telegram bot api url
$botapi = 'https://api.telegram.org/bot' . getenv('TELEGRAM_TOKEN') . '/sendMessage?';

$notify = [
    'chat_id' => getenv('TELEGRAM_CHAT'),
    'parse_mode' => 'HTML'
];

try {
    $data = [];

    $page = '2019–20_Wuhan_coronavirus_outbreak';
    $wiki = file_get_contents('https://en.wikipedia.org/api/rest_v1/page/html/' . urlencode($page));

    if ($wiki === false) {
        throw new Exception("Can't get WIKI page");
    }

    $html = new DiDom\Document;
    $html->loadHtml($wiki);

    $rows = $html->find('.infobox .wikitable tr:has(td)');

    foreach ($rows as $row) {
        if (!$row->child(0)->has('td > b')) {
            preg_match('#[A-Z][\w\s]+#', $row->child(0)->text(), $region);

            $info = [
                'region' => $region[0],
                'cases' => str_replace(',', '', $row->child(1)->text()),
                'death' => $row->child(2)->text()
            ];

            $data[] = array_map('trim', $info);
        }
    }

    $checksum = null;

    // Data file path
    $file = __DIR__ . '/build/data.json';

    if (file_exists($file)) {
        $checksum = md5_file($file);
    }

    // Update data file
    file_put_contents($file, json_encode($data));

    if (md5_file($file) !== $checksum) {
        $rows = [];

        foreach ($data as $info) {
            $rows[] = str_pad($info['region'], 25) . $info['cases'];
        }

        $notify['text'] = implode("\n", $rows);
        $notify['text'] = '<pre>' . $notify['text'] . '</pre>';

        // Send telegram message
        file_get_contents($botapi . http_build_query($notify));
    }

} catch (Exception $e) {
    $notify['text'] = $e->getMessage();

    // Send telegram message
    file_get_contents($botapi . http_build_query($notify));
}
