# Changelog

All notable changes to this extension are documented here. The format
is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [1.3.13] - 2026-10-06

### Changed
- Post body: 16px text with a 1.5 line height, and paragraphs at most 72 characters wide. H2 is 28px (24px on phones; 24px before) and H3 is 22px (20px before), with 40px and 32px above them. Images never get wider than the column.
- The post title, the blog home title and the category, tag and author banner titles are 36px (28px on phones). Before, they went up to 48px.
- The featured post title and the Related posts heading are 28px (24px on phones). Before, they were 30px and 20px.
- The TL;DR text is 16px (15px before).
- Related posts and comments start 64px below the post (40px on phones).

### Added
- Unit tests for the reading styles.
