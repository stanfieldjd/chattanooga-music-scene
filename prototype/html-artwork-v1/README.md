# Chattanooga Music Scene HTML artwork prototype

`index.html` is the authoritative prototype source. It constructs a 1920 × 1080 desktop composition from independently addressable HTML/CSS layers rather than using a flattened full-page mockup.

- The custom `SceneHeadline` display font is embedded directly in `index.html`.
- The river background and performer are replaceable image layers loaded from the Chattanooga Music Scene WordPress media library.
- Event dates, counts, headings, labels, navigation, calls to action, and editorial copy remain editable HTML.
- `layer-manifest.json` records the 14-layer reconstruction order and each layer's implementation type.

Status: prototype source preserved for further visual refinement. This branch does not change the repository's `main` branch, the WordPress site, or the live website.
