#!/usr/bin/env python3

import json
import os
import sys
import time
from pathlib import Path

from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

MARKETPLACE_URL = "https://www.chattanoogamusicscene.com/classifieds/"
ROOT = Path(__file__).resolve().parents[3]
CSS_PATH = ROOT / "site-plugins" / "chattanooga-music-marketplace" / "assets" / "marketplace.css"
OUTPUT_DIR = Path(os.environ.get("CMS_MARKETPLACE_VISUAL_OUTPUT", "/tmp/cms-marketplace-visual"))


def fail(message):
    raise RuntimeError(message)


def browser_options(width, height):
    options = webdriver.ChromeOptions()
    options.add_argument("--headless=new")
    options.add_argument("--no-sandbox")
    options.add_argument("--disable-dev-shm-usage")
    options.add_argument("--disable-gpu")
    options.add_argument(f"--window-size={width},{height}")
    options.add_argument("--force-device-scale-factor=1")
    options.add_argument("--hide-scrollbars")
    options.page_load_strategy = "normal"
    return options


def inject_fixture(driver, css_text):
    driver.execute_script(
        """
        const existingStyle = document.getElementById('cms-marketplace-visual-style');
        if (existingStyle) existingStyle.remove();
        const existingFixture = document.querySelector('[data-cms-marketplace-visual-fixture]');
        if (existingFixture) existingFixture.remove();

        const style = document.createElement('style');
        style.id = 'cms-marketplace-visual-style';
        style.textContent = arguments[0];
        document.head.appendChild(style);

        const firstListing = document.querySelector('.awpcp-listings .awpcp-listing-excerpt') ||
                             document.querySelector('.awpcp-listing-excerpt');
        if (!firstListing) {
            throw new Error('No AWP listing item found on the public Marketplace page.');
        }

        const sourceImage = firstListing.querySelector('img');
        const source = sourceImage ? (sourceImage.currentSrc || sourceImage.src) : '';
        const fallback = 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(
            '<svg xmlns="http://www.w3.org/2000/svg" width="640" height="480" viewBox="0 0 640 480">' +
            '<rect width="640" height="480" fill="#dedede"/>' +
            '<text x="320" y="240" text-anchor="middle" dominant-baseline="middle" font-family="sans-serif" font-size="36" fill="#555">Marketplace product</text>' +
            '</svg>'
        );

        const article = document.createElement('article');
        article.className = 'awpcp-listing-excerpt cms-marketplace-item cms-marketplace-product';
        article.setAttribute('data-cms-marketplace-visual-fixture', '1');
        article.innerHTML = `
            <div class="cms-marketplace-item__image">
                <a href="#visual-product" tabindex="-1" aria-hidden="true">
                    <img src="${source || fallback}" alt="Integration Test Guitar Strings" loading="eager">
                </a>
            </div>
            <div class="cms-marketplace-item__content">
                <h4 class="cms-marketplace-item__title">
                    <a href="#visual-product">Integration Test Guitar Strings</a>
                </h4>
                <p class="cms-marketplace-item__excerpt">Fresh strings for rehearsals, recording sessions, and live shows with reliable tone, balanced tension, and durable winding.</p>
                <div class="cms-marketplace-item__meta">
                    <span class="cms-marketplace-item__date">September 11, 2026</span>
                    <span class="cms-marketplace-item__price">Price: <span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">$</span>12.99</bdi></span></span>
                </div>
            </div>`;
        firstListing.insertAdjacentElement('afterend', article);
        return true;
        """,
        css_text,
    )


def validation_failures(name, metrics, requested_width):
    failures = []
    if metrics["card_display"] != "grid":
        failures.append(f"{name}: Marketplace product card is not rendered as a grid.")
    if metrics["card_width"] < 240:
        failures.append(f"{name}: Marketplace product card is unexpectedly narrow.")
    if metrics["card_left"] < -1 or metrics["card_right"] > metrics["viewport_width"] + 1:
        failures.append(f"{name}: Marketplace product card extends beyond the viewport.")
    if metrics["page_scroll_width"] > metrics["page_client_width"] + 2:
        failures.append(f"{name}: Marketplace fixture introduced horizontal page overflow.")
    if metrics["image_width"] < 70 or metrics["image_height"] < 50:
        failures.append(f"{name}: Marketplace product image is not visibly rendered.")
    if metrics["title_width"] <= 0 or metrics["title_height"] <= 0:
        failures.append(f"{name}: Marketplace product title is not visibly rendered.")
    if metrics["price_width"] <= 0 or metrics["price_height"] <= 0:
        failures.append(f"{name}: Marketplace product price is not visibly rendered.")
    if metrics["overlaps_previous"] or metrics["overlaps_next"]:
        failures.append(f"{name}: Marketplace product card geometrically overlaps an adjacent visible AWP item.")
    if name == "mobile" and abs(metrics["viewport_width"] - requested_width) > 1:
        failures.append(
            f"{name}: expected a {requested_width}px CSS viewport but captured {metrics['viewport_width']}px."
        )
    return failures


