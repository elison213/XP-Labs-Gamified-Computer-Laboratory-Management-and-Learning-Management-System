/**
 * UI behaviors (reveal on scroll). REST integration:
 * - window.XPLabs.config / XPLabs.api (see config.js, api.js)
 * - document.body[data-page] — current screen id for your router
 * - [data-api-list="/path"] — suggested GET target for lists
 * - [data-api-endpoint] on forms — suggested POST/PATCH target
 */
(function () {
  'use strict';

  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    document.querySelectorAll('.reveal, .reveal-stagger').forEach(function (el) {
      el.classList.add('is-visible');
    });
    return;
  }

  var revealables = document.querySelectorAll('.reveal, .reveal-stagger');
  if (!revealables.length || !('IntersectionObserver' in window)) {
    revealables.forEach(function (el) {
      el.classList.add('is-visible');
    });
    return;
  }

  var io = new IntersectionObserver(
    function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-visible');
        io.unobserve(entry.target);
      });
    },
    { root: null, rootMargin: '0px', threshold: 0 }
  );

  revealables.forEach(function (el) {
    io.observe(el);
  });
})();
