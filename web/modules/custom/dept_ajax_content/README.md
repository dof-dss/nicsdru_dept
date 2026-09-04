# Department Ajax Content

Provides a configurable block that fetches content from a JSON API endpoint via
client-side AJAX and renders it on the page. Because the fetch happens in the
browser after the page has loaded, the page itself can be served from the full
page cache even when the underlying content changes frequently.

## Enabling the module

```bash
drush en dept_ajax_content -y
drush cache:rebuild
```

## Placing a block

1. Go to **Structure > Block layout** and click **Place block** in the region
   you want the content to appear in.
2. Search for **Ajax Content** and click **Place block**.
3. Fill in the block configuration:
   - **API URL** — the JSON endpoint to fetch. Either a fully-qualified URL
     (e.g. `https://apisource.com/api/some/data`) or a root-relative path
     (e.g. `/api/news/latest`). A root-relative path is automatically prefixed
     with the current department's canonical domain.
   - **Number of items to display** — how many items from the response to
     render (default: 3), dependant on how many results the endpoint returns.
   - **"More" link URL** — optional URL rendered as a *More...* link beneath
     the list. Leave empty to hide it.
4. Save the block. Clear caches if the block does not appear immediately.

## How content is rendered

The block renders each item in the JSON array as a `<li>` element. The raw
HTML value of every key in the item object is written directly into the list
item in the order the API returns the fields. No additional markup or class
names are added by the module — apply styling via the theme.

## Creating a compatible Views REST API endpoint

The module expects the endpoint to return a JSON array of objects. When building
the endpoint with Views:

1. Create or edit an existing View and choose **REST export** as the display type.
2. Set the **Path** to the URL you will enter in the block config (e.g.
   `/api/news/latest`).
3. Set **Format** to **Serializer** with **json** selected.
4. Add the fields you want to expose (e.g. Title, Date published).
5. **For each field, enable output markup via Rewrite results:**
   - Click the field label to open its settings.
   - Expand **Rewrite results**.
   - Check **Output this field as custom markup**.
   - In the text area, enter the HTML you want the block to render for that
     field — for example a link using Views token replacement:
     ```
     <a href="/node/{{ nid }}">{{ title }}</a>
     ```
   - This is necessary because the block writes field values as raw HTML. If
     you skip this step, plain text values are written with no markup, and
     fields such as dates or titles will not be linked or formatted.
   - Ensure proper precautions have been taken to ensure that content returned is properly sanitised and not a potential security threat.
6. Under **Pager**, set the item limit to at least the **Number of items**
   value configured in the block.
7. Save the view and verify the endpoint returns the expected JSON at its path.
