<?php
/**
 * Parse wiki page and create html
 */

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();


// Check if telegram token exists
$dotenv->required(['TELEGRAM_TOKEN', 'TELEGRAM_GROUP', 'TELEGRAM_FAULT']);


// Parse data from wiki
function parse_data($data = []) {
    $page = '2019–20_Wuhan_coronavirus_outbreak';
    $wiki = file_get_contents('https://en.wikipedia.org/api/rest_v1/page/html/' . urlencode($page));

    if ($wiki === false) {
        throw new Exception("Can't get wiki page");
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

    return $data;
}


// Send message to Telegram
function send_message($text, $fault = false) {
    $message = [
        'chat_id' => getenv('TELEGRAM_GROUP'),
        'text' => $text,
        'parse_mode' => 'HTML'
    ];

    if ($fault === true) {
        $message['chat_id'] = getenv('TELEGRAM_FAULT');
    }

    // Telegram bot api url
    $botapi = 'https://api.telegram.org/bot' . getenv('TELEGRAM_TOKEN') . '/sendMessage?';

    file_get_contents($botapi . http_build_query($message));
}


// Compare and update item value
function compare_value($info, $current, $key) {
    if ($info[$key] > $current[$key]) {
        return $info[$key] . '+';
    }

    if ($info[$key] < $current[$key]) {
        return $info[$key] . '-';
    }

    return $info[$key];
}


// Update data in channel
function update_channel($current, $parsed) {
    if ($current === false) {
        return false;
    }

    $current = json_decode($current, JSON_OBJECT_AS_ARRAY);

    // Don't send if equal
    if ($current === $parsed) {
        return false;
    }

    $rows = [];

    foreach ($parsed as $info) {
        $item = array_search($info['region'], array_column($current, 'region'));

        if (is_int($item)) {
            // Update cases field
            $info['cases'] = compare_value($info, $current[$item], 'cases');

            // Updated death field
            $info['death'] = compare_value($info, $current[$item], 'death');
        }

        $rows[] = str_pad($info['region'], 18) . str_pad($info['cases'], 6) . $info['death'];
    }

    $message = sprintf('<pre>%s</pre>', implode("\n", $rows));

    // Send update message to telegram
    send_message($message);
}

try {
    $storage = __DIR__ . '/build//data.json';

    // Parse data from wiki
    $parsed = parse_data();

    // Get current data json
    $current = file_get_contents($storage);

    // Try to update channel data
    update_channel($current, $parsed);

    // Update data file
    file_put_contents($storage, json_encode($parsed));

} catch (Exception $e) {
    $message = sprintf('<strong>Error: </strong>%s', $e->getMessage());

    // Send fault message to telegram
    send_message($message, true);
}
