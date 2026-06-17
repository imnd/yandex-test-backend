const { chromium } = require('playwright-chromium');

const RV = 'business-review-view';
const RC = 'business-reviews-card-view';

const any = (...classes) => classes.join(', ');

const S = {
    org: {
        cardView: '[class*="business-card-view"], h1',
        rating: any(
            '.business-summary-rating-badge-view__rating',
            '.business-header-rating-view__rating',
            '[class*="summary-rating-badge-view__rating"]',
        ),
        ratingCount: any(
            '.business-summary-rating-badge-view__rating-count',
            '[class*="summary-rating-badge-view__rating-count"]',
        ),
        reviewCount: any(
            '.tabs-select-view__title._name_reviews',
            '[class*="tabs-select-view__title"][class*="_name_reviews"]',
        ),
        name: any('h1', '[class*="orgpage-header-view__header"]', '[class*="card-title-view__title"]'),
    },
    reviews: {
        waitFor: any(`.${RV}`, `[class*="${RV} "]`, `.${RC}`),
        card: any(`.${RV}`, `.${RC}`),
        author: any(
            `.${RV}__author-name span[itemprop="name"]`,
            `.${RV}__author-name`,
            `.${RC}__author [class*="name"]`,
            `.${RC}__author`,
        ),
        authorAvatarMeta: `.${RV}__author-name meta[itemprop="image"]`,
        authorAvatarDiv: '.user-icon-view__icon',
        authorAvatarImg: any(
            `.${RV}__author-image img`,
            `.${RV}__user-icon img`,
            '.user-avatar__image',
            '[class*="avatar"] img',
        ),
        text: any(`.${RV}__body`, `.${RV}__text`, `.${RC}__text`),
        date: any(`.${RV}__date`, `.${RC}__date`, '[class*="date"]'),
        ratingMeta: 'meta[itemprop="ratingValue"]',
        ratingContainer: any(
            `.${RV}__rating`,
            '.business-rating-badge-view',
            '.business-rating-stars-view',
            '[class*="stars-view"]',
        ),
        starFilled: any(
            '.business-rating-badge-view__star._full',
            '.business-rating-stars-view__star_active',
            '[class*="star_active"]',
            '[class*="star_filled"]',
            '[class*="star--filled"]',
        ),
        starHalf: '.business-rating-badge-view__star._half',
        viewAll: any(
            `[class*="${RC}__more"] [role="button"]`,
            `[class*="${RC}__more"]`,
        ),
        scrollContainer: any(
            '.scroll__content',
            '[class*="scroll__content"]',
            '.business-tab-wrapper__content',
            `[class*="${RC}__reviews-container"]`,
        ),
    },
    reviewsTab: [
        '[class*="tabs-select-view__title _name_reviews"]',
        '[class*="tabs-select-view__title"][class*="_name_reviews"]',
        'a[href*="/reviews/"]',
        '[role="tab"][class*="_name_reviews"]',
    ],
    schema: {
        aggRating: '[itemProp="aggregateRating"]',
        ratingValue: '[itemProp="ratingValue"]',
        reviewCount: '[itemProp="reviewCount"]',
        ratingCount: '[itemProp="ratingCount"]',
    },
};

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

        await page.waitForSelector(S.org.cardView, { timeout: 15000 });

        const orgInfo = await page.evaluate((s) => {
            let name = null;
            let rating = null;
            let ratingCount = null;
            let reviewCount = null;

            const aggRating = document.querySelector(s.aggRating);
            if (aggRating) {
                const rv = aggRating.querySelector(s.ratingValue);
                const rc = aggRating.querySelector(s.reviewCount);
                const rtc = aggRating.querySelector(s.ratingCount);
                if (rv) rating = parseFloat(rv.getAttribute('content')) || null;
                if (rc) reviewCount = parseInt(rc.getAttribute('content'), 10) || null;
                if (rtc) ratingCount = parseInt(rtc.getAttribute('content'), 10) || null;
            }

            if (!rating) {
                const ratingEl = document.querySelector(s.ratingFallback);
                if (ratingEl) {
                    const val = parseFloat(ratingEl.innerText.replace(',', '.'));
                    if (!isNaN(val)) rating = val;
                }
            }

            if (!ratingCount) {
                const ratingCountEl = document.querySelector(s.ratingCountFallback);
                if (ratingCountEl) {
                    const text = ratingCountEl.innerText || '';
                    const m = text.match(/(\d[\d\s]*)/);
                    if (m) ratingCount = parseInt(m[1].replace(/\s/g, ''), 10);
                }
            }

            if (!reviewCount) {
                const reviewsTab = document.querySelector(s.reviewCountFallback);
                if (reviewsTab) {
                    const text = reviewsTab.innerText || '';
                    const m = text.match(/(\d[\d\s]*)/);
                    if (m) reviewCount = parseInt(m[1].replace(/\s/g, ''), 10);
                }
            }

            const nameEl = document.querySelector(s.name);
            if (nameEl) name = nameEl.innerText.trim();

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
        }, {
            aggRating: S.schema.aggRating,
            ratingValue: S.schema.ratingValue,
            reviewCount: S.schema.reviewCount,
            ratingCount: S.schema.ratingCount,
            ratingFallback: S.org.rating,
            ratingCountFallback: S.org.ratingCount,
            reviewCountFallback: S.org.reviewCount,
            name: S.org.name,
        });

        const reviewsTabClicked = await page.evaluate((tabSelectors) => {
            for (const sel of tabSelectors) {
                const el = document.querySelector(sel);
                if (el) {
                    el.click();
                    return true;
                }
            }
            return false;
        }, S.reviewsTab);

        if (reviewsTabClicked) {
            await page.waitForTimeout(2000);
        } else {
            const viewAllClicked = await page.evaluate((btnSelector) => {
                const btn = document.querySelector(btnSelector);
                if (btn) {
                    btn.click();
                    return true;
                }
                return false;
            }, S.reviews.viewAll);
            if (viewAllClicked) {
                await page.waitForTimeout(2000);
            }
        }

        try {
            await page.waitForSelector(S.reviews.waitFor, { timeout: 15000 });
        } catch (e) {
            // Reviews might not have loaded; continue anyway
        }

        let lastCount = 0;
        let noChangeCount = 0;
        const targetCount = 600;
        const maxScrolls = 60;

        for (let i = 0; i < maxScrolls; i++) {
            const currentCount = await page.locator(S.reviews.card).count();

            if (currentCount >= targetCount) break;

            if (currentCount === lastCount) {
                noChangeCount++;
                if (noChangeCount >= 6) break;
            } else {
                noChangeCount = 0;
                lastCount = currentCount;
            }

            await page.evaluate((scrollSel) => {
                const container = document.querySelector(scrollSel);
                if (container) {
                    container.scrollTo(0, container.scrollHeight);
                } else {
                    window.scrollTo(0, document.body.scrollHeight);
                }
            }, S.reviews.scrollContainer);

            await page.waitForTimeout(800 + Math.random() * 700);
        }

        const reviews = await page.evaluate((s) => {
            const cards = document.querySelectorAll(s.card);
            return Array.from(cards).map(card => {
                const authorEl = card.querySelector(s.author);
                const authorName = authorEl ? authorEl.innerText.trim() : 'Аноним';

                let authorAvatar = null;
                const metaAvatar = card.querySelector(s.authorAvatarMeta);
                if (metaAvatar) {
                    authorAvatar = metaAvatar.getAttribute('content');
                } else {
                    const avatarDiv = card.querySelector(s.authorAvatarDiv);
                    if (avatarDiv) {
                        const style = avatarDiv.getAttribute('style') || '';
                        const m = style.match(/url\("?([^"]+)"?\)/);
                        if (m) authorAvatar = m[1];
                    }
                }
                if (!authorAvatar) {
                    const avatarImg = card.querySelector(s.authorAvatarImg);
                    if (avatarImg) {
                        authorAvatar = avatarImg.getAttribute('src') || avatarImg.getAttribute('data-src') || null;
                    }
                }

                const textEl = card.querySelector(s.text);
                const text = textEl ? textEl.innerText.trim() : '';

                const dateEl = card.querySelector(s.date);
                const publishedAtStr = dateEl ? dateEl.innerText.trim() : '';

                let rating = 5;
                const ratingMeta = card.querySelector(s.ratingMeta);
                if (ratingMeta) {
                    const val = parseFloat(ratingMeta.getAttribute('content'));
                    if (!isNaN(val)) rating = val;
                } else {
                    const starsContainer = card.querySelector(s.ratingContainer);
                    if (starsContainer) {
                        const labeledEl = starsContainer.hasAttribute('aria-label') ? starsContainer : starsContainer.querySelector('[aria-label]');
                        const ariaLabel = labeledEl ? labeledEl.getAttribute('aria-label') : null;
                        if (ariaLabel) {
                            const m = ariaLabel.match(/(\d+)/);
                            if (m) rating = parseInt(m[1], 10);
                        } else {
                            const filledStars = starsContainer.querySelectorAll(s.starFilled);
                            const halfStars = starsContainer.querySelectorAll(s.starHalf);
                            if (filledStars.length > 0 || halfStars.length > 0) {
                                rating = filledStars.length + (halfStars.length > 0 ? 0.5 : 0);
                            }
                        }
                    }
                }

                return { authorName, authorAvatar, text, rating, publishedAtStr };
            });
        }, {
            card: S.reviews.card,
            author: S.reviews.author,
            authorAvatarMeta: S.reviews.authorAvatarMeta,
            authorAvatarDiv: S.reviews.authorAvatarDiv,
            authorAvatarImg: S.reviews.authorAvatarImg,
            text: S.reviews.text,
            date: S.reviews.date,
            ratingMeta: S.reviews.ratingMeta,
            ratingContainer: S.reviews.ratingContainer,
            starFilled: S.reviews.starFilled,
            starHalf: S.reviews.starHalf,
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
