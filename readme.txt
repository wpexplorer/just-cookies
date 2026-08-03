=== Just Cookies – Cookie Consent & Embed Blocking ===
Contributors: wpexplorer
Donate link: https://www.wpexplorer.com/donate/
Tags: cookie consent, cookie banner, cookie notice, cookies, gdpr
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Cookie consent and third-party embed blocking. Self-hosted, no third-party service, no account required.

== Description ==

A cookie banner that names specific cookies instead of generic boilerplate, and holds third-party embeds and trackers until visitors accept them.

Everything runs on your own server. There is no external service, no subscription, no page-view limit and no account to create.

Rather than scanning your site — which only catches what a logged-out visitor happens to trigger on one page — we research what each supported service sets, from its own documentation and source, and build that into the plugin. You switch on the services your site uses and the disclosure table is built from those.

That research reflects what we found while building each release, so treat the tables as a starting point to check against your own site. If you find a cookie we should be disclosing, tell us and we will add it.

= Cookie banner and disclosures =

* Builds the disclosure table from the services you switch on, naming each cookie, its source, its purpose and how long it lasts.
* Checks which supported plugins are active and offers their cookies, or lets you choose them yourself.
* Covers optional cookies a plugin only sets in some configurations — auto-detect reads WooCommerce's own order attribution switch, and choosing plugins by hand gives you a switch of your own, since that feature can differ from site to site.
* Includes built-in entries for Cloudflare, Cloudflare Turnstile, reCAPTCHA, Stripe and WordPress login cookies.
* Shows a simple acknowledgement instead of accept/reject when the site only sets necessary cookies.
* Lets you set the banner text, layout, position, colors and consent lifetime.
* Adds an optional closing section to the preferences popup — your own heading and text, HTML allowed, for a contact address or a link to a fuller policy.
* Adds an optional floating button so visitors can reopen their choices later, with its own label, colors and stacking order so it can sit under your menus, popups and modals.

= Blocking =

* Holds embeds behind a placeholder until the visitor accepts them. Switch on the services your site uses — YouTube, Vimeo, SoundCloud, Spotify, Dailymotion, Twitch, Wistia, Mixcloud, Bandcamp, Loom, Calendly, Typeform, and Google Maps, Calendar and Docs.
* Moves the embed's address out of the `src` attribute on the server, so the browser makes no request and the provider sets no cookie until consent.
* Catches lightbox video links too — the kind a theme opens in a popup, where there is no iframe in the page to rewrite. The click is held and a prompt names the service instead; accept, and the video opens straight away.
* Asks per service, so loading a video does not also accept maps — each provider is accepted separately and can be toggled on its own in the preferences popup.
* Rewrites YouTube to youtube-nocookie.com and adds Do Not Track to Vimeo.
* Optionally defers analytics scripts (Google Analytics, Tag Manager, Meta Pixel, Hotjar, Clarity and more) until consent.
* Deletes the analytics cookies again if a visitor changes their mind, so withdrawing consent removes the identifier rather than only stopping new ones.

= Policy pages =

* Tells you whether you have a cookie policy, privacy policy and terms page.
* Creates the missing pages for you as drafts — a cookie policy built on the disclosure shortcode so it stays current, and a terms stub. Read them over and publish when you are happy; the banner links to each one only once it is published.
* Finds pages you already have, checking the common slugs for each — /terms-of-service/, /terms/, /terms-and-conditions/ and so on — so an existing page is linked rather than duplicated.
* Links whichever of the three you choose from the banner, and never links to a page that is missing or unpublished — so a site already listing them in its footer can leave them out of the notice.

= Multisite =

Network activate to configure consent once for the whole network, while each site keeps control of its own appearance. Ideal where sites share a domain and therefore share one consent cookie. Appearance can be locked down too, so the banner is identical everywhere and site administrators have no settings screen at all.

= Supported plugin integrations =

WooCommerce, Easy Digital Downloads, Simple Membership, BuddyPress, LifterLMS, LearnDash, Events Manager, Polylang, WPML, Jetpack, AffiliateWP and Post Views Counter. Developers can register more with the `just_cookies_integrations` filter.

