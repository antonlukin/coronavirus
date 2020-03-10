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


// Parse non-China table
function parse_table($html, $data = []) {
    $rows = $html->find('tr');

    // Set initial skip value
    $skip = true;

    foreach (array_slice($rows, 7, -4) as $row) {
        $region = $row->child(1)->text();

        if (preg_match('/china/i', $region)) {
            $region = 'China';
        }

        $data[] = [
            'region' => $region,
            'cases' => $row->child(2)->text(),
            'death' => $row->child(3)->text()
        ];
    }

    return $data;
}


// Parse data from wiki
function parse_data($data = []) {
    $context = stream_context_create([
        'http'=> [
            'timeout' => 10,
            'user_agent' => 'Coronavirus fetch bot / https://coronavirus.zone'
        ]
    ]);

    $page = @file_get_contents('https://docs.google.com/spreadsheets/u/0/d/e/2PACX-1vR30F8lYP3jG7YOq8es0PBpJIE5yvRVZffOyaqC0GgMBN6yt0Q-NI8pxS7hd1F9dYXnowSC6zpZmW9D/pubhtml/sheet?gid=0', false, $context);

    if ($page === false) {
        throw new Exception("Can't get news page");
    }

    $html = new DiDom\Document;
    $html->loadHtml($page);

    // Get all tables
    $table = $html->first('table.waffle');

    // Parse data from table
    $data = parse_table($table);

    foreach($data as &$info) {
        foreach ($info as &$field) {
            $field = str_replace([',', '*'], '', trim($field));

            if (strlen($field) === 0) {
                $field = 0;
            }
        }
    }


    // Sort by cases
    $cases = array_column($data, 'cases');
    array_multisort($cases, SORT_DESC, $data);

    return $data;
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
        throw new Exception("Something broken in news page markup");
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
