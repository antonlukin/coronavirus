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


// Calculate data across regions
function calculate_data($data) {
    $calc = [];

    foreach ($data as $item) {
        $region = $item['region'];

    }


    return $data;
}


// Get csv from github
function parse_data($data = [], $output = []) {
    $context = stream_context_create([
        'http'=> [
            'timeout' => 10,
            'user_agent' => 'Coronavirus fetch bot / https://coronavirus.zone'
        ]
    ]);

    $raw = 'https://raw.githubusercontent.com/CSSEGISandData/COVID-19/master/csse_covid_19_data/csse_covid_19_daily_reports/';

    // Get csv
    $csv = @file_get_contents($raw . date('m-d-Y', time() - 3600 * 24) . '.csv', false, $context);

    foreach (explode("\n", $csv) as $i => $row) {
        $handle = str_getcsv($row);

        if (!array_filter($handle) || $i === 0) {
            continue;
        }

        $region = $handle[1];

        if (!isset($data[$region])) {
            $data[$region] = ['cases' => 0, 'death' => 0];
        }

        $data[$region]['cases'] += $handle[3];
        $data[$region]['death'] += $handle[4];
    }

    // Sort by cases
    $cases = array_column($data, 'cases');
    array_multisort($cases, SORT_DESC, $data);

    foreach ($data as $region => $info) {
        $output[] = array_merge(['region' => $region], $info);
    }

    return $output;
}


// Compare and update item value
function compare_value($info, $current, $item, $key) {
    if ($item === false || empty($current[$item][$key])) {
        $current[$item][$key] = 0;
    }

    if ($info[$key] > $current[$item][$key]) {
        return $info[$key] . '+';
    }

    if ($info[$key] < $current[$item][$key]) {
        return $info[$key] . '-';
    }

    return $info[$key];
}


// Update data in channel
function update_channel($current, $parsed) {
    $rows = [];

    foreach ($parsed as $info) {
        $item = array_search($info['region'], array_column($current, 'region'));

        // Update cases field
        $info['cases'] = compare_value($info, $current, $item, 'cases');

        // Updated death field
        $info['death'] = compare_value($info, $current, $item, 'death');

        // Create data string
        $data = "{$info['region']} - {$info['cases']} / {$info['death']}";

        if ($info !== $current[$item]) {
            $data = " • " . $data;
        }

        $rows[] = $data;
    }

    $message = sprintf("<strong>Latest updates on the Wuhan coronavirus outbreak: </strong>\n<pre>%s</pre>", implode("\n", $rows));

    // Send update message to telegram
    send_message($message);
}

try {
    $storage = __DIR__ . '/build/data.json';

    // Parse data from wiki
    $parsed = parse_data();

    // Check buggy markup
    if (count($parsed) < 10) {
        throw new Exception("Something broken in markup");
    }

    // Get current data json
    $saved = @file_get_contents($storage);

    if ($saved !== false) {
        // Get JSON from file data
        $current = json_decode($saved, JSON_OBJECT_AS_ARRAY);

        // Check if not updated
        if ($current !== $parsed) {
            $backup = __DIR__ . '/library/data-' . time() . '.json';

            // Backup current data
            file_put_contents($backup, $saved);

            // Update channel message
            update_channel($current, $parsed);
        }
    }

    // Update data file
    file_put_contents($storage, json_encode($parsed));

} catch (Exception $e) {
    $message = sprintf('<strong>Error: </strong>%s', $e->getMessage());

    // Send fault message to telegram
    send_message($message, true);
}
