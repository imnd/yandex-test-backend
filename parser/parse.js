const { chromium } = require('playwright-chromium');
const fs = require('fs');

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

        // Set automation control bypass
        await context.addInitScript(() => {
            Object.defineProperty(navigator, 'webdriver', {
                get: () => undefined
            });
        });

        const page = await context.newPage();
        
        // Navigate to URL and wait for domcontentloaded
        await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 });
        
        // Wait up to 5 seconds for organization name/rating block to render
        try {
            await page.waitForSelector('h1, .orgpage-header-view__header', { timeout: 10000 });
        } catch (e) {
            // Continue even if selector fails, maybe it loaded
        }

        // Get organization general details
        const orgInfo = await page.evaluate(() => {
            const nameEl = document.querySelector('h1, .orgpage-header-view__header, [class*="header-view__header"]');
            const name = nameEl ? nameEl.innerText.trim() : null;

            // Rating
            const ratingEl = document.querySelector('.business-rating-badge-view__rating, [class*="rating-badge-view__rating"], [class*="rating-value"]');
            let rating = null;
            if (ratingEl) {
                const ratingStr = ratingEl.innerText.trim().replace(',', '.');
                rating = parseFloat(ratingStr) || null;
            }

            // Look for rating and reviews count in elements
            let ratingCount = null;
            let reviewCount = null;
            
            const textElements = Array.from(document.querySelectorAll('span, div, a, p'));
            for (const el of textElements) {
                const text = el.innerText || '';
                
                // Russian pattern
                if (text.includes('оценк') && !ratingCount) {
                    const match = text.match(/(\d[\d\s]*)\s*оцен/);
                    if (match) {
                        ratingCount = parseInt(match[1].replace(/\s/g, ''), 10);
                    }
                }
                if (text.includes('отзыв') && !reviewCount) {
                    const match = text.match(/(\d[\d\s]*)\s*отзыв/);
                    if (match) {
                        reviewCount = parseInt(match[1].replace(/\s/g, ''), 10);
                    }
                }

                // English pattern fallbacks
                if (text.includes('rating') && !ratingCount) {
                    const match = text.match(/(\d[\d\s]*)\s*rating/i);
                    if (match) {
                        ratingCount = parseInt(match[1].replace(/\s/g, ''), 10);
                    }
                }
                if (text.includes('review') && !reviewCount) {
                    const match = text.match(/(\d[\d\s]*)\s*review/i);
                    if (match) {
                        reviewCount = parseInt(match[1].replace(/\s/g, ''), 10);
                    }
                }
            }

            return { name, rating, ratingCount, reviewCount };
        });

        // Ensure we find reviews tab if not already there, or try scrolling
        // Yandex orgpage can load reviews tab directly if URL has /reviews/
        // Scroll loop
        let lastCount = 0;
        let noChangeCount = 0;
        const targetCount = 600;
        const maxScrolls = 60; // Up to 60 scroll iterations

        for (let i = 0; i < maxScrolls; i++) {
            const currentCount = await page.locator('.business-reviews-card-view, [class*="business-reviews-card-view"]').count();
            
            if (currentCount >= targetCount) {
                break;
            }

            if (currentCount === lastCount) {
                noChangeCount++;
                if (noChangeCount >= 6) {
                    break; // No new content loaded after 6 attempts, assume end
                }
            } else {
                noChangeCount = 0;
                lastCount = currentCount;
            }

            // Perform scroll inside the container or fallback to body
            await page.evaluate(() => {
                const container = document.querySelector('.scroll__content, [class*="scroll__content"], .business-tab-wrapper__content');
                if (container) {
                    container.scrollTo(0, container.scrollHeight);
                } else {
                    window.scrollTo(0, document.body.scrollHeight);
                }
            });

            // Random delay 800ms to 1500ms
            await page.waitForTimeout(800 + Math.random() * 700);
        }

        // Extract reviews data
        const reviews = await page.evaluate(() => {
            const cards = document.querySelectorAll('.business-reviews-card-view, [class*="business-reviews-card-view"]');
            return Array.from(cards).map(card => {
                // Author Name
                const authorEl = card.querySelector('.business-reviews-card-view__author [class*="name"], .business-reviews-card-view__author, [class*="author"]');
                const authorName = authorEl ? authorEl.innerText.trim() : 'Аноним';

                // Author Avatar
                const avatarEl = card.querySelector('.user-avatar__image, [class*="avatar"] img');
                const authorAvatar = avatarEl ? avatarEl.getAttribute('src') : null;

                // Text
                const textEl = card.querySelector('.business-reviews-card-view__text, [class*="text"], [class*="body"]');
                const text = textEl ? textEl.innerText.trim() : '';

                // Date
                const dateEl = card.querySelector('.business-reviews-card-view__date, [class*="date"]');
                const publishedAtStr = dateEl ? dateEl.innerText.trim() : '';

                // Rating
                const starsContainer = card.querySelector('.business-rating-stars-view, [class*="stars-view"]');
                let rating = 5;
                if (starsContainer) {
                    const ariaLabel = starsContainer.getAttribute('aria-label');
                    if (ariaLabel) {
                        const match = ariaLabel.match(/(\d)/);
                        if (match) {
                            rating = parseInt(match[1], 10);
                        }
                    } else {
                        const filledStars = starsContainer.querySelectorAll('.business-rating-stars-view__star_active, [class*="star_active"], [class*="star_filled"], [class*="star--filled"]');
                        if (filledStars.length > 0) {
                            rating = filledStars.length;
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
