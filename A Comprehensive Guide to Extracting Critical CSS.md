# A Comprehensive Guide to Extracting Critical CSS

In the pursuit of optimal web performance, developers are constantly seeking ways to reduce page load times and improve the user experience. One of the most effective techniques for achieving this is the implementation of **critical CSS**. This document provides a comprehensive overview of what critical CSS is, why it matters, and how to extract it using both automated tools and manual methods.

## What is Critical CSS?

Critical CSS is the minimal set of CSS required to render the visible, above-the-fold content of a webpage. The "above-the-fold" portion is what a user sees without scrolling when they first land on a page. By identifying and inlining these critical styles directly into the HTML `<head>`, the browser can begin rendering the page immediately, without having to wait for external CSS files to download and parse. This technique significantly improves the perceived performance of a website, especially on mobile devices with slower network connections [1].

### Why is Critical CSS Important?

CSS is a **render-blocking resource**, which means the browser must download and process all CSS files before it can display any content to the user. When CSS files are large or network conditions are poor, this can lead to a blank white screen for several seconds, resulting in a poor user experience and a high First Contentful Paint (FCP) time. By inlining the critical CSS, the browser has everything it needs to render the initial view, while the rest of the non-critical CSS can be loaded asynchronously in the background [2].

To maximize the benefit, it is recommended to keep the size of the inlined critical CSS under **14 KB** (compressed) [1].

## Automated Tools for Critical CSS Extraction

Manually identifying critical CSS can be a tedious and complex process. Fortunately, several excellent tools can automate this task. These tools analyze a webpage, determine which styles are applied to the above-the-fold content, and generate a minified CSS file containing only those critical styles.

Here is a comparison of some of the most popular automated tools:

| Tool | Description | Key Features |
|---|---|---|
| **Critical** | An npm module that extracts, minifies, and inlines above-the-fold CSS. It is one of the most widely used and versatile tools available [3]. | - Automatically detects stylesheets<br>- Supports multiple screen resolutions<br>- Integrates with Gulp, Grunt, and webpack<br>- Can inline the generated CSS directly into the HTML file |
| **criticalCSS** | Another npm module that extracts above-the-fold CSS. It is also available as a command-line interface (CLI) [4]. | - Allows for force-including rules that may not be automatically detected<br>- Provides more granular control over including `@font-face` declarations |
| **Penthouse** | A robust tool for generating critical-path CSS, particularly well-suited for sites with a large number of styles or dynamically injected CSS (common in single-page applications) [3]. | - Uses Puppeteer for accurate rendering<br>- Excellent at running multiple jobs in parallel<br>- Requires manual specification of HTML and CSS files |

### Practical Example: Using the `critical` npm Package

The `critical` package is a popular choice for its ease of use and powerful features. Here’s how to get started with it:

1.  **Installation:**

    ```bash
    npm install critical
    ```

2.  **Configuration and Execution:**

    Create a JavaScript file (e.g., `critical.js`) and add the following code:

    ```javascript
    const critical = require("critical");

    critical.generate({
      base: "public/",
      src: "index.html",
      dest: "index.html",
      inline: true,
      dimensions: [
        {
          height: 500,
          width: 300,
        },
        {
          height: 720,
          width: 1280,
        },
      ],
    });
    ```

    This configuration tells `critical` to:
    *   Look for the source HTML file in the `public` directory.
    *   Use `index.html` as both the source and destination (since `inline` is `true`).
    *   Generate critical CSS for two different viewport sizes: a small mobile screen and a standard laptop screen.

## Manual Extraction Methods

For those who prefer a more hands-on approach or need to debug the results of automated tools, it is possible to extract critical CSS manually. This process provides a deeper understanding of which styles are being applied to the page.

### Using the Chrome DevTools Coverage Panel

Chrome DevTools includes a powerful **Coverage** panel that can help identify unused CSS. While it doesn't automatically extract the critical CSS, it shows which lines of code are used and unused, making it an invaluable tool for manual extraction [5].

Here’s how to use it:

1.  Open Chrome DevTools (`F12` or `Ctrl+Shift+J`).
2.  Open the Command Menu (`Ctrl+Shift+P`).
3.  Type "coverage" and select "Show Coverage."
4.  Click the reload button in the Coverage panel to start recording.
5.  After the page loads, the panel will display a report showing the percentage of used and unused code for each CSS file.
6.  Click on a CSS file to view it in the Sources panel, where used lines are marked in green and unused lines are marked in red.

By analyzing this report, you can manually copy the used CSS rules into a separate file to create your critical CSS.

### JavaScript-Based Detection

Web pioneer Paul Kinlan developed a JavaScript-based approach that can be run as a bookmarklet or DevTools snippet to detect critical CSS. The script works as follows [6]:

1.  It iterates through every element in the DOM.
2.  It identifies elements that are visible within the current viewport.
3.  It uses `window.getMatchedCSSRules(node)` to get the styles applied to each visible element.
4.  It aggregates these styles to create the critical CSS.

This method is clever but has limitations. It only works in WebKit-based browsers (Chrome, Safari), doesn't account for media queries beyond the current viewport, and won't find styles for pseudo-elements like `:hover`.

## Conclusion

Extracting and inlining critical CSS is a powerful optimization technique that can dramatically improve the perceived performance of your website. While automated tools like `critical` provide an efficient and reliable solution for most use cases, understanding the manual extraction process using tools like the Chrome DevTools Coverage panel can provide deeper insights and greater control. By prioritizing the delivery of above-the-fold content, you can create a faster, more engaging experience for your users.

## References

[1] [web.dev: Extract critical CSS](https://web.dev/articles/extract-critical-css)
[2] [NitroPack: Critical CSS: How to Boost Your Website's Speed and UX](https://nitropack.io/blog/post/critical-css)
[3] [GitHub: addyosmani/critical](https://github.com/addyosmani/critical)
[4] [GitHub: addyosmani/critical-path-css-tools](https://github.com/addyosmani/critical-path-css-tools)
[5] [Chrome for Developers: Coverage: Find unused JavaScript and CSS](https://developer.chrome.com/docs/devtools/coverage)
[6] [Paul Kinlan: Detecting critical above-the-fold CSS](https://paul.kinlan.me/detecting-critical-above-the-fold-css/)
