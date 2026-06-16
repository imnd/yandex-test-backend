const { chromium } = require('playwright-chromium');

async function main() {
    const url = process.argv[2];
    if (!url) {
        console.error(JSON.stringify({ error: 'No URL provided' }));
        process.exit(1);
    }

    let browser;
    try {
        browser = await chromium.launch({
            headless: true,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-blink-features=AutomationControlled'
            ]
        });

        const context = await browser.newContext({
            userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            viewport: { width: 1280, height: 800 },
            locale: 'ru-RU',
            timezoneId: 'Europe/Moscow'
        });

        await context.addInitScript(() => {
            Object.defineProperty(navigator, 'webdriver', {
                get: () => undefined
            });
        });

        const page = await context.newPage();

        await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 });

        try {
            await page.waitForSelector('[class*="business-card-view"], h1', { timeout: 15000 });
        } catch (e) {
            // Continue even if selector fails
        }

        // Extract org info from schema.org structured data and DOM
        const orgInfo = await page.evaluate(() => {
            let name = null;
            let rating = null;
            let ratingCount = null;
            let reviewCount = null;

            // Try schema.org first (most reliable)
            const aggRating = document.querySelector('[itemProp="aggregateRating"]');
            if (aggRating) {
                const rv = aggRating.querySelector('[itemProp="ratingValue"]');
                const rc = aggRating.querySelector('[itemProp="reviewCount"]');
                const rtc = aggRating.querySelector('[itemProp="ratingCount"]');
                if (rv) rating = parseFloat(rv.getAttribute('content')) || null;
                if (rc) reviewCount = parseInt(rc.getAttribute('content'), 10) || null;
                if (rtc) ratingCount = parseInt(rtc.getAttribute('content'), 10) || null;
            }

            // Fallback for rating: check business rating badge class
            if (!rating) {
                const ratingEl = document.querySelector('.business-summary-rating-badge-view__rating, .business-header-rating-view__rating, [class*="summary-rating-badge-view__rating"]');
                if (ratingEl) {
                    const val = parseFloat(ratingEl.innerText.replace(',', '.'));
                    if (!isNaN(val)) rating = val;
                }
            }

            // Fallback for ratingCount: check rating count badge class
            if (!ratingCount) {
                const ratingCountEl = document.querySelector('.business-summary-rating-badge-view__rating-count, [class*="summary-rating-badge-view__rating-count"]');
                if (ratingCountEl) {
                    const text = ratingCountEl.innerText || '';
                    const m = text.match(/(\d[\d\s]*)/);
                    if (m) ratingCount = parseInt(m[1].replace(/\s/g, ''), 10);
                }
            }

            // Fallback for reviewCount: check reviews tab title
            if (!reviewCount) {
                const reviewsTab = document.querySelector('.tabs-select-view__title._name_reviews, [class*="tabs-select-view__title"][class*="_name_reviews"]');
                if (reviewsTab) {
                    const text = reviewsTab.innerText || '';
                    const m = text.match(/(\d[\d\s]*)/);
                    if (m) reviewCount = parseInt(m[1].replace(/\s/g, ''), 10);
                }
            }

            // Name from h1 or business card header
            const nameEl = document.querySelector('h1, [class*="orgpage-header-view__header"], [class*="card-title-view__title"]');
            if (nameEl) name = nameEl.innerText.trim();

            // Fallback: try text patterns for counts if still not found
            if (!ratingCount || !reviewCount) {
                const textElements = Array.from(document.querySelectorAll('span, div, a, p'));
                for (const el of textElements) {
                    const text = el.innerText || '';
                    if (!ratingCount && /оценк/i.test(text)) {
                        const m = text.match(/(\d[\d\s]*)\s*оцен/);
                        if (m) ratingCount = parseInt(m[1].replace(/\s/g, ''), 10);
                    }
                    if (!reviewCount && /отзыв/i.test(text)) {
                        const m = text.match(/(\d[\d\s]*)\s*отзыв/);
                        if (m) reviewCount = parseInt(m[1].replace(/\s/g, ''), 10);
                    }
                    if (!ratingCount && /\brating\b/i.test(text)) {
                        const m = text.match(/(\d[\d\s]*)\s*rating/i);
                        if (m) ratingCount = parseInt(m[1].replace(/\s/g, ''), 10);
                    }
                    if (!reviewCount && /\breview\b/i.test(text)) {
                        const m = text.match(/(\d[\d\s]*)\s*review/i);
                        if (m) reviewCount = parseInt(m[1].replace(/\s/g, ''), 10);
                    }
                }
            }

            return { name, rating, ratingCount, reviewCount };
        });

        // Navigate to reviews: click the Reviews tab
        const reviewsTabClicked = await page.evaluate(() => {
            // Try clicking the Reviews tab by various selectors
            const selectors = [
                '[class*="tabs-select-view__title _name_reviews"]',
                '[class*="tabs-select-view__title"][class*="_name_reviews"]',
                'a[href*="/reviews/"]',
                '[role="tab"][class*="_name_reviews"]'
            ];
            for (const sel of selectors) {
                const el = document.querySelector(sel);
                if (el) {
                    el.click();
                    return true;
                }
            }
            return false;
        });

        if (reviewsTabClicked) {
            // Wait for reviews to load after clicking tab
            await page.waitForTimeout(2000);
        } else {
            // Try clicking "View all N review" button
            const viewAllClicked = await page.evaluate(() => {
                const btn = document.querySelector('[class*="business-reviews-card-view__more"] [role="button"], [class*="business-reviews-card-view__more"]');
                if (btn) {
                    btn.click();
                    return true;
                }
                return false;
            });
            if (viewAllClicked) {
                await page.waitForTimeout(2000);
            }
        }

        // Wait for individual review elements to appear
        try {
            await page.waitForSelector('.business-review-view, [class*="business-review-view "], .business-reviews-card-view', { timeout: 15000 });
        } catch (e) {
            // Reviews might not have loaded; continue anyway
        }

        // Scroll to load all reviews
        let lastCount = 0;
        let noChangeCount = 0;
        const targetCount = 600;
        const maxScrolls = 60;

        for (let i = 0; i < maxScrolls; i++) {
            const currentCount = await page.locator('.business-review-view, .business-reviews-card-view').count();

            if (currentCount >= targetCount) break;

            if (currentCount === lastCount) {
                noChangeCount++;
                if (noChangeCount >= 6) break;
            } else {
                noChangeCount = 0;
                lastCount = currentCount;
            }

            await page.evaluate(() => {
                const container = document.querySelector('.scroll__content, [class*="scroll__content"], .business-tab-wrapper__content, [class*="business-reviews-card-view__reviews-container"]');
                if (container) {
                    container.scrollTo(0, container.scrollHeight);
                } else {
                    window.scrollTo(0, document.body.scrollHeight);
                }
            });

            await page.waitForTimeout(800 + Math.random() * 700);
        }

        // Extract reviews using correct selectors
        const reviews = await page.evaluate(() => {
            const cards = document.querySelectorAll('.business-review-view, .business-reviews-card-view');
            return Array.from(cards).map(card => {
                // Author Name
                const authorEl = card.querySelector('.business-review-view__author-name span[itemprop="name"], .business-review-view__author-name, .business-reviews-card-view__author [class*="name"], .business-reviews-card-view__author');
                const authorName = authorEl ? authorEl.innerText.trim() : 'Аноним';

                // Author Avatar
                let authorAvatar = null;
                const metaAvatar = card.querySelector('.business-review-view__author-name meta[itemprop="image"]');
                if (metaAvatar) {
                    authorAvatar = metaAvatar.getAttribute('content');
                } else {
                    const avatarDiv = card.querySelector('.user-icon-view__icon');
                    if (avatarDiv) {
                        const style = avatarDiv.getAttribute('style') || '';
                        const m = style.match(/url\("?([^"]+)"?\)/);
                        if (m) authorAvatar = m[1];
                    }
                }
                if (!authorAvatar) {
                    const avatarImg = card.querySelector('.business-review-view__author-image img, .business-review-view__user-icon img, .user-avatar__image, [class*="avatar"] img');
                    if (avatarImg) {
                        authorAvatar = avatarImg.getAttribute('src') || avatarImg.getAttribute('data-src') || null;
                    }
                }

                // Review Text
                const textEl = card.querySelector('.business-review-view__body, .business-review-view__text, .business-reviews-card-view__text');
                const text = textEl ? textEl.innerText.trim() : '';

                // Date
                const dateEl = card.querySelector('.business-review-view__date, .business-reviews-card-view__date, [class*="date"]');
                const publishedAtStr = dateEl ? dateEl.innerText.trim() : '';

                // Rating from schema.org meta tags or star badges
                let rating = 5;
                const ratingMeta = card.querySelector('meta[itemprop="ratingValue"]');
                if (ratingMeta) {
                    const val = parseFloat(ratingMeta.getAttribute('content'));
                    if (!isNaN(val)) rating = val;
                } else {
                    const starsContainer = card.querySelector('.business-review-view__rating, .business-rating-badge-view, .business-rating-stars-view, [class*="stars-view"]');
                    if (starsContainer) {
                        const labeledEl = starsContainer.hasAttribute('aria-label') ? starsContainer : starsContainer.querySelector('[aria-label]');
                        const ariaLabel = labeledEl ? labeledEl.getAttribute('aria-label') : null;
                        if (ariaLabel) {
                            const m = ariaLabel.match(/(\d+)/);
                            if (m) rating = parseInt(m[1], 10);
                        } else {
                            const filledStars = starsContainer.querySelectorAll('.business-rating-badge-view__star._full, .business-rating-stars-view__star_active, [class*="star_active"], [class*="star_filled"], [class*="star--filled"]');
                            const halfStars = starsContainer.querySelectorAll('.business-rating-badge-view__star._half');
                            if (filledStars.length > 0 || halfStars.length > 0) {
                                rating = filledStars.length + (halfStars.length > 0 ? 0.5 : 0);
                            }
                        }
                    }
                }

                return { authorName, authorAvatar, text, rating, publishedAtStr };
            });
        });

        const output = {
            success: true,
            resolvedUrl: page.url(),
            orgInfo,
            reviewsCount: reviews.length,
            reviews
        };
        if (!orgInfo.name) {
            console.error(JSON.stringify({
                success: false,
                error: 'ORG_NOT_FOUND',
                message: 'Could not extract organization name from page'
            }));
            process.exit(1);
        }
        console.log(JSON.stringify(output));
    } catch (err) {
        console.error(JSON.stringify({
            success: false,
            error: err.message,
            stack: err.stack
        }));
        process.exit(1);
    } finally {
        if (browser) {
            await browser.close();
        }
    }
}

main();
