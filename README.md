[![Latest Stable Version](https://poser.pugx.org/neosidekick/markdown-for-agents/v/stable)](https://packagist.org/packages/neosidekick/markdown-for-agents)
[![License](https://poser.pugx.org/neosidekick/markdown-for-agents/license)](LICENSE)

# NEOSidekick.MarkdownForAgents

**Markdown rendering for AI agents for the Neos CMS.**

The package makes Neos pages available as clean Markdown while keeping the normal
HTML experience unchanged for browsers. Agents can request Markdown either via a
`.md` URL suffix or through HTTP content negotiation. The package converts most of
your content correctly out of the box; add a dedicated renderer only where the
result needs improvement.

Also check out our just launched [NEOSidekick cms content editing agent v3](https://neosidekick.com/)

## Why Markdown

AI agents usually do not need navigation menus, footers, scripts, cookie banners,
or layout markup. They need the canonical page content in a format that is easy
to parse, quote, summarize, and reason about.

This package provides that agent-facing representation while reusing normal Neos
rendering whenever no dedicated Markdown Fusion prototype exists.

Content that cannot be expressed inline is turned into a link the agent can
follow rather than being dropped: an `<iframe>` becomes a link to its `src`
(labelled with its `title`), and a `<form>` becomes a link to the page that hosts
it — so an agent can still route a user to your contact form. These link labels
are translatable via `Resources/Private/Translations/<locale>/Markdown.xlf`.

## Installation

Require the package in the site package or project:

```bash
composer require neosidekick/markdown-for-agents
```

Then make sure Neos package discovery and caches are refreshed.

```bash
./flow flow:package:rescan
./flow flow:cache:flush
```

### Ask your AI agent of choice to implement the Fusion components

````
SITE BASE URL: https://example.com
SITEMAP: <base>/sitemap.xml

# 1. Verify basic functionality

Fetch the homepage as Markdown and as HTML and compare:

```bash
curl -H "Accept: text/markdown" <base>/
curl <base>/
```

The Markdown response must send `Content-Type: text/markdown` and `Vary: Accept`,
and the body must contain the primary content without header, navigation, footer,
scripts, cookie banners, or explicitly skipped chrome.

# 2. Triage every page type from the sitemap

For each document type, fetch one page as `text/markdown` and `text/html` and
compare. A dedicated `.Markdown` renderer is the EXCEPTION, not the default — most
pages convert well via the fallback. Only intervene where the Markdown is poor.
You can delegate page types to sub-agents.

Look specifically for: empty-alt image noise `![](…)`, tables collapsed into one
line, duplicated link text, and JS-only state messages ("no results for the
selected filters"). Keep screen-reader-only (`.sr-only`) text — it is often the
best label for an agent; drop only pure control noise (e.g. slider scroll buttons)
by marking that specific control with `data-markdown-skip`.

Forms: the package turns every kept `<form>` into a link to its page (a contact
CTA for agents). Classify each one — keep real contact/lead forms, but flag
**technical** forms that are page mechanics rather than content (site search,
result filters, language/region switchers, login, newsletter sign-up) to be hidden.

# 3. Fix, preferring the least invasive option (in this order)

1. Mark non-content and interactive elements with `data-markdown-skip` — filters,
   sliders, carousels, and the technical forms you flagged above.
2. Add or adjust `removeSelectors` in the package settings for cross-cutting noise.
3. Let listing/overview pages paginate normally — the pagination renders as
   Markdown links agents can follow page by page (see the Flowpack.Listable recipe).
4. Only add a dedicated `{Type}.Markdown` prototype when the content hierarchy
   genuinely needs it (articles, structured pages). See this README for the pattern
   and for recipes such as the Flowpack.Listable pagination override. For interactive 
   elements render the context and a link to the live website for users to interact.

Never change the HTML/visitor output — make Markdown-only adjustments.

# 4. Test suite

If the site package has a test suite, add a few basic tests to verify the above.
````

## Robots.txt Content Signals

The package extends `Neos.Seo:RobotsTxt` with content signals for agents:

```text
Content-Signal: ai-train=yes, search=yes, ai-input=yes
```

The values are configured on the `Neos.Neos:Site` node through the added
inspector group **Agents Content Signals** in the SEO tab:

- Allow AI Training
- Allow Search
- Allow AI Input

Each option is stored as a boolean and defaults to enabled. Disabled options are
rendered as `no` in the generated `Content-Signal` directive.

If you cannot see these fields on the homepage node, make sure your homepage
node type includes `Neos.Neos:Site` as a supertype:

```yaml
'Vendor.Site:Document.HomePage':
  superTypes:
    'Neos.Neos:Site': true
```

## llms.txt

The package serves an [`llms.txt`](https://llmstxt.org/) at the site root — e.g.
`https://example.com/llms.txt`, and at each language root such as
`https://example.com/en/llms.txt` for additional content dimensions. It always
responds with `200` and `Content-Type: text/markdown`.

Two properties are added to the `Neos.Neos:Site` node (inspector group **Agents
llms.txt** in the SEO tab):

- **llms.txt content** — a rich-text field for the human-authored part of the file
  (title, summary, descriptive paragraphs). It is stored as HTML and converted to
  Markdown for the response; internal links are resolved to canonical, absolute HTML
  URLs. When left empty, a fallback is rendered from the site node's title and meta
  description.
- **Navigation levels** — an optional dropdown (1–5). When set, the main navigation is
  appended under a `## Pages` heading (translatable) as a nested Markdown link list of
  that many levels, starting at the site node. Menu entries that are pure groupings
  (flyout containers with children but no own page) are rendered as plain labels, their
  pages as links. Leave the dropdown empty to omit the navigation.

So a filled-out `llms.txt` renders as:

```markdown
# Your Title

> Your summary

## Pages

- [About us](https://example.com/about-us)
- Products
  - [Product A](https://example.com/products/a)
```

and an empty one falls back to:

```markdown
# Site Title

The site's meta description.

## Pages

- …
```

As with the content signals above, the properties only appear if the homepage node
type includes `Neos.Neos:Site` as a supertype.

### Discovery

The `/llms.txt` is advertised in two places so agents can find it:

- **robots.txt** gets a comment line pointing to it, next to the Content-Signal
  directive: `# LLM-friendly overview for AI agents: https://example.com/llms.txt`.
- Every **HTML page** adds it to its `Link` header (alongside the page's own Markdown
  alternate): `Link: <https://example.com/llms.txt>; rel="describedby"; type="text/markdown"`.
  (`describedby` is a pragmatic choice — there is no registered link relation for
  llms.txt yet.)

## Testing

Useful checks after installation:

```bash
curl -I -H "Accept: text/markdown" https://example.com/
curl -H "Accept: text/markdown" https://example.com/
curl https://example.com/some/page.md
```

The first response should include `Content-Type: text/markdown` and `Vary:
Accept`. The body should contain the primary page content without global header,
navigation, footer, scripts, cookie banners, or explicitly skipped site chrome.

## Package Architecture

### Requesting Markdown

There are two supported entry points.

#### URL suffix

Every Neos frontend node can be requested with the `.md` suffix:

```bash
curl https://example.com/company/about-us.md
```

The package registers a route for `{node}.md` and maps it to the Neos frontend
node controller with the `markdown` format.

The site root (homepage) has an empty URI path segment, so its Markdown URL is
`/index.md` rather than `/.md` — the latter is a dotfile-like path that common
web servers reject. A dedicated `{node}index.md` route that only matches the site
node serves that direct request:

```bash
curl https://example.com/index.md
```

#### Accept header

Agents can also request the regular URL and express that they prefer Markdown:

```bash
curl -H "Accept: text/markdown" https://example.com/company/about-us
```

The content negotiation middleware switches eligible Neos frontend node requests
from `html` to `markdown` when `text/markdown` is preferred over `text/html`.
`text/plain` is also accepted as an agent fallback. Browser-style requests keep
rendering HTML.

Examples:

```bash
# Markdown wins
curl -H "Accept: text/markdown,text/html;q=0.1" https://example.com/page

# HTML wins
curl -H "Accept: text/html,text/markdown" https://example.com/page
```

Markdown responses use:

```http
Content-Type: text/markdown; charset=utf-8
Vary: Accept
```

### Linking HTML and Markdown

Every page cross-references its two representations through HTTP `Link` headers,
so agents and crawlers can discover the Markdown variant without parsing the HTML
body.

The **HTML** response advertises its Markdown variant as an alternate:

```http
Link: <https://example.com/company/about-us.md>; rel="alternate"; type="text/markdown"
```

The **Markdown** response points back to the canonical HTML page:

```http
Link: <https://example.com/company/about-us>; rel="canonical"
```

Internal links inside the Markdown body resolve to the canonical HTML page, not the
`.md` variant — the same target as the `rel="canonical"` header above. An agent that
wants a linked page as Markdown requests it like any other page, via the Accept
header or the `.md` suffix.

### Rendering Flow

1. A request reaches Neos as format `markdown`.
2. `Root.fusion` selects `NEOSidekick.MarkdownForAgents:DocumentResponse`.
3. The response delegates the body to `NEOSidekick.MarkdownForAgents:MarkdownRenderer`.
4. The renderer looks at the current document node type.
5. It first tries to render a dedicated Fusion prototype with the same type name
   and the `.Markdown` suffix.
6. If no dedicated prototype exists, it renders the normal HTML prototype and
   converts the result to Markdown.

For a document type named:

```text
Example.Site:Document.BlogPost
```

the renderer looks for:

```text
Example.Site:Document.BlogPost.Markdown
```

The `.Markdown` suffix follows Neos Fusion prototype naming conventions and keeps
Markdown renderers next to the document prototypes they specialize.

### Dedicated Markdown Prototypes

Dedicated Markdown prototypes should be used for important content types such as
blog posts, press releases, documentation pages, product pages, and landing pages
where the generic HTML fallback cannot know the intended content hierarchy.

Example:

```fusion
prototype(Vendor.Site:Document.Article.Markdown) < prototype(Neos.Fusion:Component) {
    @context.documentNode = ${node}

    title = ${q(documentNode).property('title')}
    canonicalUri = Neos.Neos:NodeUri {
        node = ${documentNode}
        absolute = true
        format = 'html'
    }
    intro = ${q(documentNode).property('intro')}
    content = Neos.Neos:ContentCollection {
        nodePath = 'main'
    }

    renderer = afx`
# {props.title}

Source: {props.canonicalUri}

{NEOSidekickMarkdown.htmlToMarkdown(props.intro)}

{NEOSidekickMarkdown.htmlToMarkdown(props.content)}
    `
}
```

Recommended structure for dedicated Markdown output:

- Start with one `#` heading matching the page title.
- Include the canonical HTML URL near the top.
- Render only the primary content, not global page chrome.
- Preserve useful metadata such as date, author, category, or source.
- Use meaningful image alt text.
- Internal links are resolved to the canonical HTML page automatically; no manual
  rewriting is needed.

### HTML Fallback

When no `.Markdown` prototype exists, the renderer falls back to the normal HTML
Fusion prototype and converts that HTML with `league/html-to-markdown`. During
conversion the package also turns HTML tables into Markdown tables. `iframe` and
`form` elements are rewritten as followable links rather than stripped (see above).

Before conversion, `HtmlContentSimplifier` removes generic page chrome. The
selectors are configured through package settings as `selector => true` maps, so a
downstream project can add its own selectors or disable a default by overriding its
value to `false`. `navigationSelectors` is a separate map that is only applied when
`removeNavigation` is enabled (the default):

```yaml
NEOSidekick:
  MarkdownForAgents:
    htmlContentSimplifier:
      navigationSelectors:
        'nav': true
        '[role*="navigation"]': true
        '.navigation': true
        '.navi': true
        '.navbox': true
      removeSelectors:
        'header': true
        'footer': true
        'head': true
        '.head': true
        '.header': true
        '.footer': true
        '.cookie-dialog': true
        'svg': true
        'source': true
        # iframe and form are deliberately absent: HtmlContentSimplifier turns them
        # into links. Use data-markdown-skip to drop individual ones.
        'script': true
        'style': true
        '[aria-hidden="true"]': true
        '[data-sidekick-skip]': true
        '[data-neosidekick-skip]': true
        '[data-markdown-skip]': true
```

For example, a site package adds its own selectors and disables one default like so:

```yaml
NEOSidekick:
  MarkdownForAgents:
    htmlContentSimplifier:
      removeSelectors:
        '.sr-only': true
        'footer': false
```

The converter has no rule for definition lists, so it drops the
`<dl>`/`<dt>`/`<dd>` tags but keeps their text — `<dl><dt>Foo</dt><dd>Bar</dd></dl>`
becomes `FooBar`. `tagSeparatorAfter` maps a tag to a string inserted after its
closing tag to keep the parts apart, and the defaults render definition lists as
`Term: Definition`:

```yaml
NEOSidekick:
  MarkdownForAgents:
    htmlContentSimplifier:
      tagSeparatorAfter:
        'dt': ': '
        'dd': ' '
```

Set a tag to an empty string to insert no separator after it.

#### Image source selection

Markdown has no `srcset`, so image conversion writes exactly one URL into
`![alt](url)`. Browser HTML often keeps a small thumbnail in `src` and puts larger
variants into `srcset`; using the raw `src` would make the Markdown image too small
for agents to inspect useful visual content.

Before conversion, the package normalizes every `<img>` `src` according to an
ordered `source => bool` map:

```yaml
NEOSidekick:
  MarkdownForAgents:
    images:
      sourcePreference:
        'data-markdown-src': true
        'srcset': true
        'data-srcset': true
        'data-src': true
        'src': true
      srcsetMaxCandidateWidth: 1600
```

The default preference means:

1. Use `data-markdown-src` when a project renders an explicit agent-friendly image
   URL.
2. Otherwise parse `srcset` and choose the largest width candidate up to
   `srcsetMaxCandidateWidth`. If all candidates are larger, choose the smallest
   larger one. Set `srcsetMaxCandidateWidth` to `0` to always choose the largest
   width candidate.
3. If the image is lazy-loaded, apply the same picking logic to `data-srcset`, then
   fall back to `data-src`.
4. Fall back to `src`.

The map is intentionally override-friendly: disable a default with `false`, use
the conventional `data-markdown-src` attribute, or add another attribute source
when project Fusion already renders a better agent-facing image URL than the
browser-facing `src`:

```yaml
NEOSidekick:
  MarkdownForAgents:
    images:
      sourcePreference:
        'data-original-src': true
        'data-markdown-src': true
        'srcset': true
        'data-srcset': true
        'data-src': true
        'src': true
```

Any enabled source named `srcset` or ending in `-srcset` is parsed as a srcset
candidate list and uses `srcsetMaxCandidateWidth`. This keeps custom lazy-loading
schemes simple: render an additional attribute such as `data-markdown-srcset` in
your project and add it to `sourcePreference`. When an image is wrapped in
`<picture>`, matching `srcset`-like attributes on child `<source>` elements are
considered before those helper elements are removed from the Markdown input.
Commas inside candidate URLs are kept when the candidate has a width or density
descriptor; unsafe candidates such as `data:` URLs are skipped. If a srcset-like
attribute is not valid `srcset`, the converter does not fail; it falls back to the
first useful URL it can read from the value.

Site packages can also explicitly mark non-content chrome with `data-markdown-skip`
when it should be omitted from Markdown, so no extra Fusion component is needed.

Example:

```fusion
renderer = afx`
    <main>
        <article>
            {props.content}
        </article>
        <aside class="sidebar" data-markdown-skip>
            {props.shareLinks}
            {props.latestArticles}
            {props.contactBox}
        </aside>
    </main>
`
```

#### Per-request overrides

The settings above are global defaults. For a single render you can override the
simplifier through the `htmlContentSimplifier` property of
`NEOSidekick.MarkdownForAgents:MarkdownRenderer`. It is merged over the package
defaults, so you change individual entries instead of replacing the whole
configuration — useful when one agent profile needs slightly different output:

```fusion
renderer = NEOSidekick.MarkdownForAgents:MarkdownRenderer {
    type = 'Vendor.Site:Document.Page'
    canonicalUri = Neos.Neos:NodeUri {
        node = ${documentNode}
        absolute = true
        format = 'html'
    }

    htmlContentSimplifier = Neos.Fusion:DataStructure {
        removeLinks = true
        removeSelectors = Neos.Fusion:DataStructure {
            '.pricing-widget' = true   # drop an extra selector for this render
            'footer' = false           # keep a default that is normally removed
        }
        imageSourcePreference = Neos.Fusion:DataStructure {
            'srcset' = false            # use data-markdown-src or src for this render
        }
        srcsetMaxCandidateWidth = 1200
    }
}
```

Accepted keys are `removeSelectors`, `tagSeparatorAfter`, `removeNavigation`,
`removeLinks`, `keepEmptyAltImages`, `imageSourcePreference` and
`srcsetMaxCandidateWidth`; the labels `canonicalUri`, `formNoticeLabel` and
`iframeFallbackLabel` stay on their own properties and always win. Unknown keys are
rejected with an exception, so a typo fails loudly instead of being silently
ignored.

### Eel Helper

The package exposes the helper as `NEOSidekickMarkdown`.

```fusion
markdown = ${NEOSidekickMarkdown.htmlToMarkdown(q(node).property('text'))}
```

The helper accepts the same options as the converter:

```fusion
markdown = ${NEOSidekickMarkdown.htmlToMarkdown(value, {
    removeNavigation: true,
    removeLinks: false,
    keepEmptyAltImages: true,
    srcsetMaxCandidateWidth: 1200
})}
```

Available options. Each one defaults to the matching package setting; passing it
here deliberately overrides that fallback for the single conversion:

- `removeNavigation`: also removes the configured `navigationSelectors` before
  conversion. Defaults to `true` (configurable via
  `htmlContentSimplifier.removeNavigation`).
- `removeLinks`: removes `href` attributes before conversion. Defaults to `false`
  (configurable via `htmlContentSimplifier.removeLinks`).
- `keepEmptyAltImages`: keeps images that have no `alt` text. Defaults to `true`
  (configurable via `htmlContentSimplifier.keepEmptyAltImages`); set it to `false`
  to drop such images as decorative noise. Agents can still fetch and analyse a
  kept image, which is why keeping them is the default.
- `removeSelectors`: a `selector => bool` map merged over the configured
  `htmlContentSimplifier.removeSelectors`; add a selector with `true` or disable a
  default with `false`.
- `tagSeparatorAfter`: a `tag => separator` map merged over the configured
  `htmlContentSimplifier.tagSeparatorAfter`; inserts the given string after each
  closing tag (e.g. `dt: ': '`), or an empty string to insert nothing.
- `imageSourcePreference`: a `source => bool` map merged over the configured
  `images.sourcePreference`; sources named `srcset` or ending in `-srcset` are
  parsed as candidate lists, other sources are treated as image attribute names.
- `srcsetMaxCandidateWidth`: maximum preferred width when selecting a width
  candidate from a srcset-like source. `0` means "choose the largest candidate".

Unknown option keys are rejected with an exception, so a typo fails loudly
instead of being silently ignored.

### Working with community packages

Third-party rendering packages occasionally produce output that is fine for
browsers but wrong for agents. Solve this with a small Fusion override that is
gated on `request.format == 'markdown'`, so the HTML representation stays
untouched.

#### Full lists instead of pagination (Flowpack.Listable)

Agents consume the whole document at once, so a paginated listing must render
every item — otherwise an agent only ever sees the first page. Lift the page size
for the Markdown format by overriding `Flowpack.Listable:PaginatedCollection`:

```fusion
prototype(Flowpack.Listable:PaginatedCollection) {
    itemsPerPage.@process.renderAllForMarkdown = ${request.format == 'markdown' ? 100000 : value}
}
```

Every overview that builds on `PaginatedCollection` inherits this, so a single
override makes all listing pages complete for agents while browsers keep their
pagination. The same `request.format == 'markdown'` gate works for other
community prototypes — e.g. forcing `absolute` URIs or hiding interactive widgets.

### Error Handling

Markdown rendering should never expose a Neos exception page as Markdown.

If an explicit `.Markdown` prototype throws an exception, the renderer logs the
failure and falls back to converted HTML when `fallbackToHtml` is enabled.

If HTML fallback rendering fails, or if the fallback output looks like a Neos
exception page, the package returns a small Markdown error document containing
the page title and canonical URL instead of leaking the HTML error output.

### Test Suite

The package ships unit tests that run inside a Neos distribution, the same way
the GitHub Actions pipeline runs them. From the distribution root:

```bash
bin/phpunit -c DistributionPackages/NEOSidekick.MarkdownForAgents/Tests/UnitTests.xml
```

The workflow additionally runs PSR-12 code style (`phpcs`) and PHPStan static
analysis across the supported PHP and Neos versions.

## Open Questions

### Absolute asset URIs

Node and inline link URIs are made absolute and resolved as canonical HTML under
the `markdown` format (via a `Neos.Neos:NodeUri` override and a
`Neos.Neos:ConvertUris` subclass), but **asset URIs are not** made absolute. `LinkingService::resolveAssetUri()` has no `absolute` flag and simply
returns whatever the resource publishing target yields (host-relative by
default), and projects often re-route assets through their own controller, so
there is no single place the package can hook. Deciding where and how to force
absolute asset URIs for agents — package default vs. per-project resource/target
configuration — is still open.
