<?php

declare(strict_types=1);

namespace Pdir\MatomoAiTrackingBundle\Tests\Detector;

use Pdir\MatomoAiTrackingBundle\Detector\AiBotDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AiBotDetectorTest extends TestCase
{
    #[DataProvider('matomoChatbotProvider')]
    public function testDetectsChatbotsSupportedByMatomo(string $userAgent, string $expected): void
    {
        $this->assertSame($expected, (new AiBotDetector())->match($userAgent));
    }

    public static function matomoChatbotProvider(): iterable
    {
        yield 'ChatGPT-User' => ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; ChatGPT-User/1.0; +https://openai.com/bot', 'chatgpt-user'];
        yield 'Claude-User' => ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; Claude-User/1.0; +Claude-User@anthropic.com)', 'claude-user'];
        yield 'Perplexity-User' => ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; Perplexity-User/1.0; +https://perplexity.ai/perplexity-user)', 'perplexity-user'];
        yield 'MistralAI-User' => ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; MistralAI-User/1.0; +https://docs.mistral.ai/robots)', 'mistralai-user'];
        yield 'Gemini-Deep-Research' => ['Mozilla/5.0 (compatible; Gemini-Deep-Research; +https://gemini.google/overview/deep-research/)', 'gemini-deep-research'];
        yield 'Google-NotebookLM' => ['Mozilla/5.0 (compatible; Google-NotebookLM)', 'google-notebooklm'];
        yield 'Google-GeminiNotebook' => ['Mozilla/5.0 (compatible; Google-GeminiNotebook)', 'google-gemininotebook'];
    }

    public function testDefaultListMatchesMatomo(): void
    {
        $this->assertSame([
            'chatgpt-user',
            'claude-user',
            'gemini-deep-research',
            'google-gemininotebook',
            'google-notebooklm',
            'mistralai-user',
            'perplexity-user',
        ], require \dirname(__DIR__, 2).'/config/ai_user_agents.php');
    }

    #[DataProvider('ignoredProvider')]
    public function testIgnoresBrowsersAndCrawlers(string $userAgent): void
    {
        $this->assertFalse((new AiBotDetector())->isAiBot($userAgent));
    }

    public static function ignoredProvider(): iterable
    {
        yield 'Chrome' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36'];
        yield 'Safari iOS' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1'];
        yield 'Firefox' => ['Mozilla/5.0 (X11; Linux x86_64; rv:132.0) Gecko/20100101 Firefox/132.0'];
        yield 'leer' => [''];

        // Crawler verwirft Matomo bei recMode=1 – sie sollen gar nicht erst gesendet werden
        yield 'GPTBot' => ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.1; +https://openai.com/gptbot)'];
        yield 'ClaudeBot' => ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)'];
        yield 'PerplexityBot' => ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; PerplexityBot/1.0; +https://perplexity.ai/perplexitybot)'];
        yield 'Googlebot' => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'];
    }

    public function testWordBoundaries(): void
    {
        $detector = new AiBotDetector(userAgents: ['code', 'dot']);

        $this->assertFalse($detector->isAiBot('Mozilla/5.0 VSCode/1.95 Electron'));
        $this->assertFalse($detector->isAiBot('Mozilla/5.0 Anecdotal/1.0'));
        $this->assertTrue($detector->isAiBot('Code/1.0'));
    }

    public function testAdditionalAndIgnoredUserAgents(): void
    {
        $detector = new AiBotDetector(['MyCustomAgent'], ['ChatGPT-User']);

        $this->assertTrue($detector->isAiBot('MyCustomAgent/2.0'));
        $this->assertFalse($detector->isAiBot('Mozilla/5.0 (compatible; ChatGPT-User/1.0)'));
        $this->assertTrue($detector->isAiBot('Mozilla/5.0 (compatible; Claude-User/1.0)'));
    }
}
