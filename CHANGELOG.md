# Changelog

All notable changes to this extension are documented here. The format
is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [1.3.11] - 2026-10-03

### Fixed
- Post page images, the post Open Graph image, the RSS feed media image and the BlogPosting structured data image now point to the blog media folder (media/blog/...), the same folder the admin uploader saves to and the listing pages already use. Absolute image URLs are kept as entered.
- Table of contents: a heading without an id no longer receives an id that another element in the post already uses, so every TOC link jumps to the right heading and the page has no duplicate ids.
