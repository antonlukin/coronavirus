<?php
/**
 * Parse wiki page and create html
 */

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();


// Check if telegram token exists
$dotenv->required(['TELEGRAM_TOKEN', 'TELEGRAM_GROUP', 'TELEGRAM_FAULT']);


// Send message to Telegram
function send_message($text, $fault = false) {
    $message = [
        'chat_id' => getenv('TELEGRAM_GROUP'),
        'text' => $text,
        'disable_notification' => true,
        'parse_mode' => 'HTML'
    ];

    // Telegram bot api url
    $botapi = 'https://api.telegram.org/bot' . getenv('TELEGRAM_TOKEN') . '/sendMessage?';

    if ($fault === true) {
        $message['chat_id'] = getenv('TELEGRAM_FAULT');
    }

    @file_get_contents($botapi . http_build_query($message));
}


// Parse data from wiki
function parse_data($data = []) {
    $context = stream_context_create([
        'http'=> [
            'timeout' => 10,
            'user_agent' => 'Coronavirus fetch bot / https://coronavirus.zone'
        ]
    ]);

    $page = '2019–20_Wuhan_coronavirus_outbreak';
    $wiki = file_get_contents('https://en.wikipedia.org/api/rest_v1/page/html/' . urlencode($page), false, $context);

    if ($wiki === false) {
        throw new Exception("Can't get wiki page");
    }

    $html = new DiDom\Document;
    $html->loadHtml($wiki);

    $rows = $html->find('.infobox .wikitable tr:has(td)');

    foreach (array_slice($rows, 0, -1) as $row) {
        // Parse region from first cell
        preg_match('#[A-Z][\w\s]+#', $row->child(0)->text(), $region);

        // Fix China title variability
        if (strpos(strtolower($region[0]), 'china') !== false) {
            $region[0] = 'China';
        }

        // Parse cases
        preg_match('#[\d,]+#', $row->child(1)->text(), $cases);

        // Parse death
        preg_match('#[\d,]+#', $row->child(2)->text(), $death);

        // Parse cured
        preg_match('#[\d,]+#', $row->child(3)->text(), $cured);

        $info = [
            'region' => trim($region[0]),
        ];

        // Add numeric fields
        foreach (['cases', 'death', 'cured'] as $k => $item) {
            $info[$item] = str_replace(',', '', $$item[0]);
        }

        $data[] = $info;
    }

    return $data;
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

            // Updated cured field
            $info['cured'] = compare_value($info, $current[$item], 'cured');
        }

        $rows[] = str_pad($info['region'], 18) . str_pad($info['cases'], 10) . str_pad($info['death'], 10) . $info['cured'];
    }

    $message = sprintf("<strong>Latest updates on the Wuhan coronavirus outbreak: </strong>\n<pre>%s</pre>", implode("\n", $rows));

    // Send update message to telegram
    send_message($message);
}

try {
    $storage = __DIR__ . '/buid/data.json';

    // Parse data from wiki
    $parsed = parse_data();

    // Check buggy markup
    if (count($parsed) < 10) {
        throw new Exception("Something broken in wiki markup");
    }

    // Get current data json
    $current = @file_get_contents($storage);

    // Try to update channel data
    update_channel($current, $parsed);

    // Update data file
    file_put_contents($storage, json_encode($parsed));

} catch (Exception $e) {
    $message = sprintf('<strong>Error: </strong>%s', $e->getMessage());

    // Send fault message to telegram
    send_message($message, true);
}