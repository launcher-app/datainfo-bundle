<?php

namespace Hallboav\DatainfoBundle\Sistema\Crawler;

use Symfony\Component\BrowserKit\Cookie as BrowserKitCookie;
use Symfony\Component\BrowserKit\CookieJar as BrowserKitCookieJar;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Hallison Boaventura <hallisonboaventura@gmail.com>
 */
abstract class AbstractPageCrawler
{
    /**
     * @var HttpClientInterface
     */
    protected $client;

    /**
     * @var BrowserKitCookieJar
     */
    protected $cookieJar;

    /**
     * @var string
     */
    protected $instance;

    /**
     * @var AdapterInterface
     */
    protected $cache;

    /**
     * @var string
     */
    protected $contents;

    /**
     * @var Form
     */
    protected $form;

    /**
     * @var Crawler
     */
    protected $crawler;

    /**
     * Construtor.
     *
     * @param HttpClientInterface $client
     * @param string              $instance p_instance.
     * @param AdapterInterface    $cache
     * @param string              $cacheKey
     */
    public function __construct(HttpClientInterface $client, string $instance, AdapterInterface $cache, string $cacheKey)
    {
        $this->client = $client;
        $this->instance = $instance;
        $this->cache = $cache;
        $this->cacheKey = $cacheKey;
    }

    /**
     * Obtém o URI.
     *
     * URI onde está a página para fazer o crawling.
     *
     * @return string URI.
     */
    abstract protected function getUri(): string;

    /**
     * Obtém o texto do botão do formulário.
     *
     * Usado para obter pSalt, pPageItemsProtected, etc.
     *
     * @return string
     */
    abstract protected function getFormButtonText(): string;

    /**
     * Obtém o conteúdo HTML da página.
     *
     * @return self
     */
    public function crawl(): self
    {
        $pageCacheItem = $this->cache->getItem($this->cacheKey);
        if ($pageCacheItem->isHit()) {
            $page = $pageCacheItem->get();
            $this->contents = $page['contents'];
            $this->cookieJar = $page['cookie_jar'];

            return $this;
        }

        $uri = $this->getUri($this->instance);
        $response = $this->client->request('GET', $uri);

        $this->contents = $response->getContent();

        $cookieJar = new BrowserKitCookieJar();
        $headers = $response->getHeaders();
        if (isset($headers['set-cookie'])) {
            foreach ($headers['set-cookie'] as $cookieStr) {
                $cookieJar->set(BrowserKitCookie::fromString($cookieStr, $uri));
            }
        }

        $pageCacheItem->set([
            'contents' => $this->contents,
            'cookie_jar' => $cookieJar,
        ]);

        $this->cache->save($pageCacheItem);

        return $this;
    }

    public function getCookieJar(): BrowserKitCookieJar
    {
        return $this->cookieJar;
    }

    /**
     * Obtém o salt contido na tela de login.
     *
     * @return string
     */
    public function getSalt(): string
    {
        if (null === $this->crawler) {
            $this->crawler = $this->getCrawler();
        }

        return $this->crawler->filter('input#pSalt')->attr('value');
    }

    /**
     * Obtém o protected contido na tela de login.
     *
     * @return string
     */
    public function getProtected(): string
    {
        if (null === $this->crawler) {
            $this->crawler = $this->getCrawler();
        }

        return $this->crawler->filter('input#pPageItemsProtected')->attr('value');
    }

    /**
     * Obtém o ajaxId.
     *
     * @param string $contents    String que contém o ajaxIdentifier.
     * @param string $leftRegExp  Expressão regular à esquerda da expressão regular do ajaxIdentifier.
     * @param string $rightRegExp Expressão regular à direita da expressão regular do ajaxIdentifier.
     *
     * @return string ajaxId.
     *
     * @throws \LengthException Quando não é possível obter o ajaxIdentifier.
     */
    protected function getAjaxId(string $subject, string $leftRegExp, string $rightRegExp): string
    {
        if (null === $ajaxId = $this->parseAjaxId($subject, $leftRegExp, $rightRegExp)) {
            throw new \LengthException('ajaxIdentifier não encontrado');
        }

        return $ajaxId;
    }

    /**
     * Obtém o formulário.
     *
     * @return Form
     */
    protected function getForm(): Form
    {
        if (null === $this->crawler) {
            $this->crawler = $this->getCrawler();
        }

        return $this->crawler->selectButton($this->getFormButtonText())->form();
    }

    /**
     * Obtém instância de Crawler.
     *
     * @return Crawler
     */
    protected function getCrawler(): Crawler
    {
        if (null === $this->contents) {
            $this->crawl();
        }

        $uri = sprintf('%s%s', 'http://sistema.datainfo.inf.br', $this->getUri());

        return new Crawler($this->contents, $uri);
    }

    /**
     * Obtém o valor do ajaxIdentifier.
     *
     * @param string $subject     String que contém o ajaxIdentifier.
     * @param string $leftRegExp  Expressão regular à esquerda da expressão regular do ajaxIdentifier.
     * @param string $rightRegExp Expressão regular à direita da expressão regular do ajaxIdentifier.
     *
     * @return string|null ajaxIdentifier encontrado ou null caso não encontrado.
     */
    private function parseAjaxId(string $subject, string $leftRegExp, string $rightRegExp): ?string
    {
        $pattern = sprintf('#%s"ajaxIdentifier":"(?P<ajax_id>.+)"%s#', $leftRegExp, $rightRegExp);

        if (preg_match($pattern, $subject, $matches)) {
            return $matches['ajax_id'];
        }

        return null;
    }
}
