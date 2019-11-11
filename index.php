<?php

require 'vendor/autoload.php';

const CALENDAR_ID = 'primary';

if (php_sapi_name() != 'cli') {
    throw new Exception('This application must be run on the command line.');
}

$dotenv = Dotenv\Dotenv::create(__DIR__);
$dotenv->load();

/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 */
function getClient()
{
    $client = new Google_Client();
    $client->setApplicationName('MyGes Sync');
    $client->setScopes([
        Google_Service_Calendar::CALENDAR
    ]);
    $client->setAuthConfig('credentials.json');
    $client->setAccessType('offline');
    $client->setPrompt('select_account consent');

    $tokenPath = 'token.json';
    if (file_exists($tokenPath)) {
        $accessToken = json_decode(file_get_contents($tokenPath), true);
        $client->setAccessToken($accessToken);
    }

    if ($client->isAccessTokenExpired()) {
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        } else {
            $authUrl = $client->createAuthUrl();
            printf("Open the following link in your browser:\n%s\n", $authUrl);
            print 'Enter verification code: ';
            $authCode = trim(fgets(STDIN));

            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
            $client->setAccessToken($accessToken);

            if (array_key_exists('error', $accessToken)) {
                throw new Exception(join(', ', $accessToken));
            }
        }

        if (!file_exists(dirname($tokenPath))) {
            mkdir(dirname($tokenPath), 0700, true);
        }

        file_put_contents($tokenPath, json_encode($client->getAccessToken()));
    }

    return $client;
}

function getRoomsFromSession($session) 
{
    $rooms = [];

    if (!empty($session->rooms)) {
        foreach ($session->rooms as $room) {
            $rooms[] = $room->name . ' - ' . $room->floor;
        }
    }

    return $rooms;
}

function getDateTimeForEvent($msTimestamp) {
    $date = DateTime::createFromFormat('U', $msTimestamp / 1000);
    return $date->format(\DateTime::RFC3339);
}

$client = getClient();
$service = new Google_Service_Calendar($client);

try {
    $mygesClient = new MyGes\Client(getenv('MYGES_CLIENT_ID'), getenv('MYGES_LOGIN'), getenv('MYGES_PASSWORD'));    
} catch (Exception $e) {
    die($e->getMessage());
}

$start  = new DateTime('next monday +1 week');
$end    = new DateTime('next sunday +1 week');

$me = new MyGes\Me($mygesClient);
$agenda = $me->getAgenda($start->getTimestamp() * 1000, $end->getTimestamp() * 1000);

if ($agenda) {
    $events = $service->events->listEvents(CALENDAR_ID, [
        'timeMin' => $start->format(DateTime::RFC3339), 
        'timeMax' => $end->format(DateTime::RFC3339)
    ]);

    // delete
    foreach ($events as $e) {
        if (strpos($e->summary, 'ESGI >') !== false) {
            $service->events->delete(CALENDAR_ID, $e->id);
            echo '[-] ' . $e->summary;
        }
    }
    
    // import
    foreach ($agenda as $session) {
        $rooms  = getRoomsFromSession($session);

        $event = new Google_Service_Calendar_Event();
        $event->setSummary('ESGI > #' . $session->reservation_id . ' > ' . $session->name);

        if ($session->teacher && $rooms) {
            $event->setDescription('Avec ' . $session->teacher . ' en ' . implode('\n', $rooms));
        }

        $s = new Google_Service_Calendar_EventDateTime();
        $s->setDateTime(getDateTimeForEvent($session->start_date));
        $event->setStart($s);

        $e = new Google_Service_Calendar_EventDateTime();
        $e->setDateTime(getDateTimeForEvent($session->end_date));
        $event->setEnd($e);

        $service->events->insert(CALENDAR_ID, $event);
        echo '[+] ' . $e->summary;
    }
}