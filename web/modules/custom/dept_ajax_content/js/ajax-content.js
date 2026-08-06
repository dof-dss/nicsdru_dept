(function (Drupal, once) {
  'use strict';

  const BLOCKED_TAGS = ['script', 'style', 'iframe', 'object', 'embed', 'base', 'form'];

  /**
   * Strips executable content from an HTML string.
   *
   * Parses the value with DOMParser (sandboxed from the live document), removes
   * dangerous elements, and strips event-handler and javascript: attributes
   * before returning the sanitised inner HTML.
   *
   * @param {string} html - Raw HTML string from the API.
   * @return {string} - Sanitised HTML safe for insertion via innerHTML.
   */
  function sanitise(html) {
    const doc = new DOMParser().parseFromString(html, 'text/html');

    BLOCKED_TAGS.forEach((tag) => {
      doc.querySelectorAll(tag).forEach((el) => el.remove());
    });

    doc.querySelectorAll('*').forEach((el) => {
      Array.from(el.attributes).forEach((attr) => {
        // Remove all event handlers (onclick, onload, onerror, etc.).
        if (/^on/i.test(attr.name)) {
          el.removeAttribute(attr.name);
          return;
        }
        // Remove javascript: URIs from attributes that accept URLs.
        if (['href', 'src', 'action', 'formaction', 'data'].includes(attr.name.toLowerCase())) {
          if (/^\s*javascript:/i.test(attr.value)) {
            el.removeAttribute(attr.name);
          }
        }
      });
    });

    return doc.body.innerHTML;
  }

  Drupal.behaviors.ajaxContent = {
    attach(context) {
      once('dept-ajax-content', '.ajax-content-block', context).forEach((block) => {
        const url = block.dataset.ajaxUrl;
        const count = parseInt(block.dataset.count, 10) || 3;
        const viewAllUrl = block.dataset.viewAllUrl || null;

        if (!url) {
          return;
        }

        fetch(url, {
          headers: { Accept: 'application/json' },
          // no-cache revalidates with Fastly on every request but allows
          // Fastly to serve from its cache (304) without hitting origin.
          // Omitting Cache-Control/Pragma request headers avoids bypassing
          // the CDN cache.
          cache: 'no-cache',
        })
          .then((response) => {
            if (!response.ok) {
              throw new Error(`HTTP ${response.status}`);
            }
            return response.json();
          })
          .then((items) => {
            if (!Array.isArray(items) || items.length === 0) {
              return;
            }

            const list = document.createElement('ul');
            list.className = 'ajax-content-list';

            items.slice(0, count).forEach((item) => {
              const li = document.createElement('li');
              li.className = 'ajax-content-list__item';

              Object.values(item).forEach((value) => {
                li.innerHTML += sanitise(String(value));
              });

              list.appendChild(li);
            });

            block.appendChild(list);

            if (viewAllUrl) {
              const more = document.createElement('p');
              more.className = 'ajax-content-list__more';
              const link = document.createElement('a');
              link.href = viewAllUrl;
              link.textContent = Drupal.t('More...');
              more.appendChild(link);
              block.appendChild(more);
            }
          })
          .catch((error) => {
            console.error(`Ajax Content: failed to fetch ${url}`, error);
          });
      });
    },
  };
}(Drupal, once));
