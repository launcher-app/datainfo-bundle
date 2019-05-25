<?php

namespace Hallboav\DatainfoBundle\Sistema\Crawler;

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;

/**
 * @author Hallison Boaventura <hallisonboaventura@gmail.com>
 */
class QueryPageCrawler extends AbstractPageCrawler
{
    /**
     * @var Form
     */
    private $form;

    /**
     * @var Crawler
     */
    private $crawler;

    /**
     * {@inheritDoc}
     */
    public function getUri(): string
    {
        return sprintf('/apex/f?p=104:10:%s::NO::P10_W_DAT_INICIO,P10_W_DAT_TERMINO:', $this->instance);
    }

    /**
     * Obtém ajaxId para consultar o saldo.
     *
     * @return string
     */
    public function getAjaxIdForBalanceChecking(): string
    {
        if (null === $this->contents) {
            $this->crawl();
        }

        $rightRegExp = '\,"attribute01":"\#P10_W_DAT_INICIO';

        return $this->getAjaxId($this->contents, '', $rightRegExp);
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
     * Obtém o formulário.
     *
     * @return Form
     */
    private function getForm(): Form
    {
        if (null === $this->crawler) {
            $this->crawler = $this->getCrawler();
        }

        return $this->crawler->selectButton('Consultar')->form();
    }

    /**
     * Obtém instância de Crawler.
     *
     * @return Crawler
     */
    private function getCrawler(): Crawler
    {
        if (null === $this->contents) {
            $this->crawl();
        }

        $uri = sprintf('%s%s', $this->client->getConfig('base_uri'), $this->getUri());

        return new Crawler($this->contents, $uri);
    }
}
