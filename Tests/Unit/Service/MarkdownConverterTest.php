<?php

declare(strict_types=1);

namespace NEOSidekick\MarkdownForAgents\Tests\Unit\Service;

use NEOSidekick\MarkdownForAgents\Dto\ConversionOptions;
use NEOSidekick\MarkdownForAgents\Service\HtmlContentSimplifier;
use NEOSidekick\MarkdownForAgents\Service\MarkdownConverter;
use Neos\Flow\I18n\Translator;
use Neos\Flow\Tests\UnitTestCase;

final class MarkdownConverterTest extends UnitTestCase
{
    /**
     * Fixed, test-owned selector sets. They are injected into the configuration
     * properties in setUp() instead of being passed in directly, so the selectors
     * travel the same path that @Flow\InjectConfiguration uses at runtime.
     *
     * @var array<string, bool>
     */
    private const REMOVE_SELECTORS = [
        'header' => true,
        'footer' => true,
        'head' => true,
        '.head' => true,
        '.header' => true,
        '.footer' => true,
        '.cookie-dialog' => true,
        'svg' => true,
        'source' => true,
        'script' => true,
        'style' => true,
        '[aria-hidden="true"]' => true,
        '[data-sidekick-skip]' => true,
        '[data-neosidekick-skip]' => true,
        '[data-markdown-skip]' => true,
    ];

    /**
     * @var array<string, bool>
     */
    private const NAVIGATION_SELECTORS = [
        'nav' => true,
        '[role*="navigation"]' => true,
        '.navigation' => true,
        '.navi' => true,
        '.navbox' => true,
    ];

