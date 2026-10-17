# Aqar Nova — social media kit

HTML sources for the brand's social assets; `node render.mjs <outDir>` renders every page to a PNG at its own size
(needs the repo's `node_modules` beside it: `ln -s ../../node_modules`).

| File | Size | Use |
|---|---|---|
| profile-1080 | 1080×1080 | Profile picture (Facebook, Instagram, X, LinkedIn) — safe inside the circle crop |
| facebook-cover-1640x624 | 1640×624 | Facebook page cover (key content kept in the mobile-safe centre) |
| linkedin-banner-1584x396 | 1584×396 | LinkedIn page banner; also fits X (1500×500) with a small crop |
| post-1-launch … post-5-demo | 1080×1080 | Feed posts (Facebook, Instagram, LinkedIn, X) |
| story-1080x1920 | 1080×1920 | Instagram / Facebook story and reel cover |

Colours and type follow the product (`resources/views/filament/theme.blade.php`): teal `#0f766e`, deep teal
`#0b2a2e`, mint `#5eead4`, orange `#eb6834` for the demo call to action, Cairo throughout.
