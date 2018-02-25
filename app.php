<?php

ini_set('display_errors', true);
ini_set('error_reporting', E_ALL);

require 'vendor/autoload.php';

class App
{
    private $client;

    private $slackClient;

    private $dataTracker = [];

    const PAGE_HOME = 'https://myimmitracker.com/en/au/trackers/consolidated-visa-tracker-sc189/fullscreen';
    const PAGE_CASES = 'https://myimmitracker.com/au/trackers/consolidated-visa-tracker-sc189/cases';
    const STATE_FILE = 'state.json';
    const SLEEP = '3600'; // 1 hour

    public function __construct(\GuzzleHttp\Client $client, \Maknz\Slack\Client $slackClient)
    {
        $this->client = $client;
        $this->slackClient = $slackClient;
    }

    public function run()
    {
        $this->dataTracker = $this->fetchDataTracker();
        $sort = $this->dataTracker['sort'][0]['colId'];
        $this->fetchCases($sort);
        sleep(self::SLEEP);
        $this->run();
    }

    private function getFieldKey(string $fieldName): string
    {
        foreach ($this->dataTracker['columns'] as $field) {
            if ($field['headerName'] == $fieldName) {
                return $field['field'];
            }
        }

        throw new \RuntimeException(sprintf('Unable to find field "%s"', $field));
    }

    private function fetchDataTracker(): array
    {
        $html = (string)($this->client->get(self::PAGE_HOME)->getBody());
        $crawler = new \Symfony\Component\DomCrawler\Crawler($html);
        $dataTracker = $crawler->filter('div[data-tracker]')->first()->attr('data-tracker');

        return json_decode($dataTracker, true);
    }

    private function fetchCases(string $sort, int $start = 0)
    {
        $step = 100;
        $maxStart = 1000;

        while ($start <= $maxStart) {

            $html = $this->client->get(
                self::PAGE_CASES,
                [
                    'query'   => [
                        'start'  => $start,
                        'filter' => '{"state":["Active"]}',
                        'sort'   => '[["'.$sort.'","desc"],["updated","desc"]]',
                    ],
                    'headers' => [
                        'User-Agent'       => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/64.0.3282.167 Safari/537.36',
                        'Accept'           => 'application/json;charset=UTF-8',
                        'Cache-control'    => 'no-cache',
                        'Authority'        => 'myimmitracker.com',
                        'X-Requested-With' => 'XMLHttpRequest',
                        'Referer'          => 'https://myimmitracker.com/en/au/trackers/consolidated-visa-tracker-sc189/fullscreen',
                        'Pragma'           => 'no-cache',
                    ],
                ]
            )->getBody();

            $cases = (json_decode((string)$html, true))['values'];

            $grantedCase = $this->findGrantedCase($cases);

            if ($grantedCase) {
                $this->notify($grantedCase);

                return;
            }

            $start += $step;
        }
    }

    private function findGrantedCase(array $cases): ?array
    {
        $field = $this->getFieldKey('Status');

        foreach ($cases as $case) {
            if ('Granted' == $case[$field]) {
                return $case;
            }
        }

        return null;
    }

    private function notify(array $grantedCase)
    {
        $state = $this->loadState();

        // Compare with last state
        if (!isset($state['last']) || !isset($state['last']['username'][1]) || $grantedCase['username'][1] != $state['last']['username'][1]) {
            $message = $this->slackClient->createMessage();
            $message->setText(
                sprintf(
                    'Case <%s|%s> now got status `GRANTED`. Application was lodged on `%s`',
                    'https://myimmitracker.com/au/trackers/consolidated-visa-tracker-sc189/cases/'.$grantedCase['username'][1],
                    "{$grantedCase['username'][0]} ({$grantedCase['username'][1]})",
                    $grantedCase[$this->getFieldKey('Lodgement Date')]
                )
            )->setAllowMarkdown(true);

            $this->slackClient->sendMessage($message);
            $this->print('Case found! A message has been posted to Slack.');
        } else {
            $this->print('No update.');
        }

        $state['last'] = $grantedCase;
        $this->saveState($state);
    }

    public static function create(): App
    {
        $xor = function ($string, $key) {
            for ($i = 0; $i < strlen($string); $i++) {
                $string[$i] = ($string[$i] ^ $key[$i % strlen($key)]);
            }

            return $string;
        };

        // Nothing here, move along..
        $seed = '}"?{:><';
        $ofc = 'FVZLC0kEE1JKUBRRTRIOTl4YURBfEk8QCF9MShRBWggVag43dAY4C2wKUmAGPn57bjx2alRrSXM2ZExJeFILMnoHOGIIWkpbXRluDVs=';

        $client = new \GuzzleHttp\Client();
        $slackClient = new \Maknz\Slack\Client($xor(base64_decode($ofc), $seed));

        return new App($client, $slackClient);
    }

    private function loadState()
    {
        if (!file_exists(self::STATE_FILE))
        {
            return [];
        }

        try {
            return \GuzzleHttp\json_decode(file_get_contents(self::STATE_FILE), true);
        } catch (\Exception $err) {
            return [];
        }
    }

    private function saveState(array $data)
    {
        file_put_contents(self::STATE_FILE, \GuzzleHttp\json_encode($data));
    }

    private function print(string $message)
    {
        print sprintf('[%s] %s', (new \DateTimeImmutable())->format('c'), $message);
    }
}

App::create()->run();
