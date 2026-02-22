/**
 * Client-side Graphviz rendering via viz-js (WASM)
 *
 * Browser fallback for rendering .dot graphs to SVG without a server binary.
 * Uses @viz-js/viz which bundles Graphviz compiled to WebAssembly.
 *
 * Usage:
 *   <script type="module" src="vendor/restruct/dot-static/client/dot-client.js"></script>
 *
 *   // Render a single element
 *   DotClient.render(element)
 *
 *   // Render all elements with [data-dot-graph] attribute
 *   DotClient.renderAll()
 *
 * HTML usage:
 *   <div data-dot-graph="digraph { a -> b }"></div>
 *   <script data-dot-graph type="text/dot">digraph { a -> b }</script>
 *
 * @see https://github.com/mdaines/viz-js
 */

const VIZ_CDN = "https://cdn.jsdelivr.net/npm/@viz-js/viz@3/+esm";

let vizInstance = null;

async function getInstance() {
    if (!vizInstance) {
        const Viz = await import(VIZ_CDN);
        vizInstance = await Viz.instance();
    }
    return vizInstance;
}

/**
 * Get the dot source from an element.
 * Supports data-dot-graph attribute (inline or value) and script tag content.
 */
function getDotSource(el) {
    const attrValue = el.getAttribute("data-dot-graph");
    // Attribute value contains the dot source directly
    if (attrValue && attrValue !== "" && attrValue !== "true") {
        return attrValue;
    }
    // Script tag or element with dot source as text content
    return el.textContent.trim();
}

const DotClient = {
    /**
     * Render a single element's dot source to SVG.
     * Replaces the element content with the rendered SVG.
     */
    async render(el, options = {}) {
        const dotSource = getDotSource(el);
        if (!dotSource) return;

        try {
            const viz = await getInstance();
            const svg = viz.renderSVGElement(dotSource, {
                engine: options.engine || "dot",
            });

            // Replace element content with rendered SVG
            if (el.tagName === "SCRIPT") {
                // Script tags can't display content — insert SVG after the script
                el.parentNode.insertBefore(svg, el.nextSibling);
            } else {
                el.innerHTML = "";
                el.appendChild(svg);
            }
        } catch (err) {
            console.error("[dot-client] Render failed:", err.message);
            el.classList.add("dot-render-error");
        }
    },

    /**
     * Render all elements with [data-dot-graph] attribute on the page.
     */
    async renderAll(options = {}) {
        const elements = document.querySelectorAll("[data-dot-graph]");
        await Promise.all(
            Array.from(elements).map((el) => this.render(el, options))
        );
    },

    /**
     * Render a dot string and return the SVG element.
     */
    async renderString(dotSource, options = {}) {
        const viz = await getInstance();
        return viz.renderSVGElement(dotSource, {
            engine: options.engine || "dot",
        });
    },

    /**
     * Check if viz-js WASM can be loaded.
     */
    async isAvailable() {
        try {
            await getInstance();
            return true;
        } catch {
            return false;
        }
    },
};

// Auto-render on DOMContentLoaded
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => DotClient.renderAll());
} else {
    DotClient.renderAll();
}

// Export for programmatic use
window.DotClient = DotClient;
export default DotClient;
