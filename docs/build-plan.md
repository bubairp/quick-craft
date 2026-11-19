# Quick Craft: build plan

Figma file: `WdjEJ9NeK2FTVsJcGF6lby`. Node IDs are written `6-2902` (URL form), which is `6:2902` in the Figma API.
Workflow for each item is in `CLAUDE.md` → *Workflow: build one section at a time*. Commit each block separately and check it with Playwright at 375, 768 and 1440 px.

Status: ☐ to do · ◐ in progress · ☑ done

## 0. Foundations
| | Item | Notes |
|---|---|---|
| ☑ | DDEV, Craft 5, plugins, Vite + Tailwind v4 | commit `1889b68` |
| ☑ | Design tokens + content model migration | commit `0e5eaa4` |
| ☑ | Menus (Simple RP Menu): `headerMenu`, `footerCompany`, `footerProduct`, `footerSocials`, `footerLegal`, `footerSupport` | seeded by migration; some placeholder URLs |
| ◐ | Site settings global: logo, header buttons, app store links | buttons + store links done; logo waits for image export |
| ☐ | Placeholder images from Figma → `images` volume, folder `placeholders/` | on hold until WSL moves off C: |
| ☐ | Visual QA with Playwright at 375/768/1440 | blocked on Windows; redo inside WSL after the move |

## 1. Site chrome
| | Partial | Figma node | Notes |
|---|---|---|---|
| ☑ | `_partials/header.twig` | 6-2902 (in 6-2901) | floating pill nav: logo tile, menu, 2 CTA buttons; mobile menu (Alpine) |
| ☑ | `_partials/footer.twig` | 6-2058 (inner 6-2060) | dark rounded panel: 5 menu columns, store links (text until badges exported), giant wordmark (`siteName`) |

## 2. Home (6-1252), Single `home`
| | Block (entry type) | Layout options | Figma node | Content |
|---|---|---|---|---|
| ☑ | `hero` | split, dark | 6-2210 | "Parties are back", 2 buttons, chat-bubble collage (image) |
| ☑ | `testimonials` | press | 6-1259 | press logo strip (added after all): Swiper, active logo shows its quote |
| ☑ | `cardGrid` | grid, light | 6-1320 | "One link. Everyone's in.", 3 step cards + button ("Takes 30 seconds" note has no field) |
| ☑ | `imageGallery` | fan | 6-1384 | "A million reasons to hang", 20 invite images |
| ☑ | `cityList` | n/a | 6-4774 | "The best events in your city", 5 cities with hover thumbnails (`card.images`) |
| ☑ | `featureRows` | stickyPhone, dark | 6-1499 | Text blast / Collect info / Build hype |
| ☑ | `cardGrid` | compact, light | 6-1719 | "More than an invite", 4 tiles + 2 buttons |
| ☑ | `testimonials` | carousel | 6-2347 | "Social life runs on quick craft", stats + reviews |
| ☑ | `ctaBanner` | light + image rings | 6-1787 / 6-1792 | "Get the app" pill, store links |

All Home blocks were checked from rendered HTML only (structure, headings, empty fields). Visual QA is still pending.

## 3. Pages (Channel `pages`: landing pages, Careers, Press…)
### Organizers (17-267)
| | Block | Layout | Node |
|---|---|---|---|
| ☐ | banner (Banner tab) | centered + rotatingWords | 17-426 |
| — | logo strip | | 17-530 **skipped** |
| ☐ | `testimonials` | quotes | 17-576 / 17-591 |
| ☐ | `featureRows` | alternating | 17-640 |
| ☐ | `cardGrid` | grid | 17-716 |
| ☐ | `cardGrid` + `ctaBanner` | grid (community) | 17-771 |

### Birthday Invitations (17-890)
| | Block | Layout | Node |
|---|---|---|---|
| ☐ | banner (Banner tab) | split | 17-1049 |
| ☑ | `promoBar` | | 17-1076 |
| ☐ | `imageGallery` | grid (captions) | 17-1088 |
| ☐ | `cardGrid` | grid | 17-1271 |
| ☑ | `richText` | | 17-1343 |
| ☐ | `ctaBanner` | app badges | 17-1368 |