    private HtmlContentSimplifier $simplifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->simplifier = new HtmlContentSimplifier();
        $this->inject($this->simplifier, 'removeSelectors', self::REMOVE_SELECTORS);
        $this->inject($this->simplifier, 'navigationSelectors', self::NAVIGATION_SELECTORS);
    }

    /**
     * @test
     */
    public function convertsMainHtmlContentAndRemovesPageChrome(): void
    {
        $html = <<<'HTML'
<!doctype html>
<html>
    <head><title>Ignored head</title><style>.hidden { color: red; }</style></head>
    <body>
        <header>Global Header Menu</header>
        <nav>Global Navigation</nav>
        <main>
            <h1>Main Heading</h1>
            <p>Useful <a href="/target">content link</a>.</p>
            <ul><li>First point</li><li>Second point</li></ul>
        </main>
        <script>alert('tracking');</script>
        <footer>Global Footer Links</footer>
    </body>
</html>
HTML;

        $markdown = $this->createConverter()->convert($html);

        self::assertStringContainsString('Main Heading', $markdown);
        self::assertStringContainsString('content link', $markdown);
        self::assertStringContainsString('First point', $markdown);
        self::assertStringNotContainsString('Global Header Menu', $markdown);
        self::assertStringNotContainsString('Global Navigation', $markdown);
        self::assertStringNotContainsString('Global Footer Links', $markdown);
        self::assertStringNotContainsString('tracking', $markdown);
        self::assertStringNotContainsString('<header', $markdown);
        self::assertStringNotContainsString('<footer', $markdown);
    }

    /**
     * @test
     */
    public function convertsIframesToLinksUsingTheirTitle(): void
    {
        $html = <<<'HTML'
<html>
    <body>
        <main>
            <h1>Video Page</h1>
            <iframe src="https://www.youtube.com/embed/abc123" title="Promo video"></iframe>
            <p>Description below the player.</p>
        </main>
    </body>
</html>
HTML;

        $markdown = $this->createConverter()->convert($html);

        self::assertStringContainsString('Video Page', $markdown);
        self::assertStringContainsString('Description below the player.', $markdown);
        self::assertStringContainsString('[Promo video](https://www.youtube.com/embed/abc123)', $markdown);
        self::assertStringNotContainsString('<iframe', $markdown);
    }

    /**
     * @test
     */
    public function usesFallbackLabelForIframesWithoutATitle(): void
    {
        $html = '<html><body><main><iframe src="https://maps.example.test/embed"></iframe></main></body></html>';

        $markdown = $this->createConverter()->convert($html, ConversionOptions::fromArray(['iframeFallbackLabel' => 'Eingebetteter Inhalt']));

        self::assertStringContainsString('[Eingebetteter Inhalt](https://maps.example.test/embed)', $markdown);
    }

    /**
     * @test
     */
    public function dropsIframesWithoutAFollowableSrc(): void
    {
        $html = <<<'HTML'
<html>
    <body>
        <main>
            <h1>Page</h1>
            <iframe title="No source"></iframe>
            <iframe src="about:blank" title="Blank frame"></iframe>
            <p>Body text.</p>
        </main>
    </body>
</html>
HTML;

        $markdown = $this->createConverter()->convert($html);

        self::assertStringContainsString('Page', $markdown);
        self::assertStringContainsString('Body text.', $markdown);
        self::assertStringNotContainsString('No source', $markdown);
        self::assertStringNotContainsString('Blank frame', $markdown);
        self::assertStringNotContainsString('about:blank', $markdown);
    }

    /**
     * @test
     */
    public function convertsHtmlTablesToMarkdownTables(): void
    {
        $html = <<<'HTML'
<html>
    <body>
        <main>
            <table>
                <thead><tr><th>Feature</th><th>Included</th></tr></thead>
                <tbody>
                    <tr><td>Compatibility</td><td>Yes</td></tr>
                    <tr><td>Solar integration</td><td>Yes</td></tr>
                </tbody>
            </table>
        </main>
    </body>
</html>
HTML;

        $markdown = $this->createConverter()->convert($html);

        self::assertStringContainsString('| Feature | Included |', $markdown);
        self::assertStringContainsString('| Compatibility | Yes |', $markdown);
        self::assertStringContainsString('| Solar integration | Yes |', $markdown);
    }

    /**
     * @test
     */
    public function keepsImagesWithoutAltTextByDefault(): void
    {
        $html = '<html><body><main><h1>Partners</h1><img src="https://example.test/logo-1.png" alt="" /></main></body></html>';

        $markdown = $this->createConverter()->convert($html);

        self::assertStringContainsString('Partners', $markdown);
        self::assertStringContainsString('logo-1.png', $markdown);
    }

    /**
     * @test
     */
    public function convertsImagesUsingThePreferredSrcsetCandidate(): void
    {
        $html = <<<'HTML'
<html>
    <body>
        <main>
            <img
                src="https://example.test/image-100.jpg"
                srcset="https://example.test/image-400.jpg 400w, https://example.test/image-1200.jpg 1200w, https://example.test/image-2400.jpg 2400w"
                alt="Campaign visual"
            />
        </main>
    </body>
</html>
HTML;

        $markdown = $this->createConverter()->convert($html);

        self::assertStringContainsString('![Campaign visual](https://example.test/image-1200.jpg)', $markdown);
        self::assertStringNotContainsString('image-100.jpg', $markdown);
    }

    /**
     * @test
     */
    public function dropsImagesWithoutAltTextWhenKeepEmptyAltImagesIsDisabled(): void
    {
        $html = <<<'HTML'
<html>
    <body>
        <main>
            <h1>Partners</h1>
            <img src="https://example.test/logo-1.png" alt="" />
            <img src="https://example.test/logo-2.png" />
            <img src="https://example.test/diagram.png" alt="Network diagram" />
        </main>
    </body>
</html>
HTML;

        $markdown = $this->createConverter(false)->convert($html);

        self::assertStringContainsString('Partners', $markdown);
        self::assertStringContainsString('![Network diagram](https://example.test/diagram.png)', $markdown);
        self::assertStringNotContainsString('logo-1.png', $markdown);
        self::assertStringNotContainsString('logo-2.png', $markdown);
    }

    /**
     * @test
     */
    public function convertsContactFormsToALinkToThePageUrl(): void
    {
        $html = <<<'HTML'
<html>
    <body>
        <main>
            <h1>Contact us</h1>
            <p>Ask us anything.</p>
            <form action="/submit">
                <label>Vorname*</label><input name="firstname" />
                <label>Business E-Mail*</label><input name="email" />
                <button type="submit">Senden</button>
            </form>
        </main>
    </body>
</html>
HTML;

        $markdown = $this->createConverter()->convert($html, ConversionOptions::fromArray([
            'canonicalUri' => 'https://example.test/contact',
            'formNoticeLabel' => 'A form can be found at',
        ]));

        self::assertStringContainsString('Ask us anything.', $markdown);
        self::assertStringContainsString('[A form can be found at](https://example.test/contact)', $markdown);
        self::assertStringNotContainsString('Vorname', $markdown);
        self::assertStringNotContainsString('Business E-Mail', $markdown);
    }

    /**
     * @test
     */
    public function translatesFormAndIframeLabelsFromThePackageCatalogWhenNoneAreProvided(): void
    {
        $translator = $this->createMock(Translator::class);
        $translator->method('translateById')->willReturnMap([
            ['formNotice', [], null, null, 'Markdown', 'NEOSidekick.MarkdownForAgents', 'Formular auf dieser Seite'],
            ['iframeFallback', [], null, null, 'Markdown', 'NEOSidekick.MarkdownForAgents', 'Eingebetteter Inhalt'],
        ]);
        $this->inject($this->simplifier, 'translator', $translator);

        $html = <<<'HTML'
<html><body><main>
    <iframe src="https://maps.example.test/embed"></iframe>
    <form action="/submit"><input name="email" /></form>
</main></body></html>
HTML;

        $markdown = $this->createConverter()->convert($html, ConversionOptions::fromArray(['canonicalUri' => 'https://example.test/contact']));

        self::assertStringContainsString('[Formular auf dieser Seite](https://example.test/contact)', $markdown);
        self::assertStringContainsString('[Eingebetteter Inhalt](https://maps.example.test/embed)', $markdown);
    }

    /**
     * @test
     */
    public function fallsBackToTheEnglishLabelWhenTheCatalogHasNoTranslation(): void
    {
        $translator = $this->createMock(Translator::class);
        $translator->method('translateById')->willReturn(null);
        $this->inject($this->simplifier, 'translator', $translator);

        $html = '<html><body><main><form action="/submit"><input name="email" /></form></main></body></html>';

        $markdown = $this->createConverter()->convert($html, ConversionOptions::fromArray(['canonicalUri' => 'https://example.test/contact']));

        self::assertStringContainsString('[A form can be found at](https://example.test/contact)', $markdown);
    }

    /**
     * @test
     */
    public function rendersOneLinkPerFormWithoutDeduping(): void
    {
        $html = <<<'HTML'
<html>
    <body>
        <main>
            <h1>Multiple forms</h1>
            <form action="/a"><input name="a" /></form>
            <p>Between the forms.</p>
            <form action="/b"><input name="b" /></form>
        </main>
    </body>
</html>
HTML;

        $markdown = $this->createConverter()->convert($html, ConversionOptions::fromArray(['canonicalUri' => 'https://example.test/page']));

        self::assertSame(2, substr_count($markdown, '(https://example.test/page)'));
        self::assertStringContainsString('Between the forms.', $markdown);
    }

    /**
     * @test
     */
    public function doesNotLinkFormsExplicitlyMarkedWithDataMarkdownSkip(): void
    {
        $html = <<<'HTML'
<html>
    <body>
        <main>
            <h1>Search</h1>
            <form action="/search" data-markdown-skip>
                <input name="q" />
            </form>
            <p>Results follow.</p>
        </main>
    </body>
</html>
HTML;

        $markdown = $this->createConverter()->convert($html, ConversionOptions::fromArray(['canonicalUri' => 'https://example.test/search']));

        self::assertStringContainsString('Search', $markdown);
        self::assertStringContainsString('Results follow.', $markdown);
        self::assertStringNotContainsString('example.test/search', $markdown);
    }

    /**
     * @test
     */
    public function dropsFormsWhenNoPageUrlIsAvailable(): void
    {
        $html = '<html><body><main><h1>Contact</h1><form action="/submit"><input name="email" /></form></main></body></html>';

        $markdown = $this->createConverter()->convert($html);

        self::assertStringContainsString('Contact', $markdown);
        self::assertStringNotContainsString('](', $markdown);
    }

    /**
     * @test
     */
    public function rendersPaginationAsCleanMarkdownLinks(): void
    {
        // Mirrors Flowpack.Listable pagination, incl. an icon-only "next" link whose <svg> was removed.
        $html = <<<'HTML'
<html>
    <body>
        <main>
            <ul class="pagination">
                <li><a>1</a></li>
                <li><a href="/news?currentPage=2">2</a></li>
                <li><a href="/news?currentPage=3">3</a></li>
                <li class="next"><a href="/news?currentPage=2" rel="next" title="Next"></a></li>
            </ul>
        </main>
    </body>
</html>
HTML;

        $markdown = $this->createConverter()->convert($html);

        self::assertStringContainsString('[2](/news?currentPage=2)', $markdown);
        self::assertStringContainsString('[3](/news?currentPage=3)', $markdown);
        self::assertStringContainsString('[Next](/news?currentPage=2)', $markdown);
        self::assertStringNotContainsString('<a', $markdown);
        self::assertStringNotContainsString('[](', $markdown);
    }

    /**
     * @test
     */
    public function unwrapsAnchorsWithoutAFollowableHrefButKeepsFragmentLinks(): void
    {
        $html = '<html><body><main><p><a>Plain</a> and <a name="anchor">Named</a> and <a href="#section">Jump</a>.</p></main></body></html>';

        $markdown = $this->createConverter()->convert($html);

        self::assertStringContainsString('Plain', $markdown);
        self::assertStringContainsString('Named', $markdown);
        self::assertStringContainsString('[Jump](#section)', $markdown);
        self::assertStringNotContainsString('<a', $markdown);
    }

    /**
     * @test
     */
    public function collapsesBlockLevelAnchorLabelsIntoSingleLineMarkdownLinks(): void
    {
        $html = <<<'HTML'
<html>
    <body>
        <main>
            <a href="/organisation/finanztransparenz">
                <div>
                    <p>30.362</p>
                    <p>Tassen Kaffee getrunken</p>
                    <span>Finanztransparenz</span>
                </div>
            </a>
        </main>
    </body>
</html>
HTML;

        $markdown = $this->createConverter()->convert($html);

        self::assertStringContainsString(
            '[30.362 Tassen Kaffee getrunken Finanztransparenz](/organisation/finanztransparenz)',
            $markdown
        );
        self::assertStringNotContainsString("[30.362\n\n", $markdown);
        self::assertStringNotContainsString("getrunken\n\nFinanztransparenz", $markdown);
    }

    /**
     * @test
     */
    public function collapsesBlockLinkLabelsWithoutBreakingNestedImageMarkdown(): void
    {
        $html = <<<'HTML'
<html>
    <body>
        <main>
            <a href="/article">
                <img src="/cover.jpg" alt="Cover">
                <p>Read more</p>
            </a>
        </main>
    </body>
</html>
HTML;

        $markdown = $this->createConverter()->convert($html);

        self::assertStringContainsString('[![Cover](/cover.jpg) Read more](/article)', $markdown);
    }

    /**
     * @test
     */
    public function collapsesLinkedImageHeadingCardsIntoPlainLinkLabels(): void
    {
        $html = <<<'HTML'
<html>
    <body>
        <main>
            <a href="/bundesregierung">
                <img src="/team.jpg" alt="Beate Meinl-Reisinger Christoph Wiederkehr Sepp Schellhorn NEOS">
                <h3>Unsere Regierungsmitglieder</h3>
                <p>NEOS stellt zwei zentrale Stimmen in der Bundesregierung.</p>
                <span>Lerne unsere Regierungsmitglieder kennen</span>
            </a>
        </main>
    </body>
</html>
HTML;

        $markdown = $this->createConverter()->convert($html);

        self::assertStringContainsString(
            '[![Beate Meinl-Reisinger Christoph Wiederkehr Sepp Schellhorn NEOS](/team.jpg) Unsere Regierungsmitglieder NEOS stellt zwei zentrale Stimmen in der Bundesregierung. Lerne unsere Regierungsmitglieder kennen](/bundesregierung)',
            $markdown
        );
        self::assertStringNotContainsString("[![Beate Meinl-Reisinger\n\n###", $markdown);
        self::assertStringNotContainsString('### Unsere Regierungsmitglieder', $markdown);
    }

    /**
     * @test
     */
    public function collapsesHeadingsInsideBlockLinkLabelsIntoPlainText(): void
    {
        $html = <<<'HTML'
<html>
    <body>
        <main>
            <a href="https://www.neos.eu/mitmachen/jobs">
                <h3>Jobs bei Neos</h3>
                <p>Wir machen nicht nur Politik für Freiheit, Fortschritt und Gerechtigkeit.</p>
                <span>Bewirb dich jetzt!</span>
            </a>
        </main>
    </body>
</html>
HTML;

        $markdown = $this->createConverter()->convert($html);

        self::assertStringContainsString(
            '[Jobs bei Neos Wir machen nicht nur Politik für Freiheit, Fortschritt und Gerechtigkeit. Bewirb dich jetzt!](https://www.neos.eu/mitmachen/jobs)',
            $markdown
        );
        self::assertStringNotContainsString('### Jobs bei Neos', $markdown);
        self::assertStringNotContainsString("[\n\n###", $markdown);
    }

    /**
     * @test
     */
    public function keepsHashCharactersInsideInlineLinkLabels(): void
    {
        $html = '<html><body><main><a href="/jobs">C# developer role</a></main></body></html>';

        $markdown = $this->createConverter()->convert($html);

        self::assertStringContainsString('[C# developer role](/jobs)', $markdown);
    }

    /**
     * @test
     */
    public function removesElementsMarkedAsSidekickSkip(): void
    {
        $html = <<<'HTML'
<html>
    <body>
        <main>
            <h1>Visible</h1>
            <p data-sidekick-skip>Skip this</p>
            <p data-neosidekick-skip>Skip this too</p>
            <p data-markdown-skip>Skip markdown only</p>
        </main>
    </body>
</html>
HTML;

        $markdown = $this->createConverter()->convert($html);

        self::assertStringContainsString('Visible', $markdown);
        self::assertStringNotContainsString('Skip this', $markdown);
        self::assertStringNotContainsString('Skip this too', $markdown);
        self::assertStringNotContainsString('Skip markdown only', $markdown);
    }

    /**
     * @test
     */
    public function keepsComplementaryAsideContentByDefault(): void
    {
        $html = <<<'HTML'
<html>
    <body>
        <main>
            <div class="sidebar__wrapper">
                <section class="content">
                    <h1>Press Article</h1>
                    <p>Important article body.</p>
                </section>
                <aside class="sidebar">
                    <h2>Related article context</h2>
                    <p>Complementary but useful content.</p>
                </aside>
            </div>
        </main>
    </body>
</html>
HTML;

        $markdown = $this->createConverter()->convert($html);

        self::assertStringContainsString('Press Article', $markdown);
        self::assertStringContainsString('Important article body.', $markdown);
        self::assertStringContainsString('Related article context', $markdown);
        self::assertStringContainsString('Complementary but useful content.', $markdown);
    }

    /**
     * @test
     */
    public function removesSiteChromeMarkedAsSidekickSkipFromAside(): void
    {
        $html = <<<'HTML'
<html>
    <body>
        <main>
            <div class="sidebar__wrapper">
                <section>
                    <h1>Article content stays visible</h1>
                    <p>Important article body.</p>
                </section>
                <aside class="sidebar" data-neosidekick-skip>
                    <p>Jetzt teilen</p>
                    <ul class="social-menu">
                        <li><a href="https://www.facebook.com/sharer/sharer.php">Facebook</a></li>
                    </ul>
                    <p>Aktuellste Artikel</p>
                    <p>Pressekontakt</p>
                </aside>
            </div>
        </main>
    </body>
</html>
HTML;

        $markdown = $this->createConverter()->convert($html);

        self::assertStringContainsString('Article content stays visible', $markdown);
        self::assertStringContainsString('Important article body.', $markdown);
        self::assertStringNotContainsString('Jetzt teilen', $markdown);
        self::assertStringNotContainsString('Facebook', $markdown);
        self::assertStringNotContainsString('facebook.com/sharer', $markdown);
        self::assertStringNotContainsString('Aktuellste Artikel', $markdown);
        self::assertStringNotContainsString('Pressekontakt', $markdown);
    }

    /**
     * @test
     */
    public function removesSelectorsConfiguredForTheSimplifier(): void
    {
        $html = <<<'HTML'
<html>
    <body>
        <main>
            <h1>Visible Content</h1>
            <div class="agent-hidden">Configured hidden content</div>
            <div class="agent-navigation">Configured navigation</div>
        </main>
    </body>
</html>
HTML;

        $simplifier = new HtmlContentSimplifier();
        $this->inject($simplifier, 'removeSelectors', ['.agent-navigation' => true, '.agent-hidden' => true]);
        $converter = new MarkdownConverter($simplifier);

        $markdown = $converter->convert($html);

        self::assertStringContainsString('Visible Content', $markdown);
        self::assertStringNotContainsString('Configured hidden content', $markdown);
        self::assertStringNotContainsString('Configured navigation', $markdown);
    }

    /**
     * @test
     */
    public function selectorMapAllowsUnsettingASelectorWithFalse(): void
    {
        $html = '<html><body><main><h1>Visible</h1><div class="keep-me">Kept content</div></main></body></html>';

        $simplifier = new HtmlContentSimplifier();
        $this->inject($simplifier, 'removeSelectors', ['.keep-me' => false, '.drop-me' => true]);
        $converter = new MarkdownConverter($simplifier);

        $markdown = $converter->convert($html);

        self::assertStringContainsString('Kept content', $markdown);
    }

    /**
     * @test
     */
    public function navigationSelectorsAreRemovedByDefaultButKeptWhenDisabled(): void
    {
        $html = '<html><body><main><h1>Visible</h1><div class="agent-nav">Nav block</div></main></body></html>';

        $simplifier = new HtmlContentSimplifier();
        $this->inject($simplifier, 'navigationSelectors', ['.agent-nav' => true]);
        $converter = new MarkdownConverter($simplifier);

        self::assertStringNotContainsString('Nav block', $converter->convert($html));
        self::assertStringContainsString('Nav block', $converter->convert($html, ConversionOptions::fromArray(['removeNavigation' => false])));
    }

    /**
     * @test
     */
    public function removesLinkHrefsWhenRequestedWithoutFailingOnHrefLessAnchors(): void
    {
        $html = '<html><body><main><p><a href="/target">link text</a> and <a name="anchor">named anchor</a>.</p></main></body></html>';

        $markdown = $this->createConverter()->convert($html, ConversionOptions::fromArray(['removeLinks' => true]));

        self::assertStringContainsString('link text', $markdown);
        self::assertStringContainsString('named anchor', $markdown);
        self::assertStringNotContainsString('/target', $markdown);
    }

    /**
     * @test
     */
    public function removeNavigationSettingActsAsDefaultButTheOptionStillWins(): void
    {
        $html = '<html><body><main><h1>Visible</h1><div class="agent-nav">Nav block</div></main></body></html>';

        $simplifier = new HtmlContentSimplifier();
        $this->inject($simplifier, 'navigationSelectors', ['.agent-nav' => true]);
        $this->inject($simplifier, 'removeNavigation', false);
        $converter = new MarkdownConverter($simplifier);

        // The setting is the fallback: navigation is kept when no option is passed.
        self::assertStringContainsString('Nav block', $converter->convert($html));
        // A per-call option overrides the setting.
        self::assertStringNotContainsString('Nav block', $converter->convert($html, ConversionOptions::fromArray(['removeNavigation' => true])));
    }

    /**
     * @test
     */
    public function removeLinksSettingActsAsDefaultButTheOptionStillWins(): void
    {
        $html = '<html><body><main><p><a href="/target">link text</a></p></main></body></html>';

        $simplifier = new HtmlContentSimplifier();
        $this->inject($simplifier, 'removeLinks', true);
        $converter = new MarkdownConverter($simplifier);

        // The setting is the fallback: hrefs are stripped when no option is passed.
        self::assertStringNotContainsString('/target', $converter->convert($html));
        // A per-call option overrides the setting.
        self::assertStringContainsString('/target', $converter->convert($html, ConversionOptions::fromArray(['removeLinks' => false])));
    }

    /**
     * @test
     */
    public function perCallRemoveSelectorsExtendTheConfiguredDefaults(): void
    {
        $html = <<<'HTML'
<html><body><main>
    <h1>Visible</h1>
    <div class="default-drop">Configured default noise</div>
    <div class="per-call-drop">Per-call noise</div>
</main></body></html>
HTML;

        $simplifier = new HtmlContentSimplifier();
        $this->inject($simplifier, 'removeSelectors', ['.default-drop' => true]);
        $converter = new MarkdownConverter($simplifier);

        $markdown = $converter->convert($html, ConversionOptions::fromArray(['removeSelectors' => ['.per-call-drop' => true]]));

        self::assertStringContainsString('Visible', $markdown);
        self::assertStringNotContainsString('Configured default noise', $markdown);
        self::assertStringNotContainsString('Per-call noise', $markdown);
    }

    /**
     * @test
     */
    public function perCallRemoveSelectorsCanDisableAConfiguredDefaultWithFalse(): void
    {
        $html = '<html><body><main><h1>Visible</h1><div class="default-drop">Configured default noise</div></main></body></html>';

        $simplifier = new HtmlContentSimplifier();
        $this->inject($simplifier, 'removeSelectors', ['.default-drop' => true]);
        $converter = new MarkdownConverter($simplifier);

        $markdown = $converter->convert($html, ConversionOptions::fromArray(['removeSelectors' => ['.default-drop' => false]]));

        self::assertStringContainsString('Configured default noise', $markdown);
    }

    /**
     * @test
     */
    public function insertsConfiguredSeparatorsBetweenDefinitionListTermsAndDefinitions(): void
    {
        $html = '<html><body><main><dl><dt>Foo</dt><dd>Bar</dd><dt>Baz</dt><dd>Qux</dd></dl></main></body></html>';

        $simplifier = new HtmlContentSimplifier();
        $this->inject($simplifier, 'tagSeparatorAfter', ['dt' => ': ', 'dd' => ' ']);
        $converter = new MarkdownConverter($simplifier);

        $markdown = $converter->convert($html);

        self::assertStringContainsString('Foo: Bar', $markdown);
        self::assertStringContainsString('Baz: Qux', $markdown);
        self::assertStringNotContainsString('FooBar', $markdown);
        self::assertStringNotContainsString('BarBaz', $markdown);
    }

    /**
     * @test
     */
    public function leavesDefinitionListsGluedWhenNoTagSpacingIsConfigured(): void
    {
        $html = '<html><body><main><dl><dt>Foo</dt><dd>Bar</dd></dl></main></body></html>';

        $simplifier = new HtmlContentSimplifier();
        $converter = new MarkdownConverter($simplifier);

        self::assertStringContainsString('FooBar', $converter->convert($html));
    }

    private function createConverter(bool $keepEmptyAltImages = true): MarkdownConverter
    {
        $this->inject($this->simplifier, 'keepEmptyAltImages', $keepEmptyAltImages);

        return new MarkdownConverter($this->simplifier);
    }
}