def capture_viewport(width, height, name, css_text):
    driver = webdriver.Chrome(options=browser_options(width, height))
    driver.set_page_load_timeout(45)
    try:
        if name == "mobile":
            driver.execute_cdp_cmd(
                "Emulation.setDeviceMetricsOverride",
                {
                    "mobile": True,
                    "width": width,
                    "height": height,
                    "deviceScaleFactor": 1,
                    "screenWidth": width,
                    "screenHeight": height,
                },
            )

        driver.get(MARKETPLACE_URL)
        WebDriverWait(driver, 30).until(
            EC.presence_of_element_located((By.CSS_SELECTOR, ".awpcp-listing-excerpt"))
        )
        inject_fixture(driver, css_text)
        card = WebDriverWait(driver, 10).until(
            EC.presence_of_element_located((By.CSS_SELECTOR, "[data-cms-marketplace-visual-fixture]"))
        )
        driver.execute_script("arguments[0].scrollIntoView({block: 'center', inline: 'nearest'});", card)
        time.sleep(1.0)

        metrics = driver.execute_script(
            """
            function nodeInfo(element) {
                if (!element) return null;
                const rect = element.getBoundingClientRect();
                const style = getComputedStyle(element);
                return {
                    tag: element.tagName,
                    class_name: element.className || '',
                    top: rect.top,
                    bottom: rect.bottom,
                    left: rect.left,
                    right: rect.right,
                    width: rect.width,
                    height: rect.height,
                    display: style.display,
                    position: style.position,
                    float: style.float,
                    clear: style.clear,
                    overflow: style.overflow,
                };
            }

            function intersectionArea(first, second) {
                if (!first || !second) return 0;
                const firstStyle = getComputedStyle(first);
                const secondStyle = getComputedStyle(second);
                if (firstStyle.display === 'none' || secondStyle.display === 'none') return 0;
                const a = first.getBoundingClientRect();
                const b = second.getBoundingClientRect();
                if (a.width <= 0 || a.height <= 0 || b.width <= 0 || b.height <= 0) return 0;
                const width = Math.max(0, Math.min(a.right, b.right) - Math.max(a.left, b.left));
                const height = Math.max(0, Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top));
                return width * height;
            }

            const card = document.querySelector('[data-cms-marketplace-visual-fixture]');
            const previous = card.previousElementSibling;
            const next = card.nextElementSibling;
            const image = card.querySelector('.cms-marketplace-item__image img');
            const title = card.querySelector('.cms-marketplace-item__title');
            const price = card.querySelector('.cms-marketplace-item__price');
            const rect = card.getBoundingClientRect();
            const imageRect = image.getBoundingClientRect();
            const titleRect = title.getBoundingClientRect();
            const priceRect = price.getBoundingClientRect();
            const previousIntersectionArea = intersectionArea(card, previous);
            const nextIntersectionArea = intersectionArea(card, next);
            const style = getComputedStyle(card);
            const parentStyle = card.parentElement ? getComputedStyle(card.parentElement) : null;
            return {
                viewport_width: window.innerWidth,
                viewport_height: window.innerHeight,
                page_scroll_width: document.documentElement.scrollWidth,
                page_client_width: document.documentElement.clientWidth,
                card_left: rect.left,
                card_right: rect.right,
                card_top: rect.top,
                card_bottom: rect.bottom,
                card_width: rect.width,
                card_height: rect.height,
                card_display: style.display,
                card_position: style.position,
                card_float: style.float,
                card_clear: style.clear,
                card_overflow: style.overflow,
                grid_template_columns: style.gridTemplateColumns,
                parent_display: parentStyle ? parentStyle.display : null,
                parent_class_name: card.parentElement ? card.parentElement.className : null,
                image_width: imageRect.width,
                image_height: imageRect.height,
                title_width: titleRect.width,
                title_height: titleRect.height,
                price_width: priceRect.width,
                price_height: priceRect.height,
                previous: nodeInfo(previous),
                next: nodeInfo(next),
                previous_intersection_area: previousIntersectionArea,
                next_intersection_area: nextIntersectionArea,
                overlaps_previous: previousIntersectionArea > 1,
                overlaps_next: nextIntersectionArea > 1,
            };
            """
        )

        failures = validation_failures(name, metrics, width)
        metrics["requested_width"] = width
        metrics["requested_height"] = height
        metrics["failures"] = failures

        screenshot = OUTPUT_DIR / f"marketplace-{name}.png"
        driver.save_screenshot(str(screenshot))
        metrics_path = OUTPUT_DIR / f"marketplace-{name}-metrics.json"
        metrics_path.write_text(json.dumps(metrics, indent=2, sort_keys=True) + "\n", encoding="utf-8")
        return metrics
    finally:
        driver.quit()


def main():
    if not CSS_PATH.is_file():
        fail(f"Marketplace stylesheet is missing: {CSS_PATH}")

    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    css_text = CSS_PATH.read_text(encoding="utf-8")

    report = {
        "source_url": MARKETPLACE_URL,
        "css_path": str(CSS_PATH.relative_to(ROOT)),
        "desktop": capture_viewport(1440, 1000, "desktop", css_text),
        "mobile": capture_viewport(390, 844, "mobile", css_text),
    }

    report_path = OUTPUT_DIR / "marketplace-visual-report.json"
    report_path.write_text(json.dumps(report, indent=2, sort_keys=True) + "\n", encoding="utf-8")
    print(json.dumps(report, sort_keys=True))

    failures = report["desktop"]["failures"] + report["mobile"]["failures"]
    if failures:
        fail(" | ".join(failures))

    print("cms-marketplace-visual: PASS desktop=rendered mobile=rendered overflow=absent overlap=absent")


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:
        print(f"cms-marketplace-visual: FAIL {exc}", file=sys.stderr)
        raise
