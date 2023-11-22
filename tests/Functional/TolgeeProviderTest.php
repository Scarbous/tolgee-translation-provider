<?php

declare(strict_types=1);

namespace Scarbous\TolgeeTranslationProvider\Test\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Provider\TranslationProviderCollection;
use Symfony\Component\Translation\TranslatorBag;
use Symfony\Bundle\FrameworkBundle\Console\Application;


class TolgeeProviderTest extends KernelTestCase
{

    private $currentKernel;
    private $app;

    public function setUp(): void
    {
        $this->currentKernel = self::bootKernel([
            'environment' => 'test',
            'debug'       => false,
        ]);
        $this->app = new Application($this->currentKernel);
    }

    private function getTolgeeProvider()
    {
        /** @var TranslationProviderCollection $translationProviderCollection */
        $translationProviderCollection = $this->getContainer()->get('translation.provider_collection');

        return $translationProviderCollection->get('tolgee');
    }

    private function writeTranslations():void
    {
        $translatorBag = new TranslatorBag();
        $translatorBag->addCatalogue(new MessageCatalogue('sk', [
            'message' => [
                'foo' => 'Foo sk'
            ],
            'validat' => [
                'error' => 'Error'
            ]
        ]));

        $translatorBag->addCatalogue(new MessageCatalogue('en', [
            'message' => [
                'foo' => 'Foo en'
            ]
        ]));

        $this->getTolgeeProvider()->write($translatorBag);
    }

    public function testTranslationPull(): void
    {
        $this->expectNotToPerformAssertions();
        $this->app->run(new ArrayInput([
            'translation:pull',
            '--force' => true,
            '-vvv' => '',
            'provider' => 'tolgee'
        ]));
    }

    public function testTranslationPush(): void
    {
        $this->expectNotToPerformAssertions();
        $this->app->run(new ArrayInput([
            'translation:push',
            '--force' => true,
            '-vvv' => '',
            'provider' => 'tolgee'
        ]));
    }
}
