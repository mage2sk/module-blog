# Panth_Blog User Guide

A short walkthrough for store admins managing the blog. Examples use `https://example.com` and the default URL prefix `blog`.

## Creating a post

1. Go to **Panth Infotech > Blog > Manage Posts** and click **Add New Post**.
2. Fill in the fields:
   - **Title**: the headline shown on the page.
   - **URL Key**: generated from the title when left empty. Example: `style-linen-shirts`.
   - **Short Description**: the snippet shown on listing pages and the fallback meta description.
   - **Content**: the post body (WYSIWYG).
   - **Featured Image**: upload a JPG, PNG, GIF or WebP image (stored under `pub/media/blog`).
   - **Author**: one of the authors from **Manage Authors**.
   - **Status**: `draft`, `scheduled`, `published` or `archived`.
3. Click **Save**. A published post is available at `https://example.com/blog/style-linen-shirts`.

The Categorisation section sets the author, store views (All Store Views shows the post everywhere), categories, the primary category (defaults to the first selected one), tags and related posts.

## Categories and tags

- **Categories** are hierarchical (parent category) and have their own listing template, posts per page, image and SEO fields.
- **Tags** are flat labels. A nightly job sets tags with fewer published posts than the thin-content threshold to `noindex,follow`.

## SEO fields

The **SEO** section of a post has meta title, meta description, meta keywords, meta robots, canonical URL and OG image.

- The page title comes from **Title Template** (default `{{post.title}}`). Use `{{post.meta_title}}` in the template to use the meta title.
- The meta description falls back to the short description, then to the default meta description.
- Leave the canonical URL empty to use the post URL.

## TL;DR and citations

- When **TL;DR Summary** is empty and Auto-extract TL;DR is enabled, a summary is taken from the first 80 to 120 words of long posts when the post is saved.
- **Citation List** takes JSON, for example `[{"label": "Sample handbook", "url": "/sample-handbook"}]`. Citations render as a numbered list at the end of the post.

## Scheduled posts

1. Set **Status** to `scheduled` and **Published At** to a future date and time.
2. Save.

The `panth_blog_publish_scheduled` cron job runs every 5 minutes and publishes due posts. Publishing through the cron saves the post normally, so the post URL is queued for IndexNow and the blog pages in the full page cache are refreshed.

## Store views

A post or category with no rows in `panth_blog_post_store` / `panth_blog_category_store`, or with store `0`, is shown in every store view. A post or category linked to specific store views is only shown in those views: on the storefront pages, feeds, search, sidebar, related posts, `llms.txt`, REST and GraphQL. Per-store text overrides are not supported.

## Markdown export

Each published post is available as Markdown at `https://example.com/blog/<url-key>.md` when Markdown export is enabled.

## Feeds

- Site-wide RSS: `https://example.com/blog/feed.xml`
- Atom: `https://example.com/blog/feed/atom.xml`
- Per category: `https://example.com/blog/feed/category/<url-key>.xml` (Per-Category Feeds, default Yes)
- Per author: `https://example.com/blog/feed/author/<url-key>.xml` (Per-Author Feeds, default Yes)
- Per tag: `https://example.com/blog/feed/tag/<url-key>.xml` (Per-Tag Feeds, default No)

Feed length, full content and cache lifetime are set under **Stores > Configuration > Panth > Blog > Feeds**.