### Invitation Maker (17-1440)
| | Block | Layout | Node |
|---|---|---|---|
| ☐ | banner (Banner tab) | split + eyebrow pill | 17-1599 |
| — | quote marquee | | 17-1633 **skipped** |
| ☐ | `imageGallery` | row (categories) | 17-1931 |
| ☐ | `cardGrid` | grid | 17-2004 |
| ☑ | `comparison` | | 17-2074 |
| ☐ | `testimonials` | reviews | 17-2211 |
| ☐ | `richText` | | 17-2385 |
| ☑ | `faq` | | 17-2418 |
| ☐ | `ctaBanner` | | 17-2485 |

### Sell Tickets (17-2557), dark page
| | Block | Layout | Node |
|---|---|---|---|
| ☐ | banner (Banner tab) | split, dark, video | 17-2716 |
| — | logo strip | | 17-2735 **skipped** |
| ☐ | `cardGrid` | grid, dark | 17-2788 |
| ☐ | `imageGallery` | marquee | 17-3649 |
| ☐ | `cardGrid` | bento, dark | 17-3995 |
| ☐ | `cardGrid` | carousel, dark | 17-4296 |
| ☐ | `ctaBanner` | dark | 17-2992 |

## 4. Careers (6-6167), Single `careers` + Jobs channel
| | Block | Layout | Node |
|---|---|---|---|
| ☐ | banner (Banner tab) | fullBleed | 6-6336 |
| — | quote marquee | | 6-6349 **skipped** |
| ☑ | `textMedia` (+ `imageGallery` row ☐) | right | 6-6646 |
| ☐ | `cardGrid` | grid (emoji cards) | 6-6770 |
| ☑ | `textMedia` | left | 6-6862 |
| ☑ | `jobList` | grouped by department | 6-6899 |
| ☐ | `image` | full width | 6-7092 |

## 5. Press (18-5996), Single `press` + Press mentions channel
| | Block | Layout | Node |
|---|---|---|---|
| ☐ | banner (Banner tab) | simple | 18-6155 |
| ☐ | `pressList` | numbered | 18-6161 |

## 6. Blog (channel `blog`)
| | Template | Node | Notes |
|---|---|---|---|
| ☐ | `blog/index.twig` | 18-4865 (header 18-5024, grid 18-5028) | category filter, 3-col cards, pagination |
| ☐ | `blog/_entry.twig` | 18-5645 (article 18-5804) | featured/latest tags, body, "Next:" link |
| ☐ | `_partials/newsletter.twig` | 18-5576 / 18-5927 | `newsletter` Single (via `_globals`) + Wheel Form; add its Live Preview target → `blog` |

## 6b. Shopify-style page builder (agreed 2026-11-19, in this order)
- ☑ Colour schemes (`scheme`: light / white / soft / dark) + spacing presets (`spacing`: default / noTop / noBottom / none)
- ☑ Page tabs (Page · Banner · Content · SEO) + fixed banner (added 2026-11-20, as in Site7 Engine)
- ☑ Live Preview check and fixes (Swiper, Lenis, animations inside the preview) + siteSettings preview target
- ☑ Entry type icons and colours + `/blocks` library page (every block, every variant)
- ☑ Reusable sections (a channel + a `reusableSection` block); Home's "Get the app" is the first one
- ☑ Page presets (a new page starts with a chosen set of blocks): `pagePresets` + module `modules/PagePresets.php`
- ☐ (Optional, later) Click a section in Live Preview to edit it

## 7. Finish
- ☐ Ether SEO defaults (title suffix, social images), sitemap
- ◐ 404 page (☑ 404/500/503 system Singles), favicon ☐
- ☐ Cross-page Playwright pass (375/768/1440), accessibility and empty-field checks
- ☐ Replace placeholder content with real Quick Craft assets and copy (the imported design contains third-party logos, fonts and screenshots)
