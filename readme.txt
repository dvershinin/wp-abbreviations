=== Abbreviations ===
Contributors: dvershinin, MrXHellboy
Donate link: https://www.buymeacoffee.com/dvershinin
Tags: abbreviation, seo, accessibility, abbr, html
Requires at least: 4.6
Requires PHP: 7.0
Tested up to: 6.7
Stable tag: 1.6
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Wrap abbreviations in proper HTML abbr tags for better SEO and accessibility.

== Description ==
Wrap abbreviations for search engine optimization and support other applications

Using abbreviations on your website is not a bad idea since people will get tired of reading the same over and over again.
Unfortunately, search engines and other application which might render your website are not that great with abbreviations.
Reading software, spelling checkers, language translations or other applications might fall over them.

By wrapping abbreviations in the therefor "abbr" HTML element, along with a title(description), applications relying on the structure of your site, can do their job just fine.

Search engines will read the abbreviation and the description which increases the chance for more relevance by specific terms used on search engines like Google.

== Changelog ==

= 1.6 =
* Performance: Single-pass regex now matches ALL abbreviations in one scan (O(1) instead of O(n))
* Performance: Added WordPress object caching to reduce database queries
* Performance: Longer abbreviations now match first to prevent partial matches
* Code quality: Refactored to use named functions for better maintainability
* Code quality: Full WordPress Coding Standards compliance

= 1.5 =
* Initial public release
