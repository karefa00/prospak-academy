# Phase 1 Hope UI component integration

User-provided asset source: `hope-ui-v5.0.0.zip` (Hope UI v5.0.0 distribution).

Selected original compiled assets placed under `public/assets/`:

- `html/assets/css/core/libs.min.css` → `css/libs.min.css`
- `html/assets/css/hope-ui.min.css` → `css/hope-ui.min.css`
- `html/assets/css/custom.min.css` → `css/custom.min.css`
- `html/assets/js/core/libs.min.js` → `js/libs.min.js`
- `html/assets/js/hope-ui.js` → `js/hope-ui.js`
- `html/assets/images/auth/01.png` → `images/auth-01.png`
- `html/assets/images/favicon.ico` → `images/favicon.ico`

The authentication form structure is adapted from `html/dashboard/auth/sign-in.html` and `html/dashboard/auth/recoverpw.html`. Reusable components are informed by the Handlebars source partials in `gulp+scss+hbs/src/templates/partials/dashboard/`.

Changes: CSS/JS asset paths are application-relative; no default Hope UI account or external social sign-in is shown; password recovery and login submit to protected PHP routes; dashboard menus and account details are server-rendered. `app.css` and `reset-token.js` are new original code for this application. The bundled demonstration charts, sample tables, and unrelated plugins are not presented as academic data.

The supplied ZIP did not include a top-level license file during Phase 0 inspection. Before any public redistribution or commercial sublicensing of assets, independently confirm the actual applicable Hope UI license and attribution requirements.