If one of these sets a cookie we are not disclosing, or you would like another plugin supported, let us know on the [support forum](https://wordpress.org/support/plugin/just-cookies/) and we will look at adding it.

= Shortcodes =

* `[just_cookies_settings_link]` — a link or button that reopens the preferences popup.
* `[just_cookies_table]` — the full cookie disclosure table for your cookie policy page, which updates itself as settings change. Each table is headed with the category it covers; add `titles="no"` if your page already introduces them, or `title_tag="h4"` to fit your page's heading order (`just_cookies_table_heading_tag` sets it site-wide).

= For developers =

The plugin is built to be extended rather than forked. Eighteen filters cover the parts a theme or another plugin is most likely to need:

* **Disclosures** — add or change cookie rows with `just_cookies_necessary_rows`, `just_cookies_analytics_rows` and `just_cookies_embed_rows`, or register a whole plugin with `just_cookies_integrations` so its cookies are disclosed automatically whenever it is active.
* **Embed blocking** — `just_cookies_embed_content_filters` changes where embeds are looked for, `just_cookies_embed_providers` changes which services are offered on the settings screen, `just_cookies_provider_labels` renames them in the preferences popup, and `just_cookies_lightbox_selectors` adds the class your theme puts on a lightbox link.
* **Analytics gating** — `just_cookies_analytics_buffer_hooks` changes where trackers are looked for, `just_cookies_analytics_script_patterns` and `just_cookies_analytics_inline_patterns` change what counts as one, and `just_cookies_analytics_clear_cookies` changes which cookies are deleted when consent is withdrawn.
* **The banner itself** — `just_cookies_consent_config` hands you the complete consent configuration before it reaches the browser, so categories, wording and behavior can all be adjusted, and `just_cookies_default_url` and `just_cookies_policy_slugs` change how a policy page is found and resolved.
* **Multisite** — `just_cookies_per_site_keys` controls which settings a subsite is allowed to override, and `just_cookies_cookie_name` renames the consent cookie. Sites sharing a domain share one consent record by design, so change that name only before a site is live — afterwards it orphans every stored consent and everyone is asked again.

If you maintain a plugin that sets cookies, registering an integration is the friendliest option for your users: one filter, and your cookies are disclosed correctly for everyone running both plugins without them having to do anything.

= Disclaimer =

**We are not lawyers and this plugin is not legal advice.** It is a tool that helps you present and act on cookie choices; it cannot tell you what the law requires of your site.

Installing this plugin does not by itself make your site compliant. That depends on what your site actually does, how you configure the plugin, and which rules apply to you — GDPR, ePrivacy, UK GDPR, CCPA/CPRA and others differ, and they change.

In particular:

* **Check the disclosures against reality.** For each service you enable, we disclose the cookies documented for it in the research we did while building that release. Services change what they set, and your theme or another plugin can add cookies we know nothing about, so check your own site with your browser's developer tools and re-check after major updates. If you spot a cookie set by a supported plugin that we are not disclosing, please tell us on the [support forum](https://wordpress.org/support/plugin/just-cookies/) and we will update the list.
* **The defaults are a starting point, not a compliant configuration.** Which services you switch on, and whether you gate analytics and embeds, are your decisions.
* **You are responsible for the policies you link to.** Both generated pages are unpublished drafts and starting points, not finished legal documents.
* **Consent choices are stored in the visitor's browser**, not on your server, so this plugin does not keep records of consent. If your circumstances require you to demonstrate consent, you need something else as well.
* **There is no Google Consent Mode v2 support.** If you run Google Ads or AdSense to visitors in the EEA or UK, Google expects those signals and this plugin does not send them. See the FAQ below before choosing it.

If you are unsure what applies to you, talk to a qualified professional in your jurisdiction. This plugin is provided without warranty of any kind, as set out in the GPL license included with the plugin.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/` or install it through the Plugins screen.
2. Activate it. The banner stays hidden until you turn it on, so nothing changes on your site yet.
3. Go to Settings → Just Cookies, set up the banner and the services your site uses, then switch "Enable cookie banner" on.

On multisite, network activate the plugin and configure it under Network Admin → Settings → Just Cookies. Consent settings are then shared by every site on the network, and each site keeps its own appearance settings under its own Settings → Just Cookies — unless you turn on "Lock appearance for all sites", which removes that screen.

== Frequently Asked Questions ==

= Does it scan my site to find the cookies? =

No. Scanning only observes the cookies present on the page it visited, as a logged-out visitor who did not buy anything or play a video, so anything set later can be missed.

We take a different approach: we research each supported service's cookies from its own documentation and source, and build that into the plugin. You switch on the ones your site uses. The plugin does check which supported plugins are active so it can offer you the right ones, but you stay in control of what is disclosed.

Neither approach maintains itself — whichever you use, check the result against your own site.

= Does this make my site GDPR compliant? =

Not on its own. It gives you the tools to disclose cookies accurately and hold third-party content until visitors consent, but you are responsible for checking the disclosures match what your site actually does and for publishing the policies you link to. See the disclaimer above.

= Does it send data anywhere? =

No. The consent library is bundled with the plugin, consent is stored in the visitor's browser, and nothing is sent to an external service.

= Are embeds really blocked before consent? =

Yes, for the embeds it sees. The address is moved out of the `src` attribute on the server, so the browser never contacts the provider until the visitor accepts, and visitors with JavaScript disabled never load blocked embeds at all.

What it sees is the catch. The plugin scans post content as it is rendered, the text and block widget output, and the HTML WordPress produces when it turns a pasted URL into an embed. That covers the editor, reusable blocks, widgets and most page builders, because they all render through post content.

It will not see an embed that never passes through those filters. The usual cases are a theme template outputting an iframe directly, a custom field printed in a template, a builder module that renders through its own filter, or a template calling `wp_oembed_get()` itself.

If you have one of those, you do not need to modify the plugin:

* **In the settings.** On the Blocking tab, add the filter hook name under "Additional content filters", one per line. No code required.
* **In code.** Add hooks to the `just_cookies_embed_content_filters` filter, or use `just_cookies_embed_providers` to change which services can be selected.

Two other limits worth knowing. Content a provider's own JavaScript API draws — a map built with the Google Maps API rather than an iframe — is not caught. And blocking applies to the supported services rather than every third party on the page.

Lightbox links are recognised by their class. Fancybox, Lity, GLightbox, Magnific Popup and Elementor's lightbox are covered out of the box; a theme using its own marker can add it with the `just_cookies_lightbox_selectors` filter.

= Will it block analytics added by another plugin? =

Often, but not always — it depends on where the script is printed rather than on who added it.

With analytics gating on, the plugin looks in two places. Any script queued the normal WordPress way is checked wherever it ends up, which covers most plugins. Scripts printed straight into the page are checked in the header and footer, which is where tracking snippets and tag managers usually go.

A script is treated as analytics if its address matches a known tracking host, or, for inline snippets with no address, if its contents match a known pattern such as a `gtag(` or `dataLayer.push` call. Tags already held by another consent plugin are left alone.

So it can miss a tracker that is printed somewhere other than the header or footer — inside post content, or directly in a theme template — and one that is not on the known list, including analytics served from your own domain, since there is no third-party address to recognize.

Developers can extend all three parts: `just_cookies_analytics_buffer_hooks` to look in more places, and `just_cookies_analytics_script_patterns` and `just_cookies_analytics_inline_patterns` to recognize more trackers.

Whichever way you set it up, confirm it with your browser's network tab before relying on it: load the page, reject analytics, and check that no request to the tracker is made.

= Does it support Google Consent Mode v2? =

No, and if you run Google Ads you probably want a plugin that does.

The two approaches are different. This plugin holds the tag back entirely, so nothing loads until the visitor accepts. Consent Mode does the opposite: Google's tag loads either way, and your site tells it what it is allowed to do, so Google still receives signals from visitors who declined.

Which you need depends on what you use Google for:

* **No Google advertising products.** Blocking is fine, and arguably stronger — the tag never runs at all without consent.
* **Google Ads, AdSense, or GA4 with Google signals, on visitors in the EEA or UK.** Google expects Consent Mode v2 signals, and without them conversion tracking, remarketing audiences and reporting degrade for those visitors. Google also requires a CMP from its own certified list for some of these products. This plugin is not one, and does not send those signals.

That is a limitation of this plugin, not advice about anyone else's. If it applies to you, look for a Google-certified consent platform instead of this. If it does not, you are not missing anything by blocking.

= If someone withdraws consent, are the cookies removed? =

Analytics cookies, yes. They are first-party — written on your own domain by the tracking script — so the plugin can delete them. Withdrawing analytics consent clears the Google Analytics, Meta Pixel, Hotjar, Clarity, Bing, Matomo, TikTok and Mixpanel cookies and reloads the page, because the tracker stays loaded until then and would simply write them again. Add your own with the `just_cookies_analytics_clear_cookies` filter.

Embed cookies, no — and no plugin can. YouTube, Vimeo and the rest set their cookies on their own domains, and a site can only delete cookies belonging to itself. Once a visitor has loaded an embed, only they can clear those, through their browser. Refusing afterwards stops the embed loading again, which is why blocking before the first request is the part that matters.

= Can I add my own cookies to the disclosure? =

Yes, with the `just_cookies_necessary_rows`, `just_cookies_analytics_rows` and `just_cookies_embed_rows` filters, or register a whole plugin integration with `just_cookies_integrations`.

= Can you add support for another plugin, or fix a cookie that is missing? =

Please ask. Open a thread on the [support forum](https://wordpress.org/support/plugin/just-cookies/) and we will look at adding it to a future release.

It helps a lot if you can include:

* The plugin name and a link to it.
* The cookie name, or the pattern if part of it varies (for example `wp_woocommerce_session_[hash]`).
* Roughly what it is for and when it gets set — on every page, only at checkout, only once logged in.
* How long it lasts, if you know.
* Where you saw it: a line in the plugin's own documentation or source is ideal, otherwise your browser's developer tools is fine.

Every entry in the supported list was checked against that plugin's own source or documentation before being added, so a pointer to either gets it in much faster. We would rather leave a plugin out than disclose a cookie name we could not verify.

In the meantime you can add the rows yourself with the filters above — you do not have to wait for a release.

== Screenshots ==

1. The cookie banner.
2. The preferences popup with cookie disclosures.
3. A blocked embed placeholder.
4. The settings screen.

== Changelog ==

= 1.0.0 =
* Initial release.
