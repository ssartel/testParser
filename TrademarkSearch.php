<?php

use GuzzleHttp\Client;
use System\InvalidParamException;

class TrademarkSearch
{
    private Client $client;
    private string $baseUrl = 'https://search.ipaustralia.gov.au';
    private string $searchUrl = '/trademarks/search/';
    private string $csrfToken;

    private array $outputData;

    public function __construct()
    {
        $this->baseUrl .= $this->searchUrl;

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'cookies' => true,  // Включаем обработку cookies
        ]);

        $this->getCsrfToken();
    }

    public function getCsrfToken() :void
    {
        // Отправляем GET-запрос для получения страницы и получения куки с токеном
        $this->client->get('advanced');

        // Получаем cookies из клиента
        $cookies = $this->client->getConfig('cookies');

        // Ищем XSRF-TOKEN в cookies
        $csrfToken = $cookies->getCookieByName('XSRF-TOKEN');

        if (!$csrfToken->getValue()) {
            throw new InvalidParamException("Unable to retrieve CSRF token.\n");
        }

        $this->csrfToken = $csrfToken->getValue();
    }

    public function getBodyContent(string $query, string $method, string $url) :DOMXPath
    {
        $response = $this->client->request(strtoupper($method), $url, [
            'form_params' => [
                'wv[0]' => $query,
                //'_csrf' => $this->csrfToken,
            ],
            'headers' => [
                'X-XSRF-TOKEN' => $this->csrfToken,
                'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.104 Safari/537.36',
                'accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9'
            ],
        ]);

        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->loadHTML($response->getBody()->getContents());

        return new DOMXPath($doc);
    }

    public function parseData(DOMXPath $xpath) :void
    {
        $numbers = $xpath->evaluate('//table[@id="resultsTable"]//tbody//tr//td[@class="number"]//a');
        $logos = $xpath->evaluate('//table[@id="resultsTable"]//tbody//tr//td[@class="trademark image"]//img/@src');
        $names = $xpath->evaluate('//table[@id="resultsTable"]//tbody//tr//td[@class="trademark words"]');
        $classes = $xpath->evaluate('//table[@id="resultsTable"]//tbody//tr//td[@class="classes "]');
        $statuses = $xpath->evaluate('//table[@id="resultsTable"]//tbody//tr//td[@class="status"]');
        $url = $xpath->evaluate('//table[@id="resultsTable"]//tbody//tr/@data-markurl');

        foreach ($numbers as $key => $number) {
            $this->outputData[] = [
                'number' => $number->textContent,
                'url_logo' => $logos[$key]->textContent,
                'name' => preg_replace("/(\r\n|\r|\n)/u", " ", trim($names[$key]->textContent)),
                'class' => preg_replace("/(\r\n|\r|\n)/u", " ", trim($classes[$key]->textContent)),
                'status' => trim(preg_replace("/[^ a-zа-я\d.]/ui", " ", $statuses[$key]->textContent)),
                'url_details_page' => $this->baseUrl . str_replace($this->searchUrl, '', $url[$key]->textContent),
            ];
        }
    }

    public function searchTrademarks(string $query) :void
    {
        $xpath = $this->getBodyContent($query, 'post', 'doSearch');

        $firstPageData = $xpath->evaluate('//div[@class="pagination-buttons"]//a[@data-gotopage="0"]/@href');
        $lastPageData = $xpath->evaluate('//div[@class="pagination-buttons"]//a[@class="button green no-fill square goto-last-page"]/@data-gotopage');

        $searchUrl = str_replace($this->searchUrl, '', substr($firstPageData[0]->textContent, 0, -1));
        $lastPageNumber = $lastPageData[0]->textContent;

        $this->parseData($xpath);

        $page = 1;

        do {
            $xpath = $this->getBodyContent($query, 'get', $searchUrl . $page);
            $this->parseData($xpath);

            $page++;

        } while ($page <= $lastPageNumber);
    }

    public function setOutput() :void
    {
        printf("Results: %s\n[\n", count($this->outputData));

        foreach ($this->outputData as $key => $data) {
            printf("%4s.{\n", ($key + 1));
            foreach ($data as $name => $value) {
                printf("%6s", '');
                printf("\"%s\": \"%s\",\n", $name, $value);
            }
        }

        printf("%s\n]\n", '');
    }
}
