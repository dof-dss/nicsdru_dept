(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.ajaxContent = {
    attach(context) {
      once('dept-ajax-content', '.ajax-content-block', context).forEach((block) => {
        const url = block.dataset.ajaxUrl;
        const count = parseInt(block.dataset.count, 10) || 3;
        const viewAllUrl = block.dataset.viewAllUrl || null;

        if (!url) {
          return;
        }

        fetch(url, { headers: { Accept: 'application/json', 'Cache-Control': 'no-cache', Pragma: 'no-cache' }, cache: 'no-store' })
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
                li.innerHTML += value;
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
