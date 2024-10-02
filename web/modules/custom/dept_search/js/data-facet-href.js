(function () {
  document.addEventListener('DOMContentLoaded', function() {
    const links = document.querySelectorAll('a[data-facet-href]');
    links.forEach(function(link) {
      const dataHref = link.getAttribute('data-facet-href');
      if (dataHref) {
        link.setAttribute('href', dataHref);
      }
    });
  });
})();
